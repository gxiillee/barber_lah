<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';
require_once __DIR__ . '/Usuario.php';

/**
 * Modelo de datos para los Clientes (Socio del Club VIP).
 * Hereda de Usuario y encapsula las operaciones específicas de la base de datos para el rol cliente.
 * Siguiendo la nueva arquitectura limpia, no maneja lógica de vistas ni respuestas HTTP.
 */
class Cliente extends Usuario {

    // Propiedad extra: no está en el constructor del padre, se asigna en obtenerPorId

    // --------------------------------------------------------------
    // 1. CONSTRUCTOR
    // --------------------------------------------------------------

    /**
     * Constructor del Cliente.
     * Mapea todas las propiedades heredadas y fija automáticamente el rol como 'cliente'.
     * Soluciona el error de desajuste de parámetros con la clase padre Usuario.
     */
    public function __construct(
        string $nombre,
        string $email,
        ?int $id = null,
        ?string $google_id = null,
        ?string $password = null,
        ?string $avatar = null,
        ?string $telefono = null,
        int $puntos_fidelidad = 0,
        ?string $nota_interna = null,
        ?string $created_at = null
    ) {
        parent::__construct($id, $google_id, $nombre, $email, $password, $avatar, $telefono, $puntos_fidelidad, 'cliente', $nota_interna, $created_at);
    }
    // --------------------------------------------------------------
    // 2. MÉTODOS DE ESCRITURA (Escritura en BD)
    // --------------------------------------------------------------

    /**
     * Inserta un nuevo cliente en PostgreSQL con la contraseña ya encriptada.
     * Utiliza la cláusula 'RETURNING id' nativa de PostgreSQL para devolver el ID generado.
     * * NOTA PARA EL TFG: Las excepciones (como correos duplicados, código de error 23000)
     * ya no se capturan aquí con textos planos; se dejan propagar para que el controlador
     * (formulario de registro) decida de manera flexible cómo mostrárselas al usuario.
     */
    public static function crear(string $nombre, string $email, string $password_normal, string $telefono): int {
        $conexion = BD::obtenerConexion();
        $password_hash = password_hash($password_normal, PASSWORD_DEFAULT);

        $stmt = $conexion->prepare("
            INSERT INTO usuarios (nombre, email, password, telefono, rol, activo, puntos_fidelidad) 
            VALUES (:nombre, :email, :password, :telefono, 'cliente', 1, 0)
        ");

        $stmt->execute([
            'nombre'   => $nombre,
            'email'    => $email,
            'password' => $password_hash,
            'telefono' => $telefono
        ]);

        return (int)$conexion->lastInsertId();
    }

    // --------------------------------------------------------------
    // 3. MÉTODOS DE CONSULTA (Lectura en BD)
    // --------------------------------------------------------------

    /**
     * Recupera la lista completa de clientes registrados para el panel de administración.
     * Mapea limpiamente los registros a objetos de la clase Cliente para asegurar el tipado estricto.
     */
    public static function obtenerTodosLosClientes(): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT id, google_id, nombre, email, password, avatar, telefono, puntos_fidelidad 
            FROM usuarios 
            WHERE rol = 'cliente' 
            ORDER BY id DESC
        ");
        $stmt->execute();

        $clientes = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clientes[] = new self(
                $fila['id'],
                $fila['google_id'] ?? null,
                $fila['nombre'],
                $fila['email'],
                $fila['password'] ?? null,
                $fila['avatar'] ?? null,
                $fila['telefono'] ?? '',
                (int)($fila['puntos_fidelidad'] ?? 0)
            );
        }

        return $clientes;
    }



    /* =======================================================================
     * Devuelve un objeto Cliente (que extends Usuario) o null si no existe.
     * Se usa en ficha_cliente.php para cargar el cliente por su id.
     * ======================================================================= */

    public static function obtenerPorId(int $id): ?Cliente {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare(
            "SELECT id, google_id, nombre, email, password, avatar,
                    telefono, puntos_fidelidad, nota_interna, created_at
               FROM usuarios
              WHERE id = :id
                AND activo = true"
        );
        $stmt->execute(['id' => $id]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fila == false) {
            return null;
        }

        $cliente = new Cliente(
            $fila['nombre'],
            $fila['email'],
            $fila['id'],
            $fila['google_id'],
            $fila['password'],
            $fila['avatar'],
            $fila['telefono'],
            (int)($fila['puntos_fidelidad'] ?? 0)
        );

        $cliente->setCreatedAt($fila['created_at']);
        $cliente->setNotaInterna($fila['nota_interna'] ?? null);
        return $cliente;
    }

    // Setter interno para created_at (usado solo en obtenerPorId)
    private function setCreatedAt($valor): void {
        $this->created_at = $valor;
    }

    // Setter para nota_interna (usado desde obtenerPorId)
    private function setNotaInterna($valor): void {
        $this->nota_interna = $valor;
    }

    /**
     * Procesa el registro de un nuevo cliente desde el formulario de login.php.
     * Valida los datos, hashea la contraseña, crea el usuario y devuelve el objeto
     * creado o un mensaje de error.
     *
     * @param array $datos Array asociativo con las claves:
     *                     - nombre
     *                     - telefono
     *                     - email_registro
     *                     - password_registro
     * @return array       ['usuario' => Cliente|null, 'errorRegistro' => string, 'valores' => array]
     */
    public static function procesarRegistroLogin(array $datos): array {
        // Inicializar retorno
        $retorno = [
            'usuario' => null,
            'errorRegistro' => '',
            'valores' => $datos
        ];

        // Extraer campos
        $nombre = trim($datos['nombre'] ?? '');
        $telefono = trim($datos['telefono'] ?? '');
        $email = trim($datos['email_registro'] ?? '');
        $password = $datos['password_registro'] ?? '';

        // Validaciones básicas
        if ($nombre === '') {
            $retorno['errorRegistro'] = 'El nombre es obligatorio.';
            return $retorno;
        }
        if ($telefono === '') {
            $retorno['errorRegistro'] = 'El teléfono es obligatorio.';
            return $retorno;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $retorno['errorRegistro'] = 'El email es obligatorio y debe ser válido.';
            return $retorno;
        }
        if ($password === '' || strlen($password) < 6) {
            $retorno['errorRegistro'] = 'La contraseña debe tener al menos 6 caracteres.';
            return $retorno;
        }

        // Nota: crear() ya aplica password_hash, así que pasamos la contraseña en plano
        try {
            // Intentar crear el usuario
            $idNuevo = self::crear($nombre, $email, $password, $telefono);
            if ($idNuevo <= 0) {
                $retorno['errorRegistro'] = 'No se pudo crear el usuario. Inténtalo de nuevo.';
                return $retorno;
            }
            // Obtener el objeto creado
            $usuario = self::obtenerPorId($idNuevo);
            if ($usuario === null) {
                $retorno['errorRegistro'] = 'Error al recuperar el usuario creado.';
                return $retorno;
            }
            $retorno['usuario'] = $usuario;
            return $retorno;
        } catch (Throwable $e) {
            // Manejo de excepciones de base de datos (ej. email duplicado)
            $errorMsg = $e->getMessage();
            // Detectar error de unicidad (SQLSTATE 23000) en PostgreSQL
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $retorno['errorRegistro'] = 'Este correo electrónico ya está registrado.';
            } else {
                // En producción no se debe exponer el error completo; se puede loggear.
                $retorno['errorRegistro'] = 'Error al registrar el usuario. Inténtalo de nuevo.';
            }
            return $retorno;
        }
    }
}