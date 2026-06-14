<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/BdMongo.php';
require_once __DIR__ . '/../clases/ConfigWeb.php';
require_once __DIR__ . '/../clases/helpers.php';

session_start();
if (!isset($_SESSION['usuario'])) redirigir('../login.php');
if (!$_SESSION['usuario']->tieneRolAdmin()) redirigir('../cliente/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $datos = [
        'direccion'       => trim($_POST['direccion'] ?? ''),
        'telefono'        => trim($_POST['telefono'] ?? ''),
        'email'           => trim($_POST['email'] ?? ''),
        'instagram'       => trim($_POST['instagram'] ?? ''),
        'horario_resumen' => trim($_POST['horario_resumen'] ?? ''),

        'sobre_subtitulo'  => trim($_POST['sobre_subtitulo'] ?? ''),
        'sobre_titulo'     => trim($_POST['sobre_titulo'] ?? ''),
        'sobre_imagen'     => trim($_POST['sobre_imagen'] ?? ''),
        'sobre_anios'      => trim($_POST['sobre_anios'] ?? ''),
        'sobre_anios_texto'=> trim($_POST['sobre_anios_texto'] ?? ''),
        'sobre_nombre'     => trim($_POST['sobre_nombre'] ?? ''),
        'sobre_texto_1'    => trim($_POST['sobre_texto_1'] ?? ''),
        'sobre_texto_2'    => trim($_POST['sobre_texto_2'] ?? ''),
        'sobre_texto_3'    => trim($_POST['sobre_texto_3'] ?? ''),
    ];

    if (isset($_FILES['sobre_imagen_file']) && $_FILES['sobre_imagen_file']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/../public/uploads/sobre';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $ext = strtolower(pathinfo($_FILES['sobre_imagen_file']['name'], PATHINFO_EXTENSION));
        $nombre = 'sobre_' . time() . '.' . $ext;
        $destino = "$dir/$nombre";
        if (move_uploaded_file($_FILES['sobre_imagen_file']['tmp_name'], $destino)) {
            $datos['sobre_imagen'] = 'public/uploads/sobre/' . $nombre;
        }
    }

    if (ConfigWeb::guardar($datos)) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Configuración guardada.'];
        redirigir('config-web.php');
    } else {
        $error = 'Error al guardar en MongoDB.';
    }
}

$config = ConfigWeb::obtener();
$pagina_activa = 'config-web';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Web — Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-content.active > * { animation: fadeIn 0.25s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .pill-tab.active { background: rgba(212, 175, 55, 0.15); color: #d4af37; border-color: rgba(212, 175, 55, 0.35); }
        .preview-mockup { background: #080808; border: 1px solid rgba(212, 175, 55, 0.12); }
        @media (min-width: 1024px) {
            .desktop-split { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; }
            .preview-panel { position: sticky; top: 90px; align-self: start; max-height: calc(100vh - 110px); overflow-y: auto; }
        }
    </style>
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[70px] pb-28 px-4 mx-auto lg:ml-[240px] lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-6xl pagina-entrada">

    <!-- Header + pill tabs (inline, not fixed) -->
    <div class="mb-5">
        <div class="flex items-center justify-between">
            <h1 class="text-base lg:text-xl font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">
                <i class="bi bi-sliders2 text-[var(--gold)] mr-1.5"></i> Configuración Web
            </h1>
            <div class="flex items-center gap-2">
                <a href="../index.php" target="_blank"
                   class="hidden lg:flex items-center gap-1.5 text-[0.6rem] uppercase tracking-[0.1em] text-[var(--tx-m)] bg-white/[0.03] border border-[var(--brd)] rounded-lg px-3 py-1.5 hover:text-[var(--gold)] hover:border-[var(--gold-brd)] transition-all">
                    <i class="bi bi-box-arrow-up-right"></i> Ver web
                </a>
                <button type="submit" form="configForm"
                        class="lg:hidden bg-gradient-to-r from-[#d4af37] to-[#e8c84a] text-[#0d0d0d] font-bold text-[0.55rem] tracking-[0.12em] uppercase px-3.5 py-2 rounded-lg shadow-lg shadow-[#d4af37]/20 active:scale-95 transition-all cursor-pointer">
                    <i class="bi bi-check2 text-[0.6rem] mr-1"></i> Guardar
                </button>
            </div>
        </div>
        <p class="text-[0.65rem] lg:text-[0.7rem] text-[var(--tx-m)] mt-0.5">Personaliza el contenido de la web pública</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-4 px-4 py-2.5 rounded-lg border border-rose-500/20 bg-rose-500/10 text-rose-400 text-[0.7rem] font-medium flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Pill tabs -->
    <div class="flex gap-1.5 lg:gap-2 mb-5">
        <button class="pill-tab active flex-1 lg:flex-none text-center text-[0.6rem] lg:text-[0.65rem] font-medium uppercase tracking-[0.1em] px-3 lg:px-4 py-2 lg:py-2.5 rounded-lg border border-transparent transition-all cursor-pointer" data-tab="info">
            <i class="bi bi-shop mr-1"></i><span class="hidden lg:inline">Información del </span>Negocio
        </button>
        <button class="pill-tab flex-1 lg:flex-none text-center text-[0.6rem] lg:text-[0.65rem] font-medium uppercase tracking-[0.1em] px-3 lg:px-4 py-2 lg:py-2.5 rounded-lg border border-transparent transition-all cursor-pointer" data-tab="sobre">
            <i class="bi bi-info-circle mr-1"></i>Sobre Nosotros
        </button>
        <button class="pill-tab flex-1 lg:flex-none text-center text-[0.6rem] lg:text-[0.65rem] font-medium uppercase tracking-[0.1em] px-3 lg:px-4 py-2 lg:py-2.5 rounded-lg border border-transparent transition-all cursor-pointer" data-tab="vista">
            <i class="bi bi-eye mr-1"></i>Vista Previa
        </button>
    </div>

    <form id="configForm" action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="guardar">

        <div class="desktop-split">

            <!-- ===== LEFT: FORM ===== -->
            <div>

                <!-- ─── Tab: Info ─── -->
                <div class="tab-content active" id="tab-info">
                    <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 lg:p-5 glow-card">
                        <div class="grid gap-3 lg:grid-cols-2">
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">
                                    <i class="bi bi-geo-alt text-[var(--gold)] mr-1"></i> Dirección
                                </label>
                                <input type="text" name="direccion" value="<?= h($config['direccion'] ?? '') ?>" placeholder="Calle Mayor 12"
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">
                                    <i class="bi bi-telephone text-[var(--gold)] mr-1"></i> Teléfono
                                </label>
                                <input type="text" name="telefono" value="<?= h($config['telefono'] ?? '') ?>" placeholder="612 34 56 78"
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">
                                    <i class="bi bi-envelope text-[var(--gold)] mr-1"></i> Email
                                </label>
                                <input type="email" name="email" value="<?= h($config['email'] ?? '') ?>" placeholder="info@barbershoplah.com"
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">
                                    <i class="bi bi-instagram text-[var(--gold)] mr-1"></i> Instagram
                                </label>
                                <input type="text" name="instagram" value="<?= h($config['instagram'] ?? '') ?>" placeholder="@barbershoplah"
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">
                                <i class="bi bi-clock text-[var(--gold)] mr-1"></i> Horario
                            </label>
                            <input type="text" name="horario_resumen" value="<?= h($config['horario_resumen'] ?? '') ?>" placeholder="Lun–Vie 9:00–20:00 · Sáb 9:00–14:00"
                                   class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                        </div>
                    </div>
                </div>

                <!-- ─── Tab: Sobre ─── -->
                <div class="tab-content" id="tab-sobre">
                    <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 lg:p-5 glow-card">
                        <div class="grid gap-3 lg:grid-cols-2">
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">Subtítulo</label>
                                <input type="text" name="sobre_subtitulo" value="<?= h($config['sobre_subtitulo'] ?? '') ?>" placeholder="Barbershop La H"
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">Título</label>
                                <input type="text" name="sobre_titulo" value="<?= h($config['sobre_titulo'] ?? '') ?>" placeholder="Sobre Nosotros"
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">
                                <i class="bi bi-image text-[var(--gold)] mr-1"></i> Imagen
                            </label>
                            <div class="flex gap-2">
                                <input type="text" name="sobre_imagen" value="<?= h($config['sobre_imagen'] ?? '') ?>" placeholder="public/assets/img/logo.jpg"
                                       class="flex-1 bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                                <label class="shrink-0 flex items-center gap-1.5 bg-[#1a1a1a] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.65rem] text-[var(--tx-m)] cursor-pointer hover:border-[var(--gold-brd)] transition-all active:bg-[#222]">
                                    <i class="bi bi-upload"></i>
                                    <input type="file" name="sobre_imagen_file" accept="image/*" class="hidden" onchange="this.form.submit()">
                                </label>
                            </div>
                            <?php if (!empty($config['sobre_imagen'])): ?>
                                <img src="../<?= h($config['sobre_imagen']) ?>" class="mt-2.5 h-20 w-auto rounded-lg border border-[var(--brd)] object-cover shadow-md">
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">Badge nº</label>
                                <input type="text" name="sobre_anios" value="<?= h($config['sobre_anios'] ?? '') ?>" placeholder="+10"
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                            <div>
                                <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">Badge texto</label>
                                <input type="text" name="sobre_anios_texto" value="<?= h($config['sobre_anios_texto'] ?? '') ?>" placeholder="Años de exp."
                                       class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1">
                                <i class="bi bi-person-badge text-[var(--gold)] mr-1"></i> Nombre
                            </label>
                            <input type="text" name="sobre_nombre" value="<?= h($config['sobre_nombre'] ?? '') ?>" placeholder="Hassan"
                                   class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all">
                        </div>

                        <?php $parrafos = ['sobre_texto_1' => 'Párrafo 1', 'sobre_texto_2' => 'Párrafo 2', 'sobre_texto_3' => 'Párrafo 3']; ?>
                        <?php foreach ($parrafos as $campo => $etiqueta): ?>
                        <div class="mt-3">
                            <label class="block text-[0.55rem] uppercase tracking-[0.15em] text-[var(--tx-m)] font-semibold mb-1"><?= $etiqueta ?></label>
                            <textarea name="<?= $campo ?>" rows="2"
                                      class="w-full bg-[#0d0d0d] border border-[var(--brd)] rounded-lg px-3 py-2.5 text-[0.8rem] text-[var(--tx)] placeholder:text-[var(--tx-d)]/40 focus:outline-hidden focus:border-[var(--gold-brd)] transition-all resize-y"
                                      placeholder="Escribe el texto..."><?= h($config[$campo] ?? '') ?></textarea>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ─── Tab: Vista Previa ─── -->
                <div class="tab-content" id="tab-vista">
                    <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card" id="previewContainer">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-[0.75rem] font-semibold text-[var(--tx)] flex items-center gap-1.5" style="font-family: var(--pf);">
                                <i class="bi bi-phone text-[var(--gold)]"></i> Vista previa móvil
                            </h3>
                            <span class="text-[0.45rem] uppercase tracking-[0.15em] text-[var(--tx-d)] bg-white/5 px-2 py-1 rounded border border-[var(--brd)]">Simulación</span>
                        </div>

                        <div class="preview-mockup rounded-xl overflow-hidden max-w-[320px] mx-auto shadow-2xl">
                            <div class="px-5 pt-6 pb-4 text-center border-b border-white/5">
                                <div class="font-playfair text-sm uppercase tracking-[0.2rem] text-[var(--gold)]">Barbershop La H</div>
                                <div class="text-[0.45rem] text-white/30 mt-1 uppercase tracking-[0.15em]" id="pv-sub"><?= h($config['sobre_subtitulo'] ?: 'Barbershop La H') ?></div>
                                <div class="font-playfair text-xl mt-1 text-white" id="pv-tit"><?= h($config['sobre_titulo'] ?: 'Sobre Nosotros') ?></div>
                            </div>

                            <div class="px-5 py-4">
                                <div class="flex gap-3">
                                    <div class="shrink-0 w-16 h-16 rounded-lg border border-white/10 bg-[var(--charcoal)] overflow-hidden">
                                        <?php if (!empty($config['sobre_imagen'])): ?>
                                            <img src="../<?= h($config['sobre_imagen']) ?>" class="w-full h-full object-cover" id="pv-img">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-[0.45rem] text-[var(--tx-d)]">📷</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[0.6rem] text-white/80 font-medium" id="pv-nombre">Hola, soy <?= h($config['sobre_nombre'] ?: 'Hassan') ?></div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="font-playfair text-sm font-bold text-[var(--gold)]" id="pv-anios"><?= h($config['sobre_anios'] ?: '+10') ?></span>
                                            <span class="text-[0.45rem] text-white/40 uppercase tracking-[0.1em]" id="pv-anios-tx"><?= h($config['sobre_anios_texto'] ?: 'Años') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 space-y-2 text-[0.6rem] text-white/50 leading-relaxed" id="pv-textos">
                                    <p><?= nl2br(h($config['sobre_texto_1'] ?: 'Texto de presentación...')) ?></p>
                                    <p><?= nl2br(h($config['sobre_texto_2'] ?: '')) ?></p>
                                    <p><?= nl2br(h($config['sobre_texto_3'] ?: '')) ?></p>
                                </div>
                            </div>

                            <div class="px-5 py-3 border-t border-white/5 space-y-1.5 text-[0.55rem] text-white/40">
                                <div class="flex items-center gap-2"><i class="bi bi-geo-alt text-[var(--gold)] text-[0.5rem]"></i> <span id="pv-dir"><?= h($config['direccion'] ?: 'Calle Mayor 12') ?></span></div>
                                <div class="flex items-center gap-2"><i class="bi bi-instagram text-[var(--gold)] text-[0.5rem]"></i> <span id="pv-ig"><?= h($config['instagram'] ?: '@barbershop_la_h') ?></span></div>
                                <div class="flex items-center gap-2"><i class="bi bi-clock text-[var(--gold)] text-[0.5rem]"></i> <span id="pv-hor"><?= h($config['horario_resumen'] ?: 'Lun–Vie 9:00–20:00') ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile save -->
                <div class="lg:hidden mt-5">
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-[#d4af37] to-[#e8c84a] text-[#0d0d0d] font-bold py-3 rounded-xl text-[0.65rem] tracking-[0.15em] uppercase shadow-lg shadow-[#d4af37]/20 active:scale-[0.98] transition-all cursor-pointer">
                        <i class="bi bi-check2 text-[0.75rem] mr-2"></i> Guardar configuración
                    </button>
                </div>
            </div>

            <!-- ===== RIGHT: Preview panel (desktop only, sticky) ===== -->
            <div class="hidden lg:block preview-panel">
                <div class="rounded-xl border border-[var(--brd)] bg-white/[0.03] p-4 glow-card">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-[0.65rem] font-semibold" style="font-family: var(--pf);">
                            <i class="bi bi-phone text-[var(--gold)]"></i> Vista previa
                        </h3>
                        <span class="text-[0.45rem] uppercase tracking-[0.15em] text-[var(--tx-d)]">En vivo</span>
                    </div>

                    <div class="preview-mockup rounded-xl overflow-hidden shadow-2xl">
                        <div class="px-4 pt-5 pb-3 text-center border-b border-white/5">
                            <div class="font-playfair text-xs uppercase tracking-[0.2rem] text-[var(--gold)]">Barbershop La H</div>
                            <div class="text-[0.4rem] text-white/30 mt-1 uppercase tracking-[0.15em]" id="pv-d-sub"><?= h($config['sobre_subtitulo'] ?: 'Barbershop La H') ?></div>
                            <div class="font-playfair text-lg mt-1 text-white" id="pv-d-tit"><?= h($config['sobre_titulo'] ?: 'Sobre Nosotros') ?></div>
                        </div>

                        <div class="px-4 py-3">
                            <div class="flex gap-3">
                                <div class="shrink-0 w-14 h-14 rounded-lg border border-white/10 bg-[var(--charcoal)] overflow-hidden">
                                    <?php if (!empty($config['sobre_imagen'])): ?>
                                        <img src="../<?= h($config['sobre_imagen']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-[0.4rem] text-[var(--tx-d)]">📷</div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[0.55rem] text-white/80 font-medium" id="pv-d-nombre">Hola, soy <?= h($config['sobre_nombre'] ?: 'Hassan') ?></div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="font-playfair text-sm font-bold text-[var(--gold)]" id="pv-d-anios"><?= h($config['sobre_anios'] ?: '+10') ?></span>
                                        <span class="text-[0.4rem] text-white/40 uppercase tracking-[0.1em]" id="pv-d-anios-tx"><?= h($config['sobre_anios_texto'] ?: 'Años') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2.5 space-y-1.5 text-[0.55rem] text-white/50 leading-relaxed" id="pv-d-textos">
                                <p><?= nl2br(h($config['sobre_texto_1'] ?: 'Texto de presentación...')) ?></p>
                                <p><?= nl2br(h($config['sobre_texto_2'] ?: '')) ?></p>
                                <p><?= nl2br(h($config['sobre_texto_3'] ?: '')) ?></p>
                            </div>
                        </div>

                        <div class="px-4 py-2.5 border-t border-white/5 space-y-1 text-[0.5rem] text-white/40">
                            <div class="flex items-center gap-2"><i class="bi bi-geo-alt text-[var(--gold)] text-[0.45rem]"></i> <span id="pv-d-dir"><?= h($config['direccion'] ?: 'Calle Mayor 12') ?></span></div>
                            <div class="flex items-center gap-2"><i class="bi bi-instagram text-[var(--gold)] text-[0.45rem]"></i> <span id="pv-d-ig"><?= h($config['instagram'] ?: '@barbershop_la_h') ?></span></div>
                            <div class="flex items-center gap-2"><i class="bi bi-clock text-[var(--gold)] text-[0.45rem]"></i> <span id="pv-d-hor"><?= h($config['horario_resumen'] ?: 'Lun–Vie 9:00–20:00') ?></span></div>
                        </div>
                    </div>

                    <!-- Desktop save -->
                    <div class="mt-4">
                        <button type="submit"
                                class="w-full bg-gradient-to-r from-[#d4af37] to-[#e8c84a] hover:opacity-90 text-[#0d0d0d] font-bold py-2.5 rounded-lg text-[0.6rem] tracking-[0.15em] uppercase transition-all cursor-pointer shadow-lg shadow-[#d4af37]/10 active:scale-[0.99]">
                            <i class="bi bi-check2 text-[0.7rem] mr-2"></i> Guardar configuración
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>

</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

<script>
/* ─── Tab system ─── */
document.querySelectorAll('.pill-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.pill-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const target = document.getElementById('tab-' + tab);
        if (target) target.classList.add('active');
    });
});

/* ─── Live preview sync ─── */
function val(name) {
    return document.querySelector(`[name="${name}"]`)?.value || '';
}

document.querySelectorAll('input, textarea').forEach(el => {
    el.addEventListener('input', () => {
        const prefix = window.innerWidth >= 1024 ? 'pv-d-' : 'pv-';

        const setT = (id, v, fallback) => {
            const el = document.getElementById(id);
            if (el) el.textContent = v || fallback || '';
        };
        setT(prefix + 'sub', val('sobre_subtitulo'), 'Barbershop La H');
        setT(prefix + 'tit', val('sobre_titulo'), 'Sobre Nosotros');
        setT(prefix + 'nombre', 'Hola, soy ' + (val('sobre_nombre') || 'Hassan'));
        setT(prefix + 'anios', val('sobre_anios'), '+10');
        setT(prefix + 'anios-tx', val('sobre_anios_texto'), 'Años');
        setT(prefix + 'dir', val('direccion'), 'Calle Mayor 12');
        setT(prefix + 'ig', val('instagram'), '@barbershop_la_h');
        setT(prefix + 'hor', val('horario_resumen'), 'Lun–Vie 9:00–20:00');

        const container = document.getElementById(prefix + 'textos');
        if (container) {
            const ps = container.querySelectorAll('p');
            if (ps[0]) ps[0].innerHTML = val('sobre_texto_1') || 'Texto de presentación...';
            if (ps[1]) ps[1].innerHTML = val('sobre_texto_2') || '';
            if (ps[2]) ps[2].innerHTML = val('sobre_texto_3') || '';
        }

        const imgSrc = val('sobre_imagen');
        const img = document.querySelector('.preview-mockup img');
        if (img && imgSrc) img.src = '../' + imgSrc;
    });
});
</script>

</body>
</html>
