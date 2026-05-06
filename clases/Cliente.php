<?php
require_once 'BD.php';
require_once 'Usuario.php';

class Cliente extends Usuario {

    // El constructor llama al constructor del padre (Usuario) forzando el rol 'cliente'
    public function __construct($id, $nombre, $email, $password, $telefono) {
        parent::__construct($id, $nombre, $email, $password, $telefono, 'cliente');
    }

    /**
     * Crea un nuevo usuario
     * Se usa en al registrar un nuevo cliente en login.php
     */
    public static function crear($nombre, $email, $password_normal, $telefono) {
        $conexion = BD::obtenerConexion();

        // Encriptar la contraseña antes de guardar
        $password_hash = password_hash($password_normal, PASSWORD_DEFAULT);

        $stmt = $conexion->prepare("
            INSERT INTO usuarios (nombre, email, password, telefono, rol, activo) 
            VALUES (:nombre, :email, :password, :telefono, 'cliente', 1)
        ");

        // Ejecutar con el array de parámetros
        $stmt->execute([
            'nombre' => $nombre,
            'email' => $email,
            'password' => $password_hash,
            'telefono' => $telefono
        ]);

        return $conexion->lastInsertId();
    }

    /**
     * Devuelve un array con todos los clientes registrados
     * Se usa en panel admin mostrando clientes
     */
    public static function obtenerTodosLosClientes() {
        $conexion = BD::obtenerConexion();

        // Filtramos para que solo devuelva usuarios con rol de cliente
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE rol = 'cliente' ORDER BY created_at DESC");
        $stmt->execute();

        $todosLosClientes = [];

        // Uso de fetch_assoc y bucle while
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $todosLosClientes[] = new Cliente(
                $fila['id'],
                $fila['nombre'],
                $fila['email'],
                $fila['password'],
                $fila['telefono']
            );
        }

        return $todosLosClientes;
    }


}
?>