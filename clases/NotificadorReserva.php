<?php
declare(strict_types=1);

require_once __DIR__ . '/Usuario.php';

// Importante: Asegúrate de que PHPMailer esté cargado vía Composer o manualmente
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificadorReserva {
    /**
     * Envía la confirmación por los canales activos (Email actualmente)
     * @param Usuario $usuario Objeto usuario con email y nombre
     * @param array $detalle Los datos de $_SESSION['reserva_pendiente']
     */
    public static function enviarConfirmacion(Usuario $usuario, array $detalle): bool {

        // 1. Enviar Email
        $emailEnviado = self::enviarEmail($usuario, $detalle);

        // 2. TODO: Enviar WhatsApp (Futuro)
        // self::enviarWhatsApp($usuario->getTelefono(), $detalle);

        return $emailEnviado;
    }

    private static function enviarEmail(Usuario $usuario, array $detalle): bool {
        if (!class_exists(PHPMailer::class)) {
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            // Configuración del servidor (Asegúrate de tener estas variables en tu .env o hardcodeadas)
            $mail->isSMTP();
            $mail->Host       = 'tu_servidor_smtp'; // Ej: smtp.gmail.com
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tu_correo@gmail.com';
            $mail->Password   = 'tu_contraseña_aplicacion';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Remitente y Destinatario
            $mail->setFrom('no-reply@barbershoplah.com', 'Barbershop La H');
            $mail->addAddress($usuario->getEmail(), $usuario->getNombre());

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Confirmación de tu cita - Barbershop La H';

            // Adaptamos los nombres a lo que guardamos en reserva.php
            $servicio = htmlspecialchars((string)$detalle['nombre_servicio']);
            $fecha    = date('d/m/Y', strtotime($detalle['fecha'])); // Formateamos fecha
            $hora     = htmlspecialchars((string)$detalle['hora']);
            $precio   = number_format((float)$detalle['precio'], 2, ',', '.') . '€';

            $mail->Body = "
                <div style='font-family: sans-serif; border: 1px solid #d4af37; padding: 20px; border-radius: 10px;'>
                    <h1 style='color: #000;'>¡Hola {$usuario->getNombre()}!</h1>
                    <p>Tu cita ha sido reservada con éxito en <strong>Barbershop La H</strong>.</p>
                    <hr style='border: 0; border-top: 1px solid #eee;'>
                    <p><strong>Servicio:</strong> {$servicio}</p>
                    <p><strong>Día:</strong> {$fecha}</p>
                    <p><strong>Hora:</strong> {$hora}</p>
                    <p><strong>Precio:</strong> {$precio}</p>
                    <br>
                    <p style='font-size: 12px; color: #666;'>Si necesitas cancelar o modificar tu cita, por favor avísanos con antelación.</p>
                </div>
            ";

            $mail->AltBody = "Tu cita para {$servicio} el día {$fecha} a las {$hora} está confirmada.";

            return $mail->send();
        } catch (Exception $e) {
            // Log de error si fuera necesario: error_log($e->getMessage());
            return false;
        }
    }

    // Preparado para el futuro
    /*
    private static function enviarWhatsApp(string $telefono, array $detalle) {
        // Lógica de API de WhatsApp (Twilio, UltraMsg, etc.)
    }
    */
}