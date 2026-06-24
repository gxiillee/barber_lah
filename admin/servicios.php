<?php
declare(strict_types=1);
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Servicio.php';
require_once __DIR__ . '/../clases/Csrf.php';
require_once __DIR__ . '/../clases/helpers.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../login.php');
}

$mensaje = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!Csrf::validarToken('csrf_servicios', $csrf_token)) {
        $error = 'Sesión caducada. Recarga la página.';
    } else {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $duracionMin = (int)($_POST['duracion'] ?? 0);
        $precio      = (float)($_POST['precio'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descripcion = $descripcion === '' ? null : $descripcion;

        if (!empty($nombre) && $duracionMin > 0 && $precio > 0) {
            if (Servicio::crear($nombre, $duracionMin, $precio, $descripcion)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Servicio añadido correctamente al catálogo.'];
                redirigir('servicios.php');
            } else {
                $error = "Error al guardar el servicio en la base de datos.";
            }
        } else {
            $error = "Por favor, rellena nombre, duración y precio obligatoriamente.";
        }
    }

    if ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $nuevo = Servicio::toggleActivo($id);
        if ($nuevo !== null) {
            $estado = $nuevo ? 'activado' : 'desactivado';
            $_SESSION['toast'] = ['type' => 'success', 'message' => "Servicio $estado correctamente."];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'No se pudo cambiar el estado.'];
        }
        redirigir('servicios.php');
    }

    if ($accion === 'actualizar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $duracionMin = (int)($_POST['duracion'] ?? 0);
        $precio      = (float)($_POST['precio'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descripcion = $descripcion === '' ? null : $descripcion;

        if ($id > 0 && !empty($nombre) && $duracionMin > 0 && $precio > 0) {
            if (Servicio::actualizar($id, $nombre, $duracionMin, $precio, $descripcion)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Servicio actualizado correctamente.'];
                redirigir('servicios.php');
            } else {
                $error = "Error al actualizar el servicio.";
            }
        } else {
            $error = "Por favor, rellena todos los campos obligatorios.";
        }
    }
    } // else (CSRF válido)
}

$csrfToken = Csrf::generarToken('csrf_servicios');
$servicios = Servicio::obtenerTodosIncluyendoInactivos();
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
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Servicios</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Crea, edita y gestiona los servicios de la barbería</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] flex items-center gap-2"><i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <section class="lg:col-span-5 rounded-xl border border-[var(--brd)] bg-white/5 p-5 glow-card">
            <h2 class="text-[0.95rem] font-medium text-[var(--tx)] mb-4 flex items-center gap-2" style="font-family: var(--pf);">
                <i class="bi bi-scissors text-[var(--gold)]"></i> Nuevo Servicio
                <button type="button" onclick="toggleForm(this)" class="lg:hidden ml-auto flex items-center gap-1.5 text-[0.6rem] text-[var(--gold)] cursor-pointer transition-all hover:opacity-80">
                    <i class="bi bi-plus-circle text-[0.7rem]"></i>
                    <span>Mostrar</span>
                </button>
            </h2>

            <div class="mobile-collapse">
            <!-- Live preview card -->
            <div class="mb-4 rounded-lg border border-[var(--brd)] bg-[#0d0d0d] p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[0.45rem] uppercase tracking-[0.2em] text-[var(--tx-d)] font-semibold">
                        <i class="bi bi-eye"></i> Vista previa
                    </span>
                    <span class="text-[0.4rem] text-[var(--tx-d)]/40">Cómo se verá en la web</span>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[0.75rem] font-semibold text-white" id="pv-nombre-serv">Nombre del servicio</div>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="flex items-center gap-1 text-[0.55rem] text-white/40">
                                <i class="bi bi-clock text-[var(--gold)]"></i> <span id="pv-duracion-serv">30</span> min
                            </span>
                            <span class="flex items-center gap-1 text-[0.55rem] text-white/40">
                                <i class="bi bi-currency-euro text-[var(--gold)]"></i> <span id="pv-precio-serv">15</span>,00
                            </span>
                        </div>
                    </div>
                    <span class="text-[1.2rem] font-bold text-[var(--gold)]" style="font-family:var(--pf);">
                        <span id="pv-precio-grande">15</span>€
                    </span>
                </div>
                <div class="text-[0.5rem] text-white/25 mt-2 italic truncate" id="pv-desc-serv">Descripción opcional...</div>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">
                        <i class="bi bi-tag text-[var(--gold)] mr-1"></i> Nombre *
                    </label>
                    <input type="text" name="nombre" required placeholder="Ej: Corte Degradado"
                           oninput="previewServicio()"
                           class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">
                            <i class="bi bi-clock text-[var(--gold)] mr-1"></i> Duración *
                        </label>
                        <div class="relative">
                            <input type="number" name="duracion" required min="5" step="1" placeholder="30"
                                   oninput="previewServicio()"
                                   class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[0.6rem] text-[var(--tx-d)]">min</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">
                            <i class="bi bi-currency-euro text-[var(--gold)] mr-1"></i> Precio *
                        </label>
                        <div class="relative">
                            <input type="number" name="precio" required min="1" step="0.01" placeholder="15.00"
                                   oninput="previewServicio()"
                                   class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[0.6rem] text-[var(--tx-d)]">€</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">
                        <i class="bi bi-chat-text text-[var(--gold)] mr-1"></i> Descripción
                    </label>
                    <textarea name="descripcion" rows="2" placeholder="Detalles extra del servicio..."
                              oninput="previewServicio()"
                              class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] resize-none"></textarea>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-[#d4af37] to-[#e8c84a] hover:opacity-90 text-[#0d0d0d] font-bold py-2.5 rounded-lg text-[0.72rem] tracking-widest uppercase transition-all mt-2 cursor-pointer shadow-lg shadow-[#d4af37]/10">
                    <i class="bi bi-plus-circle text-[0.8rem] mr-1.5"></i> Añadir al Catálogo
                </button>
            </form>
            </div><!-- /mobile-collapse -->
        </section>

        <section class="lg:col-span-7 space-y-2">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)] mb-3 px-1">
                Todos los Servicios (<?= count($servicios) ?>)
                <span class="ml-2 text-[0.55rem] font-normal text-[var(--tx-m)] tracking-normal">(<?= count(array_filter($servicios, fn($s) => $s->isActivo())) ?> activos)</span>
            </h2>

            <?php if (empty($servicios)): ?>
                <div class="flex flex-col items-center justify-center py-12 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-2 opacity-60">
                    <i class="bi bi-scissors text-2xl text-[var(--tx-d)]"></i>
                    <p class="text-[0.75rem] text-[var(--tx-m)]">No hay servicios registrados.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-2 stagger-container">
                    <?php foreach ($servicios as $s): ?>
                        <div class="slot-card flex items-center justify-between gap-4 px-4 py-3.5 rounded-xl border transition-all duration-150 min-h-[64px] <?= $s->isActivo() ? 'border-[var(--brd)] bg-white/5 hover:bg-white/[0.07]' : 'border-red-900/20 bg-red-900/5 opacity-60' ?>">

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[0.85rem] font-semibold text-[var(--tx)] truncate"><?= h($s->getNombre()) ?></span>
                                    <?php if (!$s->isActivo()): ?>
                                        <span class="text-[0.5rem] uppercase tracking-wider font-bold px-1.5 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20">Inactivo</span>
                                    <?php endif; ?>
                                </div>
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

                            <div class="flex items-center gap-1 shrink-0">
                                <!-- Edit button -->
                                <button onclick="abrirEditar(<?= $s->getIdServicio() ?>, '<?= h(addslashes($s->getNombre())) ?>', <?= $s->getDuracion() ?>, <?= $s->getPrecio() ?>, '<?= h(addslashes($s->getDescripcion() ?? '')) ?>')"
                                        class="w-8 h-8 rounded-lg border border-transparent text-[var(--tx-d)] flex items-center justify-center hover:bg-white/10 hover:text-[var(--tx)] transition-all cursor-pointer"
                                        title="Editar servicio">
                                    <i class="bi bi-pencil-square text-[0.85rem]"></i>
                                </button>

                                <!-- Toggle active/inactive -->
                                <form action="" method="POST" class="shrink-0">
                                    <input type="hidden" name="accion" value="toggle">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= $s->getIdServicio() ?>">
                                    <button type="submit"
                                            class="w-8 h-8 rounded-lg border border-transparent flex items-center justify-center transition-all cursor-pointer <?= $s->isActivo() ? 'text-[var(--tx-d)] hover:bg-rose-500/10 hover:text-rose-400' : 'text-[var(--tx-d)] hover:bg-emerald-500/10 hover:text-emerald-400' ?>"
                                            title="<?= $s->isActivo() ? 'Desactivar' : 'Activar' ?>">
                                        <i class="bi <?= $s->isActivo() ? 'bi-eye-slash-fill' : 'bi-eye-fill' ?> text-[0.85rem]"></i>
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
            <h3 class="text-[1rem] font-semibold text-[var(--tx)]" style="font-family:var(--pf);">Editar Servicio</h3>
            <button onclick="cerrarEditar()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--tx-d)] hover:bg-white/10 hover:text-[var(--tx)] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="id" id="editId">

            <div>
                <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Nombre *</label>
                <input type="text" name="nombre" id="editNombre" required class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Duración *</label>
                    <input type="number" name="duracion" id="editDuracion" required min="5" step="1" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>
                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Precio (€) *</label>
                    <input type="number" name="precio" id="editPrecio" required min="1" step="0.01" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>
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
function abrirEditar(id, nombre, duracion, precio, descripcion) {
    document.getElementById('editId').value = id;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editDuracion').value = duracion;
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
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function toggleForm(btn) {
    const wrapper = btn.closest('section').querySelector('.mobile-collapse');
    if (!wrapper) return;
    wrapper.classList.toggle('expanded');
    const open = wrapper.classList.contains('expanded');
    btn.querySelector('span').textContent = open ? 'Ocultar' : 'Mostrar';
    btn.querySelector('i').className = open ? 'bi bi-dash-circle' : 'bi bi-plus-circle';
}

function previewServicio() {
    const nombre = document.querySelector('[name="nombre"]')?.value || 'Nombre del servicio';
    const duracion = document.querySelector('[name="duracion"]')?.value || '30';
    const precio = document.querySelector('[name="precio"]')?.value || '15';
    const desc = document.querySelector('[name="descripcion"]')?.value || 'Descripción opcional...';

    document.getElementById('pv-nombre-serv').textContent = nombre;
    document.getElementById('pv-duracion-serv').textContent = duracion;
    document.getElementById('pv-precio-serv').textContent = precio.replace('.', ',');
    document.getElementById('pv-precio-grande').textContent = Math.floor(parseFloat(precio) || 0);
    document.getElementById('pv-desc-serv').textContent = desc || 'Descripción opcional...';
}
</script>

</body>
</html>
