<?php
/**
 * admin/galeria.php — Gestión de galería de cortes (MongoDB)
 *
 * CRUD completo sobre la colección barberlah.galeria.
 * Las fotos activas se muestran en el carrusel de la landing pública.
 */
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../clases/BdMongo.php';
require_once __DIR__ . '/../clases/Galeria_corte.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/helpers.php';

session_start();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../login.php');
}

$error = '';
$dir_uploads = __DIR__ . '/../public/uploads/galeria';

if (!is_dir($dir_uploads)) {
    mkdir($dir_uploads, 0755, true);
}

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $categoria   = trim($_POST['categoria'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $imagen      = '';

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = 'gal_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], "$dir_uploads/$nombre_archivo")) {
                $imagen = 'public/uploads/galeria/' . $nombre_archivo;
            } else {
                $error = 'Error al subir la imagen.';
            }
        } else {
            $error = 'Selecciona una imagen para la galería.';
        }

        if (empty($error) && !empty($imagen)) {
            if (Corte::crear([
                'imagen'      => $imagen,
                'categoria'   => $categoria,
                'descripcion' => $descripcion,
            ])) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Foto añadida a la galería.'];
                redirigir('galeria.php');
            } else {
                $error = 'Error al guardar en MongoDB.';
            }
        } elseif (empty($error)) {
            $error = 'Selecciona una imagen.';
        }
    }

    if ($accion === 'editar') {
        $id          = $_POST['id'] ?? '';
        $categoria   = trim($_POST['categoria'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        $datos = [
            'categoria'   => $categoria,
            'descripcion' => $descripcion,
        ];

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = 'gal_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], "$dir_uploads/$nombre_archivo")) {
                $datos['imagen'] = 'public/uploads/galeria/' . $nombre_archivo;
            }
        }

        if (!empty($id)) {
            if (Corte::actualizar($id, $datos)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Foto actualizada.'];
            } else {
                $_SESSION['toast'] = ['type' => 'info', 'message' => 'No se detectaron cambios.'];
            }
            redirigir('galeria.php');
        }
    }

    if ($accion === 'toggle') {
        $id = $_POST['id'] ?? '';
        if (!empty($id)) {
            $nuevo = Corte::toggleActivo($id);
            if ($nuevo !== null) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => $nuevo ? 'Foto activada.' : 'Foto desactivada.'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error al cambiar estado.'];
            }
            redirigir('galeria.php');
        }
    }

    if ($accion === 'eliminar') {
        $id = $_POST['id'] ?? '';
        if (!empty($id) && Corte::eliminar($id)) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Foto eliminada definitivamente.'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'No se pudo eliminar.'];
        }
        redirigir('galeria.php');
    }
}

$fotos = Corte::obtenerTodos();
$pagina_activa = 'galeria';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galería — Panel Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none pagina-entrada">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Galería de Cortes</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Gestiona las fotos del carrusel de la landing pública (MongoDB)</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Formulario subir -->
        <section class="lg:col-span-5 rounded-xl border border-[var(--brd)] bg-white/5 p-5 glow-card">
            <h2 class="text-[0.95rem] font-medium text-[var(--tx)] mb-4 flex items-center gap-2" style="font-family: var(--pf);">
                <i class="bi bi-camera text-[var(--gold)]"></i> Nueva Foto
            </h2>
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="accion" value="crear">

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Imagen *</label>
                    <input type="file" name="imagen" accept="image/*" required
                           class="w-full text-[0.75rem] text-[var(--tx-m)] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-[var(--brd)] file:bg-[#141414] file:text-[0.7rem] file:text-[var(--tx)] hover:file:bg-white/5 file:cursor-pointer file:transition-all">
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Categoría</label>
                    <input type="text" name="categoria" placeholder="Ej: Corte degradado" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Descripción del corte..." class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] resize-none"></textarea>
                </div>

                <button type="submit" class="w-full bg-[var(--gold)] hover:opacity-90 text-[#0d0d0d] font-semibold py-2.5 rounded-lg text-[0.72rem] tracking-widest uppercase transition-all mt-2 cursor-pointer">
                    Subir Foto
                </button>
            </form>
        </section>

        <!-- Grid fotos -->
        <section class="lg:col-span-7">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)] mb-3 px-1">
                Galería (<?= count($fotos) ?>)
                <span class="ml-2 text-[0.55rem] font-normal text-[var(--tx-m)] tracking-normal">
                    (<?= count(array_filter($fotos, fn($f) => (bool)$f['activo'])) ?> activas)
                </span>
            </h2>

            <?php if (empty($fotos)): ?>
                <div class="flex flex-col items-center justify-center py-12 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-2 opacity-60">
                    <i class="bi bi-images text-2xl text-[var(--tx-d)]"></i>
                    <p class="text-[0.75rem] text-[var(--tx-m)]">No hay fotos en la galería.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 stagger-container">
                    <?php foreach ($fotos as $f): ?>
                        <?php $activo = (bool)$f['activo']; ?>
                        <div class="slot-card rounded-xl overflow-hidden border transition-all duration-150 <?= $activo ? 'border-[var(--brd)] bg-white/5' : 'border-red-900/20 bg-red-900/5 opacity-55' ?>">

                            <!-- Image -->
                            <div class="aspect-[4/3] overflow-hidden bg-[#141414] relative group">
                                <?php if (!empty($f['imagen'])): ?>
                                    <img src="../<?= h($f['imagen']) ?>" alt="<?= h($f['categoria'] ?? '') ?>" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-[var(--tx-d)]"><i class="bi bi-image text-2xl"></i></div>
                                <?php endif; ?>
                                <?php if (!$activo): ?>
                                    <div class="absolute top-2 right-2 text-[0.45rem] uppercase font-bold px-1.5 py-0.5 rounded-full bg-red-500/20 text-red-400 border border-red-500/30 backdrop-blur-sm">Inactiva</div>
                                <?php endif; ?>

                                <!-- Hover actions -->
                                <div class="absolute inset-0 bg-black/60 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <button onclick="abrirEditar('<?= (string)$f['_id'] ?>', '<?= h(addslashes($f['categoria'] ?? '')) ?>', '<?= h(addslashes($f['descripcion'] ?? '')) ?>')"
                                            class="w-9 h-9 rounded-lg bg-white/10 border border-white/20 flex items-center justify-center text-white hover:bg-white/20 transition-all cursor-pointer" title="Editar">
                                        <i class="bi bi-pencil-square text-[0.85rem]"></i>
                                    </button>
                                    <form action="" method="POST" class="m-0">
                                        <input type="hidden" name="accion" value="toggle">
                                        <input type="hidden" name="id" value="<?= (string)$f['_id'] ?>">
                                        <button type="submit"
                                                class="w-9 h-9 rounded-lg flex items-center justify-center border transition-all cursor-pointer <?= $activo ? 'bg-rose-500/20 border-rose-500/30 text-rose-400 hover:bg-rose-500/30' : 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/30' ?>"
                                                title="<?= $activo ? 'Desactivar' : 'Activar' ?>">
                                            <i class="bi <?= $activo ? 'bi-eye-slash-fill' : 'bi-eye-fill' ?> text-[0.85rem]"></i>
                                        </button>
                                    </form>
                                    <form action="" method="POST" onsubmit="return confirm('¿Eliminar esta foto definitivamente?')" class="m-0">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (string)$f['_id'] ?>">
                                        <button type="submit" class="w-9 h-9 rounded-lg bg-rose-500/20 border border-rose-500/30 text-rose-400 flex items-center justify-center hover:bg-rose-500/30 transition-all cursor-pointer" title="Eliminar">
                                            <i class="bi bi-trash3 text-[0.85rem]"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="p-3">
                                <?php if (!empty($f['categoria'])): ?>
                                    <div class="text-[0.75rem] font-semibold text-[var(--tx)] truncate"><?= h($f['categoria']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($f['descripcion'])): ?>
                                    <div class="text-[0.6rem] text-[var(--tx-d)] mt-0.5 truncate"><?= h($f['descripcion']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200" onclick="if(event.target===this)cerrarEditar()">
    <div class="bg-[var(--bg2)] border border-[var(--brd)] rounded-2xl p-6 w-full max-w-md shadow-2xl scale-95 transition-transform duration-200" id="editModalContent">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-[1rem] font-semibold text-[var(--tx)]" style="font-family:var(--pf);">Editar Foto</h3>
            <button onclick="cerrarEditar()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--tx-d)] hover:bg-white/10 hover:text-[var(--tx)] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="editId">

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Nueva imagen (opcional)</label>
                <input type="file" name="imagen" accept="image/*" class="w-full text-[0.75rem] text-[var(--tx-m)] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-[var(--brd)] file:bg-[#141414] file:text-[0.7rem] file:text-[var(--tx)] hover:file:bg-white/5 file:cursor-pointer file:transition-all">
            </div>

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Categoría</label>
                <input type="text" name="categoria" id="editCategoria" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
            </div>

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Descripción</label>
                <textarea name="descripcion" id="editDescripcion" rows="2" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="cerrarEditar()" class="flex-1 px-4 py-2.5 rounded-lg border border-[var(--brd)] text-[0.72rem] font-semibold tracking-wider text-[var(--tx-m)] hover:bg-white/5 transition-all cursor-pointer">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-lg bg-[var(--gold)] text-[#0d0d0d] text-[0.72rem] font-semibold tracking-wider uppercase hover:opacity-90 transition-all cursor-pointer">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

<script>
function abrirEditar(id, categoria, descripcion) {
    document.getElementById('editId').value = id;
    document.getElementById('editCategoria').value = categoria;
    document.getElementById('editDescripcion').value = descripcion;

    const modal = document.getElementById('editModal');
    const content = document.getElementById('editModalContent');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}

function cerrarEditar() {
    const modal = document.getElementById('editModal');
    const content = document.getElementById('editModalContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => { modal.classList.add('hidden'); }, 200);
}
</script>

</body>
</html>
