<?php

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";

include "includes/koneksi.php";

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// 1. REKAP RINGKASAN UTAMA (1 query instead of 5)
$stat_all = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT 
    COUNT(*) as total,
    SUM(UPPER(status_unit) IN ('TERJUAL','ACC-AKAD','AKAD','BAST')) as terjual,
    SUM(UPPER(status_unit) IN ('BOOKING','TERBOOKING','PROSES')) as terbooking,
    SUM(UPPER(status_unit)='TERSEDIA' OR status_unit IS NULL OR status_unit='') as tersedia
FROM data_rumah"));
$tot_unit      = intval($stat_all['total']);
$tot_terjual   = intval($stat_all['terjual']);
$tot_terbooking= intval($stat_all['terbooking']);
$tot_tersedia  = intval($stat_all['tersedia']);
$tot_kas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total FROM data_pembayaran"))['total'] ?? 0;

// 2. DATA UNTUK CHART STATUS UNIT (DOUGHNUT)
$chart_status_labels = ['Tersedia', 'Terbooking', 'Terjual'];
$chart_status_data   = [$tot_tersedia, $tot_terbooking, $tot_terjual];

// 3. DATA UNTUK CHART SKEMA PEMBAYARAN (DOUGHNUT)
$q_skema = mysqli_query($koneksi, "SELECT skema_bayar, COUNT(*) as jumlah FROM data_transaksi GROUP BY skema_bayar");
$skema_labels = [];
$skema_data   = [];
if ($q_skema) {
    while ($row = mysqli_fetch_assoc($q_skema)) {
        $skema_labels[] = !empty($row['skema_bayar']) ? $row['skema_bayar'] : 'Belum Ditentukan';
        $skema_data[]   = (int)$row['jumlah'];
    }
}

// 4. DATA UNTUK CHART PENJUALAN PER CLUSTER (BAR CHART) & TABEL REKAP
// Single grouped query instead of 5 queries Ã— N clusters
$stats_per_cluster = [];
$q_cstats = mysqli_query($koneksi, "SELECT 
    r.id_cluster,
    COUNT(*) as total,
    SUM(UPPER(r.status_unit) IN ('TERJUAL','ACC-AKAD','AKAD','BAST')) as terjual,
    SUM(UPPER(r.status_unit) IN ('BOOKING','TERBOOKING','PROSES')) as terbooking,
    SUM(UPPER(r.status_unit)='TERSEDIA' OR r.status_unit IS NULL OR r.status_unit='') as tersedia
FROM data_rumah r
GROUP BY r.id_cluster");
if ($q_cstats) {
    while ($row = mysqli_fetch_assoc($q_cstats)) {
        $stats_per_cluster[intval($row['id_cluster'])] = $row;
    }
}

// Dana masuk per cluster (1 grouped query instead of N)
$dana_per_cluster = [];
$q_dana = mysqli_query($koneksi, "SELECT r.id_cluster, SUM(p.jumlah_bayar) as total 
    FROM data_pembayaran p 
    JOIN data_transaksi t ON p.id_transaksi = t.id_transaksi 
    JOIN data_rumah r ON t.id_rumah = r.id_rumah 
    GROUP BY r.id_cluster");
if ($q_dana) {
    while ($row = mysqli_fetch_assoc($q_dana)) {
        $dana_per_cluster[intval($row['id_cluster'])] = floatval($row['total']);
    }
}

$q_cluster = mysqli_query($koneksi, "SELECT * FROM data_cluster ORDER BY 1 ASC");
$cluster_names      = [];
$cluster_tersedia   = [];
$cluster_terbooking = [];
$cluster_terjual    = [];
$rekap_cluster_table = [];

if ($q_cluster) {
    while ($c = mysqli_fetch_assoc($q_cluster)) {
        $id_c   = $c['id'] ?? $c['id_cluster'] ?? reset($c);
        $nama_c = $c['nama_cluster'] ?? 'Cluster ' . $id_c;

        $cs = $stats_per_cluster[$id_c] ?? ['total'=>0,'terjual'=>0,'terbooking'=>0,'tersedia'=>0];
        $c_dana = $dana_per_cluster[$id_c] ?? 0;

        $cluster_names[]      = $nama_c;
        $cluster_tersedia[]   = intval($cs['tersedia']);
        $cluster_terbooking[] = intval($cs['terbooking']);
        $cluster_terjual[]    = intval($cs['terjual']);

        $rekap_cluster_table[] = [
            'nama'     => $nama_c,
            'total'    => intval($cs['total']),
            'tersedia' => intval($cs['tersedia']),
            'booking'  => intval($cs['terbooking']),
            'terjual'  => intval($cs['terjual']),
            'dana'     => $c_dana
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Laporan Penjualan - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== STYLE CETAK / PDF ===== */
        @media print {
            body {
                background: #ffffff !important;
            }
            .sidebar,
            .d-print-none,
            .app-layout > .modal {
                display: none !important;
            }
            .main-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .content-card,
            .stat-card {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                break-inside: avoid;
            }
            .report-header {
                padding-top: 8px;
            }
            @page {
                size: A4;
                margin: 14mm;
            }
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <!-- KONTEN UTAMA -->
    <main class="main-wrapper">
        <div class="mb-4 d-flex justify-content-between align-items-center d-print-none">
            <div>
                <h4 class="fw-bold mb-1">Laporan Penjualan & Performa Proyek</h4>
                <p class="text-muted small mb-0">Ringkasan total transaksi, status unit per cluster, dan skema pembiayaan.</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="cetakPDF()" class="btn btn-danger rounded-pill px-4">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Unduh PDF
                </button>
                <button onclick="window.print()" class="btn text-white rounded-pill px-4" style="background-color: var(--primary-blue);">
                    <i class="bi bi-printer me-1"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <!-- REPORT AREA: konten yang dicetak / di-export ke PDF -->
        <div id="reportArea">
        <!-- HEADER DOKUMEN LAPORAN -->
        <div class="report-header text-center mb-4">
            <h2 class="fw-bold mb-1" style="color: var(--primary-blue); letter-spacing: 1px;">SHANKARA RESIDENCE</h2>
            <h5 class="fw-semibold mb-1 text-uppercase">Laporan Penjualan & Performa Proyek</h5>
            <p class="small text-muted mb-0">Dibuat: <?= date('d M Y, H:i') ?> &nbsp;|&nbsp; Sumber: Sistem Terintegrasi Shankara Dev</p>
            <hr style="border-top: 2px solid var(--primary-blue); opacity: 1;">
        </div>

        <!-- REKAP CARD UTAMA -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <span class="text-muted small d-block mb-1">Total Unit Proyek</span>
                    <h3 class="fw-bold text-dark mb-0"><?= $tot_unit ?> <span class="fs-6 fw-normal text-muted">Unit</span></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <span class="text-muted small d-block mb-1">Tersedia</span>
                    <h3 class="fw-bold text-primary mb-0"><?= $tot_tersedia ?> <span class="fs-6 fw-normal text-muted">Unit</span></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <span class="text-muted small d-block mb-1">Terbooking</span>
                    <h3 class="fw-bold text-warning mb-0"><?= $tot_terbooking ?> <span class="fs-6 fw-normal text-muted">Unit</span></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <span class="text-muted small d-block mb-1">Unit Terjual / BAST</span>
                    <h3 class="fw-bold text-danger mb-0"><?= $tot_terjual ?> <span class="fs-6 fw-normal text-muted">Unit</span></h3>
                </div>
            </div>
        </div>

        <!-- DANA MASUK BANNER -->
        <div class="p-3 bg-white border rounded-4 mb-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-light-blue text-success rounded-circle">
                    <i class="bi bi-wallet2 fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small d-block">Akumulasi Total Uang Masuk (Kwitansi Cash/DP/KPR)</span>
                    <h4 class="fw-bold text-success mb-0">Rp <?= number_format($tot_kas, 0, ',', '.') ?></h4>
                </div>
            </div>
        </div>

        <!-- SECTION GRAFIK -->
        <div class="row g-4 mb-4">
            <!-- BAR CHART PER CLUSTER -->
            <div class="col-md-8">
                <div class="content-card">
                    <h6 class="fw-bold mb-3" style="color: var(--primary-blue);">Penjualan & Status Unit Per Cluster / Proyek</h6>
                    <div style="height: 320px;">
                        <canvas id="barClusterChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- DOUGHNUT STATUS UNIT -->
            <div class="col-md-4">
                <div class="content-card">
                    <h6 class="fw-bold mb-3" style="color: var(--primary-blue);">Persentase Status Unit</h6>
                    <div style="height: 280px;" class="d-flex align-items-center justify-content-center">
                        <canvas id="doughnutStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- DOUGHNUT SKEMA PEMBAYARAN -->
            <div class="col-md-5">
                <div class="content-card">
                    <h6 class="fw-bold mb-3" style="color: var(--primary-blue);">Distribusi Skema Pembayaran</h6>
                    <div style="height: 280px;" class="d-flex align-items-center justify-content-center">
                        <canvas id="doughnutSkemaChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TABEL DETAIL REKAP CLUSTER -->
            <div class="col-md-7">
                <div class="content-card">
                    <h6 class="fw-bold mb-3" style="color: var(--primary-blue);">Detail Performa Per Cluster</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Cluster</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Tersedia</th>
                                    <th class="text-center">Booking</th>
                                    <th class="text-center">Terjual</th>
                                    <th class="text-end">Dana Masuk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekap_cluster_table as $rc): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($rc['nama']) ?></td>
                                    <td class="text-center"><span class="badge bg-secondary rounded-pill"><?= $rc['total'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-primary rounded-pill"><?= $rc['tersedia'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning text-dark rounded-pill"><?= $rc['booking'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-danger rounded-pill"><?= $rc['terjual'] ?></span></td>
                                    <td class="text-end fw-semibold text-success">Rp <?= number_format($rc['dana'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        </div><!-- /reportArea -->

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script>
// EXPORT PDF DARI AREA LAPORAN (html2canvas + jsPDF)
function cetakPDF() {
    const area = document.getElementById('reportArea');
    if (!area) return;

    const btn = event ? event.currentTarget : null;
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Membuat PDF...'; }

    html2canvas(area, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        windowWidth: area.scrollWidth + 60
    }).then((canvas) => {
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const margin = 8;
        const imgW = pageW - (margin * 2);
        const imgH = (canvas.height * imgW) / canvas.width;

        let heightLeft = imgH;
        let position = margin;

        pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH);
        heightLeft -= (pageH - margin * 2);

        while (heightLeft > 0) {
            position = position - (pageH - margin * 2);
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH);
            heightLeft -= (pageH - margin * 2);
        }

        const namaFile = 'Laporan_Penjualan_Shankara_' + new Date().toISOString().slice(0, 10) + '.pdf';
        pdf.save(namaFile);

        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i> Unduh PDF'; }
    }).catch(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i> Unduh PDF'; }
        alert('Gagal membuat PDF. Coba lagi.');
    });
}
</script>

<script>
// 1. BAR CHART: UNIT PER CLUSTER
const ctxBar = document.getElementById('barClusterChart').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: <?= json_encode($cluster_names) ?>,
        datasets: [
            {
                label: 'Tersedia',
                data: <?= json_encode($cluster_tersedia) ?>,
                backgroundColor: '#0d6efd'
            },
            {
                label: 'Terbooking',
                data: <?= json_encode($cluster_terbooking) ?>,
                backgroundColor: '#ffc107'
            },
            {
                label: 'Terjual',
                data: <?= json_encode($cluster_terjual) ?>,
                backgroundColor: '#dc3545'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// 2. DOUGHNUT CHART: STATUS UNIT
const ctxStatus = document.getElementById('doughnutStatusChart').getContext('2d');
new Chart(ctxStatus, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chart_status_labels) ?>,
        datasets: [{
            data: <?= json_encode($chart_status_data) ?>,
            backgroundColor: ['#0d6efd', '#ffc107', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// 3. DOUGHNUT CHART: SKEMA PEMBAYARAN
const ctxSkema = document.getElementById('doughnutSkemaChart').getContext('2d');
new Chart(ctxSkema, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($skema_labels) ?>,
        datasets: [{
            data: <?= json_encode($skema_data) ?>,
            backgroundColor: ['#2563eb', '#0dcaf0', '#ffc107', '#6f42c1', '#fd7e14']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
