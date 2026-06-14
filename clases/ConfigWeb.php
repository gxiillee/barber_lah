<?php
declare(strict_types=1);

require_once __DIR__ . '/BdMongo.php';

class ConfigWeb {

    private const COLECCION = 'config_web';

    public static function obtener(): array {
        try {
            $db = ConexionMongo::conectar();
            $doc = $db->{self::COLECCION}->findOne(['_id' => 'negocio']);
            return $doc ? iterator_to_array($doc) : self::defaults();
        } catch (Exception $e) {
            error_log("ConfigWeb::obtener error: " . $e->getMessage());
            return self::defaults();
        }
    }

    public static function guardar(array $datos): bool {
        try {
            $db = ConexionMongo::conectar();
            $datos['_id'] = 'negocio';
            $result = $db->{self::COLECCION}->replaceOne(
                ['_id' => 'negocio'],
                $datos,
                ['upsert' => true]
            );
            return $result->isAcknowledged();
        } catch (Exception $e) {
            error_log("ConfigWeb::guardar error: " . $e->getMessage());
            return false;
        }
    }

    private static function defaults(): array {
        return [
            '_id'             => 'negocio',
            'direccion'       => '',
            'telefono'        => '',
            'email'           => '',
            'instagram'       => '',
            'horario_resumen' => '',

            // Sección "Sobre Nosotros"
            'sobre_subtitulo' => 'Barbershop La H',
            'sobre_titulo'    => 'Sobre Nosotros',
            'sobre_imagen'    => 'public/assets/img/logo.jpg',
            'sobre_anios'     => '+10',
            'sobre_anios_texto' => 'Años de exp.',
            'sobre_nombre'    => 'Hassan',
            'sobre_texto_1'   => '',
            'sobre_texto_2'   => '',
            'sobre_texto_3'   => '',
        ];
    }
}
