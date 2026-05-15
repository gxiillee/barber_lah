<?php
declare(strict_types=1);

require_once __DIR__ . '/conexiones/BD.php';

class Barbero {
    private int $id;
    private string $nombre;
    private ?string $telefono;
    private ?string $especialidad;
    private bool $activo;

    public function __construct(int $id, string $nombre, ?string $telefono, ?string $especialidad, bool $activo) {
        $this->id = $id;
        $this->nombre = $nombre;
        $this->telefono = $telefono;
        $this->especialidad = $especialidad;
        $this->activo = $activo;
    }

    // --- Getters ---
    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function getEspecialidad(): ?string { return $this->especialidad; }

    /**
     * Obtiene un barbero por su ID
     */
    public static function obtenerPorId(int $id): ?self {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM barberos WHERE id = :id AND activo = true LIMIT 1");
        $stmt->execute([':id' => $id]);
        $datos = $stmt->fetch();

        if (!$datos) return null;

        return new self(
            (int)$datos['id'],
            $datos['nombre'],
            $datos['telefono'],
            $datos['especialidad'],
            (bool)$datos['activo']
        );
    }

    /**
     * Obtiene todos los barberos activos
     */
    public static function obtenerTodos(): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->query("SELECT * FROM barberos WHERE activo = true ORDER BY nombre ASC");
        $barberos = [];
        while ($datos = $stmt->fetch()) {
            $barberos[] = new self(
                (int)$datos['id'],
                $datos['nombre'],
                $datos['telefono'],
                $datos['especialidad'],
                (bool)$datos['activo']
            );
        }
        return $barberos;
    }
}