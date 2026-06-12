<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Horario.php';
require_once __DIR__ . '/../clases/Bloqueo.php';
require_once __DIR__ . '/../clases/Reserva.php';
require_once __DIR__ . '/../clases/Administrador.php';
require_once __DIR__ . '/../clases/Csrf.php';
require_once __DIR__ . '/../clases/helpers.php';

session_start();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

// ── POST: Quick actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_rapida'])) {
    if (!Csrf::validarToken('agenda', $_POST['csrf_token'] ?? '')) {
        $error_agenda = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $id_res = (int)($_POST['id_reserva'] ?? 0);
        $accion = $_POST['accion_rapida'];
        $ok = false;

        if ($accion === 'completar') {
            $ok = Reserva::marcarComoCompletada($id_res);
        } elseif ($accion === 'no_show') {
            $ok = Reserva::marcarComoNoPresentado($id_res);
        }

        if ($ok) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => $accion === 'completar' ? 'Cita completada' : 'Marcado como no presentado'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'No se pudo actualizar la cita'];
        }
        redirigir('index.php?fecha=' . ($_GET['fecha'] ?? date('Y-m-d')));
    }
}

// ── Fecha ──
$fecha_raw = $_GET['fecha'] ?? date('Y-m-d');
if (!esFechaValida($fecha_raw)) $fecha_raw = date('Y-m-d');
$fecha_seleccionada = $fecha_raw;

$dt_seleccionada = new DateTimeImmutable($fecha_seleccionada);
$fecha_anterior  = $dt_seleccionada->modify('-1 day')->format('Y-m-d');
$fecha_siguiente = $dt_seleccionada->modify('+1 day')->format('Y-m-d');

$hoy       = new DateTimeImmutable('today');
$es_hoy    = $fecha_seleccionada === $hoy->format('Y-m-d');
$es_pasado = $dt_seleccionada < $hoy;

// ── Datos ──
const ID_BARBERO = 1;
$dia_bloqueado_completo = Bloqueo::esDiaBloqueadoCompleto(ID_BARBERO, $fecha_seleccionada);
$tramos_del_dia         = Horario::obtenerTramosPorFecha(ID_BARBERO, $fecha_seleccionada);
$trabaja_hoy            = !$dia_bloqueado_completo && !empty($tramos_del_dia);

$reservas_del_dia = [];
$bloqueos_del_dia = [];

if ($trabaja_hoy) {
    $reservas_del_dia = Reserva::obtenerDelDiaParaAdmin(ID_BARBERO, $fecha_seleccionada);
    $bloqueos_del_dia = Bloqueo::obtenerPorFecha(ID_BARBERO, $fecha_seleccionada);
}

$resumen_dia    = Administrador::obtenerResumenDia($fecha_seleccionada);
$resumen_semana = $es_hoy ? Administrador::obtenerResumenSemanaActual() : null;

// ── Construir slots agrupados ──
$slots = [];
if ($trabaja_hoy) {
    foreach ($tramos_del_dia as $tramo) {
        $hora_actual    = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($tramo['hora_inicio'], 0, 5));
        $hora_fin_tramo = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($tramo['hora_fin'], 0, 5));

        while ($hora_actual < $hora_fin_tramo) {
            $hora_str      = $hora_actual->format('H:i');
            $hora_fin_slot = $hora_actual->modify('+30 minutes');
            $estado        = 'libre';
            $reserva       = null;

            foreach ($bloqueos_del_dia as $bloqueo) {
                if (empty($bloqueo['hora_inicio']) || empty($bloqueo['hora_fin'])) continue;
                $bloqueo_inicio = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($bloqueo['hora_inicio'], 0, 5));
                $bloqueo_fin    = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($bloqueo['hora_fin'], 0, 5));
                if ($hora_actual < $bloqueo_fin && $hora_fin_slot > $bloqueo_inicio) {
                    $estado = 'bloqueado'; break;
                }
            }

            if ($estado === 'libre') {
                foreach ($reservas_del_dia as $res) {
                    $res_inicio = new DateTimeImmutable($fecha_seleccionada . ' ' . substr($res['hora'], 0, 5));
                    $res_fin    = $res_inicio->modify('+' . (int)$res['duracion_historica'] . ' minutes');
                    if ($hora_actual < $res_fin && $hora_fin_slot > $res_inicio) {
                        $estado = 'reservado'; $reserva = $res; break;
                    }
                }
            }

            $slots[] = ['hora' => $hora_str, 'estado' => $estado, 'reserva' => $reserva, 'hora_fin' => $hora_fin_slot->format('H:i')];
            $hora_actual = $hora_fin_slot;
        }
    }
}

// ── Agrupar slots consecutivos del mismo tipo no-reservado ──
$slots_agrupados = [];
$i = 0;
while ($i < count($slots)) {
    $slot = $slots[$i];
    if ($slot['estado'] === 'reservado') {
        $slots_agrupados[] = $slot;
        $i++;
    } else {
        $tipo = $slot['estado'];
        $inicio = $slot['hora'];
        $fin = $slot['hora_fin'];
        $i++;
        while ($i < count($slots) && $slots[$i]['estado'] === $tipo) {
            $fin = $slots[$i]['hora_fin'];
            $i++;
        }
        $slots_agrupados[] = ['hora' => $inicio, 'hora_fin' => $fin, 'estado' => $tipo, 'reserva' => null];
    }
}

$total_reservados = count(array_filter($slots_agrupados, fn($s) => $s['estado'] === 'reservado'));
$total_libres = count(array_filter($slots_agrupados, fn($s) => $s['estado'] === 'libre'));
$total_bloqueados = count(array_filter($slots_agrupados, fn($s) => $s['estado'] === 'bloqueado'));

$pagina_activa = 'agenda';
$titulo_fecha  = fechaHumana($fecha_seleccionada);
$token_csrf    = Csrf::generarToken('agenda');
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
            <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Agenda</h1>
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
            <a href="?fecha=<?= h($fecha_anterior) ?>"
               class="w-9 h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] flex items-center justify-center transition-all hover:bg-[var(--gold-dim)] hover:border-[var(--gold-brd)] hover:text-[var(--gold)]">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php if (!$es_hoy): ?>
                <a href="?fecha=<?= h($hoy->format('Y-m-d')) ?>"
                   class="px-3.5 h-9 rounded-lg border border-[var(--gold-brd)] bg-[var(--gold-dim)] text-[var(--gold)] text-[0.65rem] font-semibold tracking-widest uppercase flex items-center transition-all hover:bg-white/10">
                    Hoy
                </a>
            <?php endif; ?>
            <a href="?fecha=<?= h($fecha_siguiente) ?>"
               class="w-9 h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] flex items-center justify-center transition-all hover:bg-[var(--gold-dim)] hover:border-[var(--gold-brd)] hover:text-[var(--gold)]">
                <i class="bi bi-chevron-right"></i>
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03]">
            <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Citas hoy</span>
            <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_dia['total'] ?></span>
            <span class="text-[0.62rem] text-[var(--tx-d)]"><?= $resumen_dia['confirmadas'] ?> confirmada<?= $resumen_dia['confirmadas'] !== 1 ? 's' : '' ?></span>
        </div>
        <div class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03]">
            <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Completadas</span>
            <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_dia['completadas'] ?></span>
            <?php if ($resumen_dia['no_presentados'] > 0): ?>
                <span class="text-[0.62rem] text-[#888]"><?= $resumen_dia['no_presentados'] ?> no show</span>
            <?php else: ?>
                <span class="text-[0.62rem] text-[var(--tx-d)]">de <?= $resumen_dia['total'] ?> totales</span>
            <?php endif; ?>
        </div>
        <div class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--gold-brd)] bg-[var(--gold-dim)]">
            <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--gold)]/70">Ingresos hoy</span>
            <span class="text-[1.6rem] font-semibold leading-none text-[var(--gold)]" style="font-family: var(--pf);"><?= number_format($resumen_dia['ingresos'], 0, ',', '.') ?>€</span>
            <span class="text-[0.62rem] text-[var(--gold)]/60">de citas completadas</span>
        </div>
        <?php if ($resumen_semana !== null): ?>
            <div class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03]">
                <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Esta semana</span>
                <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_semana['total'] ?></span>
                <span class="text-[0.62rem] text-[var(--tx-d)]"><?= number_format($resumen_semana['ingresos'], 0, ',', '.') ?>€ completados</span>
            </div>
        <?php else: ?>
            <div class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03]">
                <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Canceladas</span>
                <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_dia['canceladas'] ?></span>
                <span class="text-[0.62rem] text-[var(--tx-d)]">ese día</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error_agenda ?? false): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error_agenda) ?>
        </div>
    <?php endif; ?>

    <?php if ($trabaja_hoy): ?>
        <div class="flex flex-wrap gap-2 mb-6">
            <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-[rgba(212,175,55,0.1)] border border-[rgba(212,175,55,0.2)] text-[var(--gold)]">
                <i class="bi bi-calendar-check"></i> <?= $total_reservados ?> cita<?= $total_reservados !== 1 ? 's' : '' ?>
            </span>
            <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-white/5 border border-white/10 text-[var(--tx-m)]">
                <i class="bi bi-circle"></i> <?= $total_libres ?> bloque<?= $total_libres !== 1 ? 's' : '' ?> libre<?= $total_libres !== 1 ? 's' : '' ?>
            </span>
            <?php if ($total_bloqueados > 0): ?>
                <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-white/5 border border-white/10 text-[var(--tx-d)]">
                    <i class="bi bi-slash-circle"></i> <?= $total_bloqueados ?> bloqueado<?= $total_bloqueados !== 1 ? 's' : '' ?>
                </span>
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
        <?php elseif (empty($slots_agrupados)): ?>
            <div class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                <i class="bi bi-hourglass text-4xl text-[var(--tx-d)] opacity-50 mb-1"></i>
                <p class="text-[1.1rem] text-[var(--tx-m)]" style="font-family: var(--pf);">Sin huecos disponibles</p>
            </div>
        <?php else: ?>

            <?php foreach ($slots_agrupados as $slot): ?>

                <?php if ($slot['estado'] === 'reservado'): ?>
                    <?php $res = $slot['reserva']; ?>
                    <div onclick="window.location.href='ficha_cliente.php?id_reserva=<?= (int)$res['id'] ?>'"
                         class="slot-card glow-card flex items-center gap-4 px-4 py-3.5 rounded-xl border min-h-[64px] cursor-pointer transition-all duration-150 <?= $res['estado'] === 'confirmada' ? 'border-[var(--gold-brd)] bg-[var(--gold-dim)]' : ($res['estado'] === 'completada' ? 'border-emerald-500/20 bg-emerald-500/5' : 'border-[var(--brd)] bg-white/5') ?>">

                        <div class="text-[0.78rem] font-semibold text-[var(--tx)] min-w-[42px] shrink-0"><?= h($slot['hora']) ?></div>
                        <div class="w-[2px] h-9 rounded-full shrink-0 <?= $res['estado'] === 'completada' ? 'bg-emerald-500/50' : 'bg-[var(--gold)] opacity-70' ?>"></div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-[0.82rem] font-semibold text-[var(--tx)] truncate"><?= h($res['cliente_nombre']) ?></span>
                                <?php if ($res['estado'] === 'completada'): ?>
                                    <span class="text-[0.5rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Hecho</span>
                                <?php elseif ($res['estado'] === 'no_presentado'): ?>
                                    <span class="text-[0.5rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-white/5 text-[#888] border border-white/10">No show</span>
                                <?php elseif ($res['estado'] === 'cancelada'): ?>
                                    <span class="text-[0.5rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20">Cancelada</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[0.65rem] text-[var(--tx-m)] mt-0.5 truncate"><?= h($res['servicio_nombre']) ?></div>
                            <div class="flex gap-3 mt-1">
                                <span class="flex items-center gap-1 text-[0.6rem] text-[var(--tx-m)]"><i class="bi bi-clock text-[0.7rem]"></i> <?= (int)$res['duracion_historica'] ?> min</span>
                                <span class="flex items-center gap-1 text-[0.6rem] text-[var(--tx-m)]"><i class="bi bi-currency-euro text-[0.7rem]"></i> <?= number_format((float)$res['precio_historico'], 2, ',', '.') ?></span>
                            </div>
                        </div>

                        <?php if ($res['estado'] === 'confirmada' && !$es_pasado): ?>
                            <div class="flex flex-col gap-1.5 shrink-0">
                                <form method="POST" onsubmit="event.stopPropagation(); return confirm('¿Completar cita de <?= h(addslashes($res['cliente_nombre'])) ?>?')">
                                    <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
                                    <input type="hidden" name="accion_rapida" value="completar">
                                    <input type="hidden" name="id_reserva" value="<?= (int)$res['id'] ?>">
                                    <button type="submit" onclick="event.stopPropagation()" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[0.58rem] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition-all cursor-pointer whitespace-nowrap">
                                        <i class="bi bi-check-lg"></i> Hecho
                                    </button>
                                </form>
                                <form method="POST" onsubmit="event.stopPropagation(); return confirm('¿Marcar como no presentado?')">
                                    <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
                                    <input type="hidden" name="accion_rapida" value="no_show">
                                    <input type="hidden" name="id_reserva" value="<?= (int)$res['id'] ?>">
                                    <button type="submit" onclick="event.stopPropagation()" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[0.58rem] font-bold uppercase tracking-wider bg-white/5 text-[var(--tx-d)] border border-white/10 hover:bg-white/10 transition-all cursor-pointer whitespace-nowrap">
                                        <i class="bi bi-x-lg"></i> No show
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </div>

                <?php elseif ($slot['estado'] === 'bloqueado'): ?>
                    <div class="slot-card flex items-center gap-4 px-4 py-3 rounded-xl border border-purple-500/15 bg-purple-500/5 min-h-[48px] opacity-60 cursor-default">
                        <div class="text-[0.78rem] font-semibold text-[var(--tx-d)] min-w-[42px] shrink-0"><?= h($slot['hora']) ?></div>
                        <div class="flex items-center gap-2 text-[0.65rem] text-[var(--tx-d)] tracking-wide">
                            <i class="bi bi-slash-circle text-purple-400/60"></i>
                            Bloqueado · <?= h($slot['hora']) ?> – <?= h($slot['hora_fin']) ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="slot-card flex items-center gap-4 px-4 py-3 rounded-xl border border-[var(--brd)] bg-white/5 min-h-[48px] opacity-40 cursor-default">
                        <div class="text-[0.78rem] font-semibold text-[var(--tx-d)] min-w-[42px] shrink-0"><?= h($slot['hora']) ?></div>
                        <div class="flex items-center gap-2 text-[0.65rem] text-[var(--tx-d)] tracking-wide">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500/50 shrink-0"></span>
                            Disponible · <?= h($slot['hora']) ?> – <?= h($slot['hora_fin']) ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

</body>
</html>
