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