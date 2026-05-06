<?php
require_once 'clases/BD.php';

$pdo = BD::obtenerConexion();

$tablas = ['usuarios', 'barberos', 'servicios', 'horarios', 'bloqueos', 'reservas'];

foreach ($tablas as $tabla) {
    echo "\n=== $tabla ===\n";

    $filas = $pdo->query("SELECT * FROM $tabla")->fetchAll();

    if (empty($filas)) {
        echo "(vacía)\n";
        continue;
    }

    // Cabecera con los nombres de columnas
    echo implode(' | ', array_keys($filas[0])) . "\n";
    echo str_repeat('-', 80) . "\n";

    // Filas de datos
    foreach ($filas as $fila) {
        echo implode(' | ', array_map(fn($v) => $v ?? 'NULL', $fila)) . "\n";
    }
}
