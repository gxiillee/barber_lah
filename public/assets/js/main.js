/* ================================================================
   Barbershop La H — main.js
   Motor de scroll-video, animaciones de reveal, preloader y nav.
   ================================================================ */

"use strict";

/* ────────────────────────────────────────────────────────────────
   REFERENCIAS AL DOM
   ──────────────────────────────────────────────────────────────── */

/*
Crea objetos materializado por cada elemento de HTML
que con js se va a modificar
 */

const DOM = {
  preloader: document.getElementById("preloader"),
  mainNav: document.getElementById("mainNav"),
  mainVideo: document.getElementById("mainVideo"),
  videoSection: document.getElementById("experiencia"),
  transitionOverlay: document.getElementById("transitionOverlay"),

  // Intro
  introBlock: document.getElementById("introBlock"),
  introTitle: document.getElementById("introTitle"),
  introTagline: document.getElementById("introTagline"),
  introCity: document.getElementById("introCity"),

  // Labels flotantes
  label1: document.getElementById("label1"),
  label2: document.getElementById("label2"),
  label3: document.getElementById("label3"),

  // Progreso
  progressFill: document.getElementById("progressFill"),
  progressLabel: document.getElementById("progressLabel"),

  // Hint scroll
  scrollHint: document.getElementById("scrollHint"),

  // Sección sobre
  sobreSection: document.getElementById("sobre"),
};

/* ────────────────────────────────────────────────────────────────
   PRELOADER
   Oculta la pantalla de carga al terminar de cargar la página.
   ──────────────────────────────────────────────────────────────── */

/*
Oculta el div de preloader cuando la página ha cargado por completo.
 */
function quitarCarga() {
  const p = document.getElementById("preloader");
  if (p) {
    p.style.opacity = "0";
    setTimeout(() => {
      p.classList.add("hidden");
      document.body.style.overflow = "auto";
    }, 500);
  }
}

window.addEventListener("load", () => {
  setTimeout(quitarCarga, 1000);
});

// Failsafe: SI algo falla, lo oculta en 5s
setTimeout(quitarCarga, 5000);

/* ────────────────────────────────────────────────────────────────
   NAVEGACIÓN — clase "scrolled" al bajar del viewport
   ──────────────────────────────────────────────────────────────── */
/*
si el usuario baja >60px, cambia el scroll
 */
function updateNav() {
  DOM.mainNav.classList.toggle("scrolled", window.scrollY > 60);
}

window.addEventListener("scroll", updateNav, { passive: true });
updateNav(); // Estado inicial

/* ────────────────────────────────────────────────────────────────
   MOTOR DE SCROLL → VIDEO
   Scrub con lerp rápido (0.5 = alcanza el target en 1-2 frames).
   Incluye un threshold mínimo para no forzar búsquedas cuando
   el video ya está cerca (esencial para Safari, que se queda en
   negro si le asignas currentTime=0 explícitamente al cargar).
   ──────────────────────────────────────────────────────────────── */

// 0.25 = responde en ~66ms (4 frames), interpolación suave y sin lag perceptible
const LERP_FACTOR = 0.25;
const SEEK_THRESHOLD = 0.015; // no buscar si el cambio es menor a 15ms
const ZOOM_LERP = 0.15;

let rafId = null;
let targetTime = 0;
let currentTime = 0;
let currentZoom = 1;

/** Interpolación lineal */
function lerp(a, b, t) {
  return a + (b - a) * t;
}

/**
 *  Cálculo de Scroll VIDEO
 * Calcula dinámicamente qué porcentaje de la sección de video se ha desplazado.
 * 
 * ¿Cómo funciona?
 * 1. Obtiene las dimensiones de la sección del video respecto al viewport.
 * 2. Calcula el espacio total disponible para hacer scroll (altura total - viewport).
 * 3. Mapea la posición negativa del top respecto al total para obtener un porcentaje.
 * 
 * @returns {number} Progreso normalizado entre 0 (inicio) y 1 (fin de la sección).
 */
function getScrollProgress() {
  if (!DOM.videoSection) return 0;

  const rect = DOM.videoSection.getBoundingClientRect();
  // Altura disponible para el scroll (total - viewport)
  const scrollable = DOM.videoSection.offsetHeight - window.innerHeight;

  if (scrollable <= 0) return 0;

  return Math.max(0, Math.min(1, -rect.top / scrollable));
}

/**
 * Controla la visibilidad y animación de los labels flotantes.
 * @param {HTMLElement} el - Elemento label
 * @param {boolean} visible - Si debe estar visible
 */
function setLabelVisible(el, visible) {
  if (visible) {
    el.classList.add("visible");
  } else {
    el.classList.remove("visible");
  }
}

/**
 * Aplica el fade del título intro en función del progreso.
 * Aparece al principio y desaparece antes de los labels.
 * @param {number} p - progreso 0-1
 */
function updateIntroTitle(p) {
  let opacity;

  if (p <= 0.15) {
    opacity = 1; // visible desde el primer momento
  } else if (p < 0.28) {
    opacity = 1 - (p - 0.15) / 0.13; // fade out suave
  } else {
    opacity = 0;
  }

  opacity = Math.max(0, Math.min(1, opacity));

  DOM.introTitle.style.opacity = opacity;
  DOM.introTagline.style.opacity = opacity * 0.75;
  DOM.introCity.style.opacity = opacity * 0.5;
}

/**
 * [TFG] Bucle de Renderizado Principal (Motor de Animación)
 * Se ejecuta a ~60fps usando requestAnimationFrame.
 * 
 * El video usa lerp con factor 0.5 para alcanzar el target en 1-2 frames
 * (~33ms), lo que elimina el lag perceptible del 0.08 original (~300ms).
 * El threshold SEEK_THRESHOLD evita asignar currentTime cuando el video
 * ya está cerca del objetivo — esto es crítico en Safari, donde asignar
 * currentTime=0 al cargar causa que el video se quede en negro.
 */
function renderFrame() {
  const p = getScrollProgress(); // progreso 0 → 1

  /* ── Video scrubbing ── */
  if (DOM.mainVideo && DOM.mainVideo.duration) {
    targetTime = p * DOM.mainVideo.duration;
    currentTime = lerp(currentTime, targetTime, LERP_FACTOR);
    // Threshold: si la diferencia es menor a 5ms, no tocamos el video
    // Esto evita el bug de Safari donde setear currentTime=0 da negro
    if (Math.abs(currentTime - DOM.mainVideo.currentTime) > SEEK_THRESHOLD) {
      DOM.mainVideo.currentTime = currentTime;
    }

    // Zoom suave (lerp bajo para transición cinematográfica)
    const targetZoom = 1 + p * 0.07;
    currentZoom = lerp(currentZoom, targetZoom, ZOOM_LERP);
    DOM.mainVideo.style.transform = `translate(-50%, -50%) scale(${currentZoom})`;
  }

  /* ── Hint de scroll (desaparece tras el primer desplazamiento) ── */
  if (p > 0.04) {
    DOM.scrollHint.classList.add("hidden-hint");
  } else {
    DOM.scrollHint.classList.remove("hidden-hint");
  }

  /* ── Título intro ── */
  updateIntroTitle(p);

  /* ── Labels flotantes (ventanas de visibilidad por progreso) ── */
  setLabelVisible(DOM.label1, p >= 0.22 && p < 0.46);
  setLabelVisible(DOM.label2, p >= 0.5 && p < 0.72);
  setLabelVisible(DOM.label3, p >= 0.76 && p < 0.96);

  /* ── Barra de progreso ── */
  const pct = Math.round(p * 100);
  DOM.progressFill.style.width = `${pct}%`;

  /* ── Overlay de transición: fade a negro en el último 10% ──
       Crea la sensación cinematográfica de "corte a negro"
       antes de revelar la sección siguiente.
    ── */
  let overlayOpacity = 0;

  if (p >= 0.9) {
    // De 0.90 a 1.00: fade de 0 a 1
    overlayOpacity = (p - 0.9) / 0.1;
  }

  DOM.transitionOverlay.style.opacity = Math.min(1, overlayOpacity);

  /* Continúa el bucle */
  rafId = requestAnimationFrame(renderFrame);
}

/* Arranca el bucle */
rafId = requestAnimationFrame(renderFrame);

/* ────────────────────────────────────────────────────────────────
   REVEAL DE SECCIÓN "SOBRE" — IntersectionObserver
   Cuando la sección entra en el viewport, se activa la clase
   .revealed que dispara las animaciones CSS de los hijos.
   ──────────────────────────────────────────────────────────────── */

/**
 * Observa un elemento y añade .revealed cuando entra en pantalla.
 * Una vez revelado, deja de observarlo.
 */
function observeReveal(element, threshold = 0.1) {
  if (!element) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("revealed");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold },
  );

  observer.observe(element);
}

// Observamos la sección sobre y sus elementos internos
observeReveal(DOM.sobreSection, 0.08);

// También observamos los elementos internos directamente para más control
document
  .querySelectorAll(".reveal-up, .reveal-left, .reveal-right")
  .forEach((el) => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = "1";
            entry.target.style.transform = "translate(0, 0)";
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1 },
    );
    observer.observe(el);
  });

/* ────────────────────────────────────────────────────────────────
   SMOOTH SCROLL para los links de navegación
   ──────────────────────────────────────────────────────────────── */

document.querySelectorAll('a[href^="#"]').forEach((link) => {
  link.addEventListener("click", (e) => {
    const targetId = link.getAttribute("href");
    if (targetId === "#") return;

    const targetEl = document.querySelector(targetId);
    if (!targetEl) return;

    e.preventDefault();
    targetEl.scrollIntoView({ behavior: "smooth", block: "start" });
  });
});

/* ────────────────────────────────────────────────────────────────
   MENÚ MOBILE (toggle básico)
   ──────────────────────────────────────────────────────────────── */

const menuToggle = document.getElementById("menuToggle");
const mobileMenu = document.getElementById("mobileMenu");
const menuBackdrop = document.getElementById("menuBackdrop");

function cerrarMenuMobile() {
  mobileMenu.style.display = "none";
  mobileMenu.classList.remove("menu-open");
  if (menuBackdrop) menuBackdrop.classList.add("hidden");
}

function abrirMenuMobile() {
  mobileMenu.style.display = "flex";
  mobileMenu.classList.add("menu-open");
  if (menuBackdrop) menuBackdrop.classList.remove("hidden");
}

if (menuToggle && mobileMenu) {
  menuToggle.addEventListener("click", () => {
    const isOpen = mobileMenu.style.display === "flex";
    if (isOpen) cerrarMenuMobile();
    else abrirMenuMobile();
  });
  // cierra al tocar el fondo (fuera de los links)
  mobileMenu.addEventListener("click", (e) => {
    if (e.target === mobileMenu) cerrarMenuMobile();
  });
}

/* ────────────────────────────────────────────────────────────────
   OPTIMIZACIÓN: pausa el RAF cuando la pestaña no está activa
   ──────────────────────────────────────────────────────────────── */

document.addEventListener("visibilitychange", () => {
  if (document.hidden) {
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
  } else {
    if (!rafId) {
      rafId = requestAnimationFrame(renderFrame);
    }
  }
});

/* ────────────────────────────────────────────────────────────────
   APARICION SUAVE: seccion de nosotros con foto de hassan aparece suave todo
   ──────────────────────────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  const observerOptions = {
    threshold: 0.15,
    rootMargin: "0px 0px -50px 0px",
  };

  const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        // Disparamos la animación añadiendo la clase 'revealed' o 'active'
        entry.target.classList.add("revealed", "active");
        // Dejamos de observar una vez animado
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  // Seleccionamos todo lo que queremos que se anime al hacer scroll:
  // 1. Tu sección de 'Sobre Nosotros'
  // 2. Cualquier elemento al que le pongas la clase 'reveal-text'
  const elementsToWatch = document.querySelectorAll(
    ".sobre-section, .reveal-text",
  );

  elementsToWatch.forEach((el) => {
    revealObserver.observe(el);
  });
});

/* ================================================================
   CARRUSEL GALERÍA
   Auto-play que se pausa al poner el ratón encima.
   Click en slide → abre lightbox.
   ================================================================ */

(function initGaleria() {
  const track = document.getElementById("galeriaTrack");
  const slides = document.querySelectorAll(".galeria-slide");
  const dots = document.querySelectorAll(".galeria-dot");
  const btnPrev = document.getElementById("galeriaPrev");
  const btnNext = document.getElementById("galeriaNext");
  const carrusel = document.getElementById("galeriaCarrusel");

  if (!track || slides.length === 0) return;

  const INTERVAL = parseInt(carrusel.dataset.interval) || 4000;
  let current = 0;
  let timer = null;
  let isHovered = false;

  /* ── Calcula el ancho de cada slide (incluye el gap de 2px) ── */
  function slideWidth() {
    return slides[0].offsetWidth + 2; // 2px = margin-right
  }

  /* ── Mueve el carrusel al índice indicado ── */
  function goTo(index) {
    if (index >= slides.length) index = 0;
    if (index < 0) index = slides.length - 1;

    current = index;

    // [TFG] Responsive Slide Offset (Cálculo Dinámico del Centro)
    // Para dispositivos móviles, queremos que la imagen activa quede en el centro de la pantalla.
    // Restamos el ancho del slide al ancho de la ventana y lo dividimos entre 2 
    // para obtener los márgenes laterales (centerOffset).
    // En escritorio (desktop), el carrusel fluye libremente desde el margen izquierdo (0px).
    const isMobile = window.innerWidth <= 768;
    const centerOffset = isMobile
      ? (window.innerWidth - slides[0].offsetWidth) / 2
      : 0;

    track.style.transform = `translateX(${centerOffset - current * slideWidth()}px)`;

    dots.forEach((dot, i) => {
      dot.classList.toggle("active", i === current);
    });
  }

  /* ── Auto-play ── */
  function startAuto() {
    timer = setInterval(() => {
      if (!isHovered) goTo(current + 1);
    }, INTERVAL);
  }

  function stopAuto() {
    clearInterval(timer);
    timer = null;
  }

  /* ── Pausa al hover ── */
  carrusel.addEventListener("mouseenter", () => {
    isHovered = true;
  });
  carrusel.addEventListener("mouseleave", () => {
    isHovered = false;
  });

  /* ── Flechas ── */
  btnPrev.addEventListener("click", () => {
    stopAuto();
    goTo(current - 1);
    startAuto();
  });
  btnNext.addEventListener("click", () => {
    stopAuto();
    goTo(current + 1);
    startAuto();
  });

  /* ── Puntos ── */
  dots.forEach((dot) => {
    dot.addEventListener("click", () => {
      stopAuto();
      goTo(parseInt(dot.dataset.index));
      startAuto();
    });
  });

  /* ── Recalcula posición si cambia el tamaño de ventana ── */
  window.addEventListener("resize", () => goTo(current));

  /* ── Arranca ── */
  goTo(0);
  startAuto();
})();
