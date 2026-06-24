<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';
require_once __DIR__ . '/Horario.php';
require_once __DIR__ . '/Bloqueo.php';


class Reserva {
    // ---------------------------------------------------------------
    // No tiene ni propiedades, ni constructor ni getters.
    //en ningun lado se hacia new Reserva porque no se creaba un objeto reserva
    //solo se creaba reserva en la bd, y getters no hace falta porque ya no hay
    //propiedades que hay que poder mostrar c0on getters
    // ---------------------------------------------------------------


    // ---------------------------------------------------------------
    // LÓGICA DE DISPONIBILIDAD — núcleo del sistema de reservas
    // ---------------------------------------------------------------

    /**
     * Devuelve los huecos disponibles para hassan
     */
    public static function obtenerSlotsDisponibles(int $idBarbero, string $fecha, int $duracion, int $intervalo = 30, ?int $excluirReservaId = null): array {
        //comprobamos cosas imposibles(servicio sin tiempo)
        //argumentos invalidos
        if ($duracion <= 0 || $intervalo <= 0) {
            return [];
        }

        //fechas pasadas
        $hoy      = new DateTimeImmutable('today');
        $fechaDia = new DateTimeImmutable($fecha);

        // si es un dia completo bloqueado, bloqueamos toda la franja horaria
        if ($fechaDia < $hoy || Bloqueo::esDiaBloqueadoCompleto($idBarbero, $fecha)) {
            return [];
        }

        // sacamos horario de trabajo por dia (para el lunes)
        $tramos = Horario::obtenerTramosPorFecha($idBarbero, $fecha);
        //hassan no trabaja si los saca vacios
        if ($tramos === []) {
            return [];
        }

        // traemos reservas/bloqueos existentes de ese dia
        $reservas = self::getByBarberoYFecha($idBarbero, $fecha, $excluirReservaId);
        $bloqueos = Bloqueo::obtenerPorFecha($idBarbero, $fecha);
        $ahora    = new DateTimeImmutable('now');
        $slots    = [];

        // Sacamos los tramos primero mañanas, luego tardes.
        foreach ($tramos as $tramo) {
            //tramo 1: 09:00 y 14:00
            $actual   = self::combinarFechaHora($fecha, $tramo['hora_inicio']);
            $finTramo = self::combinarFechaHora($fecha, $tramo['hora_fin']);

            // si sumo minutos del corte, es antes que la hora de fin?
            while ($actual->modify("+{$duracion} minutes") <= $finTramo) {
                //guarda a que h acaba el peluquero el corte
                $slotFin = $actual->modify("+{$duracion} minutes");

                // si es hoy y es antes de hora actual, se modifica el actual
                //para probar en el siguiente con el siguiente slot y se manda continue para que salte al sigiente
                if ($fechaDia->format('Y-m-d') === $hoy->format('Y-m-d') && $actual <= $ahora) {
                    $actual = $actual->modify("+{$intervalo} minutes");
                    continue;
                }

                //comprueba si desde que empieza a que acaba, choca con alguna reserva/bloqueo
                if (
                    !self::solapaConReservas($actual, $slotFin, $reservas, $fecha)
                    && !self::solapaConBloqueos($actual, $slotFin, $bloqueos, $fecha)
                ) {
                    $slots[] = $actual->format('H:i');
                }

                $actual = $actual->modify("+{$intervalo} minutes");
            }
        }
        //ordena las horas conseguidas en slots y eliminamos horas repetidas (si hay)
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
        int $duracion,
        ?int $excluirReservaId = null
    ): bool {
        //substr coge el string $hora y le le dices que quieres 5 caracteres (14:30)
        $horaNormalizada = substr($hora, 0, 5);
        //busca si $horaNormalizada está en listado de slotsDisponibles
        return in_array(
            $horaNormalizada,
            self::obtenerSlotsDisponibles($idBarbero, $fecha, $duracion, 30, $excluirReservaId),
            true
        );
    }

    /**
     * Crea una reserva de forma atómica garantizando que no existe condición de carrera.
     *LLeva transaccion y LOCK para que nadie escriba en reservas si esta pendiente
     */
    public static function crearAtomicamente(
        int     $idCliente,
        int     $idBarbero,
        int     $idServicio,
        string  $fecha,
        string  $hora,
        float   $precio,
        int     $duracion,
        ?string $nota = null,
        bool    $gratis = false
    ): ?int {
        $conexion = BD::obtenerConexion();

        try {
            $conexion->beginTransaction();

            $lockOk = (int)$conexion->query("SELECT GET_LOCK('reserva_atomica', 10)")->fetchColumn();
            if ($lockOk !== 1) {
                $conexion->rollBack();
                return null;
            }

            if (!self::estaDisponible($idBarbero, $fecha, $hora, $duracion)) {
                $conexion->rollBack();
                $conexion->query("SELECT RELEASE_LOCK('reserva_atomica')")->fetch();
                return null;
            }

            $stmt = $conexion->prepare("
                INSERT INTO reservas
                    (id_cliente, id_barbero, id_servicio, fecha, hora,
                     precio_historico, duracion_historica, estado, nota, gratis)
                VALUES
                    (:cliente, :barbero, :servicio, :fecha, :hora,
                     :precio, :duracion, 'confirmada', :nota, :gratis)
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
                ':gratis'   => $gratis ? 1 : 0,
            ]);

            $id = (int)$conexion->lastInsertId();

            if ($gratis) {
                $stmtPuntos = $conexion->prepare(
                    "UPDATE usuarios SET puntos_fidelidad = 1 WHERE id = :id"
                );
                $stmtPuntos->execute([':id' => $idCliente]);
            }

            $conexion->commit();
            $conexion->query("SELECT RELEASE_LOCK('reserva_atomica')")->fetch();
            return $id;

        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $conexion->query("SELECT RELEASE_LOCK('reserva_atomica')")->fetch();
            throw $e;
        }
    }

    // ---------------------------------------------------------------
    // HELPERS PRIVADOS — fechas y detección de solapamientos
    //para ver si esta totalmente libre la hora o choca con algo
    // ---------------------------------------------------------------

    /**
     * Consulta interna de reservas activas de un barbero en una fecha.
     * Saca un listado de todas las reservas por barbero y dia concreto
     * private: solo la necesita obtenerSlotsDisponibles() para construir
     */
    private static function getByBarberoYFecha(int $idBarbero, string $fecha, ?int $excluirReservaId = null): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT hora, duracion_historica
            FROM   reservas
            WHERE  id_barbero = :id
              AND  fecha      = :fecha
              AND  estado NOT IN ('cancelada')
              AND  (:excluir_id IS NULL OR id != :excluir_id)
            ORDER  BY hora
        ");
        $stmt->execute([
            ':id'          => $idBarbero,
            ':fecha'       => $fecha,
            ':excluir_id'  => $excluirReservaId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Combina fecha '2026-06-03' y hora '10:30:00' en un DateTimeImmutable.
     * DateTimeImmutable para al realizar calculos del calendario no se
     * modifique ese horario y se cree otro nuevo
     */
    private static function combinarFechaHora(string $fecha, string $hora): DateTimeImmutable {
        //substr($hora, 0, 5) obliga a quedarse el dato con los 5 caracteres, sin segundos
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
        foreach ($diasSemana as $dia) {
            $fecha = $dia['fecha'];
            $slotsPorDuracion = [];
            foreach ($serviciosPorId as $id => $servicio) {
                $dur = $servicio->getDuracion();
                if (!isset($slotsPorDuracion[$dur])) {
                    $slotsPorDuracion[$dur] = self::obtenerSlotsDisponibles($idBarbero, $fecha, $dur);
                }
                $disponibilidad[$id][$fecha] = $slotsPorDuracion[$dur];
            }
        }
        return $disponibilidad;
    }

    // =========================================================================
    // MÉTODOS ESPECÍFICOS PARA EL ÁREA DE CLIENTE (Módulo VIP)
    // =========================================================================

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
     * Devuelvelos datos para mostrar en el dashboard.
     */
    public static function obtenerProximaPorCliente(int $id_usuario): ?array
    {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT r.fecha,
               r.hora,
               r.duracion_historica,
               r.gratis,
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


    public static function actualizarCitasPasadas(): void {
        $conexion = BD::obtenerConexion();
        $corteDT  = new DateTimeImmutable(date('Y-m-d H:i:s', strtotime('-30 minutes')));
        $fechaCorte = $corteDT->format('Y-m-d');
        $horaCorte  = $corteDT->format('H:i:s');

        try {
            $conexion->beginTransaction();

            // SELECT first, then UPDATE (MySQL doesn't support RETURNING)
            $stmtSelect = $conexion->prepare("
            SELECT r.id_cliente, r.gratis, r.fecha, r.hora, s.nombre AS servicio_nombre, r.precio_historico,
                   u.nombre AS u_nombre, u.email AS u_email, u.puntos_fidelidad AS u_puntos
            FROM reservas r
            JOIN servicios s ON r.id_servicio = s.id
            JOIN usuarios u ON r.id_cliente = u.id
            WHERE r.estado IN ('pendiente', 'confirmada')
              AND (r.fecha < :fecha_corte_s OR (r.fecha = :fecha_corte_s AND r.hora <= :hora_corte_s))
        ");
            $stmtSelect->execute([':fecha_corte_s' => $fechaCorte, ':hora_corte_s' => $horaCorte]);
            $afectadas = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($afectadas)) {
                $stmtUpdate = $conexion->prepare("
                UPDATE reservas r
                JOIN servicios s ON r.id_servicio = s.id
                SET r.estado = 'completada',
                    r.gratis = r.gratis OR (SELECT u.puntos_fidelidad >= 10 FROM usuarios u WHERE u.id = r.id_cliente)
                WHERE r.estado IN ('pendiente', 'confirmada')
                  AND (r.fecha < :fecha_corte_u OR (r.fecha = :fecha_corte_u AND r.hora <= :hora_corte_u))
            ");
                $stmtUpdate->execute([':fecha_corte_u' => $fechaCorte, ':hora_corte_u' => $horaCorte]);
                $clientesUnicos = array_unique(array_column($afectadas, 'id_cliente'));

                // Batch fetch puntos for all unique clients
                $puntosViejos = [];
                if (!empty($clientesUnicos)) {
                    $placeholders = implode(',', array_fill(0, count($clientesUnicos), '?'));
                    $stmtReadBatch = $conexion->prepare("SELECT id, puntos_fidelidad FROM usuarios WHERE id IN ($placeholders)");
                    $stmtReadBatch->execute(array_values($clientesUnicos));
                    while ($row = $stmtReadBatch->fetch(PDO::FETCH_ASSOC)) {
                        $puntosViejos[(int)$row['id']] = (int)$row['puntos_fidelidad'];
                    }
                }

                // Group by client to decide points logic per client
                $clientReservas = [];
                foreach ($afectadas as $r) {
                    $cid = (int)$r['id_cliente'];
                    $clientReservas[$cid][] = $r;
                }

                $stmtCaso = $conexion->prepare("
                    UPDATE usuarios
                    SET puntos_fidelidad = CASE
                        WHEN puntos_fidelidad >= 10 THEN 1
                        ELSE puntos_fidelidad + 1
                    END
                    WHERE id = :id
                ");
                $stmtReset = $conexion->prepare(
                    "UPDATE usuarios SET puntos_fidelidad = 1 WHERE id = :id"
                );

                foreach ($clientReservas as $cid => $reservas) {
                    $hasNonGratis = false;
                    foreach ($reservas as $r) {
                        if (!(bool)$r['gratis']) { $hasNonGratis = true; break; }
                    }

                    if ($hasNonGratis) {
                        $stmtCaso->execute([':id' => $cid]);
                    } else {
                        // All are gratis
                        $viejos = $puntosViejos[$cid] ?? 0;
                        if ($viejos >= 10) {
                            $stmtReset->execute([':id' => $cid]);
                        }
                    }
                }

                $conexion->commit();

                // ── Notificaciones post-cita ──
                $depsCargadas = false;
                $enviadosClientes = [];
                foreach ($afectadas as $r) {
                    $cid = (int)$r['id_cliente'];
                    if (isset($enviadosClientes[$cid])) continue;
                    $enviadosClientes[$cid] = true;

                    if (!$depsCargadas) {
                        require_once __DIR__ . '/NotificadorReserva.php';
                        require_once __DIR__ . '/helpers.php';
                        $depsCargadas = true;
                    }

                    $cli = new \Usuario(
                        $cid, null,
                        $r['u_nombre'], $r['u_email'],
                        null, null, null,
                        (int)($r['u_puntos'] ?? 0),
                        'cliente'
                    );

                    $_f = $r['fecha'] ?? '';
                    $viejos = $puntosViejos[$cid] ?? 0;
                    \NotificadorReserva::enviarCompletada($cli, [
                        'servicio' => $r['servicio_nombre'] ?? '',
                        'fecha'    => $_f !== '' ? \fechaHumana($_f) : '',
                    ], $viejos);
                }
            } else {
                $conexion->commit();
            }

        } catch (Exception $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log("Error en actualizarCitasPasadas: " . $e->getMessage());
        }
    }

    // =========================================================================
    // MÉTODOS ESPECÍFICOS PARA EL ÁREA DE ADMINISTRACIÓN
    // =========================================================================

    /**
     * Devuelve todas las reservas de un barbero en una fecha concreta,
     * este método devuelve la ficha completa: nombre del cliente, servicio, precio, estado y nota.
     * USO EXCLUSIVO DEL PANEL DE ADMINISTRACIÓN — agenda del día.

     * A diferencia de getByBarberoYFecha() (privado, solo hora+duración para el algoritmo),
     * @return array  Array de arrays asociativos, ordenado por hora ASC.
     */
    public static function obtenerDelDiaParaAdmin(int $idBarbero, string $fecha): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT r.id,
               r.hora,
               r.duracion_historica,
               r.precio_historico,
               r.gratis,
               r.estado,
               r.nota,
               r.id_cliente,
               u.nombre  AS cliente_nombre,
               u.email   AS cliente_email,
               u.puntos_fidelidad,
               s.nombre  AS servicio_nombre
        FROM   reservas r
        JOIN   usuarios  u ON r.id_cliente  = u.id
        JOIN   servicios s ON r.id_servicio = s.id
        WHERE  r.id_barbero = :barbero
          AND  r.fecha      = :fecha
          AND  r.estado NOT IN ('cancelada')
        ORDER  BY r.hora ASC
    ");
        $stmt->execute([':barbero' => $idBarbero, ':fecha' => $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve todas las reservas (incluyendo canceladas) en un rango de fechas.
     */
    public static function obtenerEnRango(int $idBarbero, string $inicio, string $fin): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT r.id,
               r.fecha,
               r.hora,
               r.duracion_historica,
               r.precio_historico,
               r.gratis,
               r.estado,
               r.nota,
               r.motivo_cancelacion,
               r.id_cliente,
               u.nombre  AS cliente_nombre,
               u.email   AS cliente_email,
               u.puntos_fidelidad,
               s.nombre  AS servicio_nombre
        FROM   reservas r
        JOIN   usuarios  u ON r.id_cliente  = u.id
        JOIN   servicios s ON r.id_servicio = s.id
        WHERE  r.id_barbero = :barbero
          AND  r.fecha     >= :inicio
          AND  r.fecha     <= :fin
        ORDER  BY r.fecha ASC, r.hora ASC
    ");
        $stmt->execute([':barbero' => $idBarbero, ':inicio' => $inicio, ':fin' => $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca citas confirmadas futuras para un barbero en un día de la semana y rango horario.
     * @param bool $fueraDeRango true → busca citas FUERA del rango (editar con menos horas)
     *                           false → busca citas DENTRO del rango (eliminar tramo)
     */
    public static function obtenerFuturasPorDiaYHora(
        int $idBarbero,
        string $diaSemana,
        string $horaInicio,
        string $horaFin,
        bool $fueraDeRango = false
    ): array {
        $conexion = BD::obtenerConexion();
        $mapa = [
            'domingo' => 1, 'lunes' => 2, 'martes' => 3,
            'miercoles' => 4, 'jueves' => 5, 'viernes' => 6, 'sabado' => 7,
        ];
        $diaNum = $mapa[$diaSemana] ?? 0;
        if ($diaNum === 0) return [];

        $operador = $fueraDeRango ? 'NOT' : '';
        $stmt = $conexion->prepare("
            SELECT r.id,
                   r.fecha,
                   r.hora,
                   r.duracion_historica,
                   r.id_cliente,
                   u.nombre  AS cliente_nombre,
                   u.email   AS cliente_email,
                   s.nombre  AS servicio_nombre
            FROM   reservas r
            JOIN   usuarios  u ON r.id_cliente  = u.id
            JOIN   servicios s ON r.id_servicio = s.id
            WHERE  r.id_barbero  = :barbero
              AND  r.estado      = 'confirmada'
              AND  r.fecha      >= CURDATE()
              AND  DAYOFWEEK(r.fecha) = :dia_num
              AND  r.hora $operador BETWEEN :hora_ini AND ADDTIME(:hora_fin, '-00:01:00')
            ORDER  BY r.fecha ASC, r.hora ASC
        ");
        $stmt->execute([
            ':barbero'   => $idBarbero,
            ':dia_num'   => $diaNum,
            ':hora_ini'  => $horaInicio,
            ':hora_fin'  => $horaFin,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve los datos completos de una reserva concreta, con JOIN a servicios y usuarios.
     * Se usa en la ficha del cliente del admin al clicar una tarjeta de la agenda.
     *
     * @return array|null  Array asociativo con todos los datos, o null si no existe el ID.
     */
    public static function obtenerPorId(int $id): ?array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT r.id,
               r.fecha,
               r.hora,
               r.duracion_historica,
               r.precio_historico,
               r.gratis,
               r.estado,
               r.nota,
               r.motivo_cancelacion,
               r.id_cliente,
               r.id_barbero,
               r.id_servicio,
               u.nombre  AS cliente_nombre,
               u.email   AS cliente_email,
               s.nombre  AS servicio_nombre
        FROM   reservas r
        JOIN   usuarios  u ON r.id_cliente  = u.id
        JOIN   servicios s ON r.id_servicio = s.id
        WHERE  r.id = :id
    ");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Cuenta cuántas reservas de un cliente tienen un estado concreto.
     * Se usa en la ficha del admin para mostrar las estadísticas del cliente:
     * canceladas y no_presentado (las completadas ya las cuenta contarCompletadasPorCliente).
     *
     * @param  string $estado  'cancelada' | 'no_presentado' | 'confirmada' | 'completada'
     * @return int
     */
    public static function contarPorEstadoYCliente(int $idCliente, string $estado): int {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT COUNT(*)
        FROM   reservas
        WHERE  id_cliente = :id
          AND  estado     = :estado
    ");
        $stmt->execute([':id' => $idCliente, ':estado' => $estado]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Devuelve la última cita completada de un cliente con el nombre del servicio.
     * Se usa en la ficha del admin para que Hassan vea qué corte pidió la última vez.
     *
     * @return array|null  Array asociativo con fecha, hora y servicio, o null si no tiene ninguna.
     */
    public static function obtenerUltimaCompletadaPorCliente(int $idCliente): ?array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
        SELECT r.fecha,
               r.hora,
               r.precio_historico,
               s.nombre AS servicio_nombre
        FROM   reservas r
        JOIN   servicios s ON r.id_servicio = s.id
        WHERE  r.id_cliente = :id
          AND  r.estado     = 'completada'
        ORDER  BY r.fecha DESC, r.hora DESC
        LIMIT  1
    ");
        $stmt->execute([':id' => $idCliente]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Historial completo de reservas de un cliente (todas, excepto canceladas
     * aquí devuelve todas para que Hassan vea el historial
     * ORDER BY fecha DESC para ver primero las más recientes.
     */
    public static function obtenerHistorialPorCliente(int $id_cliente): array
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare(
            "SELECT r.id, r.fecha, r.hora, r.estado,
                r.precio_historico, r.gratis, r.duracion_historica,
                r.motivo_cancelacion,
                s.nombre AS nombre_servicio
           FROM reservas r
      LEFT JOIN servicios s ON r.id_servicio = s.id
          WHERE r.id_cliente = :id_cliente
       ORDER BY r.fecha DESC, r.hora DESC"
        );
        $stmt->execute(['id_cliente' => $id_cliente]);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = $fila;
        }
        return $resultado;
    }

// -----------------------------------------------------------------------
// -----------------------------------------------------------------------
// Cancelar una reserva (Hassan cancela con motivo)
// -----------------------------------------------------------------------
    /**
     * Cancela una cita confirmada guardando el motivo.
     * @return bool True si se canceló correctamente
     */
    public static function cancelar(int $id, string $motivo, ?PDO $conexion = null): bool {
        $propia = $conexion === null;
        if ($propia) $conexion = BD::obtenerConexion();
        try {
            if ($propia) $conexion->beginTransaction();

            $stmtG = $conexion->prepare(
                "SELECT id_cliente, gratis FROM reservas WHERE id = :id AND estado = 'confirmada'"
            );
            $stmtG->execute([':id' => $id]);
            $info = $stmtG->fetch(PDO::FETCH_ASSOC);
            if (!$info) {
                if ($propia) $conexion->rollBack();
                return false;
            }

            $stmt = $conexion->prepare("
                UPDATE reservas
                SET estado = 'cancelada', motivo_cancelacion = :motivo
                WHERE id = :id AND estado = 'confirmada'
            ");
            $stmt->execute([':motivo' => $motivo, ':id' => $id]);
            $ok = $stmt->rowCount() > 0;

            if ($ok && !empty($info['gratis'])) {
                $stmtP = $conexion->prepare(
                    "UPDATE usuarios SET puntos_fidelidad = puntos_fidelidad + 9 WHERE id = :id"
                );
                $stmtP->execute([':id' => $info['id_cliente']]);
            }

            if ($propia) $conexion->commit();
            return $ok;
        } catch (Exception $e) {
            if ($propia && $conexion->inTransaction()) $conexion->rollBack();
            error_log("Error al cancelar reserva: " . $e->getMessage());
            return false;
        }
    }

// -----------------------------------------------------------------------
// Cancelar TODAS las citas de un día completo (ej: el barbero no puede ir)
// -----------------------------------------------------------------------
    /**
     * Cancela todas las confirmadas de un día con un mismo motivo.
     * @return int Número de citas canceladas
     */
    public static function cancelarPorDia(int $idBarbero, string $fecha, string $motivo): int {
        $conexion = BD::obtenerConexion();
        try {
            $horaActual = date('H:i:s');

            $stmtG = $conexion->prepare(
                "SELECT id, id_cliente, gratis FROM reservas
                 WHERE id_barbero = :barbero AND fecha = :fecha
                   AND estado = 'confirmada' AND hora >= :hora_actual"
            );
            $stmtG->execute([
                ':barbero'     => $idBarbero,
                ':fecha'       => $fecha,
                ':hora_actual' => $horaActual,
            ]);
            $aCancelar = $stmtG->fetchAll(PDO::FETCH_ASSOC);

            if (empty($aCancelar)) return 0;

            $stmt = $conexion->prepare("
                UPDATE reservas
                SET estado = 'cancelada', motivo_cancelacion = :motivo
                WHERE id_barbero = :barbero
                  AND fecha = :fecha
                  AND estado = 'confirmada'
                  AND hora >= :hora_actual
            ");
            $stmt->execute([
                ':motivo'      => $motivo,
                ':barbero'     => $idBarbero,
                ':fecha'       => $fecha,
                ':hora_actual' => $horaActual,
            ]);
            $count = $stmt->rowCount();

            $stmtP = $conexion->prepare(
                "UPDATE usuarios SET puntos_fidelidad = puntos_fidelidad + 9 WHERE id = :id"
            );
            foreach ($aCancelar as $r) {
                if (!empty($r['gratis'])) {
                    $stmtP->execute([':id' => $r['id_cliente']]);
                }
            }

            return $count;
        } catch (Exception $e) {
            error_log("Error al cancelar día: " . $e->getMessage());
            return 0;
        }
    }

// -----------------------------------------------------------------------
// Mover una reserva a otra fecha/hora (admin agenda)
// Solo se puede mover si está en estado 'confirmada' o 'pendiente'
// Devuelve los datos para notificación, o null si falla (slot ocupado)
// -----------------------------------------------------------------------
    public static function mover(int $idReserva, string $nuevaFecha, string $nuevaHora, ?string $motivo = null): ?array
    {
        $conexion = BD::obtenerConexion();

        try {
            $conexion->beginTransaction();

            $lockOk = (int)$conexion->query("SELECT GET_LOCK('reserva_atomica', 10)")->fetchColumn();
            if ($lockOk !== 1) {
                $conexion->rollBack();
                return null;
            }

            $stmtGet = $conexion->prepare("
                SELECT r.id, r.fecha, r.hora, r.duracion_historica, r.precio_historico,
                       r.id_cliente, r.id_barbero, r.id_servicio, r.gratis, r.nota,
                       s.nombre AS servicio_nombre,
                       u.nombre AS cliente_nombre, u.email AS cliente_email
                FROM   reservas r
                JOIN   servicios s ON r.id_servicio = s.id
                JOIN   usuarios  u ON r.id_cliente  = u.id
                WHERE  r.id = :id AND r.estado IN ('confirmada', 'pendiente')
            ");
            $stmtGet->execute([':id' => $idReserva]);
            $reserva = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                $conexion->rollBack();
                $conexion->query("SELECT RELEASE_LOCK('reserva_atomica')")->fetch();
                return null;
            }

            if (!self::estaDisponible(
                (int)$reserva['id_barbero'],
                $nuevaFecha,
                $nuevaHora,
                (int)$reserva['duracion_historica'],
                $idReserva
            )) {
                $conexion->rollBack();
                $conexion->query("SELECT RELEASE_LOCK('reserva_atomica')")->fetch();
                return null;
            }

            $notaAnterior = $reserva['nota'] ?? '';
            $viejaHora    = substr((string)$reserva['hora'], 0, 5);
            $nuevaHoraFmt = substr($nuevaHora, 0, 5);
            $movimiento   = '[Movida: ' . $reserva['fecha'] . ' ' . $viejaHora
                          . ' → ' . $nuevaFecha . ' ' . $nuevaHoraFmt . ']';
            if ($motivo !== null && trim($motivo) !== '') {
                $movimiento .= ' Motivo: ' . trim($motivo);
            }
            $nuevaNota = $notaAnterior !== ''
                ? $notaAnterior . "\n" . $movimiento
                : $movimiento;

            $stmtUpd = $conexion->prepare("
                UPDATE reservas
                SET fecha = :fecha, hora = :hora, nota = :nota
                WHERE id = :id
            ");
            $stmtUpd->execute([
                ':fecha' => $nuevaFecha,
                ':hora'  => $nuevaHora,
                ':nota'  => $nuevaNota,
                ':id'    => $idReserva,
            ]);

            $conexion->commit();
            $conexion->query("SELECT RELEASE_LOCK('reserva_atomica')")->fetch();

            return [
                'id_cliente'      => (int)$reserva['id_cliente'],
                'cliente_nombre'  => $reserva['cliente_nombre'],
                'cliente_email'   => $reserva['cliente_email'],
                'servicio_nombre' => $reserva['servicio_nombre'],
                'fecha_vieja'     => $reserva['fecha'],
                'hora_vieja'      => $viejaHora,
                'fecha_nueva'     => $nuevaFecha,
                'hora_nueva'      => $nuevaHoraFmt,
            ];

        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $conexion->query("SELECT RELEASE_LOCK('reserva_atomica')")->fetch();
            error_log("Error al mover reserva: " . $e->getMessage());
            return null;
        }
    }

// Marcar una reserva como completada (Hassan cierra la cita)
// Solo se puede marcar si estaba en estado 'confirmada'
// -----------------------------------------------------------------------
    public static function marcarComoCompletada(int $id_reserva): bool
    {
        $conexion = BD::obtenerConexion();

        try {
            $conexion->beginTransaction();

            $stmtSelect = $conexion->prepare(
                "SELECT id_cliente, gratis
             FROM reservas
             WHERE id = :id AND estado = 'confirmada'"
            );
            $stmtSelect->execute(['id' => $id_reserva]);
            $reserva = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                $conexion->rollBack();
                return false;
            }

            $id_cliente = $reserva['id_cliente'];
            $gratis = (bool)$reserva['gratis'];

            $stmtUpdate = $conexion->prepare(
                "UPDATE reservas SET estado = 'completada' WHERE id = :id"
            );
            $stmtUpdate->execute(['id' => $id_reserva]);

            if (!$gratis) {
                $stmtPuntos = $conexion->prepare(
                    "UPDATE usuarios
                 SET puntos_fidelidad = CASE
                     WHEN puntos_fidelidad >= 10 THEN 1
                     ELSE puntos_fidelidad + 1
                 END
                 WHERE id = :id"
                );
                $stmtPuntos->execute(['id' => $id_cliente]);
            }

            $conexion->commit();
            return true;

        } catch (Exception $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log("marcarComoCompletada($id_reserva): " . $e->getMessage());
            return false;
        }
    }

// -----------------------------------------------------------------------
// Marcar una reserva como no_presentado
// Solo funciona si estaba en estado 'confirmada'
// -----------------------------------------------------------------------
    public static function marcarComoNoPresentado(int $id_reserva): bool
    {
        $conexion = BD::obtenerConexion();

        try {
            // 1. Iniciamos la transacción para proteger ambas operaciones
            $conexion->beginTransaction();

            // 2. Primero, necesitamos saber de qué cliente es esta reserva
            $stmtSelect = $conexion->prepare(
                "SELECT id_cliente 
             FROM reservas 
             WHERE id = :id AND estado = 'confirmada'"
            );
            $stmtSelect->execute(['id' => $id_reserva]);
            $reserva = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            // Si la reserva no existe o ya no está "confirmada", abortamos
            if (!$reserva) {
                $conexion->rollBack();
                return false;
            }

            $id_cliente = $reserva['id_cliente'];

            // 3. Actualizamos el estado de la reserva a 'no_presentado'
            $stmtUpdateReserva = $conexion->prepare(
                "UPDATE reservas
             SET estado = 'no_presentado'
             WHERE id = :id"
            );
            $stmtUpdateReserva->execute(['id' => $id_reserva]);

            // 4. Actualizamos los puntos de fidelidad del cliente
            // OJO: He puesto "- 3" como ejemplo de penalización. Cambia ese 3 por los puntos que quieras quitar.
            // Uso GREATEST para asegurar que, si le quitas puntos, nunca se quede con puntos negativos en PostgreSQL.
            $stmtUpdatePuntos = $conexion->prepare(
                "UPDATE usuarios
             SET puntos_fidelidad = GREATEST(puntos_fidelidad - 3, 0)
             WHERE id = :id_cliente"
            );
            $stmtUpdatePuntos->execute(['id_cliente' => $id_cliente]);

            // 5. Si todo ha ido bien, confirmamos los cambios en ambas tablas
            $conexion->commit();
            return true;

        } catch (Exception $e) {
            // Si hay cualquier fallo (ej. la base de datos se cae a mitad), deshacemos todo
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            return false;
        }
    }
}