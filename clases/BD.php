<?php
class BD {
    // Guarda la única instancia de la conexión. Null hasta que se use por primera vez.
    private static $conexion = null;

    // 🔥 Semáforo de seguridad para evitar bucles infinitos de memoria
    private static $ejecutandoCron = false;

    // El constructor privado impide hacer "new BD()" desde fuera — solo existe una conexión.
    private function __construct() {}

    public static function obtenerConexion() {
        // Solo crea la conexión si aún no existe (la primera vez que se llama)
        if (self::$conexion === null) {

            $host      = 'localhost';
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

        // 🔥 DISPARADOR AUTOMÁTICO PROTEGIDO
        // Si no se está ejecutando ya, entramos. Esto rompe el bucle infinito.
        if (!self::$ejecutandoCron) {
            try {
                // Activamos el semáforo: "Cuidado, estamos actualizando citas"
                self::$ejecutandoCron = true;

                if (!class_exists('Reserva')) {
                    require_once __DIR__ . '/Reserva.php';
                }

                // Ahora, cuando esta función pida la conexión, el semáforo estará en true
                // y no volverá a intentar ejecutar este bloque, devolviendo la conexión limpiamente.
                Reserva::actualizarCitasPasadas();

            } catch (Throwable $e) {
                error_log("Error en el disparador automático de citas: " . $e->getMessage());
            } finally {
                // El bloque 'finally' se ejecuta SIEMPRE (vaya bien o salte un error).
                // Apagamos el semáforo para la siguiente interacción del usuario.
                self::$ejecutandoCron = false;
            }
        }

        // Las siguientes llamadas simplemente devuelven la conexión ya creada
        return self::$conexion;
    }
}