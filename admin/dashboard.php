<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Administrador.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Csrf.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

// ── Navegación de meses ──────────────────────────────────────────
$hoy = new DateTimeImmutable('first day of this month');
$mes_sel = $_GET['mes'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $mes_sel)) {
    $sel = DateTimeImmutable::createFromFormat('Y-m-d', $mes_sel . '-01');
    $sel = $sel ? $sel : $hoy;
} else {
    $sel = $hoy;
}
$anio = (int)$sel->format('Y');
$mes  = (int)$sel->format('m');
// No permitir meses futuros
if ($sel > $hoy) { $sel = $hoy; $anio = (int)$sel->format('Y'); $mes = (int)$sel->format('m'); }

$ant = $sel->modify('-1 month');
$sig = ($sel < $hoy) ? $sel->modify('+1 month') : null;
$mes_nombre = mb_strtolower(nombreMes($mes)) . ' ' . $anio;
$es_mes_actual = ($sel->format('Y-m') === $hoy->format('Y-m'));
$mes_label = $es_mes_actual ? 'este mes' : mb_strtolower(nombreMes($mes));

// ── Datos del mes seleccionado ───────────────────────────────────
$resumen_mes  = Administrador::obtenerResumenMes($anio, $mes);
$ingresos_mensuales = Administrador::obtenerIngresosMensuales(12);
$servicios    = Administrador::obtenerServiciosMasVendidos(6);
$no_shows      = Administrador::obtenerTasaNoShows($anio, $mes);
$clientes_clv  = Administrador::obtenerClientesNuevosVsRecurrentes($anio, $mes);
$no_shows_list = Administrador::obtenerNoShowsMes($anio, $mes);
$nuevos_list   = Administrador::obtenerNuevosClientesMes($anio, $mes);
$nuevos_primera   = Administrador::obtenerNuevosPrimeraVezMes($anio, $mes);
$recurrentes_list = Administrador::obtenerRecurrentesMes($anio, $mes);
$token_csrf    = Csrf::generarToken('dashboard');

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
    <style>
        .modal-scroll::-webkit-scrollbar { width: 4px; }
        .modal-scroll::-webkit-scrollbar-track { background: transparent; }
        .modal-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 4px; }
    </style>
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-6xl pagina-entrada">

    <div class="flex items-center justify-between mb-7">
        <div>
            <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Dashboard</h1>
            <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Resumen del negocio</p>
        </div>
        <!-- Navegación de meses -->
        <div class="flex items-center gap-1 text-[0.65rem] bg-white/[0.03] border border-[var(--brd)] rounded-xl overflow-hidden">
            <?php if ($ant): ?>
                <a href="?mes=<?= $ant->format('Y-m') ?>" class="px-3 py-2 text-[var(--tx-d)] hover:text-[var(--tx)] hover:bg-white/[0.05] transition-all no-underline"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <span class="px-3 py-2 font-semibold text-[var(--gold)] whitespace-nowrap" style="font-family:var(--pf);"><?= $mes_nombre ?></span>
            <?php if ($sig): ?>
                <a href="?mes=<?= $sig->format('Y-m') ?>" class="px-3 py-2 text-[var(--tx-d)] hover:text-[var(--tx)] hover:bg-white/[0.05] transition-all no-underline"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
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
                <i class="bi bi-calendar-range"></i> <?= $mes_label ?>
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
                <span class="text-[0.7rem] font-normal text-[var(--tx-m)]">· <?= $resumen_mes['completadas'] ?> completadas</span>
            </div>
            <div class="text-[0.6rem] text-[var(--tx-d)] flex items-center gap-1">
                <i class="bi bi-calendar-range"></i> <?= $mes_label ?>
            </div>
        </div>

        <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card stagger-item cursor-pointer transition-all hover:border-rose-500/30 hover:bg-rose-500/[0.06]"
             onclick="abrirModalNoShows()">
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
                <i class="bi bi-bar-chart-line"></i> <?= $mes_label ?>
                <span class="ml-auto text-[0.5rem] opacity-50">ver lista →</span>
            </div>
        </div>

        <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card stagger-item cursor-pointer transition-all hover:border-amber-500/30 hover:bg-amber-500/[0.06]"
             onclick="abrirModalNuevos()">
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
                <i class="bi bi-person-plus"></i> <?= $mes_label ?>
                <span class="ml-auto text-[0.5rem] opacity-50">ver lista →</span>
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

        <!-- Clientes nuevos vs recurrentes (donut) — clic para ver lista -->
        <div class="lg:col-span-2 rounded-xl border border-[var(--brd)] bg-white/[0.03] p-5 glow-card cursor-pointer transition-all hover:border-amber-500/30 hover:bg-amber-500/[0.06]"
             onclick="abrirModalDonut()">
            <h2 class="text-[0.9rem] font-semibold text-[var(--tx)] flex items-center gap-2 mb-5" style="font-family: var(--pf);">
                <i class="bi bi-people-fill text-[var(--gold)]"></i> Clientes
                <span class="ml-auto text-[0.5rem] font-normal text-[var(--tx-d)] opacity-50">ver lista →</span>
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

<!-- ════════════════════════════════════════════════════
     MODAL: LISTA DE NO-SHOWS
     ════════════════════════════════════════════════════ -->
<div id="modalNoShows" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200"
     onclick="if(event.target===this)cerrarModalNoShows()">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl w-full max-w-lg shadow-2xl scale-95 transition-transform duration-200 max-h-[90vh] flex flex-col"
         id="modalNoShowsContent">
        <!-- Header -->
        <div class="flex items-center justify-between p-5 pb-3 shrink-0">
            <div>
                <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8] flex items-center gap-2">
                    <i class="bi bi-person-x text-rose-400 text-[0.85rem]"></i> No-Shows
                </h3>
                <p class="text-[0.6rem] text-[var(--tx-d)] mt-0.5"><?= count($no_shows_list) ?> cliente<?= count($no_shows_list) !== 1 ? 's' : '' ?> no se presentaron este mes</p>
            </div>
            <button onclick="cerrarModalNoShows()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer shrink-0">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <!-- Lista scrollable -->
        <div class="overflow-y-auto px-5 pb-5 space-y-1.5 modal-scroll"
             style="max-height:55vh; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.08) transparent;">
            <?php if (count($no_shows_list) > 0): ?>
                <?php foreach ($no_shows_list as $ns):
                    $iniciales = mb_strtoupper(mb_substr($ns['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
                    $fecha_dt = new DateTimeImmutable($ns['fecha']);
                    $fecha_corta = $fecha_dt->format('j') . ' ' . mb_strtolower(nombreMes((int)$fecha_dt->format('n')));
                ?>
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/[0.02] border border-white/[0.05] hover:border-rose-500/20 transition-all">
                    <div class="w-8 h-8 rounded-full bg-rose-500/15 flex items-center justify-center text-[0.6rem] font-bold text-rose-400 shrink-0">
                        <?= h($iniciales) ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[0.78rem] font-medium text-[var(--tx)] truncate"><?= h($ns['nombre']) ?></div>
                        <div class="text-[0.6rem] text-[var(--tx-d)] flex flex-wrap gap-x-2">
                            <span><?= h($ns['email']) ?></span>
                            <span>·</span>
                            <span><?= $fecha_corta ?> · <?= substr($ns['hora'], 0, 5) ?>h</span>
                            <span>·</span>
                            <span><?= h($ns['servicio']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="flex flex-col items-center py-8 text-center">
                    <i class="bi bi-check-circle text-[1.5rem] text-emerald-500 mb-2"></i>
                    <p class="text-[0.75rem] text-[var(--tx-d)]">No hay ningún no-show este mes</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL: LISTA DE CLIENTES NUEVOS (altas)
     ════════════════════════════════════════════════════ -->
<div id="modalNuevos" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200"
     onclick="if(event.target===this)cerrarModalNuevos()">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl w-full max-w-lg shadow-2xl scale-95 transition-transform duration-200 max-h-[90vh] flex flex-col"
         id="modalNuevosContent">
        <div class="flex items-center justify-between p-5 pb-3 shrink-0">
            <div>
                <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8] flex items-center gap-2">
                    <i class="bi bi-person-plus text-amber-400 text-[0.85rem]"></i> Clientes nuevos
                </h3>
                <p class="text-[0.6rem] text-[var(--tx-d)] mt-0.5"><?= count($nuevos_list) ?> cliente<?= count($nuevos_list) !== 1 ? 's' : '' ?> se registraron este mes</p>
            </div>
            <button onclick="cerrarModalNuevos()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer shrink-0">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <div class="overflow-y-auto px-5 pb-5 space-y-1.5 modal-scroll"
             style="max-height:55vh; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.08) transparent;">
            <?php if (count($nuevos_list) > 0): ?>
                <?php foreach ($nuevos_list as $nv):
                    $iniciales = mb_strtoupper(mb_substr($nv['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
                    $reg_dt = new DateTimeImmutable($nv['created_at']);
                    $fecha_reg = $reg_dt->format('j') . ' ' . mb_strtolower(nombreMes((int)$reg_dt->format('n')));
                ?>
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/[0.02] border border-white/[0.05] hover:border-amber-500/20 transition-all">
                    <div class="w-8 h-8 rounded-full bg-amber-500/15 flex items-center justify-center text-[0.6rem] font-bold text-amber-400 shrink-0">
                        <?= h($iniciales) ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[0.78rem] font-medium text-[var(--tx)] truncate"><?= h($nv['nombre']) ?></div>
                        <div class="text-[0.6rem] text-[var(--tx-d)] flex flex-wrap gap-x-2">
                            <span><?= h($nv['email']) ?></span>
                            <span>·</span>
                            <span>registrado el <?= $fecha_reg ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="flex flex-col items-center py-8 text-center">
                    <i class="bi bi-people text-[1.5rem] text-[var(--tx-d)] mb-2"></i>
                    <p class="text-[0.75rem] text-[var(--tx-d)]">No hay clientes nuevos este mes</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL: DONUT — nuevos + recurrentes
     ════════════════════════════════════════════════════ -->
<div id="modalDonut" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200"
     onclick="if(event.target===this)cerrarModalDonut()">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl w-full max-w-lg shadow-2xl scale-95 transition-transform duration-200 max-h-[90vh] flex flex-col"
         id="modalDonutContent">
        <div class="flex items-center justify-between p-5 pb-3 shrink-0">
            <div>
                <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8] flex items-center gap-2">
                    <i class="bi bi-people-fill text-[#d4af37]"></i> Clientes del mes
                </h3>
                <p class="text-[0.6rem] text-[var(--tx-d)] mt-0.5"><?= $clientes_clv['nuevos'] + $clientes_clv['recurrentes'] ?> cliente<?= $clientes_clv['nuevos'] + $clientes_clv['recurrentes'] !== 1 ? 's' : '' ?> en total</p>
            </div>
            <button onclick="cerrarModalDonut()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer shrink-0">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <div class="overflow-y-auto px-5 pb-5"
             style="max-height:60vh; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.08) transparent;">

            <!-- Nuevos -->
            <div class="mb-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#d4af37] shrink-0"></span>
                    <h4 class="text-[0.85rem] font-semibold text-[var(--tx)]" style="font-family:var(--pf);">Nuevos</h4>
                    <span class="text-[0.6rem] text-[var(--tx-d)]">(<?= count($nuevos_primera) ?>)</span>
                </div>
                <?php if (count($nuevos_primera) > 0): ?>
                <div class="space-y-1">
                    <?php foreach ($nuevos_primera as $np):
                        $pdi = new DateTimeImmutable($np['primera_fecha']);
                    ?>
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-white/[0.02] border border-white/[0.05]">
                        <div class="w-7 h-7 rounded-full bg-[#d4af37]/15 flex items-center justify-center text-[0.55rem] font-bold text-[#d4af37] shrink-0">
                            <?= h(mb_strtoupper(mb_substr($np['nombre'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[0.75rem] font-medium text-[var(--tx)] truncate"><?= h($np['nombre']) ?></div>
                            <div class="text-[0.55rem] text-[var(--tx-d)]"><?= h($np['email']) ?> · primera visita el <?= (new DateTimeImmutable($np['primera_fecha']))->format('j') . ' ' . mb_strtolower(nombreMes((int)(new DateTimeImmutable($np['primera_fecha']))->format('n'))) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-[0.7rem] text-[var(--tx-d)] italic">No hay nuevos esta selección</p>
                <?php endif; ?>
            </div>

            <!-- Separador -->
            <div class="border-t border-white/[0.06] my-4"></div>

            <!-- Recurrentes -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b] shrink-0"></span>
                    <h4 class="text-[0.85rem] font-semibold text-[var(--tx)]" style="font-family:var(--pf);">Recurrentes</h4>
                    <span class="text-[0.6rem] text-[var(--tx-d)]">(<?= count($recurrentes_list) ?>)</span>
                </div>
                <?php if (count($recurrentes_list) > 0): ?>
                <div class="space-y-1">
                    <?php foreach ($recurrentes_list as $rc):
                        $rci = new DateTimeImmutable($rc['primera_fecha']);
                    ?>
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-white/[0.02] border border-white/[0.05]">
                        <div class="w-7 h-7 rounded-full bg-[#f59e0b]/15 flex items-center justify-center text-[0.55rem] font-bold text-[#f59e0b] shrink-0">
                            <?= h(mb_strtoupper(mb_substr($rc['nombre'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[0.75rem] font-medium text-[var(--tx)] truncate"><?= h($rc['nombre']) ?></div>
                            <div class="text-[0.55rem] text-[var(--tx-d)]"><?= h($rc['email']) ?> · primera visita el <?= $rci->format('j') . ' ' . mb_strtolower(nombreMes((int)$rci->format('n'))) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-[0.7rem] text-[var(--tx-d)] italic">No hay recurrentes esta selección</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalNoShows() {
    const modal = document.getElementById('modalNoShows');
    const content = document.getElementById('modalNoShowsContent');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}
function cerrarModalNoShows() {
    const modal = document.getElementById('modalNoShows');
    const content = document.getElementById('modalNoShowsContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 200);
}
function abrirModalNuevos() {
    const modal = document.getElementById('modalNuevos');
    const content = document.getElementById('modalNuevosContent');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}
function cerrarModalNuevos() {
    const modal = document.getElementById('modalNuevos');
    const content = document.getElementById('modalNuevosContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 200);
}
function abrirModalDonut() {
    const modal = document.getElementById('modalDonut');
    const content = document.getElementById('modalDonutContent');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}
function cerrarModalDonut() {
    const modal = document.getElementById('modalDonut');
    const content = document.getElementById('modalDonutContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 200);
}
</script>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

</body>
</html>
