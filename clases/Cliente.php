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

    // Valida el formulario de registro y crea el cliente si todo es correcto.
    public static function procesarRegistroLogin(array $datos): array {
        $estado = Usuario::estadoFormularioLogin();
        $estado['modo'] = 'registro';

        $nombre = Usuario::limpiarTexto($datos['nombre'] ?? '');
        $telefono = Usuario::limpiarTexto($datos['telefono'] ?? '');
        $email = Usuario::limpiarTexto($datos['email_registro'] ?? '');
        $password = (string) ($datos['password_registro'] ?? '');

        $estado['valores']['nombre'] = $nombre;
        $estado['valores']['telefono'] = $telefono;
        $estado['valores']['email_registro'] = $email;

        // Misma proteccion CSRF que en login.
        if (!Usuario::tokenLoginValido($datos['csrf_token'] ?? null)) {
            $estado['errorRegistro'] = 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.';
            return $estado;
        }

        // Validaciones basicas antes de intentar guardar en la base de datos.
        if ($nombre === '' || $telefono === '' || $email === '' || $password === '') {
            $estado['errorRegistro'] = 'Rellena todos los campos para crear tu cuenta.';
            return $estado;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $estado['errorRegistro'] = 'Introduce un email válido.';
            return $estado;
        }

        if (strlen($password) < 6) {
            $estado['errorRegistro'] = 'La contraseña debe tener al menos 6 caracteres.';
            return $estado;
        }

        try {
            // Crea el cliente y lo inicia automaticamente si se puede verificar.
            self::crear($nombre, $email, $password, $telefono);
            $estado['usuario'] = Usuario::comprobarLogin($email, $password);
            $estado['errorRegistro'] = $estado['usuario'] instanceof Usuario ? '' : 'La cuenta se creó, pero no pudimos iniciar sesión automáticamente.';
        } catch (Throwable $e) {
            $estado['errorRegistro'] = 'Ese email ya está registrado o no se pudo crear la cuenta.';
        }

        return $estado;
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
