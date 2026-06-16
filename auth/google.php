<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
iniciarSesionSegura();
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Helpers.php';

define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']);
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET']);
define('GOOGLE_REDIRECT_URI',  $_ENV['GOOGLE_REDIRECT_URI']);

try {
    // -------------------------------------------------------------------------
    // FASE 1: Redirigir al usuario a Google (Si no existe código de retorno)
    // -------------------------------------------------------------------------
    if (!isset($_GET['code'])) {
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));

        $parametros = [
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'email profile',
            'state'         => $_SESSION['oauth_state'],
            'prompt'        => 'select_account'
        ];

        redirigir('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($parametros));
    }

    // -------------------------------------------------------------------------
    // FASE 2: Callback de Google (Procesar la respuesta)
    // -------------------------------------------------------------------------

    if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        throw new Exception('Fallo crítico de verificación de estado OAuth.');
    }
    unset($_SESSION['oauth_state']);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code'
        ])
    ]);
    $respuestaToken = json_decode(curl_exec($ch) ?: '', true);
    curl_close($ch);

    if (empty($respuestaToken['access_token'])) {
        throw new Exception('No se pudo obtener el token de acceso de Google.');
    }

    $accessToken = $respuestaToken['access_token'];
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . urlencode($accessToken));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $datosGoogle = json_decode(curl_exec($ch) ?: '', true);
    curl_close($ch);

    if (empty($datosGoogle['sub'])) {
        throw new Exception('No se pudieron recuperar los datos de perfil de Google.');
    }

    $googleId = (string)$datosGoogle['sub'];
    $nombre   = (string)($datosGoogle['name'] ?? 'Usuario Google');
    $email    = (string)$datosGoogle['email'];
    $avatar   = $datosGoogle['picture'] ?? null;

    $usuario = Usuario::comprobarRegistrarGoogle($googleId, $nombre, $email, $avatar);

    if (!$usuario instanceof Usuario) {
        throw new Exception('El modelo no devolvió una instancia válida de Usuario.');
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = $usuario;
    $_SESSION['pwd_updated_at'] = $usuario->getPasswordUpdatedAt();

    if (isset($_SESSION['reserva_pendiente']) && is_array($_SESSION['reserva_pendiente'])) {
        redirigir('../cliente/confirmar_reserva.php');
    } else {
        redirigir('../mi-cuenta.php');
    }

} catch (Throwable $e) {
    redirigir('../login.php?error_google=1');
}
