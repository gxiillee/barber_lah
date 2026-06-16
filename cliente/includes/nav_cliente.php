<?php
/**
 * nav_cliente.php
 * Barra de navegación compartida del Área de Cliente.
 */

// Datos del usuario para mostrar en el nav (vienen de la sesión definida en la página que incluye este archivo)
$nav_nombre     = $usuario->getNombre();
// Refresh points from DB in case admin modified them
$stmt = BD::obtenerConexion()->prepare("SELECT puntos_fidelidad FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $usuario->getId()]);
$nav_puntos     = (int)$stmt->fetchColumn();
$usuario->setPuntosFidelidad($nav_puntos);
$nav_avatar     = $usuario->getAvatar();
$nav_inicial    = mb_strtoupper(mb_substr($nav_nombre, 0, 1, 'UTF-8'), 'UTF-8');

// Definición de los apartados del menú
$nav_items = [
        'inicio'    => ['href' => 'index.php',     'icon' => 'bi-grid-1x2',    'label' => 'Inicio',       'short' => 'Inicio'],
        'fidelidad' => ['href' => 'fidelidad.php', 'icon' => 'bi-award',        'label' => 'Mi Fidelidad', 'short' => 'Puntos'],
        'fotos'     => ['href' => 'fotos.php',     'icon' => 'bi-camera',       'label' => 'Mis Fotos',    'short' => 'Fotos'],
        'historial' => ['href' => 'historial.php', 'icon' => 'bi-clock-history','label' => 'Historial',    'short' => 'Historial'],
        'reservar'  => ['href' => 'reserva.php',   'icon' => 'bi-calendar-plus','label' => 'Nueva Cita',   'short' => 'Reservar'],
];
?>

<header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 flex items-center justify-between px-4"
        style="background:var(--bg2); border-bottom:1px solid var(--brd);">

    <span style="font-family:var(--pf); color:var(--gold); font-size:0.95rem; letter-spacing:0.04em;">
        Barbershop La H
    </span>

    <a href="perfil.php" class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center border flex-shrink-0 active:scale-95 transition-all"
       style="border-color:var(--gold-brd); background:var(--gold-dim);">
        <?php if (!empty($nav_avatar)): ?>
            <img src="<?= h($nav_avatar) ?>" alt="Foto de <?= h($nav_nombre) ?>" class="w-full h-full object-cover">
        <?php else: ?>
            <span style="font-family:var(--pf); color:var(--gold); font-size:0.82rem; line-height:1;">
                <?= h($nav_inicial) ?>
            </span>
        <?php endif; ?>
    </a>
</header>

<aside class="hidden lg:flex fixed inset-y-0 left-0 flex-col z-20 w-64"
       style="background:var(--bg2); border-right:1px solid var(--brd);">

    <div class="px-5 pt-6 pb-5" style="border-bottom:1px solid var(--brd);">
        <div style="font-family:var(--pf); color:var(--gold); font-size:1rem; letter-spacing:0.04em;">
            Barbershop La H
        </div>
        <div style="font-size:0.58rem; color:var(--tx-m); letter-spacing:0.24em; text-transform:uppercase; margin-top:2px;">
            Área de Cliente
        </div>
    </div>

    <div class="flex items-center gap-3 px-4 py-4" style="border-bottom:1px solid var(--brd);">

        <div class="w-9 h-9 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 border"
             style="min-width:36px; border-color:var(--gold-brd); background:var(--gold-dim);">
            <?php if (!empty($nav_avatar)): ?>
                <img src="<?= h($nav_avatar) ?>" alt="Foto de <?= h($nav_nombre) ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <span style="font-family:var(--pf); color:var(--gold); font-size:0.82rem; line-height:1;">
                    <?= h($nav_inicial) ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="min-w-0 flex-1">
            <div class="truncate" style="font-size:0.78rem; font-weight:500; color:var(--tx);">
                <?= h($nav_nombre) ?>
            </div>
            <!-- Mini barra de progreso de fidelidad (siempre visible) -->
            <div class="flex items-center gap-2 mt-1.5">
                <div class="flex-1 rounded-full overflow-hidden" style="height:4px; background:rgba(255,255,255,0.06);">
                    <div style="width:<?= min(100, ($nav_puntos / 10) * 100) ?>%; height:100%; background:var(--gold); border-radius:999px; transition:width 0.6s var(--ease-out);"></div>
                </div>
                <span style="font-size:0.55rem; color:var(--gold); white-space:nowrap;">
                    <?= $nav_puntos >= 10 ? '¡Gratis!' : (10 - $nav_puntos) . ' faltan' ?>
                </span>
            </div>
        </div>
    </div>

    <nav class="flex flex-col flex-1 py-2 overflow-y-auto">
        <?php foreach ($nav_items as $clave => $item): ?>
            <a href="<?= h($item['href']) ?>"
               class="nav-lateral-lnk <?= $pagina_activa === $clave ? 'activo' : '' ?>">
                <i class="bi <?= h($item['icon']) ?>"></i>
                <?= h($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-5 py-4 flex flex-col gap-3.5" style="border-top:1px solid var(--brd);">
        <a href="perfil.php"
           class="flex items-center gap-2 transition-colors duration-150 group"
           style="font-size:0.65rem; color:<?= $pagina_activa === 'perfil' ? 'var(--gold)' : 'var(--tx-m)' ?>; text-transform:uppercase; letter-spacing:0.1em; font-weight:500;">
            <i class="bi bi-person-gear" style="font-size:0.85rem; color:var(--gold);"></i>
            Mi Perfil
        </a>
        <a href="../logout.php"
           class="flex items-center gap-2 transition-colors duration-150"
           style="font-size:0.65rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.1em;">
            <i class="bi bi-box-arrow-right"></i>
            Cerrar sesión
        </a>
    </div>
</aside>

<nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 h-16 flex"
     style="background:var(--bg2); border-top:1px solid var(--brd);">

    <?php foreach ($nav_items as $clave => $item): ?>
        <a href="<?= h($item['href']) ?>"
           class="nav-inf-lnk flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors duration-150
                  <?= $pagina_activa === $clave ? 'activo' : '' ?>">
            <i class="bi <?= h($item['icon']) ?>" style="font-size:1.15rem;"></i>
            <span style="font-size:0.5rem; letter-spacing:0.04em; font-family:var(--mt);">
                <?= h($item['short']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</nav>