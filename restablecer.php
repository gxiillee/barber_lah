<?php
declare(strict_types=1);

require_once __DIR__ . '/clases/BD.php';
require_once __DIR__ . '/clases/helpers.php';
require_once __DIR__ . '/clases/Csrf.php';

iniciarSesionSegura();

$error = '';
$exito = false;
$tokenValido = false;
$emailToken = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// ── Validar token (GET inicial + POST re-validación) ──
try {
    $conexion = BD::obtenerConexion();
    $stmt = $conexion->prepare(
        "SELECT email FROM reset_tokens WHERE token = :token AND usado = false AND expira_en > NOW()"
    );
    $stmt->execute([':token' => $token]);
    $fila = $stmt->fetch();

    if ($fila) {
        $tokenValido = true;
        $emailToken = $fila['email'];
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $error = 'Este enlace ha expirado o ya ha sido utilizado. Solicita uno nuevo.';
    }
} catch (Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $error = 'Error del servidor. Inténtalo de nuevo.';
    }
    error_log("restablecer.php validación: " . $e->getMessage());
}

// ── Procesar formulario ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    if (!Csrf::validarToken('csrf_restablecer', $_POST['csrf_token'] ?? null)) {
        $error = 'Sesión caducada. Recarga la página.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmar = $_POST['confirmar'] ?? '';

        if (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $confirmar) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            try {
                $conexion = BD::obtenerConexion();
                $stmt = $conexion->prepare("SELECT id, rol FROM usuarios WHERE email = :email AND activo = true");
                $stmt->execute([':email' => $emailToken]);
                $usuario = $stmt->fetch();

                if (!$usuario) {
                    $error = 'Usuario no encontrado.';
                } else {
                    // Admin: verificar que no repita últimas 3 contraseñas
                    if ($usuario['rol'] === 'admin') {
                        $stmt = $conexion->prepare(
                            "SELECT password_hash FROM password_history WHERE usuario_id = :id ORDER BY created_at DESC LIMIT 3"
                        );
                        $stmt->execute([':id' => $usuario['id']]);
                        $historial = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        foreach ($historial as $hash) {
                            if (password_verify($password, $hash)) {
                                $error = 'Esta contraseña ya la has usado recientemente. Elige una diferente.';
                                break;
                            }
                        }
                    }

                    if (empty($error)) {
                        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                        $stmt = $conexion->prepare(
                            "UPDATE usuarios SET password = :password, password_updated_at = NOW() WHERE id = :id"
                        );
                        $stmt->execute([':password' => $hash, ':id' => $usuario['id']]);

                        $stmt = $conexion->prepare("UPDATE reset_tokens SET usado = true WHERE token = :token");
                        $stmt->execute([':token' => $token]);

                        // Admin: guardar en historial y mantener solo últimas 3
                        if ($usuario['rol'] === 'admin') {
                            $stmt = $conexion->prepare(
                                "INSERT INTO password_history (usuario_id, password_hash) VALUES (:id, :hash)"
                            );
                            $stmt->execute([':id' => $usuario['id'], ':hash' => $hash]);

                            $stmt = $conexion->prepare(
                                "DELETE FROM password_history WHERE usuario_id = :id AND id NOT IN (
                                    SELECT id FROM password_history WHERE usuario_id = :id2 ORDER BY created_at DESC LIMIT 3
                                )"
                            );
                            $stmt->execute([':id' => $usuario['id'], ':id2' => $usuario['id']]);
                        }

                        $exito = true;
                    }
                }
            } catch (Exception $e) {
                $error = 'Error del servidor. Inténtalo de nuevo.';
                error_log("restablecer.php proceso: " . $e->getMessage());
            }
        }
    }
}

$csrfToken = Csrf::generarToken('csrf_restablecer');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña · Barbershop La H</title>
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

        <?php if ($exito): ?>

            <!-- ═══ ÉXITO ═══ -->
            <div class="flex flex-col items-center text-center">
                <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[rgba(34,197,94,0.12)]">
                    <i class="bi bi-check-circle text-[28px] text-[#22c55e]"></i>
                </div>
                <h1 class="mb-2 font-[var(--font-playfair)] text-[24px] font-bold text-[#f5f0e8]">Contraseña actualizada</h1>
                <p class="mb-7 text-[12px] leading-relaxed text-white/40">
                    Tu contraseña se ha restablecido correctamente. Ya puedes iniciar sesión con tu nueva contraseña.
                </p>
                <a href="login.php"
                   class="inline-block rounded-md bg-[var(--gold)] px-8 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)] no-underline transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] hover:shadow-[0_6px_20px_rgba(212,175,55,0.25)]">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                </a>
            </div>

        <?php elseif (!$tokenValido && empty($error)): ?>

            <!-- ═══ TOKEN INVÁLIDO (sin error específico) ═══ -->
            <div class="flex flex-col items-center text-center">
                <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-red-500/10">
                    <i class="bi bi-exclamation-triangle text-[28px] text-[#ef4444]"></i>
                </div>
                <h1 class="mb-2 font-[var(--font-playfair)] text-[24px] font-bold text-[#f5f0e8]">Enlace inválido</h1>
                <p class="mb-7 text-[12px] leading-relaxed text-white/40">
                    El enlace no es válido o ya ha sido utilizado.
                </p>
                <a href="recuperar.php"
                   class="inline-block rounded-md bg-[var(--gold)] px-8 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)] no-underline transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] hover:shadow-[0_6px_20px_rgba(212,175,55,0.25)]">
                    Solicitar nuevo enlace
                </a>
            </div>

        <?php elseif ($error !== '' && !$tokenValido): ?>

            <!-- ═══ TOKEN INVÁLIDO CON ERROR ═══ -->
            <div class="flex flex-col items-center text-center">
                <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-red-500/10">
                    <i class="bi bi-exclamation-triangle text-[28px] text-[#ef4444]"></i>
                </div>
                <h1 class="mb-2 font-[var(--font-playfair)] text-[24px] font-bold text-[#f5f0e8]">Enlace inválido</h1>
                <p class="mb-7 text-[12px] leading-relaxed text-white/40"><?= h($error) ?></p>
                <a href="recuperar.php"
                   class="inline-block rounded-md bg-[var(--gold)] px-8 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)] no-underline transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] hover:shadow-[0_6px_20px_rgba(212,175,55,0.25)]">
                    Solicitar nuevo enlace
                </a>
            </div>

        <?php else: /* token válido — mostrar formulario */ ?>

            <h1 class="mb-1.5 text-center font-[var(--font-playfair)] text-[26px] font-bold text-[#f5f0e8]">Nueva contraseña</h1>
            <p class="mb-7 text-center text-[11px] tracking-[0.06em] text-white/40">Elige una contraseña segura</p>

            <?php if ($error !== ''): ?>
                <div class="mb-4 flex items-center gap-2 rounded-md border border-red-500/35 bg-red-500/10 px-3.5 py-2.5 text-[11px] leading-snug text-[#ff6b6b]" role="alert">
                    <i class="bi bi-exclamation-circle shrink-0 text-[13px]"></i>
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="flex flex-col gap-5">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <div>
                    <label for="password" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-[0.12em] text-white/35">Nueva contraseña</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required
                               class="w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] pr-10 text-[13px] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]"
                               placeholder="Mín. 6 caracteres" autocomplete="new-password">
                        <button type="button" class="toggle-pwd absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-0 text-[15px] text-[#3a3a3a] transition hover:text-[var(--gold)]" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="mt-2.5 rounded-full overflow-hidden" style="height:5px; background:rgba(255,255,255,0.04);">
                        <div id="pwdStrengthBar" class="h-full rounded-full transition-all duration-300" style="width:0%; background:transparent;"></div>
                    </div>
                    <p id="pwdStrengthLabel" style="font-size:0.65rem; color:var(--tx-d); margin-top:5px; min-height:18px;"></p>
                </div>

                <div>
                    <label for="confirmar" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-[0.12em] text-white/35">Confirmar contraseña</label>
                    <div class="relative">
                        <input type="password" name="confirmar" id="confirmar" required
                               class="w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] pr-10 text-[13px] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]"
                               placeholder="Repite la contraseña" autocomplete="new-password">
                        <button type="button" class="toggle-pwd absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-0 text-[15px] text-[#3a3a3a] transition hover:text-[var(--gold)]" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <p id="pwdMatchIndicator" style="font-size:0.65rem; margin-top:5px; min-height:18px;"></p>
                </div>

                <button type="submit"
                        class="w-full rounded-md bg-[var(--gold)] px-3 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)] transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] hover:shadow-[0_6px_20px_rgba(212,175,55,0.25)]">
                    <i class="bi bi-check-lg"></i> Restablecer contraseña
                </button>
            </form>

        <?php endif; ?>

    </div>
</main>

<script>
(function() {
    document.querySelectorAll('.toggle-pwd').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            const icon = btn.querySelector('i');
            const isPwd = input.type === 'password';
            input.type = isPwd ? 'text' : 'password';
            icon.className = isPwd ? 'bi bi-eye-slash' : 'bi bi-eye';
            btn.setAttribute('aria-label', isPwd ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });

    const pwd = document.getElementById('password');
    const confirm = document.getElementById('confirmar');
    const bar = document.getElementById('pwdStrengthBar');
    const label = document.getElementById('pwdStrengthLabel');
    const match = document.getElementById('pwdMatchIndicator');

    const levels = [
        { w: 0,  c: 'transparent', t: '' },
        { w: 20, c: '#ef4444',     t: 'Muy débil' },
        { w: 40, c: '#f97316',     t: 'Débil' },
        { w: 60, c: '#eab308',     t: 'Aceptable' },
        { w: 80, c: '#22c55e',     t: 'Fuerte' },
        { w: 100,c: '#d4af37',     t: 'Excelente ✦' },
    ];

    function calcStrength(v) {
        let s = 0;
        if (v.length >= 6) s++;
        if (v.length >= 10) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/[0-9]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        return Math.min(s, 5);
    }

    if (pwd) {
        pwd.addEventListener('input', () => {
            const s = calcStrength(pwd.value);
            const l = levels[s];
            bar.style.width = l.w + '%';
            bar.style.backgroundColor = l.c;
            label.textContent = l.t;
            label.style.color = l.c;
            checkMatch();
        });
    }

    if (confirm) {
        confirm.addEventListener('input', checkMatch);
    }

    function checkMatch() {
        if (!match || !pwd || !confirm) return;
        const n = pwd.value;
        const c = confirm.value;
        if (c.length === 0) { match.textContent = ''; return; }
        if (n === c) {
            match.textContent = '✓ Las contraseñas coinciden';
            match.style.color = '#22c55e';
        } else {
            match.textContent = '✗ Las contraseñas no coinciden';
            match.style.color = '#ef4444';
        }
    }

    document.querySelectorAll('[role="alert"]').forEach(el => {
        el.style.animation = 'bh-shake 0.42s ease both';
    });
})();
</script>
</body>
</html>
