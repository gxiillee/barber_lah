/* ================================================================
   Barbershop La H — main.js
   Motor de scroll-video, animaciones de reveal, preloader y nav.
   ================================================================ */

"use strict";

/* ────────────────────────────────────────────────────────────────
   REFERENCIAS AL DOM
   ──────────────────────────────────────────────────────────────── */

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
window.addEventListener("load", () => {
  const minDelay = 1600;
  const startTime = performance.now();

  const hidePreloader = () => {
    const elapsed = performance.now() - startTime;
    const remaining = Math.max(0, minDelay - elapsed);

    // Buscamos el elemento directamente por ID para evitar errores
    const preloader = document.getElementById("preloader");

    if (preloader) {
      setTimeout(() => {
        preloader.classList.add("hidden");
        // Opcional: habilitamos el scroll del cuerpo una vez quitado
        document.body.style.overflow = "auto";
      }, remaining);
    }
  };

  hidePreloader();
});

/* ────────────────────────────────────────────────────────────────
   NAVEGACIÓN — clase "scrolled" al bajar del viewport
   ──────────────────────────────────────────────────────────────── */

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
 * Calcula el progreso del scroll dentro de la sección de video.
 * 0 = inicio de la sección visible, 1 = fin del scroll de la sección.
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

  if (p < 0.04) {
    // Fade in muy rápido al comenzar
    opacity = p / 0.04;
  } else if (p < 0.2) {
    // Visible al 100%
    opacity = 1;
  } else if (p < 0.28) {
    // Fade out antes del primer label
    opacity = 1 - (p - 0.2) / 0.08;
  } else {
    opacity = 0;
  }

  opacity = Math.max(0, Math.min(1, opacity));

  DOM.introTitle.style.opacity = opacity;
  DOM.introTagline.style.opacity = opacity * 0.75;
  DOM.introCity.style.opacity = opacity * 0.5;
}

/**
 * Bucle de animación principal.
 * Se ejecuta cada frame via requestAnimationFrame.
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

if (menuToggle) {
  menuToggle.addEventListener("click", () => {
    // Implementar menú mobile si hace falta en el futuro
    // Por ahora simplemente el nav colapsado es funcional para desktop
    console.log("Mobile menu — pendiente de implementar drawer");
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
