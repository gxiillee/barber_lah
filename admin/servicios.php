<?php
declare(strict_types=1);
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Servicio.php';
require_once __DIR__ . '/../clases/helpers.php';

session_start();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../login.php');
}

$mensaje = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $duracionMin = (int)($_POST['duracion'] ?? 0);
        $precio      = (float)($_POST['precio'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descripcion = $descripcion === '' ? null : $descripcion;

        if (!empty($nombre) && $duracionMin > 0 && $precio > 0) {
            if (Servicio::crear($nombre, $duracionMin, $precio, $descripcion)) {
                $mensaje = "Servicio añadido correctamente al catálogo.";
            } else {
                $error = "Error al guardar el servicio en la base de datos.";
            }
        } else {
            $error = "Por favor, rellena nombre, duración y precio obligatoriamente.";
        }
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && Servicio::eliminar($id)) {
            $mensaje = "Servicio retirado. Ya no aparecerá a los clientes.";
        } else {
            $error = "No se pudo retirar el servicio.";
        }
    }
}

// USAMOS TU MÉTODO EXACTO (Devuelve objetos Servicio activos)
$servicios = Servicio::obtenerTodos();
$pagina_activa = 'servicios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios — Panel Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Servicios</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Crea y gestiona los servicios ofrecidos en la barbería</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-400 text-[0.75rem] flex items-center gap-2"><i class="bi bi-check-circle-fill"></i> <?= h($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] flex items-center gap-2"><i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <section class="lg:col-span-5 rounded-xl border border-[var(--brd)] bg-white/5 p-5">
            <h2 class="text-[0.95rem] font-medium text-[var(--tx)] mb-4 flex items-center gap-2" style="font-family: var(--pf);">
                <i class="bi bi-scissors text-[var(--gold)]"></i> Nuevo Servicio
            </h2>
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="crear">

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Nombre del Servicio *</label>
                    <input type="text" name="nombre" required placeholder="Ej: Corte Degradado" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Duración (min) *</label>
                        <input type="number" name="duracion" required min="5" step="5" placeholder="30" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                    </div>
                    <div>
                        <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Precio (€) *</label>
                        <input type="number" name="precio" required min="1" step="0.50" placeholder="15.00" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                    </div>
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Descripción (Opcional)</label>
                    <textarea name="descripcion" rows="2" placeholder="Detalles extra del servicio..." class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] resize-none"></textarea>
                </div>

                <button type="submit" class="w-full bg-[var(--gold)] hover:opacity-90 text-[#0d0d0d] font-semibold py-2.5 rounded-lg text-[0.72rem] tracking-widest uppercase transition-all mt-2 cursor-pointer">
                    Añadir al Catálogo
                </button>
            </form>
        </section>

        <section class="lg:col-span-7 space-y-2">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)] mb-3 px-1">Servicios Activos (<?= count($servicios) ?>)</h2>

            <?php if (empty($servicios)): ?>
                <div class="flex flex-col items-center justify-center py-12 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-2 opacity-60">
                    <i class="bi bi-scissors text-2xl text-[var(--tx-d)]"></i>
                    <p class="text-[0.75rem] text-[var(--tx-m)]">No hay servicios registrados.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-2">
                    <?php foreach ($servicios as $s): ?>
                        <div class="slot-card flex items-center justify-between gap-4 px-4 py-3.5 rounded-xl border border-[var(--brd)] bg-white/5 min-h-[64px]">

                            <div class="flex-1 min-w-0">
                                <div class="text-[0.85rem] font-semibold text-[var(--tx)] truncate"><?= h($s->getNombre()) ?></div>
                                <div class="flex gap-3 mt-1">
                                    <span class="flex items-center gap-1 text-[0.65rem] text-[var(--tx-m)]">
                                        <i class="bi bi-clock text-[var(--gold)]"></i> <?= $s->getDuracion() ?> min
                                    </span>
                                    <span class="flex items-center gap-1 text-[0.65rem] text-[var(--tx-m)]">
                                        <i class="bi bi-currency-euro text-[var(--gold)]"></i> <?= number_format($s->getPrecio(), 2, ',', '.') ?>
                                    </span>
                                </div>
                                <?php if ($s->getDescripcion()): ?>
                                    <div class="text-[0.65rem] text-[var(--tx-d)] mt-1.5 truncate italic">
                                        <?= h($s->getDescripcion()) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form action="" method="POST" onsubmit="return confirm('¿Seguro que quieres retirar este servicio? No aparecerá para nuevas citas.');" class="shrink-0">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $s->getIdServicio() ?>">
                                <button type="submit" class="w-8 h-8 rounded-lg border border-transparent text-[var(--tx-d)] flex items-center justify-center hover:bg-rose-500/10 hover:text-rose-400 transition-all cursor-pointer">
                                    <i class="bi bi-eye-slash-fill text-[0.85rem]"></i>
                                </button>
                            </form>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>