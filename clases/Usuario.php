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


    // Comprueba si el usuario tiene rol de administrador.
    public function tieneRolAdmin(): bool {
        return $this->rol === 'admin';
    }

    // Decide a que pagina va el usuario despues de iniciar sesion.
    public function obtenerRutaDespuesLogin(string $directorioPublico): string {
        if ($this->tieneRolAdmin() && file_exists($directorioPublico . '/admin/panel.php')) {
            return 'admin/panel.php';
        }

        return 'index.php';
    }

    // Ejecuta la redireccion HTTP usando la ruta calculada arriba.
    public function redirigirDespuesLogin(string $directorioPublico): void {
        header('Location: ' . $this->obtenerRutaDespuesLogin($directorioPublico));
        exit;
    }

    // Quita espacios al principio y al final de textos recibidos por formulario.
    public static function limpiarTexto(string $valor): string {
        return trim($valor);
    }

    // Crea o devuelve el token de seguridad del formulario de login/registro.
    public static function obtenerTokenLogin(): string {
        if (empty($_SESSION['csrf_login'])) {
            $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_login'];
    }

    // Comprueba que el token recibido coincide con el guardado en la sesion.
    public static function tokenLoginValido($token): bool {
        return isset($_SESSION['csrf_login']) && is_string($token) && hash_equals($_SESSION['csrf_login'], $token);
    }

    // Estructura comun para pintar la vista sin variables sueltas.
    public static function estadoFormularioLogin(): array {
        return [
            'usuario' => null,
            'errorLogin' => '',
            'errorRegistro' => '',
            'modo' => 'login',
            'valores' => [
                'login_email' => '',
                'nombre' => '',
                'telefono' => '',
                'email_registro' => '',
            ],
        ];
    }

    // Valida los datos del login y devuelve usuario o mensaje de error.
    public static function procesarLogin(array $datos): array {
        $estado = self::estadoFormularioLogin();
        $email = self::limpiarTexto($datos['email'] ?? '');
        $password = (string) ($datos['password'] ?? '');
        $estado['valores']['login_email'] = $email;

        // Si el token no coincide, se corta antes de consultar la base de datos.
        if (!self::tokenLoginValido($datos['csrf_token'] ?? null)) {
            $estado['errorLogin'] = 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.';
            return $estado;
        }

        // Validaciones simples para dar mensajes claros al usuario.
        if ($email === '' || $password === '') {
            $estado['errorLogin'] = 'Rellena tu email y contraseña para entrar.';
            return $estado;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $estado['errorLogin'] = 'Introduce un email válido.';
            return $estado;
        }

        try {
            // Busca el usuario y verifica la contraseña con password_verify.
            $estado['usuario'] = self::comprobarLogin($email, $password);
            $estado['errorLogin'] = $estado['usuario'] instanceof Usuario ? '' : 'Email o contraseña incorrectos.';
        } catch (Throwable $e) {
            $estado['errorLogin'] = 'No se ha podido iniciar sesión ahora mismo.';
        }

        return $estado;
    }
}
?>
