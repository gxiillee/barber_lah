<?php
declare(strict_types=1);

require_once __DIR__ . '/BD.php';

class ConfigWeb {

    public static function obtener(): array {
        try {
            $conexion = BD::obtenerConexion();
            $stmt = $conexion->prepare("SELECT * FROM config_web WHERE id = 'negocio'");
            $stmt->execute();
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            return $doc ?: self::defaults();
        } catch (Exception $e) {
            error_log("ConfigWeb::obtener error: " . $e->getMessage());
            return self::defaults();
        }
    }

    public static function guardar(array $datos): bool {
        try {
            $conexion = BD::obtenerConexion();

            $campos = [
                'direccion', 'telefono', 'email', 'instagram', 'horario_resumen',
                'sobre_subtitulo', 'sobre_titulo', 'sobre_imagen',
                'sobre_anios', 'sobre_anios_texto', 'sobre_nombre',
                'sobre_texto_1', 'sobre_texto_2', 'sobre_texto_3',
            ];

            $valores = array_merge(self::defaults(), $datos);

            $columnas = implode(', ', $campos);
            $placeholders = implode(', ', array_map(fn($c) => ":$c", $campos));

            $sql = "REPLACE INTO config_web (id, $columnas) VALUES ('negocio', $placeholders)";
            $stmt = $conexion->prepare($sql);

            $params = [];
            foreach ($campos as $c) {
                $params[":$c"] = $valores[$c] ?? '';
            }

            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("ConfigWeb::guardar error: " . $e->getMessage());
            return false;
        }
    }

    private static function defaults(): array {
        return [
            'id'              => 'negocio',
            'direccion'       => '',
            'telefono'        => '',
            'email'           => '',
            'instagram'       => '',
            'horario_resumen' => '',

            'sobre_subtitulo'  => 'Barbershop La H',
            'sobre_titulo'     => 'Sobre Nosotros',
            'sobre_imagen'     => 'public/assets/img/logo.jpg',
            'sobre_anios'      => '+10',
            'sobre_anios_texto' => 'Años de exp.',
            'sobre_nombre'     => 'Hassan',
            'sobre_texto_1'    => '',
            'sobre_texto_2'    => '',
            'sobre_texto_3'    => '',
        ];
    }
}
