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

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_perumahan";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    // Jangan bocorkan detail koneksi ke pengguna; catat ke log server.
    error_log("Koneksi DB gagal: " . mysqli_connect_error());
    http_response_code(500);
    die("Terjadi kesalahan internal. Silakan hubungi administrator.");
}