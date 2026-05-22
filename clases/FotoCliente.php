<?php
declare(strict_types=1);

/**
 * FotoCliente — gestión de las fotos de historial del cliente
 *
 * Cada cliente puede tener un máximo de MAX_FOTOS fotos.
 * Hassan las ve desde el admin para saber el estilo que lleva el cliente.
 *
 * Tabla BD:
 *   fotos_cliente(id, id_usuario, ruta, fecha_subida)
 */
class FotoCliente
{
    // ── Constante de negocio ────────────────────────────────────────
    public const MAX_FOTOS = 8;

    // ── Propiedades ─────────────────────────────────────────────────
    private int    $id;
    private int    $id_usuario;
    private string $ruta;
    private string $fecha_subida;

    // ── Constructor ─────────────────────────────────────────────────
    public function __construct(int $id, int $id_usuario, string $ruta, string $fecha_subida)
    {
        $this->id           = $id;
        $this->id_usuario   = $id_usuario;
        $this->ruta         = $ruta;
        $this->fecha_subida = $fecha_subida;
    }

    // ── Getters ─────────────────────────────────────────────────────
    public function getId(): int         { return $this->id; }
    public function getIdUsuario(): int  { return $this->id_usuario; }
    public function getRuta(): string    { return $this->ruta; }
    public function getFechaSubida(): string { return $this->fecha_subida; }

    // ════════════════════════════════════════════════════════════════
    // MÉTODOS ESTÁTICOS — consultas a la BD
    // ════════════════════════════════════════════════════════════════

    /**
     * Devuelve todas las fotos de un usuario, ordenadas de más nueva a más antigua.
     * Retorna array de arrays asociativos (no objetos) para uso directo en vistas.
     *
     * @return array<int, array{id:int, id_usuario:int, ruta:string, fecha_subida:string}>
     */
    public static function obtenerPorUsuario(int $id_usuario): array
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare(
            "SELECT id, id_usuario, ruta, fecha_subida
             FROM fotos_cliente
             WHERE id_usuario = :id_usuario
             ORDER BY fecha_subida DESC, id DESC"
        );
        $stmt->execute(['id_usuario' => $id_usuario]);

        $fotos = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fotos[] = $fila;
        }

        return $fotos;
    }

    /**
     * Cuenta cuántas fotos tiene un usuario.
     * Se usa para controlar el límite de MAX_FOTOS antes de subir.
     */
    public static function contarPorUsuario(int $id_usuario): int
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare(
            "SELECT COUNT(*) FROM fotos_cliente WHERE id_usuario = :id_usuario"
        );
        $stmt->execute(['id_usuario' => $id_usuario]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca una foto por su id comprobando que pertenece al usuario.
     * El doble filtro (id + id_usuario) evita que un usuario borre fotos de otro.
     *
     * @return array{id:int, id_usuario:int, ruta:string, fecha_subida:string}|null
     */
    public static function obtenerPorIdYUsuario(int $id, int $id_usuario): ?array
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare(
            "SELECT id, id_usuario, ruta, fecha_subida
             FROM fotos_cliente
             WHERE id = :id AND id_usuario = :id_usuario"
        );
        $stmt->execute(['id' => $id, 'id_usuario' => $id_usuario]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila == false) {
            return null;
        }

        return $fila;
    }

    /**
     * Inserta una nueva foto en la BD.
     * Llamar siempre DESPUÉS de mover el archivo al servidor.
     * Retorna el id generado o 0 si falla.
     */
    public static function crear(int $id_usuario, string $ruta): int
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare(
            "INSERT INTO fotos_cliente (id_usuario, ruta, fecha_subida)
             VALUES (:id_usuario, :ruta, CURRENT_DATE)
             RETURNING id"
        );
        $stmt->execute([
            'id_usuario' => $id_usuario,
            'ruta'       => $ruta,
        ]);

        $id_nuevo = $stmt->fetchColumn();

        return ($id_nuevo !== false) ? (int) $id_nuevo : 0;
    }

    /**
     * Elimina una foto de la BD.
     * El filtro id_usuario garantiza que solo el dueño puede borrar.
     * La eliminación del archivo físico se hace ANTES desde la página PHP.
     */
    public static function eliminar(int $id, int $id_usuario): bool
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare(
            "DELETE FROM fotos_cliente
             WHERE id = :id AND id_usuario = :id_usuario"
        );
        $stmt->execute(['id' => $id, 'id_usuario' => $id_usuario]);

        // rowCount() devuelve cuántas filas se borraron; 1 = éxito, 0 = no existía o no era suya
        return $stmt->rowCount() === 1;
    }
}