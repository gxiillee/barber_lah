<?php
require_once 'BD.php';

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
    public function getId(): int          { return $this->id; }
    public function getNombre(): string   { return $this->nombre; }
    public function getPrecio(): float    { return $this->precio; }
    public function getDuracionMin(): int { return $this->duracionMin; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function isActivo(): bool      { return $this->activo; }

    /**
     * Devuelve todos los servicios activos
     * se usa para no mostrar los inactivos pero
     * seguir teniendolos en bd para historicos
     */
    public static function ObtenerActivos(): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->query("SELECT * FROM servicios WHERE activo = TRUE ORDER BY nombre");

        $servicios = [];
        while ($fila = $stmt->fetch()) {
            // "Mapeamos" los datos de la BD al constructor de tu clase
            $servicios[] = new Servicio(
                $fila['id'],
                $fila['nombre'],
                (float)$fila['precio'],
                (int)$fila['duracion_min'], // Ojo: en la BD es duracion_min
                $fila['descripcion'],
                (bool)$fila['activo']
            );
        }
        return $servicios;
    }

    /**
     * Devuelve un servicio por ID
     * se usaria en editar servicio y
     * al darle resumen al cliente de su reserva con su servicio
     */
    public static function ObtenerById(int $id): ?Servicio {
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
        return null; // <--- IMPRESCINDIBLE: Si no hay fila, devuelve nulo.
    }
}