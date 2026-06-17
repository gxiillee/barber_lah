<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/clases/helpers.php';
require_once 'clases/BdMongo.php';
require_once 'clases/Galeria_corte.php';
require_once 'clases/Producto.php';
require_once 'clases/Servicio.php';
require_once 'clases/ConfigWeb.php';

$servicios = Servicio::obtenerTodos();
$galeriaCortes = Corte::obtenerActivos();
$productos = Producto::obtenerActivos();
$config = ConfigWeb::obtener();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barbershop La H — Hassan · Zaragoza</title>

    <!-- Google Fonts: Playfair Display (editorial) + Cormorant Garamond (cuerpo elegante) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS v4 — Browser CDN -->
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Estilos personalizados (animaciones, efectos, video) -->
    <link rel="stylesheet" href="public/assets/css/estilos.css">
</head>

<body class="bg-obsidian text-white font-montserrat overflow-x-hidden">


    <!-- ===================== PRELOADER ===================== -->
    <div id="preloader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-[var(--obsidian)] transition-all duration-1000">
        <div class="text-center">
            <div class="animate-preloader-logo font-playfair mb-2 text-4xl tracking-[0.6rem] text-[var(--gold)] uppercase">LA H</div>
            <div class="font-cormorant mb-10 text-xs uppercase tracking-[0.4rem] text-white/30">Barbershop · Zaragoza</div>

            <div class="mx-auto h-px w-[100px] overflow-hidden bg-[var(--gold)]/15">
                <div class="animate-bar-slide h-full w-full bg-gradient-to-r from-transparent via-[var(--gold)] to-transparent"></div>
            </div>
        </div>
    </div>


    <!-- ===================== NAVEGACIÓN ===================== -->
    <nav id="mainNav" class="bsh-nav fixed top-0 left-0 z-50 w-full transition-all duration-500">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-10 py-7 transition-all duration-500" id="navContainer">

            <!-- Logo -->
            <a href="" class="font-playfair text-base uppercase tracking-[0.3rem] text-[var(--gold)] select-none">
                Barbershop La H
            </a>

            <!-- Links desktop -->
            <div class="hidden items-center gap-10 md:flex">
                <a href="#sobre"       class="font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-white/50 transition-colors duration-300 hover:text-[var(--gold)]">Nosotros</a>
                <a href="#servicios"   class="font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-white/50 transition-colors duration-300 hover:text-[var(--gold)]">Servicios</a>
                <a href="#contacto"    class="font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-white/50 transition-colors duration-300 hover:text-[var(--gold)]">Encuéntranos</a>
                <!-- Nueva entrada independiente: el area cliente no interrumpe ni sustituye el flujo publico de reserva. -->
                <a href="mi-cuenta.php" class="font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-white/45 transition-colors duration-300 hover:text-[var(--gold)]">Mi cuenta</a>
                <a href="cliente/reserva.php" class="border border-[var(--gold)]/50 px-6 py-2 font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-[var(--gold)] transition-all duration-300 hover:bg-[var(--gold)] hover:text-[var(--obsidian)]">
                    Reservar Cita
                </a>
            </div>

            <!-- Hamburger mobile -->
            <button id="menuToggle" class="flex flex-col gap-[5px] p-2 md:hidden" aria-label="Menú">
                <span class="block h-px w-6 bg-[var(--gold)] transition-all duration-300"></span>
                <span class="block h-px w-4 bg-[var(--gold)]/60 transition-all duration-300"></span>
                <span class="block h-px w-6 bg-[var(--gold)] transition-all duration-300"></span>
            </button>
        </div>
    </nav>
    <!-- Menu móvil backdrop -->
    <div id="menuBackdrop" class="fixed inset-0 z-[55] bg-black/60 backdrop-blur-sm hidden" onclick="cerrarMenuMobile()"></div>
    <!-- Seccion para solo móvil -->
    <div id="mobileMenu" style="display:none;" class="fixed inset-0 z-[60] bg-gradient-to-b from-[var(--obsidian)] via-[var(--charcoal)] to-[var(--obsidian)]">
        <!-- Header: logo + cerrar -->
        <div class="flex items-center justify-between px-8 pt-10">
            <span class="font-playfair text-lg uppercase tracking-[0.3rem] text-[var(--gold)]">LA H</span>
            <button onclick="cerrarMenuMobile()" class="text-white/30 hover:text-[var(--gold)] transition-colors duration-300 text-2xl leading-none" aria-label="Cerrar menú">✕</button>
        </div>

        <!-- Enlaces de navegación centrados -->
        <div class="flex-1 flex flex-col items-center justify-center gap-12 px-8 pb-4">
            <a href="#sobre" onclick="cerrarMenuMobile()" class="menu-link font-montserrat text-sm uppercase tracking-[0.3rem] text-white/60 hover:text-[var(--gold)] flex items-center gap-3">
                <i class="bi bi-shop"></i> Nosotros
            </a>
            <a href="#servicios" onclick="cerrarMenuMobile()" class="menu-link font-montserrat text-sm uppercase tracking-[0.3rem] text-white/60 hover:text-[var(--gold)] flex items-center gap-3">
                <i class="bi bi-scissors"></i> Servicios
            </a>
            <a href="#contacto" onclick="cerrarMenuMobile()" class="menu-link font-montserrat text-sm uppercase tracking-[0.3rem] text-white/60 hover:text-[var(--gold)] flex items-center gap-3">
                <i class="bi bi-geo-alt"></i> Encuéntranos
            </a>
            <a href="mi-cuenta.php" onclick="cerrarMenuMobile()" class="menu-link font-montserrat text-sm uppercase tracking-[0.3rem] text-white/60 hover:text-[var(--gold)] flex items-center gap-3">
                <i class="bi bi-person"></i> Mi cuenta
            </a>
        </div>

        <!-- Botón reserva destacado -->
        <div class="px-8 pb-12">
            <a href="cliente/reserva.php" onclick="cerrarMenuMobile()" class="block w-full text-center bg-[var(--gold)] text-[var(--obsidian)] font-montserrat text-sm font-bold uppercase tracking-[0.2rem] py-4 rounded-xl hover:bg-[var(--gold-light)] transition-all duration-300 shadow-lg shadow-[var(--gold)]/20">
                Reservar Cita
            </a>
        </div>
    </div>


    <!-- ===================== SECCIÓN VIDEO SCROLL ===================== -->
    <section id="experiencia" class="relative w-full h-[350vh] max-md:h-[250vh]">

        <div class="sticky top-0 h-screen w-full overflow-hidden bg-black">

            <!-- Video principal -->
            <video id="mainVideo"
                   class="absolute min-w-full min-h-full w-auto h-auto object-cover"
                   style="top: 50%; left: 50%; transform: translate(-50%, -50%); will-change: transform;"
                   playsinline muted preload="metadata"
                   poster="public/assets/img/poster_video.jpg">
                <source src="public/assets/video/video_intro_web.mp4" type="video/mp4">
            </video>

            <!-- Degradado viñeta cinematográfico -->
            <div class="video-vignette absolute inset-0 z-[5] pointer-events-none"></div>

            <!-- Overlay de transición (fade a negro al final) -->
            <div id="transitionOverlay"
                 class="absolute inset-0 z-[25] pointer-events-none bg-[var(--obsidian)] opacity-0">
            </div>


            <!-- ── TÍTULO INTRO ── -->
            <div id="introBlock" class="absolute inset-0 z-[15] flex items-center justify-center pointer-events-none">
                <div class="text-center px-8">
                    <p id="introTagline" class="font-cormorant mb-6 text-xs uppercase tracking-[0.5rem] text-[var(--gold)]/60 max-md:hidden">
                        Una experiencia única
                    </p>
                     <h1 id="introTitle" class="intro-title-gradient font-playfair text-[clamp(2.8rem,10vw,7rem)] leading-none tracking-[0.4rem] uppercase">
                        BARBER LA H
                    </h1>
                    <p id="introCity" class="font-cormorant mt-6 text-sm uppercase tracking-[0.5rem] text-white/25 max-md:hidden">
                        Barbershop La H · Zaragoza
                    </p>
                </div>
            </div>


            <!-- ── ETIQUETAS FLOTANTES ── -->

            <!-- Label 1 -->
            <div id="label1" class="float-label absolute z-[15] pointer-events-none
     left-[8%] top-[22%]
     max-md:left-1/2 max-md:right-auto max-md:-translate-x-1/2 max-md:text-center max-md:w-[85vw] max-md:top-[18%] max-md:px-4 max-md:py-3">
                <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.4rem] text-[var(--gold)]/40 max-md:text-[0.45rem]">01</span>
                <h3 class="font-playfair mt-1.5 mb-2 text-3xl leading-tight text-[var(--gold)] drop-shadow-[0_2px_12px_rgba(212,175,55,0.4)] max-md:text-xl max-md:mt-1 max-md:mb-1.5">El Corte</h3>
                <p class="font-cormorant max-w-[260px] text-base leading-relaxed text-white/55 max-md:mx-auto max-md:text-sm max-md:leading-snug">
                    Precisión milimétrica en cada línea.<br>
                    La geometría que define tu estilo.
                </p>
            </div>

            <!-- Label 2 -->
            <div id="label2" class="float-label absolute z-[15] pointer-events-none text-right
     right-[8%] top-[38%]
     max-md:left-1/2 max-md:right-auto max-md:-translate-x-1/2 max-md:text-center max-md:w-[85vw] max-md:top-[38%] max-md:px-4 max-md:py-3">
                <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.4rem] text-[var(--gold)]/40 max-md:text-[0.45rem]">02</span>
                <h3 class="font-playfair mt-1.5 mb-2 text-3xl leading-tight text-[var(--gold)] drop-shadow-[0_2px_12px_rgba(212,175,55,0.4)] max-md:text-xl max-md:mt-1 max-md:mb-1.5">El Ambiente</h3>
                <p class="font-cormorant ml-auto max-w-[260px] text-base leading-relaxed text-white/55 max-md:mx-auto max-md:text-sm max-md:leading-snug">
                    Un refugio de elegancia para el<br>
                    caballero que sabe lo que quiere.
                </p>
            </div>

            <!-- Label 3 -->
            <div id="label3" class="float-label absolute z-[15] pointer-events-none
     left-[10%] top-[60%]
     max-md:left-1/2 max-md:right-auto max-md:-translate-x-1/2 max-md:text-center max-md:w-[85vw] max-md:top-[62%] max-md:px-4 max-md:py-3">
                <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.4rem] text-[var(--gold)]/40 max-md:text-[0.45rem]">03</span>
                <h3 class="font-playfair mt-1.5 mb-2 text-3xl leading-tight text-[var(--gold)] drop-shadow-[0_2px_12px_rgba(212,175,55,0.4)] max-md:text-xl max-md:mt-1 max-md:mb-1.5">El Acabado</h3>
                <p class="font-cormorant max-w-[260px] text-base leading-relaxed text-white/55 max-md:mx-auto max-md:text-sm max-md:leading-snug">
                    Productos de alta gama para un<br>
                    resultado impecable que dura días.
                </p>
            </div>


            <!-- ── BARRA DE PROGRESO INFERIOR ── -->
            <div class="absolute bottom-8 left-12 right-12 z-[30] max-md:bottom-6 max-md:left-6 max-md:right-6">
                <div class="relative h-[2px] overflow-hidden rounded-full bg-white/10">
                    <div id="progressFill" class="absolute top-0 left-0 h-full bg-[var(--gold)] rounded-full" style="width:0%;"></div>
                </div>
            </div>

            <!-- ── INDICADOR SCROLL INICIAL ── -->
            <div id="scrollHint" class="absolute bottom-24 left-1/2 z-[30] -translate-x-1/2 text-center pointer-events-none transition-opacity duration-500">
                <div class="scroll-mouse mx-auto max-md:hidden"></div>
                <div class="md:hidden mx-auto flex flex-col items-center gap-2">
                    <i class="bi bi-chevron-double-down text-[var(--gold)]/70 animate-bounce text-2xl drop-shadow-[0_0_8px_rgba(212,175,55,0.3)]"></i>
                    <span class="font-montserrat block text-[0.6rem] uppercase tracking-[0.3rem] text-white/40">Desliza</span>
                </div>
                <span class="font-montserrat mt-3 block text-[0.55rem] uppercase tracking-[0.35rem] text-white/25 max-md:hidden">Scroll</span>
            </div>

        </div>
    </section>


    <!-- ===================== SECCIÓN SOBRE ===================== -->
    <section id="sobre" class="relative overflow-hidden bg-[var(--charcoal)] pt-28 pb-32">

        <div class="absolute top-0 left-1/2 w-px -translate-x-1/2 bg-[var(--gold)] opacity-10 h-[80px]"></div>

        <div class="mx-auto max-w-6xl px-8">

            <header class="reveal-up mb-24 text-center">
                <span class="font-montserrat text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70"><?= h($config['sobre_subtitulo'] ?? 'Barbershop La H') ?></span>
                <h2 class="font-playfair mt-5 mb-5 text-5xl md:text-6xl leading-tight text-white"><?= h($config['sobre_titulo'] ?? 'Sobre Nosotros') ?></h2>
                <div class="mx-auto h-px w-12 bg-[var(--gold)]"></div>
            </header>

            <div class="grid items-center gap-16 md:grid-cols-2 lg:gap-24">

                <div class="reveal-left">
                    <div class="relative">
                        <img src="<?= h($config['sobre_imagen'] ?? 'public/assets/img/logo.jpg') ?>"
                             alt="<?= h($config['sobre_nombre'] ?? 'Hassan') ?>"
                             class="block h-[280px] md:h-[500px] w-full border-[3px] border-[var(--gold)] bg-[var(--obsidian)] object-cover shadow-[20px_20px_0_#2a2a2a] transition-transform duration-[1200ms] ease-out hover:scale-105">

                        <div class="pointer-events-none absolute top-5 left-5 right-[-16px] bottom-[-16px] border border-[var(--gold)] opacity-20"></div>

                        <div class="absolute right-[-10px] bottom-[-20px] bg-[var(--obsidian)] px-6 py-4 text-center md:right-[-20px] border border-[var(--gold)]/30 shadow-xl z-20">
                            <span class="font-playfair block text-3xl leading-none text-[var(--gold)] font-bold"><?= h($config['sobre_anios'] ?? '+10') ?></span>
                            <span class="font-montserrat mt-1 block text-[0.55rem] uppercase tracking-[0.2rem] text-white/50"><?= h($config['sobre_anios_texto'] ?? 'Años de exp.') ?></span>
                        </div>
                    </div>
                </div>

                <div class="reveal-right mt-10 md:mt-0">
                    <h3 class="font-playfair mb-8 text-3xl md:text-4xl leading-tight text-white">
                        Hola, soy <em class="not-italic text-[var(--gold)]"><?= h($config['sobre_nombre'] ?? 'Hassan') ?></em>
                    </h3>

                    <div class="font-cormorant mb-10 text-lg leading-relaxed text-white/60">
                        <?php if (!empty($config['sobre_texto_1'])): ?>
                            <p class="mb-6"><?= h($config['sobre_texto_1']) ?></p>
                        <?php else: ?>
                            <p class="mb-6">
                                Con más de <strong class="font-normal text-white">10 años de experiencia</strong> en el mundo de la barbería,
                                me he convertido en mucho más que un barbero: soy tu aliado para
                                encontrar el estilo que te representa.
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($config['sobre_texto_2'])): ?>
                            <p class="mb-6"><?= h($config['sobre_texto_2']) ?></p>
                        <?php else: ?>
                            <p class="mb-6">
                                En <strong class="font-normal text-white">Barbershop La H</strong> combinamos técnica clásica
                                con las tendencias más modernas para crear un look único que
                                se adapte a tu personalidad y estilo de vida.
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($config['sobre_texto_3'])): ?>
                            <p><?= h($config['sobre_texto_3']) ?></p>
                        <?php else: ?>
                            <p>
                                Nuestra barbería es un espacio de confianza donde el tiempo
                                se detiene y cada cliente recibe una atención completamente personalizada.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="mb-12 flex flex-wrap items-center gap-6">
                        <a href="cliente/reserva.php" class="bg-[var(--gold)] px-8 py-4 font-montserrat text-[0.72rem] font-bold uppercase tracking-[0.2rem] text-black transition-all hover:bg-white hover:-translate-y-1">
                            Reservar Cita
                        </a>
                        <a href="https://instagram.com/<?= h(ltrim($config['instagram'] ?? '@barbershop_la_h', '@')) ?>" target="_blank" rel="noopener"
                           class="font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-white/30 transition-colors duration-300 hover:text-[var(--gold)]">
                            <?= h($config['instagram'] ?? '@barbershop_la_h') ?> ↗
                        </a>
                    </div>

                    <footer class="mt-12 border-t border-[var(--gold)]/20 pt-8">

                        <p class="font-playfair text-2xl italic text-[var(--gold)]">
                            — <?= h($config['sobre_nombre'] ?? 'Hassan') ?>
                        </p>

                        <p class="font-montserrat mt-2 text-[0.6rem] uppercase tracking-[0.2rem] text-white/60">
                            <a href="#contacto">Encuentranos en el mapa</a>
                        </p>
                    </footer>
                </div>
            </div>
        </div>
    </section>


    <!-- ══════════════════════════════════════════════════════════════
     SECCIÓN SERVICIOS
     Pegar después de la sección #sobre, antes del footer
     ══════════════════════════════════════════════════════════════ -->

    <section id="servicios" class="relative overflow-hidden bg-[var(--obsidian)] py-32">

        <div class="absolute top-0 left-1/2 h-[80px] w-px -translate-x-1/2 bg-[var(--gold)] opacity-10"></div>

        <div class="pointer-events-none absolute inset-0 opacity-[0.025]"
             style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'n\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/%3E%3C/svg%3E');
          background-size: 200px 200px;"></div>

        <div class="mx-auto max-w-7xl px-8">

            <header class="reveal-text mb-20 text-center">
                <span class="font-montserrat text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70">Lo que hacemos</span>
                <h2 class="font-playfair mt-5 mb-5 text-5xl md:text-6xl leading-tight text-white">Nuestros Servicios</h2>
                <div class="mx-auto h-px w-12 bg-[var(--gold)]"></div>
                <p class="font-cormorant mx-auto mt-8 max-w-xl text-xl leading-relaxed text-white/45">
                    Cada servicio es una experiencia pensada al detalle.<br>
                    Técnica depurada, productos de calidad y tiempo para ti.
                </p>
            </header>

            <!-- Wrapper con fade derecho en móvil -->
            <div class="relative">

                <!-- Gradiente "peek" — solo móvil -->
                <div class="pointer-events-none absolute right-0 top-0 h-full w-16 z-10
                        bg-gradient-to-l from-[var(--obsidian)] to-transparent md:hidden"></div>

                <!-- Grid / carril -->
                <div class="flex overflow-x-auto snap-x snap-mandatory gap-px
                        md:grid md:overflow-visible md:snap-none md:grid-cols-2 lg:grid-cols-3"
                     style="background-color: rgba(212,175,55,0.08); scrollbar-width: none;">

                    <?php
                    $numero = 1;
                    foreach ($servicios as $servicio) { ?>

                        <article class="reveal-text group relative flex flex-col justify-between bg-[var(--obsidian)] p-10
                                    transition-colors duration-500 hover:bg-[var(--charcoal)]
                                    shrink-0 w-[72vw] snap-start md:w-auto"
                                 style="transition-delay: <?= ($numero - 1) * 0.08 ?>s;">

                        <span class="font-playfair absolute top-8 right-10 text-[4rem] leading-none font-bold
                                     text-[var(--gold)] opacity-5 select-none transition-opacity duration-500
                                     group-hover:opacity-10">
                            <?= str_pad($numero, 2, '0', STR_PAD_LEFT) ?>
                        </span>

                            <div>
                                <div class="mb-6 flex items-center gap-3">
                                    <div class="h-px w-6 bg-[var(--gold)] opacity-50"></div>
                                    <span class="font-montserrat text-[0.55rem] uppercase tracking-[0.3rem] text-[var(--gold)]/50">
                                    <?= htmlspecialchars($servicio->getDuracion()) ?>
                                </span>
                                </div>

                                <h3 class="font-playfair mb-4 text-2xl leading-tight text-white
                                       transition-colors duration-300 group-hover:text-[var(--gold)]">
                                    <?= htmlspecialchars($servicio->getNombre()) ?>
                                </h3>

                                <p class="font-cormorant text-base leading-relaxed text-white/60
                                      transition-colors duration-300 group-hover:text-white/60">
                                    <?= htmlspecialchars($servicio->getDescripcion()) ?>
                                </p>
                            </div>

                            <footer class="mt-10 flex items-end justify-between border-t border-[var(--gold)]/10 pt-8">
                                <div>
                                    <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.2rem] text-white/20 mb-1">Precio</span>
                                    <span class="font-playfair text-3xl font-bold text-[var(--gold)]">
                                    <?= number_format($servicio->getPrecio(), 0) ?>€
                                </span>
                                </div>
                                <a href="cliente/reserva.php?servicio=<?= $servicio->getIdServicio() ?>"
                                   class="font-montserrat text-[0.6rem] uppercase tracking-[0.2rem] text-white/25
                                      border-b border-transparent transition-all duration-300
                                      group-hover:text-[var(--gold)] group-hover:border-[var(--gold)]/40">
                                    Reservar →
                                </a>
                            </footer>

                        </article>

                        <?php $numero++; } ?>

                </div><!-- /grid -->

            </div><!-- /wrapper -->

            <p class="reveal-text font-cormorant mt-12 text-center text-base text-white/25 italic">
                ¿Tienes dudas? Escríbenos en Instagram
                <a href="https://instagram.com/<?= h(ltrim($config['instagram'] ?? '@barbershop_la_h', '@')) ?>" target="_blank" rel="noopener"
                   class="text-[var(--gold)]/50 transition-colors hover:text-[var(--gold)] not-italic ml-1">
                    <?= h($config['instagram'] ?? '@barbershop_la_h') ?> ↗
                </a>
            </p>

        </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════
     SECCIÓN GALERÍA — Carrusel de cortes
     Pegar después de #servicios, antes del footer
     ══════════════════════════════════════════════════════════════ -->

    <section id="galeria" class="relative overflow-hidden bg-[var(--charcoal)] py-32">

        <!-- Línea decorativa superior -->
        <div class="absolute top-0 left-1/2 h-[80px] w-px -translate-x-1/2 bg-[var(--gold)] opacity-10"></div>

        <div class="mx-auto max-w-7xl px-8">

            <!-- Cabecera -->
            <header class="reveal-text mb-20 text-center">
                <span class="font-montserrat text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70">Portfolio</span>
                <h2 class="font-playfair mt-5 mb-5 text-5xl md:text-6xl leading-tight text-white">Nuestro Trabajo</h2>
                <div class="mx-auto h-px w-12 bg-[var(--gold)]"></div>
                <p class="font-cormorant mx-auto mt-8 max-w-xl text-xl leading-relaxed text-white/45">
                    Cada corte es una firma. Cada cliente, un lienzo.
                </p>
            </header>

        </div>

        <!-- Carrusel — ancho completo -->
        <div class="relative" id="galeriaCarrusel"
             data-autoplay="true"
             data-interval="4000">

            <!-- Track deslizante -->
            <div id="galeriaTrack"
                 class="flex transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]"
                 style="will-change: transform;">

                <?php foreach ($galeriaCortes as $i => $foto) { ?>
                    <div class="galeria-slide relative shrink-0 cursor-pointer select-none overflow-hidden rounded-2xl shadow-xl shadow-black/30"
                          style="margin-right: 2px;"
                         data-index="<?= $i ?>"
                         data-src="<?= htmlspecialchars($foto['imagen']) ?>"
                         data-categoria="<?= htmlspecialchars($foto['categoria']) ?>"
                         data-descripcion="<?= htmlspecialchars($foto['descripcion']) ?>">

                        <!-- Imagen -->
                        <img src="<?= htmlspecialchars($foto['imagen']) ?>"
                             alt="<?= htmlspecialchars($foto['categoria']) ?>"
                             class="block h-[480px] w-full object-cover transition-transform duration-700 ease-out rounded-2xl max-md:h-[300px]"
                             loading="lazy">

                        <!-- Overlay info (aparece en hover vía CSS) -->
                        <div class="galeria-overlay absolute inset-0 flex flex-col justify-end p-8
                            bg-gradient-to-t from-black/80 via-black/20 to-transparent
                            opacity-0 transition-opacity duration-500">
                    <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.4rem] text-[var(--gold)]/70 mb-2">
                        <?= htmlspecialchars($foto['categoria']) ?>
                    </span>
                            <p class="font-cormorant text-lg text-white/80">
                                <?= htmlspecialchars($foto['descripcion']) ?>
                            </p>
                            <span class="font-montserrat mt-4 block text-[0.5rem] uppercase tracking-[0.3rem] text-white/30 galeria-open-lightbox">
                        Clic para ampliar
                    </span>
                        </div>

                        <!-- Borde dorado sutil -->
                        <div class="pointer-events-none absolute inset-0 border border-[var(--gold)]/0 transition-all duration-500 galeria-border"></div>

                    </div>
                <?php } ?>

            </div><!-- /track -->

            <!-- Flecha izquierda -->
            <button id="galeriaPrev"
                    class="absolute left-6 top-1/2 z-10 -translate-y-1/2
                       flex h-12 w-12 items-center justify-center
                       border border-[var(--gold)]/30 bg-black/60 backdrop-blur-sm
                       text-[var(--gold)] transition-all duration-300
                       hover:bg-[var(--gold)] hover:text-black"
                    aria-label="Anterior">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                </svg>
            </button>

            <!-- Flecha derecha -->
            <button id="galeriaNext"
                    class="absolute right-6 top-1/2 z-10 -translate-y-1/2
                       flex h-12 w-12 items-center justify-center
                       border border-[var(--gold)]/30 bg-black/60 backdrop-blur-sm
                       text-[var(--gold)] transition-all duration-300
                       hover:bg-[var(--gold)] hover:text-black"
                    aria-label="Siguiente">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
            </button>

            <!-- Puntos indicadores -->
            <div class="mt-10 flex justify-center gap-3" id="galeriaDots">
                <?php foreach ($galeriaCortes as $i => $foto) { ?>
                    <button class="galeria-dot h-2.5 w-2.5 rounded-full bg-white/20 transition-all duration-300 hover:bg-white/40"
                            data-index="<?= $i ?>"
                            aria-label="Ir a imagen <?= $i + 1 ?>"></button>
                <?php } ?>
            </div>

        </div><!-- /carrusel -->

    </section>

    <!-- ══════════════════════════════════════════════════════════════
     SECCIÓN RESEÑAS — Google Reviews
     ══════════════════════════════════════════════════════════════ -->

    <section id="resenas" class="relative overflow-hidden bg-[var(--charcoal)] py-32">

        <div class="absolute top-0 left-1/2 h-[80px] w-px -translate-x-1/2 bg-[var(--gold)] opacity-10"></div>

        <div class="mx-auto max-w-6xl px-8">

            <header class="reveal-text mb-16 text-center">
                <span class="font-montserrat text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70">Testimonios</span>
                <h2 class="font-playfair mt-5 mb-5 text-4xl md:text-5xl leading-tight text-white">Lo que dicen nuestros clientes</h2>
                <div class="mx-auto h-px w-12 bg-[var(--gold)]"></div>
            </header>

            <div class="relative" id="resenasCarrusel">

                <div class="flex gap-6 md:gap-8 reviews-scroll" id="resenasTrack"
                     style="overflow-x: auto; overflow-y: hidden; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scroll-padding: 0 2rem;">

                <div class="review-card reveal-up group relative flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-8 transition-all duration-500 hover:border-[var(--gold)]/30 hover:bg-white/[0.06] shrink-0"
                     style="width: clamp(280px, 78vw, 400px); min-height: 17rem; scroll-snap-align: center;">
                    <div class="flex justify-center gap-1 mb-5 text-lg text-[var(--gold)]">★★★★★</div>
                    <p class="font-cormorant text-lg italic leading-relaxed text-white/70 text-center flex-1">
                        "Aparte de un buen corte con calidad, muy buen trato con el cliente y muy buen ambiente"
                    </p>
                    <div class="mt-6 flex items-center justify-center flex-wrap gap-x-4">
                        <span class="font-montserrat text-xs uppercase tracking-[0.15rem] text-white/40">— Lorién Galindo Embid</span>
                        <a href="https://maps.app.goo.gl/YNokG5wohqesHasp6" target="_blank" class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-[var(--gold)]/50 transition-colors duration-300 hover:text-[var(--gold)]">Google →</a>
                    </div>
                </div>

                <div class="review-card reveal-up group relative flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-8 transition-all duration-500 hover:border-[var(--gold)]/30 hover:bg-white/[0.06] shrink-0"
                     style="width: clamp(280px, 78vw, 400px); min-height: 17rem; scroll-snap-align: center;">
                    <div class="flex justify-center gap-1 mb-5 text-lg text-[var(--gold)]">★★★★★</div>
                    <p class="font-cormorant text-lg italic leading-relaxed text-white/70 text-center flex-1">
                        "peluqueria perfecta por si quieres meterla en kenbo , recomendable 100% buenos degradados y precio ideal . 10/10"
                    </p>
                    <div class="mt-6 flex items-center justify-center flex-wrap gap-x-4">
                        <span class="font-montserrat text-xs uppercase tracking-[0.15rem] text-white/40">— Miguel Sucunza</span>
                        <a href="https://maps.app.goo.gl/gvsoUaZfFnmz1sWQ6" target="_blank" class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-[var(--gold)]/50 transition-colors duration-300 hover:text-[var(--gold)]">Google →</a>
                    </div>
                </div>

                <div class="review-card reveal-up group relative flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-8 transition-all duration-500 hover:border-[var(--gold)]/30 hover:bg-white/[0.06] shrink-0"
                     style="width: clamp(280px, 78vw, 400px); min-height: 17rem; scroll-snap-align: center;">
                    <div class="flex justify-center gap-1 mb-5 text-lg text-[var(--gold)]">★★★★★</div>
                    <p class="font-cormorant text-lg italic leading-relaxed text-white/70 text-center flex-1">
                        "Primera vez y quedé muy conforme. Fue muy amable y detallista con barba y cejas. Volveré, lo recomiendo."
                    </p>
                    <div class="mt-6 flex items-center justify-center flex-wrap gap-x-4">
                        <span class="font-montserrat text-xs uppercase tracking-[0.15rem] text-white/40">— Nehuén Agurto</span>
                        <a href="https://maps.app.goo.gl/a7WLJ1K5cQhnEdWY7" target="_blank" class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-[var(--gold)]/50 transition-colors duration-300 hover:text-[var(--gold)]">Google →</a>
                    </div>
                </div>

                <!-- Fila 2 -->
                <div class="review-card reveal-up group relative flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-8 transition-all duration-500 hover:border-[var(--gold)]/30 hover:bg-white/[0.06] shrink-0"
                     style="width: clamp(280px, 78vw, 400px); min-height: 17rem; scroll-snap-align: center;">
                    <div class="flex justify-center gap-1 mb-5 text-lg text-[var(--gold)]">★★★★★</div>
                    <p class="font-cormorant text-lg italic leading-relaxed text-white/70 text-center flex-1">
                        "¡Fue una experiencia muy buena! Corte de pelo perfecto y ambiente genial."
                    </p>
                    <div class="mt-6 flex items-center justify-center flex-wrap gap-x-4">
                        <span class="font-montserrat text-xs uppercase tracking-[0.15rem] text-white/40">— Abel Soto</span>
                        <a href="https://maps.app.goo.gl/wB71nNH46YWfXWuK6" target="_blank" class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-[var(--gold)]/50 transition-colors duration-300 hover:text-[var(--gold)]">Google →</a>
                    </div>
                </div>

                <div class="review-card reveal-up group relative flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-8 transition-all duration-500 hover:border-[var(--gold)]/30 hover:bg-white/[0.06] shrink-0"
                     style="width: clamp(280px, 78vw, 400px); min-height: 17rem; scroll-snap-align: center;">
                    <div class="flex justify-center gap-1 mb-5 text-lg text-[var(--gold)]">★★★★★</div>
                    <p class="font-cormorant text-lg italic leading-relaxed text-white/70 text-center flex-1">
                        "Muy contento con el corte moderno y elegante. Me atendieron genial, rápido y agradables. Precio imbatible, volveré."
                    </p>
                    <div class="mt-6 flex items-center justify-center flex-wrap gap-x-4">
                        <span class="font-montserrat text-xs uppercase tracking-[0.15rem] text-white/40">— Liam Shadownight</span>
                        <a href="https://maps.app.goo.gl/3G9PWi4RY1Xdykoz7" target="_blank" class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-[var(--gold)]/50 transition-colors duration-300 hover:text-[var(--gold)]">Google →</a>
                    </div>
                </div>

                <div class="review-card reveal-up group relative flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-8 transition-all duration-500 hover:border-[var(--gold)]/30 hover:bg-white/[0.06] shrink-0"
                     style="width: clamp(280px, 78vw, 400px); min-height: 17rem; scroll-snap-align: center;">
                    <div class="flex justify-center gap-1 mb-5 text-lg text-[var(--gold)]">★★★★★</div>
                    <p class="font-cormorant text-lg italic leading-relaxed text-white/70 text-center flex-1">
                        "El dueño es profesional y afable, hace su trabajo perfectamente sin posturear. Precios muy competitivos. Ya tengo barbero de confianza."
                    </p>
                    <div class="mt-6 flex items-center justify-center flex-wrap gap-x-4">
                        <span class="font-montserrat text-xs uppercase tracking-[0.15rem] text-white/40">— Alejandro Montero</span>
                        <a href="https://maps.app.goo.gl/onjni3Zh5kz7qTi47" target="_blank" class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-[var(--gold)]/50 transition-colors duration-300 hover:text-[var(--gold)]">Google →</a>
                    </div>
                </div>

            </div><!-- /track -->

            <!-- Flechas -->
            <button id="resenasPrev" class="absolute left-0 top-1/2 z-10 -translate-y-1/2 flex h-10 w-10 items-center justify-center border border-[var(--gold)]/30 bg-black/50 backdrop-blur-sm text-[var(--gold)] transition-all duration-300 hover:bg-[var(--gold)] hover:text-black" aria-label="Anterior">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            </button>
            <button id="resenasNext" class="absolute right-0 top-1/2 z-10 -translate-y-1/2 flex h-10 w-10 items-center justify-center border border-[var(--gold)]/30 bg-black/50 backdrop-blur-sm text-[var(--gold)] transition-all duration-300 hover:bg-[var(--gold)] hover:text-black" aria-label="Siguiente">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
            </button>

        </div><!-- /carrusel -->

            <div class="mt-14 text-center reveal-text">
                <a href="https://www.google.com/search?num=10&hl=es-ES&gl=es&tbm=lcl&sxsrf=ANbL-n5jwhep31gT2GMubHKjucIvhWKc7g:1781692516476&q=Barbershop+La+H+Rese%C3%B1as#rlfi=hd:;si:9266299433013010019,l,ChhCYXJiZXJzaG9wIExhIEggUmVzZcOxYXMiAjgBkgELYmFyYmVyX3Nob3A,y,a0T6sc5qPVw;mv:[[41.647828477319024,-0.8743664457266237],[41.64746852268096,-0.8748481542733764]]" target="_blank" class="inline-flex items-center gap-3 border border-[var(--gold)]/40 px-8 py-4 font-montserrat text-[0.65rem] uppercase tracking-[0.25rem] text-[var(--gold)] transition-all duration-500 hover:bg-[var(--gold)] hover:text-[var(--obsidian)] group">
                    Ver todas en Google
                    <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
            </div>

        </div>

    </section>

    <!-- ══════════════════════════════════════════════════════════════
     SECCIÓN PRODUCTOS
     Pegar después de #galeria, antes del footer
     ══════════════════════════════════════════════════════════════ -->

    <section id="productos" class="relative overflow-hidden bg-[var(--obsidian)] py-32">

        <!-- Línea decorativa superior -->
        <div class="absolute top-0 left-1/2 h-[80px] w-px -translate-x-1/2 bg-[var(--gold)] opacity-10"></div>

        <!-- Ruido de fondo igual que #servicios -->
        <div class="pointer-events-none absolute inset-0 opacity-[0.025]"
             style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'n\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/%3E%3C/svg%3E');
          background-size: 200px 200px;"></div>

        <div class="mx-auto max-w-7xl px-8">

            <!-- Cabecera -->
            <header class="reveal-text mb-20 text-center">
                <span class="font-montserrat text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70">Tienda</span>
                <h2 class="font-playfair mt-5 mb-5 text-5xl md:text-6xl leading-tight text-white">Productos</h2>
                <div class="mx-auto h-px w-12 bg-[var(--gold)]"></div>
                <p class="font-cormorant mx-auto mt-8 max-w-xl text-xl leading-relaxed text-white/45">
                    Los productos que usamos en cada servicio.<br>
                    Disponibles directamente en la barbería.
                </p>
            </header>

            <!-- Grid de productos -->
            <div class="grid gap-px grid-cols-2 lg:grid-cols-3"
                 style="background-color: rgba(212,175,55,0.06);">

                <?php foreach ($productos as $i => $prod) { ?>
                    <article class="reveal-text producto-card group relative bg-[var(--obsidian)] overflow-hidden
                            transition-colors duration-500 hover:bg-[var(--charcoal)]"
                             style="transition-delay: <?= $i * 0.07 ?>s;">

                        <!-- Imagen con overlay dorado en hover -->
                        <div class="relative overflow-hidden h-[140px] md:h-[260px]">
                            <img src="<?= htmlspecialchars($prod['imagen']) ?>"
                                 alt="<?= htmlspecialchars($prod['nombre']) ?>"
                                 class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                                 loading="lazy">

                            <!-- Overlay dorado muy sutil -->
                            <div class="absolute inset-0 bg-[var(--gold)] opacity-0 mix-blend-overlay
                                transition-opacity duration-500 group-hover:opacity-10"></div>
                        </div>

                        <!-- Info -->
                        <div class="p-4 md:p-8">

                            <!-- Número decorativo de fondo -->
                            <span class="pointer-events-none absolute top-4 right-6 font-playfair text-[5rem]
                                 leading-none font-bold text-[var(--gold)] opacity-[0.04] select-none
                                 transition-opacity duration-500 group-hover:opacity-[0.08]">
                        <?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?>
                    </span>

                            <h3 class="font-playfair mb-3 text-2xl text-white transition-colors duration-300
                               group-hover:text-[var(--gold)]">
                                <?= htmlspecialchars($prod['nombre']) ?>
                            </h3>

                            <p class="font-cormorant mb-8 text-base leading-relaxed text-white/55
                              transition-colors duration-300 group-hover:text-white/70">
                                <?= htmlspecialchars($prod['descripcion']) ?>
                            </p>

                            <footer class="border-t border-[var(--gold)]/10 pt-6">
                                <div>
                                    <span class="font-montserrat block text-[0.5rem] uppercase tracking-[0.2rem] text-white/20 mb-1">Precio</span>
                                    <span class="font-playfair text-3xl font-bold text-[var(--gold)]">
                                <?= $prod['precio'] ?>€
                            </span>
                                </div>
                            </footer>

                        </div>
                    </article>
                <?php } ?>

            </div>

            <!-- Nota inferior -->
            <p class="reveal-text font-cormorant mt-12 text-center text-base text-white/25 italic">
                Pásate por la barbería o escríbenos en
                <a href="https://instagram.com/<?= h(ltrim($config['instagram'] ?? '@barbershop_la_h', '@')) ?>" target="_blank" rel="noopener"
                   class="text-[var(--gold)]/50 transition-colors hover:text-[var(--gold)] not-italic ml-1">
                    <?= h($config['instagram'] ?? '@barbershop_la_h') ?> ↗
                </a>
            </p>

        </div>
    </section>


    <!-- ══════════════════════════════════════════════════════════════
     FOOTER — CONTACTO + MAPA
     Pegarlo justo después de la sección #servicios
     ══════════════════════════════════════════════════════════════ -->

    <footer id="contacto" class="relative overflow-hidden bg-[var(--charcoal)]">

        <!-- Línea decorativa superior -->
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-[var(--gold)]/30 to-transparent"></div>

        <!-- Bloque principal: info + mapa -->
        <div class="grid min-h-[600px] lg:grid-cols-2">

            <!-- ── Columna izquierda: información ── -->
            <div class="flex flex-col justify-between py-20 px-12 md:px-16 lg:pl-[12%] lg:pr-12">

                <!-- Logo + tagline -->
                <div class="reveal-text mb-16">
                <span class="font-montserrat block text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70 mb-6">
                    Encuéntranos
                </span>
                    <h2 class="font-playfair text-4xl md:text-5xl leading-tight text-white mb-4">
                        Barbershop La H
                    </h2>
                    <div class="h-px w-12 bg-[var(--gold)]"></div>
                </div>

                <!-- Datos de contacto -->
                <div class="reveal-text space-y-10 mb-16">

                    <!-- Dirección -->
                    <div class="flex gap-6 items-start">
                        <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center border border-[var(--gold)]/20">
                            <svg class="h-3.5 w-3.5 text-[var(--gold)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.3rem] text-[var(--gold)]/40 mb-1">Dirección</span>
                            <p class="font-cormorant text-xl text-white/70 leading-snug">
                                <?= h(!empty($config['direccion']) ? str_replace("\n", '<br>', h($config['direccion'])) : 'C/ Miguel Servet 24<br>50013 · Zaragoza') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Instagram -->
                    <div class="flex gap-6 items-start">
                        <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center border border-[var(--gold)]/20">
                            <svg class="h-3.5 w-3.5 text-[var(--gold)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke-linecap="round"/>
                                <circle cx="12" cy="12" r="4" stroke-linecap="round"/>
                                <circle cx="17.5" cy="6.5" r="0.5" fill="currentColor" stroke="none"/>
                            </svg>
                        </div>
                        <div>
                            <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.3rem] text-[var(--gold)]/40 mb-1">Instagram</span>
                            <a href="https://instagram.com/<?= h(ltrim($config['instagram'] ?? '@barbershop_la_h', '@')) ?>" target="_blank" rel="noopener"
                               class="font-cormorant text-xl text-white/70 transition-colors hover:text-[var(--gold)]">
                                <?= h($config['instagram'] ?? '@barbershop_la_h') ?> ↗
                            </a>
                        </div>
                    </div>

                    <!-- Horario -->
                    <div class="flex gap-6 items-start">
                        <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center border border-[var(--gold)]/20">
                            <svg class="h-3.5 w-3.5 text-[var(--gold)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="9"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/>
                            </svg>
                        </div>
                        <div>
                            <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.3rem] text-[var(--gold)]/40 mb-2">Horario</span>
                            <div class="font-cormorant text-lg leading-relaxed text-white/60 space-y-1">
                                <?php if (!empty($config['horario_resumen'])): ?>
                                    <p><?= h($config['horario_resumen']) ?></p>
                                <?php else: ?>
                                    <p>Lunes — Viernes &nbsp;<span class="text-white/60">·</span>&nbsp; 10:00 – 20:00</p>
                                    <p>Sábado &nbsp;<span class="text-white/0">·</span>&nbsp; 10:00 – 14:00</p>
                                    <p class="text-white/30">Domingo &nbsp;<span>·</span>&nbsp; Cerrado</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- CTA reserva -->
                <div class="reveal-text">
                    <a href="cliente/reserva.php" class="bg-[var(--gold)] px-8 py-4 font-montserrat text-[0.72rem] font-bold uppercase tracking-[0.2rem] text-black transition-all hover:bg-white hover:-translate-y-1">
                        Reservar Cita
                    </a>
                </div>

            </div>

            <!-- ── Columna derecha: mapa ── -->
            <div class="relative flex items-center justify-center bg-[var(--charcoal)] p-8 md:p-12 lg:p-16">
                <div class="relative w-full max-w-[550px] overflow-hidden border border-[var(--gold)]/10 shadow-2xl">
                    <iframe
                            title="Ubicación Barbershop La H"
                            class="h-[400px] w-full lg:h-[450px] block"
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2985.2936355819036!2d-0.8756306!3d41.6494793!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd5914ed0761614b%3A0x868b9176c764e52f!2sC.%20de%20Miguel%20Servet%2C%2024%2C%2050013%20Zaragoza!5e0!3m2!1ses!2ses!4v1716900000000!5m2!1ses!2ses"
                            style="border: 0;"
                            allowfullscreen=""
                            loading="lazy">
                    </iframe>
                </div>
            </div>
        </div>

        <!-- ── Barra inferior copyright ── -->
         <div class="border-t border-[var(--gold)]/10 px-12 py-6 md:px-16">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4">
                <p class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-white/20">
                    © <?= date('Y') ?> Barbershop La H · Hassan · Zaragoza
                </p>
                <p class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-white/15">
                    Todos los derechos reservados
                </p>
            </div>
        </div>

    </footer>


    <!-- JS principal -->
    <script src="public/assets/js/main.js"></script>

</body>
</html>
