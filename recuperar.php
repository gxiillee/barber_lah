<?php
declare(strict_types=1);

require_once __DIR__ . '/clases/BD.php';
require_once __DIR__ . '/clases/helpers.php';
require_once __DIR__ . '/clases/Csrf.php';
require_once __DIR__ . '/clases/NotificadorReserva.php';

iniciarSesionSegura();

$paso = 'formulario'; // formulario | exito
$email = '';
$error = '';
$enlace = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validarToken('csrf_recuperar', $_POST['csrf_token'] ?? null)) {
        $error = 'Sesión caducada. Recarga la página.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Introduce un email válido.';
        } else {
            try {
                $conexion = BD::obtenerConexion();
                $stmt = $conexion->prepare("SELECT id, nombre FROM usuarios WHERE email = :email AND activo = true");
                $stmt->execute([':email' => $email]);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    $token = bin2hex(random_bytes(32));
                    $expiraEn = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $stmt = $conexion->prepare(
                        "INSERT INTO reset_tokens (email, token, expira_en) VALUES (:email, :token, :expira_en)"
                    );
                    $stmt->execute([
                        ':email'     => $email,
                        ':token'     => $token,
                        ':expira_en' => $expiraEn,
                    ]);
                    $enlace = rtrim($_ENV['APP_URL'], '/') . '/restablecer.php?token=' . $token;

                    NotificadorReserva::enviarRecuperarPassword(
                        $email,
                        $usuario['nombre'],
                        $enlace
                    );
                }

                $paso = 'exito';
            } catch (Exception $e) {
                $error = 'Error del servidor. Inténtalo de nuevo.';
                error_log("recuperar.php: " . $e->getMessage());
            }
        }
    }
}

$csrfToken = Csrf::generarToken('csrf_recuperar');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña · Barbershop La H</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="public/assets/css/estilos.css">
</head>
<body class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[var(--obsidian)] p-5 font-[var(--font-montserrat)] text-[#f5f0e8]">
<div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_50%,rgba(212,175,55,0.04)_0%,transparent_70%)]"></div>
<div class="pointer-events-none fixed inset-0 z-0 opacity-55 [background-image:url('data:image/svg+xml,%3Csvg_viewBox=%270_0_200_200%27_xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cfilter_id=%27n%27%3E%3CfeTurbulence_type=%27fractalNoise%27_baseFrequency=%270.85%27_numOctaves=%274%27_stitchTiles=%27stitch%27/%3E%3C/filter%3E%3Crect_width=%27100%25%27_height=%27100%25%27_filter=%27url(%23n)%27_opacity=%270.04%27/%3E%3C/svg%3E')]"></div>

<a href="login.php" class="fixed left-6 top-6 z-40 text-[10px] font-bold uppercase tracking-[0.16em] text-white/40 no-underline transition hover:-translate-x-0.5 hover:text-[var(--gold)] max-sm:left-5 max-sm:top-5">
    ← Volver
</a>

<main class="relative z-10 w-full max-w-[440px] animate-fade-in">
    <div class="rounded-[14px] border border-[#1e1e1e] bg-[var(--obsidian)] p-8 sm:p-10 shadow-[0_0_0_0.5px_rgba(212,175,55,0.08),0_40px_80px_rgba(0,0,0,0.8)]">

        <!-- Branding -->
        <div class="mb-7 text-center">
            <div class="font-[var(--font-playfair)] text-xs uppercase tracking-[0.22em] text-[var(--gold)]">✦ La H ✦</div>
            <img src="public/assets/img/logo.jpg" alt="Barbershop La H" class="mx-auto mt-4 h-[68px] w-[68px] rounded-full border border-[var(--gold)]/25 object-cover shadow-[0_0_22px_rgba(212,175,55,0.12)]">
        </div>

        <?php if ($paso === 'formulario'): ?>

            <h1 class="mb-1.5 text-center font-[var(--font-playfair)] text-[26px] font-bold text-[#f5f0e8]">Recuperar contraseña</h1>
            <p class="mb-7 text-center text-[11px] tracking-[0.06em] text-white/40">Te enviaremos un enlace para restablecerla</p>

            <?php if ($error !== ''): ?>
                <div class="mb-4 flex items-center gap-2 rounded-md border border-red-500/35 bg-red-500/10 px-3.5 py-2.5 text-[11px] leading-snug text-[#ff6b6b]" role="alert">
                    <i class="bi bi-exclamation-circle shrink-0 text-[13px]"></i>
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="flex flex-col gap-5">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <div>
                    <label for="email" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-[0.12em] text-white/35">Email</label>
                    <input type="email" name="email" id="email" value="<?= h($email) ?>"
                           class="w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] text-[13px] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]"
                           placeholder="tucorreo@ejemplo.com" autocomplete="email" required>
                </div>
                <button type="submit"
                        class="w-full rounded-md bg-[var(--gold)] px-3 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)] transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] hover:shadow-[0_6px_20px_rgba(212,175,55,0.25)]">
                    <i class="bi bi-envelope-paper"></i> Enviar enlace
                </button>
            </form>

            <p class="mt-6 text-center text-[10px] leading-relaxed text-white/25">
                ¿Recordaste tu contraseña?
                <a href="login.php" class="text-[var(--gold)]/70 no-underline transition hover:text-[var(--gold)]">Inicia sesión</a>
            </p>

        <?php else: /* paso === exito */ ?>

            <div class="flex flex-col items-center text-center">
                <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--gold-dim)]">
                    <i class="bi bi-envelope-check text-[28px] text-[var(--gold)]"></i>
                </div>
                <h1 class="mb-2 font-[var(--font-playfair)] text-[24px] font-bold text-[#f5f0e8]">Revisa tu correo</h1>
                <p class="mb-6 text-[12px] leading-relaxed text-white/40">
                    Si existe una cuenta con <strong class="text-white/60"><?= h($email) ?></strong>,
                    recibirás un enlace para restablecer tu contraseña.
                </p>

                <p class="text-[10px] text-white/25 leading-relaxed">
                    El enlace expira en <strong class="text-white/40">1 hora</strong>.
                    Si no recibes nada, revisa tu carpeta de spam o
                    <a href="recuperar.php" class="text-[var(--gold)]/70 no-underline transition hover:text-[var(--gold)]">inténtalo de nuevo</a>.
                </p>
                <a href="login.php" class="mt-6 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--gold)]/70 no-underline transition hover:text-[var(--gold)]">
                    ← Volver al inicio de sesión
                </a>
            </div>

        <?php endif; ?>

    </div>
</main>

<script>
document.querySelectorAll('[role="alert"]').forEach(el => {
    el.style.animation = 'bh-shake 0.42s ease both';
});
</script>
</body>
</html>
