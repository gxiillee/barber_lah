<?php
declare(strict_types=1);
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';

session_start();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../login.php');
}

$mensaje = $error = '';

// Procesar eliminación de cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && Usuario::eliminar($id)) {
        $mensaje = "Cliente eliminado correctamente de la base de datos.";
    } else {
        $error = "No se ha podido eliminar al cliente.";
    }
}

// Obtenemos solo los clientes
$clientes = Usuario::listarClientes();
$pagina_activa = 'clientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes — Panel Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Directorio de Clientes</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Consulta los datos de contacto y fidelidad de tus clientes</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-400 text-[0.75rem] flex items-center gap-2">
            <i class="bi bi-check-circle-fill"></i> <?= h($mensaje) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <section class="space-y-2">
        <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)]">
                Cartera de Clientes (<?= count($clientes) ?>)
            </h2>
        </div>

        <?php if (empty($clientes)): ?>
            <div class="flex flex-col items-center justify-center py-12 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-2 opacity-60">
                <i class="bi bi-people text-2xl text-[var(--tx-d)]"></i>
                <p class="text-[0.75rem] text-[var(--tx-m)]">Aún no hay clientes registrados en el sistema.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <?php foreach ($clientes as $c): ?>
                    <div class="slot-card flex items-center gap-4 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/5 min-h-[64px]">

                        <div class="w-10 h-10 rounded-full bg-[#1a1a1a] border border-[var(--brd)] flex items-center justify-center text-[var(--tx-m)] font-semibold shrink-0 uppercase">
                            <?= substr(h($c['nombre']), 0, 1) ?>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="text-[0.85rem] font-semibold text-[var(--tx)] truncate">
                                <?= h($c['nombre']) ?>
                            </div>

                            <div class="flex flex-col gap-0.5 mt-1">
                                <?php if (!empty($c['telefono'])): ?>
                                    <div class="text-[0.65rem] text-[var(--tx-m)] flex items-center gap-1.5 truncate">
                                        <i class="bi bi-telephone text-[var(--gold)]"></i> <?= h($c['telefono']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-[0.65rem] text-[var(--tx-d)] flex items-center gap-1.5 truncate">
                                    <i class="bi bi-envelope"></i> <?= h($c['email']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 border-l border-[var(--brd)]/50 pl-3">
                            <div class="flex flex-col items-center justify-center">
                                <span class="text-[0.85rem] font-bold text-[var(--gold)] leading-none"><?= (int)$c['puntos_fidelidad'] ?></span>
                                <span class="text-[0.55rem] uppercase tracking-wider text-[var(--tx-d)] mt-0.5">Puntos</span>
                            </div>

                            <form action="" method="POST" onsubmit="return confirm('¿Seguro que quieres borrar a este cliente? Perderá sus puntos de fidelidad e historial de reservas.');" class="shrink-0">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="w-7 h-7 rounded-lg border border-transparent text-[var(--tx-d)] flex items-center justify-center hover:bg-rose-500/10 hover:text-rose-400 transition-all cursor-pointer">
                                    <i class="bi bi-trash3 text-[0.8rem]"></i>
                                </button>
                            </form>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>
</body>
</html>