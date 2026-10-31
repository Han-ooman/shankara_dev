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

// ===== SUPABASE CONNECTION (Force Pooler Port 6543 for Vercel Serverless) =====
// Project ref: iwxbdnzglypdjimeuzht (ap-southeast-1)
$PROJECT_REF = 'iwxbdnzglypdjimeuzht';

$env_user = getenv('SUPABASE_USER') ?: '';
$env_pass = getenv('SUPABASE_PASSWORD') ?: '';

$host = 'aws-0-ap-southeast-1.pooler.supabase.com';
$port = '6543';
$db   = 'postgres';

// Pooler WAJIB memakai username berformat "postgres.<project-ref>".
// Jika env var berisi "postgres" polos (atau kosong), paksa format pooler yang benar.
if ($env_user === '' || strpos($env_user, '.') === false) {
    $user = 'postgres.' . $PROJECT_REF;
} else {
    $user = $env_user;
}
$pass = $env_pass;

define('SUPABASE_DSN', "pgsql:host={$host};port={$port};dbname={$db};sslmode=require");
define('SUPABASE_USER', $user);
define('SUPABASE_PASSWORD', $pass);

// ===== NOW LOAD ADAPTER =====
require_once __DIR__ . '/db_adapter.php';

$koneksi = sdb_connect(SUPABASE_DSN, SUPABASE_USER, SUPABASE_PASSWORD, 'postgres');

if (!($koneksi instanceof sdb) || $koneksi->connect_errno) {
    $dsnForDisplay = preg_replace('/password=[^;]+/', 'password=***', SUPABASE_DSN ?? '');
    $errMsg = $koneksi->connect_error ?? 'Unknown connection error';
    error_log("Koneksi DB gagal: DSN={$dsnForDisplay} User={$user} PassLen=" . strlen($pass) . " Error={$errMsg}");
    
    http_response_code(500);
    die("DB Connection Failed: " . htmlspecialchars($errMsg)
        . "<br>DSN: " . htmlspecialchars($dsnForDisplay)
        . "<br>User: " . htmlspecialchars($user)
        . "<br>Password length: " . strlen($pass)
        . "<br>Password set: " . ($pass !== '' ? 'yes' : 'NO (env SUPABASE_PASSWORD kosong)'));
}