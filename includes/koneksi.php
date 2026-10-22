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

// Koneksi via adapter sdb -> mendukung 2 driver (Supabase/pgsql & MySQL lokal).
// Aplikasi ini punya dua koneksi: online (Supabase) dan offline (MySQL lokal).
require_once __DIR__ . '/db_adapter.php';

// ===== DUA KONEKSI DATABASE =====
// 1) ONLINE  : Supabase (PostgreSQL)  -> $koneksi_online
// 2) OFFLINE : MySQL lokal (XAMPP)    -> $koneksi_offline
// Koneksi aktif mengikuti konstanta DB_MODE ('offline' default / 'online').
$koneksi_online  = sdb_connect_pgsql();   // Supabase  (online)
$koneksi_offline = sdb_connect_mysql();   // MySQL lokal (offline)

$koneksi = (DB_MODE === 'online') ? $koneksi_online : $koneksi_offline;

// Alias yang lebih eksplisit (bisa dipakai halaman/future sync).
$koneksi_lokal    = $koneksi_offline;
$koneksi_supabase = $koneksi_online;

if (!($koneksi instanceof sdb) || !$koneksi->ping() || $koneksi->connect_errno) {
    // Jangan bocorkan detail koneksi ke pengguna; catat ke log server.
    error_log("Koneksi DB gagal: " . $koneksi->connect_error);
    http_response_code(500);
    die("Terjadi kesalahan internal. Silakan hubungi administrator.");
}