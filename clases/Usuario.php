<?php

// Obliga php a ser estricto con lo que tiene que devolver una funcion
declare(strict_types=1);

require_once __DIR__ . '/BD.php';

class Usuario {
    private $id;
    private $nombre;
    private $email;
    private $password;
    private $telefono;
    private $rol;

    // 2. CONSTRUCTOR
    public function __construct($id, $nombre, $email, $password, $telefono, $rol) {
        $this->id       = $id;
        $this->nombre   = $nombre;
        $this->email    = $email;
        $this->password = $password;
        $this->telefono = $telefono;
        $this->rol      = $rol;
    }

    // 3. GETTERS (Acceso a datos)
    public function getId()       { return $this->id; }
    public function getNombre()   { return $this->nombre; }
    public function getEmail()    { return $this->email; }
    public function getPassword() { return $this->password; }
    public function getTelefono() { return $this->telefono; }
    public function getRol()      { return $this->rol; }

    // 4. MÉTODOS DE AUTENTICACIÓN (Lógica central)

    /**
     * Consulta la BD para verificar credenciales.
     */
    public static function comprobarLogin(string $email, string $password): ?Usuario {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email AND activo = true");
        $stmt->execute(['email' => $email]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila || !password_verify($password, $fila['password'])) {
            return null;
        }

        return new Usuario(
            $fila['id'], $fila['nombre'], $fila['email'],
            $fila['password'], $fila['telefono'], $fila['rol']
        );
    }

    /**
     * Controlador de flujo del login: Valida seguridad, formato y credenciales.
     */
    public static function procesarLogin(array $datos): array {
        $estado = self::estadoFormularioLogin();
        $estado['valores'] = self::cargarValoresPost($datos);

        $email    = $estado['valores']['login_email'];
        $password = (string)($datos['password'] ?? '');

        // Validación 1: Seguridad CSRF
        if (!self::tokenLoginValido($datos['csrf_token'] ?? null)) {
            $estado['errorLogin'] = 'Sesión caducada. Por favor, recarga.';
            return $estado;
        }

        // Validación 2: Campos obligatorios
        if (empty($email) || empty($password)) {
            $estado['errorLogin'] = 'Por favor, rellena todos los campos.';
            return $estado;
        }

        // Validación 3: Formato de email
        if (!self::validarEmail($email)) {
            $estado['errorLogin'] = 'El formato del email no es correcto.';
            return $estado;
        }

        // Intento de acceso a base de datos
        try {
            $usuario = self::comprobarLogin($email, $password);

            if ($usuario instanceof Usuario) {
                $estado['usuario'] = $usuario;
            } else {
                $estado['errorLogin'] = 'Email o contraseña incorrectos.';
            }
        } catch (Throwable $e) {
            $estado['errorLogin'] = 'Error de conexión. Inténtalo más tarde.';
        }

        return $estado;
    }

    // 5. MÉTODOS DE REDIRECCIÓN (Gestión de rutas)

    public function tieneRolAdmin(): bool {
        return $this->rol === 'admin';
    }

    public function obtenerRutaDespuesLogin(string $directorio): string {
        if (isset($_SESSION['reserva_pendiente'])) {
            return 'confirmar_reserva.php';
        }
        return ($this->tieneRolAdmin() && file_exists($directorio . '/admin/panel.php'))
            ? 'admin/panel.php'
            : 'index.php';
    }

    public function redirigirDespuesLogin(string $directorio): void {
        header('Location: ' . $this->obtenerRutaDespuesLogin($directorio));
        exit;
    }

    // 6. UTILIDADES DE SEGURIDAD Y LIMPIEZA

    public static function validarEmail(string $email): bool {
        return filter_var(self::limpiarTexto($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function limpiarTexto(string $valor): string {
        return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
    }

    public static function obtenerTokenLogin(): string {
        if (empty($_SESSION['csrf_login'])) {
            $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_login'];
    }

    public static function tokenLoginValido($token): bool {
        return isset($_SESSION['csrf_login']) && is_string($token) && hash_equals($_SESSION['csrf_login'], $token);
    }

    // 7. GESTIÓN DE ESTADO (Para la Vista)

    public static function estadoFormularioLogin(): array {
        return [
            'usuario' => null,
            'errorLogin' => '',
            'errorRegistro' => '',
            'modo' => 'login',
            'valores' => ['login_email' => '', 'nombre' => '', 'telefono' => '', 'email_registro' => '']
        ];
    }

    public static function cargarValoresPost(array $datos): array {
        return [
            'login_email'    => self::limpiarTexto($datos['email'] ?? ''),
            'nombre'         => self::limpiarTexto($datos['nombre'] ?? ''),
            'telefono'       => self::limpiarTexto($datos['telefono'] ?? ''),
            'email_registro' => self::limpiarTexto($datos['email_registro'] ?? ''),
        ];
    }
}
