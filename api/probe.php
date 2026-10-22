<?php
// api/probe.php - diagnostik timing lambda Vercel (SEMENTARA)
error_reporting(E_ALL);
ini_set('display_errors', '1');

$t0 = microtime(true);
echo "boot=" . number_format((microtime(true) - $t0) * 1000, 0) . "ms\n";
flush();

$tpre = microtime(true);
$root = dirname(__DIR__);
require_once $root . '/includes/db_adapter.php';
echo "require_adapter=" . number_format((microtime(true) - $tpre) * 1000, 0) . "ms\n";
flush();

$tcon = microtime(true);
try {
    $dsn  = SUPABASE_DSN; $user = SUPABASE_USER; $pass = SUPABASE_PASSWORD;
    $pdo  = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 20]);
    echo "connect=" . number_format((microtime(true) - $tcon) * 1000, 0) . "ms\n";
} catch (Throwable $e) {
    echo "connect GAGAL: " . $e->getMessage() . "\n";
}
flush();

$tq = microtime(true);
try { $pdo->query('SELECT 1')->fetchColumn(); echo "query1=" . number_format((microtime(true) - $tq) * 1000, 0) . "ms\n"; } catch (Throwable $e) { echo "query GAGAL: " . $e->getMessage() . "\n"; }
flush();

$tq2 = microtime(true);
try { $pdo->query('SELECT COUNT(*) FROM konsumen')->fetchColumn(); echo "query2=" . number_format((microtime(true) - $tq2) * 1000, 0) . "ms total=" . number_format((microtime(true) - $t0) * 1000, 0) . "ms\n"; } catch (Throwable $e) { echo "query GAGAL: " . $e->getMessage() . "\n"; }
flush();