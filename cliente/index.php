<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Carga de dependencias ─────────────────────────────────

require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/FotoCliente.php';
require_once __DIR__ . '/../clases/Reserva.php';

// ── Fase 2: Sesión y control de acceso ────────────────────────────
session_start();

if (!isset($_SESSION['usuario'])) {
    $_SESSION['volver_panel'] = 'index.php';
    redirigir('../login.php');
}

// Auto-completar citas pasadas
Reserva::actualizarCitasPasadas();

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];

if ($usuario->tieneRolAdmin()) {
    redirigir('../admin/index.php');
}

// ── POST: guardar teléfono ──
$error_telefono = '';
$telefono_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_telefono') {
    $tel = trim($_POST['telefono'] ?? '');
    if (preg_match('/^[\d\s\+\-]{6,20}$/', $tel)) {
        if (Usuario::actualizarTelefono($id_usuario, $tel)) {
            $_SESSION['usuario']->setTelefono($tel);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Teléfono guardado correctamente.'];
            redirigir('index.php');
        } else {
            $error_telefono = 'No se pudo guardar. Inténtalo de nuevo.';
        }
    } else {
        $error_telefono = 'Introduce un número de teléfono válido.';
    }
}

// ── Fase 4: Carga de datos para el dashboard ─────────────────────
$id_usuario = (int) $usuario->getId();
$puntos     = (int) $usuario->getPuntosFidelidad();
$nombre = $usuario->getNombre() ?? 'Cliente';
$avatar_url = $usuario->getAvatar();
$inicial = mb_strtoupper(mb_substr($nombre, 0, 1, 'UTF-8'), 'UTF-8');

// Consultas de base de datos delegadas a sus modelos
$total_citas = Reserva::contarCompletadasPorCliente($id_usuario);
$proxima     = Reserva::obtenerProximaPorCliente($id_usuario);

// Formateo de la próxima cita para la vista (lógica de presentación, queda aquí)
$proxima_fecha_larga = '';
$proxima_dia_corto   = '';
$proxima_num_dia     = '';
$proxima_mes_corto   = '';
$proxima_hora        = '';

if ($proxima !== null) {
    $dt = new DateTimeImmutable($proxima['fecha']);
    $proxima_dia_corto   = nombreDiaCorto((int) $dt->format('N'));
    $proxima_num_dia     = $dt->format('j');
    $proxima_mes_corto   = nombreMesCorto((int) $dt->format('n'));
    $proxima_hora        = substr($proxima['hora'], 0, 5);
    $proxima_fecha_larga = fechaHumana($proxima['fecha']);
}

// Fotos del cliente (FotoCliente::obtenerPorUsuario)
$fotos         = FotoCliente::obtenerPorUsuario($id_usuario);
$ultimas_fotos = array_slice($fotos, 0, 4);
$total_fotos   = count($fotos);

// ── Saludo dinámico según la hora ──
$hora_actual = (int) date('G');
if ($hora_actual < 12) {
    $saludo = 'Buenos días';
} elseif ($hora_actual < 18) {
    $saludo = 'Buenas tardes';
} else {
    $saludo = 'Buenas noches';
}

$pagina_activa = 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel — Barbershop La H</title>

    <!-- Tipografías -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,600&family=Montserrat:wght@300;400;500;600&display=swap"
          rel="stylesheet">

    <!-- Bootstrap Icons (solo iconos, no CSS de Bootstrap) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Tailwind CSS v4 -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Animaciones propias (keyframes y clases de animación) -->
    <link rel="stylesheet" href="../public/assets/css/estilos.css">


</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php
// ── Incluimos la navegación compartida ────────────────────────────
// nav_cliente.php genera: header móvil, sidebar desktop y bottom nav móvil
require_once __DIR__ . '/includes/nav_cliente.php';
?>

<!-- ═══════════════════════════════════════════════════════════════
     CONTENIDO PRINCIPAL DEL DASHBOARD
     - Móvil:   pt-14 (header) + pb-20 (bottom nav) + padding lateral
     - Desktop: ml-[220px] (sidebar) sin padding extra arriba/abajo
     ═══════════════════════════════════════════════════════════════ -->
<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">

    <div class="flex-1 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        <!-- ── Cabecera de bienvenida ────────────────────── -->
        <div class="mb-5 lg:mb-6">
            <h1 style="font-family:var(--pf); font-size:clamp(1.5rem,4vw,2rem); font-weight:600; line-height:1.15;">
                <?= h($saludo) ?>, <?= h($nombre) ?>
            </h1>
            <div class="flex items-center gap-2 mt-1">
                <span style="font-size:0.62rem; color:var(--tx-m); letter-spacing:0.22em; text-transform:uppercase;">
                    <?php
                    $now = new DateTimeImmutable();
                    $hora = (int) $now->format('G');
                    $frase = match(true) {
                        $puntos >= 10 => '✨ Tienes un corte gratis esperándote',
                        $hora < 12    => '🌅 Empieza el día con estilo',
                        $hora < 18    => '☀️ Una buena barba nunca pasa de moda',
                        default       => '🌙 Un buen corte cierra el día',
                    };
                    ?>
                    <?= $frase ?>
                </span>
            </div>
        </div>

        <?php if (!$usuario->getTelefono()): ?>
        <!-- ── Banner pedir teléfono ── -->
        <div class="mb-5 rounded-xl border border-amber-500/20 bg-gradient-to-r from-amber-500/8 to-amber-500/2 p-4 sm:p-5">
            <form action="" method="POST" class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                <input type="hidden" name="accion" value="guardar_telefono">
                <div class="flex items-start gap-3 flex-1 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/15 border border-amber-500/20 flex items-center justify-center text-amber-400 shrink-0 mt-0.5">
                        <i class="bi bi-telephone text-[0.85rem]"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[0.78rem] font-semibold text-[var(--tx)]">¿Nos dejas tu teléfono?</p>
                        <p class="text-[0.62rem] text-[var(--tx-m)] mt-0.5">Por si necesitamos contactarte rápido o para futuras notificaciones.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="tel" name="telefono" placeholder="Ej: 612 34 56 78" required
                           class="flex-1 sm:w-44 bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/50 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                    <button type="submit"
                            class="shrink-0 px-4 py-2 rounded-lg bg-[var(--gold)] text-[#0d0d0d] text-[0.65rem] font-bold uppercase tracking-wider hover:opacity-90 transition-all cursor-pointer whitespace-nowrap">
                        Guardar
                    </button>
                </div>
                <?php if ($error_telefono): ?>
                    <p class="text-[0.65rem] text-rose-400 w-full"><?= h($error_telefono) ?></p>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── Grid de estadísticas rápidas ──────────────── -->
        <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-4 stagger-container">

            <!-- Stat: Puntos de fidelidad -->
            <div class="glow-card rounded-xl p-3 sm:p-4 border" style="background:var(--card); border-color:var(--brd);">
                <div class="flex items-center justify-between mb-2">
                    <span style="font-size:0.56rem; text-transform:uppercase; letter-spacing:0.2em; color:var(--tx-m);">Mis puntos</span>
                    <i class="bi bi-star-fill glow-icon" style="font-size:0.7rem; color:var(--tx-d); transition:all 0.25s ease;"></i>
                </div>
                <div style="font-family:var(--pf); font-size:clamp(1.6rem,5vw,2rem); color:var(--gold); line-height:1;">
                    <?= $puntos ?>
                </div>
                <div style="font-size:0.58rem; color:var(--tx-d); margin-top:4px;">
                    <?php if ($puntos >= 10): ?>
                        <span style="color:var(--gold);">¡corte gratis!</span>
                    <?php else: ?>
                        <?= 10 - $puntos ?> para gratis
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stat: Cortes realizados -->
            <div class="glow-card rounded-xl p-3 sm:p-4 border" style="background:var(--card); border-color:var(--brd);">
                <div class="flex items-center justify-between mb-2">
                    <span style="font-size:0.56rem; text-transform:uppercase; letter-spacing:0.2em; color:var(--tx-m);">Cortes</span>
                    <i class="bi bi-scissors glow-icon" style="font-size:0.7rem; color:var(--tx-d); transition:all 0.25s ease;"></i>
                </div>
                <div style="font-family:var(--pf); font-size:clamp(1.6rem,5vw,2rem); line-height:1;">
                    <?= $total_citas ?>
                </div>
                <div style="font-size:0.58rem; color:var(--tx-d); margin-top:4px;">realizados</div>
            </div>

            <!-- Stat: Próxima cita (resumen) -->
            <div class="glow-card rounded-xl p-3 sm:p-4 border" style="background:var(--card); border-color:var(--brd);">
                <div class="flex items-center justify-between mb-2">
                    <span style="font-size:0.56rem; text-transform:uppercase; letter-spacing:0.2em; color:var(--tx-m);">Próxima</span>
                    <i class="bi bi-calendar-check glow-icon" style="font-size:0.7rem; color:var(--tx-d); transition:all 0.25s ease;"></i>
                </div>
                <?php if ($proxima): ?>
                    <div style="font-family:var(--pf); font-size:clamp(1rem,3vw,1.15rem); line-height:1.1;">
                        <?= h($proxima_dia_corto) ?> <?= h($proxima_num_dia) ?>
                    </div>
                    <div style="font-size:0.58rem; color:var(--tx-d); margin-top:4px;">
                        <?= h($proxima_mes_corto) ?> · <?= h($proxima_hora) ?>h
                    </div>
                <?php else: ?>
                    <div style="font-size:0.78rem; color:var(--tx-d); margin-top:4px;">Sin citas</div>
                    <div style="font-size:0.58rem; color:var(--tx-d); margin-top:4px;">—</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Tarjeta de próxima cita (detalle completo) ── -->
        <?php if ($proxima): ?>
            <div class="card-proxima p-4 sm:p-5 mb-4 flex items-center justify-between gap-3" style="opacity:0; animation:fadeInUp 0.45s var(--ease-out) 0.22s forwards;">
                <div class="min-w-0">
                    <div style="font-size:0.58rem; text-transform:uppercase; letter-spacing:0.22em; color:var(--gold); margin-bottom:5px;">
                        <i class="bi bi-calendar-check"></i>&nbsp;Próxima cita
                    </div>
                    <div class="capitalize" style="font-family:var(--pf); font-size:clamp(0.95rem,3vw,1.1rem);">
                        <?= h($proxima_fecha_larga) ?>
                    </div>
                    <div style="font-size:0.68rem; color:var(--tx-m); margin-top:3px;">
                        <?= h($proxima['servicio']) ?>
                        &nbsp;·&nbsp; <?= h($proxima_hora) ?>h
                        &nbsp;·&nbsp; <?= h($proxima['duracion_historica']) ?> min
                    </div>
                </div>
                <!-- Enlace a historial para ver detalles -->
                <a href="historial.php"
                   class="flex-shrink-0 rounded-lg px-3 py-2 border transition-colors duration-150"
                   style="font-size:0.68rem; color:var(--tx-m); border-color:var(--brd);"
                   onmouseover="this.style.borderColor='var(--gold-brd)'; this.style.color='var(--gold)';"
                   onmouseout="this.style.borderColor='var(--brd)'; this.style.color='var(--tx-m)';">
                    Ver
                </a>
            </div>

        <?php else: ?>
            <!-- Estado vacío: sin citas próximas → incitamos a reservar -->
            <div class="rounded-xl p-5 mb-4 text-center border" style="background:var(--card); border-color:var(--brd); opacity:0; animation:fadeInUp 0.45s var(--ease-out) 0.22s forwards;">
                <i class="bi bi-calendar-x" style="font-size:1.6rem; color:var(--tx-d);"></i>
                <div style="font-size:0.8rem; color:var(--tx-m); margin:8px 0 14px;">
                    No tienes ninguna cita próxima
                </div>
                <a href="reserva.php"
                   class="inline-flex items-center gap-2 rounded-lg px-4 py-2 cta-pulse"
                   style="background:var(--gold); color:var(--bg); font-size:0.72rem; font-weight:700; letter-spacing:0.05em;">
                    <i class="bi bi-calendar-plus"></i>Reservar ahora
                </a>
            </div>
        <?php endif; ?>

        <!-- ── Teaser del programa de fidelidad ──────────── -->
        <a href="fidelidad.php" class="card-fidelidad-teaser flex items-center justify-between gap-3 p-4 sm:p-5 mb-4 block" style="opacity:0; animation:fadeInUp 0.45s var(--ease-out) 0.28s forwards;">
            <div class="flex-1 min-w-0">
                <div style="font-size:0.58rem; text-transform:uppercase; letter-spacing:0.22em; color:var(--gold); margin-bottom:5px;">
                    <i class="bi bi-award"></i>&nbsp;Programa de fidelidad
                </div>

                <!-- Texto personalizado según cuántos puntos tiene -->
                <div style="font-size:0.82rem; margin-bottom:10px;">
                    <?php if ($puntos >= 10): ?>
                        <strong style="color:var(--gold)">¡Tienes un corte gratis!</strong>
                        Díselo a Hassan en tu próxima visita
                    <?php elseif ($puntos >= 7): ?>
                        ¡Casi! Te faltan <strong style="color:var(--gold)"><?= 10 - $puntos ?></strong> para tu corte gratis
                    <?php else: ?>
                        Te faltan <strong style="color:var(--gold)"><?= 10 - $puntos ?> cortes</strong> para tu corte gratis
                    <?php endif; ?>
                </div>

                <!-- Mini stamps (PHP los renderiza, no necesitan JS) -->
                <div class="flex gap-1">
                    <?php for ($i = 0; $i < 10; $i++): ?>
                        <?php if ($i < $puntos): ?>
                            <div class="stamp-mini" style="background:var(--gold); border:1px solid var(--gold);"></div>
                        <?php else: ?>
                            <div class="stamp-mini" style="background:rgba(255,255,255,.04); border:1px solid var(--brd);"></div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <i class="bi bi-chevron-right flex-shrink-0" style="font-size:0.85rem; color:var(--tx-d);"></i>
        </a>

        <!-- ── Strip de últimas fotos ─────────────────────── -->
        <?php if (!empty($ultimas_fotos)): ?>

            <div class="flex items-center justify-between mb-2.5" style="opacity:0; animation:fadeInUp 0.45s var(--ease-out) 0.34s forwards;">
                <span style="font-size:0.72rem; font-weight:500;">Últimas fotos</span>
                <a href="fotos.php"
                   style="font-size:0.65rem; color:var(--tx-d); text-transform:uppercase; letter-spacing:0.08em;">
                    Ver todas <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <!-- Grid de 4 fotos: siempre 4 columnas porque son miniaturas -->
            <div class="grid grid-cols-4 gap-2">
                <?php foreach ($ultimas_fotos as $foto): ?>
                    <a href="fotos.php"
                       class="block rounded-lg overflow-hidden border"
                       style="aspect-ratio:1; background:var(--bg3); border-color:var(--brd);">
                        <img src="<?= h('../' . $foto['ruta']) ?>"
                             alt="Corte del <?= h($foto['fecha_subida']) ?>"
                             class="w-full h-full object-cover"
                             loading="lazy"
                             style="transition:transform 0.3s ease;"
                             onmouseover="this.style.transform='scale(1.05)'"
                             onmouseout="this.style.transform='scale(1)'">
                    </a>
                <?php endforeach; ?>

                <!-- Si tiene menos de 4 fotos, rellenamos con el botón de añadir -->
                <?php if ($total_fotos < 4): ?>
                    <?php for ($i = $total_fotos; $i < 4; $i++): ?>
                        <a href="fotos.php"
                           class="flex items-center justify-center rounded-lg border"
                           style="aspect-ratio:1; background:var(--card); border:1.5px dashed var(--brd-h);">
                            <i class="bi bi-plus" style="font-size:1.3rem; color:var(--tx-d);"></i>
                        </a>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- Estado vacío: todavía no tiene fotos -->
            <div class="flex items-center justify-between mb-2.5" style="opacity:0; animation:fadeInUp 0.45s var(--ease-out) 0.34s forwards;">
                <span style="font-size:0.72rem; font-weight:500;">Mis fotos</span>
                <a href="fotos.php" style="font-size:0.65rem; color:var(--gold);">
                    <i class="bi bi-camera"></i> Añadir
                </a>
            </div>

            <a href="fotos.php"
               class="flex flex-col items-center justify-center rounded-xl p-6 border text-center"
               style="background:var(--card); border:1.5px dashed var(--brd-h);">
                <i class="bi bi-camera" style="font-size:1.7rem; color:var(--tx-d);"></i>
                <div style="font-size:0.72rem; color:var(--tx-m); margin-top:8px;">
                    Todavía no tienes fotos
                </div>
                <div style="font-size:0.65rem; color:var(--tx-d); margin-top:4px;">
                    Súbelas para que Hassan pueda ver tu estilo
                </div>
            </a>
        <?php endif; ?>

    </div><!-- /max-w -->
</main>

<?php require_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>