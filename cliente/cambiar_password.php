<?php
declare(strict_types=1);

// ── Fase 1: Dependencias ──────────────────────────────────────────
require_once __DIR__ . '/../clases/BD.php';
require_once __DIR__ . '/../clases/helpers.php';
require_once __DIR__ . '/../clases/Usuario.php';

// ── Fase 2: Control de acceso ─────────────────────────────────────
session_start();
if (!isset($_SESSION['usuario'])) {
    redirigir('../login.php');
}

/** @var Usuario $usuario */
$usuario = $_SESSION['usuario'];
$pagina_activa = 'perfil'; // Para que mantenga iluminado el nav de perfil

$error = '';
$exito = '';

// ── Fase 3: Procesar el Formulario ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva  = $_POST['password_nueva'] ?? '';
    $password_conf   = $_POST['password_confirmar'] ?? '';

    if (empty($password_actual) || empty($password_nueva) || empty($password_conf)) {
        $error = 'Por favor, rellena todos los campos.';
    } elseif ($password_nueva !== $password_conf) {
        $error = 'La nueva contraseña y su confirmación no coinciden.';
    } else {
        try {
            $db = BD::obtenerConexion();

            // 1. Recuperamos el hash de la contraseña actual desde la BD para comparar
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $usuario->getId()]);
            $hash_actual = $stmt->fetchColumn();

            // 2. Verificamos si la contraseña vieja introducida coincide con el hash
            if ($hash_actual && password_verify($password_actual, $hash_actual)) {

                // 3. Generamos el nuevo hash seguro con PHP
                $nuevo_hash = password_hash($password_nueva, PASSWORD_BCRYPT, ['cost' => 12]);

                // 4. Actualizamos en la base de datos
                $stmtUpdate = $db->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
                $stmtUpdate->execute([
                    'password' => $nuevo_hash,
                    'id'       => $usuario->getId()
                ]);

                $exito = 'Contraseña actualizada correctamente.';
            } else {
                $error = 'La contraseña actual no es correcta.';
            }

        } catch (Exception $e) {
            $error = 'Ocurrió un error en el servidor. Inténtalo de nuevo.';
            error_log("Error al cambiar contraseña: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña — Barbershop La H</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/assets/css/estilos.css">
</head>
<body class="pagina-cliente min-h-screen body-panel">

<?php require_once __DIR__ . '/includes/nav_cliente.php'; ?>

<main class="pt-14 pb-20 lg:pt-0 lg:pb-0 min-h-screen flex flex-col panel-main">
    <div class="flex-1 w-full max-w-md mx-auto p-4 sm:p-6 flex flex-col gap-6 justify-center">

        <div class="flex items-center justify-between">
            <a href="perfil.php" class="flex items-center gap-2 text-[var(--tx-m)] hover:text-[var(--tx)] transition-all text-sm">
                <i class="bi bi-arrow-left"></i>
                Volver a mi perfil
            </a>
        </div>

        <div class="bg-[var(--bg2)] border border-[var(--brd)] rounded-2xl p-6 shadow-xl">
            <h2 class="text-[var(--tx)] font-semibold text-lg mb-1">Seguridad de la cuenta</h2>
            <p class="text-[var(--tx-m)] text-xs mb-6">Actualiza tu contraseña para mantener tu perfil protegido.</p>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= h($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($exito)): ?>
                <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-xs flex items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= h($exito) ?></span>
                </div>
            <?php endif; ?>

            <form action="cambiar_password.php" method="POST" class="flex flex-col gap-4">

                <div class="flex flex-col gap-1.5">
                    <label for="password_actual" class="text-[var(--tx-m)] text-xs font-medium">Contraseña Actual</label>
                    <input type="password" id="password_actual" name="password_actual" required
                           class="w-full bg-[#161616] border border-[var(--brd)] rounded-xl px-4 py-3 text-sm text-[var(--tx)] focus:outline-none focus:border-[var(--gold-brd)] transition-all">
                </div>

                <hr class="border-[var(--brd)] my-1">

                <div class="flex flex-col gap-1.5">
                    <label for="password_nueva" class="text-[var(--tx-m)] text-xs font-medium">Nueva Contraseña</label>
                    <input type="password" id="password_nueva" name="password_nueva" required
                           class="w-full bg-[#161616] border border-[var(--brd)] rounded-xl px-4 py-3 text-sm text-[var(--tx)] focus:outline-none focus:border-[var(--gold-brd)] transition-all">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password_confirmar" class="text-[var(--tx-m)] text-xs font-medium">Confirmar Nueva Contraseña</label>
                    <input type="password" id="password_confirmar" name="password_confirmar" required
                           class="w-full bg-[#161616] border border-[var(--brd)] rounded-xl px-4 py-3 text-sm text-[var(--tx)] focus:outline-none focus:border-[var(--gold-brd)] transition-all">
                </div>

                <button type="submit"
                        class="w-full mt-2 p-3.5 rounded-xl bg-[var(--gold-dim)] text-[var(--gold)] border border-[var(--gold-brd)] hover:bg-[var(--gold)] hover:text-black transition-all font-semibold text-sm cursor-pointer active:scale-[0.99]">
                    Actualizar Contraseña
                </button>

            </form>
        </div>

    </div>
</main>

</body>
</html>