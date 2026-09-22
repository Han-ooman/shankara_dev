<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

include __DIR__ . "/koneksi.php";

// AUTO-UPDATE status
$now_h = date('H:i:s');
$today = date('Y-m-d');

$koneksi->query("UPDATE data_laporan_harian SET status='Sedang Berlangsung', updated_at=NOW()
    WHERE tanggal='$today' AND jam_mulai IS NOT NULL AND jam_mulai <= '$now_h'
    AND (jam_selesai IS NULL OR jam_selesai > '$now_h')
    AND status='Belum Dikerjakan'");

$koneksi->query("UPDATE data_laporan_harian SET status='Selesai', updated_at=NOW()
    WHERE tanggal='$today' AND jam_selesai IS NOT NULL AND jam_selesai <= '$now_h'
    AND status IN ('Sedang Berlangsung','Belum Dikerjakan')");

// Return updated stats
$q = $koneksi->query("SELECT
    COUNT(*) AS total,
    SUM(status='Selesai') AS selesai,
    SUM(status='Sedang Berlangsung') AS proses,
    SUM(status='Terhambat') AS terhambat,
    SUM(status='Belum Dikerjakan') AS belum
FROM data_laporan_harian");

$row = $q->fetch_assoc();
echo json_encode([
    'ok' => true,
    'stats' => [
        'total'    => (int)$row['total'],
        'selesai'  => (int)$row['selesai'],
        'proses'   => (int)$row['proses'],
        'terhambat'=> (int)$row['terhambat'],
        'belum'    => (int)$row['belum'],
    ]
]);
