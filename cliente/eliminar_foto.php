<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Carga de dependencias ─────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Csrf.php';
require_once __DIR__ . '/../clases/FotoCliente.php';

// ── Fase 2: Sesión y control de acceso ────────────────────────────
session_start();

if (!isset($_SESSION['usuario'])) {
    $_SESSION['volver_panel'] = 'index.php';
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario    = $_SESSION['usuario'];
$id_usuario = (int) $usuario->getId();

if ($usuario->tieneRolAdmin()) {
    redirigir('../admin/index.php');
}

// ── Fase 3: Solo se permite POST ─────────────────────────────────
// GET directo a esta URL no hace nada: redirige a la galería
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('fotos.php');
}

// 3.1 Validar token CSRF
if (!Csrf::validarToken('eliminar_foto', $_POST['csrf_token'] ?? '')) {
    redirigir('fotos.php');
}

// 3.2 Obtener el id de la foto
$id_foto = (int) ($_POST['id_foto'] ?? 0);
if ($id_foto <= 0) {
    redirigir('fotos.php');
}

// 3.3 Verificar que la foto existe y pertenece a este usuario
// (la query en FotoCliente::obtenerPorIdYUsuario filtra por ambos campos)
$foto = FotoCliente::obtenerPorIdYUsuario($id_foto, $id_usuario);
if ($foto === null) {
    // La foto no existe o no es suya → no hacer nada y volver
    redirigir('fotos.php');
}

// 3.4 Eliminar el archivo físico del servidor ANTES de borrar de BD
// Así si falla el unlink, al menos la BD está limpia; si falla la BD, el archivo
// no tiene referencia y no es accesible (por ruta conocida) de todas formas.
$ruta_absoluta = __DIR__ . '/../' . $foto['ruta'];
if (file_exists($ruta_absoluta)) {
    @unlink($ruta_absoluta);
}

// 3.5 Eliminar de la BD
FotoCliente::eliminar($id_foto, $id_usuario);

// 3.6 Redirigir de vuelta a la galería
redirigir('fotos.php');