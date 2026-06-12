<?php
/**
 * Producto — CRUD sobre MongoDB (colección: barberlah.productos)
 *
 * Cada documento en MongoDB tiene la estructura:
 *   {
 *     "_id": ObjectId,
 *     "nombre": "Pomada modeladora",
 *     "descripcion": "Fijación fuerte...",
 *     "precio": 12.50,
 *     "imagen": "public/uploads/productos/pomada.jpg",
 *     "activo": true,
 *     "orden": 0
 *   }
 *
 * Afecta a:
 *   - Landing pública (index.php raíz): muestra solo activos
 *   - Admin productos.php: CRUD completo
 */
class Producto {

    /**
     * Devuelve todos los productos activos para la landing pública.
     */
    public static function obtenerActivos(): array {
        try {
            $db = ConexionMongo::conectar();
            return $db->productos->find(
                ['activo' => true],
                ['sort' => ['orden' => 1]]
            )->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Devuelve todos los productos (activos e inactivos) para el admin.
     */
    public static function obtenerTodos(): array {
        try {
            $db = ConexionMongo::conectar();
            return $db->productos->find(
                [],
                ['sort' => ['orden' => 1, '_id' => -1]]
            )->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Devuelve un producto por su ID (string ObjectId de MongoDB).
     */
    public static function obtenerPorId(string $id): ?array {
        try {
            $db = ConexionMongo::conectar();
            $doc = $db->productos->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            return $doc !== null ? (array)$doc : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Crea un nuevo producto.
     *
     * @param array $datos Campos: nombre, descripcion, precio, imagen (ruta), orden
     */
    public static function crear(array $datos): bool {
        try {
            $db = ConexionMongo::conectar();
            $doc = [
                'nombre'      => $datos['nombre'] ?? '',
                'descripcion' => $datos['descripcion'] ?? '',
                'precio'      => (float)($datos['precio'] ?? 0),
                'imagen'      => $datos['imagen'] ?? '',
                'activo'      => true,
                'orden'       => (int)($datos['orden'] ?? 0),
            ];
            $result = $db->productos->insertOne($doc);
            return $result->getInsertedCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Actualiza un producto existente.
     *
     * @param string $id    ObjectId del documento
     * @param array  $datos Campos a actualizar
     */
    public static function actualizar(string $id, array $datos): bool {
        try {
            $db = ConexionMongo::conectar();
            $set = [];
            if (isset($datos['nombre']))      $set['nombre'] = $datos['nombre'];
            if (isset($datos['descripcion'])) $set['descripcion'] = $datos['descripcion'];
            if (isset($datos['precio']))      $set['precio'] = (float)$datos['precio'];
            if (isset($datos['imagen']))      $set['imagen'] = $datos['imagen'];
            if (isset($datos['orden']))       $set['orden'] = (int)$datos['orden'];

            if (empty($set)) return false;

            $result = $db->productos->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $set]
            );
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Alterna el estado activo/inactivo de un producto.
     * Devuelve el nuevo estado o null si falla.
     */
    public static function toggleActivo(string $id): ?bool {
        try {
            $db = ConexionMongo::conectar();
            $doc = $db->productos->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            if (!$doc) return null;

            $nuevo = !((bool)$doc['activo']);
            $db->productos->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => ['activo' => $nuevo]]
            );
            return $nuevo;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Elimina un producto definitivamente de MongoDB.
     */
    public static function eliminar(string $id): bool {
        try {
            $db = ConexionMongo::conectar();
            $result = $db->productos->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            return $result->getDeletedCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
