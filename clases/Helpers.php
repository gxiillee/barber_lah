<?php
declare(strict_types=1);

/**
 * Escapa el valor para mostrarlo de forma segura en HTML.
 * Evita ataques XSS convirtiendo caracteres especiales en entidades HTML.
 * Se usa en TODAS las vistas al imprimir datos que vienen de la BD o del usuario.
 */
function h(mixed $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}