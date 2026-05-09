<?php
require_once '../clases/Servicio.php';
$servicios = Servicio::obtenerActivos();

/*
 ================================================================
  DATOS MOCK — sustituir por consultas MongoDB cuando esté configurado
  Con MongoDB PHP Driver sería algo así:
  ESTO LO USARE CUANDO CONFIGURE EL MONGO DB CON GALERIA DE CORTES Y PRODUCTOS
  POR AHORA NO VA

  $client    = new MongoDB\Client("mongodb://localhost:27017");
  $db        = $client->barbershop_la_h;
  $galeria   = $db->galeria->find([], ['sort' => ['fecha' => -1]])->toArray();
  $productos = $db->productos->find(['activo' => true])->toArray();
 ================================================================
*/

$galeria = [
        ['id' => 1, 'imagen' => 'assets/img/galeria/corte1.jpg', 'categoria' => 'Fade clásico',   'descripcion' => 'Degradado suave con acabado en navaja'],
        ['id' => 2, 'imagen' => 'assets/img/galeria/corte2.jpg', 'categoria' => 'Corte moderno',  'descripcion' => 'Textura y volumen en la parte superior'],
        ['id' => 3, 'imagen' => 'assets/img/galeria/corte3.jpg', 'categoria' => 'Barba completa', 'descripcion' => 'Perfilado y arreglo de barba larga'],
        ['id' => 4, 'imagen' => 'assets/img/galeria/corte4.jpg', 'categoria' => 'Mullet',         'descripcion' => 'Corte retro con acabado pulido'],
        ['id' => 5, 'imagen' => 'assets/img/galeria/corte5.jpg', 'categoria' => 'Skin fade',      'descripcion' => 'Degradado al cero con diseño lateral'],
        ['id' => 6, 'imagen' => 'assets/img/galeria/corte6.jpg', 'categoria' => 'Texturizado',    'descripcion' => 'Corte irregular con efecto despeinado'],
];

$productos = [
        ['id' => 1, 'imagen' => 'assets/img/productos/prod1.jpg', 'nombre' => 'Pomada Mate',        'descripcion' => 'Fijación fuerte sin brillo. Control total todo el día.',      'precio' => 14],
        ['id' => 2, 'imagen' => 'assets/img/productos/prod2.jpg', 'nombre' => 'Aceite de Barba',    'descripcion' => 'Hidrata y suaviza. Con aceite de argán y jojoba.',             'precio' => 18],
        ['id' => 3, 'imagen' => 'assets/img/productos/prod3.jpg', 'nombre' => 'Crema de Afeitar',   'descripcion' => 'Espuma densa para un afeitado apurado sin irritación.',        'precio' => 12],
        ['id' => 4, 'imagen' => 'assets/img/productos/prod4.jpg', 'nombre' => 'Cera de Peinado',    'descripcion' => 'Fijación media con acabado natural. Para estilos versátiles.', 'precio' => 13],
        ['id' => 5, 'imagen' => 'assets/img/productos/prod5.jpg', 'nombre' => 'Champú Anticaída',   'descripcion' => 'Formulado con biotina y keratina. Uso diario.',                'precio' => 16],
        ['id' => 6, 'imagen' => 'assets/img/productos/prod6.jpg', 'nombre' => 'Bálsamo de Barba',   'descripcion' => 'Fijación ligera y nutrición profunda. Aroma madera y ámbar.', 'precio' => 15],
];

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
    <!-- Estilos personalizados (animaciones, efectos, video) -->
    <link rel="stylesheet" href="assets/css/estilos.css">
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
                <a href="#reservas" class="border border-[var(--gold)]/50 px-6 py-2 font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-[var(--gold)] transition-all duration-300 hover:bg-[var(--gold)] hover:text-[var(--obsidian)]">
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
    <!-- Seccion para solo móvil -->
    <div id="mobileMenu" style="display:none;" class="fixed inset-0 z-[60] bg-[var(--obsidian)] flex-col items-center justify-center gap-10">
        <a href="#sobre"     onclick="document.getElementById('mobileMenu').style.display='none'" class="font-montserrat text-sm uppercase tracking-[0.3rem] text-white/60 hover:text-[var(--gold)]">Nosotros</a>
        <a href="#servicios" onclick="document.getElementById('mobileMenu').style.display='none'" class="font-montserrat text-sm uppercase tracking-[0.3rem] text-white/60 hover:text-[var(--gold)]">Servicios</a>
        <a href="#contacto"  onclick="document.getElementById('mobileMenu').style.display='none'" class="font-montserrat text-sm uppercase tracking-[0.3rem] text-white/60 hover:text-[var(--gold)]">Encuéntranos</a>
        <a href="#reservas"  onclick="document.getElementById('mobileMenu').style.display='none'" class="border border-[var(--gold)]/50 px-8 py-3 font-montserrat text-sm uppercase tracking-[0.2rem] text-[var(--gold)]">Reservar Cita</a>
    </div>


    <!-- ===================== SECCIÓN VIDEO SCROLL ===================== -->
    <section id="experiencia" class="relative w-full" style="height: 350vh;">

        <div class="sticky top-0 h-screen w-full overflow-hidden bg-black">

            <!-- Video principal -->
            <!-- Añadir un poster si no carga: poster="assets/img/poster_hero.jpg" -->
            <video id="mainVideo"
                   class="absolute min-w-full min-h-full w-auto h-auto object-cover"
                   style="top: 50%; left: 50%; transform: translate(-50%, -50%); will-change: transform;"
                   playsinline muted preload="metadata">
                <source src="assets/video/video_intro.mp4" type="video/mp4">
            </video>

            <!-- Degradado viñeta cinematográfico -->
            <div class="video-vignette absolute inset-0 z-[5] pointer-events-none"></div>

            <!-- Overlay de transición (fade a negro al final) -->
            <div id="transitionOverlay"
                 class="absolute inset-0 z-[25] pointer-events-none bg-[var(--obsidian)] opacity-0 transition-opacity duration-700">
            </div>


            <!-- ── TÍTULO INTRO ── -->
            <div id="introBlock" class="absolute inset-0 z-[15] flex items-center justify-center pointer-events-none">
                <div class="text-center px-8">
                    <p id="introTagline" class="font-cormorant mb-6 text-xs uppercase tracking-[0.5rem] text-[var(--gold)]/60 opacity-0 transition-opacity duration-1000">
                        Una experiencia única
                    </p>
                    <h1 id="introTitle" class="intro-title-gradient font-playfair text-[clamp(3.5rem,10vw,7rem)] leading-none tracking-[0.4rem] uppercase opacity-0 transition-all duration-1000">
                        BARBER LA H
                    </h1>
                    <p id="introCity" class="font-cormorant mt-6 text-sm uppercase tracking-[0.5rem] text-white/25 opacity-0 transition-opacity duration-1000">
                        Barbershop La H · Zaragoza
                    </p>
                </div>
            </div>


            <!-- ── ETIQUETAS FLOTANTES ── -->

            <!-- Label 1 -->
            <div id="label1" class="float-label absolute z-[15] pointer-events-none
     left-[8%] top-[28%]
     max-md:left-1/2 max-md:right-auto max-md:-translate-x-1/2 max-md:text-center max-md:w-[85vw]">
                <span class="font-montserrat block text-[0.6rem] uppercase tracking-[0.4rem] text-[var(--gold)]/40">01</span>
                <h3 class="font-playfair mt-2 mb-3 text-2xl leading-tight text-[var(--gold)]">El Corte</h3>
                <p class="font-cormorant max-w-[260px] text-base leading-relaxed text-white/55 max-md:mx-auto">
                    Precisión milimétrica en cada línea.<br>
                    La geometría que define tu estilo.
                </p>
            </div>

            <!-- Label 2 -->
            <div id="label2" class="float-label absolute z-[15] pointer-events-none text-right
     right-[8%] top-[42%]
     max-md:left-1/2 max-md:right-auto max-md:-translate-x-1/2 max-md:text-center max-md:w-[85vw]">
                <span class="font-montserrat block text-[0.6rem] uppercase tracking-[0.4rem] text-[var(--gold)]/40">02</span>
                <h3 class="font-playfair mt-2 mb-3 text-2xl leading-tight text-[var(--gold)]">El Ambiente</h3>
                <p class="font-cormorant ml-auto max-w-[260px] text-base leading-relaxed text-white/55 max-md:mx-auto">
                    Un refugio de elegancia para el<br>
                    caballero que sabe lo que quiere.
                </p>
            </div>

            <!-- Label 3 -->
            <div id="label3" class="float-label absolute z-[15] pointer-events-none
     left-[10%] top-[65%]
     max-md:left-1/2 max-md:right-auto max-md:-translate-x-1/2 max-md:text-center max-md:w-[85vw]">
                <span class="font-montserrat block text-[0.6rem] uppercase tracking-[0.4rem] text-[var(--gold)]/40">03</span>
                <h3 class="font-playfair mt-2 mb-3 text-2xl leading-tight text-[var(--gold)]">El Acabado</h3>
                <p class="font-cormorant max-w-[260px] text-base leading-relaxed text-white/55 max-md:mx-auto">
                    Productos de alta gama para un<br>
                    resultado impecable que dura días.
                </p>
            </div>


            <!-- ── BARRA DE PROGRESO INFERIOR ── -->
            <div class="absolute bottom-8 left-12 right-12 z-[30] flex items-center gap-5">
                <span class="font-montserrat shrink-0 text-[0.6rem] uppercase tracking-[0.3rem] text-white/20">Scroll</span>
                <div class="relative h-px flex-1 overflow-hidden bg-white/10">
                    <div id="progressFill" class="absolute top-0 left-0 h-full bg-[var(--gold)] transition-[width] duration-75 ease-linear" style="width:0%;"></div>
                </div>
                <span id="progressLabel" class="font-montserrat w-8 shrink-0 text-right text-[0.6rem] uppercase tracking-[0.2rem] text-white/20">0%</span>
            </div>

            <!-- ── INDICADOR SCROLL INICIAL ── -->
            <div id="scrollHint" class="absolute bottom-24 left-1/2 z-[30] -translate-x-1/2 text-center pointer-events-none transition-opacity duration-500">
                <div class="scroll-mouse mx-auto"></div>
                <span class="font-montserrat mt-3 block text-[0.55rem] uppercase tracking-[0.35rem] text-white/25">Scroll</span>
            </div>

        </div>
    </section>


    <!-- ===================== SECCIÓN SOBRE ===================== -->
    <section id="sobre" class="relative overflow-hidden bg-[var(--charcoal)] pt-28 pb-32">

        <div class="absolute top-0 left-1/2 w-px -translate-x-1/2 bg-[var(--gold)] opacity-10 h-[80px]"></div>

        <div class="mx-auto max-w-6xl px-8">

            <header class="reveal-up mb-24 text-center">
                <span class="font-montserrat text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70">Barbershop La H</span>
                <h2 class="font-playfair mt-5 mb-5 text-5xl md:text-6xl leading-tight text-white">Sobre Nosotros</h2>
                <div class="mx-auto h-px w-12 bg-[var(--gold)]"></div>
            </header>

            <div class="grid items-center gap-16 md:grid-cols-2 lg:gap-24">

                <div class="reveal-left">
                    <div class="relative">
                        <img src="assets/img/logo.jpg"
                             alt="Hassan"
                             class="block h-[500px] w-full border-[3px] border-[var(--gold)] bg-[var(--obsidian)] object-cover shadow-[20px_20px_0_#2a2a2a] transition-transform duration-[1200ms] ease-out hover:scale-105">

                        <div class="pointer-events-none absolute top-5 left-5 right-[-16px] bottom-[-16px] border border-[var(--gold)] opacity-20"></div>

                        <div class="absolute right-[-10px] bottom-[-20px] bg-[var(--obsidian)] px-6 py-4 text-center md:right-[-20px] border border-[var(--gold)]/30 shadow-xl z-20">
                            <span class="font-playfair block text-3xl leading-none text-[var(--gold)] font-bold">+10</span>
                            <span class="font-montserrat mt-1 block text-[0.55rem] uppercase tracking-[0.2rem] text-white/50">Años de exp.</span>
                        </div>
                    </div>
                </div>

                <div class="reveal-right mt-10 md:mt-0">
                    <h3 class="font-playfair mb-8 text-3xl md:text-4xl leading-tight text-white">
                        Hola, soy <em class="not-italic text-[var(--gold)]">Hassan</em>
                    </h3>

                    <div class="font-cormorant mb-10 text-lg leading-relaxed text-white/60">
                        <p class="mb-6">
                            Con más de <strong class="font-normal text-white">10 años de experiencia</strong> en el mundo de la barbería,
                            me he convertido en mucho más que un barbero: soy tu aliado para
                            encontrar el estilo que te representa.
                        </p>
                        <p class="mb-6">
                            En <strong class="font-normal text-white">Barbershop La H</strong> combinamos técnica clásica
                            con las tendencias más modernas para crear un look único que
                            se adapte a tu personalidad y estilo de vida.
                        </p>
                        <p>
                            Nuestra barbería es un espacio de confianza donde el tiempo
                            se detiene y cada cliente recibe una atención completamente personalizada.
                        </p>
                    </div>

                    <div class="mb-12 flex flex-wrap items-center gap-6">
                        <a href="#reservas" class="bg-[var(--gold)] px-8 py-4 font-montserrat text-[0.72rem] font-bold uppercase tracking-[0.2rem] text-black transition-all hover:bg-white hover:-translate-y-1">
                            Reservar Cita
                        </a>
                        <a href="https://instagram.com/barbershop_la_h" target="_blank" rel="noopener"
                           class="font-montserrat text-[0.72rem] uppercase tracking-[0.2rem] text-white/30 transition-colors duration-300 hover:text-[var(--gold)]">
                            @barbershop_la_h ↗
                        </a>
                    </div>

                    <footer class="mt-12 border-t border-[var(--gold)]/20 pt-8">

                        <p class="font-playfair text-2xl italic text-[var(--gold)]">
                            — Hassan
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

        <!-- Línea decorativa superior -->
        <div class="absolute top-0 left-1/2 h-[80px] w-px -translate-x-1/2 bg-[var(--gold)] opacity-10"></div>

        <!-- Ruido de fondo sutil -->
        <div class="pointer-events-none absolute inset-0 opacity-[0.025]"
             style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'n\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/%3E%3C/svg%3E');
              background-size: 200px 200px;"></div>

        <div class="mx-auto max-w-7xl px-8">

            <!-- Cabecera -->
            <header class="reveal-text mb-20 text-center">
            <span class="font-montserrat text-[0.6rem] uppercase tracking-[0.5rem] text-[var(--gold)] opacity-70">
                Lo que hacemos
            </span>
                <h2 class="font-playfair mt-5 mb-5 text-5xl md:text-6xl leading-tight text-white">
                    Nuestros Servicios
                </h2>
                <div class="mx-auto h-px w-12 bg-[var(--gold)]"></div>
                <p class="font-cormorant mx-auto mt-8 max-w-xl text-xl leading-relaxed text-white/45">
                    Cada servicio es una experiencia pensada al detalle.<br>
                    Técnica depurada, productos de calidad y tiempo para ti.
                </p>
            </header>

            <!-- Grid de servicios -->
            <div class="grid gap-px md:grid-cols-2 lg:grid-cols-3"
                 style="background-color: rgba(212,175,55,0.08);">

                <?php
                // $servicios viene de Servicio::obtenerTodos() llamado arriba en el PHP
                // Numeración visual para cada tarjeta
                $numero = 1;
                foreach ($servicios as $servicio) { ?>

                    <article class="reveal-text group relative flex flex-col justify-between bg-[var(--obsidian)] p-10 transition-colors duration-500
                                hover:bg-[var(--charcoal)]"
                             style="transition-delay: <?= ($numero - 1) * 0.08 ?>s;">
                        <!-- Número decorativo -->
                        <span class="font-playfair absolute top-8 right-10 text-[4rem] leading-none font-bold
                                 text-[var(--gold)] opacity-5 select-none transition-opacity duration-500
                                 group-hover:opacity-10">
                        <?= str_pad($numero, 2, '0', STR_PAD_LEFT) ?>
                    </span>

                        <div>
                            <!-- Etiqueta duración -->
                            <div class="mb-6 flex items-center gap-3">
                                <div class="h-px w-6 bg-[var(--gold)] opacity-50"></div>
                                <span class="font-montserrat text-[0.55rem] uppercase tracking-[0.3rem] text-[var(--gold)]/50">
                                <?= htmlspecialchars($servicio->getDuracionMin()) ?>
                            </span>
                            </div>

                            <!-- Nombre -->
                            <h3 class="font-playfair mb-4 text-2xl leading-tight text-white
                                   transition-colors duration-300 group-hover:text-[var(--gold)]">
                                <?= htmlspecialchars($servicio->getNombre()) ?>
                            </h3>

                            <!-- Descripción -->
                            <p class="font-cormorant text-base leading-relaxed text-white/60
                                  transition-colors duration-300 group-hover:text-white/60">
                                <?= htmlspecialchars($servicio->getDescripcion()) ?>
                            </p>
                        </div>

                        <!-- Precio + CTA -->
                        <footer class="mt-10 flex items-end justify-between border-t border-[var(--gold)]/10 pt-8">
                            <div>
                            <span class="font-montserrat block text-[0.55rem] uppercase tracking-[0.2rem] text-white/20 mb-1">
                                Precio
                            </span>
                                <span class="font-playfair text-3xl font-bold text-[var(--gold)]">
                                <?= number_format($servicio->getPrecio(), 0) ?>€
                            </span>
                            </div>
                            <a href="#reservas"
                               class="font-montserrat text-[0.6rem] uppercase tracking-[0.2rem] text-white/25
                                  border-b border-transparent transition-all duration-300
                                  group-hover:text-[var(--gold)] group-hover:border-[var(--gold)]/40">
                                Reservar →
                            </a>
                        </footer>
                    </article>
                    <?php
                    $numero++;
                } ?>

            </div>

            <!-- Nota inferior -->
            <p class="reveal-text font-cormorant mt-12 text-center text-base text-white/25 italic">
                ¿Tienes dudas? Escríbenos en Instagram
                <a href="https://instagram.com/barbershop_la_h" target="_blank" rel="noopener"
                   class="text-[var(--gold)]/50 transition-colors hover:text-[var(--gold)] not-italic ml-1">
                    @barbershop_la_h ↗
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

                <?php foreach ($galeria as $i => $foto) { ?>
                    <div class="galeria-slide relative shrink-0 cursor-pointer select-none overflow-hidden"
                         style="width: clamp(280px, 38vw, 520px); margin-right: 2px;"
                         data-index="<?= $i ?>"
                         data-src="<?= htmlspecialchars($foto['imagen']) ?>"
                         data-categoria="<?= htmlspecialchars($foto['categoria']) ?>"
                         data-descripcion="<?= htmlspecialchars($foto['descripcion']) ?>">

                        <!-- Imagen -->
                        <img src="<?= htmlspecialchars($foto['imagen']) ?>"
                             alt="<?= htmlspecialchars($foto['categoria']) ?>"
                             class="block h-[480px] w-full object-cover transition-transform duration-700 ease-out"
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
                            <span class="font-montserrat mt-4 block text-[0.5rem] uppercase tracking-[0.3rem] text-white/30">
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
                <?php foreach ($galeria as $i => $foto) { ?>
                    <button class="galeria-dot h-px w-6 bg-white/20 transition-all duration-300"
                            data-index="<?= $i ?>"
                            aria-label="Ir a imagen <?= $i + 1 ?>"></button>
                <?php } ?>
            </div>

        </div><!-- /carrusel -->

    </section>


    <!-- ── LIGHTBOX (oculto por defecto) ── -->
    <div id="galeriaLightbox" style="display:none;" class="fixed inset-0 z-[200] items-center justify-center bg-black/90 backdrop-blur-sm">

        <!-- Botón cerrar -->
        <button id="lightboxClose"
                class="absolute top-6 right-8 font-montserrat text-[0.6rem] uppercase tracking-[0.3rem] text-white/40 transition-colors hover:text-[var(--gold)]">
            Cerrar ✕
        </button>

        <!-- Imagen ampliada -->
        <div class="mx-auto flex max-w-[90vw] flex-col items-center gap-6 px-4">
            <img id="lightboxImg" src="" alt=""
                 class="max-h-[75vh] max-w-full object-contain shadow-2xl border border-[var(--gold)]/20">
            <div class="text-center">
                <span id="lightboxCategoria" class="font-montserrat block text-[0.6rem] uppercase tracking-[0.4rem] text-[var(--gold)]/60 mb-2"></span>
                <p id="lightboxDesc" class="font-cormorant text-xl text-white/60"></p>
            </div>
        </div>
    </div>

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
            <div class="grid gap-px sm:grid-cols-2 lg:grid-cols-3"
                 style="background-color: rgba(212,175,55,0.06);">

                <?php foreach ($productos as $i => $prod) { ?>
                    <article class="reveal-text producto-card group relative bg-[var(--obsidian)] overflow-hidden
                            transition-colors duration-500 hover:bg-[var(--charcoal)]"
                             style="transition-delay: <?= $i * 0.07 ?>s;">

                        <!-- Imagen con overlay dorado en hover -->
                        <div class="relative overflow-hidden h-[260px]">
                            <img src="<?= htmlspecialchars($prod['imagen']) ?>"
                                 alt="<?= htmlspecialchars($prod['nombre']) ?>"
                                 class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                                 loading="lazy">

                            <!-- Overlay dorado muy sutil -->
                            <div class="absolute inset-0 bg-[var(--gold)] opacity-0 mix-blend-overlay
                                transition-opacity duration-500 group-hover:opacity-10"></div>
                        </div>

                        <!-- Info -->
                        <div class="p-8">

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

                            <footer class="flex items-end justify-between border-t border-[var(--gold)]/10 pt-6">
                                <div>
                                    <span class="font-montserrat block text-[0.5rem] uppercase tracking-[0.2rem] text-white/20 mb-1">Precio</span>
                                    <span class="font-playfair text-3xl font-bold text-[var(--gold)]">
                                <?= $prod['precio'] ?>€
                            </span>
                                </div>
                                <a href="https://instagram.com/barbershop_la_h"
                                   target="_blank" rel="noopener"
                                   class="font-montserrat text-[0.55rem] uppercase tracking-[0.2rem] text-white/25
                                  border-b border-transparent transition-all duration-300
                                  group-hover:text-[var(--gold)] group-hover:border-[var(--gold)]/40">
                                    Preguntar →
                                </a>
                            </footer>

                        </div>
                    </article>
                <?php } ?>

            </div>

            <!-- Nota inferior -->
            <p class="reveal-text font-cormorant mt-12 text-center text-base text-white/25 italic">
                Pásate por la barbería o escríbenos en
                <a href="https://instagram.com/barbershop_la_h" target="_blank" rel="noopener"
                   class="text-[var(--gold)]/50 transition-colors hover:text-[var(--gold)] not-italic ml-1">
                    @barbershop_la_h ↗
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
                                C/ Miguel Servet 24<br>
                                50013 · Zaragoza
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
                            <a href="https://instagram.com/barbershop_la_h" target="_blank" rel="noopener"
                               class="font-cormorant text-xl text-white/70 transition-colors hover:text-[var(--gold)]">
                                @barbershop_la_h ↗
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
                                <!-- Actualiza estos horarios cuando Hassan los confirme -->
                                <p>Lunes — Viernes &nbsp;<span class="text-white/60">·</span>&nbsp; 10:00 – 20:00</p>
                                <p>Sábado &nbsp;<span class="text-white/0">·</span>&nbsp; 10:00 – 14:00</p>
                                <p class="text-white/30">Domingo &nbsp;<span>·</span>&nbsp; Cerrado</p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- CTA reserva -->
                <div class="reveal-text">
                    <a href="#reservas" class="bg-[var(--gold)] px-8 py-4 font-montserrat text-[0.72rem] font-bold uppercase tracking-[0.2rem] text-black transition-all hover:bg-white hover:-translate-y-1">
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
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2985.2936355819036!2d-0.8756306!3d41.6494793!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd5914ed0761614b%3A0x868b9176c764e52f!2sC.%20de%20Miguel%20Servet%2C%2024%2C%2050013%20Zaragoza!5e0!3m2!1ses!2ses!4v1700000000000!5m2!1ses!2ses"
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
    <script src="assets/js/main.js"></script>

</body>
</html>
