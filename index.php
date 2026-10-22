<?php
include 'includes/koneksi.php';
include 'includes/berkas_helper.php';
include 'includes/cluster_hold.php';

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// 1. Ambil Stat Ringkasan (1 query)
$q_stat = mysqli_query($koneksi, "SELECT COUNT(*) as total,
    SUM(status_unit = 'terjual') as terjual,
    SUM(status_unit = 'terbooking') as booking,
    SUM(status_unit = 'tersedia') as tersedia
FROM data_rumah");
$stat = mysqli_fetch_assoc($q_stat);
$total_unit    = intval($stat['total']);
$total_terjual = intval($stat['terjual']);
$total_booking = intval($stat['booking']);
$total_tersedia= intval($stat['tersedia']);

// 1b. Statistik BI Checking (1 query)
$q_bi = mysqli_query($koneksi, "SELECT COUNT(*) AS total,
    SUM(hasil_bi = 'Lulus') AS lulus,
    SUM(hasil_bi = 'Dalam Proses') AS proses,
    SUM(hasil_bi IN ('Tidak Lulus','Dipertimbangkan')) AS tidak
FROM data_bi_checking");
$bi = mysqli_fetch_assoc($q_bi);
$bi_total = intval($bi['total']);
$bi_lulus = intval($bi['lulus']);
$bi_proses= intval($bi['proses']);
$bi_tidak = intval($bi['tidak']);

// 1b2. Data BI Checking Terbaru
$q_bi_recent = mysqli_query($koneksi, "SELECT b.*, k.name_lengkap
    FROM data_bi_checking b
    LEFT JOIN data_konsumen k ON k.id_konsumen = b.id_konsumen
    ORDER BY b.tgl_cek DESC, b.id_bi_checking DESC LIMIT 5");
$bi_recent_rows = [];
if ($q_bi_recent) { while ($br = mysqli_fetch_assoc($q_bi_recent)) $bi_recent_rows[] = $br; }

// 1c. Notifikasi Deadline Pemberkasan (deadline <= 7 hari ke depan, berkas belum lengkap)
$deadline_list = [];
$q_deadline = mysqli_query($koneksi, "SELECT * FROM view_database_utama 
                                      WHERE deadline_berkas IS NOT NULL 
                                        AND deadline_berkas != '' 
                                        AND deadline_berkas != '0000-00-00' 
                                        AND deadline_berkas <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                                      ORDER BY deadline_berkas ASC");
if ($q_deadline && mysqli_num_rows($q_deadline) > 0) {
    while ($drow = mysqli_fetch_assoc($q_deadline)) {
        $saved = !empty($drow['pemberkasan_json']) ? json_decode($drow['pemberkasan_json'], true) : [];
        if (!is_array($saved)) $saved = [];
        $prog = hitungProgresBerkas($saved, $drow['skema_bayar'] ?? 'Cash', $master_dokumen);
        if ($prog['lengkap']) continue;
        $sisa = floor((strtotime($drow['deadline_berkas']) - strtotime(date('Y-m-d'))) / 86400);
        $drow['sisa_hari'] = $sisa;
        $drow['persen'] = $prog['persen'];
        $deadline_list[] = $drow;
    }
}

// 2. Query 10 Data Transaksi Terbaru
$query = "SELECT * FROM view_database_utama ORDER BY id_transaksi DESC LIMIT 10";
$result = mysqli_query($koneksi, $query);

// 3. Laporan Harian Hari Ini
$today = date('Y-m-d');
$q_lh = mysqli_query($koneksi, "SELECT *,
    CASE
        WHEN status='Selesai' THEN 1
        WHEN status='Sedang Berlangsung' THEN 2
        WHEN status='Terhambat' THEN 3
        WHEN status='Belum Dikerjakan' THEN 4
    END AS sort_order
FROM data_laporan_harian WHERE tanggal='$today' ORDER BY sort_order ASC, jam_mulai ASC");
$lh_rows = [];
if ($q_lh) { while ($lr = mysqli_fetch_assoc($q_lh)) $lh_rows[] = $lr; }
$lh_count = count($lh_rows);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Shankara Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        .stat-card.primary {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #ffffff;
        }

        .stat-card.primary .text-muted-custom {
            color: rgba(255, 255, 255, 0.7);
        }

        .btn-outline-custom {
            border: 1px solid #cbd5e1;
            border-radius: 30px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
            background: #fff;
        }

        .badge-soft-success { background-color: #dbeafe; color: #2563eb; }
        .badge-soft-warning { background-color: #fef9c3; color: #a16207; }
        .badge-soft-secondary { background-color: #f1f5f9; color: #475569; }
        .bg-purple { background-color: #ede9fe; color: #7c3aed; }
        .bg-pink { background-color: #fce7f3; color: #db2777; }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>

    <?php include "sidebar.php"; ?>
    <!-- MAIN CONTENT -->
    <main class="main-wrapper">
        
        <header class="d-flex justify-content-between align-items-center mb-4">
            <!-- REAL-TIME CLOCK -->
            <div class="dash-clock d-none d-md-flex" id="dashClock">
                <div class="dc-icon"><i class="bi bi-clock-history"></i></div>
                <div class="dc-body">
                    <span class="dc-time" id="dcTime">00:00</span>
                    <span class="dc-date" id="dcDate">--, -- -- ----</span>
                </div>
            </div>
            <style>
                .dash-clock {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 8px 16px;
                    background: linear-gradient(135deg, #f0fdf4, #dbeafe);
                    border: 1px solid #bbf7d0;
                    border-radius: 14px;
                }
                .dc-icon {
                    width: 38px; height: 38px;
                    background: linear-gradient(135deg, #1e3a8a, #2563eb);
                    color: #fff;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1rem;
                }
                .dc-body {
                    display: flex;
                    flex-direction: column;
                    line-height: 1.2;
                }
                .dc-time {
                    font-family: 'Courier New', monospace;
                    font-size: 1.15rem;
                    font-weight: 700;
                    color: #1e3a8a;
                    letter-spacing: 1px;
                }
                .dc-date {
                    font-size: 0.7rem;
                    color: #2563eb;
                    font-weight: 500;
                    text-transform: capitalize;
                }
            </style>

            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <h6 class="mb-0 fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></h6>
                    <small class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($_SESSION['role'] ?? 'Administrator') ?></small>
                </div>
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px;">
                    <?= strtoupper(substr(trim($_SESSION['nama_lengkap'] ?? 'P'), 0, 1)) ?>
                </div>
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Dashboard Perumahan</h3>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola unit, transaksi booking, dan tanggal akad konsumen.</p>
            </div>
        </div>

        <!-- STATS WIDGETS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Total Unit</span>
                        <i class="bi bi-houses fs-5"></i>
                    </div>
                    <h2 class="fw-bold mb-1"><?= $total_unit; ?></h2>
                    <span class="badge bg-white text-dark rounded-pill" style="font-size: 0.75rem;">Semua Cluster</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Unit Terjual</span>
                        <i class="bi bi-check-circle fs-5 text-success"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-dark"><?= $total_terjual; ?></h2>
                    <span class="badge badge-soft-success rounded-pill" style="font-size: 0.75rem;">Akad / AJB</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Terbooking</span>
                        <i class="bi bi-clock-history fs-5 text-warning"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-dark"><?= $total_booking; ?></h2>
                    <span class="badge badge-soft-warning rounded-pill" style="font-size: 0.75rem;">Progres Berkas</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Unit Tersedia</span>
                        <i class="bi bi-box-seam fs-5 text-primary"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-dark"><?= $total_tersedia; ?></h2>
                    <span class="badge badge-soft-secondary rounded-pill" style="font-size: 0.75rem;">Ready Stock</span>
                </div>
            </div>
        </div>

        <!-- BI CHECKING WIDGETS -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">BI Checking Konsumen</h6>
            <a href="bi_checking.php" class="small text-success text-decoration-none fw-medium">Lihat Semua <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Total Pengecekan</span>
                        <i class="bi bi-clipboard-check fs-5 text-primary"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-dark"><?= $bi_total; ?></h2>
                    <span class="badge badge-soft-secondary rounded-pill" style="font-size: 0.75rem;">SLIK / OJK / Bank</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Lulus BI Checking</span>
                        <i class="bi bi-check-circle fs-5 text-success"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-dark"><?= $bi_lulus; ?></h2>
                    <span class="badge badge-soft-success rounded-pill" style="font-size: 0.75rem;">Layak / ACC</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Dalam Proses</span>
                        <i class="bi bi-hourglass-split fs-5 text-warning"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-dark"><?= $bi_proses; ?></h2>
                    <span class="badge badge-soft-warning rounded-pill" style="font-size: 0.75rem;">Menunggu Hasil</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted-custom">Tidak Lulus / Bermasalah</span>
                        <i class="bi bi-x-circle fs-5 text-danger"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-dark"><?= $bi_tidak; ?></h2>
                    <span class="badge rounded-pill" style="font-size: 0.75rem; background: #fee2e2; color: #b91c1c;">Perlu Tindak Lanjut</span>
                </div>
            </div>
        </div>

        <!-- TABEL BI CHECKING TERBARU -->
        <?php if (!empty($bi_recent_rows)): ?>
        <div class="content-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Riwayat BI Checking Terbaru</h6>
                <a href="bi_checking.php" class="small text-success text-decoration-none fw-medium">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px">#</th>
                            <th>Tanggal</th>
                            <th>Konsumen</th>
                            <th>Lembaga</th>
                            <th>No. Pengajuan</th>
                            <th>Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bi_recent_rows as $idx => $br):
                            $badgeHasil = 'bg-secondary text-white';
                            if ($br['hasil_bi'] === 'Lulus') $badgeHasil = 'bg-success text-white';
                            elseif ($br['hasil_bi'] === 'Dalam Proses') $badgeHasil = 'bg-warning text-dark';
                            elseif ($br['hasil_bi'] === 'Dipertimbangkan') $badgeHasil = 'bg-info text-dark';
                            elseif ($br['hasil_bi'] === 'Tidak Lulus') $badgeHasil = 'bg-danger text-white';
                        ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><span class="fw-semibold"><?= date('d M Y', strtotime($br['tgl_cek'])) ?></span></td>
                            <td><?= htmlspecialchars($br['name_lengkap'] ?? '-') ?></td>
                            <td><span class="text-muted"><?= htmlspecialchars($br['lembaga'] ?? '-') ?></span></td>
                            <td><span class="text-monospace small"><?= htmlspecialchars($br['no_pengajuan'] ?? '-') ?></span></td>
                            <td><span class="badge <?= $badgeHasil ?> rounded-pill px-2"><?= $br['hasil_bi'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- LAPORAN HARIAN HARI INI -->
        <?php if ($lh_count > 0): ?>
        <div class="content-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0"><i class="bi bi-journal-text text-success me-2"></i>Laporan Harian Hari Ini</h5>
                    <small class="text-muted"><?= $lh_count ?> kegiatan tercatat &bull; <?= date('d M Y', strtotime($today)) ?></small>
                </div>
                <a href="laporan_harian.php?tanggal=<?= $today ?>" class="small text-success text-decoration-none fw-medium">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px">#</th>
                            <th>Kategori</th>
                            <th>Nama</th>
                            <th>Kegiatan</th>
                            <th>Jam</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lh_rows as $idx => $lr):
                            $badgeSt = 'bg-success';
                            if ($lr['status'] === 'Sedang Berlangsung') $badgeSt = 'bg-primary';
                            elseif ($lr['status'] === 'Terhambat') $badgeSt = 'bg-danger';
                            elseif ($lr['status'] === 'Belum Dikerjakan') $badgeSt = 'bg-warning text-dark';

                            $badgeKat = $lr['kategori'] === 'Admin' ? 'bg-purple' : 'bg-pink';
                            $jam = '';
                            if ($lr['jam_mulai'] && $lr['jam_selesai']) {
                                $jam = date('H:i', strtotime($lr['jam_mulai'])) . ' - ' . date('H:i', strtotime($lr['jam_selesai']));
                            } elseif ($lr['jam_mulai']) {
                                $jam = date('H:i', strtotime($lr['jam_mulai'])) . ' - ...';
                            }
                        ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><span class="badge rounded-pill px-2 <?= $badgeKat ?>"><?= $lr['kategori'] ?></span></td>
                            <td class="fw-semibold"><?= htmlspecialchars($lr['nama_lengkap']) ?></td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width:280px;"><?= htmlspecialchars($lr['judul_kegiatan']) ?></div>
                            </td>
                            <td><span class="text-muted small"><?= $jam ?: '-' ?></span></td>
                            <td><span class="badge <?= $badgeSt ?> rounded-pill px-2"><?= $lr['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="content-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text text-muted fs-5"></i>
                    <div>
                        <h6 class="fw-bold mb-0">Laporan Harian Hari Ini</h6>
                        <small class="text-muted">Belum ada kegiatan tercatat hari ini.</small>
                    </div>
                </div>
                <a href="laporan_harian.php" class="small text-success text-decoration-none fw-medium">Buat Laporan <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <?php endif; ?>

        <!-- NOTIFIKASI DEADLINE PEMBERKASAN -->
        <?php if (count($deadline_list) > 0): ?>
        <div class="content-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0"><i class="bi bi-alarm-fill text-warning me-2"></i>Deadline Pemberkasan Mendekat</h5>
                    <small class="text-muted"><?= count($deadline_list) ?> konsumen dengan deadline berkas dalam 7 hari ke depan & berkas belum lengkap</small>
                </div>
                <a href="pemberkasan.php" class="small text-success text-decoration-none fw-medium">Kelola Berkas <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="row g-3">
                <?php foreach ($deadline_list as $dl): ?>
                <?php 
                    $dl_sisa = $dl['sisa_hari'];
                    if ($dl_sisa < 0) { $badge = 'bg-danger'; $label = 'Lewat '.abs($dl_sisa).' hari'; }
                    elseif ($dl_sisa <= 3) { $badge = 'bg-warning text-dark'; $label = 'H-'.$dl_sisa; }
                    else { $badge = 'bg-secondary'; $label = 'H-'.$dl_sisa; }
                ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center border rounded-3 p-3">
                        <div class="me-2">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($dl['name_lengkap'] ?? '-') ?></div>
                            <small class="text-muted">Blok <?= htmlspecialchars($dl['no_blok'] ?? '-') ?> - <?= htmlspecialchars($dl['cluster_name'] ?? '-') ?> | <?= htmlspecialchars($dl['pekerjaan'] ?? '-') ?></small>
                            <div class="mt-1">
                                <div class="progress" style="height: 5px; width: 140px;">
                                    <div class="progress-bar <?= ($dl['persen'] == 100) ? 'bg-success' : 'bg-warning' ?>" style="width: <?= $dl['persen'] ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge <?= $badge ?> rounded-pill px-3 py-2 mb-1"><i class="bi bi-clock me-1"></i><?= $label ?></span>
                            <div class="small text-muted">Deadline <?= date('d M Y', strtotime($dl['deadline_berkas'])) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- TABLE SECTION -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0">Database Transaksi Utama</h5>
                    <small class="text-muted">Menampilkan 10 transaksi terbaru</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" style="font-size: 0.9rem;">
                    <thead>
                        <tr class="text-muted border-bottom">
                            <th class="fw-semibold">NO</th>
                            <th class="fw-semibold">TGL BOOKING</th>
                            <th class="fw-semibold">KONSUMEN</th>
                            <th class="fw-semibold">WHATSAPP</th>
                            <th class="fw-semibold">CLUSTER / BLOK</th>
                            <th class="fw-semibold">SKEMA BAYAR</th>
                            <th class="fw-semibold">STATUS KONSUMEN</th>
                            <th class="fw-semibold">TGL AKAD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) { 
                        ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td><?= (!empty($row['tgl_booking']) && $row['tgl_booking'] != '0000-00-00') ? date('d M Y', strtotime($row['tgl_booking'])) : '-'; ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['name_lengkap']); ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['pekerjaan'] ?? '-') ?></small>
                            </td>
                            <td>
                                <?php if(!empty($row['no_whatsapp'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['no_whatsapp']); ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3">
                                        <i class="bi bi-whatsapp text-success me-1"></i> <?= htmlspecialchars($row['no_whatsapp']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($row['cluster_name'] ?? 'Shankara Residence'); ?></div>
                                <span class="badge bg-dark text-white" style="font-size: 0.75rem;">Blok <?= htmlspecialchars($row['no_blok']); ?></span>
                                <small class="text-muted">(Tipe <?= htmlspecialchars($row['tipe_rumah']); ?>)</small>
                            </td>
                            <td><span class="fw-semibold text-secondary"><?= htmlspecialchars($row['skema_bayar']); ?></span></td>
                            <td>
                                <?php 
                                    $status = strtoupper($row['status_konsumen']);
                                    $badge_class = 'badge-soft-warning';
                                    if ($status == 'ACC - AKAD' || $status == 'AKAD') {
                                        $badge_class = 'badge-soft-success';
                                    } elseif ($status == 'BATAL') {
                                        $badge_class = 'bg-danger text-white';
                                    }
                                ?>
                                <span class="badge <?= $badge_class; ?> rounded-pill px-3 py-2">
                                    <?= htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td>
                                <?= (!empty($row['tanggal_akad']) && $row['tanggal_akad'] != '0000-00-00') ? date('d M Y', strtotime($row['tanggal_akad'])) : '<span class="text-muted">-</span>'; ?>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center text-muted py-4'>Data transaksi tidak ditemukan.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal Pop-Up Transaksi (Shankara Green Theme) -->
    <div class="modal fade" id="modalTransaksi" tabindex="-1" aria-labelledby="modalTransaksiLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                
                <!-- Header Modal -->
                <div class="modal-header text-white" style="background-color: var(--primary-blue);">
                    <h5 class="modal-title fw-bold" id="modalTransaksiLabel">
                        <i class="bi bi-cart-plus me-2"></i>Tambah Transaksi Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="includes/proses_transaksi.php" method="POST">
                    <?= csrf_field() ?>
                    <div class="modal-body p-4" style="background-color: #f8f9fa;">
                        <div class="row g-3">
                            
                            <!-- Tanggal Booking -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">TANGGAL BOOKING</label>
                                <input type="date" name="tgl_booking" class="form-control border-0 shadow-sm" style="border-radius: 8px;" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <!-- Pilih Konsumen -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">PILIH KONSUMEN</label>
                                <select name="id_konsumen" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                    <option value="">-- Pilih Konsumen --</option>
                                    <?php
                                    $konsumen = mysqli_query($koneksi, "SELECT * FROM data_konsumen ORDER BY name_lengkap ASC");
                                    while ($k = mysqli_fetch_assoc($konsumen)) {
                                        echo "<option value='".$k['id_konsumen']."'>".htmlspecialchars($k['name_lengkap'])." - (".htmlspecialchars($k['pekerjaan']).")</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Pilih Unit Rumah -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">PILIH RUMAH / UNIT</label>
                                <select name="id_rumah" id="select_rumah" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required onchange="cekStatusUnit(this)">
                                    <option value="" data-status="" data-pemesan="">-- Pilih Unit Rumah --</option>
                                    <?php
                                    // Query mengambil data rumah + pemesan (jika terbooked/terjual)
                                    $query_rumah = "SELECT r.*, c.cluster_name, c.cluster_status, k.name_lengkap AS pemesan 
                                                    FROM data_rumah r 
                                                    LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster 
                                                    LEFT JOIN data_transaksi t ON r.id_rumah = t.id_rumah AND t.status_konsumen IN ('PROSES', 'ACC - AKAD')
                                                    LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                                    ORDER BY c.cluster_name, r.no_blok ASC";
                                    
                                    $rumah = mysqli_query($koneksi, $query_rumah);
                                    while ($r = mysqli_fetch_assoc($rumah)) {
                                        $status = strtolower($r['status_unit']);
                                        $pemesan = !empty($r['pemesan']) ? $r['pemesan'] : 'Konsumen Lain';
                                        $cluster = !empty($r['cluster_name']) ? $r['cluster_name'] : 'Shankara Residence';
                                        $isHold = cluster_is_hold($r['cluster_status'] ?? '');
                                        $labelHold = $isHold ? ' - [HOLD]' : '';
                                        echo "<option value='".$r['id_rumah']."' data-status='".$status."' data-pemesan='".htmlspecialchars($pemesan, ENT_QUOTES)."' data-hold='".($isHold ? '1' : '0')."' data-cluster='".htmlspecialchars($cluster, ENT_QUOTES)."'>";
                                        echo $cluster." - Blok ".htmlspecialchars($r['no_blok'])." (Tipe ".htmlspecialchars($r['tipe_rumah']).") - Status: ".strtoupper($r['status_unit']).$labelHold;
                                        echo "</option>";
                                    }
                                    ?>
                                </select>
                                
                                <!-- Area Info / Warning Alert (Dinamis via JS) -->
                                <div id="statusAlert" class="alert border-0 mt-2 shadow-sm d-none" role="alert" style="border-radius: 8px;">
                                    <i id="alertIcon" class="bi me-2"></i>
                                    <span id="alertMessage"></span>
                                </div>
                            </div>

                            <!-- Skema Bayar -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">SKEMA BAYAR</label>
                                <select name="skema_bayar" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                    <option value="KPR Subsidi">KPR Subsidi</option>
                                    <option value="KPR Komersil">KPR Komersil</option>
                                    <option value="Cash Tempo">Cash Tempo</option>
                                    <option value="Cash Bertahap">Cash Bertahap</option>
                                    <option value="Cash Keras">Cash Keras</option>
                                </select>
                            </div>

                            <!-- Status Konsumen -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">STATUS KONSUMEN</label>
                                <select name="status_konsumen" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                    <option value="PROSES">PROSES</option>
                                    <option value="ACC - AKAD">ACC - AKAD</option>
                                    <option value="BATAL">BATAL</option>
                                </select>
                            </div>

                            <!-- Tanggal Akad -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">TANGGAL AKAD (OPSIONAL)</label>
                                <input type="date" name="tanggal_akad" class="form-control border-0 shadow-sm" style="border-radius: 8px;">
                            </div>

                        </div>
                    </div>
                    
                    <!-- Footer Modal -->
                    <div class="modal-footer bg-white border-0 px-4 py-3">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                        <button type="submit" name="simpan" class="btn text-white px-4" style="background-color: #2d6a4f; border-radius: 8px;">
                            <i class="bi bi-check-lg me-1"></i> Simpan Transaksi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include "includes/modal_hold.php"; ?>
    <script>
        function cekStatusUnit(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const status = selectedOption.getAttribute('data-status');
            const pemesan = selectedOption.getAttribute('data-pemesan');
            const isHold = selectedOption.getAttribute('data-hold') === '1';
            const clusterName = selectedOption.getAttribute('data-cluster') || '';

            const alertBox = document.getElementById('statusAlert');
            const alertIcon = document.getElementById('alertIcon');
            const alertMessage = document.getElementById('alertMessage');

            alertBox.className = 'alert border-0 mt-2 shadow-sm';

            if (isHold) {
                alertBox.classList.add('alert-danger', 'text-danger');
                alertBox.style.backgroundColor = '#f8d7da';
                alertIcon.className = 'bi bi-ban text-danger me-2';
                alertMessage.innerHTML = '<strong>Cluster Dihold:</strong> Unit ini berada di Cluster <strong>' + clusterName + '</strong> yang sedang di <span class="badge bg-danger">HOLD</span> oleh sistem. Booking & pembayaran tidak dapat dilakukan.';
            } else if (status === 'terbooking') {
                alertBox.classList.add('alert-warning', 'text-dark');
                alertBox.style.backgroundColor = '#fff3cd';
                alertIcon.className = 'bi bi-exclamation-triangle-fill text-warning me-2';
                alertMessage.innerHTML = '<strong>Peringatan:</strong> Unit ini sudah <span class="badge bg-warning text-dark">TERBOOKING</span> oleh <strong>' + pemesan + '</strong>.';
            } else if (status === 'tersedia') {
                alertBox.classList.add('alert-success', 'text-success-emphasis');
                alertBox.style.backgroundColor = '#d1e7dd';
                alertIcon.className = 'bi bi-check-circle-fill text-success me-2';
                alertMessage.innerHTML = '<strong>Informasi:</strong> Unit ini <span class="badge bg-success">TERSEDIA</span> dan siap untuk dibooking!';
            } else if (status === 'terjual') {
                alertBox.classList.add('alert-danger', 'text-danger');
                alertBox.style.backgroundColor = '#f8d7da';
                alertIcon.className = 'bi bi-x-circle-fill text-danger me-2';
                alertMessage.innerHTML = '<strong>Peringatan Khusus:</strong> Unit ini sudah <span class="badge bg-danger">TERJUAL</span> (Akad) atas nama <strong>' + pemesan + '</strong>.';
            } else {
                alertBox.classList.add('d-none');
            }
        }
    </script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
    (function(){
        var days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        var months=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        function pad(n){return n<10?'0'+n:n;}
        function tick(){
            var now=new Date();
            var h=now.getHours(),m=now.getMinutes(),s=now.getSeconds();
            var t=pad(h)+':'+pad(m);
            var d=days[now.getDay()]+', '+now.getDate()+' '+months[now.getMonth()]+' '+now.getFullYear();
            var el;
            el=document.getElementById('dcTime'); if(el) el.textContent=t;
            el=document.getElementById('dcDate'); if(el) el.textContent=d;
        }
        tick();
        setInterval(tick,1000);
    })();
    </script>
</body>
</html>
