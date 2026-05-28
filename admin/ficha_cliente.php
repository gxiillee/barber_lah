<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

/* =====================================================================
 * FASE 1 — DEPENDENCIAS
 * ===================================================================== */
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Cliente.php';
require_once __DIR__ . '/../clases/Reserva.php';
require_once __DIR__ . '/../clases/FotoCliente.php';
require_once __DIR__ . '/../clases/Csrf.php';

/* =====================================================================
 * FASE 2 — SESIÓN Y ACCESO ADMIN
 * ===================================================================== */
session_start();

if (!isset($_SESSION['usuario'])) {
    redirigir('../login.php');
}

if (!$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../index.php');
}

/* =====================================================================
 * FASE 3 — OBTENER RESERVA POR ID (desde URL)
 * ===================================================================== */
$id_reserva = isset($_GET['id_reserva']) ? (int)$_GET['id_reserva'] : 0;

if ($id_reserva === 0) {
    redirigir('index.php');
}

$reserva_actual = Reserva::obtenerPorId($id_reserva);

if ($reserva_actual === null) {
    redirigir('index.php');
}

$id_cliente = (int)$reserva_actual['id_cliente'];

/* =====================================================================
 * FASE 4 — CARGAR DATOS DEL CLIENTE
 * ===================================================================== */
$cliente = Cliente::obtenerPorId($id_cliente);

if ($cliente === null) {
    redirigir('index.php');
}

// Stats de reservas del cliente
$total_completadas  = Reserva::contarPorEstadoYCliente($id_cliente, 'completada');
$total_confirmadas  = Reserva::contarPorEstadoYCliente($id_cliente, 'confirmada');
$total_canceladas   = Reserva::contarPorEstadoYCliente($id_cliente, 'cancelada');
$total_no_presento  = Reserva::contarPorEstadoYCliente($id_cliente, 'no_presentado');
$ultima_visita      = Reserva::obtenerUltimaCompletadaPorCliente($id_cliente);

$admin_avatar = $_SESSION['usuario']->getAvatar();
// Historial completo de reservas del cliente
$historial = Reserva::obtenerHistorialPorCliente($id_cliente);

// Fotos del cliente (vista lectura para admin)
$fotos = FotoCliente::obtenerPorUsuario($id_cliente);

/* =====================================================================
 * FASE 5 — POST: marcar reserva como completada
 * ===================================================================== */
$mensaje_exito = null;
$mensaje_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if (!Csrf::validarToken('ficha_cliente', $_POST['csrf_token'] ?? '')) {
        $mensaje_error = 'Token de seguridad inválido. Recarga la página.';
    } else {

        $accion = $_POST['accion'];

        if ($accion === 'marcar_completada') {
            $id_res = (int)($_POST['id_reserva_accion'] ?? 0);
            $ok = Reserva::marcarComoCompletada($id_res);
            if ($ok) {
                $reserva_actual = Reserva::obtenerPorId($id_reserva);
                $total_completadas = Reserva::contarPorEstadoYCliente($id_cliente, 'completada');
                $historial = Reserva::obtenerHistorialPorCliente($id_cliente);
                $ultima_visita = Reserva::obtenerUltimaCompletadaPorCliente($id_cliente);
                $mensaje_exito = 'Cita marcada como completada correctamente.';
            } else {
                $mensaje_error = 'No se pudo actualizar el estado de la cita.';
            }
        }

        if ($accion === 'marcar_no_presentado') {
            $id_res = (int)($_POST['id_reserva_accion'] ?? 0);
            $ok = Reserva::marcarComoNoPresentado($id_res);
            if ($ok) {
                $reserva_actual = Reserva::obtenerPorId($id_reserva);
                $historial = Reserva::obtenerHistorialPorCliente($id_cliente);
                $mensaje_exito = 'Reserva marcada como no presentado.';
            } else {
                $mensaje_error = 'No se pudo actualizar el estado.';
            }
        }
    }
}

$token_csrf = Csrf::generarToken('ficha_cliente');

/* =====================================================================
 * HELPERS LOCALES
 * ===================================================================== */
function badgeEstado(string $estado): string {
    $base_class = "font-['Montserrat'] text-[0.6rem] font-bold tracking-[0.1em] uppercase px-[0.55rem] py-[0.2rem] rounded-full whitespace-nowrap border inline-block";

    $mapa = [
            'confirmada'    => ['label' => 'Confirmada',    'clase' => 'bg-[#6fcf97]/12 text-[#6fcf97] border-[#6fcf97]/30'],
            'completada'    => ['label' => 'Completada',    'clase' => 'bg-[#d4af37]/12 text-[#d4af37] border-[#d4af37]/30'],
            'cancelada'     => ['label' => 'Cancelada',     'clase' => 'bg-[#e07070]/10 text-[#e07070] border-[#e07070]/25'],
            'no_presentado' => ['label' => 'No se presentó','clase' => 'bg-white/5 text-[#888888] border-white/10'],
    ];
    $info = $mapa[$estado] ?? ['label' => ucfirst($estado), 'clase' => 'bg-white/5 text-[#888888] border-white/10'];
    return '<span class="' . $base_class . ' ' . $info['clase'] . '">' . h($info['label']) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Cliente — <?= h($cliente->getNombre()) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-admin bg-[#0d0d0d] text-[#f5f0e8] font-['Montserrat'] min-h-screen">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="panel-main">
    <div class="max-w-[1100px] mx-auto px-4 sm:px-6 pt-[92px] sm:pt-6 pb-16 sm:pb-20 flex flex-col gap-6">

        <header class="bg-white/[0.025] border border-white/[0.08] rounded-2xl p-5 sm:p-8 animate-[ficha-entrar_0.45s_cubic-bezier(0.16,1,0.3,1)_both]">

            <a href="index.php" class="inline-flex items-center gap-2 px-3.5 py-2 mb-5 rounded-full bg-white/5 border border-white/10 font-['Montserrat'] text-[0.7rem] sm:text-[0.75rem] font-semibold tracking-wider uppercase text-[#aaaaaa] transition-all duration-200 hover:bg-white/10 hover:text-[#d4af37] w-fit">
                <i class="bi bi-arrow-left"></i>
                Volver a la agenda
            </a>

            <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr] md:grid-cols-[auto_1fr_auto] gap-5 sm:gap-7 items-start">
                <div class="flex items-center gap-4 sm:contents">
                    <div class="relative shrink-0">
                        <?php if ($cliente->getAvatar()): ?>
                            <img src="<?= h($cliente->getAvatar()) ?>" alt="Avatar" class="w-16 h-16 sm:w-20 sm:h-20 rounded-full border-2 border-[#d4af37]/35 object-cover">
                        <?php else: ?>
                            <span class="flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-[#d4af37]/10 border-2 border-[#d4af37]/35 font-['Playfair_Display'] text-2xl sm:text-3xl text-[#d4af37]">
                            <?= mb_strtoupper(mb_substr($cliente->getNombre(), 0, 1)) ?>
                        </span>
                        <?php endif; ?>
                        <span class="absolute bottom-[2px] right-[2px] w-5 h-5 sm:w-[22px] sm:h-[22px] rounded-full bg-[#d4af37] text-[#0d0d0d] text-[0.65rem] sm:text-[0.7rem] flex items-center justify-center border-2 border-[#0d0d0d]">
                        <i class="bi bi-person-check-fill"></i>
                        </span>
                    </div>

                    <div class="flex flex-col sm:hidden">
                        <h1 class="font-['Playfair_Display'] text-xl font-bold text-[#f5f0e8] m-0 mb-1 leading-tight">
                            <?= h($cliente->getNombre()) ?>
                        </h1>
                        <p class="font-['Montserrat'] text-[0.75rem] text-[#aaaaaa] m-0 flex items-center gap-1.5">
                            <i class="bi bi-telephone text-[#d4af37]"></i>
                            <?= h($cliente->getTelefono() ?? 'Sin teléfono') ?>
                        </p>
                    </div>
                </div>

                <div class="hidden sm:flex flex-col">
                    <h1 class="font-['Playfair_Display'] text-2xl md:text-3xl font-bold text-[#f5f0e8] m-0 mb-2 leading-[1.2]">
                        <?= h($cliente->getNombre()) ?>
                    </h1>
                    <p class="font-['Montserrat'] text-[0.8rem] text-[#aaaaaa] my-1 flex items-center gap-[0.4rem]">
                        <i class="bi bi-envelope text-[#d4af37] text-[0.85rem]"></i>
                        <?= h($cliente->getEmail()) ?>
                    </p>
                    <?php if ($cliente->getTelefono()): ?>
                        <p class="font-['Montserrat'] text-[0.8rem] text-[#aaaaaa] my-1 flex items-center gap-[0.4rem]">
                            <i class="bi bi-telephone text-[#d4af37] text-[0.85rem]"></i>
                            <?= h($cliente->getTelefono()) ?>
                        </p>
                    <?php endif; ?>
                    <p class="font-['Montserrat'] text-[0.8rem] text-[#aaaaaa] my-1 flex items-center gap-[0.4rem]">
                        <i class="bi bi-calendar3 text-[#d4af37] text-[0.85rem]"></i>
                        Cliente desde <?= h(fechaHumana(substr($cliente->getCreatedAt(), 0, 10))) ?>
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row md:flex-col gap-4 items-stretch sm:items-center md:items-end w-full md:w-auto mt-2 sm:mt-0">
                    <?php if ($ultima_visita): ?>
                        <div class="flex flex-col items-center sm:items-start md:items-end gap-[0.15rem] bg-white/5 sm:bg-transparent rounded-lg p-3 sm:p-0">
                            <span class="font-['Montserrat'] text-[0.65rem] font-semibold tracking-[0.2em] uppercase text-[#666666]">Última visita</span>
                            <span class="font-['Montserrat'] text-[0.9rem] font-semibold text-[#f5f0e8] text-center md:text-right"><?= h(fechaHumana($ultima_visita['fecha'])) ?></span>
                            <span class="font-['Montserrat'] text-[0.75rem] text-[#d4af37] text-center md:text-right"><?= h($ultima_visita['nombre_servicio'] ?? '—') ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($reserva_actual['estado'] === 'confirmada'): ?>
                        <div class="flex flex-col gap-2 w-full">
                            <div class="text-center md:text-right">
                                <p class="font-['Montserrat'] text-[0.65rem] font-semibold tracking-[0.2em] uppercase text-[#666666] m-0">Cita de hoy — <?= h($reserva_actual['hora']) ?></p>
                                <p class="font-['Montserrat'] text-[0.75rem] text-[#d4af37] m-0"><?= h($reserva_actual['nombre_servicio'] ?? '—') ?></p>
                            </div>

                            <form method="POST" class="w-full mt-1">
                                <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
                                <input type="hidden" name="accion" value="marcar_completada">
                                <input type="hidden" name="id_reserva_accion" value="<?= $id_reserva ?>">
                                <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center gap-[0.45rem] font-['Montserrat'] text-[0.75rem] font-semibold tracking-[0.06em] rounded-lg px-[1.1rem] py-3 md:py-[0.55rem] cursor-pointer transition-all duration-150 bg-[#d4af37] text-[#0d0d0d] border-none hover:-translate-y-[1px] hover:opacity-90"
                                        onclick="return confirm('¿Marcar la cita como completada?')">
                                    <i class="bi bi-check-circle"></i>
                                    Marcar completada
                                </button>
                            </form>

                            <form method="POST" class="w-full">
                                <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
                                <input type="hidden" name="accion" value="marcar_no_presentado">
                                <input type="hidden" name="id_reserva_accion" value="<?= $id_reserva ?>">
                                <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center gap-[0.45rem] font-['Montserrat'] text-[0.75rem] font-semibold tracking-[0.06em] rounded-lg px-[1.1rem] py-3 md:py-[0.55rem] cursor-pointer transition-all duration-150 bg-white/5 text-[#aaaaaa] border border-white/10 hover:border-white/20 hover:text-[#f5f0e8]"
                                        onclick="return confirm('¿Marcar como no presentado?')">
                                    <i class="bi bi-x-circle"></i>
                                    No se presentó
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center sm:items-start md:items-end gap-[0.4rem] w-full mt-2 sm:mt-0">
                            <span class="font-['Montserrat'] text-[0.65rem] font-semibold tracking-[0.2em] uppercase text-[#666666]">Estado de la cita</span>
                            <?= badgeEstado($reserva_actual['estado']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <?php if ($mensaje_exito): ?>
            <div class="flex items-center gap-[0.65rem] rounded-[0.625rem] px-4 sm:px-5 py-3 sm:py-[0.875rem] font-['Montserrat'] text-[0.75rem] sm:text-[0.82rem] font-medium animate-[ficha-entrar_0.35s_both] bg-[#28a745]/12 border border-[#28a745]/30 text-[#6fcf97]">
                <i class="bi bi-check-circle-fill"></i>
                <?= h($mensaje_exito) ?>
            </div>
        <?php endif; ?>
        <?php if ($mensaje_error): ?>
            <div class="flex items-center gap-[0.65rem] rounded-[0.625rem] px-4 sm:px-5 py-3 sm:py-[0.875rem] font-['Montserrat'] text-[0.75rem] sm:text-[0.82rem] font-medium animate-[ficha-entrar_0.35s_both] bg-[#dc3545]/10 border border-[#dc3545]/25 text-[#e07070]">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= h($mensaje_error) ?>
            </div>
        <?php endif; ?>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="flex flex-col items-center gap-1 sm:gap-[0.35rem] py-5 sm:py-6 px-3 sm:px-4 rounded-[0.875rem] border border-white/[0.08] bg-white/[0.025] text-center transition-all duration-200 hover:border-[#d4af37]/25 hover:-translate-y-[2px] animate-[ficha-entrar_0.5s_cubic-bezier(0.16,1,0.3,1)_both] delay-[50ms]">
                <i class="bi bi-scissors text-[#d4af37] text-lg sm:text-[1.25rem] mb-[0.1rem]"></i>
                <span class="font-['Playfair_Display'] text-3xl sm:text-[2rem] font-bold leading-none text-[#f5f0e8]"><?= $total_completadas ?></span>
                <span class="font-['Montserrat'] text-[0.6rem] sm:text-[0.65rem] font-semibold tracking-[0.18em] uppercase text-[#666666]">Visitas</span>
            </div>

            <div class="flex flex-col items-center gap-1 sm:gap-[0.35rem] py-5 sm:py-6 px-3 sm:px-4 rounded-[0.875rem] border border-white/[0.08] bg-white/[0.025] text-center transition-all duration-200 hover:border-[#d4af37]/25 hover:-translate-y-[2px] animate-[ficha-entrar_0.5s_cubic-bezier(0.16,1,0.3,1)_both] delay-[100ms]">
                <i class="bi bi-calendar-check text-[#6fcf97] text-lg sm:text-[1.25rem] mb-[0.1rem]"></i>
                <span class="font-['Playfair_Display'] text-3xl sm:text-[2rem] font-bold leading-none text-[#f5f0e8]"><?= $total_confirmadas ?></span>
                <span class="font-['Montserrat'] text-[0.6rem] sm:text-[0.65rem] font-semibold tracking-[0.18em] uppercase text-[#666666]">Próximas</span>
            </div>

            <div class="flex flex-col items-center gap-1 sm:gap-[0.35rem] py-5 sm:py-6 px-3 sm:px-4 rounded-[0.875rem] border border-white/[0.08] bg-white/[0.025] text-center transition-all duration-200 hover:border-[#d4af37]/25 hover:-translate-y-[2px] animate-[ficha-entrar_0.5s_cubic-bezier(0.16,1,0.3,1)_both] delay-[150ms]">
                <i class="bi bi-calendar-x text-[#e07070] text-lg sm:text-[1.25rem] mb-[0.1rem]"></i>
                <span class="font-['Playfair_Display'] text-3xl sm:text-[2rem] font-bold leading-none text-[#f5f0e8]"><?= $total_canceladas ?></span>
                <span class="font-['Montserrat'] text-[0.6rem] sm:text-[0.65rem] font-semibold tracking-[0.18em] uppercase text-[#666666]">Canceladas</span>
            </div>

            <div class="flex flex-col items-center gap-1 sm:gap-[0.35rem] py-5 sm:py-6 px-3 sm:px-4 rounded-[0.875rem] border border-white/[0.08] bg-white/[0.025] text-center transition-all duration-200 hover:border-[#d4af37]/25 hover:-translate-y-[2px] animate-[ficha-entrar_0.5s_cubic-bezier(0.16,1,0.3,1)_both] delay-[200ms]">
                <i class="bi bi-person-dash text-[#888888] text-lg sm:text-[1.25rem] mb-[0.1rem]"></i>
                <span class="font-['Playfair_Display'] text-3xl sm:text-[2rem] font-bold leading-none text-[#f5f0e8]"><?= $total_no_presento ?></span>
                <span class="font-['Montserrat'] text-[0.6rem] sm:text-[0.65rem] font-semibold tracking-[0.18em] uppercase text-[#666666]">No show</span>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 items-start">

            <section class="order-1 lg:order-2 bg-white/[0.025] border border-white/[0.08] rounded-2xl p-5 sm:p-6 animate-[ficha-entrar_0.55s_cubic-bezier(0.16,1,0.3,1)_both]">
                <h2 class="font-['Montserrat'] text-[0.7rem] font-bold tracking-[0.22em] uppercase text-[#666666] flex items-center gap-2 m-0 mb-4 pb-[0.875rem] border-b border-white/[0.06]">
                    <i class="bi bi-images text-[#d4af37] text-[0.9rem]"></i>
                    Fotos de referencia
                    <span class="ml-auto font-['Montserrat'] text-[0.7rem] text-[#d4af37] bg-[#d4af37]/10 px-2 py-[0.15rem] rounded-full tracking-normal font-normal"><?= count($fotos) ?>/8</span>
                </h2>

                <?php if (empty($fotos)): ?>
                    <div class="flex flex-col items-center gap-2 pt-8 pb-4 text-center">
                        <i class="bi bi-camera" style="font-size:2rem; color:var(--gold); opacity:.4;"></i>
                        <p class="font-['Montserrat'] text-[0.8rem] text-[#666666] m-0">El cliente aún no ha subido fotos.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-2 gap-2 sm:gap-[0.625rem] mb-4">
                        <?php foreach ($fotos as $foto): ?>
                            <div class="relative rounded-lg overflow-hidden aspect-square border border-white/[0.08] group">
                                <img src="../<?= h($foto['ruta']) ?>"
                                     alt="Foto de referencia"
                                     onclick="abrirVisor(this.src)"
                                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04] cursor-zoom-in"
                                     loading="lazy">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end p-2 opacity-0 transition-opacity duration-250 group-hover:opacity-100 pointer-events-none">
                                    <span class="font-['Montserrat'] text-[0.55rem] sm:text-[0.65rem] text-white/80">
                                        <?= h(fechaHumana($foto['fecha_subida'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="font-['Montserrat'] text-[0.65rem] sm:text-[0.7rem] text-[#666666] flex items-center gap-[0.35rem] mt-2 m-0 leading-tight">
                    <i class="bi bi-info-circle text-[#d4af37] opacity-60"></i>
                    El cliente gestiona estas fotos desde su app.
                </p>
            </section>

            <section class="order-2 lg:order-1 bg-white/[0.025] border border-white/[0.08] rounded-2xl p-4 sm:p-6 animate-[ficha-entrar_0.55s_cubic-bezier(0.16,1,0.3,1)_both]">
                <h2 class="font-['Montserrat'] text-[0.7rem] font-bold tracking-[0.22em] uppercase text-[#666666] flex items-center gap-2 m-0 mb-4 sm:mb-5 pb-[0.875rem] border-b border-white/[0.06]">
                    <i class="bi bi-clock-history text-[#d4af37] text-[0.9rem]"></i>
                    Historial de citas
                </h2>

                <?php if (empty($historial)): ?>
                    <p class="font-['Montserrat'] text-[0.82rem] text-[#666666] text-center py-8 m-0">Este cliente aún no tiene citas registradas.</p>
                <?php else: ?>
                    <div class="flex flex-col gap-3 sm:gap-[0.625rem]">
                        <?php foreach ($historial as $cita): ?>
                            <div class="flex flex-wrap sm:grid sm:grid-cols-[56px_1fr_auto_auto] gap-3 sm:gap-4 items-center px-3 py-3 sm:px-4 sm:py-[0.875rem] rounded-[0.625rem] border bg-white/[0.02] transition-colors duration-200 hover:bg-white/[0.04] hover:border-white/[0.08] animate-[ficha-entrar_0.4s_both] <?= $cita['id'] == $id_reserva ? 'border-[#d4af37]/30 bg-[#d4af37]/5' : 'border-transparent' ?>">
                                <div class="flex flex-col items-center text-center leading-[1.1] w-[45px] sm:w-auto shrink-0">
                                    <span class="font-['Montserrat'] text-[0.55rem] sm:text-[0.6rem] font-bold tracking-[0.1em] uppercase text-[#666666]">
                                        <?= h(nombreDiaCorto((int)date('N', strtotime($cita['fecha'])))) ?>
                                    </span>
                                    <span class="font-['Playfair_Display'] text-xl sm:text-2xl font-bold text-[#f5f0e8]">
                                        <?= h(date('d', strtotime($cita['fecha']))) ?>
                                    </span>
                                    <span class="font-['Montserrat'] text-[0.55rem] sm:text-[0.6rem] text-[#d4af37] uppercase">
                                        <?= h(nombreMesCorto((int)date('n', strtotime($cita['fecha'])))) ?>
                                    </span>
                                </div>
                                <div class="flex flex-col flex-1 min-w-[120px]">
                                    <p class="font-['Montserrat'] text-[0.8rem] sm:text-[0.85rem] font-medium text-[#f5f0e8] m-0 mb-[0.2rem] truncate"><?= h($cita['nombre_servicio'] ?? 'Servicio eliminado') ?></p>
                                    <p class="font-['Montserrat'] text-[0.7rem] sm:text-[0.72rem] text-[#666666] flex items-center gap-[0.3rem] m-0">
                                        <i class="bi bi-clock text-[0.75rem]"></i>
                                        <?= h(substr($cita['hora'], 0, 5)) ?>
                                        &middot;
                                        <?= h($cita['duracion_historica']) ?> min
                                    </p>
                                </div>
                                <div class="font-['Montserrat'] text-[0.8rem] sm:text-[0.85rem] font-semibold text-[#d4af37] whitespace-nowrap ml-auto sm:ml-0">
                                    <?= number_format((float)$cita['precio_historico'], 2, ',', '') ?> €
                                </div>
                                <div class="w-full sm:w-auto flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-[0.25rem] mt-1 sm:mt-0 pt-2 sm:pt-0 border-t border-white/5 sm:border-transparent">
                                    <?= badgeEstado($cita['estado']) ?>
                                    <?php if ($cita['id'] == $id_reserva): ?>
                                        <span class="font-['Montserrat'] text-[0.6rem] text-[#d4af37]/70">← esta cita</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </div>
</main>

<div id="visorFotos" class="fixed inset-0 z-[9999] bg-black/95 hidden items-center justify-center p-4 cursor-zoom-out opacity-0 transition-opacity duration-300" onclick="cerrarVisor()">
    <img id="visorImg" src="" alt="Foto ampliada" class="max-w-full max-h-full rounded-lg object-contain shadow-2xl scale-95 transition-transform duration-300">
</div>

<script>
    const visor = document.getElementById('visorFotos');
    const visorImg = document.getElementById('visorImg');

    function abrirVisor(src) {
        visorImg.src = src;
        visor.classList.remove('hidden');
        visor.classList.add('flex');

        requestAnimationFrame(() => {
            visor.classList.remove('opacity-0');
            visorImg.classList.remove('scale-95');
            visorImg.classList.add('scale-100');
        });
    }

    function cerrarVisor() {
        visor.classList.add('opacity-0');
        visorImg.classList.remove('scale-100');
        visorImg.classList.add('scale-95');

        setTimeout(() => {
            visor.classList.add('hidden');
            visor.classList.remove('flex');
        }, 300);
    }
</script>

</body>
</html>