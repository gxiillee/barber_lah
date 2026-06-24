<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Reserva.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$idReserva  = (int)($_GET['id_reserva'] ?? 0);
$fecha      = $_GET['fecha'] ?? '';

if ($idReserva <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$reserva = Reserva::obtenerPorId($idReserva);
if ($reserva === null) {
    echo json_encode(['ok' => false, 'error' => 'Reserva no encontrada']);
    exit;
}

// Si no se pasa fecha, usar la fecha actual de la reserva
if (!esFechaValida($fecha)) {
    $fecha = $reserva['fecha'];
}

$idBarbero = (int)$reserva['id_barbero'];
$duracion  = (int)$reserva['duracion_historica'];

$horaActual = substr((string)$reserva['hora'], 0, 5);
$resumen    = $reserva['cliente_nombre'] . ' · ' . $reserva['servicio_nombre'] . ' (' . $duracion . ' min) · Actualmente: ' . fechaHumana($reserva['fecha']) . ' a las ' . $horaActual;

$slots = Reserva::obtenerSlotsDisponibles($idBarbero, $fecha, $duracion, 30, $idReserva);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'             => true,
    'slots'          => $slots,
    'resumen'        => $resumen,
    'reserva_fecha'  => $reserva['fecha'],
]);
