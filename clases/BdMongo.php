<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Si usas Composer

class ConexionMongo {
    private static $cliente;
    private static $db;

    public static function conectar() {
        if (!isset(self::$cliente)) {
            // Cambia la URI si tu Mongo tiene user/pass o está en la nube (Atlas)
            self::$cliente = new MongoDB\Client("mongodb://localhost:27017/");
            self::$db = self::$cliente->barberlah;
        }
        return self::$db;
    }
}