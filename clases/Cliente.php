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

    // --------------------------------------------------------------
    // 1. CONSTRUCTOR
    // --------------------------------------------------------------

    /**
     * Constructor del Cliente.
     * Mapea todas las propiedades heredadas y fija automáticamente el rol como 'cliente'.
     * Soluciona el error de desajuste de parámetros con la clase padre Usuario.
     */
    public function __construct(
        $id,
        $google_id,
        $nombre,
        $email,
        $password,
        $avatar,
        $telefono,
        int $puntos_fidelidad = 0
    ) {
        parent::__construct($id, $google_id, $nombre, $email, $password, $avatar, $telefono, $puntos_fidelidad, 'cliente');
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
            VALUES (:nombre, :email, :password, :telefono, 'cliente', true, 0)
            RETURNING id
        ");

        $stmt->execute([
            'nombre'   => $nombre,
            'email'    => $email,
            'password' => $password_hash,
            'telefono' => $telefono
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($fila['id'] ?? 0);
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

    public static function obtenerFotos(int $id_usuario): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT id, ruta, fecha_subida
        FROM fotos_cliente
        WHERE id_usuario = :id
        ORDER BY fecha_subida DESC
    ");
        $stmt->execute([':id' => $id_usuario]);

        $fotos = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fotos[] = $fila;
        }
        return $fotos;
    }
}