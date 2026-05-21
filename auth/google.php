<?php
declare(strict_types=1);

session_start();

// Importamos el modelo de datos y los helpers unificados
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Helpers.php';

// Credenciales inalteradas de tu API de Google
define('GOOGLE_CLIENT_ID',     '107886896236-hg74nkhc5qvh64v0h32j7kgp0is7psrs.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  'http://localhost/barberlah/auth/google.php');

try {
    // -------------------------------------------------------------------------
    // FASE 1: Redirigir al usuario a Google (Si no existe código de retorno)
    // -------------------------------------------------------------------------
    if (!isset($_GET['code'])) {
        // Generamos un token anti-CSRF para el flujo OAuth y lo guardamos en sesión
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));

        $parametros = [
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'email profile',
            'state'         => $_SESSION['oauth_state'],
            'prompt'        => 'select_account'
        ];

        // Usamos la nueva función global de redirección
        redirigir('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($parametros));
    }

    // -------------------------------------------------------------------------
    // FASE 2: Callback de Google (Procesar la respuesta)
    // -------------------------------------------------------------------------

    // Validación de seguridad obligatoria del Estado (State)
    if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        throw new Exception('Fallo crítico de verificación de estado OAuth.');
    }
    unset($_SESSION['oauth_state']); // Limpieza preventiva del token de estado

    // 1. Intercambiar el código de autorización por el Token de Acceso (Access Token)
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

    // 2. Recuperar los datos del perfil utilizando el Token de Acceso
    $accessToken = $respuestaToken['access_token'];
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . urlencode($accessToken));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $datosGoogle = json_decode(curl_exec($ch) ?: '', true);
    curl_close($ch);

    if (empty($datosGoogle['sub'])) {
        throw new Exception('No se pudieron recuperar los datos de perfil de Google.');
    }

    // Extracción limpia de variables
    $googleId = (string)$datosGoogle['sub'];
    $nombre   = (string)($datosGoogle['name'] ?? 'Usuario Google');
    $email    = (string)$datosGoogle['email'];
    $avatar   = $datosGoogle['picture'] ?? null;

    // 3. Buscar o Registrar al usuario en PostgreSQL mediante el Modelo Limpio
    $usuario = Usuario::comprobarRegistrarGoogle($googleId, $nombre, $email, $avatar);

    if (!$usuario instanceof Usuario) {
        throw new Exception('El modelo no devolvió una instancia válida de Usuario.');
    }

    // Autenticación exitosa: Regeneramos ID de sesión por seguridad informática
    session_regenerate_id(true);
    $_SESSION['usuario'] = $usuario;

    // Redirección inteligente unificada respetando la carpeta public/
    if (isset($_SESSION['reserva_pendiente']) && is_array($_SESSION['reserva_pendiente'])) {
        redirigir('../public/confirmar_reserva.php');
    } else {
        redirigir('../cliente/mi-cuenta.php');
    }

} catch (Throwable $e) {
    // Si algo falla, redirige al login público con la ruta corregida hacia la carpeta public/
    redirigir('../public/login.php?error_google=1');
}