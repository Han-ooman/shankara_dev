<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "../includes/cek_sesi.php";
include "../includes/koneksi.php";

// Filter
$where = "1=1";
$filter_tanggal  = $_GET['tanggal']  ?? '';
$filter_kategori = $_GET['kategori'] ?? '';
$filter_status   = $_GET['status']   ?? '';
$filter_keyword  = $_GET['keyword']  ?? '';

if (!empty($filter_tanggal))  $where .= " AND tanggal = '" . mysqli_real_escape_string($koneksi, $filter_tanggal) . "'";
if (!empty($filter_kategori)) $where .= " AND kategori = '" . mysqli_real_escape_string($koneksi, $filter_kategori) . "'";
if (!empty($filter_status))   $where .= " AND status = '" . mysqli_real_escape_string($koneksi, $filter_status) . "'";
if (!empty($filter_keyword))  {
    $kw = mysqli_real_escape_string($koneksi, $filter_keyword);
    $where .= " AND (nama_lengkap LIKE '%$kw%' OR judul_kegiatan LIKE '%$kw%' OR detail_kegiatan LIKE '%$kw%')";
}

$result = mysqli_query($koneksi, "SELECT * FROM data_laporan_harian WHERE $where ORDER BY tanggal DESC, jam_mulai DESC");
$rows = [];
while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

$has_filter = !empty($filter_tanggal) || !empty($filter_kategori) || !empty($filter_status) || !empty($filter_keyword);

// Hitung rekap status
$rekap = ['Selesai'=>0, 'Sedang Berlangsung'=>0, 'Terhambat'=>0, 'Belum Dikerjakan'=>0];
foreach ($rows as $r) { $rekap[$r['status']] = ($rekap[$r['status']] ?? 0) + 1; }

// Group by date
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['tanggal']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../assets/images/favicon/favicon.png">
    <title>Laporan Harian Pekerjaan - Shankara Dev</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f5; padding: 10px 0; margin: 0; color: #111; }

        .no-print-btn { text-align: center; margin-bottom: 15px; }
        .btn-print { background-color: #1e3a8a; color: #fff; border: none; padding: 10px 24px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; }
        .btn-back { background-color: #64748b; color: #fff; border: none; padding: 10px 24px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; margin-left: 8px; text-decoration: none; display: inline-block; }

        .sheet { background: #fff; border: 2px solid #1e3a8a; border-radius: 12px; padding: 24px 28px; margin: 0 auto; max-width: 210mm; }
        .sheet-title { text-align: center; font-size: 16px; font-weight: 800; letter-spacing: 0.5px; color: #1e3a8a; text-transform: uppercase; margin-bottom: 4px; border-bottom: 3px double #1e3a8a; padding-bottom: 8px; }
        .sheet-sub { text-align: center; font-size: 11px; color: #475569; margin-bottom: 12px; }
        .sheet-meta { font-size: 11px; color: #64748b; margin-bottom: 16px; display: flex; justify-content: space-between; }

        .rekap-box { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .rekap-item { flex: 1; min-width: 100px; text-align: center; padding: 8px 4px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .rekap-item .num { font-size: 20px; font-weight: 800; }
        .rekap-item .label { font-size: 10px; color: #64748b; text-transform: uppercase; }

        .date-group { margin-bottom: 16px; }
        .date-label { font-size: 12px; font-weight: 700; color: #1e3a8a; background: #eff6ff; padding: 4px 10px; border-radius: 6px; margin-bottom: 6px; display: inline-block; }

        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 8px; }
        th { background: #1e3a8a; color: #fff; padding: 7px 8px; text-align: left; font-weight: 600; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) { background: #f9fafb; }

        .badge { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; display: inline-block; }
        .badge-selesai { background: #dbeafe; color: #2563eb; }
        .badge-proses { background: #dbeafe; color: #1d4ed8; }
        .badge-terhambat { background: #fee2e2; color: #dc2626; }
        .badge-belum { background: #fef9c3; color: #a16207; }
        .badge-admin { background: #ede9fe; color: #7c3aed; }
        .badge-marketing { background: #fce7f3; color: #db2777; }

        .sheet-footer { margin-top: 20px; text-align: right; font-size: 10px; color: #94a3b8; border-top: 1px solid #e5e7eb; padding-top: 8px; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print-btn { display: none; }
            .sheet { border: 1px solid #1e3a8a; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="no-print-btn">
    <button class="btn-print" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak / Simpan PDF</button>
    <a href="laporan_harian.php<?= $has_filter ? '?' . http_build_query(['tanggal'=>$filter_tanggal, 'kategori'=>$filter_kategori, 'status'=>$filter_status, 'keyword'=>$filter_keyword]) : '' ?>" class="btn-back"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<div class="sheet">
    <div class="sheet-title">Laporan Harian Pekerjaan</div>
    <div class="sheet-sub">PT. Golden Shankara Indonesia - Sistem Manajemen Perumahan Shankara Dev</div>
    <div class="sheet-meta">
        <span>Dicetak: <?= date('d M Y, H:i') ?></span>
        <span>Total: <?= count($rows) ?> kegiatan</span>
        <?php if ($has_filter): ?>
            <span>Filter: <?= htmlspecialchars(array_filter([
                'Tanggal'=>$filter_tanggal, 'Kategori'=>$filter_kategori, 'Status'=>$filter_status, 'Keyword'=>$filter_keyword
            ]) ? implode(', ', array_filter(['Tanggal'=>$filter_tanggal, 'Kategori'=>$filter_kategori, 'Status'=>$filter_status, 'Keyword'=>$filter_keyword])) : 'Semua') ?></span>
        <?php endif; ?>
    </div>

    <!-- REKAP -->
    <div class="rekap-box">
        <div class="rekap-item">
            <div class="num" style="color:#1e3a8a"><?= count($rows) ?></div>
            <div class="label">Total</div>
        </div>
        <div class="rekap-item">
            <div class="num" style="color:#2563eb"><?= $rekap['Selesai'] ?></div>
            <div class="label">Selesai</div>
        </div>
        <div class="rekap-item">
            <div class="num" style="color:#1d4ed8"><?= $rekap['Sedang Berlangsung'] ?></div>
            <div class="label">Berlangsung</div>
        </div>
        <div class="rekap-item">
            <div class="num" style="color:#dc2626"><?= $rekap['Terhambat'] ?></div>
            <div class="label">Terhambat</div>
        </div>
        <div class="rekap-item">
            <div class="num" style="color:#a16207"><?= $rekap['Belum Dikerjakan'] ?></div>
            <div class="label">Belum</div>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <p style="text-align:center; color:#94a3b8; padding:40px 0;">Tidak ada data laporan untuk filter yang dipilih.</p>
    <?php else: ?>
        <?php foreach ($grouped as $tgl => $items): ?>
            <div class="date-group">
                <div class="date-label">ðŸ“… <?= date('l, d F Y', strtotime($tgl)) ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:25px">#</th>
                            <th style="width:60px">Jam</th>
                            <th style="width:65px">Kategori</th>
                            <th>Nama</th>
                            <th>Kegiatan</th>
                            <th style="width:70px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($items as $r): ?>
                            <?php
                                $bs = 'badge-selesai';
                                if ($r['status'] === 'Sedang Berlangsung') $bs = 'badge-proses';
                                elseif ($r['status'] === 'Terhambat') $bs = 'badge-terhambat';
                                elseif ($r['status'] === 'Belum Dikerjakan') $bs = 'badge-belum';
                                $bk = $r['kategori'] === 'Admin' ? 'badge-admin' : 'badge-marketing';
                                $jam = '';
                                if ($r['jam_mulai'] && $r['jam_selesai']) $jam = date('H:i', strtotime($r['jam_mulai'])) . '-' . date('H:i', strtotime($r['jam_selesai']));
                                elseif ($r['jam_mulai']) $jam = date('H:i', strtotime($r['jam_mulai']));
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= $jam ?: '-' ?></td>
                                <td><span class="badge <?= $bk ?>"><?= $r['kategori'] ?></span></td>
                                <td><?= htmlspecialchars($r['nama_lengkap']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['judul_kegiatan']) ?></strong>
                                    <?php if (!empty($r['detail_kegiatan'])): ?>
                                        <br><span style="color:#64748b;"><?= htmlspecialchars($r['detail_kegiatan']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($r['catatan'])): ?>
                                        <br><em style="color:#94a3b8; font-size:10px;">Catatan: <?= htmlspecialchars($r['catatan']) ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $bs ?>"><?= $r['status'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="sheet-footer">
        Laporan ini dicetak secara otomatis dari sistem Shankara Dev &mdash; PT. Golden Shankara Indonesia
    </div>
</div>

</body>
</html>
