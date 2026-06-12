<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Horario.php';
require_once __DIR__ . '/../clases/helpers.php';

session_start();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

const ID_BARBERO = 1;

$error = '';

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $dia       = $_POST['dia'] ?? '';
        $horaInicio = $_POST['hora_inicio'] ?? '';
        $horaFin   = $_POST['hora_fin'] ?? '';

        if (in_array($dia, Horario::obtenerDiasValidos(), true) && $horaInicio < $horaFin) {
            Horario::agregar(ID_BARBERO, $dia, $horaInicio, $horaFin);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Tramo horario añadido.'];
            redirigir('horario.php');
        } else {
            $error = 'Datos inválidos. La hora de fin debe ser posterior a la de inicio.';
        }
    }

    if ($accion === 'editar') {
        $id        = (int)($_POST['id'] ?? 0);
        $horaInicio = $_POST['hora_inicio'] ?? '';
        $horaFin   = $_POST['hora_fin'] ?? '';

        if ($id > 0 && $horaInicio < $horaFin) {
            Horario::actualizar($id, $horaInicio, $horaFin);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Tramo actualizado.'];
            redirigir('horario.php');
        } else {
            $error = 'Datos inválidos.';
        }
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && Horario::eliminar($id)) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Tramo eliminado.'];
            redirigir('horario.php');
        } else {
            $error = 'No se pudo eliminar el tramo.';
        }
    }
}

$horarios = Horario::obtenerTodosPorBarbero(ID_BARBERO);
$pagina_activa = 'horario';

$dias_legibles = [
    'lunes'    => 'Lunes',
    'martes'   => 'Martes',
    'miercoles'=> 'Miércoles',
    'jueves'   => 'Jueves',
    'viernes'  => 'Viernes',
    'sabado'   => 'Sábado',
    'domingo'  => 'Domingo',
];

$total_tramos = array_sum(array_map('count', $horarios));
$dias_con_horario = count(array_filter($horarios, fn($d) => !empty($d)));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horario Semanal — Panel Admin · Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none pagina-entrada">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Horario Semanal</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Define los tramos horarios recurrentes de cada día de la semana</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] font-medium flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3 mb-6">
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
            <i class="bi bi-check-circle"></i> <?= $dias_con_horario ?> día<?= $dias_con_horario !== 1 ? 's' : '' ?> activo<?= $dias_con_horario !== 1 ? 's' : '' ?>
        </span>
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-white/5 border border-white/10 text-[var(--tx-m)]">
            <i class="bi bi-clock"></i> <?= $total_tramos ?> tramo<?= $total_tramos !== 1 ? 's' : '' ?>
        </span>
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[0.65rem] font-medium bg-white/5 border border-white/10 text-[var(--tx-m)]">
            <i class="bi bi-calendar-week"></i> 7 días
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 stagger-container">

        <?php foreach ($dias_legibles as $dia_key => $dia_label): 
            $tramos = $horarios[$dia_key] ?? [];
            $tiene_horario = !empty($tramos);
            $num_tramos = count($tramos);
        ?>

        <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card stagger-item">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-[0.9rem] font-semibold text-[var(--tx)]" style="font-family: var(--pf);"><?= h($dia_label) ?></h2>
                <?php if ($tiene_horario): ?>
                    <span class="text-[0.55rem] uppercase tracking-wider text-emerald-500 font-semibold bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">Activo</span>
                <?php else: ?>
                    <span class="text-[0.55rem] uppercase tracking-wider text-rose-500 font-semibold bg-rose-500/10 px-2 py-0.5 rounded-full border border-rose-500/20">Cerrado</span>
                <?php endif; ?>
            </div>

            <?php if ($tiene_horario): ?>
                <div class="space-y-1.5 mb-3">
                    <?php foreach ($tramos as $tramo): 
                        $tramo_id = (int)$tramo['id'];
                    ?>
                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-white/[0.04] border border-white/[0.06] group hover:border-[var(--gold-brd)]/30 transition-all">
                        <div class="flex items-center gap-2 text-[0.8rem] text-[var(--tx)]">
                            <i class="bi bi-clock text-[var(--gold)] text-[0.7rem]"></i>
                            <span class="font-medium"><?= h($tramo['hora_inicio']) ?></span>
                            <span class="text-[var(--tx-d)]">→</span>
                            <span class="font-medium"><?= h($tramo['hora_fin']) ?></span>
                        </div>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="abrirEditarTramo(<?= $tramo_id ?>, '<?= h($tramo['hora_inicio']) ?>', '<?= h($tramo['hora_fin']) ?>', '<?= h($dia_key) ?>')"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-[var(--tx-d)] hover:text-[var(--gold)] hover:bg-[var(--gold-dim)] transition-all cursor-pointer"
                                    title="Editar">
                                <i class="bi bi-pencil text-[0.7rem]"></i>
                            </button>
                            <form action="" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este tramo?')">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $tramo_id ?>">
                                <button type="submit"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-[var(--tx-d)] hover:text-rose-400 hover:bg-rose-500/10 transition-all cursor-pointer"
                                        title="Eliminar">
                                    <i class="bi bi-trash3 text-[0.7rem]"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-6 text-center">
                    <i class="bi bi-calendar-x text-[1.4rem] text-rose-500/40 mb-1.5"></i>
                    <span class="text-[0.7rem] text-[var(--tx-d)]">Sin horario — cerrado</span>
                </div>
            <?php endif; ?>

            <button onclick="abrirAgregarTramo('<?= h($dia_key) ?>')"
                    class="w-full flex items-center justify-center gap-1.5 py-2 rounded-lg text-[0.6rem] font-semibold uppercase tracking-wider border border-dashed border-[var(--brd)] text-[var(--tx-d)] hover:border-[var(--gold-brd)] hover:text-[var(--gold)] hover:bg-[var(--gold-dim)] transition-all cursor-pointer">
                <i class="bi bi-plus-lg text-[0.7rem]"></i>
                Añadir tramo
            </button>
        </div>

        <?php endforeach; ?>

    </div>

</main>

<!-- Modal agregar tramo -->
<div id="modalAgregar" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200" onclick="if(event.target===this)cerrarModal('modalAgregar')">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl p-6 w-full max-w-sm scale-95 transition-transform duration-200" id="modalAgregarContent">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8]">Añadir tramo</h3>
            <button onclick="cerrarModal('modalAgregar')" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="agregar">
            <input type="hidden" name="dia" id="agregarDia">

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Hora inicio</label>
                <input type="time" id="agregarInicio" name="hora_inicio" required
                       class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all">
            </div>

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Hora fin</label>
                <input type="time" id="agregarFin" name="hora_fin" required
                       class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all">
            </div>

            <div class="flex gap-3 pt-1">
                <button type="button" onclick="cerrarModal('modalAgregar')"
                        class="flex-1 px-4 py-2.5 rounded-lg border border-white/[0.08] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider text-[#888] hover:bg-white/5 transition-all cursor-pointer">
                    Cancelar
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-[#d4af37] text-[#0d0d0d] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider uppercase hover:opacity-90 transition-all cursor-pointer">
                    Añadir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal editar tramo -->
<div id="modalEditar" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200" onclick="if(event.target===this)cerrarModal('modalEditar')">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl p-6 w-full max-w-sm scale-95 transition-transform duration-200" id="modalEditarContent">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8]">Editar tramo</h3>
            <button onclick="cerrarModal('modalEditar')" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="editarId">

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1">Día</label>
                <p class="font-['Montserrat'] text-[0.85rem] text-[#f5f0e8] font-medium" id="editarDiaLabel"></p>
            </div>

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Hora inicio</label>
                <input type="time" id="editarInicio" name="hora_inicio" required
                       class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all">
            </div>

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Hora fin</label>
                <input type="time" id="editarFin" name="hora_fin" required
                       class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all">
            </div>

            <div class="flex gap-3 pt-1">
                <button type="button" onclick="cerrarModal('modalEditar')"
                        class="flex-1 px-4 py-2.5 rounded-lg border border-white/[0.08] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider text-[#888] hover:bg-white/5 transition-all cursor-pointer">
                    Cancelar
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-[#d4af37] text-[#0d0d0d] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider uppercase hover:opacity-90 transition-all cursor-pointer">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

<script>
function abrirAgregarTramo(dia) {
    document.getElementById('agregarDia').value = dia;
    document.getElementById('agregarInicio').value = '09:00';
    document.getElementById('agregarFin').value = '14:00';
    const modal = document.getElementById('modalAgregar');
    const content = document.getElementById('modalAgregarContent');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}

function abrirEditarTramo(id, inicio, fin, dia) {
    document.getElementById('editarId').value = id;
    document.getElementById('editarInicio').value = inicio;
    document.getElementById('editarFin').value = fin;
    document.getElementById('editarDiaLabel').textContent = dia.charAt(0).toUpperCase() + dia.slice(1);
    const modal = document.getElementById('modalEditar');
    const content = document.getElementById('modalEditarContent');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    const content = document.getElementById(id + 'Content');
    if (!modal || !content) return;
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}
</script>

</body>
</html>
