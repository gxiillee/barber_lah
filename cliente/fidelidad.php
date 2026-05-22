<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Carga de dependencias ─────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';

// ── Fase 2: Sesión y control de acceso ────────────────────────────
session_start();

if (!isset($_SESSION['usuario'])) {
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];

if ($usuario->tieneRolAdmin()) {
    redirigir('../admin/index.php');
}

// ── Fase 3: Datos para la vista ───────────────────────────────────
$nombre = $usuario->getNombre();
$puntos = (int) $usuario->getPuntosFidelidad();

// Cálculos de presentación
$faltan       = 10 - $puntos;
$progreso_pct = ($puntos / 10) * 100;
$tiene_gratis = $puntos >= 10;

$pagina_activa = 'fidelidad';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Fidelidad — Barbershop La H</title>

    <!-- Tipografías -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,600&family=Montserrat:wght@300;400;500;600&display=swap"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Tailwind CSS v4 -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Animaciones propias (keyframes y clases de animación) -->
    <link rel="stylesheet" href="../public/assets/css/estilos.css">


</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php
// ── Incluimos la navegación compartida ────────────────────────────
require_once __DIR__ . '/includes/nav_cliente.php';
?>

<!-- ═══════════════════════════════════════════════════════════════
     CONTENIDO PRINCIPAL — PROGRAMA DE FIDELIDAD
     ═══════════════════════════════════════════════════════════════ -->
<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">
    <div class="flex-1 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        <!-- ── Cabecera de página ─────────────────────────── -->
        <div class="mb-6 lg:mb-8">
            <h1 class="leading-tight" style="font-family:var(--pf); font-size:clamp(1.5rem,4vw,2rem); font-weight:600;">
                Mi Fidelidad
            </h1>
            <p style="font-size:0.62rem; color:var(--tx-m); letter-spacing:0.22em; text-transform:uppercase; margin-top:3px;">
                Programa de Puntos
            </p>
        </div>

        <!-- ══════════════════════════════════════════════════
             MEMBER CARD — Tarjeta tipo crédito premium
             ══════════════════════════════════════════════════ -->
        <div class="member-card tarjeta-fidelidad mb-6 lg:mb-8">

            <!-- Fila superior: marca + titular -->
            <div class="flex items-start justify-between mb-6">
                <!-- Izquierda: logo -->
                <div>
                    <div style="font-family:var(--pf); color:var(--gold); font-size:0.95rem; letter-spacing:0.04em;">
                        Barbershop La H
                    </div>
                    <div style="font-size:0.52rem; color:var(--tx-d); letter-spacing:0.28em; text-transform:uppercase; margin-top:2px;">
                        Member Card
                    </div>
                </div>

                <!-- Derecha: titular -->
                <div class="text-right">
                    <div style="font-size:0.52rem; color:var(--tx-d); letter-spacing:0.2em; text-transform:uppercase;">
                        Titular
                    </div>
                    <div class="mt-0.5" style="font-family:var(--pf); font-size:0.9rem; color:var(--tx);">
                        <?= h($nombre) ?>
                    </div>
                </div>
            </div>

            <!-- Track de 10 sellos (stamps) -->
            <div class="flex items-center justify-center gap-2 sm:gap-3 flex-wrap mb-5">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <?php if ($i <= $puntos): ?>
                        <!-- Sello LLENO — animación pop escalonada -->
                        <div class="stamp stamp-lleno"
                             style="background:var(--gold); border:1.5px solid var(--gold); animation-delay:<?= ($i - 1) * 0.06 ?>s;">
                            <i class="bi bi-check" style="font-size:0.85rem; color:var(--bg); font-weight:700;"></i>
                        </div>
                    <?php elseif ($i === $puntos + 1 && !$tiene_gratis): ?>
                        <!-- Sello SIGUIENTE — pulso dorado que incita a reservar -->
                        <div class="stamp stamp-siguiente"
                             style="background:transparent; border:1.5px dashed var(--gold-brd);">
                            <i class="bi bi-scissors" style="font-size:0.65rem; color:var(--gold); opacity:0.5;"></i>
                        </div>
                    <?php else: ?>
                        <!-- Sello VACÍO -->
                        <div class="stamp"
                             style="background:rgba(255,255,255,.02); border:1.5px solid var(--brd-h);">
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <!-- Barra de progreso -->
            <div class="rounded-full overflow-hidden mb-4" style="height:6px; background:rgba(255,255,255,.06);">
                <div class="barra-progreso-fill rounded-full h-full"
                     style="--progreso:<?= $progreso_pct ?>%; background:linear-gradient(90deg, var(--gold), var(--gold-l));">
                </div>
            </div>

            <!-- Texto de estado dinámico -->
            <div class="text-center">
                <?php if ($tiene_gratis): ?>
                    <div class="badge-gratis inline-flex items-center gap-2 rounded-full px-4 py-1.5"
                         style="background:var(--gold-dim); border:1px solid var(--gold-brd);">
                        <i class="bi bi-gift-fill" style="color:var(--gold); font-size:0.85rem;"></i>
                        <span style="font-size:0.76rem; font-weight:600; color:var(--gold);">
                            ¡Tienes un corte gratis disponible!
                        </span>
                    </div>
                    <p style="font-size:0.62rem; color:var(--tx-d); margin-top:8px;">
                        Díselo a Hassan en tu próxima visita
                    </p>
                <?php else: ?>
                    <p style="font-size:0.76rem; color:var(--tx-m);">
                        <?php if ($faltan === 1): ?>
                            ¡Solo te falta <strong style="color:var(--gold)">1 corte</strong> para tu corte gratis!
                        <?php else: ?>
                            Faltan <strong style="color:var(--gold)"><?= $faltan ?> cortes</strong> para tu próximo corte gratis
                        <?php endif; ?>
                    </p>
                    <p style="font-size:0.58rem; color:var(--tx-d); margin-top:4px;">
                        <?= $puntos ?> / 10 puntos acumulados
                    </p>
                <?php endif; ?>
            </div>
        </div>


        <!-- ══════════════════════════════════════════════════
             SECCIÓN: ¿Cómo funciona el programa?
             ══════════════════════════════════════════════════ -->
        <div class="mb-6">
            <h2 style="font-size:0.62rem; text-transform:uppercase; letter-spacing:0.22em; color:var(--tx-m); margin-bottom:14px;">
                <i class="bi bi-info-circle" style="font-size:0.7rem;"></i>&nbsp;
                ¿Cómo funciona?
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

                <!-- Card 1: Acumula puntos -->
                <div class="info-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center rounded-lg"
                             style="width:36px; height:36px; background:var(--gold-dim); border:1px solid var(--gold-brd); flex-shrink:0;">
                            <i class="bi bi-calendar-check" style="font-size:0.9rem; color:var(--gold);"></i>
                        </div>
                        <h3 style="font-family:var(--pf); font-size:0.9rem; font-weight:600;">
                            1 cita = 1 punto
                        </h3>
                    </div>
                    <p style="font-size:0.72rem; color:var(--tx-m); line-height:1.55;">
                        Cada cita completada suma un punto automáticamente a tu tarjeta de fidelidad. No tienes que hacer nada extra.
                    </p>
                </div>

                <!-- Card 2: Canjea tu corte -->
                <div class="info-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center rounded-lg"
                             style="width:36px; height:36px; background:var(--gold-dim); border:1px solid var(--gold-brd); flex-shrink:0;">
                            <i class="bi bi-gift" style="font-size:0.9rem; color:var(--gold);"></i>
                        </div>
                        <h3 style="font-family:var(--pf); font-size:0.9rem; font-weight:600;">
                            10 puntos = corte gratis
                        </h3>
                    </div>
                    <p style="font-size:0.72rem; color:var(--tx-m); line-height:1.55;">
                        Al llegar a 10 puntos, tu siguiente corte de pelo es completamente gratis. Solo díselo a Hassan al reservar.
                    </p>
                </div>

                <!-- Card 3: Reinicio automático -->
                <div class="info-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center rounded-lg"
                             style="width:36px; height:36px; background:var(--gold-dim); border:1px solid var(--gold-brd); flex-shrink:0;">
                            <i class="bi bi-arrow-repeat" style="font-size:0.9rem; color:var(--gold);"></i>
                        </div>
                        <h3 style="font-family:var(--pf); font-size:0.9rem; font-weight:600;">
                            Reinicio automático
                        </h3>
                    </div>
                    <p style="font-size:0.72rem; color:var(--tx-m); line-height:1.55;">
                        Al canjear tu corte gratis, el contador de puntos vuelve a cero y puedes empezar a acumular de nuevo. ¡Sin límite!
                    </p>
                </div>

            </div><!-- /grid -->
        </div>

    </div><!-- /max-w container -->
</main>

</body>
</html>
