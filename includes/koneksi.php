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

// ===== KONEKSI SUPABASE =====
// Semua normalisasi DSN/user/password ada di includes/db_adapter.php (satu tempat),
// sehingga tidak ada lagi konstanta yang bentrok / DSN format URI yang lolos ke PDO.
require_once __DIR__ . '/db_adapter.php';

$koneksi = sdb_connect();

if (!($koneksi instanceof sdb) || $koneksi->connect_errno) {
    $dsnForDisplay = preg_replace('/password=[^;]+/', 'password=***', SUPABASE_DSN ?? '');
    $errMsg = $koneksi->connect_error ?? 'Unknown connection error';
    error_log("Koneksi DB gagal: DSN={$dsnForDisplay} User=" . (SUPABASE_USER ?? '-') . " Error={$errMsg}");

    http_response_code(500);
    if (getenv('APP_DEBUG') === 'true') {
        die("DB Connection Failed: " . htmlspecialchars($errMsg)
            . "<br>DSN: " . htmlspecialchars($dsnForDisplay)
            . "<br>User: " . htmlspecialchars(SUPABASE_USER ?? '-')
            . "<br>Password set: " . (defined('SUPABASE_PASSWORD') && SUPABASE_PASSWORD !== '' ? 'yes' : 'NO'));
    }
    die("Terjadi kesalahan internal. Silakan hubungi administrator.");
}

// Alias koneksi untuk modul sinkronisasi (mode online-only di server).
$koneksi_online  = $koneksi;
$koneksi_offline = null; // MySQL lokal hanya tersedia saat dijalankan di XAMPP