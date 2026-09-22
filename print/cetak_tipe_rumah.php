<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include "../includes/cek_sesi.php";
include "../includes/koneksi.php";

$q_tipe = mysqli_query($koneksi, "SELECT * FROM data_tipe_rumah ORDER BY CAST(tipe_rumah AS UNSIGNED) ASC, id_tipe ASC");
$data_tipe = [];
if ($q_tipe) { while ($r = mysqli_fetch_assoc($q_tipe)) { $data_tipe[] = $r; } }

$q_ang = mysqli_query($koneksi, "SELECT * FROM data_angsuran ORDER BY tenor_bulan ASC, id_angsuran ASC");
$data_ang = [];
if ($q_ang) { while ($r = mysqli_fetch_assoc($q_ang)) { $data_ang[$r['id_tipe']][] = $r; } }

function rupiah($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}

function ambil_plafon($keterangan) {
    if (preg_match('/Plafon kredit\s*Rp\s*([\d.]+)/i', $keterangan ?? '', $m)) {
        return (int)str_replace('.', '', $m[1]);
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Tipe Rumah & Paket Pembayaran - Shankara Dev</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
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
            max-width: 210mm;
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

        .tenor-block { margin-bottom: 12px; }
        .tenor-head {
            font-size: 13px;
            font-weight: 800;
            color: #fff;
            background: #1e3a8a;
            padding: 6px 12px;
            border-radius: 6px 6px 0 0;
            text-align: center;
        }
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

        .syarat-title {
            font-size: 13px;
            font-weight: 800;
            color: #1e3a8a;
            margin: 14px 0 6px;
        }
        .syarat-list {
            margin: 0;
            padding-left: 20px;
            font-size: 12px;
            line-height: 1.7;
        }
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
    <button onclick="window.print()" class="btn-print">ðŸ–¨ï¸ Cetak / Simpan PDF</button>
    <a href="tipe_rumah.php" class="btn-back">â† Kembali</a>
</div>

<?php if (empty($data_tipe)): ?>
    <div class="sheet" style="text-align:center; font-size:14px; color:#64748b;">Belum ada data tipe rumah.</div>
<?php endif; ?>

<?php foreach ($data_tipe as $t): ?>
    <?php
        $persyaratan = array_values(array_filter(array_map('trim', explode("\n", $t['persyaratan'] ?? ''))));
        $skema = $data_ang[$t['id_tipe']] ?? [];
        $tenor_group = [];
        foreach ($skema as $row) { $tenor_group[$row['tenor_bulan']][] = $row; }
        ksort($tenor_group);
        $plafon = ambil_plafon($t['keterangan']);
    ?>
    <div class="sheet">
        <div class="sheet-title">Tipe <?= htmlspecialchars($t['tipe_rumah']) ?></div>

        <table class="info-table">
            <tr>
                <th>Harga Rumah</th>
                <td><?= rupiah($t['harga']) ?></td>
            </tr>
            <tr>
                <th>Uang Muka (DP)</th>
                <td><?= rtrim(rtrim(number_format($t['dp_persen'], 2, ',', '.'), '0'), ',') ?>% / <?= rupiah($t['dp_nominal']) ?></td>
            </tr>
            <?php if ($plafon !== null): ?>
            <tr>
                <th>Plafon Kredit</th>
                <td><?= rupiah($plafon) ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if (!empty($tenor_group)): ?>
            <?php foreach ($tenor_group as $tb => $rows): ?>
            <div class="tenor-block">
                <div class="tenor-head">TENOR <?= intval($tb) ?> BULAN (<?= intval($tb / 12) ?> TAHUN)</div>
                <table class="ang-table">
                    <tr>
                        <?php if (count($rows) == 1 && empty($rows[0]['periode'])): ?>
                            <th style="width:40%;">Angsuran / Bulan</th>
                            <td colspan="2"><?= rupiah($rows[0]['angsuran']) ?></td>
                        <?php else: ?>
                            <th>Periode</th>
                            <?php foreach ($rows as $row): ?>
                                <th><?= htmlspecialchars($row['periode']) ?></th>
                            <?php endforeach; ?>
                            </tr>
                            <tr>
                            <td style="font-weight:700;">Angsuran</td>
                            <?php foreach ($rows as $row): ?>
                                <td><?= rupiah($row['angsuran']) ?></td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($persyaratan)): ?>
        <div class="syarat-title">Syarat Pengajuan Kredit :</div>
        <ul class="syarat-list">
            <?php foreach ($persyaratan as $s): ?>
                <li><?= htmlspecialchars($s) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!empty($t['keterangan'])): ?>
        <div class="catatan"><strong>Catatan :</strong> <?= htmlspecialchars($t['keterangan']) ?></div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<script>
    window.onload = function() {
        window.print();
    };
</script>
</body>
</html>
