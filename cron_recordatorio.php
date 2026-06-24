<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Acceso denegado');
}

// ─── Parse --today / --tomorrow / --autocomplete ───
$modo = 'tomorrow';
foreach ($argv as $arg) {
    if ($arg === '--today')        { $modo = 'today'; break; }
    if ($arg === '--tomorrow')     { $modo = 'tomorrow'; break; }
    if ($arg === '--autocomplete') { $modo = 'autocomplete'; break; }
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/clases/BD.php';
require_once __DIR__ . '/clases/helpers.php';
require_once __DIR__ . '/clases/Usuario.php';
require_once __DIR__ . '/clases/Cliente.php';
require_once __DIR__ . '/clases/Reserva.php';

// ─── Auto-completar citas pasadas ───
Reserva::actualizarCitasPasadas();
echo '[' . date('Y-m-d H:i:s') . "] Citas pasadas actualizadas." . PHP_EOL;

if ($modo === 'autocomplete') {
    exit;
}

require_once __DIR__ . '/clases/NotificadorReserva.php';

$conexion   = BD::obtenerConexion();
$esHoy      = $modo === 'today';
$fechaObj   = $esHoy ? 'today' : '+1 day';
$fechaLabel = $esHoy ? 'hoy' : 'mañana';
$fechaSQL   = date('Y-m-d', strtotime($fechaObj));
$colCheck   = $esHoy ? 'recordatorio_hoy_enviado' : 'recordatorio_enviado';

$stmt = $conexion->prepare("
    SELECT r.id, r.hora, r.id_cliente, u.nombre, u.email, s.nombre AS servicio_nombre
    FROM reservas r
    JOIN servicios s ON r.id_servicio = s.id
    JOIN usuarios u ON r.id_cliente = u.id AND u.activo = 1
    WHERE r.fecha = :fecha
      AND r.estado NOT IN ('cancelada', 'no_presentado')
      AND ($colCheck IS NULL OR $colCheck = FALSE)
");
$stmt->execute([':fecha' => $fechaSQL]);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($citas)) {
    echo '[' . date('Y-m-d H:i:s') . "] No hay citas pendientes de notificar para $fechaLabel ($fechaSQL)." . PHP_EOL;
    exit;
}

$enviados = 0;
$errores  = 0;
$stmtMark = $conexion->prepare("UPDATE reservas SET $colCheck = TRUE WHERE id = :id");

foreach ($citas as $cita) {
    $cliente = new Usuario(
        (int)$cita['id_cliente'], null,
        $cita['nombre'], $cita['email'],
        null, null, null, 0, 'cliente'
    );

    $ok = NotificadorReserva::enviarRecordatorio($cliente, [
        'servicio'          => $cita['servicio_nombre'],
        'fecha_humana'      => fechaHumana($fechaSQL),
        'fecha'             => $fechaSQL,
        'hora'              => $cita['hora'],
        'nombre_servicio'   => $cita['servicio_nombre'],
    ], $fechaLabel);

    if ($ok) {
        $stmtMark->execute([':id' => $cita['id']]);
        $enviados++;
    } else {
        echo "Error al enviar recordatorio para reserva #{$cita['id']}." . PHP_EOL;
        $errores++;
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Recordatorios ($fechaLabel): $enviados enviados, $errores errores." . PHP_EOL;
