<?php
// Reserva.php
require_once 'BD.php';

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

    // Getters
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

    /**
     * Devuelve todas las reservas por cliente
     * Se puede usar en el historial del cliente mostrandole
     * su historial
     */
    // Devuelve todas las reservas de un cliente
    public static function getByCliente(int $idCliente): array {
        $conexion  = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM reservas WHERE id_cliente = :id ORDER BY fecha DESC, hora DESC");
        $stmt->execute([':id' => $idCliente]);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve reservas por barbero y fecha.
     * se usa para ver cada barber sus citas del dia
     */
    // Devuelve las reservas de un barbero en una fecha concreta
    public static function getByBarberoYFecha(int $idBarbero, string $fecha): array {
        $conexion  = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM reservas WHERE id_barbero = :id AND fecha = :fecha AND estado NOT IN ('cancelada') ORDER BY hora");
        $stmt->execute([':id' => $idBarbero, ':fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva reserva con su cliente barbero servicio fecha....
     * Crea una reserva nueva y devuelve el id generado
     */
    public static function crear(int $idCliente, int $idBarbero, int $idServicio, string $fecha, string $hora, float $precio, int $duracion, ?string $nota): int {
        $conexion  = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            INSERT INTO reservas (id_cliente, id_barbero, id_servicio, fecha, hora, precio_historico, duracion_historica, nota)
            VALUES (:cliente, :barbero, :servicio, :fecha, :hora, :precio, :duracion, :nota)
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
        return (int) $conexion->lastInsertId();
    }

    /**
     * Cambia el estado de una reserva
     * se usa para poner confirmada, completada, cancelada
     */
    public static function cambiarEstado(int $id, string $estado): bool {
        $conexion  = BD::obtenerConexion();
        $stmt = $conexion->prepare("UPDATE reservas SET estado = :estado WHERE id = :id");
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }
}