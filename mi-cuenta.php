<?php
declare(strict_types=1);

// Carga de dependencias
require_once __DIR__ . '/clases/helpers.php';
require_once __DIR__ . '/clases/Usuario.php';

// Inicialización de la sesión
session_start();

// Validar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    redirigir('login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];

// Enrutamiento basado en el rol del usuario
if ($usuario->tieneRolAdmin()) {
    redirigir('admin/index.php');
} else {
    redirigir('cliente/index.php');
}