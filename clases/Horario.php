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
     * Genera un array de horas (strings) entre dos puntos temporales.
     * Ejemplo: generarSlots('09:00', '11:00', 30) -> ['09:00', '09:30', '10:00', '10:30']
     */
    public static function generarSlots(string $inicio, string $fin, int $intervalo = 30): array {
        $slots = [];
        $actual = new DateTime($inicio);
        $cierre = new DateTime($fin);

        // Mientras la hora actual sea menor al cierre, seguimos generando huecos
        while ($actual < $cierre) {
            $slots[] = $actual->format('H:i');
            $actual->modify("+$intervalo minutes");
        }

        return $slots;
    }

    /**
     * Define la estructura semanal de trabajo.
     * Por ahora la dejamos fija, pero ya preparada para recibir un ID de barbero.
     */
    public static function obtenerPorBarbero(int $idBarbero): array {

        $pdo = BD::obtenerConexion();

        $stmt = $pdo->prepare("
        SELECT dia_semana, hora_inicio, hora_fin
        FROM horarios
        WHERE id_barbero = :id
    ");

        $stmt->execute([':id' => $idBarbero]);

        $resultado = [
            0 => [],
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
            6 => [],
        ];

        $mapaDias = [
            'domingo'   => 0,
            'lunes'     => 1,
            'martes'    => 2,
            'miercoles' => 3,
            'miércoles' => 3,
            'jueves'    => 4,
            'viernes'   => 5,
            'sabado'    => 6,
            'sábado'    => 6,
        ];

        while ($fila = $stmt->fetch()) {

            $diaTexto = mb_strtolower(trim($fila['dia_semana']));

            if (!isset($mapaDias[$diaTexto])) {
                continue;
            }

            $diaNumero = $mapaDias[$diaTexto];

            $slots = self::generarSlots(
                substr($fila['hora_inicio'], 0, 5),
                substr($fila['hora_fin'], 0, 5),
                30
            );

            $resultado[$diaNumero] = array_merge(
                $resultado[$diaNumero],
                $slots
            );
        }

        return $resultado;
    }

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