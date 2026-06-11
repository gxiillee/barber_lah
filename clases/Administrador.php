<?php
// Obliga php a ser estricto con lo que tiene que devolver una funcion
declare(strict_types=1);

require_once __DIR__ . '/BD.php';
require_once __DIR__ . '/Usuario.php';

class Administrador extends Usuario {

    // 1. CONSTRUCTOR
    // Hereda de Usuario y fuerza el rol a 'admin'.
    // Todos los campos aceptan nulabilidad excepto nombre y email.
    public function __construct(
        ?int $id = null,
        ?string $google_id = null,
        string $nombre,
        string $email,
        ?string $password = null,
        ?string $avatar = null,
        ?string $telefono = null,
        int $puntos_fidelidad = 0,
        string $rol = 'admin'
    ) {
        parent::__construct($id, $google_id, $nombre, $email, $password, $avatar, $telefono, $puntos_fidelidad, $rol);
    }

    // 3. MÉTODOS DE ESTADÍSTICAS (Panel de Gestión)

    /**
     * Devuelve el número total de citas agendadas para el día de hoy.
     * Se podria utilizar para un resumen rápido del Dashboard de Hassan.
     */
    public static function obtenerResumenCitasHoy(): int {
        $conexion = BD::obtenerConexion();

        // COUNT(*) siempre devuelve una fila, incluso si es 0
        $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM reservas WHERE fecha = CURRENT_DATE");
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Retornamos el valor entero directamente
        return $fila ? (int)$fila['total'] : 0;
    }

    /**
     * Ejemplo de método adicional: Obtener próximos clientes (opcional)
     * Puedes ir añadiendo aquí lógica específica que solo Hassan deba ver.
     */
}
