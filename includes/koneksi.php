<?php
// includes/koneksi.php — Koneksi Utama Aplikasi (Migrasi ke Turso / libSQL)
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/security.php';

// Cek mode maintenance
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

require_once __DIR__ . '/db_turso.php';

// Konfigurasi Turso yang diberikan user
$turso_url   = 'libsql://shankara-dev-han-ooman.aws-ap-southeast-1.turso.io';
$turso_token = 'eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.eyJhIjoicnciLCJleHAiOjE4MjQ3Nzg1ODIsImlhdCI6MTc5MzI0MjU4MiwiaWQiOiIwMWExODU5MS1iZDAxLTc5ZDUtYTBjMi1jZTA1OWQxYzcxMmMiLCJraWQiOiJHNnh6ZWtvaE53TFZscnAyaHV1N2tRMzI2aTFWQzk0R0o5VzFBRG1OTUljIiwicmlkIjoiOWE0YzkzYmMtNWY2Ni00NDc2LWExM2UtNTg0MGZlOWY5MWUxIn0.-e1UP-h8AKtFZ_oqs4STped3R7sD21_-XHizagugb1IwXBaJyliISr38-G0X6MAKsP0rzJ8bRKLMT8Z0Ks3XCw';

// Jika ada local.php, izinkan override
$__local_config = dirname(__DIR__) . '/config/local.php';
if (is_file($__local_config)) {
    require_once $__local_config;
    if (defined('TURSO_URL')) $turso_url = TURSO_URL;
    if (defined('TURSO_TOKEN')) $turso_token = TURSO_TOKEN;
}

$koneksi = new TursoDB($turso_url, $turso_token);
$koneksi_online = $koneksi;
$koneksi_offline = $koneksi;
$koneksi_lokal = $koneksi;
$koneksi_supabase = $koneksi;

if (!($koneksi instanceof TursoDB)) {
    error_log("Koneksi Turso gagal.");
    http_response_code(500);
    die("Terjadi kesalahan internal koneksi database.");
}
