<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Dependencias ──────────────────────────────────────────
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Cliente.php';

// ── Fase 2: Control de acceso ─────────────────────────────────────
session_start();
if (!isset($_SESSION['usuario'])) {
    $_SESSION['volver_panel'] = 'index.php';
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];

// ── Datos de perfil ──
$id_usuario  = (int) $usuario->getId();
$nombre      = $usuario->getNombre() ?? 'Cliente';
$email       = $usuario->getEmail();
$telefono    = $usuario->getTelefono() ?? '';
$avatar      = $usuario->getAvatar();
$inicial     = mb_strtoupper(mb_substr($nombre, 0, 1, 'UTF-8'), 'UTF-8');
$puntos      = (int) $usuario->getPuntosFidelidad();
$fecha_reg   = $usuario->getCreatedAt();

// ── Cálculo de días desde el registro ──
$dias_miembro = 0;
if ($fecha_reg) {
    try {
        $reg_dt = new DateTimeImmutable($fecha_reg);
        $dias_miembro = (int) $reg_dt->diff(new DateTimeImmutable())->format('%a');
    } catch (Exception $e) {}
}

// ── Streak: citas en los últimos 90 días ──
require_once __DIR__ . '/../clases/Reserva.php';
$historial_reciente = Reserva::obtenerHistorialPorCliente($id_usuario);
$streak = 0;
$hoy = new DateTimeImmutable();
foreach ($historial_reciente as $cita) {
    if ($cita['estado'] !== 'completada') continue;
    try {
        $cita_dt = new DateTimeImmutable($cita['fecha']);
        $diff = (int) $hoy->diff($cita_dt)->format('%a');
        if ($diff <= 90) $streak++;
    } catch (Exception $e) {}
}

$tienePassword = $usuario->tienePassword();
$total_citas   = 0;
foreach ($historial_reciente as $c) {
    if ($c['estado'] === 'completada') $total_citas++;
}

// ── Último cambio de contraseña ──
$diasPass = null;
if ($tienePassword) {
    $ultimoCambio = Usuario::obtenerFechaUltimoCambioPassword($id_usuario);
    if ($ultimoCambio) {
        $diasPass = floor((time() - strtotime($ultimoCambio)) / 86400);
    }
}

$pagina_activa = 'perfil';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil — Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php require_once __DIR__ . '/includes/nav_cliente.php'; ?>

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">
    <div class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-8 lg:px-12 py-4 sm:py-6 lg:py-10 stagger-container">

        <!-- ── Cabecera de página ── -->
        <div class="mb-6 lg:mb-10">
            <h1 style="font-family:var(--pf); font-size:clamp(1.6rem,3vw,2.2rem); font-weight:600; line-height:1.15;">
                Mi Perfil
            </h1>
            <p style="font-size:0.65rem; color:var(--tx-m); letter-spacing:0.22em; text-transform:uppercase; margin-top:4px;">
                Tus datos personales
            </p>
        </div>

        <!-- ── Grid responsive: móvil 1 col, desktop 2 cols ── -->
        <div class="grid lg:grid-cols-[380px_1fr] gap-6 lg:gap-10 xl:gap-14">

            <!-- ═══ Columna izquierda: Avatar + stats ═══ -->
            <div class="flex flex-col gap-4 lg:gap-5">

                <!-- Avatar card -->
                <div class="rounded-2xl p-8 sm:p-8 border flex flex-col items-center text-center gap-5" style="background:var(--bg2); border-color:var(--brd);">
                    <div class="relative" style="width:96px; height:96px;">
                        <svg class="absolute inset-0 w-full h-full" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="46" fill="none" stroke="var(--gold-brd)" stroke-width="3"/>
                            <circle cx="50" cy="50" r="46" fill="none" stroke="var(--gold)" stroke-width="3"
                                    stroke-dasharray="289.03" stroke-dashoffset="<?= 289.03 * (1 - $puntos / 10) ?>"
                                    class="progress-ring-circle" stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-2 rounded-full overflow-hidden border-2 border-transparent flex items-center justify-center" style="background:var(--bg3);">
                            <?php if (!empty($avatar)): ?>
                                <img src="<?= h($avatar) ?>" alt="Avatar" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span style="font-family:var(--pf); color:var(--gold); font-size:1.8rem;"><?= h($inicial) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <h2 style="font-size:1.3rem; font-weight:600; color:var(--tx);"><?= h($nombre) ?></h2>
                        <p style="font-size:0.85rem; color:var(--tx-m); margin-top:3px;"><?= h($email) ?></p>
                        <?php if ($telefono): ?>
                            <p style="font-size:0.78rem; color:var(--tx-d); margin-top:2px;"><i class="bi bi-whatsapp" style="color:var(--gold);"></i> <?= h($telefono) ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.68rem; color:var(--gold); letter-spacing:0.06em;">
                        <i class="bi bi-star-fill" style="font-size:0.55rem;"></i> <?= $puntos ?>/10 puntos ·
                        <span style="color:var(--tx-d);">
                            <?= $dias_miembro > 0 ? $dias_miembro . ' días como miembro' : 'Miembro reciente' ?>
                        </span>
                    </div>
                </div>

                <!-- Stats en desktop -->
                <div class="hidden lg:grid grid-cols-2 gap-4">
                    <div class="rounded-xl p-5 border glow-card" style="background:var(--card); border-color:var(--brd);">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="bi bi-fire streak-fire" style="color:var(--gold); font-size:0.95rem;"></i>
                            <span style="font-size:0.6rem; text-transform:uppercase; letter-spacing:0.16em; color:var(--tx-m);">Visitas recientes</span>
                        </div>
                        <span style="font-family:var(--pf); font-size:1.8rem; color:var(--gold);"><?= $streak ?></span>
                        <span style="font-size:0.68rem; color:var(--tx-d); margin-left:8px;">en 90 días</span>
                    </div>
                    <div class="rounded-xl p-5 border glow-card" style="background:var(--card); border-color:var(--brd);">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="bi bi-award" style="color:var(--gold); font-size:0.95rem;"></i>
                            <span style="font-size:0.6rem; text-transform:uppercase; letter-spacing:0.16em; color:var(--tx-m);">Fidelidad</span>
                        </div>
                        <div class="flex items-baseline gap-1.5">
                            <?php if ($puntos >= 10): ?>
                                <span style="font-size:1.5rem;">🎉</span>
                            <?php else: ?>
                                <span style="font-family:var(--pf); font-size:1.8rem; color:var(--tx);"><?= 10 - $puntos ?></span>
                                <i class="bi bi-scissors" style="font-size:0.9rem; color:var(--gold);"></i>
                                <span style="font-size:0.68rem; color:var(--tx-d);">para gratis</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Columna derecha: stats (móvil) + acciones ═══ -->
            <div class="flex flex-col gap-4 lg:gap-5">

                <!-- Stats en móvil -->
                <div class="grid grid-cols-2 gap-3 lg:hidden">
                    <div class="rounded-xl p-4 border glow-card" style="background:var(--card); border-color:var(--brd);">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="bi bi-fire streak-fire" style="color:var(--gold); font-size:0.85rem;"></i>
                            <span style="font-size:0.56rem; text-transform:uppercase; letter-spacing:0.16em; color:var(--tx-m);">Visitas recientes</span>
                        </div>
                        <span style="font-family:var(--pf); font-size:1.5rem; color:var(--gold);"><?= $streak ?></span>
                        <span style="font-size:0.62rem; color:var(--tx-d); margin-left:6px;">en 90 días</span>
                    </div>
                    <div class="rounded-xl p-4 border glow-card" style="background:var(--card); border-color:var(--brd);">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="bi bi-award" style="color:var(--gold); font-size:0.85rem;"></i>
                            <span style="font-size:0.56rem; text-transform:uppercase; letter-spacing:0.16em; color:var(--tx-m);">Fidelidad</span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <?php if ($puntos >= 10): ?>
                                <span style="font-size:1.3rem;">🎉</span>
                            <?php else: ?>
                                <span style="font-family:var(--pf); font-size:1.5rem; color:var(--tx);"><?= 10 - $puntos ?></span>
                                <i class="bi bi-scissors" style="font-size:0.75rem; color:var(--gold);"></i>
                                <span style="font-size:0.62rem; color:var(--tx-d);">para gratis</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="flex flex-col gap-3">
                    <a href="cambiar_password.php"
                       class="w-full p-5 rounded-xl border flex items-center justify-between transition-all hover:-translate-y-0.5 glow-card"
                       style="background:var(--bg2); border-color:var(--brd); color:var(--tx);">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg" style="background:var(--gold-dim);">
                                <i class="bi bi-shield-lock" style="color:var(--gold); font-size:1.15rem;"></i>
                            </div>
                            <div>
                                <span style="font-size:0.9rem; font-weight:500; display:block;">
                                    <?php if (!$tienePassword): ?>
                                        <span style="color:var(--gold);">Establecer contraseña</span>
                                    <?php else: ?>
                                        Cambiar contraseña
                                    <?php endif; ?>
                                </span>
                                <span style="font-size:0.7rem; color:var(--tx-d);">
                                    <?php if ($diasPass !== null): ?>
                                        Cambiada hace <?= $diasPass === 0 ? 'hoy' : ($diasPass === 1 ? 'ayer' : number_format($diasPass) . ' días') ?>
                                    <?php else: ?>
                                        Seguridad de la cuenta
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right" style="color:var(--tx-d); font-size:0.85rem;"></i>
                    </a>

                    <a href="../index.php"
                       class="w-full p-5 rounded-xl border flex items-center justify-between transition-all hover:-translate-y-0.5 glow-card"
                       style="background:var(--bg2); border-color:var(--brd); color:var(--tx);">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg" style="background:var(--card);">
                                <i class="bi bi-globe2" style="color:var(--tx-m); font-size:1.15rem;"></i>
                            </div>
                            <div>
                                <span style="font-size:0.9rem; font-weight:500; display:block;">Web principal</span>
                                <span style="font-size:0.7rem; color:var(--tx-d);">Volver a la página pública</span>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right" style="color:var(--tx-d); font-size:0.85rem;"></i>
                    </a>

                    <a href="../logout.php"
                       class="w-full p-5 rounded-xl border flex items-center justify-between transition-all hover:-translate-y-0.5"
                       style="background:rgba(239,68,68,0.06); border-color:rgba(239,68,68,0.12); color:#f87171;">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg" style="background:rgba(239,68,68,0.1);">
                                <i class="bi bi-box-arrow-right" style="font-size:1.15rem;"></i>
                            </div>
                            <div>
                                <span style="font-size:0.9rem; font-weight:500; display:block;">Cerrar sesión</span>
                                <span style="font-size:0.7rem; color:rgba(248,113,113,0.6);">Desconectarte de tu cuenta</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Info adicional solo en desktop -->
                <div class="hidden lg:flex items-center gap-3 rounded-xl p-5 border" style="background:var(--card); border-color:var(--brd);">
                    <i class="bi bi-info-circle" style="color:var(--gold); font-size:1.1rem;"></i>
                    <p style="font-size:0.72rem; color:var(--tx-d); line-height:1.5; margin:0;">
                        Miembro desde hace <?= $dias_miembro > 0 ? $dias_miembro . ' días' : 'hoy' ?> ·
                        <?= $total_citas ?> cortes realizados
                    </p>
                </div>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>