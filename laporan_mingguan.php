<?php

session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
include "includes/koneksi.php";
require_once __DIR__ . '/includes/laporan_mingguan_data.php';

// Minggu terpilih (default: minggu berjalan)
list($week, $start, $end) = lm_parse_week($_GET['week'] ?? '');

$L = lm_hitung($koneksi, $start, $end);

// Navigasi minggu sebelumnya / berikutnya
$prevWeek = (new DateTime($start))->modify('-7 days')->format('o-\WW');
$nextWeek = (new DateTime($start))->modify('+7 days')->format('o-\WW');

$labelPeriode = date('d M Y', strtotime($start)) . ' — ' . date('d M Y', strtotime($end));
$pdfUrl = 'print/cetak_laporan_mingguan.php?week=' . urlencode($week);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Laporan Mingguan - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .kpi-card { border-left: 4px solid #1e3a8a; }
        .kpi-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.4px; }
        .hl-item { background: #f8fafc; border-left: 3px solid #2563eb; padding: 8px 12px; border-radius: 0 8px 8px 0; font-size: 0.85rem; }
        @media print {
            .sidebar, .sidebar-toggle-btn, .app-overlay, .btn, .no-print { display: none !important; }
            .main-wrapper { margin-left: 0 !important; padding: 10px !important; }
        }
    </style>
</head>
<body>

<?php include "sidebar.php"; ?>

<div class="app-overlay" onclick="document.body.classList.toggle('sidebar-open')"></div>
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Laporan Mingguan</h4>
            <p class="text-muted small mb-0">Ringkasan penjualan, kas, prospek &amp; kegiatan — periode <?= htmlspecialchars($labelPeriode) ?>.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <div class="btn-group">
                <a href="laporan_mingguan.php?week=<?= $prevWeek ?>" class="btn btn-sm btn-outline-secondary" title="Minggu sebelumnya"><i class="bi bi-chevron-left"></i></a>
                <form method="GET" class="d-flex">
                    <input type="week" name="week" class="form-control form-control-sm" style="width: 180px;" value="<?= htmlspecialchars($week) ?>" onchange="this.form.submit()">
                </form>
                <a href="laporan_mingguan.php?week=<?= $nextWeek ?>" class="btn btn-sm btn-outline-secondary" title="Minggu berikutnya"><i class="bi bi-chevron-right"></i></a>
            </div>
            <a href="laporan_mingguan.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-calendar-week me-1"></i> Minggu Ini</a>
            <a href="<?= $pdfUrl ?>" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</a>
        </div>
    </div>

    <!-- KPI PENJUALAN & KAS -->
    <div class="row g-3 mb-3">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card kpi-card">
                <div class="kpi-label text-muted">Unit Booking</div>
                <div class="fw-bold fs-3 text-dark"><?= number_format($L['kpi']['booking'], 0, ',', '.') ?></div>
                <div class="text-muted small">unit masuk minggu ini</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card kpi-card" style="border-color:#16a34a">
                <div class="kpi-label text-muted">Akad</div>
                <div class="fw-bold fs-3" style="color:#16a34a"><?= number_format($L['kpi']['akad'], 0, ',', '.') ?></div>
                <div class="text-muted small">unit akad minggu ini</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card kpi-card" style="border-color:#0891b2">
                <div class="kpi-label text-muted">Kas Masuk</div>
                <div class="fw-bold fs-4" style="color:#0891b2"><?= lm_rupiah($L['kpi']['kas_masuk']) ?></div>
                <div class="text-muted small">total pembayaran diterima</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card kpi-card" style="border-color:#dc2626">
                <div class="kpi-label text-muted">Pengeluaran</div>
                <div class="fw-bold fs-4" style="color:#dc2626"><?= lm_rupiah($L['kpi']['pengeluaran']) ?></div>
                <div class="text-muted small">saldo kas: <?= lm_rupiah($L['kpi']['saldo']) ?></div>
            </div>
        </div>
    </div>

    <!-- KPI AKTIVITAS -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#7c3aed"><?= number_format($L['kpi']['prospek_baru'], 0, ',', '.') ?></div>
                <div class="text-muted small">Prospek Baru</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#db2777"><?= number_format($L['kpi']['followup'], 0, ',', '.') ?></div>
                <div class="text-muted small">Jadwal Follow-up</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#0e7490"><?= number_format($L['kpi']['berkas'], 0, ',', '.') ?></div>
                <div class="text-muted small">BI Checking / Berkas</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#2563eb"><?= number_format($L['kegiatan']['selesai'], 0, ',', '.') ?>/<?= number_format($L['kegiatan']['total'], 0, ',', '.') ?></div>
                <div class="text-muted small">Kegiatan Selesai</div>
            </div>
        </div>
    </div>

    <!-- RINGKASAN NARATIF -->
    <div class="content-card mb-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-stars text-primary me-1"></i> Ringkasan Otomatis</h6>
        <p class="mb-0" style="font-size: 0.9rem;">
            Pada periode <strong><?= htmlspecialchars($labelPeriode) ?></strong>,
            tercatat <strong><?= $L['kpi']['booking'] ?></strong> unit booking dan <strong><?= $L['kpi']['akad'] ?></strong> unit akad.
            Kas masuk sebesar <strong><?= lm_rupiah($L['kpi']['kas_masuk']) ?></strong> dengan pengeluaran <strong><?= lm_rupiah($L['kpi']['pengeluaran']) ?></strong>.
            Prospek baru <strong><?= $L['kpi']['prospek_baru'] ?></strong> orang dengan <strong><?= $L['kpi']['followup'] ?></strong> jadwal follow-up,
            serta <strong><?= $L['kpi']['berkas'] ?></strong> proses berkas/BI Checking.
            <?php if ($L['jml_telat'] > 0): ?>
            Terdapat <strong class="text-danger"><?= $L['jml_telat'] ?></strong> item melewati tenggat yang perlu ditindaklanjuti.
            <?php else: ?>
            Tidak ada item yang melewati tenggat. 👍
            <?php endif; ?>
        </p>
        <?php if (!empty($L['highlight'])): ?>
        <div class="row g-2 mt-3">
            <?php foreach ($L['highlight'] as $h): ?>
            <div class="col-md-6"><div class="hl-item"><i class="bi bi-award text-primary me-1"></i> <?= htmlspecialchars($h) ?></div></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- REKAP PER CLUSTER -->
        <div class="col-lg-7">
            <div class="content-card h-100">
                <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt text-primary me-1"></i> Rekap per Cluster</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cluster</th>
                                <th class="text-center">Booking</th>
                                <th class="text-center">Akad</th>
                                <th class="text-end">Kas Masuk</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($L['rekap'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada aktivitas pada minggu ini.</td></tr>
                            <?php else: foreach ($L['rekap'] as $r): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($r['cluster']) ?></td>
                                <td class="text-center"><?= $r['booking'] ?: '-' ?></td>
                                <td class="text-center"><?= $r['akad'] ?: '-' ?></td>
                                <td class="text-end fw-semibold"><?= $r['kas'] > 0 ? lm_rupiah($r['kas']) : '-' ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- KEGIATAN HARIAN -->
        <div class="col-lg-5">
            <div class="content-card h-100">
                <h6 class="fw-bold mb-3"><i class="bi bi-journal-text text-primary me-1"></i> Kegiatan Harian</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-secondary">Total <?= $L['kegiatan']['total'] ?></span>
                    <span class="badge bg-success">Selesai <?= $L['kegiatan']['selesai'] ?></span>
                    <span class="badge bg-primary">Berlangsung <?= $L['kegiatan']['berlangsung'] ?></span>
                    <span class="badge bg-danger">Terhambat <?= $L['kegiatan']['terhambat'] ?></span>
                    <span class="badge bg-warning text-dark">Belum <?= $L['kegiatan']['belum'] ?></span>
                </div>
                <?php if (empty($L['keg_kategori'])): ?>
                <p class="text-muted small mb-0">Belum ada laporan kegiatan minggu ini.</p>
                <?php else: ?>
                <table class="table table-sm mb-0" style="font-size:0.85rem">
                    <?php foreach ($L['keg_kategori'] as $kat => $n): ?>
                    <tr><td><?= htmlspecialchars($kat) ?></td><td class="text-end fw-semibold"><?= $n ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <!-- SUMBER PROSPEK -->
        <div class="col-lg-5">
            <div class="content-card h-100">
                <h6 class="fw-bold mb-3"><i class="bi bi-megaphone text-primary me-1"></i> Sumber Prospek</h6>
                <?php if (empty($L['sumber'])): ?>
                <p class="text-muted small mb-0">Belum ada prospek baru minggu ini.</p>
                <?php else: ?>
                <table class="table table-sm mb-0" style="font-size:0.85rem">
                    <?php foreach ($L['sumber'] as $src => $n): ?>
                    <tr><td><?= htmlspecialchars($src ?: 'Lainnya') ?></td><td class="text-end fw-semibold"><?= $n ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- PENGINGAT & TUNGGAKAN -->
        <div class="col-lg-7">
            <div class="content-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-bell text-danger me-1"></i> Pengingat &amp; Tunggakan</h6>
                    <a href="pengingat.php" class="btn btn-sm btn-outline-secondary rounded-pill px-2">Kelola</a>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-secondary">Total <?= count($L['tunggakan']) ?></span>
                    <span class="badge bg-danger">Telat <?= $L['jml_telat'] ?></span>
                </div>
                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.82rem">
                        <thead class="table-light">
                            <tr><th>Jenis</th><th>Nama / Unit</th><th>Jatuh Tempo</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($L['tunggakan'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada tunggakan. 👍</td></tr>
                            <?php else: foreach ($L['tunggakan'] as $it): ?>
                            <tr>
                                <td><span class="text-muted"><?= htmlspecialchars($it['label']) ?></span></td>
                                <td><?= htmlspecialchars($it['nama']) ?><br><small class="text-muted"><?= htmlspecialchars($it['unit']) ?></small></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($it['tanggal']))) ?></td>
                                <td><span class="badge <?= $it['badge'] ?>"><?= htmlspecialchars($it['st']) ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
