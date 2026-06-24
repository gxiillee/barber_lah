<?php

require_once __DIR__ . '/BD.php';

class Producto {

    public static function obtenerActivos(): array {
        try {
            $conexion = BD::obtenerConexion();
            $stmt = $conexion->query("SELECT * FROM productos WHERE activo = 1 ORDER BY orden ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function obtenerTodos(): array {
        try {
            $conexion = BD::obtenerConexion();
            $stmt = $conexion->query("SELECT * FROM productos ORDER BY orden ASC, id DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function obtenerPorId(int|string $id): ?array {
        try {
            $conexion = BD::obtenerConexion();
            $stmt = $conexion->prepare("SELECT * FROM productos WHERE id = :id");
            $stmt->execute([':id' => (int)$id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            return $doc ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function crear(array $datos): bool {
        try {
            $conexion = BD::obtenerConexion();
            $stmt = $conexion->prepare("
                INSERT INTO productos (nombre, descripcion, precio, imagen, activo, orden)
                VALUES (:nombre, :descripcion, :precio, :imagen, 1, :orden)
            ");
            return $stmt->execute([
                ':nombre'      => $datos['nombre'] ?? '',
                ':descripcion' => $datos['descripcion'] ?? '',
                ':precio'      => (float)($datos['precio'] ?? 0),
                ':imagen'      => $datos['imagen'] ?? '',
                ':orden'       => (int)($datos['orden'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function actualizar(int|string $id, array $datos): bool {
        try {
            $conexion = BD::obtenerConexion();
            $set = [];
            $params = [':id' => (int)$id];
            if (isset($datos['nombre']))      { $set[] = 'nombre = :nombre';         $params[':nombre'] = $datos['nombre']; }
            if (isset($datos['descripcion'])) { $set[] = 'descripcion = :descripcion'; $params[':descripcion'] = $datos['descripcion']; }
            if (isset($datos['precio']))      { $set[] = 'precio = :precio';          $params[':precio'] = (float)$datos['precio']; }
            if (isset($datos['imagen']))      { $set[] = 'imagen = :imagen';          $params[':imagen'] = $datos['imagen']; }
            if (isset($datos['orden']))       { $set[] = 'orden = :orden';            $params[':orden'] = (int)$datos['orden']; }

            if (empty($set)) return false;

            $sql = "UPDATE productos SET " . implode(', ', $set) . " WHERE id = :id";
            $stmt = $conexion->prepare($sql);
            return $stmt->execute($params);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function toggleActivo(int|string $id): ?bool {
        try {
            $conexion = BD::obtenerConexion();
            $stmt = $conexion->prepare("SELECT activo FROM productos WHERE id = :id");
            $stmt->execute([':id' => (int)$id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) return null;

            $nuevo = !((bool)$doc['activo']);
            $upd = $conexion->prepare("UPDATE productos SET activo = :activo WHERE id = :id");
            $upd->execute([':activo' => $nuevo ? 1 : 0, ':id' => (int)$id]);
            return $nuevo;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function eliminar(int|string $id): bool {
        try {
            $conexion = BD::obtenerConexion();
            $stmt = $conexion->prepare("DELETE FROM productos WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
