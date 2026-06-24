<?php
/**
 * Bootstrap — Carga las variables de entorno desde .env
 * Si no hay .env, usa los valores por defecto en cada clase.
 */
require_once __DIR__ . '/vendor/autoload.php';

// Session persistente 30 días (solo si aún no arrancó)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime',   2592000);
}

// Zona horaria centralizada (España peninsular)
date_default_timezone_set('Europe/Madrid');

// Log de errores de la aplicación en archivo
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/app.log');

use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    // .env no encontrado — seguro en producción con vars de entorno reales
}

// ── Función segura de inicio de sesión + verificación de integridad ──
function iniciarSesionSegura(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['usuario'])) {
        $uid = $_SESSION['usuario']->getId();
        $pwdAtSesion = $_SESSION['pwd_updated_at'] ?? null;
        if ($pwdAtSesion !== null) {
            try {
                $c = new PDO(
                    "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
                    $_ENV['DB_USER'], $_ENV['DB_PASS'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $s = $c->prepare("SELECT password_updated_at FROM usuarios WHERE id = :id");
                $s->execute([':id' => $uid]);
                $pwdAtDB = $s->fetchColumn();
                if ($pwdAtDB !== $pwdAtSesion) {
                    session_unset();
                    session_destroy();
                }
            } catch (Exception $e) {}
        }
    }
}
