<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Carga de dependencias ─────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Csrf.php';
require_once __DIR__ . '/../clases/FotoCliente.php';

// ── Fase 2: Sesión y control de acceso ────────────────────────────
session_start();

if (!isset($_SESSION['usuario'])) {
    $_SESSION['volver_panel'] = 'index.php';
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario    = $_SESSION['usuario'];
$id_usuario = (int) $usuario->getId();

if ($usuario->tieneRolAdmin()) {
    redirigir('../admin/index.php');
}

// ── Fase 3: Procesamiento POST ────────────────────────────────────
$errores = [];
$subidas = 0;
$huecos  = FotoCliente::MAX_FOTOS - FotoCliente::contarPorUsuario($id_usuario);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!Csrf::validarToken('subir_foto', $_POST['csrf_token'] ?? '')) {
        redirigir('fotos.php');
    }

    if ($huecos <= 0) {
        $errores[] = 'Ya tienes el máximo de ' . FotoCliente::MAX_FOTOS . ' fotos.';
    } elseif (isset($_FILES['fotos']) && !empty($_FILES['fotos']['name'][0])) {

        // Delegamos validación y almacenamiento al modelo (principio de responsabilidad única)
        $resultado = FotoCliente::procesarSubidaMultiple($_FILES['fotos'], $id_usuario, $huecos);

        $subidas = $resultado['subidas'];
        $errores = $resultado['errores'];

        // Si todo fue bien y no hay errores, volver a la galería
        if ($subidas > 0 && empty($errores)) {
            redirigir('fotos.php');
        }

        // Actualizamos los huecos por si hemos subido alguna foto
        $huecos -= $subidas;
    }
}

// ── Fase 4: Datos para la vista ───────────────────────────────────
$puede_subir = $huecos > 0;
$total_fotos = FotoCliente::MAX_FOTOS - $huecos;
$csrf_token  = Csrf::generarToken('subir_foto');
$pagina_activa = 'fotos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir fotos — Barbershop La H</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php require_once __DIR__ . '/includes/nav_cliente.php'; ?>

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">
    <div class="flex-1 w-full max-w-2xl mx-auto p-4 sm:p-6 lg:p-8">

        <!-- Cabecera -->
        <div class="flex items-center gap-3 mb-6">
            <a href="fotos.php" class="flex items-center justify-center w-9 h-9 rounded-lg border hover:border-amber-500 transition-colors" style="border-color:var(--brd);">
                <i class="bi bi-arrow-left" style="color:var(--tx-m);"></i>
            </a>
            <div>
                <h1 style="font-family:var(--pf); font-size:clamp(1.3rem,4vw,1.8rem); font-weight:600;">Subir fotos</h1>
                <p style="font-size:0.62rem; color:var(--tx-m); letter-spacing:0.22em; text-transform:uppercase; margin-top:3px;">
                    <?= $total_fotos ?> / <?= FotoCliente::MAX_FOTOS ?> · Puedes añadir <?= $huecos ?> más
                </p>
            </div>
        </div>

        <?php if (!$puede_subir): ?>
            <div class="rounded-xl p-6 text-center border" style="background:var(--card); border-color:var(--brd);">
                <i class="bi bi-camera-fill" style="font-size:2.5rem; color:var(--tx-d);"></i>
                <div style="font-family:var(--pf); font-size:1.1rem; margin:12px 0 6px;">Límite alcanzado</div>
                <p style="font-size:0.78rem; color:var(--tx-m); max-width:300px; margin:0 auto 20px;">
                    Ya tienes <?= FotoCliente::MAX_FOTOS ?> fotos. Elimina alguna para poder subir nuevas.
                </p>
                <a href="fotos.php" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 font-bold" style="background:var(--gold); color:var(--bg); font-size:0.75rem;">
                    <i class="bi bi-arrow-left"></i> Volver a la galería
                </a>
            </div>
        <?php else: ?>

            <?php if ($subidas > 0): ?>
                <div class="flex items-center gap-3 rounded-xl p-4 mb-4 border bg-green-500/10 border-green-500/30">
                    <i class="bi bi-check-circle text-green-400"></i>
                    <p class="text-sm text-green-300 m-0"><?= $subidas ?> foto(s) subida(s) correctamente.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="rounded-xl p-4 mb-5 border bg-red-500/10 border-red-500/30">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="bi bi-exclamation-circle text-red-500"></i>
                        <span class="text-sm text-red-400 font-semibold">Problemas con algunos archivos:</span>
                    </div>
                    <ul class="m-0 pl-5 list-disc">
                        <?php foreach ($errores as $err): ?>
                            <li class="text-xs text-red-300 mb-1"><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="form-subida" class="rounded-xl p-5 sm:p-6 border" style="background:var(--card); border-color:var(--brd);">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

                <!-- Botón de selección de archivos (Label estilizado que activa el input oculto) -->
                <label for="input-fotos" class="rounded-xl flex flex-col items-center justify-center cursor-pointer mb-5 hover:border-amber-500 transition-colors" style="border:2px dashed var(--brd-h); min-height:200px;">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full mb-3 bg-white/5">
                        <i class="bi bi-camera" style="font-size:1.8rem; color:var(--tx-d);"></i>
                    </div>
                    <p style="font-size:0.85rem; color:var(--tx-m); margin-bottom:4px;">Toca aquí para elegir tus fotos</p>
                    <p style="font-size:0.7rem; color:var(--tx-d);">JPG, PNG o WEBP · Máx. 10 MB · Hasta <?= $huecos ?> foto(s)</p>

                    <!-- Input oculto dentro del label -->
                    <input type="file" id="input-fotos" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                </label>

                <!-- Feedback visual de selección -->
                <p id="resumen" class="hidden text-center mb-4 font-medium" style="font-size:0.85rem; color:var(--gold-l);"></p>

                <!-- Botón de envío -->
                <button type="submit" id="btn-subir" class="w-full rounded-lg py-3 font-semibold transition-opacity" style="background:var(--gold); color:var(--bg); font-size:0.85rem; opacity:0.4; cursor:not-allowed;" disabled>
                    <i class="bi bi-cloud-upload"></i> <span id="btn-texto">Subir fotos</span>
                </button>
            </form>

        <?php endif; ?>
    </div>
</main>

<script>
    /**
     * [TFG] Patrón declarativo: los elementos ya existen en el HTML.
     * El JS solo añade comportamiento (event listeners), no crea DOM.
     * Esto simplifica el mantenimiento y mejora la legibilidad.
     */
    const inputFotos  = document.getElementById('input-fotos');
    const resumen     = document.getElementById('resumen');
    const btnSubir    = document.getElementById('btn-subir');
    const btnTexto    = document.getElementById('btn-texto');
    const formulario  = document.getElementById('form-subida');

    // [TFG] Variable inyectada desde PHP — puente servidor → cliente
    const huecosDisponibles = <?= $huecos ?>;

    if (inputFotos) {
        inputFotos.addEventListener('change', function(e) {
            const numArchivos = e.target.files.length;

            if (numArchivos > 0) {
                let mensaje = '';

                // [TFG] Feedback inmediato al usuario si selecciona más archivos de los permitidos
                if (numArchivos > huecosDisponibles) {
                    mensaje = 'Has seleccionado ' + numArchivos + ' archivos, pero solo se guardarán los primeros ' + huecosDisponibles + '.';
                    resumen.style.color = '#fca5a5'; // Color rojo de advertencia
                } else {
                    mensaje = 'Has seleccionado ' + numArchivos + ' foto(s) listo(s) para subir.';
                    resumen.style.color = 'var(--gold-l)'; // Color normal
                }

                resumen.textContent = mensaje;
                resumen.classList.remove('hidden');

                btnSubir.disabled = false;
                btnSubir.style.opacity = '1';
                btnSubir.style.cursor = 'pointer';

                // [TFG] UX: el texto del botón refleja la cantidad real que se procesará
                const aSubir = (numArchivos > huecosDisponibles) ? huecosDisponibles : numArchivos;
                btnTexto.textContent = 'Subir ' + aSubir + ' foto' + (aSubir > 1 ? 's' : '');
            } else {
                resumen.classList.add('hidden');
                btnSubir.disabled = true;
                btnSubir.style.opacity = '0.4';
                btnSubir.style.cursor = 'not-allowed';
                btnTexto.textContent = 'Subir fotos';
            }
        });

        // [TFG] Feedback visual de envío: desactivar botón y mostrar spinner
        formulario.addEventListener('submit', function() {
            btnSubir.disabled = true;
            btnSubir.style.opacity = '0.7';
            btnSubir.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Subiendo... por favor espera';
        });
    }
</script>

<?php require_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>