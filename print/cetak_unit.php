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

$cluster_label = '';
if ($filter_cluster !== '') {
    $cnameRow = mysqli_query($koneksi, "SELECT cluster_name FROM data_cluster WHERE id_cluster = '$filter_cluster' LIMIT 1");
    $cluster_label = ($cnameRow && $cr = mysqli_fetch_assoc($cnameRow)) ? $cr['cluster_name'] : $filter_cluster;
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
            margin: 10mm 10mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            background-color: #f4f7f5;
            padding: 10px 0;
            margin: 0;
            color: #111;
        }

        .no-print-btn {
            text-align: center;
            margin-bottom: 15px;
        }

        .btn-print {
            background-color: #1e3a8a;
            color: #fff;
            border: none;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-back {
            background-color: #64748b;
            color: #fff;
            border: none;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 8px;
            text-decoration: none;
            display: inline-block;
        }

        .sheet {
            background: #fff;
            border: 2px solid #1e3a8a;
            border-radius: 12px;
            padding: 22px 26px;
            margin: 0 auto 18px;
            max-width: 277mm;
            page-break-after: always;
        }
        .sheet:last-child {
            page-break-after: auto;
        }

        .sheet-title {
            text-align: center;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 14px;
            border-bottom: 3px double #1e3a8a;
            padding-bottom: 8px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 12.5px;
        }
        .info-table th, .info-table td {
            border: 1.5px solid #1e3a8a;
            padding: 7px 10px;
            text-align: left;
        }
        .info-table th {
            background: #eff6ff;
            color: #1e3a8a;
            font-weight: 700;
            width: 30%;
        }
        .info-table td { font-weight: 600; }

        .ang-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .ang-table th, .ang-table td {
            border: 1px solid #1e3a8a;
            padding: 6px 8px;
            text-align: center;
        }
        .ang-table th {
            background: #eff6ff;
            color: #1e3a8a;
            font-weight: 700;
        }
        .ang-table td { font-weight: 600; }
        .ang-table .text-left { text-align: left; }
        .ang-table .text-right { text-align: right; }

        .catatan {
            margin-top: 10px;
            font-size: 11.5px;
            color: #333;
            background: #f6f8f7;
            border-left: 4px solid #b45309;
            padding: 8px 12px;
            border-radius: 0 6px 6px 0;
        }

        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            html, body {
                background-color: #fff;
                padding: 0 !important;
                margin: 0 !important;
            }
            .sheet {
                box-shadow: none;
                border: 2px solid #1e3a8a;
                margin: 0 !important;
            }
            .no-print-btn { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print-btn">
    <button onclick="window.print()" class="btn-print">🖨 Cetak / Simpan PDF</button>
    <a href="unit.php" class="btn-back">← Kembali</a>
</div>

<div class="sheet">
    <div class="sheet-title">Data Unit Rumah</div>

    <?php if ($filter_keyword !== '' || $cluster_label !== '' || $filter_status !== '' || $filter_tipe !== ''): ?>
    <table class="info-table">
        <tr>
            <th>Filter Aktif</th>
            <td>
                <?php
                $parts = [];
                if ($filter_keyword !== '') $parts[] = 'Keyword: "' . htmlspecialchars($filter_keyword) . '"';
                if ($cluster_label !== '') $parts[] = 'Cluster: ' . htmlspecialchars($cluster_label);
                if ($filter_status !== '') $parts[] = 'Status: ' . htmlspecialchars(strtoupper($filter_status));
                if ($filter_tipe !== '') $parts[] = 'Tipe: ' . htmlspecialchars($filter_tipe);
                echo implode(' &nbsp;|&nbsp; ', $parts);
                ?>
            </td>
        </tr>
        <tr>
            <th>Total Data</th>
            <td><?= count($data) ?> unit</td>
        </tr>
    </table>
    <?php else: ?>
    <table class="info-table">
        <tr>
            <th>Total Data</th>
            <td><?= count($data) ?> unit (semua data)</td>
        </tr>
    </table>
    <?php endif; ?>

    <table class="ang-table">
        <thead>
            <tr>
                <th>Cluster</th>
                <th>No. Blok</th>
                <th>Tipe</th>
                <th>No. SHM</th>
                <th>L. Tanah (m²)</th>
                <th>L. Bangunan (m²)</th>
                <th>Harga Jual</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($data) === 0): ?>
            <tr>
                <td colspan="8">Tidak ada data unit dengan filter yang dipilih.</td>
            </tr>
            <?php else: foreach ($data as $row): 
                $status = strtoupper(trim($row['status_unit'] ?? 'TERSEDIA'));
            ?>
            <tr>
                <td class="text-left"><?= htmlspecialchars($row['cluster_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['no_blok']) ?></td>
                <td><?= htmlspecialchars($row['tipe_rumah']) ?></td>
                <td class="text-left"><?= htmlspecialchars($row['no_shm'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['luas_tanah'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['luas_bangunan'] ?? '-') ?></td>
                <td class="text-right"><?= rupiah($row['harga_jual'] ?? 0) ?></td>
                <td><?= htmlspecialchars($status) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="catatan"><strong>Dicetak :</strong> <?= date('d/m/Y H:i:s') ?> WIB &nbsp;&nbsp;|&nbsp;&nbsp; Dicetak oleh: <?= htmlspecialchars($_SESSION['username'] ?? '-') ?></div>
</div>

<script>
    window.onload = function() {
        window.print();
    };
</script>
</body>
</html>