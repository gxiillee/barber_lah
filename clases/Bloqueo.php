<?php
// Bloqueo.php
require_once 'BD.php';

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
     * ESTA POR VER
     */




    // Comprueba si un barbero tiene el día completo bloqueado
    public function esDiaCompleto(): bool {
        return $this->horaInicio === null && $this->horaFin === null;
    }

    // Devuelve los bloqueos de un barbero en una fecha concreta
    public static function getByBarberoYFecha(int $idBarbero, string $fecha): array {
        $pdo  = BD::obtenerConexion();
        $stmt = $pdo->prepare("SELECT * FROM bloqueos WHERE id_barbero = :id AND fecha = :fecha");
        $stmt->execute([':id' => $idBarbero, ':fecha' => $fecha]);
        return $stmt->fetchAll();
    }
}