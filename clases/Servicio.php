<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';

class Servicio {
    private int $id;
    private string $nombre;
    private float $precio;
    private int $duracionMin;
    private ?string $descripcion;
    private bool $activo;

    public function __construct(int $id, string $nombre, float $precio, int $duracionMin, ?string $descripcion, bool $activo) {
        $this->id          = $id;
        $this->nombre      = $nombre;
        $this->precio      = $precio;
        $this->duracionMin = $duracionMin;
        $this->descripcion = $descripcion;
        $this->activo      = $activo;
    }

    // Getters
    public function getIdServicio(): int          { return $this->id; }
    public function getNombre(): string   { return $this->nombre; }
    public function getPrecio(): float    { return $this->precio; }
    public function getDuracion(): int { return $this->duracionMin; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function isActivo(): bool      { return $this->activo; }

    /**
     * Devuelve todos los servicios activos
     * Se usa para no mostrar los inactivos pero
     * seguir teniendolos en bd para historicos
     */
    public static function obtenerTodos(): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->query("SELECT * FROM servicios WHERE activo = TRUE ORDER BY nombre ASC");

        return array_map(static function (array $fila): Servicio {
            return new self(
                (int)$fila['id'],
                $fila['nombre'],
                (float)$fila['precio'],
                (int)$fila['duracion_min'],
                $fila['descripcion'] ?? null,
                (bool)$fila['activo']
            );
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Devuelve un servicio por ID
     * Se usaria en editar servicio y
     * al darle resumen al cliente de su reserva con su servicio
     */
    public static function obtenerPorId(int $id): ?Servicio {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM servicios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        if ($fila) {
            return new Servicio(
                $fila['id'],
                $fila['nombre'],
                (float)$fila['precio'],
                (int)$fila['duracion_min'],
                $fila['descripcion'],
                (bool)$fila['activo']
            );
        }
        return null; //  Si no hay fila, devuelve nulo.
    }
    /**
     * Crea un nuevo servicio y lo marca como activo por defecto
     */
    public static function crear(string $nombre, int $duracionMin, float $precio, ?string $descripcion = null): bool {
        $conexion = BD::obtenerConexion();
        // Usamos el nombre exacto de tu columna: duracion_min
        $stmt = $conexion->prepare("
            INSERT INTO servicios (nombre, duracion_min, precio, descripcion, activo) 
            VALUES (:n, :d, :p, :desc, TRUE)
        ");
        return $stmt->execute([
            ':n'    => $nombre,
            ':d'    => $duracionMin,
            ':p'    => $precio,
            ':desc' => $descripcion
        ]);
    }

    /**
     * "Elimina" un servicio de forma lógica (activo = FALSE)
     * Así no se borran del historial de citas pasadas.
     */
    public static function eliminar(int $id): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("UPDATE servicios SET activo = FALSE WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Alterna el estado activo/inactivo de un servicio.
     * Devuelve el nuevo estado (true=activo, false=inactivo) o null si falla.
     */
    public static function toggleActivo(int $id): ?bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT activo FROM servicios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) return null;

        $nuevo = !((bool)$fila['activo']);
        $upd = $conexion->prepare("UPDATE servicios SET activo = :activo WHERE id = :id");
        $upd->execute([':activo' => $nuevo ? 'true' : 'false', ':id' => $id]);
        return $nuevo;
    }

    /**
     * Actualiza los datos de un servicio existente.
     */
    public static function actualizar(int $id, string $nombre, int $duracionMin, float $precio, ?string $descripcion = null): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            UPDATE servicios
            SET nombre = :n, duracion_min = :d, precio = :p, descripcion = :desc
            WHERE id = :id
        ");
        return $stmt->execute([
            ':n'    => $nombre,
            ':d'    => $duracionMin,
            ':p'    => $precio,
            ':desc' => $descripcion,
            ':id'   => $id,
        ]);
    }

    /**
     * Devuelve todos los servicios (activos e inactivos).
     * Se usa en admin para gestionar el catálogo completo.
     */
    public static function obtenerTodosIncluyendoInactivos(): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->query("SELECT * FROM servicios ORDER BY activo DESC, nombre ASC");
        return array_map(static function (array $fila): Servicio {
            return new self(
                (int)$fila['id'],
                $fila['nombre'],
                (float)$fila['precio'],
                (int)$fila['duracion_min'],
                $fila['descripcion'] ?? null,
                (bool)$fila['activo']
            );
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}