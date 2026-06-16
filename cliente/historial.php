<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Carga de dependencias ─────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Reserva.php';

// ── Fase 2: Sesión y control de acceso ────────────────────────────
iniciarSesionSegura();

if (!isset($_SESSION['usuario'])) {
    $_SESSION['volver_panel'] = 'index.php';
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];

if ($usuario->tieneRolAdmin()) {
    redirigir('../admin/index.php');
}

// ── Fase 3: Recuperación de datos ─────────────────────────────────
// Actualizar estado de citas pasadas antes de consultar el historial
Reserva::actualizarCitasPasadas();

$id_usuario = (int)$usuario->getId();
$historial  = Reserva::obtenerHistorialPorCliente($id_usuario);
$pagina_activa = 'historial';

// ── Fase 4: Agrupar citas por mes-año ─────────────────────────────
$meses_es = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
             'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$grupos = [];
foreach ($historial as $cita) {
    $ts = strtotime($cita['fecha']);
    $clave = date('Y-m', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    $grupos[$clave]['label'] = $meses_es[$m] . " $y";
    $grupos[$clave]['items'][] = $cita;
}
krsort($grupos);

// Contadores globales
$total_completadas = 0;
$total_canceladas = 0;
foreach ($historial as $cita) {
    if ($cita['estado'] === 'completada') $total_completadas++;
    elseif ($cita['estado'] === 'cancelada') $total_canceladas++;
}

?>>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Historial — Barbershop La H</title>

    <!-- Tailwind CSS v4 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Fuentes y Iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Estilos globales -->
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php require_once __DIR__ . '/includes/nav_cliente.php'; ?>

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">
    <div class="flex-1 w-full max-w-4xl mx-auto p-4 sm:p-6 lg:p-8 flex flex-col gap-6">

        <!-- Cabecera interna -->
        <div class="flex flex-col gap-1">
            <h1 class="font-[var(--pf)] text-3xl sm:text-4xl font-semibold text-[var(--tx)]">
                Mi Historial
            </h1>
            <p class="text-[0.62rem] text-[var(--tx-m)] tracking-[0.22em] uppercase">
                TU HISTORIAL DE VISITAS
            </p>
        </div>

        <!-- Banner de Google Reviews (compacto) -->
        <div class="flex items-center justify-between gap-3 rounded-xl border border-[var(--gold-brd)] px-4 py-3" style="background:linear-gradient(135deg,rgba(255,215,0,0.04),transparent)">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex items-center gap-0.5 text-[var(--gold)] text-xs shrink-0">
                    <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                </div>
                <span class="text-[0.68rem] text-[var(--tx-m)] truncate">¿Te gustó? Valora en Google</span>
            </div>
            <a href="https://search.google.com/local/writereview?placeid=ChIJUbsCcgAVWQ0RY94kRz2CmIA" target="_blank"
               class="shrink-0 rounded-lg bg-[var(--gold)] px-3 py-1.5 text-[0.6rem] font-bold uppercase tracking-wider text-[var(--bg)] transition-all hover:opacity-90 flex items-center gap-1.5 no-underline">
               <i class="bi bi-google"></i> Valorar
            </a>
        </div>

        <!-- Mini stats -->
        <?php if (!empty($historial)): ?>
        <div class="grid grid-cols-2 gap-2 sm:gap-3">
            <div class="rounded-xl p-3 sm:p-4 border text-center" style="background:var(--card); border-color:var(--brd);">
                <div style="font-family:var(--pf); font-size:clamp(1.4rem,4vw,1.8rem); color:#4ade80; line-height:1;"><?= $total_completadas ?></div>
                <div style="font-size:0.55rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.2em; margin-top:2px;">completadas</div>
            </div>
            <div class="rounded-xl p-3 sm:p-4 border text-center" style="background:var(--card); border-color:var(--brd);">
                <div style="font-family:var(--pf); font-size:clamp(1.4rem,4vw,1.8rem); color:#f87171; line-height:1;"><?= $total_canceladas ?></div>
                <div style="font-size:0.55rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.2em; margin-top:2px;">canceladas</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Listado de Citas agrupado por mes -->
        <?php if (empty($historial)): ?>
            <div class="rounded-xl p-10 flex flex-col items-center text-center gap-5 border" style="background:var(--card); border-color:var(--brd); opacity:0; animation:fadeInUp 0.45s var(--ease-out) 0.1s forwards;">
                <div class="w-16 h-16 rounded-full flex items-center justify-center border" style="background:var(--bg2); border-color:var(--brd);">
                    <i class="bi bi-calendar2-x" style="color:var(--tx-d); font-size:1.6rem;"></i>
                </div>
                <div>
                    <h3 style="font-family:var(--pf); font-size:1.15rem; font-weight:600; color:var(--tx); margin-bottom:8px;">Aún no tienes citas</h3>
                    <p style="font-size:0.78rem; color:var(--tx-m); max-width:280px; line-height:1.5;">
                        Tu historial aparecerá aquí en cuanto realices tu primera visita.
                    </p>
                </div>
                <a href="reserva.php" class="cta-pulse inline-flex items-center gap-2 rounded-xl px-6 py-3 font-bold uppercase tracking-wider transition-opacity" style="background:var(--gold); color:var(--bg); font-size:0.72rem;">
                    <i class="bi bi-calendar-plus"></i> Reservar mi primera cita
                </a>
            </div>
        <?php else: ?>
            <!-- Grupos por mes -->
            <?php foreach ($grupos as $clave => $grupo):
                $items = $grupo['items'];
                $label = $grupo['label'];
            ?>
            <section>
                <div class="flex items-center gap-3 mb-3">
                    <h2 style="font-family:var(--pf); font-size:clamp(0.95rem,3vw,1.1rem); font-weight:600; color:var(--tx); white-space:nowrap;"><?= h($label) ?></h2>
                    <div class="h-px flex-1" style="background:var(--brd);"></div>
                    <span style="font-size:0.55rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.15em; white-space:nowrap;"><?= count($items) ?> cita<?= count($items) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="flex flex-col gap-2 sm:gap-2.5">
                <?php foreach ($items as $cita):
                    $badgeClass = "";
                    $estadoLabel = "";
                    $iconEstado = "";
                    switch($cita['estado']) {
                        case 'completada':
                            $badgeClass = "background:rgba(34,197,94,0.12); color:#4ade80; border-color:rgba(34,197,94,0.2)";
                            $estadoLabel = "Completada";
                            $iconEstado = "bi-check-circle-fill";
                            break;
                        case 'cancelada':
                            $badgeClass = "background:rgba(239,68,68,0.12); color:#f87171; border-color:rgba(239,68,68,0.2)";
                            $estadoLabel = "Cancelada";
                            $iconEstado = "bi-x-circle-fill";
                            break;
                        case 'no_presentado':
                            $badgeClass = "background:rgba(136,136,136,0.12); color:#888888; border-color:rgba(136,136,136,0.2)";
                            $estadoLabel = "No presentado";
                            $iconEstado = "bi-question-circle-fill";
                            break;
                        case 'confirmada':
                        case 'pendiente':
                            $badgeClass = "background:var(--gold-dim); color:var(--gold); border-color:var(--gold-brd)";
                            $estadoLabel = "Próxima";
                            $iconEstado = "bi-clock-fill";
                            break;
                        default:
                            $badgeClass = "background:rgba(255,255,255,0.04); color:rgba(255,255,255,0.4); border-color:rgba(255,255,255,0.08)";
                            $estadoLabel = ucfirst($cita['estado']);
                            $iconEstado = "bi-record-circle";
                    }
                ?>
                    <div class="rounded-xl p-3 sm:p-4 border transition-all duration-200 hover:-translate-y-0.5" style="background:var(--bg2); border-color:var(--brd);">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <div style="font-size:0.85rem; font-weight:600; color:var(--tx);"><?= h($cita['nombre_servicio']) ?></div>
                                <div class="flex flex-wrap items-center gap-2.5 mt-1" style="font-size:0.68rem; color:var(--tx-m);">
                                    <span class="flex items-center gap-1">
                                        <i class="bi bi-calendar3" style="color:var(--gold); font-size:0.6rem;"></i>
                                        <?= h(fechaHumana($cita['fecha'])) ?>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <i class="bi bi-clock" style="color:var(--gold); font-size:0.6rem;"></i>
                                        <?= h(substr($cita['hora'], 0, 5)) ?>h
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                <span style="font-weight:700; font-size:0.9rem; color:var(--tx);">
                                    <?= number_format((float)$cita['precio_historico'], 2, ',', '.') ?> €
                                </span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[0.55rem] font-bold uppercase tracking-wider border" style="<?= $badgeClass ?>">
                                    <i class="bi <?= $iconEstado ?>" style="font-size:0.5rem;"></i>
                                    <?= h($estadoLabel) ?>
                                </span>
                                <?php if ($cita['estado'] === 'cancelada' && !empty($cita['motivo_cancelacion'])): ?>
                                    <div class="flex items-start gap-1 max-w-[160px]">
                                        <i class="bi bi-chat-quote" style="color:#f87171; font-size:0.5rem; margin-top:2px; flex-shrink:0;"></i>
                                        <span style="font-size:0.55rem; color:#f87171; line-height:1.3;"><?= h($cita['motivo_cancelacion']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
