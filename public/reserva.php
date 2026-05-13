<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Servicio.php';
require_once __DIR__ . '/../clases/Barbero.php';
require_once __DIR__ . '/../clases/Reserva.php';

session_start();

const ID_BARBERO_ACTIVO = 1;

function h(mixed $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function tokenReserva(): string {
    if (empty($_SESSION['csrf_reserva'])) {
        $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_reserva'];
}

function tokenReservaValido(?string $token): bool {
    return isset($_SESSION['csrf_reserva']) && is_string($token) && hash_equals($_SESSION['csrf_reserva'], $token);
}

function inicioSemana(DateTimeImmutable $fecha): DateTimeImmutable {
    return $fecha->modify('-' . ((int)$fecha->format('N') - 1) . ' days');
}

function fechaValida(string $fecha): bool {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $fecha;
}

function nombreMes(int $mes): string {
    $meses = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    return $meses[$mes] ?? '';
}

function nombreDia(int $dia): string {
    $dias = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        7 => 'domingo',
    ];

    return $dias[$dia] ?? '';
}

function fechaHumana(string $fecha): string {
    $dt = new DateTimeImmutable($fecha);
    return nombreDia((int)$dt->format('N')) . ' ' . $dt->format('j') . ' de ' . nombreMes((int)$dt->format('n'));
}

$servicios = Servicio::obtenerTodos();
$serviciosPorId = [];
foreach ($servicios as $servicio) {
    $serviciosPorId[$servicio->getIdServicio()] = $servicio;
}

$barbero = Barbero::obtenerPorId(ID_BARBERO_ACTIVO);
$hoy = new DateTimeImmutable('today');
$semanaActual = inicioSemana($hoy);
$semanaMaxima = inicioSemana($hoy->modify('+12 weeks'));

$semanaParam = $_GET['semana'] ?? ($_GET['inicio'] ?? $hoy->format('Y-m-d'));
try {
    $semanaSolicitada = new DateTimeImmutable((string)$semanaParam);
} catch (Throwable) {
    $semanaSolicitada = $hoy;
}

$inicioSemana = inicioSemana($semanaSolicitada);
if ($inicioSemana < $semanaActual) {
    $inicioSemana = $semanaActual;
}
if ($inicioSemana > $semanaMaxima) {
    $inicioSemana = $semanaMaxima;
}

$idServicioInicial = isset($_GET['servicio']) ? (int)$_GET['servicio'] : 0;
if (!isset($serviciosPorId[$idServicioInicial]) && $servicios !== []) {
    $idServicioInicial = $servicios[0]->getIdServicio();
}

$fechaInicial = isset($_GET['fecha']) && fechaValida((string)$_GET['fecha']) ? (string)$_GET['fecha'] : '';
if ($fechaInicial === '') {
    $fechaInicial = ($inicioSemana->format('Y-m-d') === $semanaActual->format('Y-m-d'))
        ? $hoy->format('Y-m-d')
        : $inicioSemana->format('Y-m-d');
}

$fechaInicialDt = new DateTimeImmutable($fechaInicial);
$finSemana = $inicioSemana->modify('+6 days');
if ($fechaInicialDt < $inicioSemana || $fechaInicialDt > $finSemana) {
    $fechaInicial = ($inicioSemana->format('Y-m-d') === $semanaActual->format('Y-m-d'))
        ? $hoy->format('Y-m-d')
        : $inicioSemana->format('Y-m-d');
}

$horaInicial = '';
$errorReserva = $_SESSION['reserva_error'] ?? '';
unset($_SESSION['reserva_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'continuar') {
    $idServicioInicial = (int)($_POST['id_servicio'] ?? 0);
    $fechaInicial = (string)($_POST['fecha'] ?? '');
    $horaInicial = substr((string)($_POST['hora'] ?? ''), 0, 5);
    $servicioSeleccionado = $serviciosPorId[$idServicioInicial] ?? null;

    if (!tokenReservaValido($_POST['csrf_token'] ?? null)) {
        $errorReserva = 'La sesion ha caducado. Recarga la pagina y vuelve a elegir el hueco.';
    } elseif (!$barbero || !$servicioSeleccionado) {
        $errorReserva = 'Selecciona un servicio valido para continuar.';
    } elseif (!fechaValida($fechaInicial) || !preg_match('/^\d{2}:\d{2}$/', $horaInicial)) {
        $errorReserva = 'Selecciona un dia y una hora disponibles antes de continuar.';
    } elseif (!Reserva::estaDisponible(ID_BARBERO_ACTIVO, $fechaInicial, $horaInicial, $servicioSeleccionado->getDuracion())) {
        $errorReserva = 'Ese hueco acaba de dejar de estar disponible. Elige otra hora.';
    } else {
        $_SESSION['reserva_pendiente'] = [
            'id_barbero' => ID_BARBERO_ACTIVO,
            'barbero_nombre' => $barbero->getNombre(),
            'id_servicio' => $servicioSeleccionado->getIdServicio(),
            'servicio_nombre' => $servicioSeleccionado->getNombre(),
            'fecha' => $fechaInicial,
            'fecha_label' => fechaHumana($fechaInicial),
            'hora' => $horaInicial,
            'precio' => $servicioSeleccionado->getPrecio(),
            'duracion' => $servicioSeleccionado->getDuracion(),
            'guardada_en' => time(),
        ];

        $destino = (($_SESSION['usuario'] ?? null) instanceof Usuario)
            ? 'confirmar_reserva.php'
            : 'login.php?source=reserva';

        header('Location: ' . $destino);
        exit;
    }
}

$diasSemana = [];
for ($i = 0; $i < 7; $i++) {
    $fecha = $inicioSemana->modify('+' . $i . ' days');
    $fechaIso = $fecha->format('Y-m-d');
    $diasSemana[] = [
        'fecha' => $fechaIso,
        'dia_corto' => ucfirst(substr(nombreDia((int)$fecha->format('N')), 0, 3)),
        'dia_largo' => fechaHumana($fechaIso),
        'numero' => $fecha->format('j'),
        'mes' => nombreMes((int)$fecha->format('n')),
        'es_hoy' => $fechaIso === $hoy->format('Y-m-d'),
        'pasado' => $fecha < $hoy,
    ];
}

$disponibilidad = [];
$serviciosJson = [];
foreach ($serviciosPorId as $id => $servicio) {
    $serviciosJson[$id] = [
        'id' => $servicio->getIdServicio(),
        'nombre' => $servicio->getNombre(),
        'precio' => $servicio->getPrecio(),
        'precio_formateado' => number_format($servicio->getPrecio(), 2, ',', '.') . ' €',
        'duracion' => $servicio->getDuracion(),
        'descripcion' => $servicio->getDescripcion() ?? '',
    ];

    foreach ($diasSemana as $dia) {
        $disponibilidad[$id][$dia['fecha']] = Reserva::obtenerSlotsDisponibles(ID_BARBERO_ACTIVO, $dia['fecha'], $servicio->getDuracion());
    }
}

$mesInicio = nombreMes((int)$inicioSemana->format('n'));
$mesFin = nombreMes((int)$finSemana->format('n'));
$tituloSemana = $mesInicio === $mesFin
    ? $mesInicio . ' ' . $inicioSemana->format('Y')
    : $mesInicio . ' / ' . $mesFin . ' ' . $inicioSemana->format('Y');

$prevSemana = $inicioSemana->modify('-7 days');
$sigSemana = $inicioSemana->modify('+7 days');
$puedeRetroceder = $prevSemana >= $semanaActual;
$puedeAvanzar = $sigSemana <= $semanaMaxima;

$queryBase = ['servicio' => $idServicioInicial];
$hrefPrev = 'reserva.php?' . http_build_query(array_merge($queryBase, ['semana' => $prevSemana->format('Y-m-d')]));
$hrefSig = 'reserva.php?' . http_build_query(array_merge($queryBase, ['semana' => $sigSemana->format('Y-m-d')]));

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$csrfToken = tokenReserva();
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
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="min-h-screen overflow-x-hidden bg-[var(--obsidian)] font-[var(--font-montserrat)] text-[#f5f0e8]">
    <div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_70%_50%_at_15%_0%,rgba(212,175,55,0.075)_0%,transparent_65%),linear-gradient(180deg,rgba(255,255,255,0.035),transparent_24%)]"></div>

    <a href="index.php" class="fixed left-5 top-5 z-40 inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/45 no-underline transition hover:-translate-x-0.5 hover:text-[var(--gold)]">
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
                    <img src="assets/img/logo.jpg" alt="Hassan" class="h-14 w-14 rounded-full border border-[var(--gold)]/30 object-cover">
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

                    <!-- Nuevo estado compacto: despues de elegir servicio, libera espacio para ver calendario y horas. -->
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
                                            <span class="mt-1 block text-xs text-white/40"><?= $servicio->getDuracion() ?> min<?= $servicio->getDescripcion() ? ' · ' . h($servicio->getDescripcion()) : '' ?></span>
                                        </span>
                                        <span class="shrink-0 font-[var(--font-playfair)] text-xl font-bold text-[var(--gold)]"><?= number_format($servicio->getPrecio(), 2, ',', '.') ?> €</span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="reserve-stagger rounded-lg border border-white/10 bg-[#0d0d0d]/95 p-5 shadow-[0_18px_60px_rgba(0,0,0,0.45)] sm:p-6">
                    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[var(--gold)]">2. Fecha</p>
                            <h2 class="mt-1 text-xl font-semibold capitalize text-white"><?= h($tituloSemana) ?></h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($puedeRetroceder): ?>
                                <a href="<?= h($hrefPrev) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/10 text-white/50 transition hover:border-[var(--gold)]/45 hover:text-[var(--gold)]" aria-label="Semana anterior">
                                    <i class="bi bi-chevron-left text-sm"></i>
                                </a>
                            <?php else: ?>
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/5 text-white/10" aria-hidden="true">
                                    <i class="bi bi-chevron-left text-sm"></i>
                                </span>
                            <?php endif; ?>

                            <?php if ($puedeAvanzar): ?>
                                <a href="<?= h($hrefSig) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/10 text-white/50 transition hover:border-[var(--gold)]/45 hover:text-[var(--gold)]" aria-label="Semana siguiente">
                                    <i class="bi bi-chevron-right text-sm"></i>
                                </a>
                            <?php else: ?>
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/5 text-white/10" aria-hidden="true">
                                    <i class="bi bi-chevron-right text-sm"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-2">
                        <?php foreach ($diasSemana as $dia): ?>
                            <?php
                            $slotsDia = $disponibilidad[$idServicioInicial][$dia['fecha']] ?? [];
                            $sinHuecos = count($slotsDia) === 0;
                            ?>
                            <button type="button"
                                    class="day-option rounded-lg border border-white/10 bg-white/[0.025] px-1 py-3 text-center transition duration-300 hover:border-[var(--gold)]/45 hover:bg-white/[0.055] disabled:cursor-not-allowed disabled:opacity-35 aria-selected:border-[var(--gold)] aria-selected:bg-[var(--gold)]/[0.10]"
                                    data-date="<?= h($dia['fecha']) ?>"
                                    data-past="<?= $dia['pasado'] ? '1' : '0' ?>"
                                    aria-selected="<?= $dia['fecha'] === $fechaInicial ? 'true' : 'false' ?>"
                                    <?= $sinHuecos ? 'disabled' : '' ?>>
                                <span class="block text-[10px] font-bold uppercase tracking-[0.12em] text-white/38"><?= h($dia['dia_corto']) ?></span>
                                <span class="mt-1 block text-lg font-semibold text-white"><?= h($dia['numero']) ?></span>
                                <span class="mt-1 block text-[10px] text-[var(--gold)]/65" data-day-count><?= count($slotsDia) ?> huecos</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="reserve-stagger rounded-lg border border-white/10 bg-[#0d0d0d]/95 p-5 shadow-[0_18px_60px_rgba(0,0,0,0.45)] sm:p-6">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[var(--gold)]">3. Hora</p>
                            <h2 class="mt-1 text-xl font-semibold text-white" id="slotsTitle">Huecos disponibles</h2>
                        </div>
                        <span class="rounded-full border border-[var(--gold)]/20 bg-[var(--gold)]/[0.08] px-3 py-1 text-[11px] font-semibold text-[var(--gold)]" id="selectedDayPill"></span>
                    </div>

                    <!-- Las horas se agrupan por franja para evitar una parrilla larga y dificil de escanear. -->
                    <div id="slotsGrid" class="grid gap-4 lg:grid-cols-3"></div>

                    <div id="slotsEmpty" class="hidden rounded-lg border border-dashed border-white/10 bg-white/[0.02] px-4 py-10 text-center">
                        <i class="bi bi-calendar-x reserve-live-pulse block text-3xl text-white/15"></i>
                        <p class="mt-3 text-sm text-white/45">No quedan huecos para este dia con el servicio seleccionado.</p>
                    </div>
                </section>
            </div>

            <aside class="reserve-stagger lg:sticky lg:top-6">
                <div class="rounded-lg border border-[var(--gold)]/18 bg-[#101010] p-5 shadow-[0_24px_70px_rgba(0,0,0,0.55)]">
                    <div class="mb-5 flex items-center justify-between">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-white/35">Resumen</p>
                        <span class="rounded-full bg-[var(--gold)] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)]">Sin cuenta aun</span>
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
                        <?= $usuarioConSesion ? 'Tu sesion esta iniciada. Pasaras directo al resumen final.' : 'Al continuar guardamos tu seleccion y te pedimos acceso solo para confirmar.' ?>
                    </p>
                </div>
            </aside>
        </div>
    </main>

    <script>
        const reservaServicios = <?= json_encode($serviciosJson, $jsonFlags) ?>;
        const reservaDisponibilidad = <?= json_encode($disponibilidad, $jsonFlags) ?>;
        const reservaDias = <?= json_encode(array_column($diasSemana, null, 'fecha'), $jsonFlags) ?>;

        const state = {
            serviceId: String(<?= (int)$idServicioInicial ?>),
            date: "<?= h($fechaInicial) ?>",
            hour: "<?= h($horaInicial) ?>",
        };

        const serviceButtons = document.querySelectorAll(".service-option");
        const serviceChoices = document.getElementById("serviceChoices");
        const serviceCompact = document.getElementById("serviceCompact");
        const changeServiceButton = document.getElementById("changeServiceButton");
        const dayButtons = document.querySelectorAll(".day-option");
        const slotsGrid = document.getElementById("slotsGrid");
        const slotsEmpty = document.getElementById("slotsEmpty");
        const selectedDayPill = document.getElementById("selectedDayPill");
        const inputService = document.getElementById("inputService");
        const inputDate = document.getElementById("inputDate");
        const inputHour = document.getElementById("inputHour");
        const continueButton = document.getElementById("continueButton");

        function slotsFor(serviceId, date) {
            return reservaDisponibilidad[serviceId]?.[date] ?? [];
        }

        function serviceData() {
            return reservaServicios[state.serviceId] ?? null;
        }

        // Divide los huecos en franjas visuales tipo Booksy: mañana, mediodia y tarde.
        function groupSlotsByMoment(slots) {
            const groups = [
                { key: "morning", label: "Mañana", icon: "bi-sunrise", slots: [] },
                { key: "midday", label: "Mediodía", icon: "bi-brightness-high", slots: [] },
                { key: "afternoon", label: "Tarde", icon: "bi-sunset", slots: [] },
            ];

            slots.forEach((slot) => {
                const [hour] = slot.split(":").map(Number);

                if (hour < 12) {
                    groups[0].slots.push(slot);
                } else if (hour < 16) {
                    groups[1].slots.push(slot);
                } else {
                    groups[2].slots.push(slot);
                }
            });

            return groups;
        }

        // Nuevo comportamiento: el selector se contrae cuando ya hay servicio elegido.
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

        function updateDayAvailability() {
            dayButtons.forEach((button) => {
                const count = slotsFor(state.serviceId, button.dataset.date).length;
                const label = button.querySelector("[data-day-count]");
                label.textContent = count === 1 ? "1 hueco" : `${count} huecos`;
                button.disabled = count === 0;
            });
        }

        function renderSlots() {
            const slots = slotsFor(state.serviceId, state.date);
            slotsGrid.innerHTML = "";
            slotsEmpty.classList.toggle("hidden", slots.length > 0);
            selectedDayPill.textContent = reservaDias[state.date]?.dia_largo ?? "";

            if (!slots.includes(state.hour)) {
                state.hour = "";
            }

            groupSlotsByMoment(slots).forEach((group) => {
                const section = document.createElement("section");
                section.className = "reserve-slot-appear rounded-lg border border-white/10 bg-white/[0.025] p-3";

                const header = document.createElement("div");
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
                    empty.className = "rounded-lg border border-dashed border-white/10 px-3 py-4 text-center text-xs text-white/25";
                    empty.textContent = "Sin huecos";
                    section.appendChild(empty);
                } else {
                    group.slots.forEach((slot) => {
                        const button = document.createElement("button");
                        button.type = "button";
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
            document.getElementById("summaryService").textContent = service?.nombre ?? "-";
            document.getElementById("summaryDuration").textContent = service ? `${service.duracion} min con Hassan` : "-";
            document.getElementById("summaryPrice").textContent = service?.precio_formateado ?? "-";
            document.getElementById("summaryDate").textContent = reservaDias[state.date]?.dia_largo ?? "-";
            document.getElementById("summaryHour").textContent = state.hour || "Selecciona una hora";

            inputService.value = state.serviceId;
            inputDate.value = state.date;
            inputHour.value = state.hour;
            continueButton.disabled = !(state.serviceId && state.date && state.hour);
        }

        function selectService(serviceId, collapsePicker = true) {
            state.serviceId = String(serviceId);
            state.hour = "";
            setSelected(serviceButtons, "serviceId", state.serviceId);
            updateCompactService();
            setServicePicker(!collapsePicker);
            updateDayAvailability();

            if (slotsFor(state.serviceId, state.date).length === 0) {
                const firstAvailable = Array.from(dayButtons).find((button) => slotsFor(state.serviceId, button.dataset.date).length > 0);
                if (firstAvailable) {
                    state.date = firstAvailable.dataset.date;
                }
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

        dayButtons.forEach((button) => {
            button.addEventListener("click", () => selectDate(button.dataset.date));
        });

        document.getElementById("bookingForm").addEventListener("submit", (event) => {
            updateSummary();
            if (continueButton.disabled) {
                event.preventDefault();
            }
        });

        selectService(state.serviceId, <?= isset($_GET['servicio']) ? 'true' : 'false' ?>);
    </script>
</body>
</html>
