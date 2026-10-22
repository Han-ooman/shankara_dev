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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Data Konsumen - Shankara Dev</title>
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
            font-size: 11.5px;
        }
        .ang-table th, .ang-table td {
            border: 1px solid #1e3a8a;
            padding: 6px 8px;
            text-align: center;
            vertical-align: top;
        }
        .ang-table th {
            background: #eff6ff;
            color: #1e3a8a;
            font-weight: 700;
        }
        .ang-table td { font-weight: 600; }
        .ang-table .text-left { text-align: left; }

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
    <a href="../konsumen.php" class="btn-back">← Kembali</a>
</div>

<div class="sheet">
    <div class="sheet-title">Data Konsumen</div>

    <table class="info-table">
        <tr>
            <th>Filter Aktif</th>
            <td>
                <?php if ($ada_filter && count($labels) > 0): ?>
                    <?= implode(' &nbsp;|&nbsp; ', array_map('htmlspecialchars', $labels)) ?>
                <?php else: ?>
                    Tidak ada filter (semua data)
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Total Data</th>
            <td><?= count($data) ?> konsumen</td>
        </tr>
    </table>

    <table class="ang-table">
        <thead>
            <tr>
                <th style="width:32px;">No</th>
                <th>ID</th>
                <th>Nama Lengkap</th>
                <th>Pekerjaan</th>
                <th>Alamat KTP</th>
                <th>No. KTP</th>
                <th>No. WhatsApp</th>
                <th>BI Checking</th>
                <th>Status Transaksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($data) === 0): ?>
            <tr>
                <td colspan="9">Tidak ada data konsumen dengan filter yang dipilih.</td>
            </tr>
            <?php else: $no = 1; foreach ($data as $row):
                $tx = konsumen_status_badge_data($row['status_transaksi'] ?? null);
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td class="text-left">#KNS-<?= htmlspecialchars($row['id_konsumen']) ?></td>
                <td class="text-left"><?= htmlspecialchars($row['name_lengkap']) ?></td>
                <td><?= htmlspecialchars($row['pekerjaan'] ?: '-') ?></td>
                <td class="text-left"><?= htmlspecialchars($row['alamat_ktp'] ?: '-') ?></td>
                <td><?= htmlspecialchars(!empty($row['no_ktp']) && $row['no_ktp'] != '-' ? $row['no_ktp'] : '-') ?></td>
                <td><?= htmlspecialchars(!empty($row['no_whatsapp']) && $row['no_whatsapp'] != '-' ? $row['no_whatsapp'] : '-') ?></td>
                <td><?= htmlspecialchars(!empty($row['bi_status']) ? $row['bi_status'] : 'Belum Dicek') ?></td>
                <td><?= htmlspecialchars($tx['label']) ?></td>
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
