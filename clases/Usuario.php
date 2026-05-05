<?php
require_once 'BD.php';

class Usuario {
    // 1. Propiedades siempre privadas
    private $id;
    private $nombre;
    private $email;
    private $password;
    private $telefono;
    private $rol;

    // 2. Constructor
    public function __construct($id, $nombre, $email, $password, $telefono, $rol) {
        $this->id = $id;
        $this->nombre = $nombre;
        $this->email = $email;
        $this->password = $password;
        $this->telefono = $telefono;
        $this->rol = $rol;
    }

    // 3. Getters
    public function getId() { return $this->id; }
    public function getNombre() { return $this->nombre; }
    public function getEmail() { return $this->email; }
    public function getPassword() { return $this->password; }
    public function getTelefono() { return $this->telefono; }
    public function getRol() { return $this->rol; }

    // 4. Métodos estáticos de BD

    // Método para comprobar el login
    public static function comprobarLogin($email_ingresado, $password_ingresada) {
        $conexion = BD::obtenerConexion();

        // Siempre prepare + execute para evitar inyección SQL
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email AND activo = 1");
        $stmt->execute(['email' => $email_ingresado]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // El usuario no existe o está inactivo dar null
        if ($fila == false) {
            return null;
        }

        // Comprobar la contraseña encriptada
        if (password_verify($password_ingresada, $fila['password'])) {
            // Retornamos el objeto con los datos de la base de datos
            return new Usuario(
                $fila['id'],
                $fila['nombre'],
                $fila['email'],
                $fila['password'],
                $fila['telefono'],
                $fila['rol']
            );
        } else {
            return null; // Contraseña incorrecta
        }
    }
}
?>