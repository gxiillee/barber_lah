<?php
declare(strict_types=1);

/**
 * Gestiona los tokens CSRF para proteger los formularios contra peticiones falsificadas.
 * Cada formulario tiene su propia clave de sesion para poder validarlos de forma independiente.
 *
 * Uso:
 *   $token = Csrf::generarToken('csrf_reserva');          // en la vista
 *   Csrf::validarToken('csrf_reserva', $_POST['csrf_token']) // al procesar el POST
 */
class Csrf {

    /**
     * Devuelve el token CSRF asociado a la clave dada.
     * Si aun no existe en sesion lo genera y lo guarda.
     * La sesion debe estar iniciada antes de llamar a este metodo.
     */
    public static function generarToken(string $clave): string {
        if (empty($_SESSION[$clave])) {
            $_SESSION[$clave] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$clave];
    }

    /**
     * Comprueba que el token enviado en el formulario coincide con el guardado en sesion.
     * Usa hash_equals para evitar ataques de tiempo.
     */
    public static function validarToken(string $clave, ?string $token): bool {
        return isset($_SESSION[$clave])
            && is_string($token)
            && hash_equals($_SESSION[$clave], $token);
    }
}