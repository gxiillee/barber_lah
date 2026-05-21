<?php
/**
 * nav_cliente.php
 * Barra de navegación compartida del Área de Cliente.
 * Se incluye al principio de CADA página del panel de cliente.
 *
 * REQUISITO: antes de incluir este archivo, la página ya debe haber:
 *   1. Hecho session_start()
 *   2. Comprobado que $_SESSION['usuario'] existe
 *   3. Definido $pagina_activa con el nombre de la sección actual
 *      ('inicio', 'fidelidad', 'fotos', 'historial', 'reservar')
 *
 * Uso: require_once __DIR__ . '/nav_cliente.php';
 */

// Datos del usuario para mostrar en el nav (vienen de la sesión)
$nav_nombre     = $usuario->getNombre();
$nav_puntos     = (int) $usuario->getPuntosFidelidad();
$nav_avatar     = $usuario->getAvatar();
$nav_inicial    = mb_strtoupper(mb_substr($nav_nombre, 0, 1, 'UTF-8'), 'UTF-8');

// Definición de los apartados del menú
// 'href' es relativo dentro de la carpeta /cliente/
$nav_items = [
    'inicio'    => ['href' => 'index.php',     'icon' => 'bi-grid-1x2',    'label' => 'Inicio',       'short' => 'Inicio'],
    'fidelidad' => ['href' => 'fidelidad.php', 'icon' => 'bi-award',        'label' => 'Mi Fidelidad', 'short' => 'Puntos'],
    'fotos'     => ['href' => 'fotos.php',     'icon' => 'bi-camera',       'label' => 'Mis Fotos',    'short' => 'Fotos'],
    'historial' => ['href' => 'historial.php', 'icon' => 'bi-clock-history','label' => 'Historial',    'short' => 'Historial'],
    'reservar'  => ['href' => 'reservar.php',  'icon' => 'bi-calendar-plus','label' => 'Nueva Cita',   'short' => 'Reservar'],
];
?>

<!-- ═══════════════════════════════════════════════════════════════
     HEADER MÓVIL — visible solo en móvil (lg:hidden)
     Barra fija en la parte superior con logo y avatar del cliente
     ═══════════════════════════════════════════════════════════════ -->
<header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 flex items-center justify-between px-4"
        style="background:var(--bg2); border-bottom:1px solid var(--brd);">

    <!-- Logo izquierda -->
    <span style="font-family:var(--pf); color:var(--gold); font-size:0.95rem; letter-spacing:0.04em;">
        Barbershop La H
    </span>

    <!-- Avatar derecha: foto de Google o inicial del nombre -->
    <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center border flex-shrink-0"
         style="border-color:var(--gold-brd); background:var(--gold-dim);">
        <?php if ($nav_avatar): ?>
            <img src="<?= h($nav_avatar) ?>" alt="Foto de <?= h($nav_nombre) ?>" class="w-full h-full object-cover">
        <?php else: ?>
            <span style="font-family:var(--pf); color:var(--gold); font-size:0.82rem; line-height:1;">
                <?= h($nav_inicial) ?>
            </span>
        <?php endif; ?>
    </div>
</header>


<!-- ═══════════════════════════════════════════════════════════════
     SIDEBAR DESKTOP — visible solo en desktop (hidden lg:flex)
     Panel lateral fijo con toda la navegación
     ═══════════════════════════════════════════════════════════════ -->
<aside class="hidden lg:flex fixed inset-y-0 left-0 flex-col z-20 w-64"
       style="background:var(--bg2); border-right:1px solid var(--brd);">

    <!-- Logo -->
    <div class="px-5 pt-6 pb-5" style="border-bottom:1px solid var(--brd);">
        <div style="font-family:var(--pf); color:var(--gold); font-size:1rem; letter-spacing:0.04em;">
            Barbershop La H
        </div>
        <div style="font-size:0.58rem; color:var(--tx-m); letter-spacing:0.24em; text-transform:uppercase; margin-top:2px;">
            Área de Cliente
        </div>
    </div>

    <!-- Info del usuario: avatar + nombre + puntos -->
    <div class="flex items-center gap-3 px-4 py-4" style="border-bottom:1px solid var(--brd);">

        <!-- Avatar: foto Google o círculo con inicial -->
        <div class="w-9 h-9 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 border"
             style="border-color:var(--gold-brd); background:var(--gold-dim);">
            <?php if ($nav_avatar): ?>
                <img src="<?= h($nav_avatar) ?>" alt="" class="w-full h-full object-cover">
            <?php else: ?>
                <span style="font-family:var(--pf); color:var(--gold); font-size:0.82rem; line-height:1;">
                    <?= h($nav_inicial) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Nombre y puntos -->
        <div class="min-w-0">
            <div class="truncate" style="font-size:0.78rem; font-weight:500; color:var(--tx);">
                <?= h($nav_nombre) ?>
            </div>
            <div style="font-size:0.62rem; color:var(--gold); margin-top:1px;">
                <i class="bi bi-star-fill" style="font-size:0.55rem;"></i>
                <?= $nav_puntos ?> / 10 puntos
            </div>
        </div>
    </div>

    <!-- Links de navegación -->
    <nav class="flex flex-col flex-1 py-2 overflow-y-auto">
        <?php foreach ($nav_items as $clave => $item): ?>
            <a href="<?= h($item['href']) ?>"
               class="nav-lateral-lnk <?= $pagina_activa === $clave ? 'activo' : '' ?>">
                <i class="bi <?= h($item['icon']) ?>"></i>
                <?= h($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Cerrar sesión -->
    <div class="px-5 py-4" style="border-top:1px solid var(--brd);">
        <a href="../logout.php"
           class="flex items-center gap-2 transition-colors duration-150"
           style="font-size:0.65rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.1em;">
            <i class="bi bi-box-arrow-right"></i>
            Cerrar sesión
        </a>
    </div>
</aside>


<!-- ═══════════════════════════════════════════════════════════════
     NAV INFERIOR MÓVIL — visible solo en móvil (lg:hidden)
     Barra fija en la parte inferior con los 5 apartados
     ═══════════════════════════════════════════════════════════════ -->
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