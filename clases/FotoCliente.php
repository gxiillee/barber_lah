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
     * Comprime y guarda una imagen en formato JPEG optimizado.
     * Redimensiona automáticamente si el ancho supera 1200px.
     *
     * @param string $archivo_tmp Ruta temporal del archivo subido
     * @param string $destino Ruta final donde se guardará
     * @return bool True si tuvo éxito, false si hubo error
     */
    public static function comprimirYGuardar(string $archivo_tmp, string $destino): bool {
        $info = getimagesize($archivo_tmp);
        if (!$info) {
            return false;
        }

        $ancho = $info[0];
        $alto = $info[1];
        $tipo = $info['mime'];

        // Crear recurso de imagen según el tipo
        $img = match ($tipo) {
            'image/jpeg' => imagecreatefromjpeg($archivo_tmp),
            'image/png' => imagecreatefrompng($archivo_tmp),
            'image/webp' => imagecreatefromwebp($archivo_tmp),
            default => false,
        };

        if (!$img) {
            return false;
        }

        // Redimensionar si el ancho supera 1200px
        if ($ancho > 1200) {
            $ancho_max = 1200;
            $nuevo_alto = (int)($alto * ($ancho_max / $ancho));
            $lienzo = imagecreatetruecolor($ancho_max, $nuevo_alto);

            if ($tipo === 'image/png' || $tipo === 'image/webp') {
                imagealphablending($lienzo, false);
                imagesavealpha($lienzo, true);
            }

            imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $ancho_max, $nuevo_alto, $ancho, $alto);
            imagedestroy($img);
            $img = $lienzo;
        }

        // Guardar como JPEG con calidad 85%
        $resultado = imagejpeg($img, $destino, 85);
        imagedestroy($img);

        return $resultado;
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

        for ($i = 0; $i < $total; $i++) {
            // Si ya se alcanzó el límite, parar
            if ($resultado['subidas'] >= $huecos_disponibles) {
                $resultado['errores'][] = 'Límite alcanzado. Solo se subieron ' . $resultado['subidas'] . ' foto(s).';
                break;
            }

            // Ignorar si hubo error en la carga
            if ($archivos['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmp = $archivos['tmp_name'][$i];
            $nombre_original = h($archivos['name'][$i]);

            // Validar tipo MIME
            if (!in_array(mime_content_type($tmp), $tipos_permitidos, true)) {
                $resultado['errores'][] = "\"$nombre_original\": tipo no permitido (JPG, PNG, WEBP).";
                continue;
            }

            // Validar tamaño máximo (10 MB)
            if ($archivos['size'][$i] > 10 * 1024 * 1024) {
                $resultado['errores'][] = "\"$nombre_original\": supera los 10 MB.";
                continue;
            }

            // Generar nombre único y rutas
            $nombre = 'foto_' . $id_usuario . '_' . time() . '_' . $i . '.jpg';
            $destino = $carpeta . $nombre;
            $ruta_bd = 'public/uploads/fotos_clientes/' . $nombre;

            // Comprimir y guardar
            if (!self::comprimirYGuardar($tmp, $destino)) {
                $resultado['errores'][] = "\"$nombre_original\": error al procesar la imagen.";
                continue;
            }

            // Insertar en Base de Datos
            if (self::crear($id_usuario, $ruta_bd) === 0) {
                @unlink($destino);
                $resultado['errores'][] = "\"$nombre_original\": error al guardar en la BD.";
                continue;
            }

            $resultado['subidas']++;
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