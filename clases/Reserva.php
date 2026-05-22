<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';
require_once __DIR__ . '/Horario.php';
require_once __DIR__ . '/Bloqueo.php';
// fechaHumana(), nombreMes() y nombreDia() vienen de helpers.php (cargado en el bootstrap)

class Reserva {

    // ---------------------------------------------------------------
    // PROPIEDADES
    // ---------------------------------------------------------------

    private int     $id;
    private int     $idCliente;
    private int     $idBarbero;
    private int     $idServicio;
    private string  $fecha;
    private string  $hora;
    private float   $precioHistorico;
    private int     $duracionHistorica;
    private string  $estado;
    private ?string $nota;
    private string  $createdAt;

    // ---------------------------------------------------------------
    // CONSTRUCTOR
    // ---------------------------------------------------------------

    public function __construct(
        int     $id,
        int     $idCliente,
        int     $idBarbero,
        int     $idServicio,
        string  $fecha,
        string  $hora,
        float   $precioHistorico,
        int     $duracionHistorica,
        string  $estado,
        ?string $nota,
        string  $createdAt
    ) {
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
    // ÁREA CLIENTE — consumido en mi-cuenta.php (pendiente de construir)
    // ---------------------------------------------------------------

    /**
     * Devuelve el historial de reservas de un cliente, de la más reciente a la más antigua.
     * Se usará en el área privada para que el cliente consulte sus citas pasadas y futuras.
     */
    public static function getByCliente(int $idCliente): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT *
            FROM   reservas
            WHERE  id_cliente = :id
            ORDER  BY fecha DESC, hora DESC
        ");
        $stmt->execute([':id' => $idCliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===============================================================
    // MÉTODOS DE GESTIÓN ADMINISTRATIVA (PREPARADOS PARA EL FUTURO)
    // ===============================================================
    //
    // Estos métodos forman la interfaz que el panel de administración
    // usará para interactuar con la entidad Reserva de forma centralizada.
    //
    // Principio de diseño:
    //   Toda operación sobre la tabla 'reservas' pasa por esta clase,
    //   tanto el flujo público (crearAtomicamente) como el administrativo
    //   (crear y cambiarEstado). Así el panel de admin nunca ejecuta SQL
    //   directamente: delega en el modelo, que es el único responsable
    //   de la integridad de los datos.
    //
    // Por qué crear() no usa transacción ni bloqueo:
    //   El administrador actúa con privilegios elevados y conocimiento
    //   del sistema. Puede necesitar forzar una cita en un hueco ya
    //   ocupado (reagendaciones, excepciones) o insertar datos históricos.
    //   En ese contexto, el bloqueo pesimista de crearAtomicamente()
    //   sería una restricción, no una protección.
    // ===============================================================

    /**
     * INSERT directo en la BD sin transacción ni comprobación de disponibilidad.
     * USO EXCLUSIVO DEL PANEL DE ADMINISTRACIÓN.
     *
     * En el flujo público de reservas siempre se usa crearAtomicamente() en su lugar,
     * que garantiza atomicidad y protege contra condiciones de carrera.
     *
     * @return int  ID de la reserva recién insertada (PostgreSQL RETURNING id).
     */
    public static function crear(
        int     $idCliente,
        int     $idBarbero,
        int     $idServicio,
        string  $fecha,
        string  $hora,
        float   $precio,
        int     $duracion,
        ?string $nota
    ): int {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            INSERT INTO reservas
                (id_cliente, id_barbero, id_servicio, fecha, hora,
                 precio_historico, duracion_historica, nota)
            VALUES
                (:cliente, :barbero, :servicio, :fecha, :hora,
                 :precio, :duracion, :nota)
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
            ':nota'     => $nota,
        ]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Cambia el estado de una reserva a cualquier valor del enum del dominio:
     * 'confirmada' | 'cancelada' | 'completada' | 'no_presentado'.
     * USO EXCLUSIVO DEL PANEL DE ADMINISTRACIÓN.
     *
     * El admin la usa para marcar citas como completadas (lo que dispara
     * el envío del token de reseña verificada) o para cancelar citas en nombre del cliente.
     *
     * @return bool  true si el UPDATE afectó al menos una fila.
     */
    public static function cambiarEstado(int $id, string $estado): bool {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            UPDATE reservas
            SET    estado = :estado
            WHERE  id = :id
        ");
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    // ---------------------------------------------------------------
    // LÓGICA DE DISPONIBILIDAD — núcleo del sistema de reservas
    // ---------------------------------------------------------------

    /**
     * Devuelve los huecos horarios disponibles para un barbero en una fecha y duración dadas.
     *
     * Algoritmo (5 pasos):
     *   1. Descarte rápido: fecha pasada o día bloqueado por completo → devuelve [].
     *   2. Carga los tramos laborales del barbero para ese día de la semana.
     *      Si no trabaja ese día → devuelve [].
     *   3. Carga reservas activas y bloqueos parciales del día (1 consulta cada uno,
     *      fuera del bucle para no repetirlas por cada candidato).
     *   4. Recorre cada tramo generando candidatos cada 'intervalo' minutos (30 por defecto).
     *      Descarta los que ya pasaron (si la fecha es hoy).
     *   5. Acepta solo los candidatos que no solapen con reservas ni con bloqueos.
     *
     * @param  int    $idBarbero  ID del barbero (solo hay uno en este proyecto: Hassan).
     * @param  string $fecha      Fecha en formato 'Y-m-d'.
     * @param  int    $duracion   Duración del servicio en minutos.
     * @param  int    $intervalo  Granularidad del calendario en minutos (defecto: 30).
     * @return array<string>      Array de horas disponibles en formato 'H:i', ordenado ASC.
     */
    public static function obtenerSlotsDisponibles(
        int $idBarbero,
        string $fecha,
        int $duracion,
        int $intervalo = 30
    ): array {
        if ($duracion <= 0 || $intervalo <= 0) {
            return [];
        }

        $hoy      = new DateTimeImmutable('today');
        $fechaDia = new DateTimeImmutable($fecha);

        // Paso 1: descartes rápidos sin ir a la BD
        if ($fechaDia < $hoy || Bloqueo::esDiaBloqueadoCompleto($idBarbero, $fecha)) {
            return [];
        }

        // Paso 2: tramos laborales del barbero para ese día de la semana
        $tramos = Horario::obtenerTramosPorFecha($idBarbero, $fecha);
        if ($tramos === []) {
            return [];
        }

        // Paso 3: cargamos reservas y bloqueos una sola vez para todos los candidatos
        $reservas = self::getByBarberoYFecha($idBarbero, $fecha);
        $bloqueos = Bloqueo::obtenerPorFecha($idBarbero, $fecha);
        $ahora    = new DateTimeImmutable('now');
        $slots    = [];

        // Pasos 4 y 5: recorrido de tramos y filtrado de candidatos
        foreach ($tramos as $tramo) {
            $actual   = self::combinarFechaHora($fecha, $tramo['hora_inicio']);
            $finTramo = self::combinarFechaHora($fecha, $tramo['hora_fin']);

            // Condición del while: el servicio completo debe caber dentro del tramo
            while ($actual->modify("+{$duracion} minutes") <= $finTramo) {
                $slotFin = $actual->modify("+{$duracion} minutes");

                // Paso 4: descartar huecos ya pasados si es hoy
                if ($fechaDia->format('Y-m-d') === $hoy->format('Y-m-d') && $actual <= $ahora) {
                    $actual = $actual->modify("+{$intervalo} minutes");
                    continue;
                }

                // Paso 5: aceptar solo si el candidato está libre de reservas y bloqueos
                if (
                    !self::solapaConReservas($actual, $slotFin, $reservas, $fecha)
                    && !self::solapaConBloqueos($actual, $slotFin, $bloqueos, $fecha)
                ) {
                    $slots[] = $actual->format('H:i');
                }

                $actual = $actual->modify("+{$intervalo} minutes");
            }
        }

        sort($slots);
        return array_values(array_unique($slots));
    }

    /**
     * Comprueba si un hueco concreto (barbero + fecha + hora + duración) sigue disponible.
     *
     * Es un envoltorio booleano sobre obtenerSlotsDisponibles().
     * Se invoca en dos momentos distintos del flujo con propósitos distintos:
     *   - En reserva.php (POST): comprobación UX antes de guardar en sesión.
     *     Si el hueco ya no existe, el usuario no llega a confirmar_reserva.php.
     *   - Dentro de crearAtomicamente(): comprobación real bajo bloqueo PostgreSQL.
     *     Esta es la que garantiza la atomicidad; la anterior es solo comodidad.
     */
    public static function estaDisponible(
        int $idBarbero,
        string $fecha,
        string $hora,
        int $duracion
    ): bool {
        $horaNormalizada = substr($hora, 0, 5);
        return in_array(
            $horaNormalizada,
            self::obtenerSlotsDisponibles($idBarbero, $fecha, $duracion),
            true
        );
    }

    /**
     * Crea una reserva de forma atómica garantizando que no existe condición de carrera.
     *
     * Por qué necesita transacción + LOCK:
     *   Sin este mecanismo, dos usuarios podrían superar la comprobación de disponibilidad
     *   simultáneamente, ver el hueco libre ambos e insertar la misma cita (race condition).
     *   El LOCK pesimista (SHARE ROW EXCLUSIVE) bloquea escrituras concurrentes sobre
     *   'reservas' hasta que esta transacción confirme o deshaga.
     *   La comprobación de disponibilidad ocurre DENTRO del bloqueo, no antes,
     *   para que la verificación y el INSERT sean una operación atómica indivisible.
     *
     * @return int|null  ID de la nueva reserva si el hueco estaba libre; null si ya estaba ocupado.
     * @throws Throwable Relanza cualquier excepción de BD tras deshacer la transacción.
     */
    public static function crearAtomicamente(
        int     $idCliente,
        int     $idBarbero,
        int     $idServicio,
        string  $fecha,
        string  $hora,
        float   $precio,
        int     $duracion,
        ?string $nota = null
    ): ?int {
        $conexion = BD::obtenerConexion();

        try {
            $conexion->beginTransaction();

            // Bloqueo pesimista a nivel de tabla: ninguna escritura concurrente
            // puede insertar en 'reservas' hasta que esta transacción termine.
            $conexion->exec('LOCK TABLE reservas IN SHARE ROW EXCLUSIVE MODE');

            // Comprobamos disponibilidad DENTRO del bloqueo.
            // Si el hueco ya fue ocupado por otra sesión concurrente, abortamos.
            if (!self::estaDisponible($idBarbero, $fecha, $hora, $duracion)) {
                $conexion->rollBack();
                return null;
            }

            $stmt = $conexion->prepare("
                INSERT INTO reservas
                    (id_cliente, id_barbero, id_servicio, fecha, hora,
                     precio_historico, duracion_historica, estado, nota)
                VALUES
                    (:cliente, :barbero, :servicio, :fecha, :hora,
                     :precio, :duracion, 'confirmada', :nota)
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
                ':nota'     => $nota,
            ]);

            $id = (int)$stmt->fetchColumn();
            $conexion->commit();
            return $id;

        } catch (Throwable $e) {
            // Garantizamos el rollback aunque el fallo ocurra en la comprobación,
            // en el INSERT o incluso en el propio commit.
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e; // Relanzamos para que el controlador pueda registrar el error
        }
    }

    // ---------------------------------------------------------------
    // HELPERS PRIVADOS — fechas y detección de solapamientos
    // ---------------------------------------------------------------

    /**
     * Consulta interna de reservas activas de un barbero en una fecha.
     * private: solo la necesita obtenerSlotsDisponibles() para construir
     * los objetos DateTimeImmutable del algoritmo de solapamiento.
     * Exponer tramos en bruto fuera de la clase violaría la encapsulación.
     *
     * SELECT reducido a las dos columnas que realmente consume el algoritmo.
     */
    private static function getByBarberoYFecha(int $idBarbero, string $fecha): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT hora, duracion_historica
            FROM   reservas
            WHERE  id_barbero = :id
              AND  fecha      = :fecha
              AND  estado NOT IN ('cancelada')
            ORDER  BY hora
        ");
        $stmt->execute([':id' => $idBarbero, ':fecha' => $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Combina fecha 'Y-m-d' y hora 'H:i[:ss]' en un DateTimeImmutable.
     * Renombrado de crearFechaHora() a combinarFechaHora() para que el nombre
     * describa qué hace sin depender de conocer su implementación.
     */
    private static function combinarFechaHora(string $fecha, string $hora): DateTimeImmutable {
        return new DateTimeImmutable($fecha . ' ' . substr($hora, 0, 5));
    }

    /**
     * Determina si dos intervalos [inicioA, finA) y [inicioB, finB) se solapan.
     * La condición A.inicio < B.fin && A.fin > B.inicio cubre todos los casos:
     * solapamiento parcial por izquierda, por derecha, uno dentro del otro e iguales.
     */
    private static function intervalosSolapan(
        DateTimeImmutable $inicioA,
        DateTimeImmutable $finA,
        DateTimeImmutable $inicioB,
        DateTimeImmutable $finB
    ): bool {
        return $inicioA < $finB && $finA > $inicioB;
    }

    /**
     * Comprueba si un candidato [slotInicio, slotFin) choca con alguna reserva del día.
     * Cada reserva ocupa [hora, hora + duracion_historica minutos).
     */
    private static function solapaConReservas(
        DateTimeImmutable $slotInicio,
        DateTimeImmutable $slotFin,
        array             $reservas,
        string            $fecha
    ): bool {
        foreach ($reservas as $reserva) {
            $rInicio = self::combinarFechaHora($fecha, (string)$reserva['hora']);
            $rFin    = $rInicio->modify('+' . (int)$reserva['duracion_historica'] . ' minutes');

            if (self::intervalosSolapan($slotInicio, $slotFin, $rInicio, $rFin)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Comprueba si un candidato [slotInicio, slotFin) choca con algún bloqueo parcial.
     * Los bloqueos de día completo (sin hora) ya se descartan antes en esDiaBloqueadoCompleto().
     * Si por alguna razón llega aquí uno sin hora, se trata como bloqueo total (defensa extra).
     */
    private static function solapaConBloqueos(
        DateTimeImmutable $slotInicio,
        DateTimeImmutable $slotFin,
        array             $bloqueos,
        string            $fecha
    ): bool {
        foreach ($bloqueos as $bloqueo) {
            if (empty($bloqueo['hora_inicio']) || empty($bloqueo['hora_fin'])) {
                return true; // Defensa extra: bloqueo sin horas = día completo bloqueado
            }

            $bInicio = self::combinarFechaHora($fecha, (string)$bloqueo['hora_inicio']);
            $bFin    = self::combinarFechaHora($fecha, (string)$bloqueo['hora_fin']);

            if (self::intervalosSolapan($slotInicio, $slotFin, $bInicio, $bFin)) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------
    // CONSTRUCTORES DE DATOS PARA LA VISTA
    // Preparan los arrays y matrices que reserva.php necesita para
    // el HTML inicial y que el JS consume como JSON local.
    //
    // Por qué están en el modelo y no en la vista:
    //   Estos métodos no generan HTML; transforman datos de dominio
    //   (fechas, servicios, disponibilidad) en estructuras serializables
    //   que el JS puede leer sin peticiones adicionales al servidor.
    //   Cambiar de servicio o de día en el selector es O(1) en el cliente:
    //   consulta el array local en vez de hacer fetch. Al navegar de semana
    //   sí se hace un fetch, pero devuelve esta misma estructura ya calculada.
    // ---------------------------------------------------------------

    /**
     * Construye el array de los 7 días de la semana visible.
     * Cada elemento contiene todo lo que la vista y el JS necesitan:
     * fecha ISO, nombre corto/largo, número del día, mes y si está pasado.
     *
     * Usa las funciones globales fechaHumana() y nombreMes() de helpers.php.
     */
    public static function construirDiasSemana(DateTimeImmutable $inicioSemana, DateTimeImmutable $hoy): array {
        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $fecha    = $inicioSemana->modify("+{$i} days");
            $fechaIso = $fecha->format('Y-m-d');
            $dias[]   = [
                'fecha'     => $fechaIso,
                'dia_corto' => ucfirst(substr(nombreDia((int)$fecha->format('N')), 0, 3)),
                'dia_largo' => fechaHumana($fechaIso),
                'numero'    => $fecha->format('j'),
                'mes'       => nombreMes((int)$fecha->format('n')),
                'es_hoy'    => $fechaIso === $hoy->format('Y-m-d'),
                'pasado'    => $fecha < $hoy,
            ];
        }
        return $dias;
    }

    /**
     * Serializa los servicios al formato que espera la constante JS reservaServicios.
     * El JS la usa para actualizar el panel lateral cuando el usuario cambia de servicio
     * sin necesidad de recargar la página ni lanzar una petición al servidor.
     */
    public static function construirServiciosJson(array $serviciosPorId): array {
        $resultado = [];
        foreach ($serviciosPorId as $id => $servicio) {
            $resultado[$id] = [
                'id'                => $servicio->getIdServicio(),
                'nombre'            => $servicio->getNombre(),
                'precio'            => $servicio->getPrecio(),
                'precio_formateado' => number_format($servicio->getPrecio(), 2, ',', '.') . ' €',
                'duracion'          => $servicio->getDuracion(),
                'descripcion'       => $servicio->getDescripcion() ?? '',
            ];
        }
        return $resultado;
    }

    /**
     * Precalcula la disponibilidad completa para todos los servicios y los 7 días visibles.
     * Estructura resultante: [id_servicio][fecha] => ['09:00', '09:30', ...]
     *
     * Al cargar la página, el JS ya tiene la disponibilidad de toda la semana en memoria.
     * Cambiar de servicio o de día no requiere petición al servidor: el JS filtra el array local.
     * Solo al navegar a otra semana se lanza un fetch, que devuelve esta misma estructura.
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

    // =========================================================================
    // MÉTODOS ESPECÍFICOS PARA EL ÁREA DE CLIENTE (Módulo VIP)
    // =========================================================================

    /**
     * Recupera la cita más cercana en el futuro que esté en estado 'pendiente'.
     * Justificación TFG: Control de flujo. Si existe, alimenta la tarjeta destacada
     * del Dashboard para mitigar el ausentismo (No-Show).
     */
    public static function obtenerProximaCita(int $idCliente): array|false {
        $conexion = BD::obtenerConexion();
        // Usamos CURRENT_DATE para asegurar la compatibilidad con PostgreSQL
        $stmt = $conexion->prepare("
            SELECT r.id, r.fecha, r.hora, r.estado, r.nota,
                   s.nombre as servicio_nombre, s.precio as servicio_precio, s.duracion,
                   u.nombre as barbero_nombre
            FROM citas r
            INNER JOIN servicios s ON r.id_servicio = s.id
            INNER JOIN usuarios u ON r.id_barbero = u.id
            WHERE r.id_usuario = :id_cliente 
              AND r.fecha >= CURRENT_DATE 
              AND r.estado = 'pendiente'
            ORDER BY r.fecha ASC, r.hora ASC 
            LIMIT 1
        ");
        $stmt->execute(['id_cliente' => $idCliente]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta cuántos cortes con estado 'completada' tiene el cliente.
     * Justificación TFG: Regla de negocio automatizada para el plan de fidelidad (Fidelización 10 puntos).
     */
    public static function contarCortesCompletados(int $idCliente): int {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT COUNT(*) 
            FROM citas 
            WHERE id_usuario = :id_cliente AND estado = 'completada'
        ");
        $stmt->execute(['id_cliente' => $idCliente]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retorna todo el histórico de citas del usuario (pasadas, pendientes y canceladas).
     * Justificación TFG: Auditoría visual transparente para que el usuario gestione sus visitas.
     */
    public static function obtenerHistorialCliente(int $idCliente): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT r.id, r.fecha, r.hora, r.estado, r.precio_historico, r.duracion_historica,
                   s.nombre as servicio_nombre,
                   u.nombre as barbero_nombre
            FROM citas r
            INNER JOIN servicios s ON r.id_servicio = s.id
            INNER JOIN usuarios u ON r.id_barbero = u.id
            WHERE r.id_usuario = :id_cliente
            ORDER BY r.fecha DESC, r.hora DESC
        ");
        $stmt->execute(['id_cliente' => $idCliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * APARTADO PARA EL USO EN CLIENTE
     * Contar rservas para fidelidad
     * obtener proxima para avisarle cuando es la siguiente
     */

    /**
     * Cuenta las citas completadas de un cliente.
     * Usada en el dashboard para el contador de cortes realizados.
     */
    public static function contarCompletadasPorCliente(int $id_usuario): int
    {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT COUNT(*)
        FROM reservas
        WHERE id_cliente = :id
          AND estado     = 'completada'
    ");
        $stmt->execute([':id' => $id_usuario]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve la próxima cita confirmada del cliente, o null si no tiene ninguna.
     * Hace JOIN con servicios para traer el nombre en la misma consulta.
     * Devuelve un array asociativo porque no necesitamos un objeto completo Reserva,
     * solo los datos para mostrar en el dashboard.
     */
    public static function obtenerProximaPorCliente(int $id_usuario): ?array
    {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT r.fecha,
               r.hora,
               r.duracion_historica,
               s.nombre AS servicio
        FROM reservas r
        JOIN servicios s ON r.id_servicio = s.id
        WHERE r.id_cliente = :id
          AND r.estado     = 'confirmada'
          AND r.fecha      >= CURRENT_DATE
        ORDER BY r.fecha ASC, r.hora ASC
        LIMIT 1
    ");
        $stmt->execute([':id' => $id_usuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Obtiene el historial completo de citas de un cliente determinado.
     * Trae tanto las completadas como las canceladas o ausentes.
     *
     * @param int $id_usuario ID único del cliente en la sesión.
     * @return array Listado asociativo con los datos de las reservas y servicios.
     */
    public static function obtenerHistorialPorUsuario(int $id_usuario): array {
        try {
            $db = BD::obtenerConexion();

            // 🌟 AGREGAMOS 'r.estado' A LA CONSULTA SQL
            $sql = "SELECT 
                    s.nombre AS servicio_nombre, 
                    r.fecha, 
                    r.hora, 
                    r.precio_historico, 
                    r.estado 
                FROM reservas r
                INNER JOIN servicios s ON r.id_servicio = s.id
                WHERE r.id_cliente = :id_usuario
                ORDER BY r.fecha DESC, r.hora DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute(['id_usuario' => $id_usuario]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error en obtenerHistorialPorUsuario: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Pone las citas pasadas como completadas
     * Una vez pasa media hora del corte ya es completada
     *
     */
    public static function actualizarCitasPasadas(): void {
        try {
            $db = BD::obtenerConexion();
            $db->beginTransaction();

            // 1. PostgreSQL permite el RETURNING en el UPDATE.
            // Combinamos la fecha y hora y sumamos el intervalo.
            $sqlReservas = "UPDATE reservas 
                        SET estado = 'completada' 
                        WHERE estado IN ('pendiente', 'confirmada') 
                          AND NOW() >= (fecha + hora + (duracion_historica * INTERVAL '1 minute'))
                        RETURNING id_cliente";

            $stmtReservas = $db->prepare($sqlReservas);
            $stmtReservas->execute();

            // Obtenemos los IDs directamente del resultado del update
            $clientesAfectados = $stmtReservas->fetchAll(PDO::FETCH_COLUMN);

            // 2. Si hay clientes, actualizamos sus puntos
            if (!empty($clientesAfectados)) {
                $sqlPuntos = "UPDATE usuarios 
                          SET puntos_fidelidad = CASE 
                              WHEN puntos_fidelidad + 1 >= 10 THEN 0 
                              ELSE puntos_fidelidad + 1 
                          END 
                          WHERE id = :id_cliente";

                $stmtPuntos = $db->prepare($sqlPuntos);

                foreach ($clientesAfectados as $id_cliente) {
                    $stmtPuntos->execute(['id_cliente' => $id_cliente]);
                }
            }

            $db->commit();

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en Reserva::actualizarCitasPasadas: " . $e->getMessage());
        }
    }
}