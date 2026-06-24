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
require_once __DIR__ . '/../clases/Cliente.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

if (!defined('ID_BARBERO')) define('ID_BARBERO', 1);

// ── Fecha ──
$fecha_raw = $_GET['fecha'] ?? date('Y-m-d');
if (!esFechaValida($fecha_raw)) $fecha_raw = date('Y-m-d');
$fecha_seleccionada = $fecha_raw;

/* ── Mover cita (AJAX) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'mover_cita') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Csrf::validarToken('mover_cita', $_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'Sesión caducada. Recarga la página.']);
        exit;
    }

    $id_reserva  = (int)($_POST['id_reserva'] ?? 0);
    $nueva_fecha = $_POST['nueva_fecha'] ?? '';
    $nueva_hora  = $_POST['nueva_hora'] ?? '';
    $motivo      = trim($_POST['motivo'] ?? '');

    if ($id_reserva <= 0 || !esFechaValida($nueva_fecha) || $nueva_hora === '') {
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
        exit;
    }

    require_once __DIR__ . '/../clases/NotificadorReserva.php';

    $resultado = Reserva::mover($id_reserva, $nueva_fecha, $nueva_hora, $motivo !== '' ? $motivo : null);

    if ($resultado === null) {
        echo json_encode(['ok' => false, 'error' => 'Ese hueco ya no está disponible. Selecciona otra hora.']);
        exit;
    }

    $cli = new Usuario(
        $resultado['id_cliente'], null,
        $resultado['cliente_nombre'], $resultado['cliente_email'],
        null, null, null, 0, 'cliente'
    );

    NotificadorReserva::enviarCambio($cli, [
        'servicio' => $resultado['servicio_nombre'],
        'fecha'    => fechaHumana($resultado['fecha_vieja']),
        'hora'     => $resultado['hora_vieja'],
    ], [
        'fecha'    => fechaHumana($resultado['fecha_nueva']),
        'hora'     => $resultado['hora_nueva'],
    ], $motivo !== '' ? $motivo : null);

    echo json_encode(['ok' => true]);
    exit;
}

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
        if (!Csrf::validarToken('agenda', $_POST['csrf_token'] ?? '')) {
            $error_agenda = 'Token de seguridad inválido.';
        } elseif ($_POST['accion'] === 'cancelar_dia') {
            $motivo = trim($_POST['motivo_cancelacion'] ?? '');
            if ($motivo === '') {
                $error_agenda = 'Debes indicar el motivo de la cancelación.';
            } else {
                $conexion = BD::obtenerConexion();
                $horaActual = date('H:i:s');
                $antes = $conexion->prepare(
                    "SELECT r.id_cliente, r.hora, u.nombre, u.email, s.nombre AS servicio_nombre
                     FROM reservas r
                     JOIN servicios s ON r.id_servicio = s.id
                     JOIN usuarios u ON r.id_cliente = u.id AND u.activo = 1
                     WHERE r.id_barbero = :b AND r.fecha = :f AND r.estado = 'confirmada'
                       AND r.hora >= :hora_actual"
                );
                $antes->execute([':b' => ID_BARBERO, ':f' => $fecha_seleccionada, ':hora_actual' => $horaActual]);
                $aCancelar = $antes->fetchAll(PDO::FETCH_ASSOC);

                $canceladas = Reserva::cancelarPorDia(ID_BARBERO, $fecha_seleccionada, $motivo);
                if ($canceladas > 0) {
                    require_once __DIR__ . '/../clases/NotificadorReserva.php';
                    foreach ($aCancelar as $r) {
                        $cli = new Usuario(
                            (int)$r['id_cliente'], null,
                            $r['nombre'], $r['email'],
                            null, null, null, 0, 'cliente'
                        );
                        $_f = $fecha_seleccionada;
                        NotificadorReserva::enviarCancelacion($cli, [
                            'servicio' => $r['servicio_nombre'] ?? '',
                            'fecha'    => $_f !== '' ? fechaHumana($_f) : '',
                            'hora'     => $r['hora'] ?? '',
                        ], $motivo);
                    }
                    $_SESSION['toast'] = ['type' => 'success', 'message' => "$canceladas cita(s) cancelada(s) por: $motivo"];
                } else {
                    $_SESSION['toast'] = ['type' => 'info', 'message' => 'No había citas confirmadas para cancelar en esta fecha.'];
                }
                redirigir('index.php?fecha=' . $fecha_seleccionada);
            }
        }
    }

// ── Password banner (solo si tiene contraseña, no Google auth) ──
$diasPass = null;
$passBanner = '';
if ($_SESSION['usuario']->tienePassword()) {
    try {
        $ultimoCambio = Usuario::obtenerFechaUltimoCambioPassword((int)$_SESSION['usuario']->getId());
        if ($ultimoCambio) {
            $diasPass = floor((time() - strtotime($ultimoCambio)) / 86400);
            if ($diasPass >= 90) {
                $passBanner = 'danger';
            } elseif ($diasPass >= 80) {
                $passBanner = 'warning';
            }
        }
    } catch (Exception $e) {}
}

$dt_seleccionada = new DateTimeImmutable($fecha_seleccionada);
$fecha_anterior  = $dt_seleccionada->modify('-1 day')->format('Y-m-d');
$fecha_siguiente = $dt_seleccionada->modify('+1 day')->format('Y-m-d');

$hoy       = new DateTimeImmutable('today');
$es_hoy    = $fecha_seleccionada === $hoy->format('Y-m-d');
$es_pasado = $dt_seleccionada < $hoy;

// ── Datos ──
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
$token_mover   = Csrf::generarToken('mover_cita');
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
            <input type="date" value="<?= h($fecha_seleccionada) ?>"
                   onchange="window.location.href='?fecha='+this.value"
                   class="w-[140px] sm:w-[155px] h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] text-[0.72rem] px-2.5 cursor-pointer transition-all hover:border-[var(--gold-brd)] focus:border-[var(--gold)] focus:outline-hidden [color-scheme:dark]">
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

    <?php if ($passBanner !== ''): ?>
    <div class="mb-5 px-4 py-3 rounded-xl border flex items-start gap-3 text-[0.75rem] leading-relaxed <?= $passBanner === 'danger'
        ? 'bg-red-500/10 border-red-500/20 text-red-400'
        : 'bg-amber-500/10 border-amber-500/20 text-amber-300' ?>">
        <i class="bi bi-shield-exclamation mt-0.5 text-sm shrink-0"></i>
        <div>
            <strong class="block text-[0.65rem] uppercase tracking-widest mb-0.5">
                <?= $passBanner === 'danger' ? '⚠️ Contraseña sin cambiar' : '⏳ Cambio de contraseña pendiente' ?>
            </strong>
            <span>No cambias tu contraseña desde hace <strong><?= number_format($diasPass) ?> días</strong>.
            <a href="../cliente/cambiar_password.php" class="underline underline-offset-2 transition <?= $passBanner === 'danger' ? 'text-red-300 hover:text-red-100' : 'text-amber-200 hover:text-amber-100' ?>">
                Cambiarla ahora
            </a></span>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $lunes_semana = obtenerLunesDeSemanaStr($fecha_seleccionada);
    $label_dia = $es_hoy ? 'hoy' : ucfirst(nombreDiaCorto((int)date('N', strtotime($fecha_seleccionada))));
    $ruta_semanal = 'historial_semanal.php?semana=' . h($lunes_semana);
    ?>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div onclick="window.location.href='<?= $ruta_semanal ?>'" class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03] cursor-pointer transition-all duration-200 hover:border-[#d4af37]/30 hover:-translate-y-[1px]">
            <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Citas · <?= $label_dia ?></span>
            <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_dia['total'] ?></span>
            <span class="text-[0.62rem] text-[var(--tx-d)]"><?= $resumen_dia['confirmadas'] ?> confirmada<?= $resumen_dia['confirmadas'] !== 1 ? 's' : '' ?></span>
        </div>
        <div onclick="window.location.href='<?= $ruta_semanal ?>'" class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03] cursor-pointer transition-all duration-200 hover:border-[#d4af37]/30 hover:-translate-y-[1px]">
            <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Completadas</span>
            <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_dia['completadas'] ?></span>
            <?php if ($resumen_dia['no_presentados'] > 0): ?>
                <span class="text-[0.62rem] text-[#888]"><?= $resumen_dia['no_presentados'] ?> no show</span>
            <?php else: ?>
                <span class="text-[0.62rem] text-[var(--tx-d)]">de <?= $resumen_dia['total'] ?> totales</span>
            <?php endif; ?>
        </div>
        <div onclick="window.location.href='<?= $ruta_semanal ?>'" class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--gold-brd)] bg-[var(--gold-dim)] cursor-pointer transition-all duration-200 hover:opacity-80 hover:-translate-y-[1px]">
            <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--gold)]/70">Ingresos · <?= $label_dia ?></span>
            <span class="text-[1.6rem] font-semibold leading-none text-[var(--gold)]" style="font-family: var(--pf);"><?= number_format($resumen_dia['ingresos'], 0, ',', '.') ?>€</span>
            <span class="text-[0.62rem] text-[var(--gold)]/60">de citas completadas</span>
        </div>
        <?php if ($resumen_semana !== null): ?>
            <div onclick="window.location.href='<?= $ruta_semanal ?>'" class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03] cursor-pointer transition-all duration-200 hover:border-[#d4af37]/30 hover:-translate-y-[1px]">
                <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Esta semana</span>
                <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_semana['total'] ?></span>
                <span class="text-[0.62rem] text-[var(--tx-d)]"><?= number_format($resumen_semana['ingresos'], 0, ',', '.') ?>€ completados</span>
            </div>
        <?php else: ?>
            <div onclick="window.location.href='<?= $ruta_semanal ?>'" class="glow-card flex flex-col gap-1 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/[0.03] cursor-pointer transition-all duration-200 hover:border-[#d4af37]/30 hover:-translate-y-[1px]">
                <span class="text-[0.6rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Canceladas · <?= $label_dia ?></span>
                <span class="text-[1.6rem] font-semibold leading-none text-[var(--tx)]" style="font-family: var(--pf);"><?= $resumen_dia['canceladas'] ?></span>
                <span class="text-[0.62rem] text-[var(--tx-d)]">ese día</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$es_pasado && !$dia_bloqueado_completo && $total_reservados > 0): ?>
        <button type="button" onclick="abrirCancelarDia()"
                class="mb-5 w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-red-900/30 bg-red-900/10 text-[#e07070] text-[0.7rem] font-semibold tracking-wider hover:bg-red-900/20 hover:border-red-500/40 transition-all cursor-pointer">
            <i class="bi bi-x-lg text-[0.75rem]"></i>
            Cancelar todo el día
        </button>
    <?php endif; ?>

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
                <p class="text-[0.7rem] text-[var(--tx-d)] max-w-xs">Este día ha sido bloqueado por completo.</p>
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
                    <div onclick="window.location.href='ficha_cliente.php?id_reserva=<?= (int)$res['id'] ?>&amp;fecha=<?= h($fecha_seleccionada) ?>'"
                         class="slot-card glow-card flex items-start gap-3 px-4 py-3 rounded-xl border min-h-[64px] cursor-pointer transition-all duration-150 border-[var(--brd)] border-l-4 <?php
                            $__est = $res['estado'];
                            if ($__est === 'confirmada') {
                                echo 'border-l-[var(--gold)] bg-yellow-600/4';
                            } elseif ($__est === 'completada') {
                                echo 'border-l-emerald-500/50 bg-emerald-500/[0.015] opacity-85';
                            } elseif ($__est === 'no_presentado' || $__est === 'cancelada') {
                                echo 'border-l-red-800/50 bg-white/[0.02] opacity-60';
                            }
                        ?>">

                        <span class="inline-flex items-center justify-center min-w-[46px] px-2 py-1 rounded-md bg-black/30 text-[0.68rem] font-semibold text-[var(--tx)] shrink-0 mt-0.5 leading-none"><?= h($slot['hora']) ?></span>

                        <?php if ($res['estado'] === 'confirmada' || $res['estado'] === 'pendiente'): ?>
                            <button type="button" onclick="event.stopPropagation(); abrirMoverCita(<?= (int)$res['id'] ?>)"
                                    class="ml-auto shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-[var(--tx-d)] hover:text-[var(--gold)] hover:bg-[var(--gold-dim)] transition-all cursor-pointer"
                                    title="Reagendar / Mover cita">
                                <i class="bi bi-arrow-left-right text-[0.75rem]"></i>
                            </button>
                        <?php endif; ?>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[0.88rem] font-semibold text-[var(--tx)] truncate"><?= h($res['cliente_nombre']) ?></span>
                                <?php if ($res['estado'] === 'completada'): ?>
                                    <span class="text-[0.5rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Hecho</span>
                                <?php elseif ($res['estado'] === 'no_presentado'): ?>
                                    <span class="text-[0.5rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20">No show</span>
                                <?php elseif ($res['estado'] === 'cancelada'): ?>
                                    <span class="text-[0.5rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20">Cancelada</span>
                                <?php endif; ?>
                                <?php if ($res['estado'] === 'confirmada' && (int)$res['puntos_fidelidad'] >= 10): ?>
                                    <span class="text-[0.45rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/25">Fidelidad</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[0.65rem] text-[var(--tx-m)] mt-0.5 truncate"><?= h($res['servicio_nombre']) ?></div>
                            <div class="flex gap-3 mt-1">
                                <span class="flex items-center gap-1 text-[0.6rem] text-[var(--tx-m)]"><i class="bi bi-clock text-[0.7rem]"></i> <?= (int)$res['duracion_historica'] ?> min</span>
                                <span class="flex items-center gap-1 text-[0.6rem] text-[var(--tx-m)]"><i class="bi bi-currency-euro text-[0.7rem]"></i> <?php if (!empty($res['gratis']) || ($res['estado'] === 'confirmada' && (int)$res['puntos_fidelidad'] >= 10)): ?><span class="text-emerald-500/70 uppercase font-bold text-[0.5rem] tracking-wider">GRATIS</span><?php else: ?><?= number_format((float)$res['precio_historico'], 2, ',', '.') ?><?php endif; ?></span>
                            </div>
                        </div>

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

<!-- Modal cancelar día -->
<div id="modalCancelarDia" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200" onclick="if(event.target===this)cerrarCancelarDia()">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl p-6 w-full max-w-md shadow-2xl scale-95 transition-transform duration-200" id="modalCancelarDiaContent">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8]">Cancelar todo el día</h3>
            <button onclick="cerrarCancelarDia()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
            <input type="hidden" name="accion" value="cancelar_dia">

            <div class="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-[0.75rem] text-red-400 space-y-1">
                <p class="font-semibold"><i class="bi bi-exclamation-triangle-fill mr-1.5"></i>Se cancelarán todas las citas confirmadas del <strong><?= h(fechaHumana($fecha_seleccionada)) ?></strong>.</p>
                <p class="text-[0.65rem] text-red-400/70">Esta acción no se puede deshacer.</p>
            </div>

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Motivo de cancelación</label>
                <textarea name="motivo_cancelacion" id="inputMotivoCancelarDia" rows="3" required
                          placeholder="Ej: Cierre por vacaciones, emergencia personal..."
                          class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="button" onclick="cerrarCancelarDia()"
                        class="flex-1 px-4 py-2.5 rounded-lg border border-white/[0.08] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider text-[#888] hover:bg-white/5 transition-all cursor-pointer">
                    Volver
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-red-800 text-[#f5f0e8] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider uppercase hover:opacity-90 transition-all cursor-pointer"
                        onclick="return confirm('¿Estás seguro de cancelar TODAS las citas de este día?')">
                    Cancelar día
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal mover cita -->
<div id="modalMoverCita" class="fixed inset-0 z-[9999] bg-black/80 hidden flex items-end lg:items-center justify-center p-0 lg:p-4 opacity-0 transition-opacity duration-200"
     onclick="if(event.target===this)cerrarMoverCita()">
    <div id="modalMoverCitaContent"
         class="w-full lg:max-w-[600px] bg-[#1a1a1a] border border-white/[0.08] rounded-t-2xl lg:rounded-2xl shadow-2xl translate-y-0 lg:translate-y-0 lg:scale-95 transition-all duration-300 ease-out flex flex-col max-h-[85vh] lg:max-h-auto"
         onclick="event.stopPropagation()">

        <!-- Header -->
        <div class="flex items-start justify-between p-5 pb-3 border-b border-white/[0.06] shrink-0">
            <div class="min-w-0">
                <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8]">Mover cita</h3>
                <p class="text-[0.65rem] text-[var(--tx-m)] mt-0.5 truncate" id="moverResumen">Selecciona nueva fecha y hora</p>
            </div>
            <button onclick="cerrarMoverCita()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer shrink-0 ml-3">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>

        <!-- Cuerpo -->
        <div class="p-5 pt-4 overflow-y-auto flex-1">
            <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Nueva fecha</label>
            <input type="date" id="moverFecha" onchange="cargarSlots()"
                   class="w-full h-11 bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all [color-scheme:dark] cursor-pointer mb-4">

            <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Nueva hora</label>
            <div id="moverSlotsContainer" class="mb-4">
                <div class="flex items-center justify-center py-6 text-[var(--tx-d)]">
                    <i class="bi bi-arrow-repeat animate-spin mr-2"></i>
                    <span class="text-[0.7rem]">Cargando horarios...</span>
                </div>
            </div>

            <details class="group">
                <summary class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] cursor-pointer select-none list-none flex items-center gap-2 mb-1.5">
                    <i class="bi bi-chevron-right text-[0.6rem] group-open:rotate-90 transition-transform"></i>
                    Añadir motivo <span class="text-[var(--tx-d)] font-normal normal-case tracking-normal">(opcional)</span>
                </summary>
                <textarea id="moverMotivo" rows="2"
                          placeholder="Ej: El cliente pidió cambio de hora..."
                          class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all resize-none mt-1"></textarea>
            </details>
        </div>

        <!-- Footer -->
        <div class="flex gap-3 p-5 pt-3 border-t border-white/[0.06] shrink-0">
            <button type="button" onclick="cerrarMoverCita()"
                    class="flex-1 px-4 py-2.5 rounded-lg border border-white/[0.08] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider text-[#888] hover:bg-white/5 transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="button" onclick="confirmarMoverCita()"
                    class="flex-1 px-4 py-2.5 rounded-lg bg-[var(--gold)] text-[#0d0d0d] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider uppercase hover:opacity-90 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    id="btnConfirmarMover">
                Confirmar cambio
            </button>
        </div>
    </div>
</div>

<script>
/* ── Mover cita ── */
let moverState = { id: 0, fechaBase: '' };

function abrirMoverCita(id) {
    moverState.id = id;
    moverState.fechaBase = '';
    moverState.selectedHora = '';

    const modal = document.getElementById('modalMoverCita');
    const content = document.getElementById('modalMoverCitaContent');

    document.getElementById('moverResumen').textContent = 'Cargando...';
    document.getElementById('moverSlotsContainer').innerHTML =
        '<div class="flex items-center justify-center py-6 text-[var(--tx-d)]">' +
        '<i class="bi bi-arrow-repeat animate-spin mr-2"></i>' +
        '<span class="text-[0.7rem]">Cargando horarios...</span></div>';
    document.getElementById('moverMotivo').value = '';
    document.getElementById('btnConfirmarMover').disabled = false;

    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('translate-y-0', 'lg:scale-95');
        content.classList.add('translate-y-0', 'lg:scale-100');
    });

    // Sin fecha → el backend usa la fecha actual de la reserva
    fetch('ajax_mover_cita_slots.php?id_reserva=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('moverFecha').value = data.reserva_fecha;
                moverState.fechaBase = data.reserva_fecha;
                renderizarSlots(data.slots);
                document.getElementById('moverResumen').textContent = data.resumen || '';
            } else {
                document.getElementById('moverSlotsContainer').innerHTML =
                    '<div class="flex items-center justify-center py-6 text-red-400">' +
                    '<i class="bi bi-exclamation-circle mr-2"></i>' +
                    '<span class="text-[0.7rem]">' + (data.error || 'Error') + '</span></div>';
            }
        })
        .catch(() => {
            document.getElementById('moverSlotsContainer').innerHTML =
                '<div class="flex items-center justify-center py-6 text-red-400">' +
                '<i class="bi bi-exclamation-circle mr-2"></i>' +
                '<span class="text-[0.7rem]">Error al cargar horarios</span></div>';
        });
}

function cerrarMoverCita() {
    const modal = document.getElementById('modalMoverCita');
    const content = document.getElementById('modalMoverCitaContent');
    modal.classList.add('opacity-0');
    content.classList.remove('translate-y-0', 'lg:scale-100');
    content.classList.add('lg:scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.querySelectorAll('#moverSlotsContainer .slot-pill.selected').forEach(el => el.classList.remove('selected'));
    }, 250);
}

function cargarSlots() {
    const fecha = document.getElementById('moverFecha').value;
    if (!fecha || moverState.id === 0) return;

    document.getElementById('moverSlotsContainer').innerHTML =
        '<div class="flex items-center justify-center py-6 text-[var(--tx-d)]">' +
        '<i class="bi bi-arrow-repeat animate-spin mr-2"></i>' +
        '<span class="text-[0.7rem]">Cargando horarios...</span></div>';
    document.querySelectorAll('#moverSlotsContainer .slot-pill.selected').forEach(el => el.classList.remove('selected'));

    fetch('ajax_mover_cita_slots.php?id_reserva=' + moverState.id + '&fecha=' + encodeURIComponent(fecha))
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                renderizarSlots(data.slots);
            } else {
                document.getElementById('moverSlotsContainer').innerHTML =
                    '<div class="flex items-center justify-center py-6 text-red-400">' +
                    '<i class="bi bi-exclamation-circle mr-2"></i>' +
                    '<span class="text-[0.7rem]">' + (data.error || 'Error') + '</span></div>';
            }
        })
        .catch(() => {
            document.getElementById('moverSlotsContainer').innerHTML =
                '<div class="flex items-center justify-center py-6 text-red-400">' +
                '<i class="bi bi-exclamation-circle mr-2"></i>' +
                '<span class="text-[0.7rem]">Error de conexión</span></div>';
        });
}

function renderizarSlots(slots) {
    const container = document.getElementById('moverSlotsContainer');
    if (!slots || slots.length === 0) {
        container.innerHTML =
            '<div class="flex flex-col items-center justify-center py-8 text-[var(--tx-d)] gap-2">' +
            '<i class="bi bi-clock-history text-xl"></i>' +
            '<span class="text-[0.7rem]">No hay horarios disponibles para esta fecha</span></div>';
        return;
    }

    let html = '<div class="grid grid-cols-3 sm:grid-cols-4 gap-2">';
    slots.forEach(h => {
        html += '<button type="button" class="slot-pill w-full py-2.5 px-2 rounded-lg border border-white/[0.08] bg-white/[0.03] text-[0.78rem] font-medium text-[var(--tx)] hover:border-[var(--gold-brd)] hover:bg-[var(--gold-dim)] hover:text-[var(--gold)] transition-all cursor-pointer focus:outline-hidden" onclick="seleccionarSlot(this, \'' + h + '\')">' + h + '</button>';
    });
    html += '</div>';
    container.innerHTML = html;
}

function seleccionarSlot(btn, hora) {
    document.querySelectorAll('#moverSlotsContainer .slot-pill').forEach(el => {
        if (el.classList.contains('selected')) {
            el.classList.remove('selected');
            el.style.borderColor = '';
            el.style.backgroundColor = '';
            el.style.color = '';
        }
    });
    btn.classList.add('selected');
    btn.style.borderColor = 'var(--gold)';
    btn.style.backgroundColor = 'var(--gold-dim)';
    btn.style.color = 'var(--gold)';
    moverState.selectedHora = hora;
}

function confirmarMoverCita() {
    const hora = moverState.selectedHora;
    const fecha = document.getElementById('moverFecha').value;
    const motivo = document.getElementById('moverMotivo').value.trim();

    if (!fecha || !hora) {
        if (window.Toast) Toast.mostrar('error', 'Selecciona una fecha y hora.');
        return;
    }

    const btn = document.getElementById('btnConfirmarMover');
    btn.disabled = true;
    btn.textContent = 'Moviendo...';

    const params = new URLSearchParams();
    params.append('accion', 'mover_cita');
    params.append('csrf_token', '<?= h($token_mover) ?>');
    params.append('id_reserva', moverState.id.toString());
    params.append('nueva_fecha', fecha);
    params.append('nueva_hora', hora);
    params.append('motivo', motivo);

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            cerrarMoverCita();
            if (window.Toast) Toast.mostrar('success', 'Cita movida correctamente. Cliente notificado.');
            setTimeout(() => window.location.reload(), 600);
        } else {
            if (window.Toast) Toast.mostrar('error', data.error || 'No se pudo mover la cita.');
            cargarSlots(); // refrescar slots
        }
    })
    .catch(() => {
        if (window.Toast) Toast.mostrar('error', 'Error de conexión.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Confirmar cambio';
    });
}

function abrirCancelarDia() {
    const modal = document.getElementById('modalCancelarDia');
    const content = document.getElementById('modalCancelarDiaContent');
    const input = document.getElementById('inputMotivoCancelarDia');
    input.value = '';
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}

function cerrarCancelarDia() {
    const modal = document.getElementById('modalCancelarDia');
    const content = document.getElementById('modalCancelarDiaContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}
</script>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

</body>
</html>
