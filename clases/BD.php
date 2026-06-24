<?php
require_once __DIR__ . '/../bootstrap.php';

class BD {
    private static $conexion = null;
    private static $ejecutandoCron = false;
    private function __construct() {}

    public static function obtenerConexion() {
        if (self::$conexion === null) {
            $host      = $_ENV['DB_HOST'] ?? 'localhost';
            $puerto    = $_ENV['DB_PORT'] ?? '3306';
            $bd        = $_ENV['DB_NAME'] ?? 'barberlah';
            $usuario   = $_ENV['DB_USER'] ?? 'root';
            $contrasena = $_ENV['DB_PASS'] ?? '';

            $dsn = "mysql:host=$host;port=$puerto;dbname=$bd;charset=utf8mb4";

            try {
                self::$conexion = new PDO($dsn, $usuario, $contrasena, [
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]);
                self::$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$conexion->exec("SET time_zone = '" . date('P') . "'");
            } catch (PDOException $e) {
                error_log("Error de conexión BD: " . $e->getMessage());
                die("Error de conexión. Inténtalo de nuevo más tarde.");
            }
        }
        return self::$conexion;
    }
}
