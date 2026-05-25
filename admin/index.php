<?php
// ============================================================
// admin/index.php — Agenda del día (Panel de Administración)
// ============================================================

declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── FASE 1: Dependencias ─────────────────────────────────────
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Horario.php';
require_once __DIR__ . '/../clases/Bloqueo.php';
require_once __DIR__ . '/../clases/Reserva.php';
require_once __DIR__ . '/../clases/helpers.php';

// ── FASE 2: Sesión y control de acceso ──────────────────────
session_start();
if (!isset($_SESSION['usuario'])) {
    redirigir('../login.php');
}
if (!$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../cliente/index.php');
}

// ── FASE 3: Fecha seleccionada ───────────────────────────────
$fecha_raw = $_GET['fecha'] ?? date('Y-m-d');
if (!esFechaValida($fecha_raw)) {
    $fecha_raw = date('Y-m-d');
}
$fecha_seleccionada = $fecha_raw;

$dt_seleccionada = new DateTimeImmutable($fecha_seleccionada);
$fecha_anterior  = $dt_seleccionada->modify('-1 day')->format('Y-m-d');
$fecha_siguiente = $dt_seleccionada->modify('+1 day')->format('Y-m-d');

$hoy          = new DateTimeImmutable('today');
$es_hoy       = $fecha_seleccionada === $hoy->format('Y-m-d');
$es_pasado    = $dt_seleccionada < $hoy;

// ── FASE 4: Datos del día ────────────────────────────────────
const ID_BARBERO = 1;
$dia_bloqueado_completo = Bloqueo::esDiaBloqueadoCompleto(ID_BARBERO, $fecha_seleccionada);
$tramos_del_dia = Horario::obtenerTramosPorFecha(ID_BARBERO, $fecha_seleccionada);
$trabaja_hoy = !$dia_bloqueado_completo && !empty($tramos_del_dia);

$reservas_del_dia = [];
$bloqueos_del_dia = [];

if ($trabaja_hoy) {
    $reservas_del_dia = Reserva::obtenerDelDiaParaAdmin(ID_BARBERO, $fecha_seleccionada);
    $bloqueos_del_dia = Bloqueo::obtenerPorFecha(ID_BARBERO, $fecha_seleccionada);
}

// ── FASE 5: Construir los slots ──────────────────────────────
$slots = [];

if ($trabaja_hoy) {
    foreach ($tramos_del_dia as $tramo) {
        $hora_actual   = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($tramo['hora_inicio'], 0, 5));
        $hora_fin_tramo = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($tramo['hora_fin'], 0, 5));

        while ($hora_actual < $hora_fin_tramo) {
            $hora_str  = $hora_actual->format('H:i');
            $hora_fin_slot = $hora_actual->modify('+30 minutes');
            $estado  = 'libre';
            $reserva = null;

            // Bloqueos
            foreach ($bloqueos_del_dia as $bloqueo) {
                if (empty($bloqueo['hora_inicio']) || empty($bloqueo['hora_fin'])) continue;
                $bloqueo_inicio = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($bloqueo['hora_inicio'], 0, 5));
                $bloqueo_fin    = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($bloqueo['hora_fin'], 0, 5));

                if ($hora_actual < $bloqueo_fin && $hora_fin_slot > $bloqueo_inicio) {
                    $estado = 'bloqueado';
                    break;
                }
            }

            // Reservas
            if ($estado === 'libre') {
                foreach ($reservas_del_dia as $res) {
                    $res_inicio = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($res['hora'], 0, 5));
                    $res_fin    = $res_inicio->modify('+' . (int)$res['duracion_historica'] . ' minutes');

                    if ($hora_actual < $res_fin && $hora_fin_slot > $res_inicio) {
                        $estado  = 'reservado';
                        $reserva = $res;
                        break;
                    }
                }
            }

            $slots[] = ['hora' => $hora_str, 'estado' => $estado, 'reserva' => $reserva];
            $hora_actual = $hora_fin_slot;
        }
    }
}

// ── FASE 6: Estadísticas ─────────────────────────────────────
$total_reservados = $total_libres = $total_bloqueados = 0;
foreach ($slots as $slot) {
    if ($slot['estado'] === 'reservado')  $total_reservados++;
    if ($slot['estado'] === 'libre')      $total_libres++;
    if ($slot['estado'] === 'bloqueado')  $total_bloqueados++;
}

$pagina_activa = 'agenda';
$titulo_fecha = fechaHumana($fecha_seleccionada);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda — Panel Admin · Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none">

    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">
                Agenda
            </h1>
            <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1 flex items-center gap-2">
                <?php if ($es_hoy): ?>
                    <span class="bg-[var(--gold)] text-[#0d0d0d] text-[0.48rem] font-bold tracking-[0.18em] px-2 py-[3px] rounded-full uppercase">HOY</span>
                <?php elseif ($es_pasado): ?>
                    <span class="bg-white/10 text-[var(--tx-d)] text-[0.48rem] font-semibold tracking-[0.18em] px-2 py-[3px] rounded-full uppercase">PASADO</span>
                <?php endif; ?>
                <?= h(ucfirst($titulo_fecha)) ?>
            </p>
        </div>

        <nav class="flex items-center gap-2">
            <a href="?fecha=<?= h($fecha_anterior) ?>" class="w-9 h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] flex items-center justify-center transition-all hover:bg-[var(--gold-dim)] hover:border-[var(--gold-brd)] hover:text-[var(--gold)]">
                <i class="bi bi-chevron-left"></i>
            </a>

            <?php if (!$es_hoy): ?>
                <a href="?fecha=<?= h($hoy->format('Y-m-d')) ?>" class="px-3.5 h-9 rounded-lg border border-[var(--gold-brd)] bg-[var(--gold-dim)] text-[var(--gold)] text-[0.65rem] font-semibold tracking-widest uppercase flex items-center transition-all hover:bg-white/10">
                    Hoy
                </a>
            <?php endif; ?>

            <a href="?fecha=<?= h($fecha_siguiente) ?>" class="w-9 h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] flex items-center justify-center transition-all hover:bg-[var(--gold-dim)] hover:border-[var(--gold-brd)] hover:text-[var(--gold)]">
                <i class="bi bi-chevron-right"></i>
            </a>
        </nav>
    </div>

    <?php if ($trabaja_hoy): ?>
        <div class="flex flex-wrap gap-2 mb-6">
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-[rgba(212,175,55,0.1)] border border-[rgba(212,175,55,0.2)] text-[var(--gold)]">
                <i class="bi bi-calendar-check"></i>
                <span><?= $total_reservados ?> cita<?= $total_reservados !== 1 ? 's' : '' ?></span>
            </div>
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-white/5 border border-white/10 text-[var(--tx-m)]">
                <i class="bi bi-circle"></i>
                <span><?= $total_libres ?> libre<?= $total_libres !== 1 ? 's' : '' ?></span>
            </div>
            <?php if ($total_bloqueados > 0): ?>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-white/5 border border-white/10 text-[var(--tx-d)]">
                    <i class="bi bi-slash-circle"></i>
                    <span><?= $total_bloqueados ?> bloqueado<?= $total_bloqueados !== 1 ? 's' : '' ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col gap-2 agenda-grid">

        <?php if ($dia_bloqueado_completo): ?>
            <div class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                <i class="bi bi-moon-stars text-4xl text-[var(--tx-d)] opacity-50 mb-1"></i>
                <p class="text-[1.1rem] text-[var(--tx-m)]" style="font-family: var(--pf);">Día cerrado</p>
                <p class="text-[0.7rem] text-[var(--tx-d)] max-w-xs">Hassan ha bloqueado este día completo.</p>
            </div>
        <?php elseif (empty($tramos_del_dia)): ?>
            <div class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                <i class="bi bi-calendar-x text-4xl text-[var(--tx-d)] opacity-50 mb-1"></i>
                <p class="text-[1.1rem] text-[var(--tx-m)]" style="font-family: var(--pf);">Día de descanso</p>
                <p class="text-[0.7rem] text-[var(--tx-d)] max-w-xs">No hay horario definido para este día.</p>
            </div>
        <?php elseif (empty($slots)): ?>
            <div class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                <i class="bi bi-hourglass text-4xl text-[var(--tx-d)] opacity-50 mb-1"></i>
                <p class="text-[1.1rem] text-[var(--tx-m)]" style="font-family: var(--pf);">Sin huecos disponibles</p>
            </div>
        <?php else: ?>

            <?php foreach ($slots as $slot): ?>
                <?php if ($slot['estado'] === 'reservado'): ?>

                    <a href="ficha_cliente.php?id_reserva=<?= (int)$slot['reserva']['id'] ?>"
                       class="slot-card group flex items-center gap-4 px-4 py-3.5 rounded-xl border border-[var(--gold-brd)] bg-[var(--gold-dim)] min-h-[64px] cursor-pointer transition-all duration-150 hover:bg-[rgba(212,175,55,0.1)] hover:border-[rgba(212,175,55,0.4)] hover:translate-x-1">

                        <div class="text-[0.78rem] font-semibold text-[var(--tx)] min-w-[42px] shrink-0"><?= h($slot['hora']) ?></div>
                        <div class="w-[2px] h-9 bg-[var(--gold)] rounded-full shrink-0 opacity-70"></div>

                        <div class="flex-1 min-w-0">
                            <div class="text-[0.82rem] font-semibold text-[var(--tx)] truncate"><?= h($slot['reserva']['cliente_nombre']) ?></div>
                            <div class="text-[0.65rem] text-[var(--gold)] mt-0.5 truncate"><?= h($slot['reserva']['servicio_nombre']) ?></div>
                            <div class="flex gap-3 mt-1.5">
                                <span class="flex items-center gap-1 text-[0.6rem] text-[var(--tx-m)]">
                                    <i class="bi bi-clock text-[0.7rem]"></i> <?= (int)$slot['reserva']['duracion_historica'] ?> min
                                </span>
                                <span class="flex items-center gap-1 text-[0.6rem] text-[var(--tx-m)]">
                                    <i class="bi bi-currency-euro text-[0.7rem]"></i> <?= number_format((float)$slot['reserva']['precio_historico'], 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>

                        <div class="text-[var(--tx-d)] text-xs shrink-0 transition-all duration-150 group-hover:text-[var(--gold)] group-hover:translate-x-1">
                            <i class="bi bi-chevron-right"></i>
                        </div>
                    </a>

                <?php elseif ($slot['estado'] === 'bloqueado'): ?>

                    <div class="slot-card flex items-center gap-4 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/5 min-h-[64px] opacity-45 cursor-default">
                        <div class="text-[0.78rem] font-semibold text-[var(--tx-d)] min-w-[42px] shrink-0"><?= h($slot['hora']) ?></div>
                        <div class="flex items-center gap-2 text-[0.68rem] text-[var(--tx-d)] tracking-wide">
                            <i class="bi bi-slash-circle"></i> Bloqueado
                        </div>
                    </div>

                <?php else: ?>

                    <div class="slot-card flex items-center gap-4 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/5 min-h-[64px] opacity-50 cursor-default">
                        <div class="text-[0.78rem] font-semibold text-[var(--tx-d)] min-w-[42px] shrink-0"><?= h($slot['hora']) ?></div>
                        <div class="flex items-center gap-2 text-[0.68rem] text-[var(--tx-d)] tracking-wide">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500/50 shrink-0"></span> Disponible
                        </div>
                    </div>

                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</main>

</body>
</html>