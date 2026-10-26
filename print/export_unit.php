<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include __DIR__ . "/../includes/cek_sesi.php";
include __DIR__ . "/../includes/koneksi.php";

// ===== Filter identik dgn unit.php =====
$filter_keyword = isset($_GET['keyword']) ? sdb_real_escape_string($koneksi, $_GET['keyword']) : '';
$filter_cluster = isset($_GET['cluster']) ? sdb_real_escape_string($koneksi, $_GET['cluster']) : '';
$filter_status  = isset($_GET['status']) ? sdb_real_escape_string($koneksi, $_GET['status']) : '';
$filter_tipe    = isset($_GET['tipe']) ? sdb_real_escape_string($koneksi, $_GET['tipe']) : '';

$query = "SELECT r.*, c.cluster_name
          FROM data_rumah r
          LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
          WHERE 1=1";

if (!empty($filter_keyword)) {
    $likekw = str_replace(['%', '_'], ['\%', '\_'], $filter_keyword);
    $query .= " AND (r.no_blok LIKE '%$likekw%'
                   OR r.tipe_rumah LIKE '%$likekw%'
                   OR r.no_shm LIKE '%$likekw%'
                   OR c.cluster_name LIKE '%$likekw%')";
}
if (!empty($filter_cluster)) $query .= " AND r.id_cluster = '$filter_cluster'";
if (!empty($filter_status))  $query .= " AND UPPER(r.status_unit) = '$filter_status'";
if (!empty($filter_tipe))    $query .= " AND r.tipe_rumah = '$filter_tipe'";
$query .= " ORDER BY c.cluster_name ASC, r.no_blok ASC";

$result = sdb_query($koneksi, $query);
$data   = $result ? sdb_fetch_all($result) : [];

// Label filter aktif
$labels = [];
if (!empty($filter_keyword)) $labels[] = 'Kata kunci: ' . $filter_keyword;
if (!empty($filter_cluster)) {
    $qc = sdb_query($koneksi, "SELECT cluster_name FROM data_cluster WHERE id_cluster = '" . (int)$filter_cluster . "'");
    $rc = $qc ? sdb_fetch_assoc($qc) : null;
    $labels[] = 'Cluster: ' . ($rc['cluster_name'] ?? $filter_cluster);
}
if (!empty($filter_status))  $labels[] = 'Status: ' . $filter_status;
if (!empty($filter_tipe))    $labels[] = 'Tipe: ' . $filter_tipe;
$ada_filter = count($labels) > 0;

$nama_file = 'Data_Unit_Rumah_' . date('Y-m-d_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Cache-Control: max-age=0');

function xls_cell($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="UTF-8">
    <style>
        td, th { font-family: Arial, sans-serif; font-size: 11px; vertical-align: top; }
        th { background: #1e3a8a; color: #ffffff; font-weight: bold; }
        .judul { font-size: 15px; font-weight: bold; color: #1e3a8a; }
        .subjudul { font-size: 11px; color: #444444; }
        .angka { mso-number-format: '#,##0'; }
        .tengah { text-align: center; }
        .teks { mso-number-format: '\@'; }
    </style>
</head>
<body>
    <table border="1">
        <tr><td colspan="10" class="judul">DATA UNIT RUMAH - SHANKARA DEV</td></tr>
        <tr>
            <td colspan="10" class="subjudul">
                Filter Aktif: <?= $ada_filter ? xls_cell(implode(' | ', $labels)) : 'Tidak ada filter (semua data)' ?>
            </td>
        </tr>
        <tr>
            <td colspan="10" class="subjudul">Total: <?= count($data) ?> unit &nbsp;&nbsp;|&nbsp;&nbsp; Dicetak: <?= date('d/m/Y H:i:s') ?> &nbsp;&nbsp;|&nbsp;&nbsp; Oleh: <?= xls_cell($_SESSION['username'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>No</th>
            <th>ID Rumah</th>
            <th>Cluster</th>
            <th>No. Blok</th>
            <th>Tipe</th>
            <th>No. SHM</th>
            <th>Luas Tanah (m2)</th>
            <th>Luas Bangunan (m2)</th>
            <th>Harga Jual (Rp)</th>
            <th>Status Unit</th>
        </tr>
        <?php $no = 0; foreach ($data as $row): $no++; $status = strtoupper(trim($row['status_unit'] ?? '')); ?>
        <tr>
            <td class="tengah"><?= $no ?></td>
            <td class="teks"><?= xls_cell($row['id_rumah']) ?></td>
            <td class="teks"><?= xls_cell($row['cluster_name'] ?? 'SHANKARA RESIDENCE') ?></td>
            <td class="teks"><?= xls_cell($row['no_blok']) ?></td>
            <td class="teks"><?= xls_cell($row['tipe_rumah']) ?></td>
            <td class="teks"><?= xls_cell($row['no_shm'] ?: '-') ?></td>
            <td class="angka"><?= xls_cell($row['luas_tanah'] !== null && $row['luas_tanah'] !== '' ? $row['luas_tanah'] : '-') ?></td>
            <td class="angka"><?= xls_cell($row['luas_bangunan'] !== null && $row['luas_bangunan'] !== '' ? $row['luas_bangunan'] : '-') ?></td>
            <td class="angka"><?= xls_cell($row['harga_jual'] !== null && $row['harga_jual'] !== '' ? $row['harga_jual'] : '-') ?></td>
            <td class="tengah"><?= xls_cell($status ?: '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($data) === 0): ?>
        <tr><td colspan="10" class="tengah">Tidak ada unit yang cocok dengan filter.</td></tr>
        <?php endif; ?>
    </table>
</body>
</html>
