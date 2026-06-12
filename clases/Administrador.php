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
        ?int    $id            = null,
        ?string $google_id     = null,
        string  $nombre,
        string  $email,
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
                COUNT(*) FILTER (WHERE estado = 'confirmada')        AS confirmadas,
                COUNT(*) FILTER (WHERE estado = 'completada')        AS completadas,
                COUNT(*) FILTER (WHERE estado = 'no_presentado')     AS no_presentados,
                COUNT(*) FILTER (WHERE estado = 'cancelada')         AS canceladas,
                COALESCE(
                    SUM(precio_historico) FILTER (WHERE estado = 'completada'),
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
                COUNT(*) FILTER (WHERE estado = 'completada')       AS completadas,
                COALESCE(
                    SUM(precio_historico) FILTER (WHERE estado = 'completada'),
                    0
                )                                                    AS ingresos
            FROM reservas
            WHERE fecha >= date_trunc('week', CURRENT_DATE)
              AND fecha <  date_trunc('week', CURRENT_DATE) + INTERVAL '7 days'
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
     * Resumen del mes en curso.
     *
     * @return array{total: int, completadas: int, ingresos: float, clientes_nuevos: int}
     */
    public static function obtenerResumenMesActual(): array
    {
        $conexion = BD::obtenerConexion();

        // Ingresos y citas del mes
        $stmt = $conexion->prepare("
            SELECT
                COUNT(*)                                             AS total,
                COUNT(*) FILTER (WHERE estado = 'completada')       AS completadas,
                COALESCE(
                    SUM(precio_historico) FILTER (WHERE estado = 'completada'),
                    0
                )                                                    AS ingresos
            FROM reservas
            WHERE date_trunc('month', fecha) = date_trunc('month', CURRENT_DATE)
        ");
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Clientes que se registraron este mes (dato de usuarios, no de reservas)
        $stmtClientes = $conexion->prepare("
            SELECT COUNT(*) AS clientes_nuevos
            FROM   usuarios
            WHERE  rol = 'cliente'
              AND  date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE)
        ");
        $stmtClientes->execute();
        $filaClientes = $stmtClientes->fetch(PDO::FETCH_ASSOC);

        return [
            'total'           => (int)$fila['total'],
            'completadas'     => (int)$fila['completadas'],
            'ingresos'        => (float)$fila['ingresos'],
            'clientes_nuevos' => (int)$filaClientes['clientes_nuevos'],
        ];
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
    public static function obtenerTodosLosClientes(): array
    {
        $conexion = BD::obtenerConexion();

        $stmt = $conexion->prepare("
            SELECT u.id,
                   u.nombre,
                   u.email,
                   u.telefono,
                   u.avatar,
                   u.puntos_fidelidad,
                   u.activo,
                   u.created_at,
                   COUNT(r.id)                                            AS total_reservas,
                   COUNT(r.id) FILTER (WHERE r.estado = 'completada')    AS citas_completadas
            FROM   usuarios u
            LEFT JOIN reservas r ON r.id_cliente = u.id
            WHERE  u.rol = 'cliente'
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
                   COUNT(r.id)                                            AS total_reservas,
                   COUNT(r.id) FILTER (WHERE r.estado = 'completada')    AS citas_completadas
            FROM   usuarios u
            LEFT JOIN reservas r ON r.id_cliente = u.id
            WHERE  u.rol    = 'cliente'
              AND (u.nombre ILIKE :busqueda OR u.email ILIKE :busqueda)
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
            ':activo' => $activo ? 'true' : 'false',
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
            (int)$fila['id'],
            $fila['google_id'],
            $fila['nombre'],
            $fila['email'],
            $fila['password'],
            $fila['avatar'],
            $fila['telefono'],
            (int)$fila['puntos_fidelidad']
        );
    }
}