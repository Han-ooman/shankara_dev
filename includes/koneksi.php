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

// ===== PARSE DSN & DEFINE CONSTANTS BEFORE loading db_adapter.php =====
// Supabase Project: iwxbdnzglypdjimeuzht (ap-southeast-1)
$raw_dsn  = getenv('SUPABASE_DSN') ?: '';
$env_user = getenv('SUPABASE_USER') ?: '';
$env_pass = getenv('SUPABASE_PASSWORD') ?: '';

if (!empty($raw_dsn) && (str_starts_with($raw_dsn, 'postgresql://') || str_starts_with($raw_dsn, 'postgres://'))) {
    // Parse postgresql:// URI from Supabase pooler
    $parsed = parse_url($raw_dsn);
    $host = $parsed['host'] ?? 'aws-0-ap-southeast-1.pooler.supabase.com';
    $port = $parsed['port'] ?? '6543';
    $db   = ltrim($parsed['path'] ?? '/postgres', '/');
    $user = $env_user !== '' ? $env_user : ($parsed['user'] ?? 'postgres');
    $pass = $env_pass !== '' ? $env_pass : ($parsed['pass'] ?? '');
    
    define('SUPABASE_DSN', "pgsql:host={$host};port={$port};dbname={$db};sslmode=require");
    define('SUPABASE_USER', $user);
    define('SUPABASE_PASSWORD', $pass);
} elseif (!empty($raw_dsn) && str_starts_with($raw_dsn, 'pgsql:')) {
    // Already in PDO pgsql format
    define('SUPABASE_DSN', $raw_dsn);
    define('SUPABASE_USER', $env_user !== '' ? $env_user : 'postgres');
    define('SUPABASE_PASSWORD', $env_pass !== '' ? $env_pass : '');
} else {
    // Default fallback: Supabase Pooler (port 6543) for serverless reliability
    $host = 'aws-0-ap-southeast-1.pooler.supabase.com';
    $port = '6543';
    $db   = 'postgres';
    $user = $env_user !== '' ? $env_user : 'postgres';
    $pass = $env_pass;
    
    define('SUPABASE_DSN', "pgsql:host={$host};port={$port};dbname={$db};sslmode=require");
    define('SUPABASE_USER', $user);
    define('SUPABASE_PASSWORD', $pass);
}

// ===== NOW LOAD ADAPTER (constants already defined) =====
require_once __DIR__ . '/db_adapter.php';

$koneksi = sdb_connect(SUPABASE_DSN, SUPABASE_USER, SUPABASE_PASSWORD, 'postgres');

if (!($koneksi instanceof sdb) || $koneksi->connect_errno) {
    $dsnForDisplay = preg_replace('/password=[^;]+/', 'password=***', SUPABASE_DSN ?? '');
    $errMsg = $koneksi->connect_error ?? 'Unknown connection error';
    error_log("Koneksi DB gagal: DSN={$dsnForDisplay} Error={$errMsg}");
    
    http_response_code(500);
    die("DB Connection Failed: " . htmlspecialchars($errMsg) . "<br>DSN: " . htmlspecialchars($dsnForDisplay));
}