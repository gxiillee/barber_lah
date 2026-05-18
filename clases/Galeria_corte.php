<?php
class Corte {
    /**
     * SACA TODAS LAS FOTOS DE LOS CORTES PARA CARRUSEL INDEX
     * GENERA UN array con las FOTOS
     */
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