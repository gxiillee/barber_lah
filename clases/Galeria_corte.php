<?php
/**
 * Corte — CRUD sobre MongoDB (colección: barberlah.galeria)
 *
 * Cada documento en MongoDB tiene la estructura:
 *   {
 *     "_id": ObjectId,
 *     "imagen": "public/uploads/galeria/corte-degradado.jpg",
 *     "categoria": "Corte degradado",
 *     "descripcion": "Degradado con tijera y navaja",
 *     "activo": true,
 *     "orden": 0,
 *     "fecha_subida": ISODate
 *   }
 *
 * Afecta a:
 *   - Landing pública (index.php raíz): muestra todos (ordenados por fecha desc)
 *   - Admin galeria.php: CRUD completo
 */
class Corte {

    /**
     * Devuelve todas las fotos de la galería para la landing pública.
     * Solo activas, ordenadas por orden ascendente.
     */
    public static function obtenerActivos(): array {
        try {
            $db = ConexionMongo::conectar();
            return $db->galeria->find(
                ['activo' => true],
                ['sort' => ['orden' => 1]]
            )->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Devuelve TODAS las fotos (activas e inactivas) para el admin.
     */
    public static function obtenerTodos(): array {
        try {
            $db = ConexionMongo::conectar();
            return $db->galeria->find(
                [],
                ['sort' => ['orden' => 1, '_id' => -1]]
            )->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Devuelve una foto por su ID (string ObjectId de MongoDB).
     */
    public static function obtenerPorId(string $id): ?array {
        try {
            $db = ConexionMongo::conectar();
            $doc = $db->galeria->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            return $doc !== null ? (array)$doc : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Crea una nueva foto en la galería.
     *
     * @param array $datos Campos: imagen (ruta), categoria, descripcion, orden
     */
    public static function crear(array $datos): bool {
        try {
            $db = ConexionMongo::conectar();
            $doc = [
                'imagen'       => $datos['imagen'] ?? '',
                'categoria'    => $datos['categoria'] ?? '',
                'descripcion'  => $datos['descripcion'] ?? '',
                'activo'       => true,
                'orden'        => (int)($datos['orden'] ?? 0),
                'fecha_subida' => new MongoDB\BSON\UTCDateTime(time() * 1000),
            ];
            $result = $db->galeria->insertOne($doc);
            return $result->getInsertedCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Actualiza una foto existente.
     *
     * @param string $id    ObjectId del documento
     * @param array  $datos Campos a actualizar
     */
    public static function actualizar(string $id, array $datos): bool {
        try {
            $db = ConexionMongo::conectar();
            $set = [];
            if (isset($datos['imagen']))       $set['imagen'] = $datos['imagen'];
            if (isset($datos['categoria']))    $set['categoria'] = $datos['categoria'];
            if (isset($datos['descripcion']))  $set['descripcion'] = $datos['descripcion'];
            if (isset($datos['orden']))        $set['orden'] = (int)$datos['orden'];

            if (empty($set)) return false;

            $result = $db->galeria->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $set]
            );
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Alterna el estado activo/inactivo de una foto.
     * Devuelve el nuevo estado o null si falla.
     */
    public static function toggleActivo(string $id): ?bool {
        try {
            $db = ConexionMongo::conectar();
            $doc = $db->galeria->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            if (!$doc) return null;

            $nuevo = !((bool)$doc['activo']);
            $db->galeria->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => ['activo' => $nuevo]]
            );
            return $nuevo;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Elimina una foto definitivamente de MongoDB.
     */
    public static function eliminar(string $id): bool {
        try {
            $db = ConexionMongo::conectar();
            $result = $db->galeria->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            return $result->getDeletedCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
