<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/security.php';

// Cek mode maintenance — skip jika halaman maintenance atau toggle
$_self = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($_self !== 'maintenance.php' && $_self !== 'toggle_maintenance.php') {
    $mfile = __DIR__ . '/.maintenance';
    if (file_exists($mfile)) {
        // Request API (mobile): jawab JSON 503, jangan halaman HTML maintenance
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

// Auto-construct Supabase DSN if not provided as full URI
// Supabase Project: iwxbdnzglypdjimeuzht
if (!defined('SUPABASE_DSN') || str_starts_with(SUPABASE_DSN, 'pgsql:') === false) {
    // Get credentials from env or constants
    $host = getenv('SUPABASE_HOST') ?: (defined('SUPABASE_HOST') ? SUPABASE_HOST : 'db.iwxbdnzglypdjimeuzht.supabase.co');
    $port = getenv('SUPABASE_PORT') ?: (defined('SUPABASE_PORT') ? SUPABASE_PORT : '5432');
    $db   = getenv('SUPABASE_DB') ?: (defined('SUPABASE_DB') ? SUPABASE_DB : 'postgres');
    $user = getenv('SUPABASE_USER') ?: (defined('SUPABASE_USER') ? SUPABASE_USER : 'postgres');
    $pass = getenv('SUPABASE_PASSWORD') ?: (defined('SUPABASE_PASSWORD') ? SUPABASE_PASSWORD : '');
    
    // Use pooler for serverless (Vercel) - port 6543
    // Use direct for local - port 5432
    $isVercel = getenv('VERCEL') !== false || getenv('VERCEL_ENV') !== false;
    if ($isVercel) {
        // Use pooler
        $host = 'aws-0-ap-southeast-1.pooler.supabase.com';
        $port = '6543';
    }
    
    define('SUPABASE_DSN', "pgsql:host={$host};port={$port};dbname={$db};sslmode=require");
    define('SUPABASE_USER', $user);
    define('SUPABASE_PASSWORD', $pass);
}

$koneksi = sdb_connect(SUPABASE_DSN, SUPABASE_USER, SUPABASE_PASSWORD, 'postgres');

if (!($koneksi instanceof sdb) || $koneksi->connect_errno) {
    // Jangan bocorkan detail koneksi ke pengguna; catat ke log server.
    $errMsg = $koneksi->connect_error ?? 'Unknown connection error';
    error_log("Koneksi DB gagal: " . $errMsg);
    // Debug: show error in development
    if (getenv('APP_DEBUG') === 'true') {
        http_response_code(500);
        die("DB Connection Failed: " . htmlspecialchars($errMsg));
    }
    http_response_code(500);
    die("Terjadi kesalahan internal. Silakan hubungi administrator.");
}