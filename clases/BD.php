<?php
class BD {
    // Guarda la única instancia de la conexión. Null hasta que se use por primera vez.
    private static $conexion = null;
    // El constructor privado impide hacer "new BD()" desde fuera — solo existe una conexión.
    private function __construct() {}

    public static function obtenerConexion() {
        // Solo crea la conexión si aún no existe (la primera vez que se llama)
        if (self::$conexion === null) {

            $host      = '192.168.4.17';
            $puerto    = '5432';
            $bd        = 'barberlah';
            $usuario   = 'postgres';
            $contrasena = '1234';

            $dsn = "pgsql:host=$host;port=$puerto;dbname=$bd";

            try {
                //guarda la conexion para que no se tenga que hacer cada vez
                self::$conexion = new PDO($dsn, $usuario, $contrasena);
                //si algo falla, da una excepcion al catch
                self::$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Error de conexión: " . $e->getMessage());
            }
        }
        // Las siguientes llamadas simplemente devuelven la conexión ya creada
        return self::$conexion;
    }
}