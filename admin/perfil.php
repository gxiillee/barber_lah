<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Administrador.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Servicio.php';
require_once __DIR__ . '/../clases/helpers.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

$admin = $_SESSION['usuario'];
$admin_avatar = $admin->getAvatar();
$admin_nombre = $admin->getNombre();
$admin_email  = $admin->getEmail();
$admin_telefono = $admin->getTelefono();

$resumen_mes  = Administrador::obtenerResumenMes();

// Total de clientes y servicios
$conexion = BD::obtenerConexion();
$total_clientes   = (int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente'")->fetchColumn();
$total_servicios  = (int)$conexion->query("SELECT COUNT(*) FROM servicios WHERE activo = TRUE")->fetchColumn();
$total_reservas   = (int)$conexion->query("SELECT COUNT(*) FROM reservas")->fetchColumn();

$pagina_activa = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil — Panel Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-[900px] pagina-entrada">

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Mi Perfil</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Panel de administración de Barbershop La H</p>
    </div>

    <!-- Admin profile card -->
    <div class="glow-card rounded-2xl border border-[var(--brd)] bg-white/[0.025] p-6 sm:p-8 mb-6">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 sm:gap-6">
            <div class="relative shrink-0">
                <?php if ($admin_avatar): ?>
                    <img src="<?= h($admin_avatar) ?>" alt="Avatar" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-2 border-[var(--gold-brd)] object-cover">
                <?php else: ?>
                    <span class="flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-[var(--gold-dim)] border-2 border-[var(--gold-brd)] font-bold text-[2rem] text-[var(--gold)]" style="font-family:var(--pf);">
                        <?= mb_strtoupper(mb_substr($admin_nombre, 0, 1, 'UTF-8'), 'UTF-8') ?>
                    </span>
                <?php endif; ?>
                <span class="absolute -bottom-0.5 -right-0.5 w-6 h-6 rounded-full bg-[var(--gold)] text-[#0d0d0d] text-[0.65rem] flex items-center justify-center border-2 border-[var(--bg)]">
                    <i class="bi bi-shield-fill-check"></i>
                </span>
            </div>

            <div class="flex flex-col items-center sm:items-start text-center sm:text-left min-w-0 flex-1">
                <h2 class="text-[1.3rem] font-bold text-[var(--tx)]" style="font-family:var(--pf);"><?= h($admin_nombre) ?></h2>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span class="text-[0.62rem] font-semibold uppercase tracking-wider text-[var(--gold)]">Administrador</span>
                </div>

                <div class="flex flex-wrap gap-4 mt-4 text-[0.75rem]">
                    <span class="flex items-center gap-1.5 text-[var(--tx-m)]">
                        <i class="bi bi-envelope text-[var(--gold)]"></i> <?= h($admin_email) ?>
                    </span>
                    <?php if ($admin_telefono): ?>
                        <span class="flex items-center gap-1.5 text-[var(--tx-m)]">
                            <i class="bi bi-telephone text-[var(--gold)]"></i> <?= h($admin_telefono) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="flex gap-3 mt-5">
                    <a href="../cliente/cambiar_password.php"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-[0.65rem] font-semibold uppercase tracking-wider border border-[var(--brd)] text-[var(--tx-m)] hover:bg-white/5 hover:text-[var(--tx)] transition-all">
                        <i class="bi bi-key"></i> Cambiar contraseña
                    </a>
                    <a href="../logout.php"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-[0.65rem] font-semibold uppercase tracking-wider border border-red-500/20 text-red-400 hover:bg-red-500/10 transition-all">
                        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly stats -->
    <h3 class="text-[0.7rem] font-bold uppercase tracking-widest text-[var(--tx-d)] mb-3">Resumen del mes</h3>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="glow-card flex flex-col gap-1 px-4 py-4 rounded-xl border border-[var(--brd)] bg-white/[0.03]">
            <span class="text-[0.55rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Citas totales</span>
            <span class="text-[1.6rem] font-bold leading-none text-[var(--tx)]" style="font-family:var(--pf);"><?= $resumen_mes['total'] ?></span>
        </div>
        <div class="glow-card flex flex-col gap-1 px-4 py-4 rounded-xl border border-[var(--brd)] bg-white/[0.03]">
            <span class="text-[0.55rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Completadas</span>
            <span class="text-[1.6rem] font-bold leading-none text-[var(--tx)]" style="font-family:var(--pf);"><?= $resumen_mes['completadas'] ?></span>
        </div>
        <div class="glow-card flex flex-col gap-1 px-4 py-4 rounded-xl border border-[var(--gold-brd)] bg-[var(--gold-dim)]">
            <span class="text-[0.55rem] uppercase tracking-widest font-semibold text-[var(--gold)]/70">Ingresos</span>
            <span class="text-[1.6rem] font-bold leading-none text-[var(--gold)]" style="font-family:var(--pf);"><?= number_format($resumen_mes['ingresos'], 0, ',', '.') ?>€</span>
        </div>
        <div class="glow-card flex flex-col gap-1 px-4 py-4 rounded-xl border border-[var(--brd)] bg-white/[0.03]">
            <span class="text-[0.55rem] uppercase tracking-widest font-semibold text-[var(--tx-d)]">Clientes nuevos</span>
            <span class="text-[1.6rem] font-bold leading-none text-[var(--tx)]" style="font-family:var(--pf);"><?= $resumen_mes['clientes_nuevos'] ?></span>
        </div>
    </div>

    <!-- System stats -->
    <h3 class="text-[0.7rem] font-bold uppercase tracking-widest text-[var(--tx-d)] mb-3">Estado del sistema</h3>
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="flex flex-col items-center gap-1 px-3 py-4 rounded-xl border border-[var(--brd)] bg-white/[0.02]">
            <i class="bi bi-people text-[var(--gold)] text-lg"></i>
            <span class="text-[1.3rem] font-bold text-[var(--tx)]" style="font-family:var(--pf);"><?= $total_clientes ?></span>
            <span class="text-[0.55rem] uppercase tracking-wider text-[var(--tx-d)]">Clientes</span>
        </div>
        <div class="flex flex-col items-center gap-1 px-3 py-4 rounded-xl border border-[var(--brd)] bg-white/[0.02]">
            <i class="bi bi-scissors text-[var(--gold)] text-lg"></i>
            <span class="text-[1.3rem] font-bold text-[var(--tx)]" style="font-family:var(--pf);"><?= $total_servicios ?></span>
            <span class="text-[0.55rem] uppercase tracking-wider text-[var(--tx-d)]">Servicios</span>
        </div>
        <div class="flex flex-col items-center gap-1 px-3 py-4 rounded-xl border border-[var(--brd)] bg-white/[0.02]">
            <i class="bi bi-calendar-check text-[var(--gold)] text-lg"></i>
            <span class="text-[1.3rem] font-bold text-[var(--tx)]" style="font-family:var(--pf);"><?= $total_reservas ?></span>
            <span class="text-[0.55rem] uppercase tracking-wider text-[var(--tx-d)]">Reservas</span>
        </div>
    </div>

</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

</body>
</html>
