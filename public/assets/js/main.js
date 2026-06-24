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
  endMessageInner: document.getElementById("endMessageInner"),

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
    }, 250);
  }
}

// Oculta en cuanto el DOM está listo (sin esperar videos, fuentes, etc.)
document.addEventListener("DOMContentLoaded", () => {
  setTimeout(quitarCarga, 100);
});
// Segundo intento al cargar todo (por si DOMContentLoaded no se disparó bien)
window.addEventListener("load", () => {
  setTimeout(quitarCarga, 800);
});

// Failsafe: si algo falla, lo oculta en 5s
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

const LERP_FACTOR = 0.25;
const SEEK_THRESHOLD = 0.03;
const ZOOM_LERP = 0.15;

let rafId = null;
let targetTime = 0;
let currentTime = 0;
let currentZoom = 1;
let lastScrollY = -1;
let cachedProgress = -1;
let maxProgressReached = -1;
let progressFreezeUntil = 0;
const FREEZE_MS = 800;
let deferFirstSeek = false;
let firstVideoFrame = true;
let wasInOverlay = false;
let lastP = 0;

function lerp(a, b, t) {
  return a + (b - a) * t;
}

function getScrollProgress() {
  if (!DOM.videoSection) return 0;
  if (window.scrollY === lastScrollY && cachedProgress >= 0) {
    return cachedProgress;
  }
  lastScrollY = window.scrollY;
  const rect = DOM.videoSection.getBoundingClientRect();
  const scrollable = DOM.videoSection.offsetHeight - window.innerHeight;
  if (scrollable <= 0) return 0;
  cachedProgress = Math.max(0, Math.min(1, -rect.top / scrollable));
  return cachedProgress;
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
  const rawP = getScrollProgress(); // progreso puro (sin freeze)
  let p = rawP;

  /* ── Anti-bounce persistente (congela el progreso aunque el bucle se pause) ── */
  if (p > maxProgressReached) {
    maxProgressReached = p;
  }
  if (p >= 0.97) {
    progressFreezeUntil = performance.now() + FREEZE_MS;
  }
  if (maxProgressReached >= 0.97 && p < maxProgressReached && performance.now() < progressFreezeUntil) {
    p = maxProgressReached;
  }

  /* ── Video scrubbing (solo cuando la sección está expandida) ── */
  const videoReady = DOM.videoSection && DOM.videoSection.classList.contains("video-ready");
  if (videoReady && DOM.mainVideo && DOM.mainVideo.duration && DOM.mainVideo.readyState >= 2) {
    // Si acabamos de expandirnos en medio del scroll, congelar en frame 0
    // hasta que el usuario vuelva al inicio de la sección
    if (firstVideoFrame) {
      firstVideoFrame = false;
      if (p > 0.15) deferFirstSeek = true;
    }

    if (deferFirstSeek) {
      targetTime = 0;
      currentTime = lerp(currentTime, 0, LERP_FACTOR);
      if (p < 0.05) deferFirstSeek = false;
      if (Math.abs(DOM.mainVideo.currentTime) > SEEK_THRESHOLD) {
        DOM.mainVideo.currentTime = 0;
      }
    } else {
      targetTime = p * DOM.mainVideo.duration;
      currentTime = lerp(currentTime, targetTime, LERP_FACTOR);
      // Threshold: si la diferencia es menor a 5ms, no tocamos el video
      // Esto evita el bug de Safari donde setear currentTime=0 da negro
      if (Math.abs(currentTime - DOM.mainVideo.currentTime) > SEEK_THRESHOLD) {
        // Solo seek si la posición ya está buffereada (evita tirones en conexiones lentas)
        const b = DOM.mainVideo.buffered;
        let canSeek = false;
        for (let i = 0; i < b.length; i++) {
          if (currentTime >= b.start(i) && currentTime <= b.end(i)) {
            canSeek = true;
            break;
          }
        }
        if (canSeek) {
          DOM.mainVideo.currentTime = currentTime;
        }
      }
    }

    // Zoom suave (lerp bajo para transición cinematográfica)
    const targetZoom = 1 + p * 0.07;
    currentZoom = lerp(currentZoom, targetZoom, ZOOM_LERP);
    DOM.mainVideo.style.transform = `translate(-50%, -50%) scale(${currentZoom})`;
  }

  /* ── Detectar dirección de scroll para transiciones suaves ── */
  const goingBackward = rawP < lastP;
  lastP = rawP;

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

  /* ── Overlay de transición: fade a negro ──
     Adelante: fade 0→1 en p 0.9→1.0
     Atrás:    fade 1→0 en p 1.0→0.82 (continuo, sin saltos) ── */
  let overlayOpacity = 0;

  if (videoReady && DOM.mainVideo && DOM.mainVideo.videoWidth > 0) {
    if (goingBackward && wasInOverlay) {
      overlayOpacity = Math.min(1, Math.max(0, (p - 0.60) / 0.40));
      if (p < 0.60) wasInOverlay = false;
    } else if (p >= 0.9) {
      wasInOverlay = true;
      overlayOpacity = Math.min(1, Math.max(0, (p - 0.9) / 0.1));
    } else {
      wasInOverlay = false;
    }
  }

  DOM.transitionOverlay.style.opacity = overlayOpacity;

  /* ── Mensaje de bienvenida — fade gradual (direccional) ── */
  if (DOM.endMessageInner) {
    let em = 0;
    if (videoReady && DOM.mainVideo && DOM.mainVideo.videoWidth > 0) {
      if (goingBackward && wasInOverlay) {
        // Al ir hacia atrás: el mensaje se mantiene visible y se desvanece más tarde
        if (p >= 0.92) em = 1;
        else if (p >= 0.65) em = Math.max(0, (p - 0.65) / 0.27);
      } else {
        // Hacia adelante o primera vez: fade in normal
        if (p >= 0.97) em = 1;
        else if (p >= 0.92) em = (p - 0.92) / 0.05;
      }
    }
    DOM.endMessageInner.style.opacity = em.toString();
    DOM.endMessageInner.style.transform = em > 0 ? "translateY(0)" : "translateY(0.75rem)";
  }

  /* Continúa el bucle */
  rafId = requestAnimationFrame(renderFrame);
}

/* Arranca el bucle solo si la sección es visible */
if (DOM.videoSection) {
  const visObs = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) {
        if (!rafId) rafId = requestAnimationFrame(renderFrame);
      } else {
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
      }
    },
    { threshold: 0 },
  );
  visObs.observe(DOM.videoSection);
}

/* Si el video no ha cargado metadata aún, reintenta cuando esté listo */
if (DOM.mainVideo) {
  DOM.mainVideo.addEventListener("loadedmetadata", () => {
    if (!rafId) {
      rafId = requestAnimationFrame(renderFrame);
    }
  });
}

/* ── Fuerza primer frame en móvil ──
   En móvil, los navegadores no decodifican frames hasta que no hay
   una llamada a play(). Hacemos play+immediate pause para forzar
   la decodificación del frame 0. Con playsinline+muted funciona
   sin necesidad de gesto del usuario. */
if (DOM.mainVideo) {
  DOM.mainVideo.play().then(() => DOM.mainVideo.pause()).catch(() => {});
}

/* ── Expande sección solo si hay suficiente buffer ──
   #experiencia empieza en 100vh. Si el video bufferea ≥4s en los
   primeros 5 segundos, se expande a 350vh para scroll scrubbing.
   Si no (conexión lenta), se queda en 100vh, solo se ve el poster,
   y nunca se activa el scroll-scrub. */
if (DOM.videoSection && DOM.mainVideo) {
  const MIN_BUFFER = 4; // segundos mínimos buffereados para expandir
  const TIMEOUT_MS = 5000;
  let expanded = false;
  const startTime = Date.now();

  function getBufferedSeconds() {
    const b = DOM.mainVideo.buffered;
    return b.length > 0 ? b.end(b.length - 1) : 0;
  }

  function tryExpand() {
    if (expanded) return;
    if (DOM.mainVideo.videoWidth > 0 && getBufferedSeconds() >= MIN_BUFFER) {
      DOM.videoSection.classList.add("video-ready");
      expanded = true;
    }
  }

  // Poll cada 500ms hasta alcanzar buffer mínimo o agotar timeout
  const pollId = setInterval(() => {
    tryExpand();
    if (expanded || Date.now() - startTime >= TIMEOUT_MS) {
      clearInterval(pollId);
    }
  }, 500);

  // Intento inicial
  tryExpand();
  // Evento canplay (frame decodificado y listo para reproducir)
  DOM.mainVideo.addEventListener("canplay", tryExpand, { once: true });
}

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

    slides.forEach((slide, i) => {
      slide.classList.toggle("active", i === current);
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

  /* ── Overlay toggle on tap (mobile + desktop) ── */
  slides.forEach((slide, idx) => {
    slide.addEventListener("click", function(e) {
      const ampliar = e.target.closest(".galeria-open-lightbox");
      if (ampliar) {
        openLightbox(parseInt(this.dataset.index));
        return;
      }
      slides.forEach(s => s.classList.remove("overlay-visible"));
      this.classList.toggle("overlay-visible");
    });
  });

  /* ── Lightbox ── */
  function openLightbox(index) {
    if (index >= slides.length) index = 0;
    if (index < 0) index = slides.length - 1;

    stopAuto();

    const overlay = document.createElement("div");
    overlay.className = "lightbox-overlay";

    const img = document.createElement("img");
    img.src = slides[index].dataset.src;
    img.alt = "";
    overlay.appendChild(img);

    const closeBtn = document.createElement("button");
    closeBtn.className = "lightbox-close bi bi-x";
    closeBtn.innerHTML = "✕";
    closeBtn.setAttribute("aria-label", "Cerrar");
    closeBtn.addEventListener("click", function() { closeLightbox(overlay); });
    overlay.appendChild(closeBtn);

    const prevBtn = document.createElement("button");
    prevBtn.className = "lightbox-nav lightbox-prev bi bi-chevron-left";
    prevBtn.innerHTML = "‹";
    prevBtn.setAttribute("aria-label", "Anterior");
    prevBtn.addEventListener("click", function(e) {
      e.stopPropagation();
      overlay.remove();
      openLightbox(index - 1);
    });
    overlay.appendChild(prevBtn);

    const nextBtn = document.createElement("button");
    nextBtn.className = "lightbox-nav lightbox-next bi bi-chevron-right";
    nextBtn.innerHTML = "›";
    nextBtn.setAttribute("aria-label", "Siguiente");
    nextBtn.addEventListener("click", function(e) {
      e.stopPropagation();
      overlay.remove();
      openLightbox(index + 1);
    });
    overlay.appendChild(nextBtn);

    const counter = document.createElement("span");
    counter.className = "lightbox-counter";
    counter.textContent = (index + 1) + " / " + slides.length;
    overlay.appendChild(counter);

    overlay.addEventListener("click", function(e) {
      if (e.target === overlay) closeLightbox(overlay);
    });

    document.body.appendChild(overlay);
  }

  function closeLightbox(overlay) {
    overlay.classList.add("closing");
    setTimeout(function() {
      overlay.remove();
      startAuto();
    }, 250);
  }

  /* ── Touch swipe ── */
  let touchStartX = 0;

  carrusel.addEventListener("touchstart", function(e) {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });

  carrusel.addEventListener("touchend", function(e) {
    const diff = touchStartX - e.changedTouches[0].screenX;
    if (Math.abs(diff) > 50) {
      stopAuto();
      goTo(current + (diff > 0 ? 1 : -1));
      startAuto();
    }
  }, { passive: true });

  /* ── Arranca ── */
  goTo(0);
  startAuto();
})();

/* ================================================================
   RESEÑAS — carrusel con flechas + drag (ratón y táctil)
   ================================================================ */

(function initResenas() {
  const track = document.getElementById("resenasTrack");
  const prev = document.getElementById("resenasPrev");
  const next = document.getElementById("resenasNext");
  if (!track || !prev || !next) {
    setTimeout(initResenas, 300);
    return;
  }

  let current = 0;

  function goTo(i) {
    const total = track.children.length;
    if (i >= total) i = total - 1;
    if (i < 0) i = 0;
    current = i;
    const card = track.children[current];
    const scrollTarget = card.offsetLeft - (track.clientWidth - card.offsetWidth) / 2;
    track.scrollTo({ left: Math.max(0, scrollTarget), behavior: "smooth" });
  }

  prev.addEventListener("click", function() { goTo(current - 1); });
  next.addEventListener("click", function() { goTo(current + 1); });

  /* ── Drag con ratón ── */
  let isDown = false;
  let startX = 0;
  let scrollLeft = 0;

  track.addEventListener("mousedown", (e) => {
    isDown = true;
    track.classList.add("grabbing");
    startX = e.pageX - track.offsetLeft;
    scrollLeft = track.scrollLeft;
  });

  track.addEventListener("mouseleave", () => {
    if (!isDown) return;
    isDown = false;
    track.classList.remove("grabbing");
  });

  track.addEventListener("mouseup", () => {
    isDown = false;
    track.classList.remove("grabbing");
  });

  track.addEventListener("mousemove", (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - track.offsetLeft;
    const walk = (x - startX) * 1.5;
    track.scrollLeft = scrollLeft - walk;
  });

  /* ── Centrar primera tarjeta al cargar ── */
  if (track.children.length > 0) {
    const firstCard = track.children[0];
    const scrollTarget = firstCard.offsetLeft - (track.clientWidth - firstCard.offsetWidth) / 2;
    track.scrollTo({ left: Math.max(0, scrollTarget), behavior: "instant" });
  }
})();
