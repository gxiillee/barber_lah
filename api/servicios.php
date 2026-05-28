<?php
require_once '../clases/Servicio.php';

header('Content-Type: application/json; charset=utf-8');

$servicios = Servicio::obtenerTodos();

$servicio_json = [];
foreach ($servicios as $s) {
    $servicio_json[] = [
        'id'       => $s->getIdServicio(),
        'nombre'   => $s->getNombre(),
        'precio'   => $s->getPrecio(),
        'duracion' => $s->getDuracion(),
    ];
}
//el flag es para que tildes o ñ no rompan y lo pone bonito
echo json_encode($servicio_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);