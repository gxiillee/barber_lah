<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Reserva.php';
require_once __DIR__ . '/../clases/Administrador.php';
require_once __DIR__ . '/../clases/Csrf.php';
require_once __DIR__ . '/../clases/helpers.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

if (!defined('ID_BARBERO')) define('ID_BARBERO', 1);

$semana_raw = $_GET['semana'] ?? obtenerLunesDeSemanaStr(date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $semana_raw)) $semana_raw = obtenerLunesDeSemanaStr(date('Y-m-d'));

$lunes = new DateTimeImmutable($semana_raw);
$domingo = $lunes->modify('+6 days');

$semana_anterior = $lunes->modify('-7 days')->format('Y-m-d');
$semana_siguiente = $lunes->modify('+7 days')->format('Y-m-d');
$es_semana_actual = $lunes->format('Y-m-d') === obtenerLunesDeSemanaStr(date('Y-m-d'));

$inicio = $lunes->format('Y-m-d');
$fin = $domingo->format('Y-m-d');

$reservas = Reserva::obtenerEnRango(ID_BARBERO, $inicio, $fin);

$total_citas = 0;
$total_ingresos = 0;
$conteo = ['completada' => 0, 'confirmada' => 0, 'cancelada' => 0, 'no_presentado' => 0];

$dias_semana = [];
for ($i = 0; $i < 7; $i++) {
    $dia = $lunes->modify("+$i days");
    $fecha_str = $dia->format('Y-m-d');
    $dias_semana[$fecha_str] = [
        'label' => nombreDia((int)$dia->format('N')) . ' ' . $dia->format('d/m'),
        'corto' => nombreDiaCorto((int)$dia->format('N')),
        'num' => $dia->format('d'),
        'mes' => $dia->format('m'),
        'reservas' => [],
    ];
}

foreach ($reservas as $r) {
    $dias_semana[$r['fecha']]['reservas'][] = $r;
}

// Mostrar solo dias pasados + hoy, ordenados del mas reciente al lunes
$hoy_str = date('Y-m-d');
$reordenado = [];
for ($i = 6; $i >= 0; $i--) {
    $f = $lunes->modify("+$i days")->format('Y-m-d');
    if ($f <= $hoy_str && isset($dias_semana[$f])) $reordenado[$f] = $dias_semana[$f];
}
$dias_semana = $reordenado;

// Recalcular stats solo sobre los dias mostrados
foreach ($dias_semana as $dia) {
    foreach ($dia['reservas'] as $r) {
        $total_citas++;
        $est = $r['estado'];
        if (isset($conteo[$est])) $conteo[$est]++;
        if ($est === 'completada' && empty($r['gratis'])) $total_ingresos += (float)$r['precio_historico'];
    }
}

$pagina_activa = 'agenda';
$titulo_semana = 'Semana del ' . $lunes->format('d/m/Y') . ' al ' . $domingo->format('d/m/Y');
$token_csrf = Csrf::generarToken('agenda');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Semanal — Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
    <style>
        @media (min-width: 1024px) { .col-header { scroll-margin-top: 40px; } }
        .scrollbar-none { -ms-overflow-style:none; scrollbar-width:none; }
        .scrollbar-none::-webkit-scrollbar { display:none; }
    </style>
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans" style="background:#181818;">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-16 lg:max-w-[1200px]">

    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="index.php" class="inline-flex items-center gap-1.5 text-[0.65rem] font-semibold tracking-wide text-[var(--tx-m)] hover:text-[var(--gold)] transition-colors mb-1">
                <i class="bi bi-arrow-left"></i> Volver a Agenda
            </a>
            <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Historial semanal</h1>
            <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">
                <?= h($titulo_semana) ?>
                <?php if ($es_semana_actual): ?>
                    <span class="ml-2 bg-[var(--gold)] text-[#0d0d0d] text-[0.48rem] font-bold tracking-[0.18em] px-2 py-[3px] rounded-full uppercase">ACTUAL</span>
                <?php endif; ?>
            </p>
        </div>
        <nav class="flex items-center gap-2">
            <input type="date" value="<?= h($lunes->format('Y-m-d')) ?>"
                   onchange="var d=new Date(this.value);var w=d.getDate()-d.getDay()+(d.getDay()===0?-6:1);var m=new Date(d.getFullYear(),d.getMonth(),w);window.location.href='?semana='+m.getFullYear()+'-'+String(m.getMonth()+1).padStart(2,'0')+'-'+String(m.getDate()).padStart(2,'0')"
                   class="w-[150px] h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] text-[0.72rem] px-2.5 cursor-pointer transition-all hover:border-[var(--gold-brd)] focus:border-[var(--gold)] focus:outline-hidden [color-scheme:dark]">
            <a href="?semana=<?= h($semana_anterior) ?>"
               class="w-9 h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] flex items-center justify-center transition-all hover:bg-[var(--gold-dim)] hover:border-[var(--gold-brd)] hover:text-[var(--gold)]">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php if (!$es_semana_actual): ?>
                <a href="?semana=<?= h(obtenerLunesDeSemanaStr(date('Y-m-d'))) ?>"
                   class="px-3.5 h-9 rounded-lg border border-[var(--gold-brd)] bg-[var(--gold-dim)] text-[var(--gold)] text-[0.65rem] font-semibold tracking-widest uppercase flex items-center transition-all hover:bg-white/10">
                    Esta semana
                </a>
            <?php endif; ?>
            <a href="?semana=<?= h($semana_siguiente) ?>"
               class="w-9 h-9 rounded-lg border border-[var(--brd)] bg-white/5 text-[var(--tx-m)] flex items-center justify-center transition-all hover:bg-[var(--gold-dim)] hover:border-[var(--gold-brd)] hover:text-[var(--gold)]">
                <i class="bi bi-chevron-right"></i>
            </a>
        </nav>
    </div>

    <div class="flex sm:flex-wrap items-center justify-start sm:justify-center gap-4 sm:gap-x-7 overflow-x-auto whitespace-nowrap sm:whitespace-normal mb-6 px-4 sm:px-6 py-3 rounded-xl border border-[var(--brd)] bg-white/[0.02] scrollbar-none">
        <span class="flex items-center gap-1.5 text-[0.7rem] sm:text-[0.8rem] text-[var(--tx-m)] shrink-0">
            <i class="bi bi-calendar3 text-[0.6rem] sm:text-[0.65rem]"></i>
            <strong class="text-[var(--tx)]"><?= $total_citas ?></strong> <span class="hidden sm:inline ml-1">total</span>
        </span>
        <span class="w-[2px] h-4 bg-white/10 rounded-full shrink-0"></span>
        <span class="flex items-center gap-1.5 text-[0.7rem] sm:text-[0.8rem] text-[var(--tx-m)] shrink-0">
            <i class="bi bi-check-circle text-emerald-400/60 text-[0.6rem] sm:text-[0.65rem]"></i>
            <strong class="text-emerald-400/80"><?= $conteo['completada'] ?></strong> <span class="hidden sm:inline ml-1">completadas</span>
        </span>
        <span class="w-[2px] h-4 bg-white/10 rounded-full shrink-0"></span>
        <span class="flex items-center gap-1.5 text-[0.7rem] sm:text-[0.8rem] text-[var(--tx-m)] shrink-0">
            <i class="bi bi-x-circle text-red-400/50 text-[0.6rem] sm:text-[0.65rem]"></i>
            <strong class="text-red-400/70"><?= $conteo['cancelada'] ?></strong> <span class="hidden sm:inline ml-1">canceladas</span>
        </span>
        <span class="w-[2px] h-4 bg-white/10 rounded-full shrink-0"></span>
        <span class="flex items-center gap-1.5 text-[0.7rem] sm:text-[0.8rem] text-[var(--tx-m)] shrink-0">
            <i class="bi bi-person-x text-amber-400/50 text-[0.6rem] sm:text-[0.65rem]"></i>
            <strong class="text-amber-400/70"><?= $conteo['no_presentado'] ?></strong> <span class="hidden sm:inline ml-1">no show</span>
        </span>

        <span class="w-[2px] h-4 bg-white/10 rounded-full shrink-0"></span>
        <span class="flex items-center gap-1.5 text-[0.7rem] sm:text-[0.8rem] text-[var(--gold)]/70 shrink-0">
            <i class="bi bi-currency-euro text-[0.6rem] sm:text-[0.65rem]"></i>
            <strong class="text-[var(--gold)]"><?= number_format($total_ingresos, 0, ',', '.') ?>€</strong> <span class="hidden sm:inline ml-1">ingresos</span>
        </span>
    </div>

    <?php foreach ($dias_semana as $fecha_str => $dia): ?>
        <?php
        $reservas_dia = $dia['reservas'];
        $es_hoy = $fecha_str === date('Y-m-d');
        $total_dia = count($reservas_dia);
        $ingresos_dia = 0;
        foreach ($reservas_dia as $r) { if ($r['estado'] === 'completada' && empty($r['gratis'])) $ingresos_dia += (float)$r['precio_historico']; }
        ?>
        <div class="mb-6">
            <div class="flex items-center justify-between py-2">
                <div class="flex items-center gap-2">
                    <span class="font-['Playfair_Display'] text-[0.95rem] font-semibold text-[var(--tx)]"><?= h($dia['label']) ?></span>
                    <?php if ($es_hoy): ?>
                        <span class="bg-[var(--gold)] text-[#0d0d0d] text-[0.4rem] font-bold tracking-[0.18em] px-1.5 py-[2px] rounded-full uppercase">HOY</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2 text-[0.6rem] text-[var(--tx-m)]">
                    <span><?= $total_dia ?> cita<?= $total_dia !== 1 ? 's' : '' ?></span>
                    <?php if ($ingresos_dia > 0): ?>
                        <span class="text-[var(--gold)]">· <?= number_format($ingresos_dia, 0, ',', '.') ?>€</span>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="border-0 border-t border-white/[0.06] mb-2">

            <?php if (empty($reservas_dia)): ?>
                <div class="py-4 text-center">
                    <p class="font-['Montserrat'] text-[0.7rem] text-[#555] m-0">Sin reservas este día</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col">
                    <?php foreach ($reservas_dia as $r): ?>
                        <div onclick="window.location.href='ficha_cliente.php?id_reserva=<?= (int)$r['id'] ?>&amp;fecha=<?= h($fecha_str) ?>'"
                             class="flex items-center gap-3 sm:gap-4 px-1 py-2.5 transition-colors duration-150 cursor-pointer rounded-lg hover:bg-white/[0.02]">
                            <div class="text-[0.7rem] font-semibold text-[var(--tx-m)] min-w-[32px] shrink-0 text-center">
                                <?= h(substr($r['hora'], 0, 5)) ?>
                            </div>
                            <div class="w-[2px] h-8 rounded-full shrink-0 <?= $r['estado'] === 'completada' ? 'bg-emerald-500/40' : ($r['estado'] === 'cancelada' || $r['estado'] === 'no_presentado' ? 'bg-red-500/40' : 'bg-white/10') ?>"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[0.82rem] font-semibold text-[var(--tx)] truncate"><?= h($r['cliente_nombre']) ?></span>
                                    <?php if ($r['estado'] === 'completada'): ?>
                                        <span class="text-[0.48rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shrink-0">Hecho</span>
                                    <?php elseif ($r['estado'] === 'cancelada'): ?>
                                        <span class="text-[0.48rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20 shrink-0">Cancelada</span>
                                    <?php elseif ($r['estado'] === 'no_presentado'): ?>
                                        <span class="text-[0.48rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20 shrink-0">No show</span>
                                    <?php elseif ($r['estado'] === 'confirmada'): ?>
                                        <span class="text-[0.48rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-[var(--gold-dim)] text-[var(--gold)] border-[var(--gold-brd)] shrink-0">Próxima</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[0.62rem] text-[var(--tx-m)] truncate"><?= h($r['servicio_nombre']) ?></span>
                                    <span class="text-[0.55rem] text-[var(--tx-d)]">·</span>
                                    <span class="text-[0.6rem] text-[var(--tx-d)]"><?= (int)$r['duracion_historica'] ?>min</span>
                                </div>
                            </div>
                            <div class="text-[0.75rem] font-semibold shrink-0 text-right min-w-[50px]">
                                <?php if ($r['estado'] === 'completada'): ?>
                                    <?php if (!empty($r['gratis'])): ?>
                                        <span class="text-emerald-400/60 text-[0.55rem] uppercase tracking-wider font-bold">GRATIS</span>
                                    <?php else: ?>
                                        <span class="text-emerald-400"><?= number_format((float)$r['precio_historico'], 0, ',', '.') ?>€</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-[var(--tx-d)]"><?= number_format((float)$r['precio_historico'], 0, ',', '.') ?>€</span>
                                <?php endif; ?>
                            </div>
                            <i class="bi bi-chevron-right text-[0.55rem] text-[var(--tx-d)] shrink-0"></i>
                        </div>
                        <?php if ($r['estado'] === 'cancelada' && !empty($r['motivo_cancelacion'])): ?>
                            <div class="pl-[48px] sm:pl-[58px] pb-1 -mt-0.5">
                                <div class="flex items-start gap-1.5">
                                    <i class="bi bi-chat-quote text-[#e07070] text-[0.5rem] mt-[2px] shrink-0"></i>
                                    <span class="font-['Montserrat'] text-[0.55rem] text-[#e07070]/70 leading-relaxed"><?= h($r['motivo_cancelacion']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

</body>
</html>
