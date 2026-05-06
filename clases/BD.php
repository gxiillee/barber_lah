<?php
class BD {

    private static $conexion = null;

    private function __construct() {}

    public static function obtenerConexion() {
        if (self::$conexion === null) {

            $host      = '192.168.4.17';
            $puerto    = '5432';
            $bd        = 'barberlah';
            $usuario   = 'postgres';
            $contrasena = '1234';

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