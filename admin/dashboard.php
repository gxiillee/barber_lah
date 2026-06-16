<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Administrador.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

$resumen_mes  = Administrador::obtenerResumenMesActual();
$ingresos_mensuales = Administrador::obtenerIngresosMensuales(12);
$servicios    = Administrador::obtenerServiciosMasVendidos(6);
$no_shows     = Administrador::obtenerTasaNoShows();
$clientes_clv = Administrador::obtenerClientesNuevosVsRecurrentes();
$resumen_sem  = Administrador::obtenerResumenSemanaActual();

$max_ingreso = max(array_column($ingresos_mensuales, 'ingresos')) ?: 1;
$pagina_activa = 'dashboard';

// Datos para el donut SVG
$circ = 2 * M_PI * 38;
$pct_nuevos = $clientes_clv['pct_nuevos'];
$pct_rec    = $clientes_clv['total'] > 0 ? round(100 - $pct_nuevos, 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Panel Admin · Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-6xl pagina-entrada">

    <div class="flex items-center justify-between mb-7">
        <div>
            <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Dashboard</h1>
            <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Resumen del negocio · <?= mb_strtolower(nombreMes((int)date('m'))) ?> <?= date('Y') ?></p>
        </div>
        <div class="flex items-center gap-3 text-[0.6rem] text-[var(--tx-d)] bg-white/[0.03] border border-[var(--brd)] rounded-xl px-3 py-2">
            <i class="bi bi-calendar3 text-[var(--gold)]"></i>
            <span>Semana: <strong class="text-[var(--tx)]"><?= $resumen_sem['total'] ?></strong> citas · <strong class="text-[var(--gold)]"><?= number_format($resumen_sem['ingresos'], 0, ',', '.') ?>€</strong></span>
        </div>
    </div>

    <!-- ── KPIs ── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8 stagger-container">

        <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card stagger-item">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[0.55rem] uppercase tracking-[0.15em] font-semibold text-[var(--tx-d)]">Ingresos</span>
                <span class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-[0.75rem]">
                    <i class="bi bi-currency-euro"></i>
                </span>
            </div>
            <div class="text-[1.5rem] font-bold text-[var(--tx)] leading-none mb-1" style="font-family: var(--pf);">
                <?= number_format($resumen_mes['ingresos'], 0, ',', '.') ?>€
            </div>
            <div class="text-[0.6rem] text-[var(--tx-d)] flex items-center gap-1">
                <i class="bi bi-calendar-range"></i> este mes
            </div>
        </div>

        <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card stagger-item">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[0.55rem] uppercase tracking-[0.15em] font-semibold text-[var(--tx-d)]">Citas</span>
                <span class="w-7 h-7 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-400 text-[0.75rem]">
                    <i class="bi bi-calendar-check"></i>
                </span>
            </div>
            <div class="text-[1.5rem] font-bold text-[var(--tx)] leading-none mb-1" style="font-family: var(--pf);">
                <?= $resumen_mes['total'] ?>
                <span class="text-[0.7rem] font-normal text-[var(--tx-m)]">· <?= $resumen_mes['completadas'] ?> hechas</span>
            </div>
            <div class="text-[0.6rem] text-[var(--tx-d)] flex items-center gap-1">
                <i class="bi bi-calendar-range"></i> este mes
            </div>
        </div>

        <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card stagger-item">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[0.55rem] uppercase tracking-[0.15em] font-semibold text-[var(--tx-d)]">No-Shows</span>
                <span class="w-7 h-7 rounded-lg bg-rose-500/10 flex items-center justify-center text-rose-400 text-[0.75rem]">
                    <i class="bi bi-person-x"></i>
                </span>
            </div>
            <div class="text-[1.5rem] font-bold text-[var(--tx)] leading-none mb-1" style="font-family: var(--pf);">
                <?= $no_shows['tasa'] ?>%
                <span class="text-[0.7rem] font-normal text-[var(--tx-m)]">(<?= $no_shows['no_shows'] ?>/<?= $no_shows['total'] ?>)</span>
            </div>
            <div class="text-[0.6rem] text-[var(--tx-d)] flex items-center gap-1">
                <i class="bi bi-bar-chart-line"></i> este mes
            </div>
        </div>

        <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card stagger-item">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[0.55rem] uppercase tracking-[0.15em] font-semibold text-[var(--tx-d)]">Clientes</span>
                <span class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400 text-[0.75rem]">
                    <i class="bi bi-people"></i>
                </span>
            </div>
            <div class="text-[1.5rem] font-bold text-[var(--tx)] leading-none mb-1" style="font-family: var(--pf);">
                <?= $resumen_mes['clientes_nuevos'] ?> <span class="text-[0.7rem] font-normal text-[var(--tx-m)]">nuevos</span>
            </div>
            <div class="text-[0.6rem] text-[var(--tx-d)] flex items-center gap-1">
                <i class="bi bi-person-plus"></i> este mes
            </div>
        </div>
    </div>

    <!-- ── Gráficos ── -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-8">

        <!-- Ingresos mensuales (bar chart) -->
        <div class="lg:col-span-3 rounded-xl border border-[var(--brd)] bg-white/[0.03] p-5 glow-card">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[0.9rem] font-semibold text-[var(--tx)] flex items-center gap-2" style="font-family: var(--pf);">
                    <i class="bi bi-graph-up-arrow text-[var(--gold)]"></i> Ingresos Mensuales
                </h2>
                <span class="text-[0.55rem] uppercase tracking-wider text-[var(--tx-d)] bg-white/5 px-2 py-1 rounded border border-[var(--brd)]">Últimos 12 meses</span>
            </div>

            <div class="flex items-end gap-1.5 sm:gap-2 h-44 sm:h-52 pt-2" style="border-bottom: 1px solid var(--brd);">
                <?php foreach ($ingresos_mensuales as $m): 
                    $altura = $max_ingreso > 0 ? round($m['ingresos'] / $max_ingreso * 100) : 0;
                    $es_actual = ((int)$m['anio'] === (int)date('Y') && (int)$m['mes'] === (int)date('m'));
                ?>
                <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end">
                    <span class="text-[0.5rem] text-[var(--tx-d)] leading-tight text-center <?= $altura < 15 ? 'opacity-0' : '' ?>">
                        <?= $m['ingresos'] > 0 ? number_format($m['ingresos'], 0, ',', '.') . '€' : '' ?>
                    </span>
                    <div class="w-full rounded-t-md transition-all duration-500 ease-out <?= $es_actual ? 'bg-gradient-to-t from-[#d4af37] to-[#e8c84a]' : 'bg-[#d4af37]/60 hover:bg-[#d4af37]/90' ?>"
                         style="height: <?= $altura > 0 ? max($altura, 3) : 2 ?>%; min-height: <?= $m['ingresos'] > 0 ? '4px' : '2px' ?>;">
                    </div>
                    <span class="text-[0.55rem] text-[var(--tx-d)] font-medium <?= $es_actual ? 'text-[var(--gold)]' : '' ?>">
                        <?= h($m['mes_nombre']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Clientes nuevos vs recurrentes (donut) -->
        <div class="lg:col-span-2 rounded-xl border border-[var(--brd)] bg-white/[0.03] p-5 glow-card">
            <h2 class="text-[0.9rem] font-semibold text-[var(--tx)] flex items-center gap-2 mb-5" style="font-family: var(--pf);">
                <i class="bi bi-people-fill text-[var(--gold)]"></i> Clientes
            </h2>

            <div class="flex flex-col items-center">
                <?php if ($clientes_clv['total'] > 0): ?>
                <svg viewBox="0 0 100 100" width="140" height="140" class="mb-4">
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#1a1a1a" stroke-width="13"/>
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#d4af37" stroke-width="13"
                        stroke-dasharray="<?= $circ * $pct_nuevos / 100 ?> <?= $circ ?>"
                        stroke-linecap="butt"
                        transform="rotate(-90 50 50)"
                        class="transition-all duration-700"/>
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#f59e0b" stroke-width="13"
                        stroke-dasharray="<?= $circ * $pct_rec / 100 ?> <?= $circ ?>"
                        stroke-dashoffset="<?= -$circ * $pct_nuevos / 100 ?>"
                        stroke-linecap="butt"
                        transform="rotate(-90 50 50)"
                        class="transition-all duration-700"/>
                </svg>
                <?php else: ?>
                <div class="w-[140px] h-[140px] flex items-center justify-center rounded-full bg-white/[0.03] border border-[var(--brd)] mb-4">
                    <span class="text-[0.65rem] text-[var(--tx-d)]">Sin datos</span>
                </div>
                <?php endif; ?>

                <div class="flex items-center gap-6 text-[0.7rem]">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#d4af37]"></span>
                        <span><strong class="text-[var(--tx)]"><?= $clientes_clv['nuevos'] ?></strong> <span class="text-[var(--tx-d)]">nuevos</span></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
                        <span><strong class="text-[var(--tx)]"><?= $clientes_clv['recurrentes'] ?></strong> <span class="text-[var(--tx-d)]">recurrentes</span></span>
                    </div>
                </div>

                <div class="mt-3 flex gap-4 text-[0.6rem] text-[var(--tx-d)]">
                    <span><?= $pct_nuevos ?>% nuevos</span>
                    <span>·</span>
                    <span><?= $pct_rec ?>% recurrentes</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Servicios más vendidos ── -->
    <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-5 glow-card mb-8">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[0.9rem] font-semibold text-[var(--tx)] flex items-center gap-2" style="font-family: var(--pf);">
                <i class="bi bi-trophy text-[var(--gold)]"></i> Servicios más vendidos
            </h2>
            <span class="text-[0.55rem] uppercase tracking-wider text-[var(--tx-d)] bg-white/5 px-2 py-1 rounded border border-[var(--brd)]">Histórico</span>
        </div>

        <?php if (count($servicios) > 0): 
            $max_serv = max(array_column($servicios, 'total')) ?: 1;
        ?>
        <div class="space-y-2.5">
            <?php foreach ($servicios as $i => $s): 
                $pct_bar = round($s['total'] / $max_serv * 100);
                $medallas = ['text-[#ffd700]', 'text-[#c0c0c0]', 'text-[#cd7f32]'];
                $icono = isset($medallas[$i]) ? 'bi-trophy-fill' : '';
                $color_medalla = $medallas[$i] ?? 'text-[var(--tx-d)]';
            ?>
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/[0.02] border border-white/[0.05] hover:border-[var(--gold-brd)]/20 transition-all">
                <span class="w-6 text-center text-[0.75rem] font-bold <?= $i < 3 ? $color_medalla : 'text-[var(--tx-d)]' ?>">
                    <?= $icono ? '<i class="bi '.$icono.'"></i>' : '#'.($i+1) ?>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[0.8rem] font-medium text-[var(--tx)] truncate"><?= h($s['nombre']) ?></span>
                        <span class="text-[0.65rem] text-[var(--tx-m)] font-medium shrink-0 ml-2"><?= $s['total'] ?> citas</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-white/[0.06] overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-[#d4af37] to-[#e8c84a] transition-all duration-700"
                             style="width: <?= $pct_bar ?>%;"></div>
                    </div>
                </div>
                <span class="text-[0.6rem] text-[var(--tx-d)] shrink-0 hidden sm:block"><?= number_format((float)$s['ingresos'], 0, ',', '.') ?>€</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="flex flex-col items-center justify-center py-10 text-center">
            <i class="bi bi-inbox text-[2rem] text-[var(--tx-d)] mb-2"></i>
            <p class="text-[0.75rem] text-[var(--tx-d)]">Aún no hay servicios con reservas completadas.</p>
        </div>
        <?php endif; ?>
    </div>

</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

</body>
</html>
