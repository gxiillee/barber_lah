<?php
require_once __DIR__ . '/Usuario.php';

class NotificadorReserva {
    public static function enviarConfirmacion(Usuario $usuario, array $detalle): bool {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            $smtpHost = getenv('SMTP_HOST') ?: '';
            if ($smtpHost !== '') {
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
                $mail->SMTPAuth = (bool)(getenv('SMTP_USER') ?: false);
                if ($mail->SMTPAuth) {
                    $mail->Username = getenv('SMTP_USER') ?: '';
                    $mail->Password = getenv('SMTP_PASS') ?: '';
                }
                $mail->SMTPSecure = getenv('SMTP_SECURE') ?: \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->isMail();
            }

            $from = getenv('MAIL_FROM') ?: 'no-reply@barberlah.local';
            $fromName = getenv('MAIL_FROM_NAME') ?: 'Barbershop La H';

            $mail->setFrom($from, $fromName);
            $mail->addAddress($usuario->getEmail(), $usuario->getNombre());
            $mail->isHTML(true);
            $mail->Subject = 'Tu cita en Barbershop La H esta confirmada';

            $servicio = htmlspecialchars((string)$detalle['servicio_nombre'], ENT_QUOTES, 'UTF-8');
            $fecha = htmlspecialchars((string)$detalle['fecha_label'], ENT_QUOTES, 'UTF-8');
            $hora = htmlspecialchars((string)$detalle['hora'], ENT_QUOTES, 'UTF-8');
            $precio = htmlspecialchars((string)$detalle['precio_formateado'], ENT_QUOTES, 'UTF-8');

            $mail->Body = "
                <h1>Tu cita esta confirmada</h1>
                <p><strong>Servicio:</strong> {$servicio}</p>
                <p><strong>Dia y hora:</strong> {$fecha} a las {$hora}</p>
                <p><strong>Precio:</strong> {$precio}</p>
                <p>Si necesitas cambiarla o cancelarla, contacta con Barbershop La H.</p>
            ";

            $mail->AltBody = "Tu cita esta confirmada: {$servicio}, {$fecha} a las {$hora}. Precio: {$precio}.";

            return $mail->send();
        } catch (Throwable) {
            return false;
        }
    }
}
