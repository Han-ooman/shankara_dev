<?php
// api/probe.php — diagnostik environment lambda Vercel (SEMENTARA)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo "PHP=" . PHP_VERSION . " cwd=" . getcwd() . "\n";
echo "REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? '-') . "\n";
echo "SCRIPT_NAME=" . ($_SERVER['SCRIPT_NAME'] ?? '-') . "\n";

$root = dirname(__DIR__);
foreach (['index.php', 'includes/koneksi.php', 'includes/db_adapter.php', 'config/local.php', 'vendor/autoload.php'] as $rel) {
    echo "is_file[$rel]=" . (is_file($root . '/' . $rel) ? 'YA' : 'TIDAK') . "\n";
}

echo "env SUPABASE_DSN=" . (getenv('SUPABASE_DSN') ? 'ADA' : 'TIDAK') . "\n";

try {
    require_once $root . '/includes/db_adapter.php';
    echo "adapter dimuat\n";
} catch (Throwable $e) {
    echo "adapter GAGAL: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

try {
    $dsn  = SUPABASE_DSN;
    $user = SUPABASE_USER;
    $pass = SUPABASE_PASSWORD;
    $pdo  = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
    echo "koneksi PG OK, server=" . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
} catch (Throwable $e) {
    echo "koneksi GAGAL: " . $e->getMessage() . "\n";
}
