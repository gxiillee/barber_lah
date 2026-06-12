<?php
/**
 * toast.php — Sistema de notificaciones toast para el panel de cliente.
 *
 * Incluir justo antes de </body> en cualquier página del panel.
 * Uso desde PHP:
 *   $_SESSION['toast'] = ['type' => 'success', 'message' => 'Foto eliminada'];
 * Uso desde JS:
 *   Toast.mostrar('success', 'Cita confirmada');
 */
?>
<!-- Toast container (se crea una vez con JS) -->
<script>
/**
 * Sistema de notificaciones toast — Barbershop La H
 * 
 * Uso: Toast.mostrar('success', 'Mensaje aquí')
 * Tipos: success, error, info
 * Tiempo: 4 segundos por defecto
 */
(function() {
    const TYPES = {
        success: { icon: 'bi-check-circle-fill', cls: 'toast-success' },
        error:   { icon: 'bi-exclamation-circle-fill', cls: 'toast-error' },
        info:    { icon: 'bi-info-circle-fill', cls: 'toast-info' },
    };

    let container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function mostrar(tipo, mensaje, duracion) {
        if (!mensaje) return;
        const cfg = TYPES[tipo] || TYPES.info;
        duracion = duracion || 4000;

        const el = document.createElement('div');
        el.className = 'toast ' + cfg.cls;
        el.innerHTML = '<span class="toast-icon"><i class="bi ' + cfg.icon + '"></i></span>'
                     + '<span>' + mensaje + '</span>';

        getContainer().appendChild(el);

        // Auto-destruir después de la duración
        setTimeout(() => {
            el.classList.add('closing');
            setTimeout(() => { if (el.parentNode) el.remove(); }, 260);
        }, duracion);

        // Cerrar al hacer clic
        el.addEventListener('click', function() {
            if (el.classList.contains('closing')) return;
            el.classList.add('closing');
            setTimeout(() => { if (el.parentNode) el.remove(); }, 260);
        });
    }

    // Exponer globalmente
    window.Toast = { mostrar: mostrar };

    // Toast desde PHP (flash message en sesión)
    <?php if (isset($_SESSION['toast']) && is_array($_SESSION['toast'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            mostrar(
                '<?= $_SESSION['toast']['type'] ?? 'info' ?>',
                '<?= addslashes($_SESSION['toast']['message'] ?? '') ?>'
            );
        });
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>
})();
</script>