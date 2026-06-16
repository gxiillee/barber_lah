<?php
/**
 * Bootstrap — Carga las variables de entorno desde .env
 * Si no hay .env, usa los valores por defecto en cada clase.
 */
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    // .env no encontrado — seguro en producción con vars de entorno reales
}
