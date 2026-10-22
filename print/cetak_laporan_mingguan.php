<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include __DIR__ . "/../includes/cek_sesi.php";
include __DIR__ . "/../includes/koneksi.php";
require_once __DIR__ . '/../includes/laporan_mingguan_data.php';

list($week, $start, $end) = lm_parse_week($_GET['week'] ?? '');
$L = lm_hitung($koneksi, $start, $end);

$labelPeriode = date('d M Y', strtotime($start)) . ' — ' . date('d M Y', strtotime($end));
$labelMinggu  = 'Minggu ke-' . (int)date('W', strtotime($start)) . ' (' . $week . ')';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Mingguan - Shankara Dev</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            background-color: #f4f7f5;
            padding: 10px 0;
            margin: 0;
            color: #111;
        }

        .no-print-btn { text-align: center; margin-bottom: 15px; }
        .btn-print {
            background-color: #1e3a8a; color: #fff; border: none;
            padding: 10px 24px; font-size: 14px; font-weight: 600;
            border-radius: 6px; cursor: pointer;
        }
        .btn-back {
            background-color: #64748b; color: #fff; border: none;
            padding: 10px 24px; font-size: 14px; font-weight: 600;
            border-radius: 6px; cursor: pointer; margin-left: 8px;
            text-decoration: none; display: inline-block;
        }

        .sheet {
            background: #fff; border: 2px solid #1e3a8a; border-radius: 12px;
            padding: 22px 26px; margin: 0 auto 18px; max-width: 210mm;
            page-break-after: always;
        }
        .sheet:last-child { page-break-after: auto; }

        .sheet-title {
            text-align: center; font-size: 17px; font-weight: 800;
            letter-spacing: 0.5px; color: #1e3a8a; text-transform: uppercase;
            margin-bottom: 4px;
        }
        .sheet-sub {
            text-align: center; font-size: 12px; color: #475569;
            margin-bottom: 12px; border-bottom: 3px double #1e3a8a; padding-bottom: 8px;
        }

        .kpi-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 8px; margin-bottom: 14px;
        }
        .kpi {
            border: 1.5px solid #1e3a8a; border-radius: 8px;
            padding: 8px 10px; text-align: center;
        }
        .kpi .lbl {
            font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.3px;
            color: #1e3a8a; font-weight: 700; margin-bottom: 3px;
        }
        .kpi .val { font-size: 14px; font-weight: 800; color: #111; }

        .section-title {
            font-size: 12.5px; font-weight: 800; color: #1e3a8a;
            margin: 16px 0 6px; text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        th, td { border: 1px solid #1e3a8a; padding: 5px 8px; }
        th { background: #eff6ff; color: #1e3a8a; font-weight: 700; }
        td { font-weight: 600; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .ringkasan {
            font-size: 11.5px; line-height: 1.6; background: #f6f8f7;
            border-left: 4px solid #1e3a8a; padding: 10px 14px; border-radius: 0 6px 6px 0;
        }
        .hl-list { margin: 8px 0 0; padding-left: 18px; font-size: 11px; line-height: 1.7; }

        .catatan {
            margin-top: 14px; font-size: 10.5px; color: #333;
            background: #f6f8f7; border-left: 4px solid #b45309;
            padding: 8px 12px; border-radius: 0 6px 6px 0;
        }

        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            html, body { background-color: #fff; padding: 0 !important; margin: 0 !important; }
            .sheet { box-shadow: none; border: 2px solid #1e3a8a; margin: 0 !important; }
            .no-print-btn { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print-btn">
    <button onclick="window.print()" class="btn-print">🖨 Cetak / Simpan PDF</button>
    <a href="../laporan_mingguan.php?week=<?= htmlspecialchars($week) ?>" class="btn-back">← Kembali</a>
</div>

<div class="sheet">
    <div class="sheet-title">Laporan Mingguan</div>
    <div class="sheet-sub"><?= htmlspecialchars($labelMinggu) ?> &nbsp;•&nbsp; Periode <?= htmlspecialchars($labelPeriode) ?></div>

    <div class="kpi-grid">
        <div class="kpi"><div class="lbl">Unit Booking</div><div class="val"><?= $L['kpi']['booking'] ?></div></div>
        <div class="kpi"><div class="lbl">Akad</div><div class="val"><?= $L['kpi']['akad'] ?></div></div>
        <div class="kpi"><div class="lbl">Kas Masuk</div><div class="val"><?= lm_rupiah($L['kpi']['kas_masuk']) ?></div></div>
        <div class="kpi"><div class="lbl">Pengeluaran</div><div class="val"><?= lm_rupiah($L['kpi']['pengeluaran']) ?></div></div>
        <div class="kpi"><div class="lbl">Prospek Baru</div><div class="val"><?= $L['kpi']['prospek_baru'] ?></div></div>
        <div class="kpi"><div class="lbl">Follow-up</div><div class="val"><?= $L['kpi']['followup'] ?></div></div>
        <div class="kpi"><div class="lbl">Berkas / BI</div><div class="val"><?= $L['kpi']['berkas'] ?></div></div>
        <div class="kpi"><div class="lbl">Saldo Kas</div><div class="val"><?= lm_rupiah($L['kpi']['saldo']) ?></div></div>
    </div>

    <div class="ringkasan">
        <strong>Ringkasan :</strong>
        Pada periode ini tercatat <strong><?= $L['kpi']['booking'] ?></strong> unit booking dan <strong><?= $L['kpi']['akad'] ?></strong> unit akad.
        Kas masuk <strong><?= lm_rupiah($L['kpi']['kas_masuk']) ?></strong>, pengeluaran <strong><?= lm_rupiah($L['kpi']['pengeluaran']) ?></strong>.
        Prospek baru <strong><?= $L['kpi']['prospek_baru'] ?></strong>, follow-up <strong><?= $L['kpi']['followup'] ?></strong>,
        berkas/BI Checking <strong><?= $L['kpi']['berkas'] ?></strong>.
        <?php if ($L['jml_telat'] > 0): ?>
        Terdapat <strong><?= $L['jml_telat'] ?></strong> item melewati tenggat.
        <?php else: ?>
        Tidak ada item melewati tenggat.
        <?php endif; ?>
        <?php if (!empty($L['highlight'])): ?>
        <ul class="hl-list">
            <?php foreach ($L['highlight'] as $h): ?>
            <li><?= htmlspecialchars($h) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <div class="section-title">Rekap per Cluster</div>
    <table>
        <thead>
            <tr>
                <th>Cluster</th>
                <th class="text-center">Booking</th>
                <th class="text-center">Akad</th>
                <th class="text-right">Kas Masuk</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($L['rekap'])): ?>
            <tr><td colspan="4" class="text-center">Belum ada aktivitas pada minggu ini.</td></tr>
            <?php else: foreach ($L['rekap'] as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['cluster']) ?></td>
                <td class="text-center"><?= $r['booking'] ?: '-' ?></td>
                <td class="text-center"><?= $r['akad'] ?: '-' ?></td>
                <td class="text-right"><?= $r['kas'] > 0 ? lm_rupiah($r['kas']) : '-' ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="section-title">Kegiatan Harian</div>
    <table>
        <thead>
            <tr>
                <th>Total</th><th class="text-center">Selesai</th><th class="text-center">Berlangsung</th>
                <th class="text-center">Terhambat</th><th class="text-center">Belum</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center"><?= $L['kegiatan']['total'] ?></td>
                <td class="text-center"><?= $L['kegiatan']['selesai'] ?></td>
                <td class="text-center"><?= $L['kegiatan']['berlangsung'] ?></td>
                <td class="text-center"><?= $L['kegiatan']['terhambat'] ?></td>
                <td class="text-center"><?= $L['kegiatan']['belum'] ?></td>
            </tr>
        </tbody>
    </table>
    <?php if (!empty($L['keg_kategori'])): ?>
    <table style="margin-top:6px">
        <thead><tr><th>Kategori Kegiatan</th><th class="text-center">Jumlah</th></tr></thead>
        <tbody>
            <?php foreach ($L['keg_kategori'] as $kat => $n): ?>
            <tr><td><?= htmlspecialchars($kat) ?></td><td class="text-center"><?= $n ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="section-title">Sumber Prospek</div>
    <table>
        <thead><tr><th>Sumber</th><th class="text-center">Jumlah</th></tr></thead>
        <tbody>
            <?php if (empty($L['sumber'])): ?>
            <tr><td colspan="2" class="text-center">Belum ada prospek baru.</td></tr>
            <?php else: foreach ($L['sumber'] as $src => $n): ?>
            <tr><td><?= htmlspecialchars($src ?: 'Lainnya') ?></td><td class="text-center"><?= $n ?></td></tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="section-title">Pengingat &amp; Tunggakan (<?= count($L['tunggakan']) ?> item, <?= $L['jml_telat'] ?> telat)</div>
    <table>
        <thead>
            <tr><th>Jenis</th><th>Nama / Unit</th><th>Jatuh Tempo</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php if (empty($L['tunggakan'])): ?>
            <tr><td colspan="4" class="text-center">Tidak ada tunggakan.</td></tr>
            <?php else: foreach ($L['tunggakan'] as $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['label']) ?></td>
                <td><?= htmlspecialchars($it['nama']) ?> — <?= htmlspecialchars($it['unit']) ?></td>
                <td class="text-center"><?= htmlspecialchars(date('d/m/Y', strtotime($it['tanggal']))) ?></td>
                <td class="text-center"><?= htmlspecialchars($it['st']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="catatan">
        <strong>Dicetak :</strong> <?= date('d/m/Y H:i:s') ?> WIB &nbsp;|&nbsp;
        Dicetak oleh: <?= htmlspecialchars($_SESSION['username'] ?? '-') ?>
    </div>
</div>

<script>
    window.onload = function() { window.print(); };
</script>
</body>
</html>