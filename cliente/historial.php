<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

// ── Fase 1: Carga de dependencias ─────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Reserva.php';

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
// Actualizar estado de citas pasadas antes de consultar el historial
Reserva::actualizarCitasPasadas();

$id_usuario = (int)$usuario->getId();
$historial  = Reserva::obtenerHistorialPorCliente($id_usuario);
$pagina_activa = 'historial';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Historial — Barbershop La H</title>

    <!-- Tailwind CSS v4 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Fuentes y Iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Estilos globales -->
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php require_once __DIR__ . '/includes/nav_cliente.php'; ?>

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col pagina-entrada panel-main">
    <div class="flex-1 w-full max-w-4xl mx-auto p-4 sm:p-6 lg:p-8 flex flex-col gap-6">

        <!-- Cabecera interna -->
        <div class="flex flex-col gap-1">
            <h1 class="font-[var(--pf)] text-3xl sm:text-4xl font-semibold text-[var(--tx)]">
                Mi Historial
            </h1>
            <p class="text-[0.62rem] text-[var(--tx-m)] tracking-[0.22em] uppercase">
                TU HISTORIAL DE VISITAS
            </p>
        </div>

        <!-- Banner de Google Reviews -->
        <div class="rounded-2xl bg-gradient-to-br from-[#161616] to-[#0a0a0a] border border-[var(--gold-brd)] p-5 sm:p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex flex-col items-center sm:items-start text-center sm:text-left gap-2">
                <div class="flex items-center gap-1 text-[var(--gold)] text-lg">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                </div>
                <h2 class="text-[var(--tx)] font-semibold text-lg">¿Te ha gustado tu corte?</h2>
                <p class="text-[var(--tx-m)] text-sm max-w-sm">
                    Tu opinión nos ayuda a crecer. Valora tu experiencia con Hassan en Google.
                </p>
            </div>
            <a href="https://search.google.com/local/writereview?placeid=ChIJUbsCcgAVWQ0RY94kRz2CmIA" target="_blank"
               class="px-6 py-2.5 rounded-lg bg-[var(--gold)] text-[var(--bg)] font-bold text-sm uppercase tracking-wide transition-all hover:opacity-90 hover:scale-[1.02] flex items-center gap-2">
               <i class="bi bi-google"></i>
               Valorar ahora
            </a>
        </div>

        <!-- Listado de Citas -->
        <div class="flex flex-col gap-4">
            <?php if (empty($historial)): ?>
                <!-- Estado Vacío -->
                <div class="bg-[var(--bg2)] border border-[var(--brd)] rounded-2xl p-10 flex flex-col items-center text-center gap-5">
                    <div class="w-16 h-16 rounded-full bg-[var(--card)] flex items-center justify-center border border-[var(--brd)]">
                        <i class="bi bi-calendar2-x text-[var(--tx-d)] text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-[var(--tx)] font-semibold text-xl mb-2">Aún no tienes citas</h3>
                        <p class="text-[var(--tx-m)] text-sm max-w-xs mx-auto">
                            Tu historial aparecerá aquí en cuanto realices tu primera visita a la barbería.
                        </p>
                    </div>
                    <a href="reservar.php" class="px-8 py-3 rounded-xl bg-[var(--gold)] text-[var(--bg)] font-bold uppercase tracking-wider transition-all hover:opacity-90">
                        Reservar mi primera cita
                    </a>
                </div>
            <?php else: ?>
                <!-- Bucle de Citas -->
                <?php foreach ($historial as $cita): ?>
                    <div class="bg-[var(--bg2)] border border-[var(--brd)] rounded-2xl p-5 transition-all duration-200 hover:border-[var(--brd-h)] hover:-translate-y-0.5 flex items-center justify-between gap-4">
                        <div class="flex flex-col gap-1.5">
                            <span class="text-[var(--tx)] font-semibold text-lg leading-tight">
                                <?= h($cita['nombre_servicio']) ?>
                            </span>
                            <div class="flex flex-wrap items-center gap-3 text-[var(--tx-m)] text-sm">
                                <span class="flex items-center gap-1.5">
                                    <i class="bi bi-calendar3 text-[var(--gold)]"></i>
                                    <?= h(fechaHumana($cita['fecha'])) ?>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <i class="bi bi-clock text-[var(--gold)]"></i>
                                    <?= h(substr($cita['hora'], 0, 5)) ?>h
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-2.5 shrink-0">
                            <span class="text-[var(--tx)] font-bold text-lg">
                                <?= number_format((float)$cita['precio_historico'], 2, ',', '.') ?> €
                            </span>
                            
                            <?php
                            $badgeClass = "";
                            $estadoLabel = "";
                            
                            switch($cita['estado']) {
                                case 'completada':
                                    $badgeClass = "bg-green-500/10 text-green-400 border-green-500/20";
                                    $estadoLabel = "Completada";
                                    break;
                                case 'cancelada':
                                    $badgeClass = "bg-red-500/10 text-red-400 border-red-500/20";
                                    $estadoLabel = "Cancelada";
                                    break;
                                case 'no_presentado':
                                    $badgeClass = "bg-orange-500/10 text-orange-400 border-orange-500/20";
                                    $estadoLabel = "No presentado";
                                    break;
                                case 'confirmada':
                                case 'pendiente':
                                    $badgeClass = "bg-[var(--gold-dim)] text-[var(--gold)] border-[var(--gold-brd)]";
                                    $estadoLabel = "Próxima";
                                    break;
                                default:
                                    $badgeClass = "bg-white/5 text-white/40 border-white/10";
                                    $estadoLabel = ucfirst($cita['estado']);
                            }
                            ?>
                            <span class="px-3 py-1 rounded-full text-[0.65rem] font-bold uppercase tracking-wider border <?= $badgeClass ?>">
                                <?= h($estadoLabel) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>
