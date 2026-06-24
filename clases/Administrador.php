<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';
require_once __DIR__ . '/Usuario.php';

/**
 * Administrador — hijo de Usuario con rol forzado a 'admin'.
 *
 * Responsabilidad de esta clase (SRP):
 *   - Representar al usuario con rol administrador.
 *   - Proporcionar métodos de GESTIÓN Y ESTADÍSTICAS que solo tienen
 *     sentido desde el panel de administración:
 *       · Resúmenes del negocio (citas, ingresos, clientes).
 *       · Listado y búsqueda de clientes.
 *
 * Lo que NO hace esta clase:
 *   - Consultar reservas concretas → sigue en Reserva.php (SRP).
 *   - Gestionar servicios u horarios → cada uno en su clase.
 *
 * Argumento de defensa para la herencia:
 *   Usuario contiene la lógica común a cualquier usuario (login, Google OAuth,
 *   getters de campos compartidos). Administrador añade solo lo que es exclusivo
 *   del rol admin: acceso a datos de gestión del negocio y de otros usuarios.
 *   Un Cliente nunca llamaría a obtenerResumenDia() ni a obtenerTodosLosClientes().
 */
class Administrador extends Usuario
{

    // =========================================================================
    // 1. CONSTRUCTOR
    // Hereda de Usuario y fuerza el rol a 'admin'.
    // Todos los campos aceptan nulabilidad excepto nombre y email.
    // =========================================================================

    public function __construct(
        string  $nombre,
        string  $email,
        ?int    $id            = null,
        ?string $google_id     = null,
        ?string $password      = null,
        ?string $avatar        = null,
        ?string $telefono      = null,
        int     $puntos_fidelidad = 0,
        string  $rol           = 'admin'
    ) {
        parent::__construct($id, $google_id, $nombre, $email, $password, $avatar, $telefono, $puntos_fidelidad, $rol);
    }


    // =========================================================================
    // 2. ESTADÍSTICAS DEL PANEL — Resumen por fecha
    //
    // Estos métodos son el equivalente a un "dashboard" del negocio.
    // Solo el admin los necesita, por eso viven aquí y no en Reserva.
    // Reserva.php sigue gestionando operaciones sobre reservas concretas;
    // estos métodos agregan datos para la vista de control del admin.
    // =========================================================================

    /**
     * Resumen completo de citas para una fecha dada.
     *
     * Devuelve un array asociativo con todos los contadores que necesita
     * el panel: total de citas, cuántas por estado, e ingresos del día.
     * Se llama con una sola consulta agregada para no hacer 5 queries separadas.
     *
     * Uso en admin/index.php:
     *   $resumen = Administrador::obtenerResumenDia(date('Y-m-d'));
     *
     * @param  string $fecha  Formato 'Y-m-d'. Por defecto hoy.
     * @return array{
     *   total: int,
     *   confirmadas: int,
     *   completadas: int,
     *   no_presentados: int,
     *   canceladas: int,
     *   ingresos: float
     * }
     */
    public static function obtenerResumenDia(string $fecha = ''): array
    {
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $conexion = BD::obtenerConexion();

        // Una sola query con COUNT condicional evita 5 consultas separadas.
        // FILTER (WHERE ...) es sintaxis PostgreSQL estándar.
        $stmt = $conexion->prepare("
            SELECT
                COUNT(*)                                              AS total,
                SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END)        AS confirmadas,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END)        AS completadas,
                SUM(CASE WHEN estado = 'no_presentado' THEN 1 ELSE 0 END)     AS no_presentados,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END)         AS canceladas,
                COALESCE(
                    SUM(CASE WHEN estado = 'completada' AND gratis != 1 THEN precio_historico ELSE 0 END),
                    0
                )                                                    AS ingresos
            FROM reservas
            WHERE fecha = :fecha
        ");
        $stmt->execute([':fecha' => $fecha]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Normalizamos tipos: la query siempre devuelve una fila aunque no haya citas.
        return [
            'total'          => (int)$fila['total'],
            'confirmadas'    => (int)$fila['confirmadas'],
            'completadas'    => (int)$fila['completadas'],
            'no_presentados' => (int)$fila['no_presentados'],
            'canceladas'     => (int)$fila['canceladas'],
            'ingresos'       => (float)$fila['ingresos'],
        ];
    }

    /**
     * Resumen de la semana en curso (lunes → domingo de la semana actual).
     *
     * Útil para la cabecera del panel: "Esta semana: X citas, Y€ de ingresos".
     * date_trunc('week', ...) en PostgreSQL devuelve el lunes de esa semana.
     *
     * @return array{total: int, completadas: int, ingresos: float}
     */
    public static function obtenerResumenSemanaActual(): array
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT
                COUNT(*)                                             AS total,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END)       AS completadas,
                COALESCE(
                    SUM(CASE WHEN estado = 'completada' AND gratis != 1 THEN precio_historico ELSE 0 END),
                    0
                )                                                    AS ingresos
            FROM reservas
            WHERE fecha >= DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY)
              AND fecha <= CURRENT_DATE
        ");
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'      => (int)$fila['total'],
            'completadas' => (int)$fila['completadas'],
            'ingresos'   => (float)$fila['ingresos'],
        ];
    }

    /**
     * Resumen del mes indicado (o mes actual si no se pasa nada).
     *
     * @param  int $anio  Año (4 dígitos). 0 = usar año actual.
     * @param  int $mes   Mes (1-12). 0 = usar mes actual.
     * @return array{total: int, completadas: int, ingresos: float, clientes_nuevos: int}
     */
    public static function obtenerResumenMes(int $anio = 0, int $mes = 0): array
    {
        $anio = $anio > 0 ? $anio : (int)date('Y');
        $mes  = $mes  > 0 ? $mes  : (int)date('m');
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT
                COUNT(*)                                             AS total,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END)       AS completadas,
                COALESCE(
                    SUM(CASE WHEN estado = 'completada' AND gratis != 1 THEN precio_historico ELSE 0 END),
                    0
                )                                                    AS ingresos
            FROM reservas
            WHERE fecha >= :inicio AND fecha < :fin
        ");
        $stmt->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmtClientes = $conexion->prepare("
            SELECT COUNT(*) AS clientes_nuevos
            FROM   usuarios
            WHERE  rol = 'cliente'
              AND  created_at >= :inicio2 AND created_at < :fin2
        ");
        $stmtClientes->execute([':inicio2' => $inicioMes, ':fin2' => $finMes]);
        $filaClientes = $stmtClientes->fetch(PDO::FETCH_ASSOC);

        return [
            'total'           => (int)$fila['total'],
            'completadas'     => (int)$fila['completadas'],
            'ingresos'        => (float)$fila['ingresos'],
            'clientes_nuevos' => (int)$filaClientes['clientes_nuevos'],
        ];
    }


    /**
     * Ingresos mensuales (últimos N meses). Para gráfico de barras en dashboard.
     * Rellena con ceros los meses sin datos.
     */
    public static function obtenerIngresosMensuales(int $ultimosMeses = 12): array
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT
                YEAR(fecha)  AS anio,
                MONTH(fecha) AS mes,
                COUNT(*)                                              AS total_citas,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END)        AS completadas,
                COALESCE(SUM(CASE WHEN estado = 'completada' AND gratis != 1 THEN precio_historico ELSE 0 END), 0) AS ingresos
            FROM reservas
            WHERE fecha >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') - INTERVAL {$ultimosMeses} MONTH
              AND fecha <  DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') + INTERVAL 1 MONTH
            GROUP BY anio, mes
            ORDER BY anio, mes
        ");
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Indexar por "Y-m"
        $indexados = [];
        foreach ($filas as $f) {
            $indexados[sprintf('%04d-%02d', (int)$f['anio'], (int)$f['mes'])] = $f;
        }

        // Rellenar meses sin datos
        $resultado = [];
        $hoy = new DateTimeImmutable('first day of this month');
        for ($i = $ultimosMeses - 1; $i >= 0; $i--) {
            $fecha = $hoy->modify("-{$i} months");
            $clave = $fecha->format('Y-m');
            $existe = $indexados[$clave] ?? null;
            $resultado[] = [
                'anio'        => (int)$fecha->format('Y'),
                'mes'         => (int)$fecha->format('m'),
                'mes_nombre'  => nombreMesCorto((int)$fecha->format('m')),
                'total_citas' => (int)($existe['total_citas'] ?? 0),
                'completadas' => (int)($existe['completadas'] ?? 0),
                'ingresos'    => (float)($existe['ingresos'] ?? 0),
            ];
        }

        return $resultado;
    }

    /**
     * Servicios más vendidos (por nº de reservas completadas).
     */
    public static function obtenerServiciosMasVendidos(int $limite = 6): array
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT
                s.id,
                s.nombre,
                COUNT(r.id)                                    AS total,
                COALESCE(SUM(CASE WHEN r.gratis != 1 THEN r.precio_historico ELSE 0 END), 0) AS ingresos
            FROM servicios s
            LEFT JOIN reservas r ON r.id_servicio = s.id AND r.estado = 'completada'
            GROUP BY s.id, s.nombre
            ORDER BY total DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tasa de no-shows del mes indicado (0..100).
     *
     * @param  int $anio  Año (4 dígitos). 0 = usar año actual.
     * @param  int $mes   Mes (1-12). 0 = usar mes actual.
     */
    public static function obtenerTasaNoShows(int $anio = 0, int $mes = 0): array
    {
        $anio = $anio > 0 ? $anio : (int)date('Y');
        $mes  = $mes  > 0 ? $mes  : (int)date('m');
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado = 'no_presentado' THEN 1 ELSE 0 END) AS no_shows
            FROM reservas
            WHERE fecha >= :inicio AND fecha < :fin
        ");
        $stmt->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)$fila['total'];
        $noShows = (int)$fila['no_shows'];

        return [
            'total'    => $total,
            'no_shows' => $noShows,
            'tasa'     => $total > 0 ? round($noShows / $total * 100, 1) : 0,
        ];
    }

    /**
     * Clientes nuevos vs recurrentes del mes indicado.
     * - Nuevos: primera reserva completada este mes
     * - Recurrentes: ya habían completado antes
     *
     * @param  int $anio  Año (4 dígitos). 0 = usar año actual.
     * @param  int $mes   Mes (1-12). 0 = usar mes actual.
     */
    public static function obtenerClientesNuevosVsRecurrentes(int $anio = 0, int $mes = 0): array
    {
        $anio = $anio > 0 ? $anio : (int)date('Y');
        $mes  = $mes  > 0 ? $mes  : (int)date('m');
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            WITH clientes_mes AS (
                SELECT DISTINCT id_cliente
                FROM reservas
                WHERE estado = 'completada'
                  AND fecha >= :inicio AND fecha < :fin
            ),
            primeras AS (
                SELECT id_cliente, MIN(fecha) AS primera_fecha
                FROM reservas
                WHERE estado = 'completada'
                GROUP BY id_cliente
            )
            SELECT
                SUM(CASE WHEN DATE_FORMAT(pr.primera_fecha, '%Y-%m-01') = :mes THEN 1 ELSE 0 END) AS nuevos,
                SUM(CASE WHEN DATE_FORMAT(pr.primera_fecha, '%Y-%m-01') < :mes THEN 1 ELSE 0 END) AS recurrentes
            FROM clientes_mes cm
            JOIN primeras pr ON pr.id_cliente = cm.id_cliente
        ");
        $stmt->execute([':inicio' => $inicioMes, ':fin' => $finMes, ':mes' => $inicioMes]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        $nuevos = (int)($fila['nuevos'] ?? 0);
        $recurrentes = (int)($fila['recurrentes'] ?? 0);
        $total = $nuevos + $recurrentes;

        return [
            'nuevos'      => $nuevos,
            'recurrentes' => $recurrentes,
            'total'       => $total,
            'pct_nuevos'  => $total > 0 ? round($nuevos / $total * 100, 1) : 0,
        ];
    }

    /**
     * Lista de clientes no presentados del mes indicado (para dashboard).
     *
     * @param  int $anio  Año (4 dígitos). 0 = usar año actual.
     * @param  int $mes   Mes (1-12). 0 = usar mes actual.
     * @return array[]  Cada elemento: nombre, email, fecha, hora, servicio
     */
    public static function obtenerNoShowsMes(int $anio = 0, int $mes = 0): array
    {
        $anio = $anio > 0 ? $anio : (int)date('Y');
        $mes  = $mes  > 0 ? $mes  : (int)date('m');
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT u.nombre, u.email, r.fecha, r.hora, s.nombre AS servicio
            FROM reservas r
            JOIN usuarios u ON r.id_cliente = u.id
            JOIN servicios s ON r.id_servicio = s.id
            WHERE r.estado = 'no_presentado'
              AND r.fecha >= :inicio AND r.fecha < :fin
            ORDER BY r.fecha DESC, r.hora DESC
        ");
        $stmt->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista de clientes registrados el mes indicado (para dashboard).
     *
     * @param  int $anio  Año (4 dígitos). 0 = usar año actual.
     * @param  int $mes   Mes (1-12). 0 = usar mes actual.
     * @return array[]  Cada elemento: nombre, email, created_at
     */
    public static function obtenerNuevosClientesMes(int $anio = 0, int $mes = 0): array
    {
        $anio = $anio > 0 ? $anio : (int)date('Y');
        $mes  = $mes  > 0 ? $mes  : (int)date('m');
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT nombre, email, created_at
            FROM usuarios
            WHERE rol = 'cliente'
              AND created_at >= :inicio AND created_at < :fin
            ORDER BY created_at DESC
        ");
        $stmt->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista de clientes que hicieron su PRIMERA reserva completada este mes.
     *
     * @param  int $anio  Año (4 dígitos). 0 = usar año actual.
     * @param  int $mes   Mes (1-12). 0 = usar mes actual.
     * @return array[]  Cada elemento: nombre, email, primera_fecha
     */
    public static function obtenerNuevosPrimeraVezMes(int $anio = 0, int $mes = 0): array
    {
        $anio = $anio > 0 ? $anio : (int)date('Y');
        $mes  = $mes  > 0 ? $mes  : (int)date('m');
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            WITH clientes_mes AS (
                SELECT DISTINCT id_cliente
                FROM reservas
                WHERE estado = 'completada'
                  AND fecha >= :inicio AND fecha < :fin
            ),
            primeras AS (
                SELECT id_cliente, MIN(fecha) AS primera_fecha
                FROM reservas
                WHERE estado = 'completada'
                GROUP BY id_cliente
            )
            SELECT u.nombre, u.email, pr.primera_fecha
            FROM clientes_mes cm
            JOIN primeras pr ON pr.id_cliente = cm.id_cliente
            JOIN usuarios u ON u.id = cm.id_cliente
            WHERE DATE_FORMAT(pr.primera_fecha, '%Y-%m-01') = :mes_r
            ORDER BY pr.primera_fecha DESC
        ");
        $stmt->execute([':inicio' => $inicioMes, ':fin' => $finMes, ':mes_r' => $inicioMes]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista de clientes recurrentes del mes (ya habían venido antes).
     *
     * @param  int $anio  Año (4 dígitos). 0 = usar año actual.
     * @param  int $mes   Mes (1-12). 0 = usar mes actual.
     * @return array[]  Cada elemento: nombre, email, primera_fecha
     */
    public static function obtenerRecurrentesMes(int $anio = 0, int $mes = 0): array
    {
        $anio = $anio > 0 ? $anio : (int)date('Y');
        $mes  = $mes  > 0 ? $mes  : (int)date('m');
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("
            WITH clientes_mes AS (
                SELECT DISTINCT id_cliente
                FROM reservas
                WHERE estado = 'completada'
                  AND fecha >= :inicio AND fecha < :fin
            ),
            primeras AS (
                SELECT id_cliente, MIN(fecha) AS primera_fecha
                FROM reservas
                WHERE estado = 'completada'
                GROUP BY id_cliente
            )
            SELECT u.nombre, u.email, pr.primera_fecha
            FROM clientes_mes cm
            JOIN primeras pr ON pr.id_cliente = cm.id_cliente
            JOIN usuarios u ON u.id = cm.id_cliente
            WHERE DATE_FORMAT(pr.primera_fecha, '%Y-%m-01') < :mes_r
            ORDER BY pr.primera_fecha DESC
        ");
        $stmt->execute([':inicio' => $inicioMes, ':fin' => $finMes, ':mes_r' => $inicioMes]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // =========================================================================
    // 3. GESTIÓN DE CLIENTES
    //
    // Métodos para listar y buscar clientes desde el panel de admin.
    // Son operaciones sobre la tabla usuarios filtradas por rol='cliente',
    // pero desde la perspectiva del admin gestionando su base de clientes.
    // Por eso viven aquí y no en Cliente (que no debería saber de otros clientes)
    // ni en Usuario (que no debería mezclar lógica de gestión con lógica común).
    // =========================================================================

    /**
     * Devuelve todos los clientes ordenados por fecha de registro descendente.
     * Se usa en admin/clientes.php para la tabla de gestión.
     *
     * @return array  Array de arrays asociativos con los datos de cada cliente.
     */
    public static function obtenerTodosLosClientes(string $filtro = 'todos'): array
    {
        $conexion = BD::obtenerConexion();

        $whereExtra = '';
        if ($filtro === 'puntos') {
            $whereExtra = "AND (u.puntos_fidelidad >= 10
                            OR EXISTS (SELECT 1 FROM reservas r
                                        WHERE r.id_cliente = u.id
                                          AND r.gratis = 1
                                          AND r.estado = 'confirmada'))";
        } elseif ($filtro === 'nuevos') {
            $whereExtra = "AND u.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        }

        $stmt = $conexion->prepare("
            SELECT u.id,
                   u.nombre,
                   u.email,
                   u.telefono,
                   u.avatar,
                   u.puntos_fidelidad,
                   u.activo,
                   u.created_at,
                   COUNT(r.id)                                                   AS total_reservas,
                   SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END)     AS citas_completadas,
                   (SELECT MAX(fecha) FROM reservas
                     WHERE id_cliente = u.id AND estado = 'completada')          AS ultima_visita,
                   CASE WHEN u.puntos_fidelidad >= 10
                        THEN 1 ELSE 0 END                                        AS tiene_puntos,
                   CASE WHEN EXISTS (SELECT 1 FROM reservas r
                                       WHERE r.id_cliente = u.id
                                         AND r.gratis = 1
                                         AND r.estado = 'confirmada')
                        THEN 1 ELSE 0 END                                        AS tiene_gratis_pendiente
            FROM   usuarios u
            LEFT JOIN reservas r ON r.id_cliente = u.id
            WHERE  u.rol = 'cliente'
                   $whereExtra
            GROUP  BY u.id
            ORDER  BY u.created_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca clientes cuyo nombre o email contengan el texto buscado.
     * Se usa en admin/clientes.php con el campo de búsqueda.
     *
     * ILIKE es PostgreSQL (insensible a mayúsculas). El % lo añadimos aquí,
     * no en la query, para que el parámetro siga siendo un :param limpio.
     *
     * @param  string $busqueda  Texto libre introducido por Hassan.
     * @return array
     */
    public static function buscarClientes(string $busqueda): array
    {
        $conexion = BD::obtenerConexion();

        $termino = '%' . $busqueda . '%';

        $stmt = $conexion->prepare("
            SELECT u.id,
                   u.nombre,
                   u.email,
                   u.telefono,
                   u.avatar,
                   u.puntos_fidelidad,
                   u.activo,
                   u.created_at,
                   COUNT(r.id)                                                   AS total_reservas,
                   SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END)     AS citas_completadas,
                   (SELECT MAX(fecha) FROM reservas
                     WHERE id_cliente = u.id AND estado = 'completada')          AS ultima_visita,
                   CASE WHEN u.puntos_fidelidad >= 10
                        THEN 1 ELSE 0 END                                        AS tiene_puntos,
                   CASE WHEN EXISTS (SELECT 1 FROM reservas r
                                       WHERE r.id_cliente = u.id
                                         AND r.gratis = 1
                                         AND r.estado = 'confirmada')
                        THEN 1 ELSE 0 END                                        AS tiene_gratis_pendiente
            FROM   usuarios u
            LEFT JOIN reservas r ON r.id_cliente = u.id
            WHERE  u.rol    = 'cliente'
              AND (LOWER(u.nombre) LIKE LOWER(:busqueda) OR LOWER(u.email) LIKE LOWER(:busqueda))
            GROUP  BY u.id
            ORDER  BY u.nombre ASC
        ");
        $stmt->execute([':busqueda' => $termino]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Activa o desactiva un cliente (borrado lógico).
     * El admin puede bloquear un cliente sin eliminar sus datos ni sus reservas.
     *
     * @param  int  $idCliente
     * @param  bool $activo     true = activar, false = desactivar
     * @return bool             true si se actualizó alguna fila
     */
    public static function cambiarEstadoCliente(int $idCliente, bool $activo): bool
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            UPDATE usuarios
               SET activo = :activo
             WHERE id  = :id
               AND rol = 'cliente'
        ");
        $stmt->execute([
            ':activo' => $activo ? 1 : 0,
            ':id'     => $idCliente,
        ]);

        return $stmt->rowCount() > 0;
    }


    // =========================================================================
    // 4. MÉTODO ESPECIAL — Cargar admin por ID desde la BD
    //
    // Equivalente a Cliente::obtenerPorId(), pero devuelve un Administrador.
    // Se usa si en algún momento necesitamos hidratar el objeto admin desde
    // la sesión (aunque normalmente se guarda el objeto entero en $_SESSION).
    // =========================================================================

    /**
     * @return Administrador|null
     */
    public static function obtenerPorId(int $id): ?Administrador
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT id, google_id, nombre, email, password,
                   avatar, telefono, puntos_fidelidad, rol
            FROM   usuarios
            WHERE  id  = :id
              AND  rol = 'admin'
        ");
        $stmt->execute([':id' => $id]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fila == false) {
            return null;
        }

        return new Administrador(
            $fila['nombre'],
            $fila['email'],
            (int)$fila['id'],
            $fila['google_id'],
            $fila['password'],
            $fila['avatar'],
            $fila['telefono'],
            (int)$fila['puntos_fidelidad']
        );
    }
}