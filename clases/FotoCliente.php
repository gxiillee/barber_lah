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
     * Retorna array de arrays asociativos para uso directo en vistas.
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
             VALUES (:id_usuario, :ruta, CURRENT_DATE)"
        );
        $stmt->execute([
            'id_usuario' => $id_usuario,
            'ruta'       => $ruta,
        ]);

        return (int)$conexion->lastInsertId();
    }



    /**
     * Procesa la subida de múltiples fotos, validándolas y guardándolas.
     * Retorna un array con el número de fotos subidas y los errores encontrados.
     *
     * @param array $archivos El array $_FILES['fotos']
     * @param int $id_usuario ID del cliente
     * @param int $huecos_disponibles Cuántas fotos le caben aún
     * @return array ['subidas' => int, 'errores' => array]
     */
    public static function procesarSubidaMultiple(array $archivos, int $id_usuario, int $huecos_disponibles): array {
        $resultado = ['subidas' => 0, 'errores' => []];
        $carpeta = __DIR__ . '/../public/uploads/fotos_clientes/';
        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/webp'];

        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        $total = count($archivos['name']);

        try {
            for ($i = 0; $i < $total; $i++) {
                if ($resultado['subidas'] >= $huecos_disponibles) {
                    $resultado['errores'][] = 'Límite alcanzado.';
                    break;
                }

                if ($archivos['error'][$i] !== UPLOAD_ERR_OK) continue;

                $tmp = $archivos['tmp_name'][$i];
                $mime = mime_content_type($tmp);

                if (!in_array($mime, $tipos_permitidos, true)) {
                    $resultado['errores'][] = 'Formato no válido (JPG, PNG, WebP).';
                    continue;
                }

                if ($archivos['size'][$i] > 2 * 1024 * 1024) {
                    $resultado['errores'][] = 'Supera los 2 MB.';
                    continue;
                }

                $extension = match ($mime) {
                    'image/webp' => 'webp',
                    'image/png'  => 'png',
                    default      => 'jpg',
                };
                $nombre = 'foto_' . $id_usuario . '_' . uniqid() . '.' . $extension;
                $destino = $carpeta . $nombre;
                $ruta_bd = 'public/uploads/fotos_clientes/' . $nombre;

                if (!move_uploaded_file($tmp, $destino)) {
                    $resultado['errores'][] = 'Error al guardar la imagen.';
                    continue;
                }

                if (self::crear($id_usuario, $ruta_bd) === 0) {
                    @unlink($destino);
                    $resultado['errores'][] = 'Error al guardar en la BD.';
                    continue;
                }

                $resultado['subidas']++;
            }
        } catch (\Throwable $e) {
            error_log('FotoCliente::procesarSubidaMultiple: ' . $e->getMessage());
            $resultado['errores'][] = 'Error interno al procesar las imágenes.';
        }

        return $resultado;
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