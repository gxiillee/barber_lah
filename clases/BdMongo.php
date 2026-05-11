<?php
require_once __DIR__ . '/../vendor/autoload.php';

class ConexionMongo {
    private static $cliente;
    private static $db;

    public static function conectar() {
        if (!isset(self::$cliente)) {
            // Pon AQUÍ tu IP fija de clase o casa
            $ip_bd = "192.168.4.17";

            // Usamos la IP en lugar de localhost para que todos apunten al mismo sitio
            try {
                self::$cliente = new MongoDB\Client("mongodb://$ip_bd:27017/");
                self::$db = self::$cliente->barberlah;
            } catch (Exception $e) {
                die("Error de conexión a MongoDB: " . $e->getMessage());
            }
        }
        return self::$db;
    }
}