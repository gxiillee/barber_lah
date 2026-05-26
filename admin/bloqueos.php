<?php
// ============================================================
// admin/bloqueos.php — Panel de Gestión de Bloqueos
// ============================================================

declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── FASE 1: Dependencias ─────────────────────────────────────
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Bloqueo.php';
require_once __DIR__ . '/../clases/helpers.php';

// ── FASE 2: Sesión y control de acceso ──────────────────────
session_start();
if (!isset($_SESSION['usuario'])) {
    redirigir('../login.php');
}
if (!$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../cliente/index.php');
}

// Usamos la misma constante que en tu agenda principal
const ID_BARBERO = 1;

$mensaje = '';
$error = '';

// ── FASE 3: Procesar acciones POST ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $fecha      = $_POST['fecha'] ?? '';
        $tipo       = $_POST['tipo'] ?? 'completo';
        $motivo     = trim($_POST['motivo'] ?? '');

        // Si es día completo las horas se guardan vacías (null)
        $horaInicio = ($tipo === 'horas') ? ($_POST['hora_inicio'] ?: null) : null;
        $horaFin    = ($tipo === 'horas') ? ($_POST['hora_fin'] ?: null) : null;

        if (!empty($fecha)) {
            // Llamamos al método estático sin consultas SQL aquí
            if (Bloqueo::crear(ID_BARBERO, $fecha, $horaInicio, $horaFin, $motivo)) {
                $mensaje = "El bloqueo se ha guardado correctamente.";
            } else {
                $error = "Error al intentar registrar el bloqueo.";
            }
        } else {
            $error = "Por favor, introduce una fecha válida.";
        }
    }

    if ($accion === 'eliminar') {
        $idEliminar = (int)($_POST['id'] ?? 0);
        if ($idEliminar > 0 && Bloqueo::eliminar($idEliminar)) {
            $mensaje = "Horario liberado. Los clientes ya pueden reservar.";
        } else {
            $error = "No se pudo eliminar la restricción.";
        }
    }
}

// ── FASE 4: Obtener restricciones activas ────────────────────
$lista_bloqueos = Bloqueo::listarTodos();
$pagina_activa  = 'bloqueos'; // Por si tu nav ilumina la opción seleccionada
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloqueos — Panel Admin · Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">
            Bloqueos de Horario
        </h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">
            Restringe días completos o tramos de horas para que no estén públicos en la web
        </p>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-400 text-[0.75rem] font-medium flex items-center gap-2">
            <i class="bi bi-check-circle-fill"></i> <?= h($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.75rem] font-medium flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <section class="lg:col-span-5 rounded-xl border border-[var(--brd)] bg-white/5 p-5">
            <h2 class="text-[0.95rem] font-medium text-[var(--tx)] mb-4 flex items-center gap-2" style="font-family: var(--pf);">
                <i class="bi bi-shield-lock text-[var(--gold)]"></i> Añadir Restricción
            </h2>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="crear">

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Fecha</label>
                    <input type="date" name="fecha" required min="<?= date('Y-m-d') ?>" class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Duración del bloqueo</label>
                    <div class="grid grid-cols-2 gap-2 bg-[#141414] p-1 rounded-lg border border-[var(--brd)]">
                        <label class="flex items-center justify-center gap-1.5 py-1.5 text-[0.68rem] font-medium rounded-md cursor-pointer transition-all has-[:checked]:bg-[var(--gold-dim)] has-[:checked]:text-[var(--gold)] text-[var(--tx-m)]">
                            <input type="radio" name="tipo" value="completo" checked onclick="toggleSeccionHoras(false)" class="sr-only">
                            <i class="bi bi-calendar-minus"></i> Todo el día
                        </label>
                        <label class="flex items-center justify-center gap-1.5 py-1.5 text-[0.68rem] font-medium rounded-md cursor-pointer transition-all has-[:checked]:bg-[var(--gold-dim)] has-[:checked]:text-[var(--gold)] text-[var(--tx-m)]">
                            <input type="radio" name="tipo" value="horas" onclick="toggleSeccionHoras(true)" class="sr-only">
                            <i class="bi bi-clock"></i> Horas sueltas
                        </label>
                    </div>
                </div>

                <div id="wrapper_horas" class="hidden grid grid-cols-2 gap-3 p-3 bg-black/20 rounded-lg border border-[var(--brd)]/50">
                    <div>
                        <label class="block text-[0.62rem] text-[var(--tx-d)] font-medium mb-1">Desde las</label>
                        <input type="time" name="hora_inicio" class="w-full bg-[#1a1a1a] border border-[var(--brd)] rounded-md px-2 py-1 text-[0.75rem] text-[var(--tx)] focus:outline-hidden">
                    </div>
                    <div>
                        <label class="block text-[0.62rem] text-[var(--tx-d)] font-medium mb-1">Hasta las</label>
                        <input type="time" name="hora_fin" class="w-full bg-[#1a1a1a] border border-[var(--brd)] rounded-md px-2 py-1 text-[0.75rem] text-[var(--tx)] focus:outline-hidden">
                    </div>
                </div>

                <div>
                    <label class="block text-[0.68rem] uppercase tracking-wider text-[var(--tx-m)] font-semibold mb-1.5">Motivo (Opcional)</label>
                    <input type="text" name="motivo" placeholder="Ej: Cita médica, Asuntos propios..." class="w-full bg-[#141414] border border-[var(--brd)] rounded-lg px-3 py-2 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/60 focus:outline-hidden focus:border-[var(--gold-brd)]">
                </div>

                <button type="submit" class="w-full bg-[var(--gold)] hover:opacity-90 text-[#0d0d0d] font-semibold py-2.5 rounded-lg text-[0.72rem] tracking-widest uppercase transition-all mt-2 cursor-pointer">
                    Aplicar Restricción
                </button>
            </form>
        </section>

        <section class="lg:col-span-7 space-y-2">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)] mb-3 px-1">
                Bloqueos establecidos (<?= count($lista_bloqueos) ?>)
            </h2>

            <?php if (empty($lista_bloqueos)): ?>
                <div class="flex flex-col items-center justify-center py-12 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-2 opacity-60">
                    <i class="bi bi-unlock text-2xl text-[var(--tx-d)]"></i>
                    <p class="text-[0.75rem] text-[var(--tx-m)]">No hay tramos ni días bloqueados actualmente.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-2">
                    <?php foreach ($lista_bloqueos as $b): ?>
                        <?php $completo = empty($b['hora_inicio']); ?>

                        <div class="slot-card flex items-center justify-between gap-4 px-4 py-3.5 rounded-xl border <?= $completo ? 'border-purple-500/20 bg-purple-500/5' : 'border-[var(--brd)] bg-white/5' ?> min-h-[64px]">

                            <div class="text-[0.78rem] font-semibold text-[var(--tx)] min-w-[50px] shrink-0 flex flex-col justify-center">
                                <?php if ($completo): ?>
                                    <span class="text-purple-400 font-medium text-[0.58rem] tracking-wider uppercase bg-purple-500/10 px-1.5 py-0.5 rounded-sm border border-purple-500/20 text-center w-full">Día</span>
                                <?php else: ?>
                                    <span class="text-[0.75rem] font-semibold text-[var(--tx-m)]"><?= substr($b['hora_inicio'], 0, 5) ?></span>
                                    <span class="text-[0.6rem] text-[var(--tx-d)] font-normal">a <?= substr($b['hora_fin'], 0, 5) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="w-[2px] h-9 <?= $completo ? 'bg-purple-500/40' : 'bg-[var(--tx-d)]/40' ?> rounded-full shrink-0"></div>

                            <div class="flex-1 min-w-0">
                                <div class="text-[0.78rem] font-semibold text-[var(--tx)]">
                                    📅 <?= date('d/m/Y', strtotime($b['fecha'])) ?>
                                </div>
                                <div class="text-[0.65rem] text-[var(--tx-d)] mt-0.5 truncate italic">
                                    <?= !empty($b['motivo']) ? '"' . h($b['motivo']) . '"' : 'Sin motivo registrado' ?>
                                </div>
                            </div>

                            <form action="" method="POST" onsubmit="return confirm('¿Deseas levantar este bloqueo y volver a liberar el horario?');" class="shrink-0">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="w-8 h-8 rounded-lg border border-transparent text-[var(--tx-d)] flex items-center justify-center transition-all hover:bg-rose-500/10 hover:border-rose-500/20 hover:text-rose-400 cursor-pointer">
                                    <i class="bi bi-trash3 text-[0.85rem]"></i>
                                </button>
                            </form>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<script>
    function toggleSeccionHoras(mostrar) {
        const wrapper = document.getElementById('wrapper_horas');
        if (mostrar) {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
        }
    }
</script>

</body>
</html>