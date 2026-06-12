<?php
/**
 * nav_admin.php
 * Barra de navegación compartida del Panel de Administración.
 * Recibe la variable $pagina_activa ('agenda', 'bloqueos', 'clientes', 'servicios')
 * y opcionalmente $resumen_dia (array con total, completadas, ingresos) desde agenda.
 */
$admin_nombre = $_SESSION['usuario']->getNombre() ?? 'Administrador';
$admin_iniciales = mb_strtoupper(mb_substr($admin_nombre, 0, 1, 'UTF-8'), 'UTF-8');
$admin_avatar = $_SESSION['usuario']->getAvatar();

$items_diarios = [
    'agenda'   => ['href' => 'index.php',       'icon' => 'bi-calendar3',     'label' => 'Agenda'],
    'bloqueos' => ['href' => 'bloqueos.php',    'icon' => 'bi-slash-circle',  'label' => 'Bloqueos']
];

$items_config = [
    'clientes'  => ['href' => 'clientes.php',   'icon' => 'bi-people',        'label' => 'Clientes'],
    'servicios' => ['href' => 'servicios.php',  'icon' => 'bi-scissors',      'label' => 'Servicios'],
    'horario'   => ['href' => 'horario.php',    'icon' => 'bi-clock-history', 'label' => 'Horario Semanal'],
];

$items_web = [
    'productos' => ['href' => 'productos.php',  'icon' => 'bi-box-seam',      'label' => 'Productos'],
    'galeria'   => ['href' => 'galeria.php',    'icon' => 'bi-images',        'label' => 'Galería'],
];

// Stats rápidas del día (opcional, pasadas desde agenda)
$stats_dia = $resumen_dia ?? null;
?>
<style>
@keyframes nav-stagger {
  from { opacity: 0; transform: translateX(-10px); }
  to   { opacity: 1; transform: translateX(0); }
}
.nav-item-stagger {
  animation: nav-stagger 0.35s ease both;
}
.nav-item-stagger:nth-child(1) { animation-delay: 0.02s; }
.nav-item-stagger:nth-child(2) { animation-delay: 0.06s; }
.nav-item-stagger:nth-child(3) { animation-delay: 0.10s; }
.nav-item-stagger:nth-child(4) { animation-delay: 0.14s; }
</style>

<header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 flex items-center justify-between px-4"
        style="background:var(--bg2); border-bottom:1px solid var(--brd);">
    <div>
        <span style="font-family:var(--pf); color:var(--gold); font-size:0.95rem; letter-spacing:0.04em; font-weight:600;">
            Barbershop La H
        </span>
        <span class="block" style="font-size:0.55rem; color:var(--tx-m); letter-spacing:0.1em; text-transform:uppercase;">
            Panel Admin
        </span>
    </div>

    <div class="flex items-center gap-2">
        <?php if ($stats_dia && $stats_dia['total'] > 0): ?>
            <span class="flex items-center gap-1 px-2.5 py-1 rounded-full bg-[var(--gold-dim)] border border-[var(--gold-brd)] text-[0.55rem] font-semibold text-[var(--gold)]">
                <i class="bi bi-calendar-check text-[0.65rem]"></i>
                <?= $stats_dia['total'] ?> hoy
            </span>
        <?php endif; ?>
        <a href="perfil.php" class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center border flex-shrink-0 active:scale-95 transition-all"
           style="border-color:var(--gold-brd); background:var(--gold-dim);">
            <?php if (!empty($admin_avatar)): ?>
                <img src="<?= h($admin_avatar) ?>" alt="Perfil de <?= h($admin_nombre) ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <span style="font-family:var(--pf); color:var(--gold); font-size:0.85rem; font-weight:600; line-height:1;">
                    <?= h($admin_iniciales) ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</header>

<aside class="hidden lg:flex fixed inset-y-0 left-0 flex-col z-20 w-60"
       style="background:var(--bg2); border-right:1px solid var(--brd);">

    <div class="px-5 pt-6 pb-4" style="border-bottom:1px solid var(--brd);">
        <div style="font-family:var(--pf); color:var(--gold); font-size:1.05rem; letter-spacing:0.04em; font-weight:600;">
            Barbershop La H
        </div>
        <div style="font-size:0.58rem; color:var(--tx-m); letter-spacing:0.2em; text-transform:uppercase; margin-top:2px;">
            Panel de Administración
        </div>
    </div>

    <div class="flex items-center gap-3 px-4 py-4" style="border-bottom:1px solid var(--brd);">
        <div class="w-9 h-9 rounded-full flex items-center justify-center border-2 font-bold"
             style="border-color:var(--gold-brd); background:var(--gold-dim); color:var(--gold); font-family:var(--pf); flex-shrink:0;">
            <?= h($admin_iniciales) ?>
        </div>
        <div class="min-w-0">
            <div class="truncate" style="font-size:0.8rem; font-weight:600; color:var(--tx);">
                <?= h($admin_nombre) ?>
            </div>
            <div class="flex items-center gap-1" style="font-size:0.58rem; color:var(--gold); text-transform:uppercase; letter-spacing:0.05em; font-weight:500;">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block animate-pulse"></span>
                Administrador
            </div>
        </div>
    </div>

    <!-- Stats rápidas del día -->
    <?php if ($stats_dia): ?>
    <div class="grid grid-cols-3 gap-0 px-3 py-3" style="border-bottom:1px solid var(--brd);">
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[0.9rem] font-bold text-[var(--tx)]" style="font-family:var(--pf);"><?= $stats_dia['total'] ?></span>
            <span class="text-[0.5rem] uppercase tracking-wider text-[var(--tx-d)]">Citas</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[0.9rem] font-bold text-[var(--gold)]" style="font-family:var(--pf);"><?= $stats_dia['completadas'] ?></span>
            <span class="text-[0.5rem] uppercase tracking-wider text-[var(--tx-d)]">Hechas</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[0.9rem] font-bold text-[var(--tx)]" style="font-family:var(--pf);"><?= number_format($stats_dia['ingresos'], 0, ',', '.') ?>€</span>
            <span class="text-[0.5rem] uppercase tracking-wider text-[var(--tx-d)]">Hoy</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Atajos rápidos -->
    <div class="px-3 pt-3 pb-1" style="border-bottom:1px solid var(--brd);">
        <div class="flex gap-2">
            <a href="../cliente/reserva.php" target="_blank"
               class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-[0.6rem] font-bold uppercase tracking-wider transition-all cursor-pointer"
               style="background:var(--gold-dim); color:var(--gold); border:1px solid var(--gold-brd);"
               onmouseover="this.style.background='rgba(212,175,55,0.2)'"
               onmouseout="this.style.background='var(--gold-dim)'">
                <i class="bi bi-plus-lg text-[0.7rem]"></i> Nueva cita
            </a>
            <a href="clientes.php"
               class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-[0.6rem] font-bold uppercase tracking-wider transition-all cursor-pointer"
               style="background:rgba(255,255,255,0.04); color:var(--tx-m); border:1px solid var(--brd);"
               onmouseover="this.style.background='rgba(255,255,255,0.08)';this.style.color='var(--tx)'"
               onmouseout="this.style.background='rgba(255,255,255,0.04)';this.style.color='var(--tx-m)'">
                <i class="bi bi-person-plus text-[0.7rem]"></i> Clientes
            </a>
        </div>
    </div>

    <nav class="flex-1 py-3 overflow-y-auto px-3 flex flex-col gap-5">

        <div>
            <div class="px-3 mb-2 font-semibold" style="font-size:0.6rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.1em;">
                Principal
            </div>
            <div class="flex flex-col gap-1 nav-item-stagger">
                <?php foreach ($items_diarios as $clave => $item): ?>
                    <a href="<?= h($item['href']) ?>"
                       class="nav-lateral-lnk <?= $pagina_activa === $clave ? 'activo' : '' ?>">
                        <i class="bi <?= h($item['icon']) ?>"></i>
                        <?= h($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <div class="px-3 mb-2 font-semibold" style="font-size:0.6rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.1em;">
                Configuración
            </div>
            <div class="flex flex-col gap-1 nav-item-stagger">
                <?php foreach ($items_config as $clave => $item): ?>
                    <a href="<?= h($item['href']) ?>"
                       class="nav-lateral-lnk flex items-center justify-between group <?= $pagina_activa === $clave ? 'activo' : '' ?>">
                        <div class="flex items-center gap-2">
                            <i class="bi <?= h($item['icon']) ?>"></i>
                            <?= h($item['label']) ?>
                        </div>
                        <span class="opacity-40 group-hover:opacity-100 transition-opacity text-xs bg-white/5 px-1.5 py-0.5 rounded border border-white/10">
                            +
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <div class="px-3 mb-2 font-semibold" style="font-size:0.6rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.1em;">
                Contenido Web
            </div>
            <div class="flex flex-col gap-1 nav-item-stagger">
                <?php foreach ($items_web as $clave => $item): ?>
                    <a href="<?= h($item['href']) ?>"
                       class="nav-lateral-lnk <?= $pagina_activa === $clave ? 'activo' : '' ?>">
                        <i class="bi <?= h($item['icon']) ?>"></i>
                        <?= h($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <div class="px-4 py-4" style="border-top:1px solid var(--brd);">
        <a href="../logout.php"
           class="flex items-center gap-2 transition-colors duration-150 p-2 rounded-xl text-red-400 hover:bg-red-500/10"
           style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.1em; font-weight:600;">
            <i class="bi bi-box-arrow-right" style="font-size:0.85rem;"></i>
            Cerrar sesión
        </a>
    </div>
</aside>

<nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 h-16 flex"
     style="background:var(--bg2); border-top:1px solid var(--brd);">

    <a href="index.php" class="nav-inf-lnk flex-1 flex flex-col items-center justify-center gap-0.5 <?= $pagina_activa === 'agenda' ? 'activo' : '' ?>">
        <i class="bi bi-calendar3" style="font-size:1.1rem;"></i>
        <span style="font-size:0.55rem;">Agenda</span>
    </a>

    <a href="bloqueos.php" class="nav-inf-lnk flex-1 flex flex-col items-center justify-center gap-0.5 <?= $pagina_activa === 'bloqueos' ? 'activo' : '' ?>">
        <i class="bi bi-slash-circle" style="font-size:1.1rem;"></i>
        <span style="font-size:0.55rem;">Bloqueos</span>
    </a>

    <button onclick="toggleMenuOtros()" class="nav-inf-lnk flex-1 flex flex-col items-center justify-center gap-0.5 <?= in_array($pagina_activa, ['clientes', 'servicios']) ? 'activo' : '' ?>" style="background:none; border:none; cursor:pointer;">
        <i class="bi bi-sliders" style="font-size:1.1rem;"></i>
        <span style="font-size:0.55rem;">Otros</span>
    </button>
</nav>

<div id="menu-otros-movil" class="hidden fixed inset-x-0 bottom-16 bg-[var(--bg2)] border-t border-[var(--brd)] p-4 flex flex-col gap-3 z-40 rounded-t-2xl shadow-2xl">
    <div class="text-[var(--tx-d)] text-[0.6rem] uppercase tracking-wider font-bold mb-1 px-2">Selecciona una opción</div>
    <a href="clientes.php" class="flex items-center gap-3 p-3 rounded-xl bg-white/5 text-sm font-medium hover:bg-white/10">
        <i class="bi bi-people text-[var(--gold)]"></i> Gestión de Clientes
    </a>
    <a href="servicios.php" class="flex items-center gap-3 p-3 rounded-xl bg-white/5 text-sm font-medium hover:bg-white/10">
        <i class="bi bi-scissors text-[var(--gold)]"></i> Gestión de Servicios
    </a>
    <a href="horario.php" class="flex items-center gap-3 p-3 rounded-xl bg-white/5 text-sm font-medium hover:bg-white/10">
        <i class="bi bi-clock-history text-[var(--gold)]"></i> Horario Semanal
    </a>
    <div class="border-t border-[var(--brd)] my-1"></div>
    <div class="text-[var(--tx-d)] text-[0.55rem] uppercase tracking-wider font-bold px-2 mb-1">Contenido Web</div>
    <a href="productos.php" class="flex items-center gap-3 p-3 rounded-xl bg-white/5 text-sm font-medium hover:bg-white/10">
        <i class="bi bi-box-seam text-[var(--gold)]"></i> Productos
    </a>
    <a href="galeria.php" class="flex items-center gap-3 p-3 rounded-xl bg-white/5 text-sm font-medium hover:bg-white/10">
        <i class="bi bi-images text-[var(--gold)]"></i> Galería de Cortes
    </a>
</div>

<script>
    function toggleMenuOtros() {
        const menu = document.getElementById('menu-otros-movil');
        menu.classList.toggle('hidden');
    }
</script>
