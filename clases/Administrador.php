<?php
require_once 'BD.php';
require_once 'Usuario.php';

class Administrador extends Usuario {

    // El constructor llama al padre y fija el rol a 'admin'
    public function __construct($id, $nombre, $email, $password, $telefono = null) {
        parent::__construct($id, $nombre, $email, $password, $telefono, 'admin');
    }

    /**
     * MÉTODO DE SEGURIDAD
     * Verifica de forma simple si el usuario tiene rol de administrador.
     */
    public static function esAdmin($usuario) {
        // Si el usuario existe y su método getRol devuelve 'admin', es verdadero
        return (is_object($usuario) && $usuario->getRol() === 'admin');
    }

    /**
     * MÉTODOS ESTÁTICOS DE BASE DE DATOS
     */

    // Obtener todos los administradores del sistema (por si Hassan tuviera socios)
    public static function obtenerTodosLosAdmins() {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE rol = 'admin' AND activo = 1");
        $stmt->execute();

        $admins = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $admins[] = new Administrador(
                $fila['id'],
                $fila['nombre'],
                $fila['email'],
                $fila['password'],
                $fila['telefono']
            );
        }
        return $admins;
    }

    // Ejemplo de método de gestión: Obtener resumen para el dashboard
    public static function obtenerResumenCitasHoy() {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM reservas WHERE fecha = CURRENT_DATE");
        $stmt->execute();

        // la fila que recoge de la BD
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si fetch devuelve false, es que no hay datos; retornamos 0 directamente
        if ($fila == false) {
            return 0;
        }
        //no se puede devolver todo el array $fila, se devuelve solo la etiqueta ['total'].
        return $fila['total'];
    }
}