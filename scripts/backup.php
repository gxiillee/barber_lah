<?php
/**
 * backup.php — Script de respaldo automático de la BD MySQL
 *
 * Uso desde cron (diario):
 *   php /ruta/a/scripts/backup.php
 *
 * Guarda en /backups/ los últimos 30 días.
 * Los archivos más antiguos se eliminan solos.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$host      = $_ENV['DB_HOST'] ?? 'localhost';
$puerto    = $_ENV['DB_PORT'] ?? '3306';
$bd        = $_ENV['DB_NAME'] ?? 'barberlah';
$usuario   = $_ENV['DB_USER'] ?? 'root';
$contrasena = $_ENV['DB_PASS'] ?? '';

$dirBackups = __DIR__ . '/../backups';

if (!is_dir($dirBackups)) {
    mkdir($dirBackups, 0755, true);
}

$fecha = date('Y-m-d_H-i');
$archivo = "$dirBackups/{$bd}_{$fecha}.sql.gz";

$mysqldump = 'mysqldump';
if (PHP_OS_FAMILY === 'Windows') {
    $posibles = ['C:\xampp\mysql\bin\mysqldump.exe', 'C:\wamp64\bin\mysql\bin\mysqldump.exe'];
    foreach ($posibles as $p) {
        if (file_exists($p)) { $mysqldump = $p; break; }
    }
}

$archivoSql = "$dirBackups/{$bd}_{$fecha}.sql";
$cmd = sprintf(
    '%s --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
    $mysqldump,
    escapeshellarg($host),
    escapeshellarg($puerto),
    escapeshellarg($usuario),
    escapeshellarg($contrasena),
    escapeshellarg($bd),
    escapeshellarg($archivoSql)
);

$salida = null;
$codigo = null;
exec($cmd, $salida, $codigo);

if ($codigo !== 0) {
    echo "[ERROR] mysqldump falló (código $codigo)" . PHP_EOL;
    exit(1);
}

$contenido = file_get_contents($archivoSql);
unlink($archivoSql);
if ($contenido === false) {
    echo "[ERROR] No se pudo leer el dump temporal." . PHP_EOL;
    exit(1);
}

$gz = gzencode($contenido, 9);
$archivoGz = "$archivoSql.gz";
if (file_put_contents($archivoGz, $gz) === false) {
    echo "[ERROR] No se pudo comprimir el backup." . PHP_EOL;
    exit(1);
}

// ── Respaldo de archivos (uploads + assets/img) ─────────────────
$dirsArchivos = [
    __DIR__ . '/../public/uploads',
    __DIR__ . '/../public/assets/img',
];
$archivoZip = "$dirBackups/{$bd}_{$fecha}_files.zip";

$zip = new ZipArchive();
if ($zip->open($archivoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    foreach ($dirsArchivos as $dir) {
        $base = dirname($dir, 2); // recortar ruta absoluta para paths relativos
        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterador as $archivo) {
            /** @var SplFileInfo $archivo */
            $rutaRelativa = str_replace($base . '\\', '', $archivo->getPathname());
            $rutaRelativa = str_replace($base . '/', '', $rutaRelativa);
            $zip->addFile($archivo->getPathname(), $rutaRelativa);
        }
    }
    $zip->close();
    echo "[OK] Backup de archivos creado: " . basename($archivoZip) . PHP_EOL;
} else {
    echo "[AVISO] No se pudo crear el zip de archivos (ZipArchive no disponible?)." . PHP_EOL;
}

$limite = strtotime('-30 days');
$eliminados = 0;
foreach (glob("$dirBackups/{$bd}_*.sql.gz") as $f) {
    if (filemtime($f) < $limite) {
        unlink($f);
        $eliminados++;
    }
}
foreach (glob("$dirBackups/{$bd}_*_files.zip") as $f) {
    if (filemtime($f) < $limite) {
        unlink($f);
        $eliminados++;
    }
}

if ($eliminados > 0) {
    echo "[OK] $eliminados backup(s) antiguo(s) eliminados." . PHP_EOL;
}

echo "[OK] Respaldo completado." . PHP_EOL;
