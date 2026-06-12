<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Carga de dependencias ─────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Reserva.php';

// ── Fase 2: Sesión y control de acceso ────────────────────────────
session_start();

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

?>
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

        <!-- Banner de Google Reviews -->
        <div class="rounded-2xl bg-gradient-to-br from-[#161616] to-[#0a0a0a] border border-[var(--gold-brd)] p-5 sm:p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex flex-col items-center sm:items-start text-center sm:text-left gap-2">
                <div class="flex items-center gap-1 text-[var(--gold)] text-lg">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                </div>
                <h2 class="text-[var(--tx)] font-semibold text-lg">¿Te ha gustado tu corte?</h2>
                <p class="text-[var(--tx-m)] text-sm max-w-sm">
                    Tu opinión nos ayuda a crecer. Valora tu experiencia con Hassan en Google.
                </p>
            </div>
            <a href="https://search.google.com/local/writereview?placeid=ChIJUbsCcgAVWQ0RY94kRz2CmIA" target="_blank"
               class="px-6 py-2.5 rounded-lg bg-[var(--gold)] text-[var(--bg)] font-bold text-sm uppercase tracking-wide transition-all hover:opacity-90 hover:scale-[1.02] flex items-center gap-2">
               <i class="bi bi-google"></i>
               Valorar ahora
            </a>
        </div>

        <!-- Listado de Citas — Timeline visual -->
        <?php if (empty($historial)): ?>
            <!-- Estado Vacío -->
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
            <!-- Timeline de citas -->
            <div class="timeline-track">
                <?php foreach ($historial as $cita):
                    // Badge de estado
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
                            $badgeClass = "background:rgba(251,146,60,0.12); color:#fb923c; border-color:rgba(251,146,60,0.2)";
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
                    <div class="timeline-item">
                        <div class="rounded-xl p-4 sm:p-5 border transition-all duration-200 hover:-translate-y-0.5" style="background:var(--bg2); border-color:var(--brd);">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span style="font-size:0.95rem; font-weight:600; color:var(--tx);"><?= h($cita['nombre_servicio']) ?></span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3 mt-1.5" style="font-size:0.72rem; color:var(--tx-m);">
                                        <span class="flex items-center gap-1.5">
                                            <i class="bi bi-calendar3" style="color:var(--gold); font-size:0.65rem;"></i>
                                            <?= h(fechaHumana($cita['fecha'])) ?>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <i class="bi bi-clock" style="color:var(--gold); font-size:0.65rem;"></i>
                                            <?= h(substr($cita['hora'], 0, 5)) ?>h
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <span style="font-weight:700; font-size:1rem; color:var(--tx);">
                                        <?= number_format((float)$cita['precio_historico'], 2, ',', '.') ?> €
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[0.6rem] font-bold uppercase tracking-wider border" style="<?= $badgeClass ?>">
                                        <i class="bi <?= $iconEstado ?>" style="font-size:0.55rem;"></i>
                                        <?= h($estadoLabel) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
