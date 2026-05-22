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
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];

if ($usuario->tieneRolAdmin()) {
    redirigir('../admin/index.php');
}

// ── Fase 3: Recuperación de datos ─────────────────────────────────
$id_usuario  = (int) $usuario->getId();
$fotos       = FotoCliente::obtenerPorUsuario($id_usuario);
$total_fotos = count($fotos);
$puede_subir = $total_fotos < FotoCliente::MAX_FOTOS;
$csrf_token  = Csrf::generarToken('eliminar_foto');

$pagina_activa = 'fotos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Fotos — Barbershop La H</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php require_once __DIR__ . '/includes/nav_cliente.php'; ?>

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">
    <div class="flex-1 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        <!-- Cabecera -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="leading-tight" style="font-family:var(--pf); font-size:clamp(1.5rem,4vw,2rem); font-weight:600;">
                    Mis Fotos
                </h1>
                <p style="font-size:0.62rem; color:var(--tx-m); letter-spacing:0.22em; text-transform:uppercase; margin-top:3px;">
                    TU HISTORIAL VISUAL DE CORTES
                </p>
            </div>

            <div class="flex flex-col items-end gap-1.5 sm:gap-2">
                <div style="font-size:0.75rem; color:var(--gold-l); opacity:0.8;">
                    <?= $total_fotos ?> / <?= FotoCliente::MAX_FOTOS ?> fotos
                </div>
                <?php if ($puede_subir): ?>
                    <a href="subir_foto.php"
                       class="flex items-center gap-2 px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg transition-opacity"
                       style="background:var(--gold); color:var(--bg); font-size:0.75rem; font-weight:600; text-decoration:none;"
                       onmouseover="this.style.opacity='0.9'"
                       onmouseout="this.style.opacity='1'">
                        <i class="bi bi-plus-lg"></i>
                        Añadir foto
                    </a>
                <?php else: ?>
                    <!-- Botón desactivado cuando se alcanza el límite -->
                    <span class="flex items-center gap-2 px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg"
                          style="background:var(--bg3); color:var(--tx-d); font-size:0.75rem; font-weight:600; cursor:not-allowed;">
                        <i class="bi bi-slash-circle"></i>
                        Límite alcanzado
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Banner informativo -->
        <div class="rounded-xl flex items-start gap-4 p-4 sm:p-5 mb-6 sm:mb-8 foto-alerta">
            <i class="bi bi-phone" style="font-size:1.4rem; color:var(--gold); margin-top:2px;"></i>
            <p style="font-size:0.82rem; color:var(--tx-m); line-height:1.5; margin:0;">
                Hassan puede ver tus fotos. Así sabe exactamente qué estilo llevas y puede reproducirlo en cada visita sin que tengas que explicar nada. Máximo <?= FotoCliente::MAX_FOTOS ?> fotos — elimina las antiguas para añadir nuevas.
            </p>
        </div>

        <!-- Grid de fotos -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 sm:gap-4 mb-8">

            <!-- Tarjeta de añadir (solo si hay hueco) -->
            <?php if ($puede_subir): ?>
                <a href="subir_foto.php"
                   class="flex flex-col items-center justify-center rounded-xl p-4 foto-add-btn w-full cursor-pointer"
                   style="text-decoration:none; aspect-ratio:1;">
                    <i class="bi bi-camera" style="font-size:1.8rem; color:var(--tx-d); transition:color 0.2s;"></i>
                    <span style="font-size:0.75rem; color:var(--tx-m); margin-top:8px; transition:color 0.2s;">Añadir foto</span>
                </a>
            <?php endif; ?>

            <!-- Fotos del usuario -->
            <?php foreach ($fotos as $foto): ?>
                <?php
                // Formatear fecha para el overlay
                $fecha_texto = $foto['fecha_subida'];
                try {
                    $dt          = new DateTimeImmutable($foto['fecha_subida']);
                    $mes         = nombreMesCorto((int) $dt->format('n'));
                    $fecha_texto = 'Corte ' . $dt->format('j') . ' ' . $mes . ' ' . $dt->format('Y');
                } catch (Exception $e) {}
                ?>
                <div class="relative rounded-xl overflow-hidden border foto-item"
                     style="aspect-ratio:1; border-color:var(--brd); background:var(--bg3);">

                    <!-- Imagen -->
                    <img src="<?= h('../' . $foto['ruta']) ?>"
                         alt="Foto historial de corte"
                         class="w-full h-full object-cover">

                    <!-- Overlay siempre visible: fecha en la parte baja -->
                    <div class="absolute bottom-0 left-0 w-full p-2.5"
                         style="background:linear-gradient(to top, rgba(0,0,0,.8), transparent);">
                        <span style="font-size:0.68rem; color:#f5f0e8; font-weight:500; letter-spacing:0.02em;">
                            <?= h($fecha_texto) ?>
                        </span>
                    </div>

                    <!-- Botón eliminar: aparece en hover sobre la foto -->
                    <div class="foto-overlay-btn absolute top-2 right-2 opacity-0 transition-opacity duration-200">
                        <form method="POST" action="eliminar_foto.php"
                              onsubmit="return confirm('¿Eliminar esta foto? Esta acción no se puede deshacer.')">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <input type="hidden" name="id_foto"    value="<?= (int) $foto['id'] ?>">
                            <button type="submit"
                                    class="flex items-center justify-center w-8 h-8 rounded-lg"
                                    style="background:rgba(220,38,38,.85); color:#fff; border:none; cursor:pointer;"
                                    title="Eliminar foto">
                                <i class="bi bi-trash3" style="font-size:0.85rem;"></i>
                            </button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>

        </div>

        <!-- Estado vacío: el usuario no tiene ninguna foto todavía -->
        <?php if ($total_fotos === 0): ?>
            <div class="text-center py-10 rounded-xl border"
                 style="background:var(--card); border:1.5px dashed var(--brd-h);">
                <i class="bi bi-camera" style="font-size:2.8rem; color:var(--tx-d);"></i>
                <div style="font-family:var(--pf); font-size:1.05rem; margin:12px 0 6px;">
                    Todavía no tienes fotos
                </div>
                <p style="font-size:0.78rem; color:var(--tx-m); max-width:280px; margin:0 auto 20px; line-height:1.5;">
                    Sube la primera para que Hassan pueda ver tu estilo antes de cada visita.
                </p>
                <a href="subir_foto.php"
                   class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5"
                   style="background:var(--gold); color:var(--bg); font-size:0.8rem; font-weight:700;">
                    <i class="bi bi-cloud-upload"></i> Subir primera foto
                </a>
            </div>
        <?php endif; ?>

        <!-- Pie de sección (solo si ya hay fotos) -->
        <?php if ($total_fotos > 0): ?>
            <div class="text-center pb-6">
                <p style="font-size:0.75rem; color:var(--tx-d); max-width:400px; margin:0 auto; line-height:1.5;">
                    Actualiza tus fotos cuando cambies de estilo para que Hassan siempre sepa cómo tienes el pelo actualmente.
                </p>
            </div>
        <?php endif; ?>

    </div>
</main>



</body>
</html>