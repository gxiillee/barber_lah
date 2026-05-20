<?php
// Evita cálculos raros con datos que suban a la BD mal (Modo Estricto)
declare(strict_types=1);

// Sincroniza los horarios con los de España
date_default_timezone_set('Europe/Madrid');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Carga de utilidades globales y modelos del sistema
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Csrf.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Servicio.php';
require_once __DIR__ . '/../clases/Barbero.php';
require_once __DIR__ . '/../clases/Reserva.php';
require_once __DIR__ . '/../clases/NotificadorReserva.php';

session_start();

// ---------------------------------------------------------------
// FASE 1: Configuración, Seguridad y Control de Acceso
// ---------------------------------------------------------------
$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario instanceof Usuario) {
    redirigir('login.php?source=reserva');
}

// ---------------------------------------------------------------
// FASE 2: Recuperación de Datos de la Sesión Pendiente
// ---------------------------------------------------------------
$pendiente = (isset($_SESSION['reserva_pendiente']) && is_array($_SESSION['reserva_pendiente']))
        ? $_SESSION['reserva_pendiente']
        : null;

if ($pendiente === null) {
    redirigir('reserva.php');
}

// ---------------------------------------------------------------
// FASE 3: Carga de Entidades Relacionadas (ORM/Modelos)
// ---------------------------------------------------------------
$idBarbero  = (int)($pendiente['id_barbero']  ?? 1);
$idServicio = (int)($pendiente['id_servicio'] ?? 0);
$fecha      = (string)($pendiente['fecha']    ?? '');
$hora       = substr((string)($pendiente['hora'] ?? ''), 0, 5);

$barbero  = Barbero::obtenerPorId($idBarbero);
$servicio = Servicio::obtenerPorId($idServicio);

if (!$barbero || !$servicio || $fecha === '' || $hora === '') {
    unset($_SESSION['reserva_pendiente']);
    redirigir('reserva.php');
}

// ---------------------------------------------------------------
// FASE 4: Verificación de Disponibilidad en Tiempo Real y UI
// ---------------------------------------------------------------
$precio            = (float)($pendiente['precio'] ?? $servicio->getPrecio());
$duracion          = (int)($pendiente['duracion'] ?? $servicio->getDuracion());
$disponibleAhora   = Reserva::estaDisponible($idBarbero, $fecha, $hora, $duracion);
$errorConfirmacion = '';

// OPTIMIZACIÓN: Pre-calculamos los datos formateados legibles para la vista (SRP)
$fechaHumana       = fechaHumana($fecha);
$precioFormateado  = number_format($precio, 2, ',', '.') . ' €';

$urlModificar = 'reserva.php?' . http_build_query([
                'servicio' => $idServicio,
                'semana'   => obtenerLunesDeSemanaStr($fecha), // Función global de helpers
                'fecha'    => $fecha,
        ]);

// ---------------------------------------------------------------
// FASE 5: Procesamiento de la Confirmación (Petición POST)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'confirmar') {

    // SOLUCIÓN AL ERROR: Validación pasando el identificador de acción requerido por tu clase Csrf
    if (!Csrf::validarToken('csrf_confirmar_reserva', $_POST['csrf_token'] ?? null)) {
        $errorConfirmacion = 'La sesión ha caducado o el token de seguridad no es válido. Vuelve a intentarlo.';
    } else {
        try {
            // MOTOR CORE: Ejecución atómica con bloqueo explícito en la base de datos
            $idReserva = Reserva::crearAtomicamente(
                    $usuario->getId(),
                    $idBarbero,
                    $idServicio,
                    $fecha,
                    $hora,
                    $precio,
                    $duracion,
                    null
            );

            if ($idReserva > 0) {
                // Construcción del catálogo de detalles de éxito para la siguiente pantalla e email
                $detalle = [
                        'id_reserva'     => $idReserva,
                        'barbero'        => $barbero->getNombre(),
                        'servicio'       => $servicio->getNombre(),
                        'fecha_humana'   => $fechaHumana,
                        'hora'           => $hora,
                        'precio'         => $precioFormateado,
                        'email'          => $usuario->getEmail(),
                        'duracion'       => $duracion,
                ];

                // Envío del correo de confirmación de forma segura
                $detalle['email_enviado'] = NotificadorReserva::enviarConfirmacion($usuario, $detalle);

                // Guardamos el éxito en la sesión y destruimos de forma limpia la reserva pendiente
                $_SESSION['reserva_confirmada'] = $detalle;
                unset($_SESSION['reserva_pendiente']);

                redirigir('reserva_exito.php');
            } else {
                // El cerrojo transaccional detectó un solapamiento en el último milisegundo
                $_SESSION['error_reserva'] = 'Lo sentimos, el turno elegido ya no está disponible. Por favor, selecciona otra hora.';
                redirigir('reserva.php?' . http_build_query([
                                'servicio' => $idServicio,
                                'semana'   => obtenerLunesDeSemanaStr($fecha),
                                'fecha'    => $fecha,
                        ]));
            }

        } catch (Exception $e) {
            // Captura y manejo robusto de excepciones críticas de la BD
            $_SESSION['error_reserva'] = 'Error crítico en el servidor: ' . $e->getMessage();
            redirigir('reserva.php');
        }
    }
}

// SOLUCIÓN AL ERROR: Generación del token inyectando el identificador string obligatorio
$csrfToken = Csrf::generarToken('csrf_confirmar_reserva');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar reserva · Barbershop La H</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--obsidian)] font-[var(--font-montserrat)] text-[#f5f0e8]">
<div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_65%_45%_at_50%_0%,rgba(212,175,55,0.085)_0%,transparent_70%)]"></div>

<a href="<?= h($urlModificar) ?>" class="fixed left-5 top-5 z-40 inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/45 no-underline transition hover:-translate-x-0.5 hover:text-[var(--gold)]">
    <i class="bi bi-arrow-left"></i>
    Cambiar hora
</a>

<main class="reserve-shell relative z-10 mx-auto flex min-h-screen w-full max-w-5xl items-center px-5 py-20 sm:px-8">
    <section class="grid w-full overflow-hidden rounded-lg border border-white/10 bg-[#0d0d0d] shadow-[0_28px_90px_rgba(0,0,0,0.65)] lg:grid-cols-[1fr_360px]">
        <div class="p-6 sm:p-9">
            <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-[var(--gold)]">Ultimo paso</p>
            <h1 class="mt-3 font-[var(--font-playfair)] text-[40px] font-bold leading-none text-white sm:text-[54px]">Confirma tu reserva</h1>
            <p class="mt-4 max-w-xl text-sm leading-7 text-white/45">No se crea ninguna cita hasta que pulses el boton de confirmacion. Revisa servicio, dia y cuenta antes de guardar.</p>

            <?php if ($errorConfirmacion !== ''): ?>
                <div class="mt-6 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-100" role="alert">
                    <i class="bi bi-exclamation-circle mt-0.5 text-red-300"></i>
                    <span><?= h($errorConfirmacion) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$disponibleAhora): ?>
                <div class="mt-6 flex items-start gap-3 rounded-lg border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-100" role="alert">
                    <i class="bi bi-clock-history mt-0.5 text-amber-300"></i>
                    <span>Este hueco acaba de ocuparse o ya no cabe en el horario. Vuelve al selector para elegir otra hora.</span>
                </div>
            <?php endif; ?>

            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-white/10 bg-white/[0.025] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/30">Servicio</p>
                    <p class="mt-2 text-lg font-semibold text-white"><?= h($servicio->getNombre()) ?></p>
                    <p class="mt-1 text-sm text-white/40"><?= $duracion ?> min · <?= h($precioFormateado) ?></p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/[0.025] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/30">Fecha</p>
                    <p class="mt-2 text-lg font-semibold text-white"><?= h($fechaHumana) ?></p>
                    <p class="mt-1 text-sm text-white/40">A las <?= h($hora) ?></p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/[0.025] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/30">Barbero</p>
                    <div class="mt-3 flex items-center gap-3">
                        <img src="assets/img/logo.jpg" alt="Hassan" class="h-11 w-11 rounded-full border border-[var(--gold)]/25 object-cover">
                        <p class="font-semibold text-white"><?= h($barbero->getNombre()) ?></p>
                    </div>
                </div>
                <div class="rounded-lg border border-[var(--gold)]/20 bg-[var(--gold)]/[0.06] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--gold)]">Cuenta</p>
                    <p class="mt-2 text-lg font-semibold text-white"><?= h($usuario->getEmail()) ?></p>
                    <p class="mt-1 text-sm text-white/40"><?= h($usuario->getNombre()) ?></p>
                </div>
            </div>
        </div>

        <aside class="border-t border-white/10 bg-[#111] p-6 sm:p-8 lg:border-l lg:border-t-0">
            <div class="flex h-full flex-col justify-between gap-8">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-white/30">Total a reservar</p>
                    <p class="mt-2 font-[var(--font-playfair)] text-5xl font-bold text-[var(--gold)]"><?= h($precioFormateado) ?></p>
                    <p class="mt-5 text-sm leading-7 text-white/42">Si todo esta correcto, confirma y guardamos la reserva en tu cuenta.</p>
                </div>

                <div>
                    <form method="post">
                        <input type="hidden" name="accion" value="confirmar">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <button type="submit" class="w-full rounded-lg bg-[var(--gold)] px-4 py-4 text-[12px] font-extrabold uppercase tracking-[0.18em] text-[var(--obsidian)] transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] disabled:cursor-not-allowed disabled:opacity-40" <?= !$disponibleAhora ? 'disabled' : '' ?>>
                            Confirmar reserva
                        </button>
                    </form>

                    <a href="<?= h($urlModificar) ?>" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-white/10 px-4 py-3 text-[11px] font-bold uppercase tracking-[0.14em] text-white/45 no-underline transition hover:border-[var(--gold)]/35 hover:text-[var(--gold)]">
                        Cambiar seleccion
                    </a>
                </div>
            </div>
        </aside>
    </section>
</main>
</body>
</html>