<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include "../includes/cek_sesi.php";
include "../includes/koneksi.php";

function rupiah($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}

$filter_keyword = isset($_GET['keyword']) ? mysqli_real_escape_string($koneksi, trim($_GET['keyword'])) : '';
$filter_cluster = isset($_GET['cluster']) ? mysqli_real_escape_string($koneksi, trim($_GET['cluster'])) : '';
$filter_status  = isset($_GET['status']) ? mysqli_real_escape_string($koneksi, trim($_GET['status'])) : '';
$filter_tipe    = isset($_GET['tipe']) ? mysqli_real_escape_string($koneksi, trim($_GET['tipe'])) : '';

$query = "SELECT r.*, c.cluster_name 
          FROM data_rumah r 
          LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster 
          WHERE 1=1";

if ($filter_keyword !== '') {
    $query .= " AND (r.no_blok LIKE '%$filter_keyword%' 
               OR r.tipe_rumah LIKE '%$filter_keyword%' 
               OR r.no_shm LIKE '%$filter_keyword%' 
               OR c.cluster_name LIKE '%$filter_keyword%')";
}
if ($filter_cluster !== '') {
    $query .= " AND r.id_cluster = '$filter_cluster'";
}
if ($filter_status !== '') {
    $query .= " AND UPPER(r.status_unit) = UPPER('$filter_status')";
}
if ($filter_tipe !== '') {
    $query .= " AND r.tipe_rumah = '$filter_tipe'";
}

$query .= " ORDER BY c.cluster_name ASC, r.no_blok ASC";
$result = mysqli_query($koneksi, $query);
$data = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Data Unit Rumah - Shankara Dev</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 8mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            font-size: 9pt;
            line-height: 1.45;
            background: #fff;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }
        .brand {
            font-weight: 800;
            font-size: 12pt;
            color: #1e3a8a;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }
        .title {
            font-weight: 700;
            font-size: 11pt;
            text-align: center;
            flex: 1;
        }
        .meta {
            text-align: right;
            font-size: 8pt;
            color: #4b5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            vertical-align: top;
        }
        thead th {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 8pt;
            border: 1px solid #000;
            background: #fff;
        }
        .filter-info {
            margin-bottom: 8px;
            font-size: 8pt;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-wrap">
            <img src="../assets/images/favicon/favicon.png" alt="Logo" class="logo">
            <div class="brand">Shankara Dev</div>
        </div>
        <div class="title">DATA UNIT RUMAH</div>
        <div class="meta">
            Dicetak: <?= date('d/m/Y H:i:s') ?> WIB
        </div>
    </div>

    <?php if ($filter_keyword !== '' || $filter_cluster !== '' || $filter_status !== '' || $filter_tipe !== ''): ?>
    <div class="filter-info">
        Filter aktif:
        <?php if ($filter_keyword !== ''): ?>Keyword: "<strong><?= htmlspecialchars($filter_keyword) ?></strong>"; <?php endif; ?>
        <?php if ($filter_cluster !== ''): 
            $cnameRow = mysqli_query($koneksi, "SELECT cluster_name FROM data_cluster WHERE id_cluster = '$filter_cluster' LIMIT 1");
            $cname = ($cnameRow && $cr = mysqli_fetch_assoc($cnameRow)) ? $cr['cluster_name'] : $filter_cluster;
            ?>Cluster: <strong><?= htmlspecialchars($cname) ?></strong>; <?php endif; ?>
        <?php if ($filter_status !== ''): ?>Status: <strong><?= htmlspecialchars(strtoupper($filter_status)) ?></strong>; <?php endif; ?>
        <?php if ($filter_tipe !== ''): ?>Tipe: <strong><?= htmlspecialchars($filter_tipe) ?></strong><?php endif; ?>
    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Cluster</th>
                <th class="text-center">No. Blok</th>
                <th class="text-center">Tipe</th>
                <th>No. SHM</th>
                <th class="text-center">Luas Tanah (m²)</th>
                <th class="text-center">Luas Bangunan (m²)</th>
                <th class="text-right">Harga Jual</th>
                <th class="text-center">Status Unit</th>
                <th class="text-center">ID</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($data) === 0): ?>
            <tr>
                <td colspan="9" class="text-center">Tidak ada data unit dengan filter yang dipilih.</td>
            </tr>
            <?php else: foreach ($data as $row): 
                $status = strtoupper(trim($row['status_unit'] ?? 'TERSEDIA'));
            ?>
            <tr>
                <td><?= htmlspecialchars($row['cluster_name'] ?? '-') ?></td>
                <td class="text-center fw-bold"><?= htmlspecialchars($row['no_blok']) ?></td>
                <td class="text-center"><?= htmlspecialchars($row['tipe_rumah']) ?></td>
                <td><?= htmlspecialchars($row['no_shm'] ?? '-') ?></td>
                <td class="text-center"><?= htmlspecialchars($row['luas_tanah'] ?? '-') ?></td>
                <td class="text-center"><?= htmlspecialchars($row['luas_bangunan'] ?? '-') ?></td>
                <td class="text-right fw-bold"><?= rupiah($row['harga_jual'] ?? 0) ?></td>
                <td class="text-center"><span class="badge"><?= htmlspecialchars($status) ?></span></td>
                <td class="text-center"><?= (int)$row['id_rumah'] ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</body>
</html>