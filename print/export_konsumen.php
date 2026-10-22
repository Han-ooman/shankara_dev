<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include __DIR__ . "/../includes/cek_sesi.php";
include __DIR__ . "/../includes/koneksi.php";
require_once __DIR__ . '/../includes/konsumen_filter.php';

$filters = konsumen_get_filters();
$data    = konsumen_list($koneksi, $filters);
$labels  = konsumen_filter_labels($filters);
$ada_filter = konsumen_filter_active($filters);

$nama_file = 'Data_Konsumen_' . date('Y-m-d_His') . '.xls';

// Header agar browser mengunduh sebagai berkas Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Cache-Control: max-age=0');

// Helper aman untuk sel Excel
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
        .tengah { text-align: center; }
        /* Paksa sel jadi format teks agar angka panjang (No. KTP/WA) tidak jadi notasi ilmiah */
        .teks { mso-number-format: '\@'; }
    </style>
</head>
<body>
    <table border="1">
        <tr><td colspan="10" class="judul">DATA KONSUMEN - SHANKARA DEV</td></tr>
        <tr>
            <td colspan="10" class="subjudul">
                Filter Aktif: <?= $ada_filter && count($labels) > 0 ? xls_cell(implode(' | ', $labels)) : 'Tidak ada filter (semua data)' ?>
            </td>
        </tr>
        <tr>
            <td colspan="10" class="subjudul">Total: <?= count($data) ?> konsumen &nbsp;&nbsp;|&nbsp;&nbsp; Dicetak: <?= date('d/m/Y H:i:s') ?> WIB &nbsp;&nbsp;|&nbsp;&nbsp; Oleh: <?= xls_cell($_SESSION['username'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>No</th>
            <th>ID Konsumen</th>
            <th>Nama Lengkap</th>
            <th>Pekerjaan</th>
            <th>Alamat KTP</th>
            <th>No. KTP</th>
            <th>No. WhatsApp</th>
            <th>BI Checking</th>
            <th>Tgl BI Terakhir</th>
            <th>Status Transaksi</th>
        </tr>
        <?php if (count($data) === 0): ?>
        <tr><td colspan="10" class="tengah">Tidak ada data konsumen dengan filter yang dipilih.</td></tr>
        <?php else: $no = 1; foreach ($data as $row):
            $tx = konsumen_status_badge_data($row['status_transaksi'] ?? null);
            $tgl_bi = (!empty($row['bi_tgl']) && $row['bi_tgl'] != '0000-00-00') ? date('d/m/Y', strtotime($row['bi_tgl'])) : '-';
        ?>
        <tr>
            <td class="tengah"><?= $no++ ?></td>
            <td>#KNS-<?= xls_cell($row['id_konsumen']) ?></td>
            <td><?= xls_cell($row['name_lengkap']) ?></td>
            <td><?= xls_cell($row['pekerjaan'] ?: '-') ?></td>
            <td><?= xls_cell($row['alamat_ktp'] ?: '-') ?></td>
            <td class="teks"><?= xls_cell(!empty($row['no_ktp']) && $row['no_ktp'] != '-' ? $row['no_ktp'] : '-') ?></td>
            <td class="teks"><?= xls_cell(!empty($row['no_whatsapp']) && $row['no_whatsapp'] != '-' ? $row['no_whatsapp'] : '-') ?></td>
            <td><?= xls_cell(!empty($row['bi_status']) ? $row['bi_status'] : 'Belum Dicek') ?></td>
            <td class="tengah teks"><?= xls_cell($tgl_bi) ?></td>
            <td><?= xls_cell($tx['label']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
    </table>
</body>
</html>
