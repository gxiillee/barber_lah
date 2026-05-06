<?php
// Horario.php
require_once 'BD.php';

class Horario {
    private int $id;
    private int $idBarbero;
    private string $diaSemana;
    private string $horaInicio;
    private string $horaFin;

    public function __construct(int $id, int $idBarbero, string $diaSemana, string $horaInicio, string $horaFin) {
        $this->id         = $id;
        $this->idBarbero  = $idBarbero;
        $this->diaSemana  = $diaSemana;
        $this->horaInicio = $horaInicio;
        $this->horaFin    = $horaFin;
    }

    // Getters
    public function getId(): int            { return $this->id; }
    public function getIdBarbero(): int     { return $this->idBarbero; }
    public function getDiaSemana(): string  { return $this->diaSemana; }
    public function getHoraInicio(): string { return $this->horaInicio; }
    public function getHoraFin(): string    { return $this->horaFin; }


    /**
     * ESTAN POR VER TODAVIA
     */


    // Devuelve el horario de un barbero en un día concreto
    public static function getByBarberoYDia(int $idBarbero, string $dia): ?array {
        $pdo  = BD::obtenerConexion();
        $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id_barbero = :id AND dia_semana = :dia");
        $stmt->execute([':id' => $idBarbero, ':dia' => $dia]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    // Devuelve todos los horarios de un barbero
    public static function getByBarbero(int $idBarbero): array {
        $pdo  = BD::obtenerConexion();
        $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id_barbero = :id ORDER BY id");
        $stmt->execute([':id' => $idBarbero]);
        return $stmt->fetchAll();
    }
}