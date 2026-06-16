<?php
require_once __DIR__ . '/../bootstrap.php';

class ConexionMongo {
    private static $cliente;
    private static $db;

    public static function conectar() {
        if (!isset(self::$cliente)) {
            $uri = $_ENV['MONGO_URI'] ?? 'mongodb://localhost:27017';
            $db  = $_ENV['MONGO_DB'] ?? 'barberlah';

            try {
                self::$cliente = new MongoDB\Client($uri);
                self::$db = self::$cliente->$db;
            } catch (Exception $e) {
                die("Error de conexión a MongoDB: " . $e->getMessage());
            }
        }
        return self::$db;
    }
}
