<?php
// clases/Corte.php
class Corte {
    public static function obtenerTodos() {
        try {
            $db = ConexionMongo::conectar();
            // Buscamos todos los cortes en la colección 'galeria'
            return $db->galeria->find([], ['sort' => ['_id' => -1]])->toArray();
        } catch (Exception $e) {
            return []; // Retorna vacío si hay error para no romper la web
        }
    }
}