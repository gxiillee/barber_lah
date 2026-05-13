<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../clases/Usuario.php';

session_start();

function h(mixed $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$detalle = (isset($_SESSION['reserva_confirmada']) && is_array($_SESSION['reserva_confirmada']))
        ? $_SESSION['reserva_confirmada']
        : null;

if ($detalle === null) {
    header('Location: reserva.php');
    exit;
}

$emailEnviado = (bool)($detalle['email_enviado'] ?? false);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita reservada · Barbershop La H</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--obsidian)] font-[var(--font-montserrat)] text-[#f5f0e8]">
    <div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_60%_45%_at_50%_0%,rgba(212,175,55,0.11)_0%,transparent_70%)]"></div>

    <main class="reserve-shell relative z-10 mx-auto flex min-h-screen w-full max-w-4xl items-center px-5 py-20 sm:px-8">
        <section class="w-full rounded-lg border border-white/10 bg-[#0d0d0d] p-6 text-center shadow-[0_28px_90px_rgba(0,0,0,0.65)] sm:p-10">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-[var(--gold)]/30 bg-[var(--gold)]/[0.08] text-3xl text-[var(--gold)]">
                <i class="bi bi-check2"></i>
            </div>
            <p class="mt-6 text-[10px] font-bold uppercase tracking-[0.28em] text-[var(--gold)]">Cita reservada</p>
            <h1 class="mt-3 font-[var(--font-playfair)] text-[44px] font-bold leading-none text-white sm:text-[64px]">Tu cita esta lista</h1>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-white/45">Hassan ya tiene tu hueco guardado. Si necesitas cambiarlo o cancelarlo, avisa a la barberia con antelacion.</p>

            <div class="mx-auto mt-9 grid max-w-3xl gap-3 text-left sm:grid-cols-3">
                <div class="rounded-lg border border-white/10 bg-white/[0.025] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/30">Servicio</p>
                    <p class="mt-2 font-semibold text-white"><?= h($detalle['servicio_nombre'] ?? '') ?></p>
                    <p class="mt-1 text-xs text-white/40"><?= h((string)($detalle['duracion'] ?? '')) ?> min</p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/[0.025] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/30">Dia y hora</p>
                    <p class="mt-2 font-semibold text-white"><?= h($detalle['fecha_label'] ?? '') ?></p>
                    <p class="mt-1 text-xs text-white/40">A las <?= h($detalle['hora'] ?? '') ?></p>
                </div>
                <div class="rounded-lg border border-[var(--gold)]/20 bg-[var(--gold)]/[0.06] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--gold)]">Confirmacion</p>
                    <p class="mt-2 font-semibold text-white">#<?= h($detalle['id_reserva'] ?? '') ?></p>
                    <p class="mt-1 text-xs text-white/40"><?= $emailEnviado ? 'Email enviado' : 'Email pendiente de configurar' ?></p>
                </div>
            </div>

            <div class="mx-auto mt-8 max-w-xl rounded-lg border border-white/10 bg-white/[0.025] px-4 py-4 text-sm leading-7 text-white/45">
                Para cancelar o mover tu cita, contacta con Barbershop La H e indica el numero de reserva.
            </div>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="index.php" class="inline-flex items-center justify-center rounded-lg bg-[var(--gold)] px-6 py-3 text-[11px] font-extrabold uppercase tracking-[0.16em] text-[var(--obsidian)] no-underline transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)]">
                    Volver al inicio
                </a>
                <a href="reserva.php" class="inline-flex items-center justify-center rounded-lg border border-white/10 px-6 py-3 text-[11px] font-bold uppercase tracking-[0.16em] text-white/45 no-underline transition hover:border-[var(--gold)]/35 hover:text-[var(--gold)]">
                    Reservar otra cita
                </a>
            </div>
        </section>
    </main>
</body>
</html>
