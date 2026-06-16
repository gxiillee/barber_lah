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
        return '
        <div style="max-width:560px; margin:0 auto; font-family:Helvetica,Arial,sans-serif; background:#0d0d0d; border:1px solid rgba(212,175,55,0.25); border-radius:12px; overflow:hidden;">
            <div style="background:#0d0d0d; padding:26px 32px 18px; text-align:center; border-bottom:1px solid rgba(212,175,55,0.12);">
                <h1 style="margin:0; color:#d4af37; font-size:18px; font-weight:700; letter-spacing:3px; text-transform:uppercase;">Barbershop La H</h1>
                <p style="margin:3px 0 0; color:rgba(255,255,255,0.2); font-size:10px; letter-spacing:2px;">BARBERÍA · ZARAGOZA</p>
            </div>
            <div style="padding:28px 32px 20px; color:#f5f0e8;">
                <h2 style="margin:0 0 16px; font-size:16px; color:#d4af37; font-weight:600; letter-spacing:0.3px;">' . $titulo . '</h2>
                ' . $contenido . '
            </div>
            <div style="border-top:1px solid rgba(212,175,55,0.1); padding:18px 32px; text-align:center;">
                <p style="margin:0 0 4px; color:rgba(255,255,255,0.25); font-size:12px; font-style:italic;">Gracias por confiar en nuestro trabajo.</p>
                <p style="margin:0; color:rgba(212,175,55,0.5); font-size:12px; font-weight:600;">— Hassan</p>
            </div>
        </div>';
    }

    private static function linea(string $label, string $valor): string
    {
        return '<tr><td style="padding:5px 12px 5px 0; color:rgba(255,255,255,0.35); font-size:12px; white-space:nowrap; width:1px;"><span style="color:rgba(212,175,55,0.3); margin-right:6px;">◆</span>' . $label . '</td>'
              . '<td style="padding:5px 0; color:#f5f0e8; font-size:13px; font-weight:600;">' . $valor . '</td></tr>';
    }

    public static function enviarConfirmacion(Usuario $usuario, array $detalle): bool
    {
        $nombre  = htmlspecialchars($usuario->getNombre());
        $servicio = htmlspecialchars($detalle['servicio'] ?? $detalle['nombre_servicio'] ?? '');
        $fecha   = htmlspecialchars($detalle['fecha_humana'] ?? $detalle['fecha_label'] ?? $detalle['fecha'] ?? '');
        $hora    = htmlspecialchars($detalle['hora'] ?? '');
        $precio  = htmlspecialchars((string)($detalle['precio'] ?? ''));
        $idRes   = htmlspecialchars((string)($detalle['id_reserva'] ?? $detalle['id'] ?? ''));

        $html = self::plantilla('Tu cita está confirmada', '
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.6); font-size:14px; line-height:1.6;">
                Hola <strong style="color:#f5f0e8;">' . $nombre . '</strong>, tu cita está lista. Te esperamos en la barbería.
            </p>
            <table style="width:100%;">'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora)
                . ($precio !== '' ? self::linea('Precio', $precio) : '')
                . ($idRes !== '' ? self::linea('Reserva #', $idRes) : '') .
            '</table>');

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

        $titulo = $esHoy ? 'Tu cita es hoy' : 'Tu cita es mañana';
        $intro  = $esHoy
            ? "te recordamos que <strong style=\"color:#f5f0e8;\">hoy</strong> tienes una cita en Barbershop La H."
            : "te recordamos que tienes una cita en Barbershop La H <strong style=\"color:#f5f0e8;\">mañana</strong>.";
        $altIntro = $esHoy ? "te recordamos tu cita para hoy" : "te recordamos tu cita para mañana";

        $html = self::plantilla($titulo, '
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.6); font-size:14px; line-height:1.6;">
                Hola <strong style="color:#f5f0e8;">' . $nombre . '</strong>, ' . $intro . '
            </p>
            <table style="width:100%;">'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora) .
            '</table>
            <p style="margin:20px 0 0; color:rgba(255,255,255,0.4); font-size:13px;">
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
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.6); font-size:14px; line-height:1.6;">
                Hola <strong style="color:#f5f0e8;">' . $nombre . '</strong>, sentimos informarte que tu cita ha tenido que cancelarse.
            </p>
            <table style="width:100%;">'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora)
                . self::linea('Motivo', $motivo) .
            '</table>');

        $alt = "Hola $nombre, tu cita para $servicio el $fecha a las $hora ha sido cancelada. Motivo: $motivo.";

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            'Cita cancelada · Barbershop La H', $html, $alt);
    }

    public static function enviarCompletada(Usuario $usuario, array $detalle, bool $incluirFidelidad = false): bool
    {
        $nombre   = htmlspecialchars($usuario->getNombre());
        $servicio = htmlspecialchars($detalle['servicio'] ?? $detalle['nombre_servicio'] ?? '');
        $fecha    = htmlspecialchars($detalle['fecha_humana'] ?? $detalle['fecha_label'] ?? $detalle['fecha'] ?? '');

        $extra = '';
        $alt   = "Hola $nombre, gracias por tu visita a Barbershop La H. Servicio: $servicio el $fecha.";

        if ($incluirFidelidad) {
            $puntos = $usuario->getPuntosFidelidad();
            $puntosMostrar = $puntos >= 10 ? 10 : $puntos;
            $extra = '
            <div style="margin-top:24px; padding-top:20px; border-top:1px solid rgba(212,175,55,0.2);">
                <p style="margin:0 0 10px; font-size:16px; font-weight:700; color:#d4af37; text-align:center;">
                    🎉 ¡Te has ganado un corte gratis!
                </p>
                <p style="margin:0 0 14px; color:rgba(255,255,255,0.5); font-size:13px; line-height:1.5; text-align:center;">
                    Has alcanzado 10 visitas, ' . $nombre . '. Tu próximo corte es por nuestra cuenta.
                </p>
                <div style="background:rgba(212,175,55,0.1); border:1px solid rgba(212,175,55,0.25); border-radius:8px; padding:16px; text-align:center;">
                    <span style="font-size:28px; font-weight:800; color:#d4af37;">' . $puntosMostrar . '</span>
                    <span style="display:block; color:rgba(255,255,255,0.4); font-size:12px; margin-top:4px;">puntos · corte gratis canjeable</span>
                </div>
                <p style="margin:12px 0 0; color:rgba(255,255,255,0.3); font-size:12px; text-align:center;">
                    Preséntate en recepción para canjearlo cuando quieras.
                </p>
            </div>';
            $alt .= " Además, has alcanzado 10 visitas y te has ganado un corte gratis.";
        }

        $html = self::plantilla('Gracias por tu visita', '
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.6); font-size:14px; line-height:1.6;">
                Hola <strong style="color:#f5f0e8;">' . $nombre . '</strong>, gracias por venir. Esperamos que el resultado te haya gustado tanto como a nosotros hacerlo.
            </p>
            <table style="width:100%;">'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha) .
            '</table>'
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
            $texto  = "Hola <strong style=\"color:#f5f0e8;\">$nombre</strong>, acabas de canjear tu corte gratis. ¡Esperamos que lo disfrutaras!";
            $footer = 'Ahora tu tarjeta de fidelidad se ha reiniciado. Sigue acumulando puntos para tu próximo corte gratis.';
        } else {
            $titulo = '¡Te has ganado un corte gratis!';
            $texto  = "Hola <strong style=\"color:#f5f0e8;\">$nombre</strong>, has alcanzado 10 visitas. ¡Felicidades!";
            $footer = 'Tu próximo corte es por nuestra cuenta. Preséntate en recepción para canjearlo cuando quieras.';
        }

        $html = self::plantilla($titulo, '
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.6); font-size:14px; line-height:1.6;">
                ' . $texto . '
            </p>
            <div style="background:rgba(212,175,55,0.1); border:1px solid rgba(212,175,55,0.25); border-radius:8px; padding:16px; text-align:center;">
                <span style="font-size:28px; font-weight:800; color:#d4af37;">' . $puntosVisibles . '</span>
                <span style="display:block; color:rgba(255,255,255,0.4); font-size:12px; margin-top:4px;">puntos actuales</span>
            </div>
            <p style="margin:16px 0 0; color:rgba(255,255,255,0.4); font-size:13px;">' . $footer . '</p>');

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
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.6); font-size:14px; line-height:1.6;">
                Hola <strong style="color:#f5f0e8;">' . $nombre . '</strong>, te esperábamos el <strong>' . $fecha . '</strong> a las <strong>' . $hora . '</strong> pero no pudimos atenderte.
            </p>
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.5); font-size:13px; line-height:1.5;">
                Si te surgió algún imprevisto, no te preocupes. Puedes reservar de nuevo desde tu panel de cliente cuando quieras.
            </p>
            <table style="width:100%;">'
                . self::linea('Servicio', $servicio)
                . self::linea('Día', $fecha)
                . self::linea('Hora', $hora) .
            '</table>');

        $alt = "Hola $nombre, no pudimos atenderte el $fecha a las $hora. Contáctanos si quieres reagendar.";

        return self::enviar($usuario->getEmail(), $usuario->getNombre(),
            'Aviso de cita no atendida · Barbershop La H', $html, $alt);
    }

    public static function enviarRecuperarPassword(string $email, string $nombre, string $enlace): bool
    {
        $html = self::plantilla('Restablece tu contraseña', '
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.6); font-size:14px; line-height:1.6;">
                Hola <strong style="color:#f5f0e8;">' . htmlspecialchars($nombre) . '</strong>, recibiste una solicitud para restablecer tu contraseña.
            </p>
            <p style="margin:0 0 20px; color:rgba(255,255,255,0.5); font-size:13px; line-height:1.5;">
                Haz clic en el botón de abajo para crear una nueva. El enlace expira en <strong>1 hora</strong>.
            </p>
            <div style="text-align:center; margin:24px 0;">
                <a href="' . $enlace . '"
                   style="display:inline-block; background:linear-gradient(135deg,#d4af37,#b8962f); color:#0d0d0d; font-size:13px; font-weight:700; padding:12px 32px; border-radius:8px; text-decoration:none; letter-spacing:0.5px;">
                    Restablecer contraseña
                </a>
            </div>
            <p style="margin:20px 0 0; color:rgba(255,255,255,0.3); font-size:11px; line-height:1.5;">
                Si no solicitaste este cambio, ignora este mensaje. Tu contraseña actual sigue siendo segura.
            </p>');

        $alt = "Hola $nombre, recibiste una solicitud para restablecer tu contraseña en Barbershop La H. Enlace: $enlace";

        return self::enviar($email, $nombre,
            'Restablece tu contraseña · Barbershop La H', $html, $alt);
    }
}
