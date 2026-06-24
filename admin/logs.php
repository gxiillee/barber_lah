<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$error = '';
$passEsperada = $_ENV['LOG_VIEWER_PASS'] ?? '';

if ($passEsperada === '') {
    $error = 'LOG_VIEWER_PASS no está definido en .env';
}

if (session_status() === PHP_SESSION_NONE) session_start();

// Logout
if (!empty($_GET['logout'])) {
    unset($_SESSION['logs_auth']);
    session_destroy();
    echo '<script>window.location.href = window.location.pathname;</script>';
    exit;
}

$autenticado = !empty($_SESSION['logs_auth']);
if (!$autenticado && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $autenticado = hash_equals($passEsperada, $_POST['password']);
    if ($autenticado) {
        $_SESSION['logs_auth'] = true;
        session_regenerate_id();
    } else {
        $error = 'Contraseña incorrecta.';
    }
}

$logFile = __DIR__ . '/../logs/app.log';

// ─── Cargar y parsear líneas ───
$lineas = [];
$totalWarnings = 0;
$totalErrors = 0;
$totalNotices = 0;
$totalOther = 0;

if ($autenticado && file_exists($logFile)) {
    $raw = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($raw)) $raw = [];

    foreach ($raw as $linea) {
        $tipo = 'other';
        $color = '#888';
        if (preg_match('/^\[([^\]]+)\]/', $linea, $m)) {
            $fechaLog = $m[1];
        } else {
            $fechaLog = '';
        }

        if (preg_match('/PHP Fatal|PHP Catchable|Uncaught|Fatal error/i', $linea)) {
            $tipo = 'error'; $color = '#ef4444'; $totalErrors++;
        } elseif (preg_match('/PHP Warning|Warning:/i', $linea)) {
            $tipo = 'warning'; $color = '#f59e0b'; $totalWarnings++;
        } elseif (preg_match('/PHP Notice|Notice:/i', $linea) && !preg_match('/PHP Warning/i', $linea)) {
            $tipo = 'notice'; $color = '#3b82f6'; $totalNotices++;
        } else {
            $totalOther++;
        }

        $lineas[] = [
            'raw' => $linea,
            'fecha' => $fechaLog,
            'tipo' => $tipo,
            'color' => $color,
        ];
    }
}

// ─── Filtros ───
$filtroFecha = $_POST['fecha'] ?? 'all';
$filtroTipo = $_POST['tipo'] ?? 'all';
$busqueda = $_POST['busqueda'] ?? '';

if ($autenticado && !empty($lineas)) {
    $lineasFiltradas = $lineas;

    // Filtro por fecha
    if ($filtroFecha === 'today') {
        $hoy = date('d-M-Y');
        $lineasFiltradas = array_filter($lineasFiltradas, fn($l) => str_starts_with($l['fecha'], $hoy));
    } elseif ($filtroFecha === 'week') {
        $semanaInicio = strtotime('-7 days');
        $lineasFiltradas = array_filter($lineasFiltradas, function($l) use ($semanaInicio) {
            if (empty($l['fecha'])) return true;
            $ts = strtotime(substr($l['fecha'], 0, 11));
            return $ts !== false && $ts >= $semanaInicio;
        });
    }

    // Filtro por tipo
    if ($filtroTipo !== 'all') {
        $lineasFiltradas = array_filter($lineasFiltradas, fn($l) => $l['tipo'] === $filtroTipo);
    }

    // Búsqueda
    if ($busqueda !== '') {
        $lineasFiltradas = array_filter($lineasFiltradas, fn($l) => mb_stripos($l['raw'], $busqueda) !== false);
    }

    // Últimas 200
    $lineasFiltradas = array_slice(array_values($lineasFiltradas), -200);
} else {
    $lineasFiltradas = [];
}

// ─── Limpiar log ───
if ($autenticado && !empty($_POST['clear'])) {
    file_put_contents($logFile, '');
    echo '<script>window.location.href = window.location.pathname;</script>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background: #0a0a0a; color: #e0e0e0; font-family: system-ui, sans-serif; margin: 0; padding: 20px; display: flex; justify-content: center; }
        .card { background: #141414; border: 1px solid #222; border-radius: 12px; padding: 28px; width: 100%; max-width: 1100px; }
        input, select { background: #0d0d0d; border: 1px solid #333; border-radius: 8px; padding: 8px 12px; color: #e0e0e0; outline: none; font-size: 13px; }
        input:focus, select:focus { border-color: #d4af37; }
        button, .btn { background: #d4af37; color: #0a0a0a; border: none; border-radius: 8px; padding: 8px 18px; font-weight: 600; font-size: 12px; cursor: pointer; }
        button:hover, .btn:hover { opacity: 0.9; }
        .btn-outline { background: transparent; border: 1px solid #333; color: #999; }
        .btn-outline:hover { border-color: #d4af37; color: #d4af37; }
        .btn-danger { background: transparent; border: 1px solid #7f1d1d; color: #ef4444; font-size: 11px; padding: 6px 14px; }
        .btn-danger:hover { background: #7f1d1d33; }
        .error-msg { color: #ef4444; font-size: 13px; }
        /* Log lines */
        .log-line { display: flex; gap: 8px; padding: 4px 0; border-bottom: 1px solid #1a1a1a; font-family: 'Cascadia Code', 'Fira Code', monospace; font-size: 11.5px; line-height: 1.5; }
        .log-line:hover { background: #1a1a1a; }
        .log-icon { width: 18px; text-align: center; flex-shrink: 0; font-size: 11px; }
        .log-time { color: #555; white-space: nowrap; flex-shrink: 0; min-width: 130px; }
        .log-msg { word-break: break-all; }
        .badge { display: inline-block; font-size: 10px; padding: 2px 8px; border-radius: 999px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .stat { background: #0d0d0d; border: 1px solid #222; border-radius: 10px; padding: 10px 16px; text-align: center; min-width: 80px; }
        .stat-num { font-size: 20px; font-weight: 700; }
        .stat-label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 2px; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
        .filters form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 0; }
        .empty { text-align: center; padding: 40px 0; color: #555; font-size: 13px; }
        @media (max-width: 640px) { .log-time { min-width: 80px; font-size: 10px; } .log-line { font-size: 10px; } .stats { gap: 6px; } .stat { min-width: 60px; padding: 6px 10px; } .stat-num { font-size: 16px; } }
    </style>
</head>
<body>
<div class="card">

<?php if (!$autenticado): ?>
    <h1 style="font-size:18px;margin:0 0 4px 0;color:#d4af37;">📋 Logs de la aplicación</h1>
    <p style="font-size:12px;color:#666;margin:0 0 20px 0;">Introduce la contraseña para ver los errores</p>
    <form method="POST" style="display:flex;gap:10px;align-items:center;">
        <input type="password" name="password" placeholder="Contraseña" required autofocus style="max-width:250px;">
        <button type="submit">Ver logs</button>
    </form>
    <?php if ($error): ?><p class="error-msg"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php else: ?>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
        <div>
            <h1 style="font-size:18px;margin:0;color:#d4af37;">📋 Logs</h1>
            <p style="font-size:11px;color:#555;margin:2px 0 0 0;">
                <?= file_exists($logFile) ? round(filesize($logFile) / 1024, 1) . ' KB · ' . count($lineas) . ' líneas totales' : '' ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;">
                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Vaciar todo el archivo de log?');">
                <button type="submit" name="clear" value="1" class="btn-danger"><i class="bi bi-trash3"></i> Vaciar log</button>
            </form>
            <a href="?logout=1" style="color:#666;font-size:12px;text-decoration:none;padding:6px 12px;border:1px solid #333;border-radius:8px;">Salir</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat" style="border-color:#ef444433;"><div class="stat-num" style="color:#ef4444;"><?= $totalErrors ?></div><div class="stat-label">Errores</div></div>
        <div class="stat" style="border-color:#f59e0b33;"><div class="stat-num" style="color:#f59e0b;"><?= $totalWarnings ?></div><div class="stat-label">Warnings</div></div>
        <div class="stat" style="border-color:#3b82f633;"><div class="stat-num" style="color:#3b82f6;"><?= $totalNotices ?></div><div class="stat-label">Avisos</div></div>
        <div class="stat"><div class="stat-num" style="color:#888;"><?= $totalOther ?></div><div class="stat-label">Otros</div></div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <form method="POST">

            <select name="fecha">
                <option value="all" <?= $filtroFecha === 'all' ? 'selected' : '' ?>>Todas las fechas</option>
                <option value="today" <?= $filtroFecha === 'today' ? 'selected' : '' ?>>Solo hoy</option>
                <option value="week" <?= $filtroFecha === 'week' ? 'selected' : '' ?>>Últimos 7 días</option>
            </select>

            <select name="tipo">
                <option value="all" <?= $filtroTipo === 'all' ? 'selected' : '' ?>>Todos los tipos</option>
                <option value="error" <?= $filtroTipo === 'error' ? 'selected' : '' ?>>Solo errores</option>
                <option value="warning" <?= $filtroTipo === 'warning' ? 'selected' : '' ?>>Solo warnings</option>
                <option value="notice" <?= $filtroTipo === 'notice' ? 'selected' : '' ?>>Solo avisos</option>
            </select>

            <input type="text" name="busqueda" placeholder="Buscar..." value="<?= htmlspecialchars($busqueda) ?>" style="min-width:140px;">

            <button type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
        </form>
    </div>

    <!-- Log lines -->
    <?php if (empty($lineasFiltradas)): ?>
        <div class="empty">
            <i class="bi bi-check-circle" style="font-size:24px;color:#22c55e;display:block;margin-bottom:8px;"></i>
            No hay líneas de log que coincidan con los filtros.
        </div>
    <?php else: ?>
        <div style="background:#0d0d0d;border:1px solid #222;border-radius:8px;padding:8px 12px;max-height:70vh;overflow-y:auto;">
            <?php foreach (array_reverse($lineasFiltradas) as $l): ?>
                <div class="log-line">
                    <span class="log-icon" style="color:<?= $l['color'] ?>;">
                        <?php if ($l['tipo'] === 'error'): ?><i class="bi bi-x-circle-fill"></i>
                        <?php elseif ($l['tipo'] === 'warning'): ?><i class="bi bi-exclamation-triangle-fill"></i>
                        <?php elseif ($l['tipo'] === 'notice'): ?><i class="bi bi-info-circle-fill"></i>
                        <?php else: ?><i class="bi bi-record-circle"></i>
                        <?php endif; ?>
                    </span>
                    <span class="log-time"><?= htmlspecialchars($l['fecha']) ?></span>
                    <span class="log-msg" style="color:<?= $l['color'] ?>;"><?= htmlspecialchars($l['raw']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:#444;margin-top:8px;text-align:right;">Mostrando las últimas <?= count($lineasFiltradas) ?> líneas (más recientes primero)</p>
    <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
