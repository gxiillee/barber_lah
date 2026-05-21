<?php
declare(strict_types=1);

/**
 * Funciones auxiliares compartidas utilizadas en todo el proyecto.
 * Mantener este archivo mínimo y enfocado en utilidades esenciales.
 */

/**
 * Escapa el valor para mostrarlo de forma segura en HTML.
 * Evita XSS convirtiendo caracteres especiales en entidades HTML.
 * Se usa en TODAS las vistas al imprimir datos de la BD o del usuario.
 *
 * @param mixed $valor Valor a escapar (se convierte a string internamente)
 * @return string      Valor escapado listo para imprimir en HTML
 */
function h(mixed $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige a la URL indicada y detiene la ejecucion.
 * Evita repetir header() + exit en cada pagina.
 *
 * @param string $url URL de destino (relativa o absoluta)
 * @return void
 */
function redirigir(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------------
// FUNCIONES DE FORMATEO DE FECHAS EN ESPAÑOL
// Movidas aquí desde Reserva.php al refactorizar (SRP):
// la clase Reserva gestiona lógica de negocio; el formateo
// de cadenas legibles para el usuario pertenece a las utilidades globales.
// ---------------------------------------------------------------

/**
 * Convierte el número de mes (1-12) en su nombre en español.
 * Usada en fechaHumana() y en construirDiasSemana() de la clase Reserva.
 */
function nombreMes(int $mes): string
{
    return [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo',
        4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
        10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ][$mes] ?? '';
}

/**
 * Convierte el número ISO del día de la semana (1=lunes, 7=domingo) en su nombre en español.
 * Usada en fechaHumana() y en construirDiasSemana() de la clase Reserva.
 */
function nombreDia(int $dia): string
{
    return [
        1 => 'lunes', 2 => 'martes', 3 => 'miercoles',
        4 => 'jueves', 5 => 'viernes', 6 => 'sabado',
        7 => 'domingo',
    ][$dia] ?? '';
}

/**
 * Convierte una fecha 'Y-m-d' en texto legible para el usuario: "lunes 3 de junio".
 * Se usa en las vistas de confirmacion (confirmar_reserva.php, reserva_exito.php)
 * y dentro de construirDiasSemana() para rellenar el campo 'dia_largo' del JSON.
 */
function fechaHumana(string $fecha): string
{
    $dt = new DateTimeImmutable($fecha);
    return nombreDia((int)$dt->format('N'))
        . ' ' . $dt->format('j')
        . ' de ' . nombreMes((int)$dt->format('n'));
}

/**
 * Valida que la cadena sea una fecha real en formato 'Y-m-d'.
 * Necesario porque PHP interpola fechas inválidas como '2025-02-31'
 * sin lanzar error; createFromFormat con '!' previene ese comportamiento.
 */
function esFechaValida(string $fecha): bool
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $fecha;
}

/**
 * Devuelve el lunes de la semana a la que pertenece la fecha recibida.
 * format('N') devuelve 1=lunes … 7=domingo (ISO 8601).
 */
function obtenerLunesDeSemana(DateTimeImmutable $fecha): DateTimeImmutable
{
    return $fecha->modify('-' . ((int)$fecha->format('N') - 1) . ' days');
}

/** Versión de obtenerLunesDeSemana que trabaja con strings 'Y-m-d' en vez de objetos. */
function obtenerLunesDeSemanaStr(string $fecha): string
{
    return obtenerLunesDeSemana(new DateTimeImmutable($fecha))->format('Y-m-d');
}

/**
 * Genera el título visible de la semana en el calendario.
 * Usa la función global nombreMes().
 * Ejemplo: "mayo 2025" o "mayo / junio 2025" si la semana abarca dos meses.
 */
function obtenerTituloSemana(DateTimeImmutable $inicioSemana): string
{
    $finSemana = $inicioSemana->modify('+6 days');
    $mesInicio = nombreMes((int)$inicioSemana->format('n'));
    $mesFin    = nombreMes((int)$finSemana->format('n'));

    return $mesInicio === $mesFin
        ? $mesInicio . ' ' . $inicioSemana->format('Y')
        : $mesInicio . ' / ' . $mesFin . ' ' . $inicioSemana->format('Y');
}

/**
 * Calcula el estado de los botones de navegación del calendario semanal.
 * Devuelve las fechas de semana anterior y siguiente, y los booleanos
 * que controlan si los botones ‹ › están activos o deshabilitados.
 */
function calcularBotonesNavegacion(
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