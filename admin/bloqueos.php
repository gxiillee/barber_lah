<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Bloqueo.php';
require_once __DIR__ . '/../clases/Reserva.php';
require_once __DIR__ . '/../clases/Cliente.php';
require_once __DIR__ . '/../clases/NotificadorReserva.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Csrf.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

const ID_BARBERO = 1;

$mensaje = '';
$error = '';

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        if (!Csrf::validarToken('bloqueos', $_POST['csrf_token'] ?? '')) {
            $error = 'Token de seguridad inválido. Recarga la página.';
        } else {
            $fecha      = $_POST['fecha'] ?? '';
            $tipo       = $_POST['tipo'] ?? 'completo';
            $motivo     = trim($_POST['motivo'] ?? '');
            $horaInicio = ($tipo === 'horas') ? ($_POST['hora_inicio'] ?: null) : null;
            $horaFin    = ($tipo === 'horas') ? ($_POST['hora_fin'] ?: null) : null;

            if (empty($fecha)) {
                $error = "Introduce una fecha válida.";
            } elseif (empty($motivo)) {
                $error = "El motivo es obligatorio para registrar el bloqueo.";
            } elseif ($tipo === 'horas' && $horaInicio !== null && $horaFin !== null && $horaInicio >= $horaFin) {
                $error = "La hora de inicio debe ser anterior a la hora de fin.";
            } else {
                // ── Find overlapping confirmada reservations ──
                $conexion = BD::obtenerConexion();
                $afectadas = [];

                if ($horaInicio === null) {
                    // Full day — all confirmada reservations on that date
                    $stmt = $conexion->prepare("
                        SELECT r.id, r.hora, r.id_cliente, r.id_servicio, r.duracion_historica, s.nombre AS servicio_nombre,
                               u.nombre AS u_nombre, u.email AS u_email
                        FROM reservas r
                        JOIN servicios s ON r.id_servicio = s.id
                        JOIN usuarios u ON r.id_cliente = u.id AND u.activo = 1
                        WHERE r.id_barbero = :barbero AND r.fecha = :fecha AND r.estado = 'confirmada'
                        ORDER BY r.hora
                    ");
                    $stmt->execute([':barbero' => ID_BARBERO, ':fecha' => $fecha]);
                    $afectadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Time range — overlapping reservations
                    $stmt = $conexion->prepare("
                        SELECT r.id, r.hora, r.id_cliente, r.id_servicio, r.duracion_historica, s.nombre AS servicio_nombre,
                               u.nombre AS u_nombre, u.email AS u_email
                        FROM reservas r
                        JOIN servicios s ON r.id_servicio = s.id
                        JOIN usuarios u ON r.id_cliente = u.id AND u.activo = 1
                        WHERE r.id_barbero = :barbero
                          AND r.fecha = :fecha
                          AND r.estado = 'confirmada'
                          AND r.hora < :hora_fin
                          AND r.hora + INTERVAL r.duracion_historica MINUTE > :hora_inicio
                        ORDER BY r.hora
                    ");
                    $stmt->execute([
                        ':barbero'    => ID_BARBERO,
                        ':fecha'      => $fecha,
                        ':hora_inicio'=> $horaInicio,
                        ':hora_fin'   => $horaFin,
                    ]);
                    $afectadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                // ── Cancel overlapping reservations & notify ──
                $canceladas = 0;
                $conexion->beginTransaction();
                try {
                    foreach ($afectadas as $r) {
                        if (Reserva::cancelar((int)$r['id'], $motivo, $conexion)) {
                            $canceladas++;
                            $cliente = new Usuario(
                                (int)$r['id_cliente'], null,
                                $r['u_nombre'], $r['u_email'],
                                null, null, null, 0, 'cliente'
                            );
                            NotificadorReserva::enviarCancelacion($cliente, [
                                'servicio' => $r['servicio_nombre'] ?? '',
                                'fecha'    => fechaHumana($fecha),
                                'hora'     => $r['hora'],
                            ], $motivo);
                        }
                    }

                    // ── Create bloqueo ──
                    if (Bloqueo::crear(ID_BARBERO, $fecha, $horaInicio, $horaFin, $motivo, $conexion)) {
                        $conexion->commit();
                        $msg = 'Bloqueo guardado correctamente.';
                        if ($canceladas > 0) {
                            $msg .= " Se cancelaron $canceladas cita(s) afectadas y se notificó a los clientes.";
                        }
                        $_SESSION['toast'] = ['type' => 'success', 'message' => $msg];
                        redirigir('bloqueos.php');
                    } else {
                        $conexion->rollBack();
                        $error = "Error al registrar el bloqueo. No se modificó ninguna cita.";
                    }
                } catch (Exception $e) {
                    $conexion->rollBack();
                    $error = "Error del servidor. No se modificó ninguna cita.";
                    error_log("bloqueos.php: " . $e->getMessage());
                }
            }
        }
    }

    if ($accion === 'eliminar') {
        if (!Csrf::validarToken('bloqueos_eliminar', $_POST['csrf_token'] ?? '')) {
            $error = 'Token de seguridad inválido. Recarga la página.';
        } else {
            $idEliminar = (int)($_POST['id'] ?? 0);
            if ($idEliminar > 0 && Bloqueo::eliminar($idEliminar)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Horario liberado.'];
                redirigir('bloqueos.php');
            } else {
                $error = "No se pudo eliminar la restricción.";
            }
        }
    }
}

$token_csrf = Csrf::generarToken('bloqueos');
$token_csrf_eliminar = Csrf::generarToken('bloqueos_eliminar');
$lista_bloqueos = Bloqueo::listarTodos();
$pagina_activa  = 'bloqueos';

// ── Contar bloqueos por tipo ──
$total_dias   = 0;
$total_tramos = 0;
foreach ($lista_bloqueos as $b) {
    if (empty($b['hora_inicio'])) $total_dias++;
    else $total_tramos++;
}

// Agrupar bloqueos: activos (hoy+future) y pasados
$bloqueos_activos  = [];
$bloqueos_pasados  = [];
$hoy_ts = strtotime('today');
foreach ($lista_bloqueos as $b) {
    if (strtotime($b['fecha']) < $hoy_ts) {
        $bloqueos_pasados[] = $b;
    } else {
        $bloqueos_activos[] = $b;
    }
}

$dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloqueos — Panel Admin · Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
    <style>
        .mobile-collapse { max-height: 0; overflow: hidden; transition: max-height 0.5s cubic-bezier(0.4,0,0.2,1), opacity 0.35s ease; opacity: 0.4; }
        .mobile-collapse.expanded { max-height: 2000px; opacity: 1; }
        @media (min-width: 1024px) { .mobile-collapse { max-height: none !important; overflow: visible !important; opacity: 1 !important; } }
    </style>
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none pagina-entrada">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Bloqueos</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Días o tramos donde no se aceptarán reservas</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] font-medium flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Mini stats con más color -->
    <div class="flex flex-wrap gap-2 mb-7">
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.6rem] font-semibold bg-gradient-to-r from-purple-500/15 to-purple-500/5 border border-purple-500/25 text-purple-300 shadow-xs">
            <i class="bi bi-calendar-x text-[0.7rem]"></i> <?= $total_dias ?> día<?= $total_dias !== 1 ? 's' : '' ?> completo<?= $total_dias !== 1 ? 's' : '' ?>
        </span>
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.6rem] font-semibold bg-gradient-to-r from-amber-500/15 to-amber-500/5 border border-amber-500/25 text-amber-300 shadow-xs">
            <i class="bi bi-clock text-[0.7rem]"></i> <?= $total_tramos ?> tramo<?= $total_tramos !== 1 ? 's' : '' ?>
        </span>
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.6rem] font-semibold bg-gradient-to-r from-white/5 to-white/0 border border-white/10 text-[var(--tx-m)] shadow-xs">
            <i class="bi bi-list text-[0.7rem]"></i> <?= count($lista_bloqueos) ?> total
        </span>
        <?php if (count($bloqueos_activos) > 0): ?>
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.6rem] font-semibold bg-gradient-to-r from-emerald-500/15 to-emerald-500/5 border border-emerald-500/25 text-emerald-300 shadow-xs">
            <i class="bi bi-shield-check text-[0.7rem]"></i> <?= count($bloqueos_activos) ?> activo<?= count($bloqueos_activos) !== 1 ? 's' : '' ?>
        </span>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ── Formulario ── -->
        <section class="lg:col-span-4 rounded-xl border border-[var(--brd)] bg-gradient-to-b from-white/[0.04] to-white/[0.01] p-5 glow-card">
            <h2 class="text-[0.85rem] font-semibold text-[var(--tx)] mb-4 flex items-center gap-2" style="font-family: var(--pf);">
                <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-[var(--gold-dim)] to-transparent border border-[var(--gold-brd)] flex items-center justify-center text-[var(--gold)] text-[0.7rem]">
                    <i class="bi bi-shield-plus"></i>
                </span>
                Nueva restricción
                <button type="button" onclick="toggleForm(this)" class="lg:hidden ml-auto flex items-center gap-1.5 text-[0.6rem] text-[var(--gold)] cursor-pointer transition-all hover:opacity-80">
                    <i class="bi bi-plus-circle text-[0.7rem]"></i>
                    <span>Mostrar</span>
                </button>
            </h2>

            <div class="mobile-collapse">
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
                <input type="hidden" name="accion" value="crear">

                <div>
                    <label class="block text-[0.62rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1.5">Fecha</label>
                    <div class="flex gap-2">
                        <input type="date" name="fecha" id="fechaBloqueo" required min="<?= date('Y-m-d') ?>"
                               class="flex-1 bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] focus:ring-1 focus:ring-[var(--gold-dim)] transition-all">
                    </div>
                    <div class="flex gap-1.5 mt-1.5">
                        <button type="button" onclick="ponerFecha('today')" class="px-2 py-1 rounded-md text-[0.55rem] font-medium border border-[var(--brd)] text-[var(--tx-d)] bg-white/[0.03] hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer">
                            <i class="bi bi-dot"></i> Hoy
                        </button>
                        <button type="button" onclick="ponerFecha('tomorrow')" class="px-2 py-1 rounded-md text-[0.55rem] font-medium border border-[var(--brd)] text-[var(--tx-d)] bg-white/[0.03] hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer">
                            Mañana
                        </button>
                        <button type="button" onclick="ponerFecha('thisweekend')" class="px-2 py-1 rounded-md text-[0.55rem] font-medium border border-[var(--brd)] text-[var(--tx-d)] bg-white/[0.03] hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer">
                            Fin de semana
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-[0.62rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1.5">Duración</label>
                    <div class="grid grid-cols-2 gap-2 bg-[#0d0d0d] p-1 rounded-lg border border-[var(--brd)]">
                        <label class="flex items-center justify-center gap-1.5 py-2 text-[0.65rem] font-medium rounded-md cursor-pointer transition-all has-[:checked]:bg-white/10 has-[:checked]:text-[var(--tx)] has-[:checked]:border-[var(--gold-brd)] has-[:checked]:shadow-xs text-[var(--tx-m)] border border-transparent">
                            <input type="radio" name="tipo" value="completo" checked onclick="toggleSeccionHoras(false)" class="sr-only">
                            <i class="bi bi-calendar-minus text-[0.7rem]"></i> Día completo
                        </label>
                        <label class="flex items-center justify-center gap-1.5 py-2 text-[0.65rem] font-medium rounded-md cursor-pointer transition-all has-[:checked]:bg-white/10 has-[:checked]:text-[var(--tx)] has-[:checked]:border-[var(--gold-brd)] has-[:checked]:shadow-xs text-[var(--tx-m)] border border-transparent">
                            <input type="radio" name="tipo" value="horas" onclick="toggleSeccionHoras(true)" class="sr-only">
                            <i class="bi bi-clock text-[0.7rem]"></i> Tramos
                        </label>
                    </div>
                </div>

                <div id="wrapper_horas" class="hidden grid grid-cols-2 gap-3 p-3 bg-[#0d0d0d] rounded-lg border border-[var(--brd)]">
                    <div>
                        <label class="block text-[0.58rem] text-[var(--tx-d)] font-medium mb-1">Desde</label>
                        <input type="time" name="hora_inicio"
                               class="w-full bg-[#1a1a1a] border border-[var(--brd)] rounded-md px-2 py-1.5 text-[0.75rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                    </div>
                    <div>
                        <label class="block text-[0.58rem] text-[var(--tx-d)] font-medium mb-1">Hasta</label>
                        <input type="time" name="hora_fin"
                               class="w-full bg-[#1a1a1a] border border-[var(--brd)] rounded-md px-2 py-1.5 text-[0.75rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                    </div>
                </div>

                <div>
                    <label class="block text-[0.62rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1.5">Motivo</label>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <?php $presets = ['Cita médica', 'Vacaciones', 'Asuntos propios', 'Festivo', 'Formación', 'Personal']; ?>
                        <?php foreach ($presets as $p): ?>
                            <button type="button" onclick="ponerMotivo('<?= h($p) ?>')"
                                    class="px-2.5 py-1 rounded-full text-[0.55rem] font-semibold tracking-wide border border-[var(--brd)] text-[var(--tx-m)] bg-white/[0.03] hover:bg-white/10 hover:border-white/20 active:bg-white/15 transition-all cursor-pointer">
                                <?= h($p) ?>
                            </button>
                        <?php endforeach; ?>
                        <button type="button" onclick="ponerMotivo('')"
                                class="px-2 py-1 rounded-full text-[0.55rem] border border-[var(--brd)] text-[var(--tx-d)] hover:bg-white/5 transition-all cursor-pointer">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <input type="text" name="motivo" id="motivoInput" placeholder="O escribe uno personalizado..."
                           class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.75rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/50 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-[#d4af37] to-[#e8c84a] hover:opacity-90 text-[#0d0d0d] font-bold py-2.5 rounded-lg text-[0.68rem] tracking-[0.15em] uppercase transition-all mt-2 cursor-pointer shadow-lg shadow-[#d4af37]/10">
                    <i class="bi bi-shield-plus text-[0.75rem] mr-1.5"></i> Bloquear
                </button>
            </form>
            </div><!-- /mobile-collapse -->
        </section>

        <!-- ── Lista ── -->
        <section class="lg:col-span-8 space-y-2">

            <?php if (empty($lista_bloqueos)): ?>
                <div class="flex flex-col items-center justify-center py-20 border border-dashed border-[var(--brd)] bg-white/[0.02] rounded-xl text-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500/10 to-amber-500/5 border border-[var(--brd)] flex items-center justify-center">
                        <i class="bi bi-unlock text-[1.6rem] text-[var(--tx-d)]"></i>
                    </div>
                    <div>
                        <p class="text-[0.85rem] text-[var(--tx-m)] font-medium" style="font-family: var(--pf);">Sin restricciones</p>
                        <p class="text-[0.65rem] text-[var(--tx-d)] mt-0.5">Todos los días están disponibles para reservar</p>
                    </div>
                </div>
            <?php else: ?>

                <!-- Activos -->
                <?php if (count($bloqueos_activos) > 0): ?>
                <div class="mb-1">
                    <span class="text-[0.6rem] uppercase tracking-[0.15em] font-bold text-emerald-400/70 flex items-center gap-1.5 px-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Activos (<?= count($bloqueos_activos) ?>)
                    </span>
                </div>
                <div class="flex flex-col gap-2 mb-6 stagger-container">
                    <?php foreach ($bloqueos_activos as $b): 
                        $completo = empty($b['hora_inicio']);
                        $fecha_ts = strtotime($b['fecha']);
                        $dia_semana = $dias_semana[(int)date('w', $fecha_ts)];
                    ?>
                        <div class="slot-card flex items-center justify-between gap-3 px-4 py-3.5 rounded-xl border transition-all duration-200 hover:-translate-y-0.5 <?= $completo ? 'border-purple-500/25 bg-gradient-to-r from-purple-500/8 to-purple-500/2 hover:border-purple-500/40 hover:shadow-lg hover:shadow-purple-500/5' : 'border-amber-500/20 bg-gradient-to-r from-amber-500/8 to-amber-500/2 hover:border-amber-500/35 hover:shadow-lg hover:shadow-amber-500/5' ?>">

                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <!-- Icon + hora -->
                                <div class="w-[52px] shrink-0 flex flex-col items-center">
                                    <?php if ($completo): ?>
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500/20 to-purple-500/5 border border-purple-500/20 flex items-center justify-center text-purple-400">
                                            <i class="bi bi-calendar-x text-[1rem]"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500/20 to-amber-500/5 border border-amber-500/20 flex items-center justify-center text-amber-400">
                                            <i class="bi bi-clock-history text-[1rem]"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[0.82rem] font-semibold text-[var(--tx)]"><?= date('d/m/Y', $fecha_ts) ?></span>
                                        <span class="text-[0.5rem] uppercase tracking-wider text-[var(--tx-d)] font-medium bg-white/[0.04] px-1.5 py-0.5 rounded border border-white/[0.06]"><?= h($dia_semana) ?></span>
                                        <?php if (!$completo): ?>
                                            <span class="text-[0.7rem] font-medium text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">
                                                <?= substr($b['hora_inicio'], 0, 5) ?> – <?= substr($b['hora_fin'], 0, 5) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[0.64rem] text-[var(--tx-d)] mt-1 flex items-center gap-1.5">
                                        <?php if (!empty($b['motivo'])): ?>
                                            <span class="text-[var(--tx-m)]"><?= h($b['motivo']) ?></span>
                                        <?php else: ?>
                                            <span class="italic text-[var(--tx-d)]/50">Sin motivo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <form action="" method="POST" onsubmit="return confirm('¿Deseas levantar este bloqueo?');" class="shrink-0">
                                <input type="hidden" name="csrf_token" value="<?= h($token_csrf_eliminar) ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit"
                                        class="w-8 h-8 rounded-lg border border-transparent text-[var(--tx-d)] flex items-center justify-center transition-all hover:bg-rose-500/15 hover:border-rose-500/25 hover:text-rose-400 cursor-pointer">
                                    <i class="bi bi-trash3 text-[0.8rem]"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Pasados -->
                <?php if (count($bloqueos_pasados) > 0): ?>
                <div class="flex items-center gap-3 px-1 mb-1">
                    <span class="h-px flex-1 bg-gradient-to-r from-transparent via-white/5 to-transparent"></span>
                    <span class="text-[0.55rem] uppercase tracking-[0.15em] font-bold text-[var(--tx-d)]/50 flex items-center gap-1.5 shrink-0">
                        <i class="bi bi-archive"></i> Pasados (<?= count($bloqueos_pasados) ?>)
                    </span>
                    <span class="h-px flex-1 bg-gradient-to-r from-transparent via-white/5 to-transparent"></span>
                </div>
                <div class="flex flex-col gap-1.5 opacity-50">
                    <?php foreach ($bloqueos_pasados as $b): 
                        $completo = empty($b['hora_inicio']);
                        $fecha_ts = strtotime($b['fecha']);
                    ?>
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl border border-[var(--brd)] bg-white/[0.02]">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <span class="text-[0.65rem] font-medium text-[var(--tx-d)] w-[36px] text-center shrink-0">
                                    <i class="bi bi-<?= $completo ? 'calendar-x' : 'clock' ?>"></i>
                                </span>
                                <span class="text-[0.7rem] text-[var(--tx-d)]"><?= date('d/m/Y', $fecha_ts) ?></span>
                                <?php if (!$completo): ?>
                                    <span class="text-[0.65rem] text-[var(--tx-d)]"><?= substr($b['hora_inicio'], 0, 5) ?>–<?= substr($b['hora_fin'], 0, 5) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($b['motivo'])): ?>
                                    <span class="text-[0.6rem] text-[var(--tx-d)]/60 italic"><?= h($b['motivo']) ?></span>
                                <?php endif; ?>
                            </div>
                            <form action="" method="POST" onsubmit="return confirm('¿Eliminar este bloqueo pasado?');">
                                <input type="hidden" name="csrf_token" value="<?= h($token_csrf_eliminar) ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="w-6 h-6 flex items-center justify-center text-[var(--tx-d)]/40 hover:text-rose-400/60 transition-all cursor-pointer">
                                    <i class="bi bi-x text-[0.7rem]"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </section>

    </div>
</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

<script>
function toggleForm(btn) {
    const wrapper = btn.closest('section').querySelector('.mobile-collapse');
    if (!wrapper) return;
    wrapper.classList.toggle('expanded');
    const open = wrapper.classList.contains('expanded');
    btn.querySelector('span').textContent = open ? 'Ocultar' : 'Mostrar';
    btn.querySelector('i').className = open ? 'bi bi-dash-circle' : 'bi bi-plus-circle';
}

function toggleSeccionHoras(mostrar) {
    const wrapper = document.getElementById('wrapper_horas');
    wrapper.classList.toggle('hidden', !mostrar);
}

function ponerMotivo(texto) {
    document.getElementById('motivoInput').value = texto;
}

function ponerFecha(que) {
    const input = document.getElementById('fechaBloqueo');
    const hoy = new Date();
    if (que === 'today') {
        input.value = hoy.toISOString().slice(0,10);
    } else if (que === 'tomorrow') {
        const m = new Date(hoy); m.setDate(m.getDate() + 1);
        input.value = m.toISOString().slice(0,10);
    } else if (que === 'thisweekend') {
        const diasHastaSab = 6 - hoy.getDay(); // 6=sábado
        const sab = new Date(hoy); sab.setDate(sab.getDate() + diasHastaSab);
        input.value = sab.toISOString().slice(0,10);
    }
}
</script>

</body>
</html>
