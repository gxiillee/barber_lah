<?php
// clases/Producto.php
class Producto {
    public static function obtenerActivos() {
        try {
            $db = ConexionMongo::conectar();
            // Solo productos donde activo sea true
            return $db->productos->find(['activo' => true])->toArray();
        } catch (Exception $e) {
            return [];
        }
    }
}