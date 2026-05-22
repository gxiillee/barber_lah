<?php
// FASE 1: Configuración e Inicialización
declare(strict_types=1);

// sincroniza los horarios con los de España
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Csrf.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Servicio.php';
require_once __DIR__ . '/../clases/Barbero.php';
require_once __DIR__ . '/../clases/Reserva.php';

session_start();

// FASE 2: Cargar los datos, servicio para elegir y barbero

// El único barbero activo del proyecto. Si en el futuro hay más, esto vendrá de la BD.
const ID_BARBERO_ACTIVO = 1;

$servicios = Servicio::obtenerTodos();

// servicio por id del array, para no recorrer todo el rato el array
// solo llamar a getIdServicio y no hace siempre foreach
$serviciosPorId = [];
foreach ($servicios as $servicio) {
    $serviciosPorId[$servicio->getIdServicio()] = $servicio;
}

$barbero = Barbero::obtenerPorId(ID_BARBERO_ACTIVO);

// FASE 3: Lógica de Calendario y Navegación

//te da el dia de hoy sin poder modificarse
$hoy = new DateTimeImmutable('today');
//te da la semana actual para no poder reservar un dia pasado
$semanaActual = obtenerLunesDeSemana($hoy);
//para poder reservar desde hoy hasta 12 semanas mas
$semanaMaxima = obtenerLunesDeSemana($hoy->modify('+12 weeks'));

//recoje la peticion de semaa por las flechas, por defecto pinta la de hoy
$semanaParam = $_GET['semana'] ?? ($_GET['inicio'] ?? $hoy->format('Y-m-d'));
//capturar por si alguien intenta en URL poner algo falso, catch dice usa el de hoy
try {
    $semanaSolicitada = new DateTimeImmutable((string)$semanaParam);
} catch (Throwable) {
    $semanaSolicitada = $hoy;
}
//por si un gracioso quiere buscar por URL hace -o+ 6 meses etc..
$inicioSemana = obtenerLunesDeSemana($semanaSolicitada);
if ($inicioSemana < $semanaActual) {
    $inicioSemana = $semanaActual;
}
if ($inicioSemana > $semanaMaxima) {
    $inicioSemana = $semanaMaxima;
}

//selecciona el servicio primero por defecto
$idServicioInicial = isset($_GET['servicio']) ? (int)$_GET['servicio'] : 0;
//si no existe el que pone le marca tambien el primero
if (!isset($serviciosPorId[$idServicioInicial]) && $servicios !== []) {
    $idServicioInicial = $servicios[0]->getIdServicio();
}
//pone fecha por defecto y si es falsa deja vacia
$fechaInicial = isset($_GET['fecha']) && esFechaValida((string)$_GET['fecha'])
        ? (string)$_GET['fecha']
    : '';

if ($fechaInicial === '') {
    $fechaInicial = ($inicioSemana->format('Y-m-d') === $semanaActual->format('Y-m-d'))
        ? $hoy->format('Y-m-d')
        : $inicioSemana->format('Y-m-d');
}

// Si la fecha inicial cae fuera de la semana visible, corregirla
$fechaInicialDt = new DateTimeImmutable($fechaInicial);
//calculo el fin de esa semana
$finSemana = $inicioSemana->modify('+6 days');
if ($fechaInicialDt < $inicioSemana || $fechaInicialDt > $finSemana) {
    $fechaInicial = ($inicioSemana->format('Y-m-d') === $semanaActual->format('Y-m-d'))
        ? $hoy->format('Y-m-d')
        : $inicioSemana->format('Y-m-d');
}

$horaInicial = '';
$errorReserva = $_SESSION['reserva_error'] ?? '';
unset($_SESSION['reserva_error']);

// FASE 4: Matriz de Disponibilidad

$diasSemana = Reserva::construirDiasSemana($inicioSemana, $hoy);
$disponibilidad = Reserva::construirDisponibilidad(ID_BARBERO_ACTIVO, $serviciosPorId, $diasSemana);
$serviciosJson = Reserva::construirServiciosJson($serviciosPorId);
$tituloSemana = obtenerTituloSemana($inicioSemana);
$navSemana = calcularBotonesNavegacion($inicioSemana, $semanaActual, $semanaMaxima);

$prevSemana = new DateTimeImmutable($navSemana['prev']);
$sigSemana = new DateTimeImmutable($navSemana['next']);
$puedeRetroceder = $navSemana['puede_retroceder'];
$puedeAvanzar = $navSemana['puede_avanzar'];

$queryBase = ['servicio' => $idServicioInicial];
$hrefPrev = 'reserva.php?' . http_build_query(array_merge($queryBase, ['semana' => $prevSemana->format('Y-m-d')]));
$hrefSig = 'reserva.php?' . http_build_query(array_merge($queryBase, ['semana' => $sigSemana->format('Y-m-d')]));

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

// Respuesta ligera para navegar semanas sin recargar toda la página
if (($_GET['ajax'] ?? '') === 'semana') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'semana'         => $inicioSemana->format('Y-m-d'),
        'titulo'         => $tituloSemana,
        'dias'           => array_column($diasSemana, null, 'fecha'),
        'disponibilidad' => $disponibilidad,
        'nav'            => $navSemana,
    ], $jsonFlags);
    exit;
}

// FASE 5: Procesamiento del Formulario (POST)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'continuar') {
    $idServicioInicial = (int)($_POST['id_servicio'] ?? 0);
    $fechaInicial = (string)($_POST['fecha'] ?? '');
    $horaInicial = substr((string)($_POST['hora'] ?? ''), 0, 5);
    $servicioSeleccionado = $serviciosPorId[$idServicioInicial] ?? null;

    if (!Csrf::validarToken('csrf_reserva', $_POST['csrf_token'] ?? null)) {
        $errorReserva = 'La sesión ha caducado. Recarga la página y vuelve a elegir el hueco.';
    } elseif (!$barbero || !$servicioSeleccionado) {
        $errorReserva = 'Selecciona un servicio válido para continuar.';
    } elseif (!esFechaValida($fechaInicial) || !preg_match('/^\d{2}:\d{2}$/', $horaInicial)) {
        $errorReserva = 'Selecciona un día y una hora disponibles antes de continuar.';
    } elseif (!Reserva::estaDisponible(ID_BARBERO_ACTIVO, $fechaInicial, $horaInicial, $servicioSeleccionado->getDuracion())) {
        $errorReserva = 'Ese hueco acaba de dejar de estar disponible. Elige otra hora.';
    } else {
        $_SESSION['reserva_pendiente'] = [
            'id_barbero'     => ID_BARBERO_ACTIVO,
            'barbero_nombre' => $barbero->getNombre(),
            'id_servicio'    => $servicioSeleccionado->getIdServicio(),
            'servicio_nombre'=> $servicioSeleccionado->getNombre(),
            'fecha'          => $fechaInicial,
            'fecha_label'    => fechaHumana($fechaInicial),
            'hora'           => $horaInicial,
            'precio'         => $servicioSeleccionado->getPrecio(),
            'duracion'       => $servicioSeleccionado->getDuracion(),
            'guardada_en'    => time(),
        ];

        $destino = (($_SESSION['usuario'] ?? null) instanceof Usuario)
            ? 'confirmar_reserva.php'
            : '../login.php?source=reserva';

        header('Location: ' . $destino);
        exit;
    }
}

// Variables finales para la vista
$csrfToken = Csrf::generarToken('csrf_reserva');
$usuarioConSesion = ($_SESSION['usuario'] ?? null) instanceof Usuario;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar cita · Barbershop La H</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen overflow-x-hidden bg-[var(--obsidian)] font-[var(--font-montserrat)] text-[#f5f0e8]">
    <div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_70%_50%_at_15%_0%,rgba(212,175,55,0.075)_0%,transparent_65%),linear-gradient(180deg,rgba(255,255,255,0.035),transparent_24%)]"></div>

    <a href="../index.php" class="fixed left-5 top-5 z-40 inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/45 no-underline transition hover:-translate-x-0.5 hover:text-[var(--gold)]">
        <i class="bi bi-arrow-left"></i>
        Inicio
    </a>

    <main class="reserve-shell relative z-10 mx-auto w-full max-w-7xl px-5 pb-16 pt-20 sm:px-8 lg:px-10">
        <header class="mb-9 grid gap-6 lg:grid-cols-[1fr_360px] lg:items-end">
            <div>
                <p class="mb-3 font-[var(--font-playfair)] text-[11px] uppercase tracking-[0.32em] text-[var(--gold)]">Barbershop La H</p>
                <h1 class="font-[var(--font-playfair)] text-[42px] font-bold leading-none text-[#f5f0e8] sm:text-[56px]">Reserva tu cita</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-white/45">Explora servicios, dias y horas disponibles sin crear cuenta. Te pediremos login solo cuando ya tengas claro el hueco.</p>
            </div>
            <div class="rounded-lg border border-white/10 bg-white/[0.035] p-4">
                <div class="flex items-center gap-3">
                    <img src="../public/assets/img/logo.jpg" alt="Hassan" class="h-14 w-14 rounded-full border border-[var(--gold)]/30 object-cover">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/35">Tu barbero</p>
                        <p class="mt-1 text-base font-semibold text-white"><?= h($barbero?->getNombre() ?? 'Hassan') ?></p>
                        <p class="text-xs text-white/40"><?= h($barbero?->getEspecialidad() ?? 'Corte, barba y diseño') ?></p>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($errorReserva !== ''): ?>
            <div class="reserve-stagger mb-6 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-100" role="alert">
                <i class="bi bi-exclamation-circle mt-0.5 text-red-300"></i>
                <span><?= h($errorReserva) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
            <div class="space-y-6">
                <section class="reserve-stagger rounded-lg border border-white/10 bg-[#0d0d0d]/95 p-5 shadow-[0_18px_60px_rgba(0,0,0,0.45)] sm:p-6" id="serviceSection">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[var(--gold)]">1. Servicio</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Elige que quieres reservar</h2>
                        </div>
                        <i class="bi bi-scissors text-2xl text-[var(--gold)]/55"></i>
                    </div>

                    <div id="serviceCompact" class="mb-1 hidden rounded-lg border border-[var(--gold)]/25 bg-[var(--gold)]/[0.07] p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--gold)]">Servicio seleccionado</p>
                                <p class="mt-1 truncate text-base font-semibold text-white" id="compactServiceName">-</p>
                                <p class="mt-1 text-xs text-white/42" id="compactServiceMeta">-</p>
                            </div>
                            <button type="button" id="changeServiceButton" class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/10 px-4 py-3 text-[11px] font-bold uppercase tracking-[0.14em] text-white/55 transition hover:border-[var(--gold)]/40 hover:text-[var(--gold)]">
                                <i class="bi bi-arrow-repeat"></i>
                                Cambiar
                            </button>
                        </div>
                    </div>

                    <?php if ($servicios === []): ?>
                        <div class="rounded-lg border border-white/10 bg-white/[0.03] px-4 py-8 text-center text-sm text-white/45">No hay servicios activos ahora mismo.</div>
                    <?php else: ?>
                        <div class="grid gap-3 sm:grid-cols-2" id="serviceChoices">
                            <?php foreach ($servicios as $servicio): ?>
                                <button type="button"
                                        class="service-option group rounded-lg border border-white/10 bg-white/[0.025] p-4 text-left transition duration-300 hover:-translate-y-0.5 hover:border-[var(--gold)]/45 hover:bg-white/[0.055] aria-selected:border-[var(--gold)] aria-selected:bg-[var(--gold)]/[0.08]"
                                        data-service-id="<?= $servicio->getIdServicio() ?>"
                                        aria-selected="<?= $servicio->getIdServicio() === $idServicioInicial ? 'true' : 'false' ?>">
                                    <span class="flex items-start justify-between gap-4">
                                        <span class="min-w-0">
                                            <span class="block truncate text-[15px] font-semibold text-[#f1eadb]"><?= h($servicio->getNombre()) ?></span>
                                            <?php if ($servicio->getDescripcion()): ?>
                                                <span class="mt-1 block text-xs leading-5 text-white/38"><?= h($servicio->getDescripcion()) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="shrink-0 text-right">
                                            <span class="block text-[15px] font-bold text-[var(--gold)]"><?= number_format($servicio->getPrecio(), 2, ',', '.') ?> €</span>
                                            <span class="block text-xs text-white/35"><?= $servicio->getDuracion() ?> min</span>
                                        </span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="reserve-stagger rounded-lg border border-white/10 bg-[#0d0d0d]/95 p-5 shadow-[0_18px_60px_rgba(0,0,0,0.45)] sm:p-6">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[var(--gold)]">2. Fecha</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Elige el dia</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="weekPrev" type="button"
                                    data-week="<?= h($prevSemana->format('Y-m-d')) ?>"
                                    class="rounded-lg border border-white/10 p-2 text-white/45 transition hover:border-[var(--gold)]/35 hover:text-[var(--gold)] disabled:cursor-not-allowed disabled:opacity-30"
                                    <?= !$puedeRetroceder ? 'disabled' : '' ?>>
                                <i class="bi bi-chevron-left text-sm"></i>
                            </button>
                            <span class="min-w-[120px] text-center text-sm font-semibold text-white" id="weekTitle"><?= h($tituloSemana) ?></span>
                            <button id="weekNext" type="button"
                                    data-week="<?= h($sigSemana->format('Y-m-d')) ?>"
                                    class="rounded-lg border border-white/10 p-2 text-white/45 transition hover:border-[var(--gold)]/35 hover:text-[var(--gold)] disabled:cursor-not-allowed disabled:opacity-30"
                                    <?= !$puedeAvanzar ? 'disabled' : '' ?>>
                                <i class="bi bi-chevron-right text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-2" id="weekDays">
                        <?php foreach ($diasSemana as $dia): ?>
                            <?php $count = count($disponibilidad[$idServicioInicial][$dia['fecha']] ?? []); ?>
                            <button type="button"
                                    class="day-option rounded-lg border border-white/10 bg-white/[0.025] px-1 py-3 text-center transition duration-300 hover:border-[var(--gold)]/45 hover:bg-white/[0.055] disabled:cursor-not-allowed disabled:opacity-35 aria-selected:border-[var(--gold)] aria-selected:bg-[var(--gold)]/[0.10]"
                                    data-date="<?= h($dia['fecha']) ?>"
                                    data-past="<?= $dia['pasado'] ? '1' : '0' ?>"
                                    <?= ($count === 0 || $dia['pasado']) ? 'disabled' : '' ?>
                                    aria-selected="<?= $dia['fecha'] === $fechaInicial ? 'true' : 'false' ?>">
                                <span class="block text-[10px] font-bold uppercase tracking-[0.12em] text-white/38"><?= h($dia['dia_corto']) ?></span>
                                <span class="mt-1 block text-lg font-semibold text-white"><?= h($dia['numero']) ?></span>
                                <span class="mt-1 block text-[10px] text-[var(--gold)]/65" data-day-count>
                                    <?= $count === 1 ? '1 hueco' : $count . ' huecos' ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="reserve-stagger rounded-lg border border-white/10 bg-[#0d0d0d]/95 p-5 shadow-[0_18px_60px_rgba(0,0,0,0.45)] sm:p-6">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[var(--gold)]">3. Hora</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Elige un hueco libre</h2>
                        </div>
                        <span class="rounded-full border border-white/10 px-3 py-1 text-[11px] text-white/35" id="selectedDayPill"></span>
                    </div>
                    <div id="slotsGrid" class="space-y-3"></div>
                    <p id="slotsEmpty" class="hidden rounded-lg border border-dashed border-white/10 px-4 py-8 text-center text-sm text-white/30">
                        No hay huecos disponibles este dia para el servicio seleccionado.
                    </p>
                </section>
            </div>

            <aside class="reserve-stagger rounded-lg border border-white/10 bg-[#0d0d0d]/95 p-5 shadow-[0_18px_60px_rgba(0,0,0,0.45)] lg:sticky lg:top-6 sm:p-6">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-white/35">Resumen</p>
                    <?php if (!$usuarioConSesion): ?>
                        <span class="rounded-full bg-[var(--gold)] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)]">Sin cuenta aun</span>
                    <?php endif; ?>
                </div>

                <div class="space-y-4">
                    <div class="rounded-lg border border-white/8 bg-white/[0.025] p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/30">Servicio</p>
                        <p class="mt-1 text-base font-semibold text-white" id="summaryService">-</p>
                        <p class="mt-1 text-xs text-white/38" id="summaryDuration">-</p>
                    </div>

                    <div class="rounded-lg border border-white/8 bg-white/[0.025] p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/30">Fecha y hora</p>
                        <p class="mt-1 text-base font-semibold text-white" id="summaryDate">-</p>
                        <p class="mt-1 text-xs text-white/38" id="summaryHour">Selecciona una hora</p>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/10 pt-4">
                        <span class="text-sm text-white/45">Total</span>
                        <span class="font-[var(--font-playfair)] text-3xl font-bold text-[var(--gold)]" id="summaryPrice">-</span>
                    </div>
                </div>

                <form method="post" class="mt-6" id="bookingForm">
                    <input type="hidden" name="accion" value="continuar">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="id_servicio" id="inputService" value="<?= $idServicioInicial ?>">
                    <input type="hidden" name="fecha" id="inputDate" value="<?= h($fechaInicial) ?>">
                    <input type="hidden" name="hora" id="inputHour" value="<?= h($horaInicial) ?>">
                    <button type="submit" id="continueButton" class="w-full rounded-lg bg-white/10 px-4 py-4 text-[12px] font-extrabold uppercase tracking-[0.18em] text-white/30 transition duration-300 disabled:cursor-not-allowed disabled:opacity-60 enabled:bg-[var(--gold)] enabled:text-[var(--obsidian)] enabled:hover:-translate-y-0.5 enabled:hover:bg-[var(--gold-light)]" disabled>
                        <?= $usuarioConSesion ? 'Ir a confirmar' : 'Continuar' ?>
                    </button>
                </form>

                <p class="mt-4 text-center text-[11px] leading-5 text-white/30">
                    <?= $usuarioConSesion
                        ? 'Tu sesion esta iniciada. Pasaras directo al resumen final.'
                        : 'Al continuar guardamos tu seleccion y te pedimos acceso solo para confirmar.' ?>
                </p>
            </aside>
        </div>
    </main>

    <script>
        const reservaServicios = <?= json_encode($serviciosJson, $jsonFlags) ?>;
        let reservaDisponibilidad = <?= json_encode($disponibilidad, $jsonFlags) ?>;
        let reservaDias = <?= json_encode(array_column($diasSemana, null, 'fecha'), $jsonFlags) ?>;

        const state = {
            serviceId: String(<?= (int)$idServicioInicial ?>),
            date: "<?= h($fechaInicial) ?>",
            hour: "<?= h($horaInicial) ?>",
        };

        const serviceButtons      = document.querySelectorAll(".service-option");
        const serviceChoices      = document.getElementById("serviceChoices");
        const serviceCompact      = document.getElementById("serviceCompact");
        const changeServiceButton = document.getElementById("changeServiceButton");
        let dayButtons            = document.querySelectorAll(".day-option");
        const weekDays            = document.getElementById("weekDays");
        const weekTitle           = document.getElementById("weekTitle");
        const weekPrev            = document.getElementById("weekPrev");
        const weekNext            = document.getElementById("weekNext");
        const slotsGrid           = document.getElementById("slotsGrid");
        const slotsEmpty          = document.getElementById("slotsEmpty");
        const selectedDayPill     = document.getElementById("selectedDayPill");
        const inputService        = document.getElementById("inputService");
        const inputDate           = document.getElementById("inputDate");
        const inputHour           = document.getElementById("inputHour");
        const continueButton      = document.getElementById("continueButton");

        function slotsFor(serviceId, date) {
            return reservaDisponibilidad[serviceId]?.[date] ?? [];
        }

        function serviceData() {
            return reservaServicios[state.serviceId] ?? null;
        }

        // Divide los huecos en franjas visuales tipo Booksy: mañana, mediodia y tarde.
        function groupSlotsByMoment(slots) {
            const groups = [
                { key: "morning",   label: "Mañana",   icon: "bi-sunrise",          slots: [] },
                { key: "midday",    label: "Mediodía", icon: "bi-brightness-high",  slots: [] },
                { key: "afternoon", label: "Tarde",    icon: "bi-sunset",           slots: [] },
            ];
            slots.forEach((slot) => {
                const [hour] = slot.split(":").map(Number);
                if      (hour < 12) { groups[0].slots.push(slot); }
                else if (hour < 16) { groups[1].slots.push(slot); }
                else                { groups[2].slots.push(slot); }
            });
            return groups;
        }

        // El selector se contrae cuando ya hay servicio elegido para liberar espacio al calendario.
        function setServicePicker(open) {
            if (!serviceChoices || !serviceCompact) return;
            serviceChoices.classList.toggle("hidden", !open);
            serviceCompact.classList.toggle("hidden", open);
        }

        function updateCompactService() {
            const service = serviceData();
            if (!service) return;
            document.getElementById("compactServiceName").textContent = service.nombre;
            document.getElementById("compactServiceMeta").textContent = `${service.duracion} min · ${service.precio_formateado}`;
        }

        function setSelected(elements, attr, value) {
            elements.forEach((element) => {
                element.setAttribute("aria-selected", element.dataset[attr] === value ? "true" : "false");
            });
        }

        function bindDayButtons() {
            dayButtons = document.querySelectorAll(".day-option");
            dayButtons.forEach((button) => {
                button.addEventListener("click", () => selectDate(button.dataset.date));
            });
        }

        function renderWeekDays() {
            weekDays.innerHTML = "";
            Object.values(reservaDias).forEach((dia) => {
                const count  = slotsFor(state.serviceId, dia.fecha).length;
                const button = document.createElement("button");
                button.type      = "button";
                button.className = "day-option rounded-lg border border-white/10 bg-white/[0.025] px-1 py-3 text-center transition duration-300 hover:border-[var(--gold)]/45 hover:bg-white/[0.055] disabled:cursor-not-allowed disabled:opacity-35 aria-selected:border-[var(--gold)] aria-selected:bg-[var(--gold)]/[0.10]";
                button.dataset.date = dia.fecha;
                button.dataset.past = dia.pasado ? "1" : "0";
                button.disabled     = count === 0;
                button.setAttribute("aria-selected", dia.fecha === state.date ? "true" : "false");
                button.innerHTML = `
                    <span class="block text-[10px] font-bold uppercase tracking-[0.12em] text-white/38">${dia.dia_corto}</span>
                    <span class="mt-1 block text-lg font-semibold text-white">${dia.numero}</span>
                    <span class="mt-1 block text-[10px] text-[var(--gold)]/65" data-day-count>${count === 1 ? "1 hueco" : `${count} huecos`}</span>
                `;
                weekDays.appendChild(button);
            });
            bindDayButtons();
        }

        function updateWeekControls(nav) {
            weekPrev.dataset.week = nav.prev;
            weekNext.dataset.week = nav.next;
            weekPrev.disabled     = !nav.puede_retroceder;
            weekNext.disabled     = !nav.puede_avanzar;
        }

        async function loadWeek(week) {
            const url = new URL(window.location.href);
            url.searchParams.set("ajax",     "semana");
            url.searchParams.set("semana",   week);
            url.searchParams.set("servicio", state.serviceId);

            const response = await fetch(url.toString(), {
                headers: { "X-Requested-With": "fetch" },
            });
            if (!response.ok) return;

            const data = await response.json();
            reservaDisponibilidad = data.disponibilidad;
            reservaDias           = data.dias;
            weekTitle.textContent = data.titulo;
            updateWeekControls(data.nav);

            if (!reservaDias[state.date] || slotsFor(state.serviceId, state.date).length === 0) {
                const firstAvailable = Object.keys(reservaDias).find((date) => slotsFor(state.serviceId, date).length > 0);
                state.date = firstAvailable ?? Object.keys(reservaDias)[0] ?? state.date;
                state.hour = "";
            }

            renderWeekDays();
            renderSlots();
            updateSummary();

            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete("ajax");
            cleanUrl.searchParams.set("semana",   data.semana);
            cleanUrl.searchParams.set("servicio", state.serviceId);
            cleanUrl.searchParams.set("fecha",    state.date);
            window.history.replaceState({}, "", cleanUrl.toString());
        }

        function updateDayAvailability() {
            dayButtons.forEach((button) => {
                const count = slotsFor(state.serviceId, button.dataset.date).length;
                const label = button.querySelector("[data-day-count]");
                label.textContent = count === 1 ? "1 hueco" : `${count} huecos`;
                button.disabled   = count === 0;
            });
        }

        function renderSlots() {
            const slots = slotsFor(state.serviceId, state.date);
            slotsGrid.innerHTML = "";
            slotsEmpty.classList.toggle("hidden", slots.length > 0);
            selectedDayPill.textContent = reservaDias[state.date]?.dia_largo ?? "";

            if (!slots.includes(state.hour)) { state.hour = ""; }

            groupSlotsByMoment(slots).forEach((group) => {
                const section  = document.createElement("section");
                section.className = "reserve-slot-appear rounded-lg border border-white/10 bg-white/[0.025] p-3";

                const header   = document.createElement("div");
                header.className = "mb-3 flex items-center justify-between gap-3";
                header.innerHTML = `
                    <span class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--gold)]">
                        <i class="bi ${group.icon}"></i>
                        ${group.label}
                    </span>
                    <span class="text-[10px] text-white/28">${group.slots.length} huecos</span>
                `;
                section.appendChild(header);

                const grid = document.createElement("div");
                grid.className = "grid grid-cols-3 gap-2 lg:grid-cols-2 xl:grid-cols-3";

                if (group.slots.length === 0) {
                    const empty = document.createElement("p");
                    empty.className  = "rounded-lg border border-dashed border-white/10 px-3 py-4 text-center text-xs text-white/25";
                    empty.textContent = "Sin huecos";
                    section.appendChild(empty);
                } else {
                    group.slots.forEach((slot) => {
                        const button     = document.createElement("button");
                        button.type      = "button";
                        button.className = "rounded-lg border border-white/10 bg-white/[0.03] px-2 py-3 text-sm font-bold text-[#ede4d2] transition duration-300 hover:-translate-y-0.5 hover:border-[var(--gold)]/50 hover:text-[var(--gold)] aria-selected:border-[var(--gold)] aria-selected:bg-[var(--gold)]/[0.10] aria-selected:text-[var(--gold)]";
                        button.textContent = slot;
                        button.dataset.hour = slot;
                        button.setAttribute("aria-selected", slot === state.hour ? "true" : "false");
                        button.addEventListener("click", () => {
                            state.hour = slot;
                            renderSlots();
                            updateSummary();
                        });
                        grid.appendChild(button);
                    });
                    section.appendChild(grid);
                }
                slotsGrid.appendChild(section);
            });
        }

        function updateSummary() {
            const service = serviceData();
            document.getElementById("summaryService").textContent  = service?.nombre ?? "-";
            document.getElementById("summaryDuration").textContent = service ? `${service.duracion} min con Hassan` : "-";
            document.getElementById("summaryPrice").textContent    = service?.precio_formateado ?? "-";
            document.getElementById("summaryDate").textContent     = reservaDias[state.date]?.dia_largo ?? "-";
            document.getElementById("summaryHour").textContent     = state.hour || "Selecciona una hora";

            inputService.value     = state.serviceId;
            inputDate.value        = state.date;
            inputHour.value        = state.hour;
            continueButton.disabled = !(state.serviceId && state.date && state.hour);
        }

        function selectService(serviceId, collapsePicker = true) {
            state.serviceId = String(serviceId);
            state.hour      = "";
            setSelected(serviceButtons, "serviceId", state.serviceId);
            updateCompactService();
            setServicePicker(!collapsePicker);
            updateDayAvailability();

            if (slotsFor(state.serviceId, state.date).length === 0) {
                const firstAvailable = Array.from(dayButtons).find(
                    (button) => slotsFor(state.serviceId, button.dataset.date).length > 0
                );
                if (firstAvailable) { state.date = firstAvailable.dataset.date; }
            }

            setSelected(dayButtons, "date", state.date);
            renderSlots();
            updateSummary();
        }

        function selectDate(date) {
            if (slotsFor(state.serviceId, date).length === 0) return;
            state.date = date;
            state.hour = "";
            setSelected(dayButtons, "date", state.date);
            renderSlots();
            updateSummary();
        }

        serviceButtons.forEach((button) => {
            button.addEventListener("click", () => selectService(button.dataset.serviceId, true));
        });

        if (changeServiceButton) {
            changeServiceButton.addEventListener("click", () => setServicePicker(true));
        }

        bindDayButtons();

        weekPrev.addEventListener("click", () => { if (!weekPrev.disabled) loadWeek(weekPrev.dataset.week); });
        weekNext.addEventListener("click", () => { if (!weekNext.disabled) loadWeek(weekNext.dataset.week); });

        document.getElementById("bookingForm").addEventListener("submit", (event) => {
            updateSummary();
            if (continueButton.disabled) { event.preventDefault(); }
        });

        selectService(state.serviceId, <?= isset($_GET['servicio']) ? 'true' : 'false' ?>);
    </script>
</body>
</html>