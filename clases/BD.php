<?php
require_once __DIR__ . '/../bootstrap.php';

class BD {
    private static $conexion = null;
    private static $ejecutandoCron = false;
    private function __construct() {}

    public static function obtenerConexion() {
        if (self::$conexion === null) {
            $host      = $_ENV['DB_HOST'] ?? 'localhost';
            $puerto    = $_ENV['DB_PORT'] ?? '5432';
            $bd        = $_ENV['DB_NAME'] ?? 'barberlah';
            $usuario   = $_ENV['DB_USER'] ?? 'postgres';
            $contrasena = $_ENV['DB_PASS'] ?? '';

            $dsn = "pgsql:host=$host;port=$puerto;dbname=$bd";

            try {
                self::$conexion = new PDO($dsn, $usuario, $contrasena);
                self::$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Error de conexión: " . $e->getMessage());
            }
        }
        return self::$conexion;
    }
}
