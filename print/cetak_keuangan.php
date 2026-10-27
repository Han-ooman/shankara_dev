<?php
require_once __DIR__ . '/../includes/session_db.php'; // sesi via DB (Vercel-safe)
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include __DIR__ . "/../includes/cek_sesi.php";
include __DIR__ . "/../includes/koneksi.php";

function rupiah($n) { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }

// Filter
$f = [];
foreach ([4, 5, 7, 8] as $c) { $f['f' . $c] = trim($_GET['f' . $c] ?? ''); }
$f['f0'] = trim($_GET['f0'] ?? '');
$f['f1'] = trim($_GET['f1'] ?? '');
$f['f2'] = trim($_GET['f2'] ?? '');
$f['f3'] = trim($_GET['f3'] ?? '');

$q = "SELECT k.*, c.cluster_name FROM data_keuangan k LEFT JOIN data_cluster c ON k.cluster_id = c.id_cluster WHERE 1=1";
if ($f['f0'] !== '') $q .= " AND k.tanggal = '" . sdb_real_escape_string($koneksi, $f['f0']) . "'";
if ($f['f1'] !== '') $q .= " AND k.jenis = '" . sdb_real_escape_string($koneksi, $f['f1']) . "'";
if ($f['f2'] !== '') $q .= " AND k.kategori LIKE '%" . sdb_real_escape_string($koneksi, $f['f2']) . "%'";
if ($f['f3'] !== '') $q .= " AND c.cluster_name LIKE '%" . sdb_real_escape_string($koneksi, $f['f3']) . "%'";
if ($f['f4'] !== '') $q .= " AND k.deskripsi LIKE '%" . sdb_real_escape_string($koneksi, $f['f4']) . "%'";
if ($f['f7'] !== '') $q .= " AND k.metode_bayar LIKE '%" . sdb_real_escape_string($koneksi, $f['f7']) . "%'";
if ($f['f8'] !== '') $q .= " AND k.no_ref LIKE '%" . sdb_real_escape_string($koneksi, $f['f8']) . "%'";
$q .= " ORDER BY k.tanggal DESC, k.id_keuangan DESC";

$result = sdb_query($koneksi, $q);
$rows = [];
$total_masuk = 0;
$total_keluar = 0;
while ($r = sdb_fetch_assoc($result)) {
    $rows[] = $r;
    if ($r['jenis'] === 'Pemasukan') $total_masuk += floatval($r['nominal']);
    else $total_keluar += floatval($r['nominal']);
}
$saldo = $total_masuk - $total_keluar;

$has_filter = array_filter($f, function($v) { return $v !== ''; });
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../assets/images/favicon/favicon.png">
    <title>Laporan Keuangan - Shankara Dev</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f5; padding: 10px 0; margin: 0; color: #111; }

        .no-print-btn { text-align: center; margin-bottom: 15px; }
        .btn-print { background-color: #1e3a8a; color: #fff; border: none; padding: 10px 24px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; }
        .btn-back { background-color: #64748b; color: #fff; border: none; padding: 10px 24px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; margin-left: 8px; text-decoration: none; display: inline-block; }

        .sheet { background: #fff; border: 2px solid #1e3a8a; border-radius: 12px; padding: 20px 24px; margin: 0 auto; max-width: 297mm; }
        .sheet-title { text-align: center; font-size: 16px; font-weight: 800; letter-spacing: 0.5px; color: #1e3a8a; text-transform: uppercase; margin-bottom: 4px; border-bottom: 3px double #1e3a8a; padding-bottom: 8px; }
        .sheet-sub { text-align: center; font-size: 11px; color: #475569; margin-bottom: 12px; }

        .filter-summary { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; padding: 8px 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; font-size: 11px; }
        .filter-summary .fchip { background: #fff; border: 1px solid #bfdbfe; border-radius: 20px; padding: 3px 10px; }

        .summary-row { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
        .summary-box { flex: 1; min-width: 150px; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .summary-box .s-label { font-size: 10px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .summary-box .s-value { font-size: 16px; font-weight: 800; margin-top: 2px; }

        table.data-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .data-table th, .data-table td { border: 1px solid #1e3a8a; padding: 5px 6px; text-align: left; vertical-align: middle; }
        .data-table th { background: #eff6ff; color: #1e3a8a; font-weight: 700; text-align: center; }
        .data-table .center { text-align: center; }
        .data-table .right { text-align: right; }
        .b-masuk { color: #2563eb; font-weight: 700; }
        .b-keluar { color: #dc2626; font-weight: 700; }

        .footer-note { margin-top: 12px; font-size: 10px; color: #475569; text-align: right; }

        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            html, body { background: #fff; padding: 0 !important; margin: 0 !important; }
            .sheet { box-shadow: none; border: 2px solid #1e3a8a; }
            .no-print-btn { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print-btn">
    <button onclick="window.print()" class="btn-print">Cetak / Simpan PDF</button>
    <a href="keuangan.php" class="btn-back">Kembali</a>
</div>

<div class="sheet">
    <div class="sheet-title">Laporan Keuangan Perusahaan</div>
    <div class="sheet-sub">Shankara Dev &bull; Dicetak <?= date('d M Y H:i') ?> &bull; <?= count($rows) ?> transaksi</div>

    <?php if (!empty($has_filter)): ?>
    <div class="filter-summary"><strong style="margin-right:2px;">Filter:</strong>
        <?php if ($f['f0'] !== '') echo '<span class="fchip"><strong>Tanggal:</strong> ' . htmlspecialchars($f['f0']) . '</span>'; ?>
        <?php if ($f['f1'] !== '') echo '<span class="fchip"><strong>Jenis:</strong> ' . htmlspecialchars($f['f1']) . '</span>'; ?>
        <?php if ($f['f2'] !== '') echo '<span class="fchip"><strong>Kategori:</strong> ' . htmlspecialchars($f['f2']) . '</span>'; ?>
        <?php if ($f['f3'] !== '') echo '<span class="fchip"><strong>Cluster:</strong> ' . htmlspecialchars($f['f3']) . '</span>'; ?>
        <?php if ($f['f4'] !== '') echo '<span class="fchip"><strong>Deskripsi:</strong> ' . htmlspecialchars($f['f4']) . '</span>'; ?>
        <?php if ($f['f7'] !== '') echo '<span class="fchip"><strong>Metode:</strong> ' . htmlspecialchars($f['f7']) . '</span>'; ?>
        <?php if ($f['f8'] !== '') echo '<span class="fchip"><strong>Ref:</strong> ' . htmlspecialchars($f['f8']) . '</span>'; ?>
    </div>
    <?php endif; ?>

    <div class="summary-row">
        <div class="summary-box" style="border-left: 4px solid #2563eb;">
            <div class="s-label">Total Pemasukan</div>
            <div class="s-value b-masuk"><?= rupiah($total_masuk) ?></div>
        </div>
        <div class="summary-box" style="border-left: 4px solid #dc2626;">
            <div class="s-label">Total Pengeluaran</div>
            <div class="s-value b-keluar"><?= rupiah($total_keluar) ?></div>
        </div>
        <div class="summary-box" style="border-left: 4px solid <?= $saldo>=0?'#2563eb':'#dc2626' ?>;">
            <div class="s-label">Saldo / Selisih</div>
            <div class="s-value" style="color: <?= $saldo>=0?'#2563eb':'#dc2626' ?>"><?= rupiah($saldo) ?></div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:24px;">No</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Kategori</th>
                <th>Cluster</th>
                <th>Deskripsi</th>
                <th>Nominal</th>
                <th>Metode Bayar</th>
                <th>No. Ref</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="center" style="padding:18px; color:#64748b;">Tidak ada data transaksi.</td></tr>
        <?php else: $no = 1; foreach ($rows as $rw): ?>
            <tr>
                <td class="center"><?= $no++ ?></td>
                <td class="center"><?= date('d/m/Y', strtotime($rw['tanggal'])) ?></td>
                <td class="center"><span style="padding:2px 8px; border-radius:10px; font-weight:700; color:#fff; font-size:9.5px; background:<?= $rw['jenis']==='Pemasukan'?'#2563eb':'#dc2626' ?>;"><?= $rw['jenis'] ?></span></td>
                <td><?= htmlspecialchars($rw['kategori']) ?></td>
                <td style="font-size:9.5px;"><?= htmlspecialchars($rw['cluster_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($rw['deskripsi'] ?? '-') ?></td>
                <td class="right <?= $rw['jenis']==='Pemasukan'?'b-masuk':'b-keluar' ?>"><?= $rw['jenis']==='Pemasukan'?'+':'-' ?> <?= rupiah($rw['nominal']) ?></td>
                <td class="center"><?= htmlspecialchars($rw['metode_bayar']) ?></td>
                <td class="center"><?= htmlspecialchars($rw['no_ref'] ?: '-') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="footer-note">Dokumen ini dihasilkan otomatis oleh Sistem Database Shankara Dev.</div>
</div>

<script>
    window.onload = function () { setTimeout(function () { window.print(); }, 400); };
</script>
</body>
</html>
