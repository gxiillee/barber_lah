<?php
//Evita calculos raro scon datos que suban a la bd mal, como en vez de un int, un string o ""
declare(strict_types=1);

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Cliente.php';
require_once __DIR__ . '/../clases/Administrador.php';

session_start();

// 1. Obtener usuario de sesión una sola vez para mejorar legibilidad
$usuarioSesion = $_SESSION['usuario'] ?? null;
$reservaPendiente = (isset($_SESSION['reserva_pendiente']) && is_array($_SESSION['reserva_pendiente']))
        ? $_SESSION['reserva_pendiente']
        : null;
$mensajeReserva = '';
$sourceLogin = $_GET['source'] ?? ($_POST['source'] ?? '');

if ($usuarioSesion instanceof Usuario) {
    $usuarioSesion->redirigirDespuesLogin(__DIR__);
}

if ($reservaPendiente !== null) {
    $servicioReserva = $reservaPendiente['servicio_nombre'] ?? 'tu servicio';
    $fechaReserva = $reservaPendiente['fecha_label'] ?? ($reservaPendiente['fecha'] ?? 'la fecha elegida');
    $horaReserva = isset($reservaPendiente['hora']) ? substr((string)$reservaPendiente['hora'], 0, 5) : '';
    $mensajeReserva = "Para confirmar tu cita de {$servicioReserva} el {$fechaReserva}" . ($horaReserva !== '' ? " a las {$horaReserva}" : '') . ", accede a tu cuenta o crea una nueva. Solo tardas 30 segundos.";
}

// 2. Inicialización del estado
$estado = Usuario::estadoFormularioLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'login';

    // Delegamos el procesamiento según la acción
    $estado = ($accion === 'registro')
            ? Cliente::procesarRegistroLogin($_POST)
            : Usuario::procesarLogin($_POST);

    // Si el login/registro fue exitoso, guardamos y redirigimos
    if ($estado['usuario'] instanceof Usuario) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = $estado['usuario'];
        $estado['usuario']->redirigirDespuesLogin(__DIR__);
    }
}

// 3. Preparación de variables para la vista (Extract de estado)
$csrfToken   = Usuario::obtenerTokenLogin();
//para si da error contraseña el email se ponga solo de nuevo
$valores     = $estado['valores'];
//para mostrar los errores
$errorLogin  = $estado['errorLogin'];
$errorReg    = $estado['errorRegistro'];

// Clase dinámica para el contenedor principal
$wrapperClase = "login-wrapper " . ($estado['modo'] === 'registro' ? 'es-registro' : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceder · Barbershop La H</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>

<body class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[var(--obsidian)] p-5 font-[var(--font-montserrat)] text-[#f5f0e8] max-sm:items-end max-sm:overflow-auto max-sm:p-0">
    <div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_60%_50%_at_20%_50%,rgba(212,175,55,0.045)_0%,transparent_70%),radial-gradient(ellipse_50%_40%_at_80%_50%,rgba(212,175,55,0.032)_0%,transparent_70%)]"></div>
    <div class="pointer-events-none fixed inset-0 z-0 opacity-55 [background-image:url('data:image/svg+xml,%3Csvg_viewBox=%270_0_200_200%27_xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cfilter_id=%27n%27%3E%3CfeTurbulence_type=%27fractalNoise%27_baseFrequency=%270.85%27_numOctaves=%274%27_stitchTiles=%27stitch%27/%3E%3C/filter%3E%3Crect_width=%27100%25%27_height=%27100%25%27_filter=%27url(%23n)%27_opacity=%270.04%27/%3E%3C/svg%3E')]"></div>

    <a href="index.php" class="fixed left-6 top-6 z-40 text-[10px] font-bold uppercase tracking-[0.16em] text-white/40 no-underline transition hover:-translate-x-0.5 hover:text-[var(--gold)] max-sm:left-5 max-sm:top-5">
        ← Inicio
    </a>

    <main class="<?= htmlspecialchars($wrapperClase) ?> group/auth relative z-10 h-[640px] w-full max-w-[960px] overflow-hidden rounded-[14px] border border-[#1e1e1e] bg-[var(--obsidian)] shadow-[0_0_0_0.5px_rgba(212,175,55,0.08),0_40px_80px_rgba(0,0,0,0.8),0_8px_20px_rgba(0,0,0,0.5)] max-sm:min-h-dvh max-sm:h-auto max-sm:max-w-full max-sm:rounded-none max-sm:border-0" id="loginWrapper" aria-label="Formulario de acceso">
        <div class="flex h-full w-full max-sm:block max-sm:h-auto">
            <section class="flex h-full w-1/2 flex-col items-center justify-center bg-[#0d0d0d] px-12 py-14 max-sm:min-h-dvh max-sm:w-full max-sm:px-7 max-sm:pb-9 max-sm:pt-[72px] max-sm:group-[.es-registro]/auth:hidden" aria-labelledby="loginTitle">
                <div class="mb-5 text-center" aria-label="Barbershop La H">
                    <div class="font-[var(--font-playfair)] text-xs uppercase tracking-[0.22em] text-[var(--gold)]">✦ La H ✦</div>
                    <img src="assets/img/logo.jpg" alt="Logo Barbershop La H" class="mx-auto mt-4 h-[72px] w-[72px] rounded-full border border-[var(--gold)]/25 object-cover shadow-[0_0_22px_rgba(212,175,55,0.12)] max-sm:h-12 max-sm:w-12">
                </div>
                <h1 class="mb-1.5 text-center font-[var(--font-playfair)] text-[28px] font-bold text-[#f5f0e8]" id="loginTitle">Bienvenido</h1>
                <p class="mb-6 text-center text-[11px] tracking-[0.06em] text-white/40">Accede a tu cuenta</p>

                <?php if ($mensajeReserva !== ''): ?>
                    <div class="mb-4 w-full rounded-lg border border-[var(--gold)]/30 bg-[var(--gold)]/[0.08] px-4 py-3 text-left text-[11px] leading-relaxed text-[#eadfbf]">
                        <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--gold)]">
                            <i class="bi bi-calendar-check"></i>
                            Cita pendiente
                        </div>
                        <?= htmlspecialchars($mensajeReserva) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorLogin !== ''): ?>
                    <div class="mb-3.5 flex w-full items-center gap-2 rounded-md border border-red-500/35 bg-red-500/10 px-3.5 py-2.5 text-[11px] leading-snug tracking-[0.03em] text-[#ff6b6b]" role="alert">
                        <i class="bi bi-exclamation-circle shrink-0 text-[13px]"></i>
                        <?= htmlspecialchars($errorLogin) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" class="w-full">
                    <input type="hidden" name="accion" value="login">
                    <!-- Se envia el token junto al formulario para validar que viene de esta pagina. -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <!-- Mantiene el contexto si el usuario llego desde Mi cuenta o desde una reserva pendiente. -->
                    <input type="hidden" name="source" value="<?= htmlspecialchars((string)$sourceLogin) ?>">

                    <input class="mb-3 w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] text-[13px] tracking-[0.03em] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]" type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($valores['login_email']) ?>" autocomplete="email" required>
                    <input class="mb-2 w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] text-[13px] tracking-[0.03em] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]" type="password" name="password" placeholder="Contraseña" autocomplete="current-password" required>

                    <a href="recuperar.php" class="mb-4 block w-full text-right text-[10px] tracking-[0.04em] text-white/35 no-underline transition hover:text-[var(--gold)]">¿Olvidaste tu contraseña?</a>
                    <button type="submit" class="w-full rounded-md bg-[var(--gold)] px-3 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)] transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] hover:shadow-[0_6px_20px_rgba(212,175,55,0.25)]">Entrar</button>
                </form>

                <div class="my-6 flex w-full items-center gap-2.5">
                    <div class="h-px flex-1 bg-white/10"></div>
                    <span class="text-[var(--gold)] opacity-40 text-[8px]">◆</span>
                    <span class="text-[9px] uppercase tracking-[0.22em] text-white/20">o continuar con</span>
                    <span class="text-[var(--gold)] opacity-40 text-[8px]">◆</span>
                    <div class="h-px flex-1 bg-white/10"></div>
                </div>

                <a href="auth/google.php" class="group/google flex w-full items-center justify-center gap-3 rounded-md border border-white/10 bg-white/[0.03] px-4 py-3 text-[10px] font-bold uppercase tracking-[0.16em] text-white/55 no-underline transition hover:border-[var(--gold)]/45 hover:bg-white/[0.06] hover:text-white">
                    <svg class="h-4 w-4 transition group-hover/google:scale-110" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    Google
                </a>

                <button class="mt-5 hidden w-full border-0 bg-transparent text-center text-[11px] tracking-[0.06em] text-[var(--gold)] underline underline-offset-4 max-sm:block" type="button" data-auth-open-register>¿No tienes cuenta? Regístrate</button>
            </section>

            <section class="flex h-full w-1/2 flex-col items-center justify-center bg-[#0a0a0a] px-12 py-14 max-sm:hidden max-sm:min-h-dvh max-sm:w-full max-sm:px-7 max-sm:pb-9 max-sm:pt-[72px] max-sm:group-[.es-registro]/auth:flex" aria-labelledby="registerTitle">
                <div class="mb-6 font-[var(--font-playfair)] text-xs uppercase tracking-[0.22em] text-[var(--gold)]" aria-label="Barbershop La H">✦ La H ✦</div>
                <h2 class="mb-1.5 text-center font-[var(--font-playfair)] text-[28px] font-bold text-[#f5f0e8]" id="registerTitle">Únete al club</h2>
                <p class="mb-6 text-center text-[11px] tracking-[0.06em] text-white/40">Crea tu cuenta VIP</p>

                <?php if ($mensajeReserva !== ''): ?>
                    <div class="mb-4 w-full rounded-lg border border-[var(--gold)]/30 bg-[var(--gold)]/[0.08] px-4 py-3 text-left text-[11px] leading-relaxed text-[#eadfbf]">
                        <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--gold)]">
                            <i class="bi bi-calendar-check"></i>
                            Cita pendiente
                        </div>
                        <?= htmlspecialchars($mensajeReserva) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorReg !== ''): ?>
                    <div class="mb-3.5 flex w-full items-center gap-2 rounded-md border border-red-500/35 bg-red-500/10 px-3.5 py-2.5 text-[11px] leading-snug tracking-[0.03em] text-[#ff6b6b]" role="alert">
                        <i class="bi bi-exclamation-circle shrink-0 text-[13px]"></i>
                        <?= htmlspecialchars($errorReg) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" class="w-full">
                    <input type="hidden" name="accion" value="registro">
                    <!-- Mismo token de seguridad, pero para el formulario de registro. -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <!-- Mantiene el contexto si el usuario llego desde Mi cuenta o desde una reserva pendiente. -->
                    <input type="hidden" name="source" value="<?= htmlspecialchars((string)$sourceLogin) ?>">

                    <input class="mb-3 w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] text-[13px] tracking-[0.03em] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]" type="text" name="nombre" placeholder="Nombre completo" value="<?= htmlspecialchars($valores['nombre']) ?>" autocomplete="name" required>
                    <div class="relative mb-3 w-full">
                        <input class="mb-0 w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-3 pr-10 text-xs tracking-[0.03em] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.08)]"
                               type="tel" name="telefono" placeholder="Teléfono (WhatsApp)"
                               value="<?= htmlspecialchars($valores['telefono']) ?>" autocomplete="tel" required>
                        <button type="button" class="group/tip absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-0 leading-none"
                                aria-label="¿Por qué pedimos tu teléfono?">
                            <i class="bi bi-question-circle text-[14px] text-[#3a3a3a] transition group-hover/tip:text-[var(--gold)]"></i>
                            <div class="pointer-events-none absolute bottom-[calc(100%+10px)] right-[-8px] w-52 rounded-lg border border-[var(--gold)]/25 bg-[#1c1c1c] px-3.5 py-2.5 text-left text-[11px] leading-relaxed tracking-[0.02em] text-white/65 opacity-0 shadow-[0_8px_28px_rgba(0,0,0,0.7)] transition group-hover/tip:opacity-100 group-focus/tip:opacity-100">
                                <i class="bi bi-whatsapp mr-1 text-[#25d366]"></i>Solo usamos tu número para avisarte por WhatsApp cuando tu cita está confirmada. Nada más.
                                <div class="absolute -bottom-[5px] right-3 h-[9px] w-[9px] rotate-45 border-b border-r border-[var(--gold)]/25 bg-[#1c1c1c]"></div>
                            </div>
                        </button>
                    </div>
                    <input class="mb-3 w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] text-[13px] tracking-[0.03em] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]" type="email" name="email_registro" placeholder="Email" value="<?= htmlspecialchars($valores['email_registro']) ?>" autocomplete="email" required>
                    <input class="mb-3 w-full rounded-md border border-[#282828] bg-[#141414] px-3.5 py-[13px] text-[13px] tracking-[0.03em] text-[#e0e0e0] outline-none transition placeholder:text-[#3a3a3a] focus:border-[var(--gold)] focus:bg-[#171717] focus:shadow-[0_0_0_2px_rgba(212,175,55,0.15)]" type="password" name="password_registro" placeholder="Contraseña (mín. 6 caracteres)" autocomplete="new-password" required>

                    <button type="submit" class="w-full rounded-md bg-[var(--gold)] px-3 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--obsidian)] transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)] hover:shadow-[0_6px_20px_rgba(212,175,55,0.25)]">Crear cuenta</button>
                </form>

                <button class="mt-5 hidden w-full border-0 bg-transparent text-center text-[11px] tracking-[0.06em] text-[var(--gold)] underline underline-offset-4 max-sm:block" type="button" data-auth-open-login>¿Ya tienes cuenta? Inicia sesión</button>
            </section>
        </div>

        <aside class="login-overlay absolute right-0 top-0 z-10 flex h-full w-1/2 items-center justify-center overflow-hidden bg-[linear-gradient(145deg,rgba(255,255,255,0.28),transparent_38%),linear-gradient(135deg,var(--gold-light),var(--gold)_45%,var(--gold-dark))] px-9 py-11 text-[var(--obsidian)] max-sm:hidden" aria-hidden="true">
            <div class="login-overlay-card login-overlay-benefits absolute inset-0 flex flex-col items-center justify-center px-9 py-11 text-center after:pointer-events-none after:absolute after:inset-4 after:border after:border-black/10">
                <p class="mb-6 text-[9px] font-bold uppercase tracking-[0.22em] text-black/50">Membresía exclusiva</p>
                <h2 class="mb-3 text-center font-[var(--font-montserrat)] text-2xl font-black uppercase leading-tight tracking-[0.08em] text-[var(--obsidian)]">El estilo<br><span class="font-[var(--font-playfair)] text-[1.65rem] normal-case italic tracking-normal">que mereces</span></h2>
                <p class="mb-6 text-center text-[11px] leading-relaxed tracking-[0.03em] text-black/60">Regístrate y accede a<br>tu espacio VIP</p>

                <ul class="mb-8 w-full list-none">
                    <li class="flex items-center gap-2.5 border-b border-black/10 py-3.5 text-left text-[11px] tracking-[0.03em] text-black/75"><i class="bi bi-calendar-check w-[17px] text-center text-[15px] text-black/50"></i><span>Reserva tus citas online</span></li>
                    <li class="flex items-center gap-2.5 border-b border-black/10 py-2.5 text-left text-[11px] tracking-[0.03em] text-black/75"><i class="bi bi-clock-history w-[17px] text-center text-[15px] text-black/50"></i><span>Historial de cortes</span></li>
                    <li class="flex items-center gap-2.5 border-b border-black/10 py-2.5 text-left text-[11px] tracking-[0.03em] text-black/75"><i class="bi bi-whatsapp w-[17px] text-center text-[15px] text-black/50"></i><span>Avisos por WhatsApp</span></li>
                    <li class="flex items-center gap-2.5 py-3.5 text-left text-[11px] tracking-[0.03em] text-black/75"><i class="bi bi-star w-[17px] text-center text-[15px] text-black/50"></i><span>Puntos y valoraciones</span></li>
                </ul>

                <button class="relative z-[1] rounded-md border border-black/35 bg-transparent px-7 py-3 text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--obsidian)] transition hover:border-black/60 hover:bg-black/10" type="button" data-auth-open-register>Hazte VIP</button>
            </div>

            <div class="login-overlay-card login-overlay-return absolute inset-0 flex flex-col items-center justify-center px-12 py-14 text-center after:pointer-events-none after:absolute after:inset-4 after:border after:border-black/10">
                <p class="mb-6 text-[9px] font-bold uppercase tracking-[0.22em] text-black/50">Ya tienes cuenta</p>
                <h2 class="mb-3 text-center font-[var(--font-montserrat)] text-2xl font-black uppercase leading-tight tracking-[0.08em] text-[var(--obsidian)]">Bienvenido<br><span class="font-[var(--font-playfair)] text-[1.65rem] normal-case italic tracking-normal">de nuevo</span></h2>
                <p class="mb-8 text-center text-[11px] leading-relaxed tracking-[0.03em] text-black/60">Vuelve a iniciar sesión<br>para ver tus citas</p>
                <button class="relative z-[1] rounded-md border border-black/35 bg-transparent px-7 py-3 text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--obsidian)] transition hover:border-black/60 hover:bg-black/10" type="button" data-auth-open-login>Iniciar sesión</button>
            </div>
        </aside>
    </main>

    <script>
        // Controla solo el cambio visual entre login y registro.
        class AuthPanel {
            constructor(wrapper) {
                this.wrapper = wrapper;
                this.bindEvents();
            }

            bindEvents() {
                // Botones que abren el panel de registro.
                document.querySelectorAll("[data-auth-open-register]").forEach((button) => {
                    button.addEventListener("click", () => this.openRegister());
                });

                // Botones que devuelven al panel de inicio de sesion.
                document.querySelectorAll("[data-auth-open-login]").forEach((button) => {
                    button.addEventListener("click", () => this.openLogin());
                });
            }

            openRegister() {
                // Esta clase activa las reglas CSS que mueven el panel dorado.
                this.wrapper.classList.add("es-registro");
            }

            openLogin() {
                // Al quitarla, el panel vuelve al lado inicial.
                this.wrapper.classList.remove("es-registro");
            }
        }

        // Añadir a cada input de contraseña un botón ojo
        document.querySelectorAll('input[type="password"]').forEach(input => {
            const wrap = document.createElement('div');
            wrap.className = 'relative mb-3';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            input.classList.replace('mb-3', 'mb-0');
            input.style.paddingRight = '38px';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('aria-label', 'Mostrar contraseña');
            btn.className = 'absolute right-3 top-1/2 -translate-y-1/2 border-0 bg-transparent p-0 leading-none cursor-pointer text-[#3a3a3a] hover:text-[var(--gold)] transition text-[15px]';
            btn.innerHTML = '<i class="bi bi-eye"></i>';
            btn.addEventListener('click', () => {
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                btn.innerHTML = visible ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
            });
            wrap.appendChild(btn);
        });

        // Barra fuerza contraseña (solo en registro)
        const pwdReg = document.querySelector('input[name="password_registro"]');
        if (pwdReg) {
            const bar = document.createElement('div');
            bar.innerHTML = `<div class="mt-1.5 h-[3px] w-full rounded-full bg-[#1e1e1e] overflow-hidden"><div id="strengthBar" class="h-full rounded-full transition-all duration-300" style="width:0"></div></div><p id="strengthLabel" class="mt-1 text-[10px] tracking-[0.04em] transition-colors" style="min-height:14px;color:#444"></p>`;
            pwdReg.closest('div').after(bar);
            const strengthBar = document.getElementById('strengthBar');
            const strengthLabel = document.getElementById('strengthLabel');
            const lvls = [
                {p:0,c:'transparent',t:''},
                {p:20,c:'#c0392b',t:'Muy débil'},
                {p:40,c:'#e67e22',t:'Débil'},
                {p:60,c:'#f1c40f',t:'Aceptable'},
                {p:80,c:'#27ae60',t:'Fuerte'},
                {p:100,c:'#d4af37',t:'Excelente ✦'}
            ];
            pwdReg.addEventListener('input', () => {
                let s = 0;
                const v = pwdReg.value;
                if (v.length >= 6) s++;
                if (v.length >= 10) s++;
                if (/[A-Z]/.test(v)) s++;
                if (/[0-9]/.test(v)) s++;
                if (/[^A-Za-z0-9]/.test(v)) s++;
                const l = lvls[Math.min(s, 5)];
                strengthBar.style.cssText = `width:${l.p}%;background:${l.c}`;
                strengthLabel.textContent = l.t;
                strengthLabel.style.color = l.c;
            });
        }

        // Shake en errores PHP
        document.querySelectorAll('[role="alert"]').forEach(el => {
            el.style.animation = 'bh-shake 0.42s ease both';
        });

        // Arranca el controlador visual sobre el contenedor principal.
        new AuthPanel(document.getElementById("loginWrapper"));
    </script>
</body>
</html>
