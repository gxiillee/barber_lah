<?php
// Obliga php a ser estricto con lo que tiene que devolver una funcion
declare(strict_types=1);

require_once __DIR__ . '/conexiones/BD.php';
require_once __DIR__ . '/Usuario.php';

class Cliente extends Usuario {

    // 1. CONSTRUCTOR
    // Forzamos el rol 'cliente' al llamar al constructor padre.
    public function __construct($id, $nombre, $email, $password, $telefono) {
        parent::__construct($id, $nombre, $email, $password, $telefono, 'cliente');
    }

    // 2. MÉTODOS DE ACCIÓN (Escritura en BD)

    /**
     * Inserta un nuevo cliente en la base de datos con contraseña encriptada.
     */
    public static function crear(string $nombre, string $email, string $password_normal, string $telefono): string {
        $conexion = BD::obtenerConexion();
        $password_hash = password_hash($password_normal, PASSWORD_DEFAULT);

        $stmt = $conexion->prepare("
            INSERT INTO usuarios (nombre, email, password, telefono, rol, activo) 
            VALUES (:nombre, :email, :password, :telefono, 'cliente', true)
        ");

        $stmt->execute([
            'nombre'   => $nombre,
            'email'    => $email,
            'password' => $password_hash,
            'telefono' => $telefono
        ]);

        return (string)$conexion->lastInsertId();
    }

    // 3. PROCESAMIENTO DE FORMULARIO (Lógica de Negocio)

    /**
     * Gestiona el flujo de registro: Valida, Crea e Inicia Sesión automáticamente.
     */
    public static function procesarRegistroLogin(array $datos): array {
        // 1. Inicializamos estado y modo
        $estado = self::estadoFormularioLogin();
        $estado['modo'] = 'registro';
        $estado['valores'] = self::cargarValoresPost($datos);

        // Extraemos valores limpios
        $nombre   = $estado['valores']['nombre'];
        $telefono = $estado['valores']['telefono'];
        $email    = $estado['valores']['email_registro'];
        $password = (string)($datos['password_registro'] ?? '');

        // 2. Validación de Seguridad (CSRF)
        if (!self::tokenLoginValido($datos['csrf_token'] ?? null)) {
            $estado['errorRegistro'] = 'Sesión caducada. Por favor, recarga.';
            return $estado;
        }

        // 3. Validaciones de Campos Vacíos
        if (empty($nombre) || empty($telefono) || empty($email) || empty($password)) {
            $estado['errorRegistro'] = 'Rellena todos los campos para tu alta VIP.';
            return $estado;
        }

        // 4. Validación de Formato de Email (usando el método del padre)
        if (!self::validarEmail($email)) {
            $estado['errorRegistro'] = 'Introduce un email válido.';
            return $estado;
        }

        // 5. Validación de Seguridad de Contraseña
        if (strlen($password) < 6) {
            $estado['errorRegistro'] = 'La contraseña debe tener al menos 6 caracteres.';
            return $estado;
        }

        // 6. Intento de Creación y Autenticación Automática
        try {
            self::crear($nombre, $email, $password, $telefono);

            // Si se crea con éxito, lo logueamos directamente para mejorar la UX
            $usuario = self::comprobarLogin($email, $password);

            if ($usuario instanceof Usuario) {
                $estado['usuario'] = $usuario;
            } else {
                $estado['errorRegistro'] = 'Cuenta creada, pero hubo un error al entrar. Inicia sesión manualmente.';
            }

        } catch (PDOException $e) {
            // Error 23000: Violación de restricción de integridad (email duplicado)
            if ($e->getCode() === '23000') {
                $estado['errorRegistro'] = 'Este email ya pertenece a un socio del Club VIP.';
            } else {
                $estado['errorRegistro'] = 'Error de conexión. Inténtalo más tarde.';
            }
        } catch (Throwable $e) {
            $estado['errorRegistro'] = 'No se ha podido completar el registro.';
        }

        return $estado;
    }

    // 4. MÉTODOS DE CONSULTA (Lectura)

    /**
     * Recupera la lista completa de clientes para el panel de administración.
     */
    public static function obtenerTodosLosClientes(): array {
        $conexion = BD::obtenerConexion();
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE rol = 'cliente' ORDER BY id DESC");
        $stmt->execute();

        $clientes = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clientes[] = new self(
                $fila['id'], $fila['nombre'], $fila['email'],
                $fila['password'], $fila['telefono']
            );
        }
        return $clientes;
    }
}
