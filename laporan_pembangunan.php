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

// Filter dari URL (GET) untuk tampilan layar & print
$filterStatus = isset($_GET['filter']) ? mysqli_real_escape_string($koneksi, $_GET['filter']) : '';
$filterCluster = isset($_GET['cluster']) ? mysqli_real_escape_string($koneksi, $_GET['cluster']) : '';

// Query: unit + cluster + pembangunan + konsumen transaksi terbaru per unit
$sql = "SELECT r.id_rumah, r.no_blok, r.tipe_rumah, c.cluster_name,
               p.persentase_progres, p.status_pembangunan, p.tgl_mulai, p.tgl_target_selesai, p.catatan_lapangan,
               k.name_lengkap, k.no_whatsapp
        FROM data_rumah r
        LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
        LEFT JOIN data_pembangunan p ON r.id_rumah = p.id_rumah
        LEFT JOIN (
            SELECT t1.id_rumah, t1.id_konsumen
            FROM data_transaksi t1
            INNER JOIN (
                SELECT id_rumah, MAX(id_transaksi) AS max_id
                FROM data_transaksi
                WHERE id_rumah IS NOT NULL AND id_konsumen IS NOT NULL
                GROUP BY id_rumah
            ) t2 ON t1.id_rumah = t2.id_rumah AND t1.id_transaksi = t2.max_id
        ) tx ON tx.id_rumah = r.id_rumah
        LEFT JOIN data_konsumen k ON k.id_konsumen = tx.id_konsumen
        WHERE 1=1";

if ($filterStatus !== '') {
    $sql .= " AND (p.status_pembangunan IS NULL OR p.status_pembangunan = '$filterStatus')";
}
if ($filterCluster !== '') {
    $sql .= " AND c.cluster_name = '$filterCluster'";
}
$sql .= " ORDER BY c.cluster_name ASC, r.no_blok ASC";

$q = mysqli_query($koneksi, $sql);

// Daftar nama konsumen untuk filter multi pilih
$q_nama = mysqli_query($koneksi, "SELECT DISTINCT k.name_lengkap
                                  FROM data_rumah r
                                  LEFT JOIN (
                                      SELECT t1.id_rumah, t1.id_konsumen
                                      FROM data_transaksi t1
                                      INNER JOIN (
                                          SELECT id_rumah, MAX(id_transaksi) AS max_id
                                          FROM data_transaksi
                                          WHERE id_rumah IS NOT NULL AND id_konsumen IS NOT NULL
                                          GROUP BY id_rumah
                                      ) t2 ON t1.id_rumah = t2.id_rumah AND t1.id_transaksi = t2.max_id
                                  ) tx ON tx.id_rumah = r.id_rumah
                                  LEFT JOIN data_konsumen k ON k.id_konsumen = tx.id_konsumen
                                  WHERE k.name_lengkap IS NOT NULL AND TRIM(k.name_lengkap) <> ''
                                  ORDER BY k.name_lengkap ASC");
$listNama = [];
if ($q_nama) {
    while ($n = mysqli_fetch_assoc($q_nama)) {
        $listNama[] = trim($n['name_lengkap']);
    }
}

// Statistik ringkas (mengikuti filter cluster)
$statSql = "SELECT
    COUNT(*) AS total_unit,
    SUM(CASE WHEN p.status_pembangunan='Selesai' THEN 1 ELSE 0 END) AS selesai,
    SUM(CASE WHEN COALESCE(p.status_pembangunan,'Persiapan') IN ('Persiapan','Pondasi','Struktur','Finishing') THEN 1 ELSE 0 END) AS berjalan
    FROM data_rumah r LEFT JOIN data_pembangunan p ON r.id_rumah = p.id_rumah";
if ($filterCluster !== '') {
    $statSql .= " JOIN data_cluster c2 ON r.id_cluster = c2.id_cluster AND c2.cluster_name = '$filterCluster'";
}
$stat = mysqli_query($koneksi, $statSql);
$st = mysqli_fetch_assoc($stat);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Laporan Status Pembangunan - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .filter-input {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 0.875rem;
            padding: 8px 16px;
            transition: all 0.2s ease;
        }

        .filter-input:focus {
            background-color: #ffffff;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        /* HEADER LAPORAN KHUSUS CETAK PDF */
        .print-header {
            display: none;
        }

        /* ==========================================
           STYLING MEDIA PRINT (CETAK KE PDF)
        ========================================== */
        @media print {
            @page {
                size: A4 portrait;
                margin: 1.2cm;
            }

            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-size: 10pt;
            }

            /* Sembunyikan Komponen Navigasi & Input Form */
            .sidebar,
            .no-print,
            .btn,
            .modal,
            .filter-section {
                display: none !important;
            }

            .main-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .content-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            /* Tampilkan Header Resmi Laporan */
            .print-header {
                display: block !important;
                border-bottom: 2px solid #1e3a8a;
                padding-bottom: 12px;
                margin-bottom: 16px;
            }

            .print-header h3 {
                margin: 0;
                font-weight: 700;
                color: #1e3a8a;
                text-transform: uppercase;
            }

            .print-header p {
                margin: 0;
                font-size: 9pt;
                color: #555;
            }

            /* Format Tabel untuk Cetak */
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .table th, .table td {
                border: 1px solid #cbd5e1 !important;
                padding: 6px 8px !important;
                font-size: 8.5pt !important;
            }

            .table th {
                background-color: #f1f5f9 !important;
                color: #000 !important;
                font-weight: bold;
            }

            .progress {
                border: 1px solid #ccc !important;
                background-color: #e2e8f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .progress-bar {
                background-color: #2563eb !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .badge {
                border: 1px solid #000 !important;
                color: #000 !important;
                background: transparent !important;
            }
        }

        /* ==========================================
           RESPONSIVE UNTUK LAYAR KECIL / HP
        ========================================== */
        @media (max-width: 767.98px) {
            .print-header {
                flex-direction: column;
                gap: 6px;
                text-align: left !important;
            }
            .print-header .text-end {
                text-align: left !important;
            }

            /* Header halaman: judul & tombol tidak mepet */
            .main-wrapper > .no-print:first-of-type {
                align-items: flex-start !important;
                gap: 10px;
            }
            .main-wrapper > .no-print:first-of-type .btn {
                white-space: nowrap;
            }

            /* Kartu statistik rapi */
            .content-card .card .card-body {
                padding: 14px !important;
            }

            /* Filter tombol Reset tidak lebih besar dari input */
            .filter-section .col-md-2 {
                margin-top: 2px;
            }
            .filter-section .btn {
                padding-top: 8px !important;
                padding-bottom: 8px !important;
            }
            /* Mode kartu tabel ditangani generik di app.css (.table-card-mode) */
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
        <!-- HEADER KOP LAPORAN UNTUK EXPORT PDF -->
        <div class="print-header d-flex justify-content-between align-items-start">
            <div>
                <h3>SHANKARA RESIDENCE</h3>
                <p>PT. GOLDEN SHANKARA INDONESIA</p>
                <p>Laporan Resmi Status Pembangunan Unit Rumah</p>
            </div>
            <div class="text-end">
                <p>Dicetak pada: <strong id="tglCetak">-</strong></p>
                <p>Filter status: <strong><?= $filterStatus!=='' ? htmlspecialchars($filterStatus) : 'Semua' ?></strong></p>
                <p>Cluster: <strong><?= $filterCluster!=='' ? htmlspecialchars($filterCluster) : 'Semua' ?></strong></p>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1">Laporan Status Pembangunan Unit</h4>
                <p class="text-muted small mb-0">Status pembangunan unit rumah beserta nama konsumen dan blok rumah.</p>
            </div>
            <button type="button" onclick="window.print()" class="btn btn-outline-danger rounded-pill fw-semibold px-3">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
            </button>
        </div>

        <!-- STATISTIK RINGKAS -->
        <div class="content-card">
            <div class="row g-3 mb-4 no-print">
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle p-3 text-white" style="background-color:#1e3a8a;"><i class="bi bi-houses fs-4"></i></div>
                            <div>
                                <div class="small text-muted">Total Unit</div>
                                <div class="fs-4 fw-bold"><?= (int)$st['total_unit'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle p-3 text-white" style="background-color:#eab308;"><i class="bi bi-hammer fs-4"></i></div>
                            <div>
                                <div class="small text-muted">Sedang Dibangun</div>
                                <div class="fs-4 fw-bold"><?= (int)$st['berjalan'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle p-3 text-white" style="background-color:#2563eb;"><i class="bi bi-check-circle fs-4"></i></div>
                            <div>
                                <div class="small text-muted">Selesai</div>
                                <div class="fs-4 fw-bold"><?= (int)$st['selesai'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION FILTER -->
            <div class="row g-2 mb-3 align-items-center filter-section no-print">
                <div class="col-md-3">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute text-muted" style="left: 16px; top: 50%; transform: translateY(-50%);"></i>
                        <input type="text" id="searchInput" class="form-control filter-input ps-5 shadow-none" placeholder="Cari Blok...">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dropdown w-100" id="namaFilterWrap">
                        <button type="button" class="btn btn-light border w-100 d-flex align-items-center justify-content-between px-3 py-2 rounded-pill shadow-none" id="btnFilterNama" style="background-color:#f8fafc; border-color:#e2e8f0; font-size:0.875rem;">
                            <span class="d-flex align-items-center gap-2">
                                <i class="bi bi-person-check text-muted"></i>
                                <span class="text-truncate" style="max-width:150px;">Filter Nama</span>
                            </span>
                            <span class="badge rounded-pill text-white d-none" id="namaFilterCount" style="background-color:var(--primary-blue);"></span>
                            <i class="bi bi-chevron-down text-muted small"></i>
                        </button>
                        <div class="dropdown-menu shadow-sm border-0 rounded-3 p-2 mt-2 w-100" id="namaFilterMenu" style="max-height:280px; overflow:auto;">
                            <div class="form-check ps-0 mb-2 pb-2 border-bottom">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100 rounded-pill" id="btnNamaSemua">Pilih Semua</button>
                            </div>
                            <div id="namaFilterList"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="filterCluster" class="form-select filter-input shadow-none">
                        <option value="">Semua Cluster</option>
                        <?php
                        $q_cluster = mysqli_query($koneksi, "SELECT DISTINCT cluster_name FROM data_cluster ORDER BY cluster_name ASC");
                        if ($q_cluster) {
                            while ($c = mysqli_fetch_assoc($q_cluster)) {
                                $sel = ($filterCluster===$c['cluster_name']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($c['cluster_name']) . "' $sel>" . htmlspecialchars($c['cluster_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterStatus" class="form-select filter-input shadow-none">
                        <option value="">Semua Status</option>
                        <?php foreach (['Persiapan','Pondasi','Struktur','Finishing','Selesai'] as $s) { ?>
                            <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= $s ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button id="btnReset" class="btn btn-outline-secondary rounded-pill w-100 py-2 btn-sm fw-medium">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- TABEL LAPORAN -->
            <div class="d-flex justify-content-between align-items-center mb-2 px-1 no-print">
                <span class="small text-muted">Menampilkan <strong id="rowInfo">0</strong> dari <strong id="rowTotal">0</strong> unit</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableLaporan">
                    <thead>
                        <tr class="text-muted">
                            <th style="width: 40px;" class="text-center">No</th>
                            <th>Blok Rumah</th>
                            <th>Cluster</th>
                            <th>Tipe</th>
                            <th>Nama Konsumen</th>
                            <th>Status Pembangunan</th>
                            <th style="width: 18%;">Progres (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($r = mysqli_fetch_assoc($q)) {
                            $progres = (int)($r['persentase_progres'] ?? 0);
                            $st_lapangan = $r['status_pembangunan'] ?? 'Persiapan';
                            $cluster_name = $r['cluster_name'] ?? '';
                            $nama = trim($r['name_lengkap'] ?? '');
                            $konsumen = $nama !== '' ? htmlspecialchars($nama) : '<span class="text-muted fst-italic">Belum terjual</span>';
                        ?>
                        <tr data-cluster="<?= htmlspecialchars($cluster_name) ?>" data-status="<?= htmlspecialchars($st_lapangan) ?>" data-name="<?= htmlspecialchars($nama) ?>">
                            <td class="text-center"><?= $no++ ?></td>
                            <td><span class="badge bg-dark rounded-pill px-3 py-2">Blok <?= htmlspecialchars($r['no_blok']) ?></span></td>
                            <td class="small"><?= htmlspecialchars($cluster_name) ?></td>
                            <td><?= htmlspecialchars($r['tipe_rumah']) ?></td>
                            <td><?= $konsumen ?></td>
                            <td>
                                <?php if ($st_lapangan === 'Selesai') { ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2">Selesai</span>
                                <?php } else { ?>
                                    <span class="badge bg-info text-dark rounded-pill px-3 py-2"><?= htmlspecialchars($st_lapangan) ?></span>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1 rounded-pill" style="height: 10px;">
                                        <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= $progres ?>%;"></div>
                                    </div>
                                    <span class="fw-bold small"><?= $progres ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php if (mysqli_num_rows($q) === 0) { ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Tidak ada data ditemukan.
                    </div>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    // Tanggal cetak pada header laporan
    document.getElementById('tglCetak').textContent = new Date().toLocaleDateString('id-ID', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    }) + ' ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

    const searchInput = document.getElementById('searchInput');
    const btnFilterNama = document.getElementById('btnFilterNama');
    const namaFilterMenu = document.getElementById('namaFilterMenu');
    const namaFilterList = document.getElementById('namaFilterList');
    const btnNamaSemua = document.getElementById('btnNamaSemua');
    const namaFilterCount = document.getElementById('namaFilterCount');
    const filterCluster = document.getElementById('filterCluster');
    const filterStatus = document.getElementById('filterStatus');
    const rows = document.querySelectorAll('#tableLaporan tbody tr');

    let namaChecked = new Set();

    function buildNamaList() {
        const uniq = [];
        rows.forEach(function (row) {
            const nm = row.dataset.name;
            if (nm && uniq.indexOf(nm) === -1) uniq.push(nm);
        });
        uniq.sort(function (a, b) { return a.localeCompare(b); });
        namaFilterList.innerHTML = '';
        if (uniq.length === 0) {
            namaFilterList.innerHTML = '<div class="small text-muted text-center py-3">Tidak ada nama konsumen pada filter aktif.</div>';
            return;
        }
        uniq.forEach(function (nm) {
            const label = document.createElement('label');
            label.className = 'form-check d-flex align-items-center gap-2 px-0 py-1 rounded';
            label.style.cursor = 'pointer';
            label.innerHTML = '<input type="checkbox" class="form-check-input mt-0 nama-check" value="' + nm.replace(/"/g, '&quot;') + '">' +
                '<span class="small text-truncate">' + nm.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
            label.querySelector('.nama-check').addEventListener('change', function () {
                const cb = this;
                const count = namaFilterList.querySelectorAll('.nama-check:checked').length;
                namaFilterCount.textContent = count;
                namaFilterCount.classList.toggle('d-none', count === 0);
                btnFilterNama.querySelector('div span span').textContent = count > 0 ? 'Nama (' + count + ')' : 'Filter Nama';
                applyFilter();
            });
            namaFilterList.appendChild(label);
        });
    }

    btnFilterNama.addEventListener('click', function (e) {
        e.preventDefault();
        const open = namaFilterMenu.classList.contains('show');
        document.querySelectorAll('.dropdown-menu.show').forEach(function (m) { m.classList.remove('show'); });
        if (!open) namaFilterMenu.classList.add('show');
    });
    document.addEventListener('click', function (e) {
        if (!namaFilterWrap.contains(e.target)) namaFilterMenu.classList.remove('show');
    });
    btnNamaSemua.addEventListener('click', function () {
        namaFilterList.querySelectorAll('.nama-check:checked').forEach(function (cb) { cb.checked = false; });
        namaFilterList.querySelectorAll('.nama-check').forEach(function (cb) { cb.checked = true; });
        namaFilterCount.textContent = namaFilterList.querySelectorAll('.nama-check').length;
        namaFilterCount.classList.remove('d-none');
        btnFilterNama.querySelector('div span span').textContent = 'Nama (' + namaFilterCount.textContent + ')';
        applyFilter();
    });

    function applyFilter() {
        const txt = searchInput.value.toLowerCase().trim();
        const cl = filterCluster.value;
        const st = filterStatus.value;
        const checkedNama = Array.from(namaFilterList.querySelectorAll('.nama-check:checked')).map(function (cb) { return cb.value; });
        let visible = 0;
        rows.forEach(function (row) {
            const hay = row.textContent.toLowerCase();
            const matchTxt = hay.includes(txt);
            const matchCl = cl === '' || row.dataset.cluster === cl;
            const matchSt = st === '' || row.dataset.status === st;
            const matchNm = checkedNama.length === 0 || checkedNama.indexOf(row.dataset.name) !== -1;
            const show = matchTxt && matchCl && matchSt && matchNm;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('rowInfo').textContent = visible;
        document.getElementById('rowTotal').textContent = rows.length;
    }

    searchInput.addEventListener('input', applyFilter);
    filterCluster.addEventListener('change', function () { window.location.href = 'laporan_pembangunan.php?filter=' + encodeURIComponent(filterStatus.value) + '&cluster=' + encodeURIComponent(this.value); });
    filterStatus.addEventListener('change', function () { window.location.href = 'laporan_pembangunan.php?filter=' + encodeURIComponent(this.value) + '&cluster=' + encodeURIComponent(filterCluster.value); });
    document.getElementById('btnReset').addEventListener('click', function () {
        window.location.href = 'laporan_pembangunan.php';
    });
    buildNamaList();
    applyFilter();
});
</script>
</body>
</html>