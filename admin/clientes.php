<?php
declare(strict_types=1);
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../clases/Administrador.php';
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';

iniciarSesionSegura();
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']->tieneRolAdmin()) {
    redirigir('../login.php');
}

// Search and filter handling
$busqueda = trim($_GET['buscar'] ?? '');
$filtro = $_GET['filter'] ?? 'todos';
$filtros_validos = ['todos', 'puntos', 'nuevos'];
if (!in_array($filtro, $filtros_validos, true)) {
    $filtro = 'todos';
}
if ($busqueda !== '') {
    $clientes = Administrador::buscarClientes($busqueda);
} else {
    $clientes = Administrador::obtenerTodosLosClientes($filtro);
}

$pagina_activa = 'clientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes — Panel Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
    <style>
        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: all 0.2s;
            color: var(--tx-d);
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--brd);
            text-decoration: none;
            white-space: nowrap;
        }
        .filter-tab:hover {
            background: rgba(255,255,255,0.08);
            color: var(--tx);
        }
        .filter-tab.active {
            background: rgba(212,175,55,0.12);
            border-color: rgba(212,175,55,0.4);
            color: var(--gold);
        }

        /* === Client Card ===
           Aislada a propósito: no reutiliza slot-card/glow-card porque esas
           clases están pensadas para otro layout (slots de agenda) y al
           mezclarlas aquí con flex rompían la fila en tarjetas con
           avatar + info + stats. Mobile-first: columna en móvil, fila desde sm. */
        .client-card {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            padding: 0.95rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--brd);
            background: rgba(255,255,255,0.05);
            text-decoration: none;
            color: inherit;
            transition: background 0.2s, border-color 0.2s, transform 0.2s;
        }
        .client-card:hover {
            background: rgba(255,255,255,0.075);
            border-color: rgba(212,175,55,0.4);
            transform: translateY(-1px);
        }

        .client-card__top {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            min-width: 0;
        }

        .client-card__avatar {
            width: 2.5rem;
            height: 2.5rem;
            flex-shrink: 0;
            border-radius: 999px;
            background: #1a1a1a;
            border: 1px solid var(--brd);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--tx-m);
        }

        .client-card__info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .client-card__nombre {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--tx);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .client-card__linea {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.65rem;
            color: var(--tx-m);
            min-width: 0;
        }
        .client-card__linea--dim {
            color: var(--tx-d);
            flex-wrap: nowrap;
            overflow: hidden;
        }
        .client-card__linea .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
        }

        .client-card__sep {
            color: var(--tx-m);
            margin: 0 0.1rem;
        }
        .client-card__visita {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.6rem;
            color: var(--tx-m);
            flex-shrink: 0;
        }

        .client-card__stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding-top: 0.7rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .client-card__stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.1rem;
        }
        .client-card__stat-num {
            font-size: 0.85rem;
            font-weight: 700;
            line-height: 1;
        }
        .client-card__stat-label {
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--tx-d);
        }

        .client-card__badge {
            font-size: 0.55rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #34d399;
            background: rgba(52,211,153,0.12);
            border: 1px solid rgba(52,211,153,0.3);
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            white-space: nowrap;
        }
        .client-card__badge--pending {
            color: #34d399;
            background: rgba(52,211,153,0.12);
            border-color: rgba(52,211,153,0.3);
        }

        /* Desde sm (≥640px) la tarjeta pasa a fila: info a la izquierda, stats a la derecha */
        @media (min-width: 640px) {
            .client-card {
                flex-direction: row;
                align-items: center;
                gap: 1rem;
            }
            .client-card__top {
                flex: 1;
                min-width: 0;
            }
            .client-card__stats {
                flex-direction: column;
                align-items: flex-end;
                padding-top: 0;
                border-top: none;
                border-left: 1px solid rgba(255,255,255,0.08);
                padding-left: 0.9rem;
                gap: 0.3rem;
            }
            .client-card__stats .client-card__stat {
                flex-direction: row;
                gap: 0.35rem;
            }
            .client-card__stat-num {
                order: 2;
            }
            .client-card__stat-label {
                order: 1;
            }
            .client-card__badge {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-[var(--bg)] text-[var(--tx)] font-sans">

<?php include_once __DIR__ . '/includes/nav_admin.php'; ?>

<main class="pt-[80px] pb-[96px] px-4 max-w-[720px] mx-auto lg:ml-[240px] lg:mr-auto lg:pt-10 lg:pb-16 lg:px-10 lg:max-w-none pagina-entrada">

    <div class="mb-6">
        <h1 class="text-[1.6rem] font-semibold text-[var(--tx)] leading-tight" style="font-family: var(--pf);">Directorio de Clientes</h1>
        <p class="text-[0.72rem] text-[var(--tx-m)] tracking-[0.04em] mt-1">Busca, consulta y gestiona tus clientes</p>
    </div>

    <!-- Search bar -->
    <div class="mb-5">
        <form action="" method="GET" id="searchForm" class="relative">
            <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--tx-d)] text-[0.9rem] pointer-events-none"></i>
            <input type="text" name="buscar" id="searchInput" value="<?= h($busqueda) ?>"
                   placeholder="Buscar por nombre o email..."
                   autocomplete="off"
                   class="w-full bg-[#141414] border border-[var(--brd)] rounded-xl pl-10 pr-4 py-3 text-[0.82rem] text-[var(--tx)] focus:outline-hidden focus:border-[var(--gold-brd)] transition-all placeholder:text-[var(--tx-d)]/60">
            <?php if ($busqueda !== ''): ?>
                <a href="clientes.php" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-white/10 flex items-center justify-center text-[var(--tx-d)] hover:bg-white/20 hover:text-[var(--tx)] transition-all">
                    <i class="bi bi-x text-[0.7rem]"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($busqueda !== '' && !empty($clientes)): ?>
        <div class="mb-4 text-[0.7rem] text-[var(--tx-m)]">
            <?= count($clientes) ?> resultado<?= count($clientes) !== 1 ? 's' : '' ?> para "<strong class="text-[var(--tx)]"><?= h($busqueda) ?></strong>"
        </div>
    <?php endif; ?>

    <?php if ($busqueda === ''): ?>
        <div class="flex flex-wrap gap-2 mb-5" id="filterTabs">
            <a href="clientes.php" class="filter-tab <?= $filtro === 'todos' ? 'active' : '' ?>">
                Todos
            </a>
            <a href="clientes.php?filter=puntos" class="filter-tab <?= $filtro === 'puntos' ? 'active' : '' ?>">
                Con puntos 🎁
            </a>
            <a href="clientes.php?filter=nuevos" class="filter-tab <?= $filtro === 'nuevos' ? 'active' : '' ?>">
                Nuevos este mes
            </a>
        </div>
    <?php endif; ?>

    <section class="space-y-2">
        <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="text-[0.68rem] uppercase tracking-widest font-bold text-[var(--tx-d)]">
                <?= $busqueda ? 'Resultados' : 'Cartera de Clientes' ?> (<?= count($clientes) ?>)
            </h2>
        </div>

        <?php if (empty($clientes)): ?>
            <div class="flex flex-col items-center justify-center py-16 border border-[var(--brd)] bg-white/5 rounded-xl text-center gap-3 opacity-60">
                <i class="bi bi-people text-3xl text-[var(--tx-d)]"></i>
                <p class="text-[0.75rem] text-[var(--tx-m)]">
                    <?php if ($busqueda): ?>
                        No se encontraron clientes con ese criterio.
                    <?php elseif ($filtro === 'puntos'): ?>
                        Ningún cliente ha alcanzado el corte gratis aún.
                    <?php elseif ($filtro === 'nuevos'): ?>
                        No hay clientes nuevos este mes.
                    <?php else: ?>
                        Aún no hay clientes registrados en el sistema.
                    <?php endif; ?>
                </p>
                <?php if ($busqueda || $filtro !== 'todos'): ?>
                    <a href="clientes.php" class="text-[0.7rem] text-[var(--gold)] underline underline-offset-2 hover:opacity-80">Ver todos los clientes</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 stagger-container" id="clientesGrid">
                <?php foreach ($clientes as $c): ?>
                    <div onclick="window.location.href='ficha_cliente.php?id_cliente=<?= (int)$c['id'] ?>'" class="client-card cursor-pointer">

                        <div class="client-card__top">
                            <div class="client-card__avatar">
                                <?= substr(h($c['nombre']), 0, 1) ?>
                            </div>

                            <div class="client-card__info">
                                <div class="client-card__nombre">
                                    <?= h($c['nombre']) ?>
                                </div>

                                <?php if (!empty($c['telefono'])): ?>
                                    <div class="client-card__linea">
                                        <i class="bi bi-telephone text-[var(--gold)]"></i>
                                        <span class="truncate"><?= h($c['telefono']) ?></span>
                                        <a href="https://wa.me/34<?= h(preg_replace('/\D/', '', $c['telefono'])) ?>?text=<?= rawurlencode('Hola, soy Hassan de Barbershop La H.') ?>"
                                           target="_blank"
                                           class="inline-flex text-[#25D366] hover:opacity-80 transition-opacity shrink-0"
                                           title="Enviar WhatsApp"
                                           onclick="event.stopPropagation()">
                                            <i class="bi bi-whatsapp text-[0.8rem]"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div class="client-card__linea client-card__linea--dim">
                                    <i class="bi bi-envelope"></i>
                                    <span class="truncate"><?= h($c['email']) ?></span>
                                    <?php if (!empty($c['ultima_visita'])): ?>
                                        <span class="client-card__sep">·</span>
                                        <span class="client-card__visita">
                                            <i class="bi bi-clock-history"></i>
                                            <?= h(fechaHumana($c['ultima_visita'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="client-card__stats">
                            <div class="client-card__stat">
                                <span class="client-card__stat-num text-[var(--gold)]"><?= (int)$c['puntos_fidelidad'] ?></span>
                                <span class="client-card__stat-label">Puntos</span>
                            </div>

                            <div class="client-card__stat">
                                <span class="client-card__stat-num text-[var(--tx)]"><?= (int)$c['total_reservas'] ?></span>
                                <span class="client-card__stat-label">Citas</span>
                            </div>

                            <?php if (!empty($c['tiene_gratis_pendiente'])): ?>
                                <span class="client-card__badge client-card__badge--pending">🎁 Por canjear</span>
                            <?php elseif (!empty($c['tiene_puntos'])): ?>
                                <span class="client-card__badge">🎁 Próxima gratis</span>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php include_once __DIR__ . '/includes/toast.php'; ?>

<script>
// Live search with debounce (preserves filter)
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    let timeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            const filterParam = new URLSearchParams(window.location.search).get('filter');
            let url;
            if (this.value.trim() !== '') {
                url = 'clientes.php?buscar=' + encodeURIComponent(this.value.trim());
                if (filterParam) url += '&filter=' + encodeURIComponent(filterParam);
            } else {
                url = 'clientes.php' + (filterParam ? '?filter=' + encodeURIComponent(filterParam) : '');
            }
            window.location.href = url;
        }, 400);
    });
}
</script>

</body>
</html>
