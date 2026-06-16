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
$fecha_volver = isset($_GET['fecha']) ? preg_replace('/[^0-9\-]/', '', $_GET['fecha']) : '';

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
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Cita marcada como completada.'];
                redirigir('ficha_cliente.php?id_reserva=' . $id_reserva . ($fecha_volver ? '&fecha=' . $fecha_volver : ''));
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
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Reserva marcada como no presentado.'];
                redirigir('ficha_cliente.php?id_reserva=' . $id_reserva . ($fecha_volver ? '&fecha=' . $fecha_volver : ''));
            } else {
                $mensaje_error = 'No se pudo actualizar el estado.';
            }
        }

        if ($accion === 'cancelar_cita') {
            $id_res = (int)($_POST['id_reserva_accion'] ?? 0);
            $motivo = trim($_POST['motivo_cancelacion'] ?? '');
            if ($motivo === '') {
                $mensaje_error = 'Debes indicar el motivo de la cancelación.';
            } else {
                $ok = Reserva::cancelar($id_res, $motivo);
                if ($ok) {
                    $reserva_actual = Reserva::obtenerPorId($id_reserva);
                    $historial = Reserva::obtenerHistorialPorCliente($id_cliente);
                    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Reserva cancelada con motivo registrado.'];
                    redirigir('ficha_cliente.php?id_reserva=' . $id_reserva . ($fecha_volver ? '&fecha=' . $fecha_volver : ''));
                } else {
                    $mensaje_error = 'No se pudo cancelar la reserva.';
                }
            }
        }

        if ($accion === 'actualizar_puntos') {
            $nuevos_puntos = (int)($_POST['puntos'] ?? 0);
            if (Usuario::actualizarPuntos($id_cliente, $nuevos_puntos)) {
                $cliente = Cliente::obtenerPorId($id_cliente);
                $_SESSION['toast'] = ['type' => 'success', 'message' => "Puntos actualizados a $nuevos_puntos."];
                redirigir('ficha_cliente.php?id_reserva=' . $id_reserva . ($fecha_volver ? '&fecha=' . $fecha_volver : ''));
            } else {
                $mensaje_error = 'No se pudieron actualizar los puntos.';
            }
        }

        if ($accion === 'actualizar_nota') {
            $nota = trim($_POST['nota'] ?? '');
            if (Usuario::actualizarNotaInterna($id_cliente, $nota)) {
                $cliente = Cliente::obtenerPorId($id_cliente);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Nota interna guardada.'];
                redirigir('ficha_cliente.php?id_reserva=' . $id_reserva . ($fecha_volver ? '&fecha=' . $fecha_volver : ''));
            } else {
                $mensaje_error = 'No se pudo guardar la nota.';
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
            'completada'    => ['label' => 'Completada',    'clase' => 'bg-[#6fcf97]/12 text-[#6fcf97] border-[#6fcf97]/30'],
            'confirmada'    => ['label' => 'Confirmada',    'clase' => 'bg-[#d4af37]/12 text-[#d4af37] border-[#d4af37]/30'],
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
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
    <style>
        .mobile-collapse { max-height: 0; overflow: hidden; transition: max-height 0.5s cubic-bezier(0.4,0,0.2,1), opacity 0.35s ease; opacity: 0.4; }
        .mobile-collapse.expanded { max-height: 2000px; opacity: 1; }
        @media (min-width: 1024px) { .mobile-collapse { max-height: none !important; overflow: visible !important; opacity: 1 !important; } }
    </style>
</head>
<body class="pagina-admin bg-[#0d0d0d] text-[#f5f0e8] font-['Montserrat'] min-h-screen">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="panel-main">
    <div class="max-w-[1100px] mx-auto px-4 sm:px-6 pt-[92px] sm:pt-6 pb-16 sm:pb-20 flex flex-col gap-6">

        <header class="bg-white/[0.025] border border-white/[0.08] rounded-2xl p-5 sm:p-8 animate-[ficha-entrar_0.45s_cubic-bezier(0.16,1,0.3,1)_both]">

            <a href="index.php<?= $fecha_volver ? '?fecha=' . h($fecha_volver) : '' ?>" class="inline-flex items-center gap-2 px-3.5 py-2 mb-5 rounded-full bg-white/5 border border-white/10 font-['Montserrat'] text-[0.7rem] sm:text-[0.75rem] font-semibold tracking-wider uppercase text-[#aaaaaa] transition-all duration-200 hover:bg-white/10 hover:text-[#d4af37] w-fit">
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
                            <?php if ($cliente->getTelefono()): ?>
                                <a href="https://wa.me/34<?= h(preg_replace('/\D/', '', $cliente->getTelefono())) ?>?text=<?= rawurlencode('Hola ' . $cliente->getNombre() . ', soy Hassan de Barbershop La H.') ?>" target="_blank" class="hover:opacity-80 transition-opacity inline-flex items-center gap-1" title="Enviar WhatsApp">
                                    <?= h($cliente->getTelefono()) ?>
                                    <i class="bi bi-whatsapp text-[#25D366] text-[0.9rem]"></i>
                                </a>
                            <?php else: ?>
                                Sin teléfono
                            <?php endif; ?>
                        </p>
                        <?php if ($cliente->getCreatedAt()): ?>
                            <p class="font-['Montserrat'] text-[0.7rem] text-[#888] m-0 mt-1 flex items-center gap-1.5">
                                <i class="bi bi-calendar3 text-[#d4af37] text-[0.75rem]"></i>
                                Cliente desde <?= h(fechaHumana(substr($cliente->getCreatedAt(), 0, 10))) ?>
                            </p>
                        <?php endif; ?>
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
                            <a href="https://wa.me/34<?= h(preg_replace('/\D/', '', $cliente->getTelefono())) ?>?text=<?= rawurlencode('Hola ' . $cliente->getNombre() . ', soy Hassan de Barbershop La H.') ?>" target="_blank" class="hover:opacity-80 transition-opacity inline-flex items-center gap-1.5" title="Enviar WhatsApp">
                                <?= h($cliente->getTelefono()) ?>
                                <i class="bi bi-whatsapp text-[#25D366] text-[0.95rem]"></i>
                            </a>
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

                            <button type="button" onclick="abrirCancelarCita()"
                                    class="w-full md:w-auto inline-flex items-center justify-center gap-[0.45rem] font-['Montserrat'] text-[0.75rem] font-semibold tracking-[0.06em] rounded-lg px-[1.1rem] py-3 md:py-[0.55rem] cursor-pointer transition-all duration-150 bg-white/5 text-[#e07070] border border-red-900/30 hover:border-red-500/50 hover:bg-red-900/10">
                                <i class="bi bi-x-lg"></i>
                                Cancelar cita
                            </button>

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
                            <?php if ($reserva_actual['estado'] === 'cancelada' && !empty($reserva_actual['motivo_cancelacion'])): ?>
                                <div class="flex items-start gap-1.5 mt-1.5 max-w-[220px]">
                                    <i class="bi bi-chat-quote text-[#e07070] text-[0.65rem] mt-0.5 shrink-0"></i>
                                    <span class="font-['Montserrat'] text-[0.65rem] text-[#e07070]/80 leading-relaxed"><?= h($reserva_actual['motivo_cancelacion']) ?></span>
                                </div>
                            <?php endif; ?>
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

        <!-- Nota interna (solo visible para admin) -->
        <div class="bg-white/[0.025] border border-white/[0.08] rounded-2xl p-4 sm:p-5 animate-[ficha-entrar_0.45s_cubic-bezier(0.16,1,0.3,1)_both]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="bi bi-journal-text text-[#d4af37] text-[0.9rem] shrink-0"></i>
                        <span class="font-['Montserrat'] text-[0.65rem] font-semibold tracking-[0.2em] uppercase text-[#666666]">Nota interna</span>
                    </div>
                    <?php if ($cliente->getNotaInterna()): ?>
                        <div class="font-['Montserrat'] text-[0.8rem] text-[#f5f0e8] leading-relaxed whitespace-pre-wrap break-words max-h-24 overflow-y-auto pr-1">
                            <?= h($cliente->getNotaInterna()) ?>
                        </div>
                    <?php else: ?>
                        <div class="font-['Montserrat'] text-[0.8rem] text-[#555] italic">Sin nota aún</div>
                    <?php endif; ?>
                </div>
                <button onclick="abrirEditarNota()"
                        class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[0.6rem] font-semibold uppercase tracking-wider border border-white/[0.08] text-[#888] hover:bg-[#d4af37]/10 hover:text-[#d4af37] hover:border-[#d4af37]/30 transition-all cursor-pointer whitespace-nowrap mt-0.5">
                    <i class="bi bi-pencil"></i>
                    <?= $cliente->getNotaInterna() ? 'Editar' : 'Añadir' ?>
                </button>
            </div>
        </div>

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
                <?php if ($total_no_presento > 0): ?>
                    <span class="font-['Montserrat'] text-[0.55rem] text-[#888888] mt-[-0.1rem] flex items-center gap-1">
                        <i class="bi bi-person-x text-[0.55rem]"></i> <?= $total_no_presento ?> no show
                    </span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col items-center gap-1 sm:gap-[0.35rem] py-5 sm:py-6 px-3 sm:px-4 rounded-[0.875rem] border border-white/[0.08] bg-white/[0.025] text-center transition-all duration-200 hover:border-[#d4af37]/25 hover:-translate-y-[2px] animate-[ficha-entrar_0.5s_cubic-bezier(0.16,1,0.3,1)_both] delay-[200ms] relative group"
                 id="puntos-card">
                <i class="bi bi-star text-[#d4af37] text-lg sm:text-[1.25rem] mb-[0.1rem]"></i>
                <span class="font-['Playfair_Display'] text-3xl sm:text-[2rem] font-bold leading-none text-[#f5f0e8]" id="puntos-valor"><?= (int)$cliente->getPuntosFidelidad() ?></span>
                <span class="font-['Montserrat'] text-[0.6rem] sm:text-[0.65rem] font-semibold tracking-[0.18em] uppercase text-[#666666]">Puntos</span>
                <button onclick="abrirEditarPuntos()"
                        class="absolute top-2 right-2 w-6 h-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[#888] hover:bg-[#d4af37]/20 hover:text-[#d4af37] hover:border-[#d4af37]/30 transition-all opacity-0 group-hover:opacity-100 cursor-pointer"
                        title="Editar puntos">
                    <i class="bi bi-pencil text-[0.6rem]"></i>
                </button>
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
                    <button type="button" id="btnToggleHistorial" onclick="toggleHistorial()"
                            class="lg:!hidden ml-auto flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[0.6rem] font-semibold tracking-wider border border-white/[0.08] text-[#888] hover:text-[#d4af37] hover:border-[#d4af37]/30 transition-all cursor-pointer">
                        <i class="bi bi-chevron-down text-[0.65rem]" id="iconToggleHistorial"></i>
                        <span id="labelToggleHistorial">Mostrar</span>
                    </button>
                </h2>

                <div id="historialCollapse" class="mobile-collapse lg:!max-h-none lg:!overflow-visible">
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
                </div><!-- /historialCollapse -->
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

<!-- Modal editar puntos -->
<div id="modalPuntos" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200" onclick="if(event.target===this)cerrarEditarPuntos()">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl p-6 w-full max-w-sm shadow-2xl scale-95 transition-transform duration-200" id="modalPuntosContent">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8]">Editar Puntos</h3>
            <button onclick="cerrarEditarPuntos()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
            <input type="hidden" name="accion" value="actualizar_puntos">

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Puntos de fidelidad</label>
                <input type="number" name="puntos" id="inputPuntos" min="0" required
                       class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all">
            </div>

            <p class="font-['Montserrat'] text-[0.65rem] text-[#888] leading-relaxed flex items-start gap-1.5">
                <i class="bi bi-info-circle text-[#d4af37] mt-0.5 shrink-0"></i>
                Útil para corregir puntos si el sistema no los asignó correctamente.
            </p>

            <div class="flex gap-3 pt-1">
                <button type="button" onclick="cerrarEditarPuntos()"
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

<!-- Modal editar nota interna -->
<div id="modalNota" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200" onclick="if(event.target===this)cerrarEditarNota()">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl p-6 w-full max-w-md shadow-2xl scale-95 transition-transform duration-200" id="modalNotaContent">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8]">Nota interna</h3>
            <button onclick="cerrarEditarNota()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
            <input type="hidden" name="accion" value="actualizar_nota">

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Observaciones (solo visible para ti)</label>
                <textarea name="nota" id="inputNota" rows="3"
                          placeholder="Ej: Prefiere navaja, alérgico a X, paga en efectivo..."
                          class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all resize-none"></textarea>
            </div>

            <p class="font-['Montserrat'] text-[0.65rem] text-[#888] leading-relaxed flex items-start gap-1.5">
                <i class="bi bi-info-circle text-[#d4af37] mt-0.5 shrink-0"></i>
                Esta nota solo la ves tú. El cliente no la ve en su panel.
            </p>

            <div class="flex gap-3 pt-1">
                <button type="button" onclick="cerrarEditarNota()"
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

<!-- Modal cancelar cita -->
<div id="modalCancelar" class="fixed inset-0 z-[9999] bg-black/80 hidden items-center justify-center p-4 opacity-0 transition-opacity duration-200" onclick="if(event.target===this)cerrarCancelarCita()">
    <div class="bg-[#1a1a1a] border border-white/[0.08] rounded-2xl p-6 w-full max-w-md shadow-2xl scale-95 transition-transform duration-200" id="modalCancelarContent">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-['Playfair_Display'] text-[1rem] font-semibold text-[#f5f0e8]">Cancelar cita</h3>
            <button onclick="cerrarCancelarCita()" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#888] hover:bg-white/10 hover:text-[#f5f0e8] transition-all cursor-pointer">
                <i class="bi bi-x-lg text-[0.8rem]"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= h($token_csrf) ?>">
            <input type="hidden" name="accion" value="cancelar_cita">
            <input type="hidden" name="id_reserva_accion" value="<?= $id_reserva ?>">

            <div>
                <label class="font-['Montserrat'] text-[0.65rem] font-semibold uppercase tracking-wider text-[#888] block mb-1.5">Motivo de cancelación</label>
                <textarea name="motivo_cancelacion" id="inputMotivoCancelar" rows="3" required
                          placeholder="Ej: Cliente llamó para cancelar, no respondió mensajes..."
                          class="w-full bg-[#0d0d0d] border border-white/[0.08] rounded-lg px-3 py-2.5 text-[0.85rem] text-[#f5f0e8] focus:outline-hidden focus:border-[#d4af37]/50 transition-all resize-none"></textarea>
            </div>

            <p class="font-['Montserrat'] text-[0.65rem] text-[#888] leading-relaxed flex items-start gap-1.5">
                <i class="bi bi-info-circle text-[#d4af37] mt-0.5 shrink-0"></i>
                El motivo se guardará para el historial. Pendiente: notificar al cliente vía WhatsApp.
            </p>

            <div class="flex gap-3 pt-1">
                <button type="button" onclick="cerrarCancelarCita()"
                        class="flex-1 px-4 py-2.5 rounded-lg border border-white/[0.08] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider text-[#888] hover:bg-white/5 transition-all cursor-pointer">
                    Volver
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-red-800 text-[#f5f0e8] font-['Montserrat'] text-[0.7rem] font-semibold tracking-wider uppercase hover:opacity-90 transition-all cursor-pointer">
                    Cancelar cita
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirEditarPuntos() {
    const modal = document.getElementById('modalPuntos');
    const content = document.getElementById('modalPuntosContent');
    const input = document.getElementById('inputPuntos');
    const valor = document.getElementById('puntos-valor');
    input.value = valor.textContent.trim();
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}

function cerrarEditarPuntos() {
    const modal = document.getElementById('modalPuntos');
    const content = document.getElementById('modalPuntosContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function abrirEditarNota() {
    const modal = document.getElementById('modalNota');
    const content = document.getElementById('modalNotaContent');
    const input = document.getElementById('inputNota');
    input.value = '<?= h(addslashes($cliente->getNotaInterna() ?? '')) ?>';
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}

function cerrarEditarNota() {
    const modal = document.getElementById('modalNota');
    const content = document.getElementById('modalNotaContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function abrirCancelarCita() {
    const modal = document.getElementById('modalCancelar');
    const content = document.getElementById('modalCancelarContent');
    const input = document.getElementById('inputMotivoCancelar');
    input.value = '';
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });
}

function cerrarCancelarCita() {
    const modal = document.getElementById('modalCancelar');
    const content = document.getElementById('modalCancelarContent');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function toggleHistorial() {
    const wrapper = document.getElementById('historialCollapse');
    const btn = document.getElementById('btnToggleHistorial');
    const icon = document.getElementById('iconToggleHistorial');
    const label = document.getElementById('labelToggleHistorial');
    const isExpanded = wrapper.classList.contains('expanded');
    if (isExpanded) {
        wrapper.classList.remove('expanded');
        icon.className = 'bi bi-chevron-down text-[0.65rem]';
        label.textContent = 'Mostrar';
    } else {
        wrapper.classList.add('expanded');
        icon.className = 'bi bi-chevron-up text-[0.65rem]';
        label.textContent = 'Ocultar';
    }
}
</script>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

</body>
</html>