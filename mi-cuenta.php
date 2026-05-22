<?php
declare(strict_types=1);

// ── Fase 1: Carga de dependencias necesarias ─────────────────────
require_once __DIR__ . '/clases/helpers.php';
require_once __DIR__ . '/clases/Usuario.php';

// ── Fase 2: Sesión y control de acceso ────────────────────────────
session_start();

// Si ni siquiera hay sesión iniciada, va directo al login
if (!isset($_SESSION['usuario'])) {
    redirigir('login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];

// ── Fase 3: El Puente (Redirección según el rol del usuario) ─────
if ($usuario->tieneRolAdmin()) {
    // Si es administrador, lo mandamos a su panel
    redirigir('admin/index.php');
} else {
    // Si es un cliente normal, lo mandamos a su área de cliente
    redirigir('cliente/index.php');
}