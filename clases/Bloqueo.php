<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';

class Bloqueo {
    private int $id;
    private int $idBarbero;
    private string $fecha;
    private ?string $horaInicio;
    private ?string $horaFin;
    private ?string $motivo;

    public function __construct(int $id, int $idBarbero, string $fecha, ?string $horaInicio, ?string $horaFin, ?string $motivo) {
        $this->id         = $id;
        $this->idBarbero  = $idBarbero;
        $this->fecha      = $fecha;
        $this->horaInicio = $horaInicio;
        $this->horaFin    = $horaFin;
        $this->motivo     = $motivo;
    }

    // Getters
    public function getId(): int             { return $this->id; }
    public function getIdBarbero(): int      { return $this->idBarbero; }
    public function getFecha(): string       { return $this->fecha; }
    public function getHoraInicio(): ?string { return $this->horaInicio; }
    public function getHoraFin(): ?string    { return $this->horaFin; }
    public function getMotivo(): ?string     { return $this->motivo; }

    /**
     * Mira si es un dia bloqueado completo
     */
    public static function esDiaBloqueadoCompleto(int $idBarbero, string $fecha): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT COUNT(*) FROM bloqueos
        WHERE id_barbero = :id
          AND fecha = :fecha
          AND hora_inicio IS NULL
          AND hora_fin IS NULL
        ");
        $stmt->execute([':id' => $idBarbero, ':fecha' => $fecha]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Devuelve los bloqueos de un barbero en una fecha concreta
     */
    public static function obtenerPorFecha(int $idBarbero, string $fecha): array {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT hora_inicio, hora_fin
            FROM bloqueos
            WHERE id_barbero = :id
              AND fecha = :fecha
        ");

        $stmt->execute([
            ':id' => $idBarbero,
            ':fecha' => $fecha
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}