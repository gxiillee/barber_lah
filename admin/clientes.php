<?php
declare(strict_types=1);
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Administrador.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';

session_start();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../login.php');
}

// Search handling
$busqueda = trim($_GET['buscar'] ?? '');
if ($busqueda !== '') {
    $clientes = Administrador::buscarClientes($busqueda);
} else {
    $clientes = Administrador::obtenerTodosLosClientes();
}

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

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none pagina-entrada">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Directorio de Clientes</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Busca, consulta y gestiona tus clientes</p>
    </div>

    <!-- Search bar -->
    <div class="mb-5">
        <form action="" method="GET" id="searchForm" class="relative">
            <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--tx-d)] text-[0.9rem] pointer-events-none"></i>
            <input type="text" name="buscar" id="searchInput" value="<?= h($busqueda) ?>"
                   placeholder="Buscar por nombre o email..."
                   autocomplete="off"
                   class="w-full bg-[#141414] border border-[var(--brd)] rounded-xl pl-10 pr-4 py-3 text-[0.82rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] transition-all placeholder:text-[var(--tx-d)]/60">
            <?php if ($busqueda !== ''): ?>
                <a href="clientes.php" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-white/10 flex items-center justify-center text-[var(--tx-d)] hover:bg-white/20 hover:text-[var(--tx)] transition-all">
                    <i class="bi bi-x text-[0.7rem]"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($busqueda !== '' && !empty($clientes)): ?>
        <div class="mb-4 text-[0.7rem] text-[var(--tx-m)]">
            <?= count($clientes) ?> resultado<?= count($clientes) !== 1 ? 's' : '' ?> para "<strong class="text-[var(--tx)]"><?= h($busqueda) ?></strong>"
        </div>
    <?php endif; ?>

    <section class="space-y-2">
        <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)]">
                <?= $busqueda ? 'Resultados' : 'Cartera de Clientes' ?> (<?= count($clientes) ?>)
            </h2>
        </div>

        <?php if (empty($clientes)): ?>
            <div class="flex flex-col items-center justify-center py-16 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-3 opacity-60">
                <i class="bi bi-people text-3xl text-[var(--tx-d)]"></i>
                <p class="text-[0.75rem] text-[var(--tx-m)]">
                    <?= $busqueda ? 'No se encontraron clientes con ese criterio.' : 'Aún no hay clientes registrados en el sistema.' ?>
                </p>
                <?php if ($busqueda): ?>
                    <a href="clientes.php" class="text-[0.7rem] text-[var(--gold)] underline underline-offset-2 hover:opacity-80">Ver todos los clientes</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 stagger-container" id="clientesGrid">
                <?php foreach ($clientes as $c): ?>
                    <div class="slot-card glow-card flex items-center gap-4 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/5 min-h-[64px]">

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

                            <div class="flex flex-col items-center justify-center">
                                <span class="text-[0.85rem] font-bold text-[var(--tx)] leading-none"><?= (int)$c['total_reservas'] ?></span>
                                <span class="text-[0.55rem] uppercase tracking-wider text-[var(--tx-d)] mt-0.5">Citas</span>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

<script>
// Live search with debounce
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    let timeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            if (this.value.trim() !== '') {
                window.location.href = 'clientes.php?buscar=' + encodeURIComponent(this.value.trim());
            } else {
                window.location.href = 'clientes.php';
            }
        }, 400);
    });
}
</script>

</body>
</html>
