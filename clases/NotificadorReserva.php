<?php
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/Usuario.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificadorReserva
{
    private static function configurarMail(PHPMailer $mail): void
    {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);
        $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@barbershoplah.com';
        $fromName  = $_ENV['SMTP_FROM_NAME'] ?? 'Barbershop La H';
        $mail->setFrom($fromEmail, $fromName);
    }

    private static function enviar(string $to, string $toName, string $subject, string $htmlBody, string $altBody): bool
    {
        if (!class_exists(PHPMailer::class)) {
            return false;
        }
        try {
            $mail = new PHPMailer(true);
            self::configurarMail($mail);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody;
            return $mail->send();
        } catch (Exception $e) {
            error_log('NotificadorReserva: ' . $e->getMessage());
            return false;
        }
    }

    private static function plantilla(string $titulo, string $contenido): string
    {
        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>' . htmlspecialchars($titulo) . '</title>
<style>
    html[data-ogsc] u + .body .gmail-blend-screen { background:#000; mix-blend-mode:screen; }
    html[data-ogsc] u + .body .gmail-blend-difference { background:#000; mix-blend-mode:difference; }
    body { margin: 0; padding: 0; background-color: #e9e4d8; }
    @media only screen and (max-width: 480px) {
        .container-la-h { width: 100% !important; max-width: 100% !important; }
    }
    @media (prefers-color-scheme: dark) {
        .bg-body { background-color: #161310 !important; }
        .bg-card { background-color: #221c16 !important; }
        .bg-header { background-color: #120f0c !important; }
        .bg-perforado { background-color:#120f0c !important; background-image:radial-gradient(circle at 0 50%,#161310 7px,transparent 7.5px) !important; }
        .text-title { color:#d4af6a !important; }
        .text-main { color:#f5f0e6 !important; }
        .text-muted { color:#a89982 !important; }
        .border-line { border-color:#332b24 !important; }
        .btn-calendar { color:#d4af6a !important; border-color:#d4af6a !important; }
    }
</style>
</head>
<body class="body bg-body" style="margin:0; padding:0; background-color:#e9e4d8;">
<div class="gmail-blend-screen">
<div class="gmail-blend-difference">
<div class="bg-body" style="background-color:#e9e4d8; padding:32px 16px;">

<div class="container-la-h bg-card" style="max-width:420px; margin:0 auto; background-color:#f3ecde; border-radius:4px; overflow:hidden; font-family:Helvetica,Arial,sans-serif;">

    <div class="bg-header" style="background-color:#1b1712; padding:30px 28px 22px; text-align:center;">
        <div style="width:56px; height:1px; background-color:#b3863a; margin:0 auto 14px; font-size:0; line-height:0;">&nbsp;</div>
        <h1 class="text-title" style="margin:0; font-family:Georgia,\'Times New Roman\',serif; font-weight:700; color:#d4af6a; font-size:23px; letter-spacing:2.5px;">Barbershop La H</h1>
        <p class="text-muted" style="margin:6px 0 0; color:#8c7a55; font-size:10.5px; letter-spacing:2.5px; text-transform:uppercase;">Barbería · Zaragoza</p>
    </div>

    <div class="bg-perforado" style="height:14px; background-color:#1b1712; background-image:radial-gradient(circle at 0 50%, #e9e4d8 7px, transparent 7.5px); background-size:14px 14px; background-repeat:repeat-x;">&nbsp;</div>

    <div style="padding:30px 28px 8px;">
        <h2 class="text-title" style="margin:0 0 14px; font-family:Georgia,\'Times New Roman\',serif; font-style:italic; font-weight:normal; color:#a9762c; font-size:18px;">' . $titulo . '</h2>
        <div style="font-size:14.5px; line-height:1.65;">
            ' . $contenido . '
        </div>
    </div>

    <div class="border-line" style="padding:20px 28px 28px; text-align:center; border-top:1px solid #ddd2b4; margin-top:18px;">
        <p class="text-muted" style="margin:0; color:#8c7a55; font-size:12.5px; font-style:italic;">Gracias por confiar en nuestro trabajo.</p>
        <p class="text-title" style="margin:6px 0 0; font-family:Georgia,\'Times New Roman\',serif; font-style:italic; color:#a9762c; font-size:14px;">— Hassan</p>
    </div>

</div>

</div>
</div>
</div>
</body>
</html>';
    }

    private static function linea(string $label, string $valor): string
    {
        return '
    <div class="border-line" style="display:table; width:100%; padding:11px 0; border-bottom:1px dashed #c9b88f;">
        <div class="text-muted" style="display:table-cell; color:#8c7a55; font-size:10.5px; letter-spacing:1.6px; text-transform:uppercase; vertical-align:middle;">' . $label . '</div>
        <div class="text-main" style="display:table-cell; text-align:right; color:#1b1712; font-size:14.5px; font-weight:bold; vertical-align:middle;">' . $valor . '</div>
    </div>';
    }

    private static function enlaceCalendario(string $servicio, string $fechaIso, string $hora, int $duracionMin = 30): string
    {
        try {
            $inicio = new \DateTime($fechaIso . ' ' . $hora);
        } catch (\Exception $e) {
            return '';
        }
        $fin = (clone $inicio)->modify("+{$duracionMin} minutes");

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => $servicio . ' · Barbershop La H',
            'dates'    => $inicio->format('Ymd\THis') . '/' . $fin->format('Ymd\THis'),
            'details'  => 'Cita reservada en Barbershop La H.',
            'location' => 'Barbershop La H, Zaragoza',
            'ctz'      => 'Europe/Madrid',
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    private static function botonCalendario(string $enlace): string
    {
        if ($enlace === '') {
            return '';
        }
        return '
    <div style="text-align:center; margin:22px 0 6px;">
        <a href="' . $enlace . '" class="btn-calendar" style="display:inline-block; border:1px dashed #b3863a; border-radius:3px; padding:11px 20px; text-decoration:none; color:#a9762c; font-size:13px; font-weight:bold; letter-spacing:0.3px;">
            &#128197;&nbsp; Añadir a mi calendario
        </a>
    </div>';
    }

    public static function enviarConfirmacion(Usuario $usuario, array $detalle): bool
    {
        $nombre  = htmlspecialchars($usuario->getNombre());
        $servicio = htmlspecialchars($detalle['servicio'] ?? $detalle['nombre_servicio'] ?? '');
        $fecha   = htmlspecialchars($detalle['fecha_humana'] ?? $detalle['fecha_label'] ?? $detalle['fecha'] ?? '');
        $hora    = htmlspecialchars($detalle['hora'] ?? '');
        $precio  = htmlspecialchars((string)($detalle['precio'] ?? ''));

        $fechaIso  = $detalle['fecha'] ?? '';
        $enlaceCal = self::enlaceCalendario($servicio, $fechaIso, $hora);
        $botonCal  = self::botonCalendario($enlaceCal);

        $html = self::plantilla('Tu cita está confirmada', '
            <p class="text-main" style="margin:0 0 20px; font-size:14.5px; line-height:1.65; color:#3a3024;">
                Hola <strong class="text-main" style="color:#3a3024;">' . $nombre . '</strong>, tu cita está lista. Te esperamos en la barbería.
            </p>'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora)
                . ($precio !== '' ? self::linea('Precio', $precio) : '')
                . $botonCal . '
            ');

        $alt = "Hola $nombre, tu cita para $servicio el $fecha a las $hora está confirmada en Barbershop La H.";
        if ($precio !== '') $alt .= " Precio: $precio.";

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            'Cita confirmada · Barbershop La H', $html, $alt);
    }

    public static function enviarRecordatorio(Usuario $usuario, array $detalle, string $cuando = 'mañana'): bool
    {
        $nombre   = htmlspecialchars($usuario->getNombre());
        $servicio = htmlspecialchars($detalle['servicio'] ?? $detalle['nombre_servicio'] ?? '');
        $fecha    = htmlspecialchars($detalle['fecha_humana'] ?? $detalle['fecha_label'] ?? $detalle['fecha'] ?? '');
        $hora     = htmlspecialchars($detalle['hora'] ?? '');
        $esHoy    = $cuando === 'hoy';

        $fechaIso  = $detalle['fecha'] ?? '';
        $enlaceCal = self::enlaceCalendario($servicio, $fechaIso, $hora);
        $botonCal  = self::botonCalendario($enlaceCal);

        $titulo = $esHoy ? 'Tu cita es hoy' : 'Tu cita es mañana';
        $intro  = $esHoy
            ? "te recordamos que <strong class=\"text-main\" style=\"color:#3a3024;\">hoy</strong> tienes una cita en Barbershop La H."
            : "te recordamos que tienes una cita en Barbershop La H <strong class=\"text-main\" style=\"color:#3a3024;\">mañana</strong>.";
        $altIntro = $esHoy ? "te recordamos tu cita para hoy" : "te recordamos tu cita para mañana";

        $html = self::plantilla($titulo, '
            <p class="text-main" style="margin:0 0 20px; font-size:14.5px; line-height:1.65; color:#3a3024;">
                Hola <strong class="text-main" style="color:#3a3024;">' . $nombre . '</strong>, ' . $intro . '
            </p>'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora)
                . $botonCal . '
            <p class="text-muted" style="margin:20px 0 0; font-size:13px; line-height:1.5; color:#8c7a55;">
                Si no puedes asistir, cancela o modifica tu cita desde tu panel de cliente con antelación.
            </p>');

        $alt = "Hola $nombre, $altIntro para $servicio $fecha a las $hora en Barbershop La H.";

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            $titulo . ' · Barbershop La H', $html, $alt);
    }

    public static function enviarCancelacion(Usuario $usuario, array $detalle, string $motivo): bool
    {
        $nombre  = htmlspecialchars($usuario->getNombre());
        $servicio = htmlspecialchars($detalle['servicio'] ?? $detalle['nombre_servicio'] ?? '');
        $fecha   = htmlspecialchars($detalle['fecha_humana'] ?? $detalle['fecha_label'] ?? $detalle['fecha'] ?? '');
        $hora    = htmlspecialchars($detalle['hora'] ?? '');
        $motivo  = htmlspecialchars($motivo);

        $html = self::plantilla('Cita cancelada', '
            <p class="text-main" style="margin:0 0 20px; font-size:14.5px; line-height:1.65; color:#3a3024;">
                Hola <strong class="text-main" style="color:#3a3024;">' . $nombre . '</strong>, sentimos informarte que tu cita ha tenido que cancelarse.
            </p>'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora)
                . self::linea('Motivo', $motivo) .
            '');

        $alt = "Hola $nombre, tu cita para $servicio el $fecha a las $hora ha sido cancelada. Motivo: $motivo.";

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            'Cita cancelada · Barbershop La H', $html, $alt);
    }

    public static function enviarCambio(Usuario $usuario, array $detalleViejo, array $detalleNuevo, ?string $motivo = null): bool
    {
        $nombre       = htmlspecialchars($usuario->getNombre());
        $servicio     = htmlspecialchars($detalleViejo['servicio'] ?? $detalleViejo['nombre_servicio'] ?? '');
        $fechaVieja   = htmlspecialchars($detalleViejo['fecha_humana'] ?? $detalleViejo['fecha_label'] ?? $detalleViejo['fecha'] ?? '');
        $horaVieja    = htmlspecialchars($detalleViejo['hora'] ?? '');
        $fechaNueva   = htmlspecialchars($detalleNuevo['fecha_humana'] ?? $detalleNuevo['fecha_label'] ?? $detalleNuevo['fecha'] ?? '');
        $horaNueva    = htmlspecialchars($detalleNuevo['hora'] ?? '');
        $motivoHtml   = $motivo !== null && trim($motivo) !== ''
            ? self::linea('Motivo', htmlspecialchars(trim($motivo)))
            : '';
        $motivoAlt    = $motivo !== null && trim($motivo) !== ''
            ? " Motivo: " . trim($motivo) . "."
            : '';

        $html = self::plantilla('Tu cita ha sido reprogramada', '
            <p class="text-main" style="margin:0 0 20px; font-size:14.5px; line-height:1.65; color:#3a3024;">
                Hola <strong class="text-main" style="color:#3a3024;">' . $nombre . '</strong>, te informamos que tu cita ha sido reprogramada.
            </p>
            <div class="border-line" style="background:#f0e8d8; border-radius:6px; padding:14px 16px; margin-bottom:14px;">
                <p class="text-muted" style="margin:0 0 4px; font-size:10px; letter-spacing:1.2px; text-transform:uppercase; color:#8c7a55;">Anteriormente</p>
                <p class="text-main" style="margin:0; font-size:14px; color:#8c7a55; text-decoration:line-through;">' . $fechaVieja . ' a las ' . $horaVieja . '</p>
            </div>
            <div class="border-line" style="background:#d4af37/10; border:1px dashed #b3863a; border-radius:6px; padding:14px 16px;">
                <p class="text-muted" style="margin:0 0 4px; font-size:10px; letter-spacing:1.2px; text-transform:uppercase; color:#8c7a55;">Nueva fecha</p>
                <p class="text-main" style="margin:0; font-size:16px; font-weight:bold; color:#3a3024;">' . $fechaNueva . ' a las ' . $horaNueva . '</p>
            </div>'
                . self::linea('Servicio', $servicio)
                . $motivoHtml . '
            <p class="text-muted" style="margin:18px 0 0; font-size:12px; line-height:1.5; color:#8c7a55;">
                Si tienes cualquier duda, contáctanos. Disculpa las molestias.
            </p>');

        $alt = "Hola $nombre, tu cita para $servicio se ha reprogramado. "
             . "Anterior: $fechaVieja a las $horaVieja. Nueva: $fechaNueva a las $horaNueva."
             . $motivoAlt;

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            'Tu cita ha sido reprogramada · Barbershop La H', $html, $alt);
    }

    public static function enviarCompletada(Usuario $usuario, array $detalle, int $puntosViejos = 0): bool
    {
        $nombre   = htmlspecialchars($usuario->getNombre());
        $servicio = htmlspecialchars($detalle['servicio'] ?? $detalle['nombre_servicio'] ?? '');
        $fecha    = htmlspecialchars($detalle['fecha_humana'] ?? $detalle['fecha_label'] ?? $detalle['fecha'] ?? '');

        $extra = '';
        $alt   = "Hola $nombre, gracias por tu visita a Barbershop La H. Servicio: $servicio el $fecha.";

        $incluirFidelidad = $puntosViejos >= 9;
        if ($incluirFidelidad) {
            if ($puntosViejos >= 10) {
                $tituloFid = '🎉 ¡Corte gratis canjeado!';
                $descFid   = 'Has canjeado tu corte gratis, ' . $nombre . '. ¡Esperamos que lo disfrutaras!';
                $labelFid  = 'puntos · tarjeta reiniciada';
                $footerFid = 'Sigue acumulando puntos para tu próximo corte gratis.';
                $altFid    = ' Has canjeado tu corte gratis. Ahora tu tarjeta se ha reiniciado.';
            } else {
                $tituloFid = '🎉 ¡Te has ganado un corte gratis!';
                $descFid   = 'Has alcanzado 10 visitas, ' . $nombre . '. Tu próximo corte es por nuestra cuenta.';
                $labelFid  = 'puntos · corte gratis canjeable';
                $footerFid = 'Preséntate en recepción para canjearlo cuando quieras.';
                $altFid    = ' Además, has alcanzado 10 visitas y te has ganado un corte gratis.';
            }
            $extra = '
            <div class="border-line" style="margin-top:24px; padding-top:20px; border-top:1px solid #ddd2b4;">
                <p class="text-title" style="margin:0 0 10px; font-size:16px; font-weight:700; color:#a9762c; text-align:center;">
                    ' . $tituloFid . '
                </p>
                <p class="text-main" style="margin:0 0 14px; font-size:13px; line-height:1.5; text-align:center; color:#3a3024;">
                    ' . $descFid . '
                </p>
                <div class="bg-body border-line" style="background-color:#e9e4d8; border:1px dashed #b3863a; border-radius:8px; padding:16px; text-align:center;">
                    <span class="text-title" style="font-size:28px; font-weight:800; color:#a9762c;">' . $usuario->getPuntosFidelidad() . '</span>
                    <span class="text-muted" style="display:block; font-size:12px; margin-top:4px; color:#8c7a55;">' . $labelFid . '</span>
                </div>
                <p class="text-muted" style="margin:12px 0 0; font-size:12px; text-align:center; color:#8c7a55;">
                    ' . $footerFid . '
                </p>
            </div>';
            $alt .= $altFid;
        }

        $html = self::plantilla('Gracias por tu visita', '
            <p class="text-main" style="margin:0 0 20px; font-size:14.5px; line-height:1.65; color:#3a3024;">
                Hola <strong class="text-main" style="color:#3a3024;">' . $nombre . '</strong>, gracias por venir. Esperamos que el resultado te haya gustado tanto como a nosotros hacerlo.
            </p>'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
            . $extra);

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            'Gracias por tu visita · Barbershop La H', $html, $alt);
    }

    public static function enviarFidelidad(Usuario $usuario, int $puntosViejos): bool
    {
        $nombre  = htmlspecialchars($usuario->getNombre());
        $tieneGratis = $puntosViejos >= 10;
        $puntosVisibles = $tieneGratis ? 1 : ($puntosViejos + 1);

        if ($puntosViejos >= 10) {
            $titulo = '¡Corte gratis canjeado!';
            $texto  = "Hola <strong class=\"text-main\" style=\"color:#3a3024;\">$nombre</strong>, acabas de canjear tu corte gratis. ¡Esperamos que lo disfrutaras!";
            $footer = 'Ahora tu tarjeta de fidelidad se ha reiniciado. Sigue acumulando puntos para tu próximo corte gratis.';
        } else {
            $titulo = '¡Te has ganado un corte gratis!';
            $texto  = "Hola <strong class=\"text-main\" style=\"color:#3a3024;\">$nombre</strong>, has alcanzado 10 visitas. ¡Felicidades!";
            $footer = 'Tu próximo corte es por nuestra cuenta. Preséntate en recepción para canjearlo cuando quieras.';
        }

        $html = self::plantilla($titulo, '
            <p class="text-main" style="margin:0 0 20px; font-size:14.5px; line-height:1.65; color:#3a3024;">
                ' . $texto . '
            </p>
            <div class="bg-body border-line" style="background-color:#e9e4d8; border:1px dashed #b3863a; border-radius:8px; padding:16px; text-align:center;">
                <span class="text-title" style="font-size:28px; font-weight:800; color:#a9762c;">' . $puntosVisibles . '</span>
                <span class="text-muted" style="display:block; font-size:12px; margin-top:4px; color:#8c7a55;">puntos actuales</span>
            </div>
            <p class="text-muted" style="margin:16px 0 0; font-size:13px; line-height:1.5; color:#8c7a55;">' . $footer . '</p>');

        $alt   = $tieneGratis
            ? "Hola $nombre, has canjeado tu corte gratis. Ahora tienes 1 punto."
            : "Hola $nombre, has alcanzado 10 visitas. ¡Tienes un corte gratis!";

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            $titulo . ' · Barbershop La H', $html, $alt);
    }

    public static function enviarNoPresentado(Usuario $usuario, array $detalle): bool
    {
        $nombre  = htmlspecialchars($usuario->getNombre());
        $servicio = htmlspecialchars($detalle['servicio'] ?? $detalle['nombre_servicio'] ?? '');
        $fecha   = htmlspecialchars($detalle['fecha_humana'] ?? $detalle['fecha_label'] ?? $detalle['fecha'] ?? '');
        $hora    = htmlspecialchars($detalle['hora'] ?? '');

        $html = self::plantilla('No pudimos atenderte', '
            <p class="text-main" style="margin:0 0 20px; font-size:14.5px; line-height:1.65; color:#3a3024;">
                Hola <strong class="text-main" style="color:#3a3024;">' . $nombre . '</strong>, te esperábamos el <strong class="text-main" style="color:#3a3024;">' . $fecha . '</strong> a las <strong class="text-main" style="color:#3a3024;">' . $hora . '</strong> pero no pudimos atenderte.
            </p>
            <p class="text-muted" style="margin:0 0 20px; font-size:13px; line-height:1.5; color:#8c7a55;">
                Si te surgió algún imprevisto, no te preocupes. Puedes reservar de nuevo desde tu panel de cliente cuando quieras.
            </p>'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora) .
            '');

        $alt = "Hola $nombre, no pudimos atenderte el $fecha a las $hora. Contáctanos si quieres reagendar.";

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            'Aviso de cita no atendida · Barbershop La H', $html, $alt);
    }

    public static function enviarRecuperarPassword(string $email, string $nombre, string $enlace): bool
    {
        $html = self::plantilla('Restablece tu contraseña', '
            <p class="text-main" style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#3a3024;">
                Hola <strong class="text-main" style="color:#3a3024;">' . htmlspecialchars($nombre) . '</strong>, recibiste una solicitud para restablecer tu contraseña.
            </p>
            <p class="text-main" style="margin:0 0 20px; font-size:13px; line-height:1.5; color:#3a3024;">
                Haz clic en el botón de abajo para crear una nueva. El enlace expira en <strong class="text-main" style="color:#3a3024;">1 hora</strong>.
            </p>
            <div style="text-align:center; margin:24px 0;">
                <a href="' . $enlace . '"
                   style="display:inline-block; background:linear-gradient(135deg,#d4af37,#b8962f); color:#0d0d0d; font-size:13px; font-weight:700; padding:12px 32px; border-radius:8px; text-decoration:none; letter-spacing:0.5px;">
                    Restablecer contraseña
                </a>
            </div>
            <p class="text-muted" style="margin:20px 0 0; font-size:11px; line-height:1.5; color:#8c7a55;">
                Si no solicitaste este cambio, ignora este mensaje. Tu contraseña actual sigue siendo segura.
            </p>');

        $alt = "Hola $nombre, recibiste una solicitud para restablecer tu contraseña en Barbershop La H. Enlace: $enlace";

        return self::enviar($email, $nombre,
            'Restablece tu contraseña · Barbershop La H', $html, $alt);
    }
}
