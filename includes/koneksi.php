<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/security.php';

// Cek mode maintenance — skip jika halaman maintenance atau toggle
$_self = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($_self !== 'maintenance.php' && $_self !== 'toggle_maintenance.php') {
    $mfile = __DIR__ . '/.maintenance';
    if (file_exists($mfile)) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($scriptName, '/api/') !== false) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Sistem sedang dalam mode maintenance. Coba lagi nanti.',
                'timestamp' => date('c')
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        include __DIR__ . '/../maintenance.php';
        exit();
    }
}

// Koneksi via adapter mysqli-compat -> Supabase PostgreSQL (PDO pgsql)
require_once __DIR__ . '/db_adapter.php';

// Parse or construct Supabase DSN robustly (support postgresql:// URIs from Supabase pooler)
$raw_dsn = getenv('SUPABASE_DSN') ?: (defined('SUPABASE_DSN') ? SUPABASE_DSN : '');
$env_user = getenv('SUPABASE_USER') ?: (defined('SUPABASE_USER') ? SUPABASE_USER : '');
$env_pass = getenv('SUPABASE_PASSWORD') ?: (defined('SUPABASE_PASSWORD') ? SUPABASE_PASSWORD : '');

if (!empty($raw_dsn) && (str_starts_with($raw_dsn, 'postgresql://') || str_starts_with($raw_dsn, 'postgres://'))) {
    $parsed = parse_url($raw_dsn);
    $host = $parsed['host'] ?? 'aws-0-ap-southeast-1.pooler.supabase.com';
    $port = $parsed['port'] ?? '6543';
    $db = ltrim($parsed['path'] ?? '/postgres', '/');
    $user = $env_user !== '' ? $env_user : ($parsed['user'] ?? 'postgres');
    $pass = $env_pass !== '' ? $env_pass : ($parsed['pass'] ?? '');
    
    if (!defined('SUPABASE_DSN')) define('SUPABASE_DSN', "pgsql:host={$host};port={$port};dbname={$db};sslmode=require");
    if (!defined('SUPABASE_USER')) define('SUPABASE_USER', $user);
    if (!defined('SUPABASE_PASSWORD')) define('SUPABASE_PASSWORD', $pass);
} elseif (!empty($raw_dsn) && str_starts_with($raw_dsn, 'pgsql:')) {
    if (!defined('SUPABASE_DSN')) define('SUPABASE_DSN', $raw_dsn);
    if (!defined('SUPABASE_USER')) define('SUPABASE_USER', $env_user !== '' ? $env_user : 'postgres');
    if (!defined('SUPABASE_PASSWORD')) define('SUPABASE_PASSWORD', $env_pass !== '' ? $env_pass : '');
} else {
    // Default fallback using Supabase Pooler (port 6543) for serverless reliability
    $host = getenv('SUPABASE_HOST') ?: 'aws-0-ap-southeast-1.pooler.supabase.com';
    $port = getenv('SUPABASE_PORT') ?: '6543';
    $db   = getenv('SUPABASE_DB') ?: 'postgres';
    $user = $env_user !== '' ? $env_user : 'postgres';
    $pass = $env_pass;
    
    if (!defined('SUPABASE_DSN')) define('SUPABASE_DSN', "pgsql:host={$host};port={$port};dbname={$db};sslmode=require");
    if (!defined('SUPABASE_USER')) define('SUPABASE_USER', $user);
    if (!defined('SUPABASE_PASSWORD')) define('SUPABASE_PASSWORD', $pass);
}

$koneksi = sdb_connect(SUPABASE_DSN, SUPABASE_USER, SUPABASE_PASSWORD, 'postgres');

if (!($koneksi instanceof sdb) || $koneksi->connect_errno) {
    $dsnForDisplay = preg_replace('/password=[^;]+/', 'password=***', SUPABASE_DSN ?? '');
    $errMsg = $koneksi->connect_error ?? 'Unknown connection error';
    error_log("Koneksi DB gagal: DSN={$dsnForDisplay} Error={$errMsg}");
    
    http_response_code(500);
    die("DB Connection Failed: " . htmlspecialchars($errMsg) . "<br>DSN: " . htmlspecialchars($dsnForDisplay));
}