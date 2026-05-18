<?php
require_once __DIR__ . '/BD.php';
require_once __DIR__ . '/Horario.php';
require_once __DIR__ . '/Bloqueo.php';

class Reserva {

    // ---------------------------------------------------------------
    // PROPIEDADES
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // GETTERS
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // CONSULTAS A LA BASE DE DATOS
    // ---------------------------------------------------------------

    /**
     * Devuelve todas las reservas de un cliente ordenadas de la mas reciente a la mas antigua.
     * Usado en el area privada del cliente para ver su historial de citas.
     */
    public static function getByCliente(int $idCliente): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM reservas WHERE id_cliente = :id ORDER BY fecha DESC, hora DESC");
        $stmt->execute([':id' => $idCliente]);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve las reservas activas (no canceladas) de un barbero en una fecha concreta.
     * Se usa internamente para calcular que huecos estan ya ocupados antes de mostrar
     * la disponibilidad al usuario.
     */
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

    /**
     * Inserta una reserva directamente en la BD sin comprobar disponibilidad ni usar transaccion.
     * Para uso en el panel de administracion, donde el admin puede forzar una cita.
     * En el flujo publico siempre se usa crearConfirmadaSiDisponible en su lugar.
     */
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

    /**
     * Devuelve un mapa [fecha => [hora1, hora2, ...]] con todos los huecos ocupados
     * de un barbero en un mes completo. Util para colorear dias en un calendario mensual.
     */
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

    /**
     * Cambia el estado de una reserva (ej: de 'confirmada' a 'cancelada').
     * Usado en el panel de administracion para gestionar citas.
     */
    public static function cambiarEstado(int $id, string $estado): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("UPDATE reservas SET estado = :estado WHERE id = :id");
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    // ---------------------------------------------------------------
    // LOGICA DE DISPONIBILIDAD
    // El nucleo del sistema de reservas: calcular que huecos estan libres.
    // ---------------------------------------------------------------

    /**
     * Calcula y devuelve todos los huecos horarios disponibles para un barbero
     * en una fecha y duracion de servicio concretas.
     *
     * El proceso es:
     * 1. Descartar si la fecha es pasada o el dia esta bloqueado por completo.
     * 2. Obtener los tramos de horario laboral del barbero ese dia.
     * 3. Recorrer cada tramo generando slots cada 'intervalo' minutos (por defecto 30).
     * 4. Para cada slot, comprobar que no solapa con reservas existentes ni con bloqueos.
     * 5. Descartar slots que ya han pasado si la fecha es hoy.
     */
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

    /**
     * Comprueba si un hueco concreto (fecha + hora) sigue disponible para una duracion dada.
     * Es un envoltorio sobre obtenerSlotsDisponibles que devuelve true/false en vez del array completo.
     * Se usa justo antes de confirmar para detectar si alguien ocupo el hueco mientras el usuario decidia.
     */
    public static function estaDisponible(int $idBarbero, string $fecha, string $hora, int $duracion): bool {
        $horaNormalizada = substr($hora, 0, 5);
        return in_array($horaNormalizada, self::obtenerSlotsDisponibles($idBarbero, $fecha, $duracion), true);
    }

    /**
     * Version segura de crear para el flujo publico. Hace tres cosas clave:
     * 1. Abre una transaccion y bloquea la tabla para que dos usuarios no
     *    puedan reservar el mismo hueco al mismo tiempo (race condition).
     * 2. Comprueba disponibilidad dentro del bloqueo, no antes, para que
     *    la comprobacion y la insercion sean atomicas.
     * 3. Devuelve el id de la nueva reserva, o null si el hueco ya no estaba libre.
     */
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

    // ---------------------------------------------------------------
    // HELPERS PRIVADOS DE FECHA Y HORA
    // Usados internamente para construir DateTimeImmutable y comparar
    // si dos intervalos de tiempo se solapan.
    // ---------------------------------------------------------------

    /**
     * Combina una fecha 'Y-m-d' y una hora 'H:i:s' en un objeto DateTimeImmutable.
     * Se usa en todos los calculos de solapamiento para poder comparar tiempos con operadores.
     */
    private static function crearFechaHora(string $fecha, string $hora): DateTimeImmutable {
        return new DateTimeImmutable($fecha . ' ' . substr($hora, 0, 5));
    }

    /**
     * Comprueba si dos intervalos de tiempo se solapan.
     * La condicion A.inicio < B.fin && A.fin > B.inicio cubre todos los casos de solapamiento posibles:
     * parcial por la izquierda, parcial por la derecha, uno dentro del otro y exactamente iguales.
     */
    private static function intervalosSolapan(DateTimeImmutable $inicioA, DateTimeImmutable $finA, DateTimeImmutable $inicioB, DateTimeImmutable $finB): bool {
        return $inicioA < $finB && $finA > $inicioB;
    }

    /**
     * Comprueba si un slot propuesto solapa con alguna de las reservas ya existentes ese dia.
     * Cada reserva ocupa desde su hora hasta su hora + duracion_historica minutos.
     */
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

    /**
     * Comprueba si un slot propuesto solapa con algun bloqueo manual del barbero ese dia.
     * Si el bloqueo no tiene hora_inicio o hora_fin es un bloqueo de dia completo: bloquea todo.
     */
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

    // ---------------------------------------------------------------
    // UTILIDADES DE FECHA (INTERFAZ PUBLICA)
    // Convierten fechas en texto legible para el usuario y calculan
    // rangos de semanas para el selector de calendario.
    // ---------------------------------------------------------------

    /**
     * Convierte el numero de mes (1-12) en su nombre en español.
     * Privado porque solo lo usan otros metodos de esta clase.
     */
    private static function nombreMes(int $mes): string {
        $meses = [
            1  => 'enero',    2  => 'febrero',   3  => 'marzo',
            4  => 'abril',    5  => 'mayo',       6  => 'junio',
            7  => 'julio',    8  => 'agosto',     9  => 'septiembre',
            10 => 'octubre',  11 => 'noviembre',  12 => 'diciembre',
        ];
        return $meses[$mes] ?? '';
    }

    /**
     * Convierte el numero ISO del dia de la semana (1=lunes, 7=domingo) en su nombre en español.
     * Privado porque solo lo usan otros metodos de esta clase.
     */
    private static function nombreDia(int $dia): string {
        $dias = [
            1 => 'lunes',   2 => 'martes',    3 => 'miercoles',
            4 => 'jueves',  5 => 'viernes',   6 => 'sabado',
            7 => 'domingo',
        ];
        return $dias[$dia] ?? '';
    }

    /**
     * Convierte una fecha 'Y-m-d' en texto legible para el usuario: "lunes 3 de junio".
     * Se usa para mostrar la fecha de la reserva en la sesion y en las vistas de confirmacion.
     */
    public static function fechaHumana(string $fecha): string {
        $dt = new DateTimeImmutable($fecha);
        return self::nombreDia((int)$dt->format('N'))
            . ' ' . $dt->format('j')
            . ' de ' . self::nombreMes((int)$dt->format('n'));
    }

    /**
     * Comprueba que una cadena es una fecha valida en formato 'Y-m-d'.
     * Protege contra fechas inventadas como '2025-02-31' que PHP a veces acepta sin quejarse.
     */
    public static function fechaValida(string $fecha): bool {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $fecha;
    }

    /**
     * Devuelve el lunes de la semana a la que pertenece la fecha recibida.
     * Ejemplo: si la fecha es un miercoles, retorna el lunes de esa misma semana.
     * Recibe y devuelve DateTimeImmutable para encadenar operaciones de fecha.
     */
    public static function inicioSemana(DateTimeImmutable $fecha): DateTimeImmutable {
        return $fecha->modify('-' . ((int)$fecha->format('N') - 1) . ' days');
    }

    /**
     * Igual que inicioSemana pero recibe y devuelve string 'Y-m-d'.
     * Util cuando la fecha llega como string desde la sesion o desde la URL.
     */
    public static function inicioSemanaStr(string $fecha): string {
        return self::inicioSemana(new DateTimeImmutable($fecha))->format('Y-m-d');
    }

    /**
     * Genera el titulo de la semana para mostrarlo encima del calendario.
     * Si la semana abarca dos meses distintos lo indica: "mayo / junio 2025".
     * Si esta dentro del mismo mes: "mayo 2025".
     */
    public static function tituloSemana(DateTimeImmutable $inicioSemana): string {
        $finSemana = $inicioSemana->modify('+6 days');
        $mesInicio = self::nombreMes((int)$inicioSemana->format('n'));
        $mesFin    = self::nombreMes((int)$finSemana->format('n'));

        return $mesInicio === $mesFin
            ? $mesInicio . ' ' . $inicioSemana->format('Y')
            : $mesInicio . ' / ' . $mesFin . ' ' . $inicioSemana->format('Y');
    }

    /**
     * Calcula el estado de los botones de navegacion del calendario semanal.
     * Devuelve las fechas de la semana anterior y siguiente, y dos booleanos
     * que indican si se puede ir atras (no antes de hoy) y si se puede ir adelante
     * (no mas de 12 semanas desde hoy, definido en reserva.php).
     */
    public static function estadoNavegacion(
        DateTimeImmutable $inicioSemana,
        DateTimeImmutable $semanaActual,
        DateTimeImmutable $semanaMaxima
    ): array {
        $prevSemana = $inicioSemana->modify('-7 days');
        $sigSemana  = $inicioSemana->modify('+7 days');

        return [
            'prev'             => $prevSemana->format('Y-m-d'),
            'next'             => $sigSemana->format('Y-m-d'),
            'puede_retroceder' => $prevSemana >= $semanaActual,
            'puede_avanzar'    => $sigSemana  <= $semanaMaxima,
        ];
    }

    // ---------------------------------------------------------------
    // CONSTRUCCION DE DATOS PARA LA VISTA
    // Metodos que preparan los arrays que la vista necesita renderizar
    // y que el JS necesita recibir como JSON. Centralizan aqui toda
    // esa logica para que reserva.php solo asigne variables.
    // ---------------------------------------------------------------

    /**
     * Construye el array de los 7 dias de la semana visible en el calendario.
     * Cada elemento tiene todo lo que la vista necesita: fecha ISO para el JS,
     * nombres corto y largo para mostrarlo, numero del dia, si es hoy y si ya paso
     * (los dias pasados se muestran deshabilitados en el selector).
     */
    public static function construirDiasSemana(DateTimeImmutable $inicioSemana, DateTimeImmutable $hoy): array {
        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $fecha    = $inicioSemana->modify('+' . $i . ' days');
            $fechaIso = $fecha->format('Y-m-d');
            $dias[]   = [
                'fecha'     => $fechaIso,
                'dia_corto' => ucfirst(substr(self::nombreDia((int)$fecha->format('N')), 0, 3)),
                'dia_largo' => self::fechaHumana($fechaIso),
                'numero'    => $fecha->format('j'),
                'mes'       => self::nombreMes((int)$fecha->format('n')),
                'es_hoy'    => $fechaIso === $hoy->format('Y-m-d'),
                'pasado'    => $fecha < $hoy,
            ];
        }
        return $dias;
    }

    /**
     * Prepara los datos de los servicios en el formato que necesita el JS del selector.
     * El JS los guarda en la constante reservaServicios y los usa para actualizar
     * el resumen lateral cuando el usuario cambia de servicio sin recargar pagina.
     */
    public static function construirServiciosJson(array $serviciosPorId): array {
        $serviciosJson = [];
        foreach ($serviciosPorId as $id => $servicio) {
            $serviciosJson[$id] = [
                'id'                => $servicio->getIdServicio(),
                'nombre'            => $servicio->getNombre(),
                'precio'            => $servicio->getPrecio(),
                'precio_formateado' => number_format($servicio->getPrecio(), 2, ',', '.') . ' €',
                'duracion'          => $servicio->getDuracion(),
                'descripcion'       => $servicio->getDescripcion() ?? '',
            ];
        }
        return $serviciosJson;
    }

    /**
     * Construye la matriz de disponibilidad que el JS usa para mostrar huecos sin llamadas AJAX.
     * Estructura: [id_servicio][fecha] => ['09:00', '09:30', '10:00', ...]
     * Se calcula de una vez para todos los servicios y todos los dias de la semana visible,
     * de forma que cambiar de servicio o de dia en el selector es instantaneo.
     */
    public static function construirDisponibilidad(int $idBarbero, array $serviciosPorId, array $diasSemana): array {
        $disponibilidad = [];
        foreach ($serviciosPorId as $id => $servicio) {
            foreach ($diasSemana as $dia) {
                $disponibilidad[$id][$dia['fecha']] = self::obtenerSlotsDisponibles(
                    $idBarbero,
                    $dia['fecha'],
                    $servicio->getDuracion()
                );
            }
        }
        return $disponibilidad;
    }
}