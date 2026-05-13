<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/Servicio.php';
require_once __DIR__ . '/../clases/Barbero.php';
require_once __DIR__ . '/../clases/Bloqueo.php';
require_once __DIR__ . '/../clases/Horario.php';
require_once __DIR__ . '/../clases/Reserva.php';

session_start();

// ─── Barbero activo ───────────────────────────────────────────────────────────
const ID_BARBERO_ACTIVO = 1;

// ─── Horario base dinámico (30 min) ───────────────────────────────────────────
$horarioSemanal = Horario::obtenerPorBarbero(ID_BARBERO_ACTIVO);

// ─── Navegación del calendario ────────────────────────────────────────────────
$mesActual  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('n');
$anyoActual = isset($_GET['anyo']) ? (int)$_GET['anyo'] : (int)date('Y');

$primerDiaMes = new DateTime(sprintf('%04d-%02d-01', $anyoActual, $mesActual));
$mesMinimo    = new DateTime(date('Y-m-01'));
$mesMaximo    = (clone $mesMinimo)->modify('+3 months');

if ($primerDiaMes < $mesMinimo) { $primerDiaMes = clone $mesMinimo; }
if ($primerDiaMes > $mesMaximo) { $primerDiaMes = clone $mesMaximo; }

$mesActual  = (int)$primerDiaMes->format('n');
$anyoActual = (int)$primerDiaMes->format('Y');

// ─── Calcular disponibilidad del mes ─────────────────────────────────────────
$hoyStr      = date('Y-m-d');
$diasEnMes   = (int)date('t', mktime(0, 0, 0, $mesActual, 1, $anyoActual));
$ocupadasMes = Reserva::obtenerOcupadasPorMes(ID_BARBERO_ACTIVO, $mesActual, $anyoActual);

$diasCalendario = [];
for ($d = 1; $d <= $diasEnMes; $d++) {
    $fechaStr    = sprintf('%04d-%02d-%02d', $anyoActual, $mesActual, $d);
    $diaSemana   = (int)(new DateTime($fechaStr))->format('w');
    $horasBase   = $horarioSemanal[$diaSemana] ?? [];
    $ocupadas    = $ocupadasMes[$fechaStr] ?? [];
    $bloqueos = Bloqueo::obtenerPorFecha(ID_BARBERO_ACTIVO, $fechaStr);
    $disponibles = array_values(array_diff($horasBase, $ocupadas));
    $esPasado    = $fechaStr < $hoyStr;

    $diasCalendario[] = [
            'num'         => $d,
            'fecha'       => $fechaStr,
            'diaSemana'   => $diaSemana,
            'disponibles' => $disponibles,
            'esPasado'    => $esPasado,
            'tieneHueco'  => !$esPasado && count($disponibles) > 0,
    ];
}

// ─── Servicios y barbero ──────────────────────────────────────────────────────
$servicios = Servicio::obtenerTodos();
$barbero   = Barbero::obtenerPorId(ID_BARBERO_ACTIVO);

// ─── Procesar "Continuar" (Fase 2) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'continuar') {
    $idServicio = (int)($_POST['id_servicio'] ?? 0);
    $fecha      = $_POST['fecha'] ?? '';
    $hora       = $_POST['hora']  ?? '';

    $servicioSel = null;
    foreach ($servicios as $s) {
        if ($s->getIdServicio() === $idServicio) {
            $servicioSel = $s;
            break;
        }
    }

    if ($servicioSel !== null && $fecha !== '' && $hora !== '') {
        $_SESSION['reserva_pendiente'] = [
                'id_barbero'      => ID_BARBERO_ACTIVO,
                'id_servicio'     => $idServicio,
                'nombre_servicio' => $servicioSel->getNombre(),
                'fecha'           => $fecha,
                'hora'            => $hora,
                'precio'          => $servicioSel->getPrecio(),
                'duracion'        => $servicioSel->getDuracion(),
        ];

        if (isset($_SESSION['usuario'])) {
            header('Location: confirmar_reserva.php');
        } else {
            header('Location: login.php?source=reserva');
        }
        exit;
    }
}
$disponibles = [];

foreach ($horasBase as $hora) {

    $ocupada = in_array($hora, $ocupadas);

    $bloqueada = false;

    foreach ($bloqueos as $b) {

        $inicio = substr($b['hora_inicio'], 0, 5);
        $fin    = substr($b['hora_fin'], 0, 5);

        if ($hora >= $inicio && $hora < $fin) {
            $bloqueada = true;
            break;
        }
    }

    if (!$ocupada && !$bloqueada) {
        $disponibles[] = $hora;
    }
}
$nombresMeses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$nombresDias  = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

$prevFecha = (clone $primerDiaMes)->modify('-1 month');
$sigFecha  = (clone $primerDiaMes)->modify('+1 month');
$puedeRetroceder = $prevFecha >= $mesMinimo;
$puedeAvanzar    = $sigFecha  <= $mesMaximo;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar cita · Barbershop La H</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>

<body class="min-h-screen bg-[var(--obsidian)] font-[var(--font-montserrat)] text-[#f5f0e8] overflow-x-hidden">

<div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_70%_50%_at_15%_40%,rgba(212,175,55,0.04)_0%,transparent_70%),radial-gradient(ellipse_50%_40%_at_85%_60%,rgba(212,175,55,0.025)_0%,transparent_70%)]"></div>

<a href="index.php" class="fixed left-6 top-6 z-40 text-[10px] font-bold uppercase tracking-[0.16em] text-white/40 no-underline transition hover:-translate-x-0.5 hover:text-[var(--gold)] max-sm:left-4 max-sm:top-4">
    ← Inicio
</a>

<main class="reserva-panel relative z-10 mx-auto max-w-[1020px] px-6 pb-16 pt-14 max-sm:px-4 max-sm:pt-16">

    <div class="mb-9 text-center">
        <p class="mb-2 font-[var(--font-playfair)] text-[10px] uppercase tracking-[0.28em] text-[var(--gold)]">✦ Barbershop La H ✦</p>
        <h1 class="font-[var(--font-playfair)] text-[32px] font-bold leading-none text-[#f5f0e8] max-sm:text-[26px]">Reserva tu cita</h1>
        <p class="mt-2 text-[11px] tracking-[0.06em] text-white/35">Selecciona servicio, día y hora — sin necesidad de cuenta</p>
    </div>

    <div class="flex items-start gap-6 max-sm:flex-col">
        <div class="min-w-0 flex-1 space-y-6">

            <div class="reserva-step" id="pasoServicio">
                <div class="mb-4 flex items-center gap-3">
                    <span class="paso-num flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-[var(--gold)]/40 font-[var(--font-playfair)] text-[11px] font-bold text-[var(--gold)]">1</span>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/50">Servicio</p>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" id="serviciosGrid">
                    <?php foreach ($servicios as $s): ?>
                        <button type="button"
                                class="servicio-chip group flex items-center gap-4 rounded-xl border border-[#252525] bg-[#0f0f0f] px-5 py-4 text-left transition"
                                data-id="<?= $s->getIdServicio() ?>"
                                data-nombre="<?= htmlspecialchars($s->getNombre()) ?>"
                                data-precio="<?= $s->getPrecio() ?>"
                                data-precio-fmt="<?= number_format($s->getPrecio(), 2, ',', '.') ?>"
                                data-duracion="<?= $s->getDuracion() ?>">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold text-[#ddd5c4] group-hover:text-[#f5f0e8]"><?= htmlspecialchars($s->getNombre()) ?></p>
                                <p class="mt-0.5 text-[11px] text-white/35"><?= $s->getDuracion() ?> min</p>
                            </div>
                            <span class="shrink-0 font-[var(--font-playfair)] text-[17px] font-bold text-[var(--gold)]"><?= number_format($s->getPrecio(), 2, ',', '.') ?> €</span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="reserva-step reserva-step-oculto" id="pasoFecha">
                <div class="mb-4 flex items-center gap-3">
                    <span class="paso-num flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-[#2e2e2e] font-[var(--font-playfair)] text-[11px] font-bold text-white/25">2</span>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/50">Fecha</p>
                </div>

                <div class="mb-5 flex items-center justify-between">
                    <h2 class="font-[var(--font-playfair)] text-[16px] capitalize tracking-wide text-[#e8e0d0]"><?= $nombresMeses[$mesActual] ?> <?= $anyoActual ?></h2>
                    <div class="flex gap-1.5">
                        <a href="?mes=<?= $prevFecha->format('n') ?>&anyo=<?= $prevFecha->format('Y') ?>" class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#282828] <?= $puedeRetroceder ? 'text-white/40 hover:border-[var(--gold)]/40' : 'opacity-20 cursor-not-allowed' ?>"><i class="bi bi-chevron-left"></i></a>
                        <a href="?mes=<?= $sigFecha->format('n') ?>&anyo=<?= $sigFecha->format('Y') ?>" class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#282828] <?= $puedeAvanzar ? 'text-white/40 hover:border-[var(--gold)]/40' : 'opacity-20 cursor-not-allowed' ?>"><i class="bi bi-chevron-right"></i></a>
                    </div>
                </div>

                <div class="dias-scroll flex gap-2 overflow-x-auto pb-2" id="diasScroll">
                    <?php foreach ($diasCalendario as $dia): ?>
                        <button type="button"
                                class="dia-btn flex min-w-[54px] flex-col items-center rounded-xl border py-3 transition <?= ($dia['esPasado'] || !$dia['tieneHueco']) ? 'dia-disabled border-[#181818] bg-[#090909] opacity-40 cursor-not-allowed' : 'border-[#252525] bg-[#0f0f0f] hover:border-[var(--gold)]/45' ?>"
                                data-fecha="<?= $dia['fecha'] ?>"
                                data-horas='<?= json_encode($dia['disponibles']) ?>'
                                data-num="<?= $dia['num'] ?>"
                                data-diasemana="<?= $dia['diaSemana'] ?>"
                                <?= ($dia['esPasado'] || !$dia['tieneHueco']) ? 'disabled' : '' ?>>
                            <span class="mb-1 text-[9px] uppercase tracking-[0.1em] text-white/40"><?= $nombresDias[$dia['diaSemana']] ?></span>
                            <span class="text-[15px] font-semibold text-[#ddd5c4]"><?= $dia['num'] ?></span>
                            <span class="mt-1.5 text-[9px] text-[var(--gold)]/50"><?= $dia['esPasado'] ? '—' : (count($dia['disponibles']) . ' h') ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="reserva-step reserva-step-oculto" id="pasoHora">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="paso-num flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-[#2e2e2e] font-[var(--font-playfair)] text-[11px] font-bold text-white/25">3</span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/50">Hora disponible</p>
                    </div>
                    <p class="text-[11px] capitalize text-white/35" id="labelDiaSeleccionado"></p>
                </div>
                <div class="grid grid-cols-4 gap-2 max-sm:grid-cols-3" id="slotsGrid"></div>
            </div>
        </div>

        <aside class="w-[300px] shrink-0 max-sm:w-full">
            <div class="sticky top-6 rounded-[16px] border border-[#1e1e1e] bg-[#0d0d0d] p-6 shadow-xl">
                <p class="mb-4 text-[10px] font-bold uppercase tracking-[0.2em] text-white/40">Tu pedido</p>

                <div id="pedidoVacio" class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-[#1f1f1f] py-7 text-center">
                    <i class="bi bi-scissors text-white/12 text-2xl"></i>
                    <p class="text-[11px] text-white/22">Selecciona un servicio</p>
                </div>

                <div id="pedidoInfo" class="hidden space-y-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p id="pedidoNombre" class="text-[13px] font-semibold text-[#e8e0d0]">—</p>
                            <p id="pedidoDuracion" class="text-[10px] text-white/35">—</p>
                        </div>
                        <p id="pedidoPrecio" class="font-[var(--font-playfair)] text-[15px] font-bold text-[var(--gold)]">—</p>
                    </div>
                    <div class="border-t border-[#181818] pt-4 flex items-center gap-3">
                        <img src="assets/img/logo.jpg" class="h-9 w-9 rounded-full border border-[var(--gold)]/20 object-cover">
                        <div>
                            <p class="text-[12px] font-semibold text-white/65"><?= htmlspecialchars($barbero->getNombre()) ?></p>
                            <p id="pedidoFechaHora" class="text-[10px] text-white/30">Sin fecha</p>
                        </div>
                    </div>
                </div>

                <form method="post" id="formReserva" class="mt-6">
                    <input type="hidden" name="accion" value="continuar">
                    <input type="hidden" name="id_servicio" id="inputServicio">
                    <input type="hidden" name="fecha" id="inputFecha">
                    <input type="hidden" name="hora" id="inputHora">
                    <button type="submit" id="btnContinuar" disabled class="w-full rounded-xl bg-[#181818] py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] text-white/20 transition-all">Continuar</button>
                </form>
                <p id="msgLogin" class="mt-3 hidden text-center text-[10px] text-white/22">Se te pedirá cuenta al confirmar</p>
            </div>
        </aside>
    </div>
</main>

<script>
    const DIAS_DISPONIBLES = <?= json_encode($diasCalendario) ?>;
    const MESES_LARGO = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const DIAS_LARGO = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const HOY = "<?= $hoyStr ?>";

    let selS = null, selF = null, selH = null;

    // Lógica Paso 1
    document.querySelectorAll('.servicio-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.servicio-chip').forEach(c => c.classList.remove('border-[var(--gold)]/60', 'bg-white/5'));
            chip.classList.add('border-[var(--gold)]/60', 'bg-white/5');
            selS = { id: chip.dataset.id, nombre: chip.dataset.nombre, precio: chip.dataset.precioFmt, duracion: chip.dataset.duracion };
            selF = null; selH = null;
            actualizarUI();
            revelarPaso('pasoFecha');
        });
    });

    // Lógica Paso 2
    document.querySelectorAll('.dia-btn:not(.dia-disabled)').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.dia-btn').forEach(b => b.classList.remove('border-[var(--gold)]', 'bg-[#141414]'));
            btn.classList.add('border-[var(--gold)]', 'bg-[#141414]');
            selF = btn.dataset.fecha;
            selH = null;

            const [y, m, d] = selF.split('-');
            document.getElementById('labelDiaSeleccionado').textContent = `${DIAS_LARGO[btn.dataset.diasemana]} ${parseInt(d)} de ${MESES_LARGO[parseInt(m)]}`;

            renderHoras(JSON.parse(btn.dataset.horas));
            actualizarUI();
            revelarPaso('pasoHora');
        });
    });

    function renderHoras(horas) {
        const grid = document.getElementById('slotsGrid');
        grid.innerHTML = '';
        horas.forEach(h => {
            const b = document.createElement('button');
            b.className = 'py-3 rounded-xl border border-[#252525] bg-[#0f0f0f] text-[13px] font-semibold text-[#ccc4b0] hover:border-[var(--gold)]/50 transition-all';
            b.textContent = h;
            b.onclick = () => {
                document.querySelectorAll('.hora-btn-active').forEach(x => x.classList.remove('hora-btn-active', 'border-[var(--gold)]', 'text-[var(--gold)]'));
                b.classList.add('hora-btn-active', 'border-[var(--gold)]', 'text-[var(--gold)]');
                selH = h;
                actualizarUI();
            };
            grid.appendChild(b);
        });
    }

    function actualizarUI() {
        if(selS) {
            document.getElementById('pedidoVacio').classList.add('hidden');
            document.getElementById('pedidoInfo').classList.remove('hidden');
            document.getElementById('pedidoNombre').textContent = selS.nombre;
            document.getElementById('pedidoDuracion').textContent = selS.duracion + ' min';
            document.getElementById('pedidoPrecio').textContent = selS.precio + ' €';
            document.getElementById('inputServicio').value = selS.id;
        }
        if(selF) {
            const [y, m, d] = selF.split('-');
            document.getElementById('pedidoFechaHora').textContent = `${d} de ${MESES_LARGO[parseInt(m)]}${selH ? ' · ' + selH : ''}`;
            document.getElementById('inputFecha').value = selF;
        }
        if(selH) {
            document.getElementById('inputHora').value = selH;
            const btn = document.getElementById('btnContinuar');
            btn.disabled = false;
            btn.className = 'w-full rounded-xl bg-[var(--gold)] py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[var(--obsidian)] shadow-lg cursor-pointer';
            document.getElementById('msgLogin').classList.remove('hidden');
        }
    }

    function revelarPaso(id) {
        const el = document.getElementById(id);
        el.classList.remove('reserva-step-oculto');
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
</script>

<style>
    .reserva-step-oculto { opacity: 0.2; pointer-events: none; filter: grayscale(1); }
    .dia-scroll::-webkit-scrollbar { height: 4px; }
    .dia-scroll::-webkit-scrollbar-thumb { background: rgba(212,175,55,0.2); border-radius: 10px; }
</style>

</body>
</html>