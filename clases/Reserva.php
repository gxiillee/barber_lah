<?php
// Reserva.php
require_once __DIR__ . '/BD.php';
require_once __DIR__ . '/Horario.php';
require_once __DIR__ . '/Bloqueo.php';

class Reserva {
    private int $id;
    private int $idCliente;
    private int $idBarbero;
    private int $idServicio;
    private string $fecha;
    private string $hora;
    private float $precioHistorico;
    private int $duracionHistorica;
    private string $estado;
    private ?string $nota;
    private string $createdAt;

    public function __construct(int $id, int $idCliente, int $idBarbero, int $idServicio, string $fecha, string $hora, float $precioHistorico, int $duracionHistorica, string $estado, ?string $nota, string $createdAt) {
        $this->id                = $id;
        $this->idCliente         = $idCliente;
        $this->idBarbero         = $idBarbero;
        $this->idServicio        = $idServicio;
        $this->fecha             = $fecha;
        $this->hora              = $hora;
        $this->precioHistorico   = $precioHistorico;
        $this->duracionHistorica = $duracionHistorica;
        $this->estado            = $estado;
        $this->nota              = $nota;
        $this->createdAt         = $createdAt;
    }

    public function getId(): int                { return $this->id; }
    public function getIdCliente(): int         { return $this->idCliente; }
    public function getIdBarbero(): int         { return $this->idBarbero; }
    public function getIdServicio(): int        { return $this->idServicio; }
    public function getFecha(): string          { return $this->fecha; }
    public function getHora(): string           { return $this->hora; }
    public function getPrecioHistorico(): float { return $this->precioHistorico; }
    public function getDuracionHistorica(): int { return $this->duracionHistorica; }
    public function getEstado(): string         { return $this->estado; }
    public function getNota(): ?string          { return $this->nota; }
    public function getCreatedAt(): string      { return $this->createdAt; }

    public static function getByCliente(int $idCliente): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM reservas WHERE id_cliente = :id ORDER BY fecha DESC, hora DESC");
        $stmt->execute([':id' => $idCliente]);
        return $stmt->fetchAll();
    }

    public static function getByBarberoYFecha(int $idBarbero, string $fecha): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT *
            FROM reservas
            WHERE id_barbero = :id
              AND fecha = :fecha
              AND estado NOT IN ('cancelada')
            ORDER BY hora
        ");
        $stmt->execute([':id' => $idBarbero, ':fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    public static function crear(int $idCliente, int $idBarbero, int $idServicio, string $fecha, string $hora, float $precio, int $duracion, ?string $nota): int {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            INSERT INTO reservas (id_cliente, id_barbero, id_servicio, fecha, hora, precio_historico, duracion_historica, nota)
            VALUES (:cliente, :barbero, :servicio, :fecha, :hora, :precio, :duracion, :nota)
            RETURNING id
        ");
        $stmt->execute([
            ':cliente'  => $idCliente,
            ':barbero'  => $idBarbero,
            ':servicio' => $idServicio,
            ':fecha'    => $fecha,
            ':hora'     => $hora,
            ':precio'   => $precio,
            ':duracion' => $duracion,
            ':nota'     => $nota
        ]);

        return (int)$stmt->fetchColumn();
    }

    public static function obtenerOcupadasPorMes(int $idBarbero, int $mes, int $anyo): array {
        $conexion = BD::obtenerConexion();
        $inicioMes = sprintf('%04d-%02d-01', $anyo, $mes);
        $finMes = date('Y-m-t', strtotime($inicioMes));

        $stmt = $conexion->prepare("
            SELECT fecha, hora
            FROM reservas
            WHERE id_barbero = :barbero
              AND fecha BETWEEN :inicio AND :fin
              AND estado NOT IN ('cancelada')
        ");

        $stmt->execute([
            ':barbero' => $idBarbero,
            ':inicio'  => $inicioMes,
            ':fin'     => $finMes
        ]);

        $ocupadas = [];
        while ($fila = $stmt->fetch()) {
            $fecha = (string)$fila['fecha'];
            $hora = substr((string)$fila['hora'], 0, 5);

            if (!isset($ocupadas[$fecha])) {
                $ocupadas[$fecha] = [];
            }

            $ocupadas[$fecha][] = $hora;
        }

        return $ocupadas;
    }

    public static function cambiarEstado(int $id, string $estado): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("UPDATE reservas SET estado = :estado WHERE id = :id");
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    public static function obtenerSlotsDisponibles(int $idBarbero, string $fecha, int $duracion, int $intervalo = 30): array {
        if ($duracion <= 0 || $intervalo <= 0) {
            return [];
        }

        $hoy = new DateTimeImmutable('today');
        $fechaDia = new DateTimeImmutable($fecha);

        if ($fechaDia < $hoy || Bloqueo::esDiaBloqueadoCompleto($idBarbero, $fecha)) {
            return [];
        }

        $tramos = Horario::obtenerTramosPorFecha($idBarbero, $fecha);
        if ($tramos === []) {
            return [];
        }

        $reservas = self::getByBarberoYFecha($idBarbero, $fecha);
        $bloqueos = Bloqueo::obtenerPorFecha($idBarbero, $fecha);
        $ahora = new DateTimeImmutable('now');
        $slots = [];

        foreach ($tramos as $tramo) {
            $actual = self::crearFechaHora($fecha, $tramo['hora_inicio']);
            $finTramo = self::crearFechaHora($fecha, $tramo['hora_fin']);

            while ($actual->modify('+' . $duracion . ' minutes') <= $finTramo) {
                $slotFin = $actual->modify('+' . $duracion . ' minutes');

                if ($fechaDia->format('Y-m-d') === $hoy->format('Y-m-d') && $actual <= $ahora) {
                    $actual = $actual->modify('+' . $intervalo . ' minutes');
                    continue;
                }

                if (
                    !self::solapaConReservas($actual, $slotFin, $reservas, $fecha)
                    && !self::solapaConBloqueos($actual, $slotFin, $bloqueos, $fecha)
                ) {
                    $slots[] = $actual->format('H:i');
                }

                $actual = $actual->modify('+' . $intervalo . ' minutes');
            }
        }

        $slots = array_values(array_unique($slots));
        sort($slots);

        return $slots;
    }

    public static function estaDisponible(int $idBarbero, string $fecha, string $hora, int $duracion): bool {
        $horaNormalizada = substr($hora, 0, 5);
        return in_array($horaNormalizada, self::obtenerSlotsDisponibles($idBarbero, $fecha, $duracion), true);
    }

    public static function crearConfirmadaSiDisponible(int $idCliente, int $idBarbero, int $idServicio, string $fecha, string $hora, float $precio, int $duracion, ?string $nota = null): ?int {
        $conexion = BD::obtenerConexion();

        try {
            $conexion->beginTransaction();
            $conexion->exec('LOCK TABLE reservas IN SHARE ROW EXCLUSIVE MODE');

            if (!self::estaDisponible($idBarbero, $fecha, $hora, $duracion)) {
                $conexion->rollBack();
                return null;
            }

            $stmt = $conexion->prepare("
                INSERT INTO reservas (id_cliente, id_barbero, id_servicio, fecha, hora, precio_historico, duracion_historica, estado, nota)
                VALUES (:cliente, :barbero, :servicio, :fecha, :hora, :precio, :duracion, 'confirmada', :nota)
                RETURNING id
            ");

            $stmt->execute([
                ':cliente'  => $idCliente,
                ':barbero'  => $idBarbero,
                ':servicio' => $idServicio,
                ':fecha'    => $fecha,
                ':hora'     => $hora,
                ':precio'   => $precio,
                ':duracion' => $duracion,
                ':nota'     => $nota
            ]);

            $id = (int)$stmt->fetchColumn();
            $conexion->commit();

            return $id;
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    private static function crearFechaHora(string $fecha, string $hora): DateTimeImmutable {
        return new DateTimeImmutable($fecha . ' ' . substr($hora, 0, 5));
    }

    private static function intervalosSolapan(DateTimeImmutable $inicioA, DateTimeImmutable $finA, DateTimeImmutable $inicioB, DateTimeImmutable $finB): bool {
        return $inicioA < $finB && $finA > $inicioB;
    }

    private static function solapaConReservas(DateTimeImmutable $slotInicio, DateTimeImmutable $slotFin, array $reservas, string $fecha): bool {
        foreach ($reservas as $reserva) {
            $reservaInicio = self::crearFechaHora($fecha, (string)$reserva['hora']);
            $reservaFin = $reservaInicio->modify('+' . (int)$reserva['duracion_historica'] . ' minutes');

            if (self::intervalosSolapan($slotInicio, $slotFin, $reservaInicio, $reservaFin)) {
                return true;
            }
        }

        return false;
    }

    private static function solapaConBloqueos(DateTimeImmutable $slotInicio, DateTimeImmutable $slotFin, array $bloqueos, string $fecha): bool {
        foreach ($bloqueos as $bloqueo) {
            if (empty($bloqueo['hora_inicio']) || empty($bloqueo['hora_fin'])) {
                return true;
            }

            $bloqueoInicio = self::crearFechaHora($fecha, (string)$bloqueo['hora_inicio']);
            $bloqueoFin = self::crearFechaHora($fecha, (string)$bloqueo['hora_fin']);

            if (self::intervalosSolapan($slotInicio, $slotFin, $bloqueoInicio, $bloqueoFin)) {
                return true;
            }
        }

        return false;
    }
}
