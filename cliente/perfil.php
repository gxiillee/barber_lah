<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Dependencias ──────────────────────────────────────────
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';

// ── Fase 2: Control de acceso ─────────────────────────────────────
session_start();
if (!isset($_SESSION['usuario'])) {
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];
$pagina_activa = 'perfil'; // Para marcarlo en el nav si lo necesitas
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

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col panel-main">
    <div class="flex-1 w-full max-w-md mx-auto p-4 sm:p-6 flex flex-col gap-6 justify-center">

        <div class="bg-[var(--bg2)] border border-[var(--brd)] rounded-2xl p-6 flex flex-col items-center text-center gap-4">
            <div class="w-20 h-20 rounded-full border-2 border-[var(--gold)] overflow-hidden">
                <img src="<?= h($usuario->getAvatar()) ?>" alt="Avatar" class="w-full h-full object-cover">
            </div>
            <div>
                <h2 class="text-[var(--tx)] font-semibold text-xl"><?= h($usuario->getNombre()) ?></h2>
                <p class="text-[var(--tx-m)] text-sm"><?= h($usuario->getEmail()) ?></p>
            </div>
        </div>

        <div class="flex flex-col gap-3">

            <a href="cambiar_password.php" class="w-full p-4 rounded-xl bg-[var(--bg2)] border border-[var(--brd)] text-[var(--tx)] hover:border-[var(--brd-h)] transition-all flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="bi bi-shield-lock text-[var(--gold)] text-lg"></i>
                    <span class="text-sm font-medium">Cambiar Contraseña</span>
                </div>
                <i class="bi bi-chevron-right text-[var(--tx-d)] text-xs"></i>
            </a>

            <a href="../index.php" class="w-full p-4 rounded-xl bg-[var(--bg2)] border border-[var(--brd)] text-[var(--tx)] hover:border-[var(--brd-h)] transition-all flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="bi bi-house text-[var(--tx-m)] text-lg"></i>
                    <span class="text-sm font-medium">Volver a la web principal</span>
                </div>
                <i class="bi bi-chevron-right text-[var(--tx-d)] text-xs"></i>
            </a>

            <a href="../logout.php" class="w-full p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 transition-all flex items-center justify-center gap-2 font-semibold text-sm mt-4">
                <i class="bi bi-box-arrow-right"></i>
                Cerrar Sesión
            </a>

        </div>

    </div>
</main>

</body>
</html>