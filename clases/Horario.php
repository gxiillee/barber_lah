<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';

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
     * Genera un array de horas (strings) entre dos puntos temporales.
     * Ejemplo: generarSlots('09:00', '11:00', 30) -> ['09:00', '09:30', '10:00', '10:30']
     */
    public static function generarSlots(string $inicio, string $fin, int $intervalo = 30): array {
        $slots = [];
        $actual = new DateTime($inicio);
        $cierre = new DateTime($fin);

        while ($actual < $cierre) {
            $slots[] = $actual->format('H:i');
            $actual->modify("+$intervalo minutes");
        }

        return $slots;
    }

    /**
     * Devuelve el nombre del dia usado por el enum de la base de datos.
     * DateTime::format('N') usa 1=lunes ... 7=domingo.
     */
    public static function nombreDiaDesdeFecha(string $fecha): string {
        $diaNumero = (int)(new DateTimeImmutable($fecha))->format('N');
        $mapa = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo',
        ];

        return $mapa[$diaNumero];
    }

    /**
     * Devuelve los tramos de trabajo de un barbero para una fecha concreta.
     * Se usa para comprobar que un servicio cabe completo antes de cerrar.
     */
    public static function obtenerTramosPorFecha(int $idBarbero, string $fecha): array {
        $conexion = BD::obtenerConexion();
        $dia = self::nombreDiaDesdeFecha($fecha);

        $stmt = $conexion->prepare("
            SELECT hora_inicio, hora_fin
            FROM horarios
            WHERE id_barbero = :id
              AND dia_semana = :dia
            ORDER BY hora_inicio
        ");

        $stmt->execute([':id' => $idBarbero, ':dia' => $dia]);

        return array_map(static function (array $fila): array {
            return [
                'hora_inicio' => substr((string)$fila['hora_inicio'], 0, 5),
                'hora_fin'    => substr((string)$fila['hora_fin'], 0, 5),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}