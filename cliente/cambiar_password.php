<?php
declare(strict_types=1);

// ── Fase 1: Dependencias ──────────────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Csrf.php';

// ── Fase 2: Control de acceso ─────────────────────────────────────
iniciarSesionSegura();
if (!isset($_SESSION['usuario'])) {
    $_SESSION['volver_panel'] = 'index.php';
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];
$pagina_activa = 'perfil';

// ── Detectar si el usuario tiene contraseña o viene de Google ──
$tienePassword = $usuario->tienePassword();
$esAdmin = $usuario->tieneRolAdmin();

// Fecha del último cambio de contraseña
$ultimoCambio = Usuario::obtenerFechaUltimoCambioPassword($usuario->getId());
$diasDesdeCambio = $ultimoCambio ? floor((time() - strtotime($ultimoCambio)) / 86400) : null;

$error = '';
$exito = '';

// ── Fase 3: Procesar el Formulario ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!Csrf::validarToken('csrf_cambiar_password', $csrf_token)) {
        $error = 'Sesión caducada. Recarga la página.';
    } else {
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_conf  = $_POST['password_confirmar'] ?? '';

    // Validaciones comunes
    if (empty($password_nueva) || empty($password_conf)) {
        $error = 'Por favor, rellena todos los campos.';
    } elseif (strlen($password_nueva) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password_nueva !== $password_conf) {
        $error = 'La nueva contraseña y su confirmación no coinciden.';
    } else {
        // Si ya tenía contraseña, verificar la actual
        if ($tienePassword) {
            $password_actual = $_POST['password_actual'] ?? '';
            if (empty($password_actual)) {
                $error = 'Introduce tu contraseña actual.';
            } else {
                try {
                    $db = BD::obtenerConexion();
                    $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = :id");
                    $stmt->execute(['id' => $usuario->getId()]);
                    $hash_actual = $stmt->fetchColumn();

                    if (!$hash_actual || !password_verify($password_actual, $hash_actual)) {
                        $error = 'La contraseña actual no es correcta.';
                    }
                } catch (Exception $e) {
                    $error = 'Ocurrió un error en el servidor. Inténtalo de nuevo.';
                    error_log("Error al verificar contraseña: " . $e->getMessage());
                }
            }
        }

        // Admin: verificar que no repita últimas 3 contraseñas
        if (empty($error) && $esAdmin && $tienePassword) {
            if (!Usuario::checkPasswordHistory($usuario->getId(), $password_nueva)) {
                $error = 'Esta contraseña ya la has usado recientemente. Elige una diferente.';
            }
        }

        // Si no hay error, establecer/actualizar la contraseña
        if (empty($error)) {
            try {
                $ok = Usuario::establecerPassword($usuario->getId(), $password_nueva);
                if ($ok) {
                    // Admin: guardar en historial de contraseñas
                    if ($esAdmin && $tienePassword) {
                        $hash = password_hash($password_nueva, PASSWORD_BCRYPT, ['cost' => 12]);
                        Usuario::addPasswordHistory($usuario->getId(), $hash);
                    }

                    // Actualizar el objeto en sesión para que tengaPassword() refleje el cambio
                    $usuario = Usuario::comprobarLogin($usuario->getEmail(), $password_nueva);
                    if ($usuario) {
                        $_SESSION['usuario'] = $usuario;
                    $_SESSION['pwd_updated_at'] = $usuario->getPasswordUpdatedAt();
                    }
                    $exito = $tienePassword
                        ? 'Contraseña actualizada correctamente.'
                        : '¡Contraseña establecida correctamente! Ya puedes iniciar sesión con email y contraseña.';
                } else {
                    $error = 'No se pudo actualizar la contraseña. Inténtalo de nuevo.';
                }
            } catch (Exception $e) {
                $error = 'Ocurrió un error en el servidor. Inténtalo de nuevo.';
                error_log("Error al establecer contraseña: " . $e->getMessage());
            }
        }
    }
    } // else (CSRF válido)
}

$csrfToken = Csrf::generarToken('csrf_cambiar_password');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tienePassword ? 'Cambiar' : 'Establecer' ?> Contraseña — Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php require_once __DIR__ . '/includes/nav_cliente.php'; ?>

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">
    <div class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-8 lg:px-12 py-4 sm:py-6 lg:py-10 stagger-container">

        <!-- ── Cabecera con back ── -->
        <div class="flex items-center gap-4 mb-6 lg:mb-10">
            <a href="perfil.php"
               class="flex items-center justify-center w-11 h-11 rounded-xl border transition-all hover:border-[var(--gold-brd)] hover:text-[var(--gold)] glow-card"
               style="border-color:var(--brd); color:var(--tx-m);">
                <i class="bi bi-arrow-left" style="font-size:1.1rem;"></i>
            </a>
            <div>
                <h1 style="font-family:var(--pf); font-size:clamp(1.4rem,3vw,2rem); font-weight:600; line-height:1.15;">
                    <?= $tienePassword ? 'Cambiar' : 'Establecer' ?> contraseña
                </h1>
                <p style="font-size:0.65rem; color:var(--tx-m); letter-spacing:0.22em; text-transform:uppercase; margin-top:3px;">
                    Seguridad de la cuenta
                </p>
            </div>
        </div>

        <!-- ── Grid responsive: móvil 1 col, desktop 2 cols ── -->
        <div class="grid lg:grid-cols-[1fr_340px] gap-8 lg:gap-10 xl:gap-14">

            <!-- ═══ Columna principal: formulario ═══ -->
            <div class="pwd-card glow-card lg:p-8">
                <div class="flex items-center justify-center mb-6">
                    <div class="flex items-center justify-center rounded-full"
                         style="width:64px; height:64px; background:var(--gold-dim); border:1px solid var(--gold-brd);">
                        <i class="bi bi-shield-lock" style="font-size:1.6rem; color:var(--gold);"></i>
                    </div>
                </div>

                <p style="font-size:0.85rem; color:var(--tx-m); text-align:center; margin-bottom:28px; line-height:1.6;">
                    <?= $tienePassword
                        ? 'Elige una contraseña segura que no uses en otros sitios.'
                        : 'Registraste con Google. Establece una contraseña para poder iniciar sesión también con email.' ?>
                </p>

                <?php if (!empty($error)): ?>
                    <div class="mb-5 p-4 rounded-xl flex items-center gap-3" style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.15);">
                        <i class="bi bi-exclamation-triangle-fill" style="color:#ef4444; font-size:1rem;"></i>
                        <span style="font-size:0.82rem; color:#fca5a5;"><?= h($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($exito)): ?>
                    <div class="mb-5 p-4 rounded-xl flex items-center gap-3" style="background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.15);">
                        <i class="bi bi-check-circle-fill" style="color:#22c55e; font-size:1rem;"></i>
                        <span style="font-size:0.82rem; color:#86efac;"><?= h($exito) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$tienePassword): ?>
                    <div class="rounded-xl p-4 flex items-start gap-3 mb-5" style="background:var(--gold-dim); border:1px solid var(--gold-brd);">
                        <i class="bi bi-google" style="color:var(--gold); font-size:1.2rem; margin-top:2px;"></i>
                        <div>
                            <p style="font-size:0.78rem; font-weight:600; color:var(--gold); margin-bottom:3px;">Cuenta vinculada con Google</p>
                            <p style="font-size:0.72rem; color:var(--tx-m); line-height:1.5;">
                                No tienes contraseña. Al establecerla podrás iniciar sesión con email y contraseña, además de con Google.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="cambiar_password.php" method="POST" class="flex flex-col gap-6">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                    <?php if ($tienePassword): ?>
                    <div>
                        <label class="pwd-label" for="password_actual" style="font-size:0.7rem;">Contraseña actual</label>
                        <div class="relative">
                            <input type="password" id="password_actual" name="password_actual" required
                                   class="pwd-field" placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="toggle-pwd-field absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-0"
                                    style="color:var(--tx-d); font-size:1.1rem;" aria-label="Mostrar contraseña" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div style="height:1px; background:var(--brd); margin:0;"></div>
                    <?php endif; ?>

                    <div>
                        <label class="pwd-label" for="password_nueva" style="font-size:0.7rem;">
                            <?= $tienePassword ? 'Nueva contraseña' : 'Contraseña' ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="password_nueva" name="password_nueva" required
                                   class="pwd-field" placeholder="Mín. 6 caracteres" autocomplete="new-password">
                            <button type="button" class="toggle-pwd-field absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-0"
                                    style="color:var(--tx-d); font-size:1.1rem;" aria-label="Mostrar contraseña" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2.5 rounded-full overflow-hidden" style="height:5px; background:rgba(255,255,255,0.04);">
                            <div id="pwdStrengthBar" class="h-full rounded-full transition-all duration-300" style="width:0%; background:transparent;"></div>
                        </div>
                        <p id="pwdStrengthLabel" style="font-size:0.65rem; color:var(--tx-d); margin-top:5px; min-height:18px; transition:color 0.2s;"></p>
                    </div>

                    <div>
                        <label class="pwd-label" for="password_confirmar" style="font-size:0.7rem;">
                            <?= $tienePassword ? 'Confirmar nueva contraseña' : 'Confirmar contraseña' ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="password_confirmar" name="password_confirmar" required
                                   class="pwd-field" placeholder="Repite la contraseña" autocomplete="new-password">
                            <button type="button" class="toggle-pwd-field absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-0"
                                    style="color:var(--tx-d); font-size:1.1rem;" aria-label="Mostrar contraseña" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <p id="pwdMatchIndicator" style="font-size:0.65rem; margin-top:5px; min-height:18px;"></p>
                    </div>

                    <button type="submit"
                            class="w-full py-4 rounded-xl font-bold text-sm uppercase tracking-wider transition-all duration-200 active:scale-[0.98]"
                            style="background:var(--gold); color:var(--bg); border:none; cursor:pointer; font-size:0.85rem;">
                        <i class="bi bi-check-lg"></i> <?= $tienePassword ? 'Actualizar contraseña' : 'Establecer contraseña' ?>
                    </button>

                </form>
            </div>

            <!-- ═══ Columna lateral: tips ═══ -->
            <div class="flex flex-col gap-5">
                <?php if ($diasDesdeCambio !== null && $tienePassword): ?>
                <div class="rounded-xl p-6 border" style="background:var(--card); border-color:var(--brd);">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="bi bi-clock-history" style="color:var(--gold); font-size:1.1rem;"></i>
                        <span style="font-size:0.78rem; font-weight:600; color:var(--tx);">Último cambio</span>
                    </div>
                    <p style="font-size:0.82rem; color:var(--tx-m);">
                        <?php if ($diasDesdeCambio === 0): ?>
                            Hoy mismo
                        <?php elseif ($diasDesdeCambio === 1): ?>
                            Ayer
                        <?php else: ?>
                            Hace <strong><?= number_format($diasDesdeCambio) ?> días</strong>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="rounded-xl p-6 border" style="background:var(--card); border-color:var(--brd);">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="bi bi-shield-check" style="color:var(--gold); font-size:1.1rem;"></i>
                        <span style="font-size:0.78rem; font-weight:600; color:var(--tx);">Consejo de seguridad</span>
                    </div>
                    <ul style="font-size:0.72rem; color:var(--tx-m); line-height:1.7; padding-left:18px; margin:0;">
                        <li>Usa al menos 6 caracteres</li>
                        <li>Combina mayúsculas y minúsculas</li>
                        <li>Añade al menos un número</li>
                        <li>No uses la misma de otros sitios</li>
                    </ul>
                </div>

                <?php if ($esAdmin && $tienePassword): ?>
                <div class="rounded-xl p-6 border" style="background:var(--card); border-color:var(--brd);">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="bi bi-arrow-repeat" style="color:var(--gold); font-size:1.1rem;"></i>
                        <span style="font-size:0.78rem; font-weight:600; color:var(--tx);">Historial</span>
                    </div>
                    <p style="font-size:0.72rem; color:var(--tx-m); line-height:1.6;">
                        No puedes repetir ninguna de tus últimas 3 contraseñas.
                    </p>
                </div>
                <?php endif; ?>

                <div class="rounded-xl p-6 border" style="background:var(--card); border-color:var(--brd);">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="bi bi-question-circle" style="color:var(--gold); font-size:1.1rem;"></i>
                        <span style="font-size:0.78rem; font-weight:600; color:var(--tx);">
                            <?= $tienePassword ? '¿Problemas?' : 'Sin contraseña aún' ?>
                        </span>
                    </div>
                    <p style="font-size:0.72rem; color:var(--tx-m); line-height:1.6;">
                        <?php if ($tienePassword): ?>
                            Si no recuerdas tu contraseña actual, contacta con Hassan para reestablecerla.
                        <?php else: ?>
                            Al establecer una contraseña podrás acceder tanto con Google como con tu email desde cualquier dispositivo.
                        <?php endif; ?>
                    </p>
                </div>

                <a href="perfil.php" class="hidden lg:flex items-center justify-center gap-2 rounded-xl p-5 border transition-all hover:border-[var(--gold-brd)] hover:text-[var(--gold)] glow-card"
                   style="border-color:var(--brd); color:var(--tx-m); font-size:0.8rem;">
                    <i class="bi bi-arrow-left"></i> Volver a mi perfil
                </a>
            </div>

        </div>

    </div>
</main>

<script>
/**
 * Cambiar contraseña — Interacciones del formulario
 */
(function() {
    // Toggle visibility de campos
    document.querySelectorAll(".toggle-pwd-field").forEach((btn) => {
        btn.addEventListener("click", () => {
            const input = btn.previousElementSibling;
            const icon  = btn.querySelector("i");
            const isPwd = input.type === "password";
            input.type  = isPwd ? "text" : "password";
            icon.className = isPwd ? "bi bi-eye-slash" : "bi bi-eye";
            btn.setAttribute("aria-label", isPwd ? "Ocultar contraseña" : "Mostrar contraseña");
        });
    });

    // Medidor de fortaleza
    const pwdNueva   = document.getElementById("password_nueva");
    const pwdConfirm = document.getElementById("password_confirmar");
    const bar        = document.getElementById("pwdStrengthBar");
    const label      = document.getElementById("pwdStrengthLabel");
    const matchLabel = document.getElementById("pwdMatchIndicator");

    const levels = [
        { w: 0,  c: "transparent", t: "" },
        { w: 20, c: "#ef4444",     t: "Muy débil" },
        { w: 40, c: "#f97316",     t: "Débil" },
        { w: 60, c: "#eab308",     t: "Aceptable" },
        { w: 80, c: "#22c55e",     t: "Fuerte" },
        { w: 100,c: "#d4af37",     t: "Excelente ✦" },
    ];

    function calcStrength(pwd) {
        let score = 0;
        if (pwd.length >= 6)  score++;
        if (pwd.length >= 10) score++;
        if (/[A-Z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;
        return Math.min(score, 5);
    }

    if (pwdNueva) {
        pwdNueva.addEventListener("input", () => {
            const s = calcStrength(pwdNueva.value);
            const l = levels[s];
            bar.style.width = l.w + "%";
            bar.style.backgroundColor = l.c;
            label.textContent = l.t;
            label.style.color = l.c;
            checkMatch();
        });
    }

    if (pwdConfirm) {
        pwdConfirm.addEventListener("input", checkMatch);
    }

    function checkMatch() {
        if (!matchLabel || !pwdNueva || !pwdConfirm) return;
        const n = pwdNueva.value;
        const c = pwdConfirm.value;
        if (c.length === 0) {
            matchLabel.textContent = "";
            return;
        }
        if (n === c) {
            matchLabel.textContent = "✓ Las contraseñas coinciden";
            matchLabel.style.color = "#22c55e";
        } else {
            matchLabel.textContent = "✗ Las contraseñas no coinciden";
            matchLabel.style.color = "#ef4444";
        }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>