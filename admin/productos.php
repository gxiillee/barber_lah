<?php
/**
 * admin/productos.php — Gestión de productos (MongoDB)
 *
 * CRUD completo sobre la colección barberlah.productos.
 * Los productos activos se muestran en la landing pública (index.php raíz).
 */
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../clases/BdMongo.php';
require_once __DIR__ . '/../clases/Producto.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/helpers.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../login.php');
}

$error = '';
$dir_uploads = __DIR__ . '/../public/uploads/productos';

// Asegurar que existe el directorio de subidas
if (!is_dir($dir_uploads)) {
    mkdir($dir_uploads, 0755, true);
}

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio      = (float)($_POST['precio'] ?? 0);
        $imagen      = '';

        // Subir imagen si viene
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], "$dir_uploads/$nombre_archivo")) {
                $imagen = 'public/uploads/productos/' . $nombre_archivo;
            } else {
                $error = 'Error al subir la imagen.';
            }
        } else {
            $error = 'Selecciona una imagen para el producto.';
        }

        if (empty($error) && !empty($nombre) && $precio > 0) {
            if (Producto::crear([
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
                'precio'      => $precio,
                'imagen'      => $imagen,
            ])) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Producto añadido correctamente.'];
                redirigir('productos.php');
            } else {
                $error = 'Error al guardar el producto en MongoDB.';
            }
        } elseif (empty($error)) {
            $error = 'Rellena nombre y precio obligatoriamente.';
        }
    }

    if ($accion === 'editar') {
        $id          = $_POST['id'] ?? '';
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio      = (float)($_POST['precio'] ?? 0);

        $datos = [
            'nombre'      => $nombre,
            'descripcion' => $descripcion,
            'precio'      => $precio,
        ];

        // Subir nueva imagen si viene
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], "$dir_uploads/$nombre_archivo")) {
                $datos['imagen'] = 'public/uploads/productos/' . $nombre_archivo;
            }
        }

        if (!empty($id) && !empty($nombre) && $precio > 0) {
            if (Producto::actualizar($id, $datos)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Producto actualizado.'];
            } else {
                $_SESSION['toast'] = ['type' => 'info', 'message' => 'No se detectaron cambios.'];
            }
            redirigir('productos.php');
        } else {
            $error = 'Rellena nombre y precio obligatoriamente.';
        }
    }

    if ($accion === 'toggle') {
        $id = $_POST['id'] ?? '';
        if (!empty($id)) {
            $nuevo = Producto::toggleActivo($id);
            if ($nuevo !== null) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => $nuevo ? 'Producto activado.' : 'Producto desactivado.'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error al cambiar estado.'];
            }
            redirigir('productos.php');
        }
    }

    if ($accion === 'eliminar') {
        $id = $_POST['id'] ?? '';
        if (!empty($id) && Producto::eliminar($id)) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Producto eliminado definitivamente.'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'No se pudo eliminar el producto.'];
        }
        redirigir('productos.php');
    }
}

$productos = Producto::obtenerTodos();
$pagina_activa = 'productos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos — Panel Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
    <style>
        .mobile-collapse { max-height: 0; overflow: hidden; transition: max-height 0.5s cubic-bezier(0.4,0,0.2,1), opacity 0.35s ease; opacity: 0.4; }
        .mobile-collapse.expanded { max-height: 2000px; opacity: 1; }
        @media (min-width: 1024px) { .mobile-collapse { max-height: none !important; overflow: visible !important; opacity: 1 !important; } }
    </style>
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none pagina-entrada">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Productos</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Gestiona los productos que se venden en la barbería (MongoDB)</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Formulario crear -->
        <section class="lg:col-span-5 rounded-xl border border-[var(--brd)] bg-white/5 p-5 glow-card">
            <h2 class="text-[0.95rem] font-medium text-[var(--tx)] mb-4 flex items-center gap-2" style="font-family: var(--pf);">
                <i class="bi bi-box-seam text-[var(--gold)]"></i> Nuevo Producto
                <button type="button" onclick="toggleForm(this)" class="lg:hidden ml-auto flex items-center gap-1.5 text-[0.6rem] text-[var(--gold)] cursor-pointer transition-all hover:opacity-80">
                    <i class="bi bi-plus-circle text-[0.7rem]"></i>
                    <span>Mostrar</span>
                </button>
            </h2>
            <div class="mobile-collapse">
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="accion" value="crear">

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Nombre *</label>
                    <input type="text" name="nombre" required placeholder="Ej: Pomada modeladora" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Precio (€) *</label>
                    <input type="number" name="precio" required min="0.01" step="0.01" placeholder="12.00" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Descripción del producto..." class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Imagen *</label>
                    <div class="flex gap-3 items-start">
                        <div class="flex-1">
                            <input type="file" name="imagen" id="prodImagen" accept="image/*" required
                                   onchange="previewImagen(this, 'prodPreview')"
                                   class="w-full text-[0.75rem] text-[var(--tx-m)] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-[var(--brd)] file:bg-[#141414] file:text-[0.7rem] file:text-[var(--tx)] hover:file:bg-white/5 file:cursor-pointer file:transition-all">
                        </div>
                        <div id="prodPreview" class="hidden shrink-0 w-20 h-20 rounded-xl border-2 border-dashed border-[var(--brd)] overflow-hidden bg-[#141414] transition-all duration-300">
                            <img class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-[var(--gold)] hover:opacity-90 text-[#0d0d0d] font-semibold py-2.5 rounded-lg text-[0.72rem] tracking-widest uppercase transition-all mt-2 cursor-pointer">
                    Añadir Producto
                </button>
            </form>
            </div><!-- /mobile-collapse -->
        </section>

        <!-- Listado productos -->
        <section class="lg:col-span-7 space-y-2">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)] mb-3 px-1">
                Todos los productos (<?= count($productos) ?>)
                <span class="ml-2 text-[0.55rem] font-normal text-[var(--tx-m)] tracking-normal">
                    (<?= count(array_filter($productos, fn($p) => (bool)$p['activo'])) ?> activos)
                </span>
            </h2>

            <?php if (empty($productos)): ?>
                <div class="flex flex-col items-center justify-center py-12 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-2 opacity-60">
                    <i class="bi bi-box-seam text-2xl text-[var(--tx-d)]"></i>
                    <p class="text-[0.75rem] text-[var(--tx-m)]">No hay productos registrados.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-2 stagger-container">
                    <?php foreach ($productos as $p): ?>
                        <?php $activo = (bool)$p['activo']; ?>
                        <div class="slot-card flex items-center gap-4 px-4 py-3 rounded-xl border transition-all duration-150 min-h-[64px] <?= $activo ? 'border-[var(--brd)] bg-white/5' : 'border-red-900/20 bg-red-900/5 opacity-60' ?>">

                            <!-- Thumbnail -->
                            <div class="w-12 h-12 rounded-lg overflow-hidden shrink-0 border border-[var(--brd)] bg-[#141414]">
                                <?php if (!empty($p['imagen'])): ?>
                                    <img src="../<?= h($p['imagen']) ?>" alt="<?= h($p['nombre'] ?? '') ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-[var(--tx-d)]"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[0.82rem] font-semibold text-[var(--tx)] truncate"><?= h($p['nombre'] ?? '') ?></span>
                                    <?php if (!$activo): ?>
                                        <span class="text-[0.5rem] uppercase font-bold px-1.5 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20">Inactivo</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[0.7rem] font-semibold text-[var(--gold)]"><?= number_format((float)($p['precio'] ?? 0), 2, ',', '.') ?>€</span>
                                    <?php if (!empty($p['descripcion'])): ?>
                                        <span class="text-[0.6rem] text-[var(--tx-d)] truncate"><?= h($p['descripcion']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 shrink-0">
                                <button onclick="abrirEditar('<?= (string)$p['_id'] ?>', '<?= h(addslashes($p['nombre'] ?? '')) ?>', '<?= (float)($p['precio'] ?? 0) ?>', '<?= h(addslashes($p['descripcion'] ?? '')) ?>')"
                                        class="w-8 h-8 rounded-lg border border-transparent text-[var(--tx-d)] flex items-center justify-center hover:bg-white/10 hover:text-[var(--tx)] transition-all cursor-pointer" title="Editar">
                                    <i class="bi bi-pencil-square text-[0.85rem]"></i>
                                </button>

                                <form action="" method="POST" class="shrink-0">
                                    <input type="hidden" name="accion" value="toggle">
                                    <input type="hidden" name="id" value="<?= (string)$p['_id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg border border-transparent flex items-center justify-center transition-all cursor-pointer <?= $activo ? 'text-[var(--tx-d)] hover:bg-rose-500/10 hover:text-rose-400' : 'text-[var(--tx-d)] hover:bg-emerald-500/10 hover:text-emerald-400' ?>" title="<?= $activo ? 'Desactivar' : 'Activar' ?>">
                                        <i class="bi <?= $activo ? 'bi-eye-slash-fill' : 'bi-eye-fill' ?> text-[0.85rem]"></i>
                                    </button>
                                </form>

                                <form action="" method="POST" onsubmit="return confirm('¿Eliminar producto definitivamente?')" class="shrink-0">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (string)$p['_id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg border border-transparent text-[var(--tx-d)] flex items-center justify-center hover:bg-rose-500/10 hover:text-rose-400 transition-all cursor-pointer" title="Eliminar">
                                        <i class="bi bi-trash3 text-[0.85rem]"></i>
                                    </button>
                                </form>
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
            <h3 class="text-[1rem] font-semibold text-[var(--tx)]" style="font-family:var(--pf);">Editar Producto</h3>
            <button onclick="cerrarEditar()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--tx-d)] hover:bg-white/10 hover:text-[var(--tx)] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="editId">

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Nombre *</label>
                <input type="text" name="nombre" id="editNombre" required class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
            </div>

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Precio (€) *</label>
                <input type="number" name="precio" id="editPrecio" required min="0.01" step="0.01" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
            </div>

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Descripción</label>
                <textarea name="descripcion" id="editDescripcion" rows="2" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] resize-none"></textarea>
            </div>

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Imagen (dejar vacío para mantener actual)</label>
                <div class="flex gap-3 items-start">
                    <div class="flex-1">
                        <input type="file" name="imagen" id="editProdImagen" accept="image/*"
                               onchange="previewImagen(this, 'editProdPreview')"
                               class="w-full text-[0.75rem] text-[var(--tx-m)] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-[var(--brd)] file:bg-[#141414] file:text-[0.7rem] file:text-[var(--tx)] hover:file:bg-white/5 file:cursor-pointer file:transition-all">
                    </div>
                    <div id="editProdPreview" class="hidden shrink-0 w-16 h-16 rounded-lg border-2 border-dashed border-[var(--brd)] overflow-hidden bg-[#141414]">
                        <img class="w-full h-full object-cover">
                    </div>
                </div>
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
function abrirEditar(id, nombre, precio, descripcion) {
    document.getElementById('editId').value = id;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editPrecio').value = precio;
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

function toggleForm(btn) {
    const wrapper = btn.closest('section').querySelector('.mobile-collapse');
    if (!wrapper) return;
    wrapper.classList.toggle('expanded');
    const open = wrapper.classList.contains('expanded');
    btn.querySelector('span').textContent = open ? 'Ocultar' : 'Mostrar';
    btn.querySelector('i').className = open ? 'bi bi-dash-circle' : 'bi bi-plus-circle';
}

function previewImagen(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    if (!file || !preview) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        preview.classList.remove('hidden');
        preview.querySelector('img').src = e.target.result;
    };
    reader.readAsDataURL(file);
}
</script>

</body>
</html>
