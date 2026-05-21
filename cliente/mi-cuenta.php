<?php

// os dejo este archivo simulando si fuese el panel del cliente donde puede ver
// las funciones susyas de ver sus cortes y tal.
//en un futuro sera su panel, es para que las rutas me funcionen al 100%

//Evita calculos raro scon datos que suban a la bd mal, como en vez de un int, un string o ""
declare(strict_types=1);

require_once __DIR__ . '/../clases/Usuario.php';

session_start();

$usuario = $_SESSION['usuario'] ?? null;

// Nueva puerta separada para el futuro area cliente:
// si no hay sesion, manda al login con contexto de panel, no al flujo de reserva.
if (!$usuario instanceof Usuario) {
    header('Location: ../public/login.php?source=panel');
    exit;
}

// Cuando exista el panel real, este archivo actuara como pasarela limpia.
$panelCliente = __DIR__ . '/cliente/index.php';
if (file_exists($panelCliente)) {
    header('Location: cliente/index.php');
    exit;
}

function h(mixed $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi cuenta · Barbershop La H</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="min-h-screen bg-[var(--obsidian)] font-[var(--font-montserrat)] text-[#f5f0e8]">
    <main class="reserve-shell mx-auto flex min-h-screen w-full max-w-3xl items-center px-6 py-16">
        <section class="w-full rounded-lg border border-white/10 bg-[#0d0d0d] p-8 text-center shadow-[0_28px_90px_rgba(0,0,0,0.65)]">
            <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-[var(--gold)]">Area cliente</p>
            <h1 class="mt-3 font-[var(--font-playfair)] text-[44px] font-bold leading-none text-white">Hola, <?= h($usuario->getNombre()) ?></h1>
            <h1 class="mt-3 font-[var(--font-playfair)] text-[44px] font-bold leading-none text-white">Hola, <?= h($usuario->getEmail()) ?></h1>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-white/45">
                Tu cuenta ya tiene una puerta propia. El panel completo llegara aqui: citas, historial e imagenes guardadas.
            </p>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="../public/reserva.php" class="inline-flex items-center justify-center rounded-lg bg-[var(--gold)] px-6 py-3 text-[11px] font-extrabold uppercase tracking-[0.16em] text-[var(--obsidian)] no-underline transition hover:-translate-y-0.5 hover:bg-[var(--gold-light)]">
                    Reservar cita
                </a>
                <a href="../cliente/index.php" class="inline-flex items-center justify-center rounded-lg border border-white/10 px-6 py-3 text-[11px] font-bold uppercase tracking-[0.16em] text-white/45 no-underline transition hover:border-[var(--gold)]/35 hover:text-[var(--gold)]">
                    ir al panel
                </a>
                <a href="../public/cierrases.php" class="inline-flex items-center justify-center rounded-lg border border-red/10 px-6 py-3 text-[11px] font-bold uppercase tracking-[0.16em] text-white/45 no-underline transition hover:border-[var(--gold)]/35 hover:text-[var(--gold)]">
                    Cierra sesion
                </a>

            </div>
        </section>
    </main>
</body>
</html>
