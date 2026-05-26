<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';

/**
 * Modelo de datos del usuario autenticado.
 * Solo contiene: propiedades, constructor, getters y los dos metodos de BD.
 * La logica de flujo (validar, redirigir, gestionar estado) vive en login.php.
 */
class Usuario {

    // ---------------------------------------------------------------
    // Propiedades protegidas — accesibles por clases hijas y getters
    // ---------------------------------------------------------------

    protected $id;
    protected $google_id;
    protected $nombre;
    protected $email;
    protected $password;
    protected $avatar;
    protected $telefono;
    protected $puntos_fidelidad;
    protected $rol;

    // ---------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------

    public function __construct($id, $google_id, $nombre, $email, $password, $avatar, $telefono, $puntos_fidelidad, $rol) {
        $this->id               = $id;
        $this->google_id        = $google_id;
        $this->nombre           = $nombre;
        $this->email            = $email;
        $this->password         = $password;
        $this->avatar           = $avatar;
        $this->telefono         = $telefono;
        $this->puntos_fidelidad = $puntos_fidelidad;
        $this->rol              = $rol;
    }

    // ---------------------------------------------------------------
    // Getters
    // ---------------------------------------------------------------

    public function getId()              { return $this->id; }
    public function getGoogleId()        { return $this->google_id; }
    public function getNombre()          { return $this->nombre; }
    public function getEmail()           { return $this->email; }
    public function getPassword()        { return $this->password; }
    public function getAvatar()          { return $this->avatar; }
    public function getTelefono()        { return $this->telefono; }
    public function getPuntosFidelidad() { return $this->puntos_fidelidad; }
    public function getRol()             { return $this->rol; }

    // ---------------------------------------------------------------
    // Metodo de utilidad de rol
    // ---------------------------------------------------------------

    /**
     * Devuelve true si el usuario es administrador.
     * Se usa en login.php para decidir a donde redirigir tras el login.
     */
    public function tieneRolAdmin(): bool {
        return $this->rol === 'admin';
    }

    // ---------------------------------------------------------------
    // Metodos de base de datos
    // ---------------------------------------------------------------

    /**
     * Busca un usuario activo por email y verifica su contrasena.
     * Devuelve una instancia de Usuario si las credenciales son correctas, o null si no.
     *
     * @param string $email    Email introducido en el formulario
     * @param string $password Contrasena en texto plano introducida en el formulario
     * @return Usuario|null    Objeto Usuario si todo es correcto, null si falla
     */
    public static function comprobarLogin(string $email, string $password): ?Usuario {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email AND activo = true");
        $stmt->execute(['email' => $email]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // 1. Si no existe el usuario, salimos
        if (!$fila) return null;

        // 2. Si existe pero NO tiene contraseña (es cuenta Google), impedimos el login por formulario
        if (empty($fila['password'])) {
            throw new Exception("Esta cuenta se registró a través de Google. Por favor, inicia sesión pulsando el botón de Google.");
        }

        // 3. Ahora sí, verificamos la contraseña con seguridad
        if (!password_verify($password, $fila['password'])) {
            return null;
        }

        return new Usuario(
            $fila['id'],
            $fila['google_id'] ?? null,
            $fila['nombre'],
            $fila['email'],
            $fila['password'],
            $fila['avatar'] ?? null,
            $fila['telefono'],
            (int)($fila['puntos_fidelidad'] ?? 0),
            $fila['rol']
        );
    }

    /**
     * Gestiona el login con Google OAuth en tres casos:
     *   1. El usuario ya existe con ese google_id → lo devuelve directamente.
     *   2. Existe por email pero sin google_id → vincula la cuenta y lo devuelve.
     *   3. No existe → crea un usuario nuevo como cliente.
     *
     * @param string      $googleId ID unico de Google del usuario
     * @param string      $nombre   Nombre completo que devuelve Google
     * @param string      $email    Email que devuelve Google
     * @param string|null $avatar   URL del avatar de Google (puede ser null)
     * @return Usuario|null         Objeto Usuario creado o encontrado, null si falla
     */
    public static function comprobarRegistrarGoogle(string $googleId, string $nombre, string $email, ?string $avatar): ?self {
        $conexion = BD::obtenerConexion();

        // Caso 1: ya existe con este google_id
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE google_id = :google_id AND activo = true LIMIT 1");
        $stmt->execute([':google_id' => $googleId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila) {
            return new self(
                $fila['id'],
                $fila['google_id'] ?? null,
                $fila['nombre'],
                $fila['email'],
                $fila['password'],
                $fila['avatar'] ?? null,
                $fila['telefono'],
                (int)($fila['puntos_fidelidad'] ?? 0),
                $fila['rol']
            );
        }

        // Caso 2: existe por email — vincular la cuenta tradicional con Google
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email AND activo = true LIMIT 1");
        $stmt->execute([':email' => $email]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila) {
            $stmt = $conexion->prepare("UPDATE usuarios SET google_id = :google_id, avatar = :avatar WHERE id = :id");
            $stmt->execute([
                ':google_id' => $googleId,
                ':avatar'    => $fila['avatar'] ?? $avatar,
                ':id'        => $fila['id']
            ]);

            return new self(
                $fila['id'],
                $googleId,
                $fila['nombre'],
                $fila['email'],
                $fila['password'],
                $fila['avatar'] ?? $avatar,
                $fila['telefono'],
                (int)($fila['puntos_fidelidad'] ?? 0),
                $fila['rol']
            );
        }

        // Caso 3: usuario nuevo — registrar como cliente
        $stmt = $conexion->prepare("
            INSERT INTO usuarios (google_id, nombre, email, password, avatar, rol, activo)
            VALUES (:google_id, :nombre, :email, null, :avatar, 'cliente', true)
            RETURNING id
        ");
        $stmt->execute([
            ':google_id' => $googleId,
            ':nombre'    => $nombre,
            ':email'     => $email,
            ':avatar'    => $avatar
        ]);

        $filaInsertada = $stmt->fetch(PDO::FETCH_ASSOC);
        $idNuevo = (int)$filaInsertada['id'];

        return new self($idNuevo, $googleId, $nombre, $email, null, $avatar, null, 0, 'cliente');
    }
}