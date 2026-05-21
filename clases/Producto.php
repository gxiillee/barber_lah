<?php
// clases/Producto.php
class Producto {
    /**
     * SACA TODOS LOS PRODUCTOS ACTIVOS INDEX
     * GENERA UN array con los PRODUCTOS
     */
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