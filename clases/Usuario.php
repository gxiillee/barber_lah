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
    protected $nota_interna;
    protected $created_at;
    protected $password_updated_at;

    // ---------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------

    public function __construct($id, $google_id, $nombre, $email, $password, $avatar, $telefono, $puntos_fidelidad, $rol, $nota_interna = null, $created_at = null) {
        $this->id               = $id;
        $this->google_id        = $google_id;
        $this->nombre           = $nombre;
        $this->email            = $email;
        $this->password         = $password;
        $this->avatar           = $avatar;
        $this->telefono         = $telefono;
        $this->puntos_fidelidad = $puntos_fidelidad;
        $this->rol              = $rol;
        $this->nota_interna     = $nota_interna;
        $this->created_at       = $created_at;
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
    public function setTelefono(string $telefono) { $this->telefono = $telefono; }
    public function getPuntosFidelidad() { return $this->puntos_fidelidad; }
    public function setPuntosFidelidad(int $puntos): void { $this->puntos_fidelidad = $puntos; }
    public function getRol()             { return $this->rol; }
    public function getNotaInterna()     { return $this->nota_interna; }
    public function getCreatedAt()       { return $this->created_at; }
    public function getPasswordUpdatedAt() { return $this->password_updated_at; }
    public function setPasswordUpdatedAt($valor): void { $this->password_updated_at = $valor; }

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

        $usuario = new Usuario(
            $fila['id'],
            $fila['google_id'] ?? null,
            $fila['nombre'],
            $fila['email'],
            $fila['password'],
            $fila['avatar'] ?? null,
            $fila['telefono'],
            (int)($fila['puntos_fidelidad'] ?? 0),
            $fila['rol'],
            $fila['nota_interna'] ?? null,
            $fila['created_at'] ?? null
        );
        $usuario->setPasswordUpdatedAt($fila['password_updated_at'] ?? null);
        return $usuario;
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
            $u = new self(
                $fila['id'],
                $fila['google_id'] ?? null,
                $fila['nombre'],
                $fila['email'],
                $fila['password'],
                $fila['avatar'] ?? null,
                $fila['telefono'],
                (int)($fila['puntos_fidelidad'] ?? 0),
                $fila['rol'],
                $fila['nota_interna'] ?? null,
                $fila['created_at'] ?? null
            );
            $u->setPasswordUpdatedAt($fila['password_updated_at'] ?? null);
            return $u;
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

            $u = new self(
                $fila['id'],
                $googleId,
                $fila['nombre'],
                $fila['email'],
                $fila['password'],
                $fila['avatar'] ?? $avatar,
                $fila['telefono'],
                (int)($fila['puntos_fidelidad'] ?? 0),
                $fila['rol'],
                $fila['nota_interna'] ?? null,
                $fila['created_at'] ?? null
            );
            $u->setPasswordUpdatedAt($fila['password_updated_at'] ?? null);
            return $u;
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

        $u = new self($idNuevo, $googleId, $nombre, $email, null, $avatar, null, 0, 'cliente', null, date('Y-m-d H:i:s'));
        $u->setPasswordUpdatedAt(null);
        return $u;
    }

    // ---------------------------------------------------------------
    // Metodos de utilidad de estado
    // ---------------------------------------------------------------

    /**
     * Indica si el usuario tiene una contraseña establecida en la BD.
     * Los usuarios registrados con Google pueden tener password = NULL.
     */
    public function tienePassword(): bool {
        return $this->password !== null && $this->password !== '';
    }

    /**
     * Establece (o actualiza) la contraseña de un usuario en la base de datos.
     * Sirve tanto para usuarios sin contraseña (Google) como para cambio normal.
     *
     * @param int    $id            ID del usuario
     * @param string $password-plano Contraseña en texto plano (se hashea internamente)
     * @return bool                 True si se actualizó correctamente
     */
    public static function establecerPassword(int $id, string $password): bool {
        $conexion = BD::obtenerConexion();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $conexion->prepare(
            "UPDATE usuarios SET password = :password, password_updated_at = NOW() WHERE id = :id AND activo = true"
        );
        $stmt->execute([
            ':password' => $hash,
            ':id'       => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Obtiene la fecha del último cambio de contraseña.
     * @return string|null Fecha en formato Y-m-d H:i:s o null si nunca se cambió
     */
    public static function obtenerFechaUltimoCambioPassword(int $id): ?string {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare(
            "SELECT password_updated_at, created_at FROM usuarios WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $fecha = $row['password_updated_at'];
        $creado = $row['created_at'] ?? null;
        // Si password_updated_at es NULL o coincide con created_at (misma registro), nunca se cambió
        if (!$fecha || ($creado && abs(strtotime($fecha) - strtotime($creado)) < 5)) {
            return null;
        }
        return $fecha;
    }

    /**
     * Verifica que una contraseña no esté en las últimas 3 del historial (admin).
     */
    public static function checkPasswordHistory(int $usuarioId, string $newPassword): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare(
            "SELECT password_hash FROM password_history WHERE usuario_id = :id ORDER BY created_at DESC LIMIT 3"
        );
        $stmt->execute([':id' => $usuarioId]);
        $hashes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($hashes as $hash) {
            if (password_verify($newPassword, $hash)) return false;
        }
        return true;
    }

    /**
     * Guarda una contraseña en el historial y mantiene solo las últimas 3 (admin).
     */
    public static function addPasswordHistory(int $usuarioId, string $passwordHash): void {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare(
            "INSERT INTO password_history (usuario_id, password_hash) VALUES (:id, :hash)"
        );
        $stmt->execute([':id' => $usuarioId, ':hash' => $passwordHash]);

        $stmt = $conexion->prepare(
            "DELETE FROM password_history WHERE usuario_id = :id AND id NOT IN (
                SELECT id FROM password_history WHERE usuario_id = :id2 ORDER BY created_at DESC LIMIT 3
            )"
        );
        $stmt->execute([':id' => $usuarioId, ':id2' => $usuarioId]);
    }

    /**
     * Admin: actualiza los puntos de fidelidad de un cliente.
     * Se usa desde ficha_cliente.php para corregir puntos manualmente.
     */
    public static function actualizarPuntos(int $idCliente, int $puntos): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            UPDATE usuarios SET puntos_fidelidad = :puntos
            WHERE id = :id AND rol = 'cliente'
        ");
        return $stmt->execute([
            ':puntos' => max(0, $puntos),
            ':id'     => $idCliente,
        ]);
    }

    /**
     * Cliente: guarda o actualiza su propio teléfono.
     * Se usa desde la pantalla de inicio del panel cliente si no tiene teléfono.
     */
    public static function actualizarTelefono(int $id, string $telefono): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            UPDATE usuarios SET telefono = :telefono
            WHERE id = :id
        ");
        return $stmt->execute([
            ':telefono' => $telefono,
            ':id'       => $id,
        ]);
    }

    /**
     * Admin: actualiza la nota interna de un cliente.
     * La nota solo la ve el admin desde ficha_cliente.php.
     */
    public static function actualizarNotaInterna(int $idCliente, ?string $nota): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            UPDATE usuarios SET nota_interna = :nota
            WHERE id = :id AND rol = 'cliente'
        ");
        return $stmt->execute([
            ':nota' => ($nota !== null && $nota !== '') ? $nota : null,
            ':id'   => $idCliente,
        ]);
    }

    /**
     * PANEL ADMIN DE HASSAN
     * Devuelve únicamente los usuarios que son clientes,
     * ideal para la agenda y el directorio del barbero.
     */
    public static function listarClientes(): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->query("
            SELECT id, nombre, email, telefono, puntos_fidelidad 
            FROM usuarios 
            WHERE rol = 'cliente' 
            ORDER BY nombre ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}