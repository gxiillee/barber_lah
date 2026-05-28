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
   Scrubbing suave con interpolación (lerp) para evitar saltos.
   ──────────────────────────────────────────────────────────────── */

// Factor de interpolación: 0.08 = movimiento suave; más alto = más rápido
const LERP_FACTOR = 0.08;

let targetTime = 0; // tiempo objetivo (donde debería estar)
let currentTime = 0; // tiempo actual (interpolado)
let rafId = null; // requestAnimationFrame id

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
 * Este es el corazón de los efectos visuales. Se ejecuta a ~60fps utilizando 
 * requestAnimationFrame (rAF), delegando la sincronización de frames al navegador.
 * 
 * ¿Por qué usar rAF y Lerp para el video?
 * En lugar de enlazar bruscamente el fotograma del video con el píxel del scroll,
 * calculamos un "tiempo objetivo" y usamos interpolación lineal (Lerp) para
 * que el "tiempo actual" alcance al objetivo suavemente, evitando tirones (stuttering).
 */
function renderFrame() {
  const p = getScrollProgress(); // progreso 0 → 1

  /* ── Video scrubbing ── */
  if (DOM.mainVideo && DOM.mainVideo.duration) {
    targetTime = p * DOM.mainVideo.duration;
    currentTime = lerp(currentTime, targetTime, LERP_FACTOR);

    // Solo escribimos si hay diferencia apreciable (ahorra CPU)
    if (Math.abs(targetTime - currentTime) > 0.001) {
      DOM.mainVideo.currentTime = currentTime;
    }

    // Zoom sutil conforme avanza el scroll
    const scale = 1 + p * 0.07;
    DOM.mainVideo.style.transform = `translate(-50%, -50%) scale(${scale})`;
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
  DOM.progressLabel.textContent = `${pct}%`;

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

if (menuToggle && mobileMenu) {
  menuToggle.addEventListener("click", () => {
    const isOpen = mobileMenu.style.display === "flex";
    if (isOpen) {
      mobileMenu.style.display = "none";
      mobileMenu.classList.remove("menu-open");
    } else {
      mobileMenu.style.display = "flex";
      mobileMenu.classList.add("menu-open");
    }
  });
  // cierra al tocar el fondo (fuera de los links)
  mobileMenu.addEventListener("click", (e) => {
    if (e.target === mobileMenu) {
      mobileMenu.style.display = "none";
      mobileMenu.classList.remove("menu-open");
    }
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
