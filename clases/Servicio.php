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

    // Devuelve todos los servicios activos
    public static function ObtenerActivos(): array {
        $conexion  = BD::obtenerConexion();
        $stmt = $conexion->query("SELECT * FROM servicios WHERE activo = TRUE ORDER BY nombre");
        return $stmt->fetchAll();
    }

    // Devuelve un servicio por id
    public static function ObtenerById(int $id): ?array {
        $conexion  = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM servicios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }
}