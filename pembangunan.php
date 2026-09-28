<?php

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
include "includes/koneksi.php";
require_once __DIR__ . '/includes/role_access.php';
role_guard();

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// 1. LOGIKA UPDATE SINGLE PROGRES
if (isset($_POST['simpan_progres'])) {
    $id_rumah = mysqli_real_escape_string($koneksi, $_POST['id_rumah']);
    $persentase = mysqli_real_escape_string($koneksi, $_POST['persentase_progres']);
    $status_p = mysqli_real_escape_string($koneksi, $_POST['status_pembangunan']);
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan_lapangan']);

    $cek = mysqli_query($koneksi, "SELECT * FROM data_pembangunan WHERE id_rumah = '$id_rumah'");
    if (mysqli_num_rows($cek) > 0) {
        $query = "UPDATE data_pembangunan SET persentase_progres='$persentase', status_pembangunan='$status_p', catatan_lapangan='$catatan' WHERE id_rumah='$id_rumah'";
    } else {
        $query = "INSERT INTO data_pembangunan (id_rumah, persentase_progres, status_pembangunan, catatan_lapangan) VALUES ('$id_rumah', '$persentase', '$status_p', '$catatan')";
    }
    
    mysqli_query($koneksi, $query);
    header("Location: pembangunan.php");
    exit();
}

// 2. LOGIKA UPDATE MASSAL (BULK EDIT)
if (isset($_POST['simpan_progres_massal'])) {
    if (!empty($_POST['id_rumah_selected']) && is_array($_POST['id_rumah_selected'])) {
        $persentase = mysqli_real_escape_string($koneksi, $_POST['massal_persentase']);
        $status_p   = mysqli_real_escape_string($koneksi, $_POST['massal_status']);
        $catatan    = mysqli_real_escape_string($koneksi, $_POST['massal_catatan']);

        foreach ($_POST['id_rumah_selected'] as $id_rumah_raw) {
            $id_rumah = intval($id_rumah_raw);
            
            $cek = mysqli_query($koneksi, "SELECT * FROM data_pembangunan WHERE id_rumah = '$id_rumah'");
            if (mysqli_num_rows($cek) > 0) {
                $query = "UPDATE data_pembangunan SET persentase_progres='$persentase', status_pembangunan='$status_p', catatan_lapangan='$catatan' WHERE id_rumah='$id_rumah'";
            } else {
                $query = "INSERT INTO data_pembangunan (id_rumah, persentase_progres, status_pembangunan, catatan_lapangan) VALUES ('$id_rumah', '$persentase', '$status_p', '$catatan')";
            }
            mysqli_query($koneksi, $query);
        }
    }
    header("Location: pembangunan.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Progres Pembangunan - Shankara Dev</title>
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

        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
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
                margin: 1.5cm;
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
            .filter-section,
            th:first-child, 
            td:first-child, /* Sembunyikan Checkbox */
            th:last-child, 
            td:last-child   /* Sembunyikan Kolom Aksi */ {
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
                margin-bottom: 20px;
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
                padding: 8px 10px !important;
                font-size: 9pt !important;
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
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <!-- KONTEN UTAMA -->
    <main class="main-wrapper">
        <!-- HEADER KOP LAPANGAN UNTUK EXPORT PDF -->
        <div class="print-header">
            <h3 id="printTitle">SHANKARA RESIDENCE</h3>
            <p>Laporan Resmi Progres Pembangunan Unit Perumahan</p>
            <p class="mt-1">Dicetak pada: <strong id="tglCetak">-</strong></p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1">Progres Pembangunan Lapangan</h4>
                <p class="text-muted small mb-0">Pantau persentase dan fisik pembangunan unit perumahan.</p>
            </div>
            <div class="d-flex gap-2">
                <!-- Tombol Export PDF -->
                <button type="button" onclick="window.print()" class="btn btn-outline-danger rounded-pill fw-semibold px-3">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                </button>
                <!-- Tombol Edit Massal -->
                <button id="btnMassal" class="btn btn-warning rounded-pill text-dark fw-semibold px-4 d-none" data-bs-toggle="modal" data-bs-target="#modalUpdateMassal">
                    <i class="bi bi-pencil-square me-1"></i> Update Massal (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>

        <div class="content-card">
            <!-- SECTION FILTER & SEARCH -->
            <div class="row g-2 mb-4 align-items-center filter-section no-print">
                <div class="col-md-4">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute text-muted" style="left: 16px; top: 50%; transform: translateY(-50%);"></i>
                        <input type="text" id="searchInput" class="form-control filter-input ps-5 shadow-none" placeholder="Cari Blok / Tipe Rumah...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterCluster" class="form-select filter-input shadow-none">
                        <option value="">Semua Cluster</option>
                        <?php
                        $q_cluster = mysqli_query($koneksi, "SELECT DISTINCT cluster_name FROM data_cluster ORDER BY cluster_name ASC");
                        if ($q_cluster) {
                            while ($c = mysqli_fetch_assoc($q_cluster)) {
                                echo "<option value='" . htmlspecialchars($c['cluster_name']) . "'>" . htmlspecialchars($c['cluster_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="dropdown w-100" id="statusDropdown">
                        <button class="btn filter-input w-100 text-start d-flex justify-content-between align-items-center shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="filterStatusBtn">
                            <span class="text-truncate" id="filterStatusLabel">Semua Status Lapangan</span>
                            <i class="bi bi-chevron-down small ms-2"></i>
                        </button>
                        <div class="dropdown-menu filter-menu shadow rounded-4 border-0 p-3 w-100" style="min-width: 220px;">
                            <div class="text-muted small fw-semibold mb-2"><i class="bi bi-funnel me-1"></i>Filter Status Pembangunan</div>
                            <div class="form-check mb-2">
                                <input class="form-check-input status-check" type="checkbox" value="Persiapan" id="stPersiapan">
                                <label class="form-check-label small" for="stPersiapan">Persiapan / Gali Pondasi</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input status-check" type="checkbox" value="Pondasi" id="stPondasi">
                                <label class="form-check-label small" for="stPondasi">Pondasi & Struktur Bawah</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input status-check" type="checkbox" value="Struktur" id="stStruktur">
                                <label class="form-check-label small" for="stStruktur">Dinding & Atap</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input status-check" type="checkbox" value="Finishing" id="stFinishing">
                                <label class="form-check-label small" for="stFinishing">Finishing & Plester</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input status-check" type="checkbox" value="Selesai" id="stSelesai">
                                <label class="form-check-label small" for="stSelesai">Selesai 100%</label>
                            </div>
                            <button type="button" id="btnTerapkanStatus" class="btn btn-sm text-white w-100 rounded-pill" style="background-color: var(--primary-blue);">Terapkan Filter</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <button id="btnReset" class="btn btn-outline-secondary rounded-pill w-100 py-2 btn-sm fw-medium">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- TABEL DATA -->
            <form id="formMassal" action="pembangunan.php" method="POST">
                <?= csrf_field() ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablePembangunan">
                        <thead>
                            <tr class="text-muted">
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="checkAll">
                                </th>
                                <th>Unit Rumah</th>
                                <th>Tipe</th>
                                <th>Status Lapangan</th>
                                <th style="width: 30%;">Progres (%)</th>
                                <th>Catatan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $q = mysqli_query($koneksi, "SELECT r.*, c.cluster_name, p.persentase_progres, p.status_pembangunan, p.catatan_lapangan 
                                                         FROM data_rumah r 
                                                         LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                                         LEFT JOIN data_pembangunan p ON r.id_rumah = p.id_rumah 
                                                         ORDER BY r.no_blok ASC");
                            while ($r = mysqli_fetch_assoc($q)) {
                                $progres = $r['persentase_progres'] ?? 0;
                                $st_lapangan = $r['status_pembangunan'] ?? 'Persiapan';
                                $cluster_name = $r['cluster_name'] ?? '';
                            ?>
                            <tr data-cluster="<?= htmlspecialchars($cluster_name) ?>">
                                <td class="text-center">
                                    <input type="checkbox" name="id_rumah_selected[]" class="form-check-input check-item" value="<?= $r['id_rumah'] ?>">
                                </td>
                                <td><span class="badge bg-dark rounded-pill px-3 py-2">Blok <?= htmlspecialchars($r['no_blok']) ?></span></td>
                                <td><?= htmlspecialchars($r['tipe_rumah']) ?></td>
                                <td><span class="badge bg-info text-dark rounded-pill px-3 py-2"><?= htmlspecialchars($st_lapangan) ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1 rounded-pill" style="height: 10px;">
                                            <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= $progres ?>%;"></div>
                                        </div>
                                        <span class="fw-bold small"><?= $progres ?>%</span>
                                    </div>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($r['catatan_lapangan'] ?? '-') ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalUpdate<?= $r['id_rumah'] ?>">Update</button>
                                </td>
                            </tr>

                            <!-- MODAL UPDATE SINGLE -->
                            <div class="modal fade" id="modalUpdate<?= $r['id_rumah'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Update Progres Blok <?= $r['no_blok'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="pembangunan.php" method="POST" id="formSingle<?= $r['id_rumah'] ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_rumah" value="<?= $r['id_rumah'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Tahap Konstruksi</label>
                                                    <select name="status_pembangunan" class="form-select rounded-3">
                                                        <option value="Persiapan" <?= $st_lapangan=='Persiapan'?'selected':'' ?>>Persiapan / Gali Pondasi</option>
                                                        <option value="Pondasi" <?= $st_lapangan=='Pondasi'?'selected':'' ?>>Pondasi & Struktur Bawah</option>
                                                        <option value="Struktur" <?= $st_lapangan=='Struktur'?'selected':'' ?>>Dinding & Atap</option>
                                                        <option value="Finishing" <?= $st_lapangan=='Finishing'?'selected':'' ?>>Finishing & Plester</option>
                                                        <option value="Selesai" <?= $st_lapangan=='Selesai'?'selected':'' ?>>Selesai 100%</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Persentase Progres (0-100%)</label>
                                                    <input type="number" min="0" max="100" name="persentase_progres" class="form-control rounded-3" value="<?= $progres ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Catatan Lapangan</label>
                                                    <textarea name="catatan_lapangan" class="form-control rounded-3" rows="3"><?= htmlspecialchars($r['catatan_lapangan'] ?? '') ?></textarea>
                                                </div>
                                                <div class="text-end">
                                                    <button type="submit" name="simpan_progres" class="btn text-white px-4 rounded-pill" style="background-color: var(--primary-blue);">Simpan Progres</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <!-- MODAL UPDATE MASSAL -->
                <div class="modal fade" id="modalUpdateMassal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Update Massal (<span class="countModal">0</span> Unit)</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-light border small mb-3">
                                    <i class="bi bi-info-circle me-1 text-primary"></i> Data yang diisi di bawah ini akan diterapkan sekaligus ke <strong class="countModal">0</strong> unit yang Anda centang.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Tahap Konstruksi Baru</label>
                                    <select name="massal_status" class="form-select rounded-3" required>
                                        <option value="Persiapan">Persiapan / Gali Pondasi</option>
                                        <option value="Pondasi">Pondasi & Struktur Bawah</option>
                                        <option value="Struktur">Dinding & Atap</option>
                                        <option value="Finishing">Finishing & Plester</option>
                                        <option value="Selesai">Selesai 100%</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Persentase Progres Baru (0-100%)</label>
                                    <input type="number" min="0" max="100" name="massal_persentase" class="form-control rounded-3" value="100" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Catatan Lapangan Baru</label>
                                    <textarea name="massal_catatan" class="form-control rounded-3" rows="3" placeholder="Contoh: Pekerjaan tahap finishing selesai bersamaan."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="simpan_progres_massal" class="btn text-white px-4 rounded-pill" style="background-color: var(--primary-blue);">Terapkan Ke Semua</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const filterCluster = document.getElementById('filterCluster');
    const statusChecks = document.querySelectorAll('.status-check');
    const filterStatusLabel = document.getElementById('filterStatusLabel');
    const btnReset = document.getElementById('btnReset');
    const tableRows = document.querySelectorAll('#tablePembangunan tbody tr');

    const checkAll = document.getElementById('checkAll');
    const checkItems = document.querySelectorAll('.check-item');
    const btnMassal = document.getElementById('btnMassal');
    const selectedCount = document.getElementById('selectedCount');
    const countModalList = document.querySelectorAll('.countModal');
    const printTitle = document.getElementById('printTitle');

    let lastChecked = null;

    if (sessionStorage.getItem('pembangunan_search')) {
        searchInput.value = sessionStorage.getItem('pembangunan_search');
    }
    if (sessionStorage.getItem('pembangunan_cluster')) {
        filterCluster.value = sessionStorage.getItem('pembangunan_cluster');
    }
    const savedStatuses = sessionStorage.getItem('pembangunan_status');
    if (savedStatuses) {
        const savedArr = savedStatuses.split(',');
        statusChecks.forEach(cb => { cb.checked = savedArr.includes(cb.value); });
    }

    function getSelectedStatuses() {
        return Array.from(statusChecks).filter(cb => cb.checked).map(cb => cb.value);
    }

    function updateStatusLabel() {
        const sel = getSelectedStatuses();
        if (sel.length === 0) {
            filterStatusLabel.innerText = 'Semua Status Lapangan';
        } else if (sel.length === 1) {
            filterStatusLabel.innerText = sel[0];
        } else {
            filterStatusLabel.innerText = sel.length + ' Status Dipilih';
        }
    }

    function updatePrintTitle() {
        const selectedCluster = filterCluster.value.trim();
        if (selectedCluster !== '') {
            printTitle.innerText = selectedCluster.toUpperCase();
        } else {
            printTitle.innerText = 'SEMUA CLUSTER - SHANKARA DEV';
        }
    }

    function filterTable() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedCluster = filterCluster.value.toLowerCase();
        const rawStatuses = getSelectedStatuses();
        const selectedStatuses = rawStatuses.map(s => s.toLowerCase());

        sessionStorage.setItem('pembangunan_search', searchInput.value);
        sessionStorage.setItem('pembangunan_cluster', filterCluster.value);
        sessionStorage.setItem('pembangunan_status', rawStatuses.join(','));

        updatePrintTitle();
        updateStatusLabel();

        tableRows.forEach(row => {
            const rowText = row.innerText.toLowerCase();
            const clusterAttr = (row.getAttribute('data-cluster') || '').toLowerCase();
            const statusCell = row.children[3]?.innerText.toLowerCase() || '';

            const matchQuery = rowText.includes(query);
            const matchCluster = selectedCluster === '' || clusterAttr === selectedCluster;
            const matchStatus = selectedStatuses.length === 0 || selectedStatuses.some(s => statusCell.includes(s));

            if (matchQuery && matchCluster && matchStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function updateMassalButton() {
        let totalChecked = 0;
        checkItems.forEach(item => {
            if (item.closest('tr').style.display !== 'none' && item.checked) {
                totalChecked++;
            }
        });

        selectedCount.innerText = totalChecked;
        countModalList.forEach(el => el.innerText = totalChecked);

        if (totalChecked > 0) {
            btnMassal.classList.remove('d-none');
        } else {
            btnMassal.classList.add('d-none');
        }
    }

    checkAll.addEventListener('change', function () {
        tableRows.forEach(row => {
            if (row.style.display !== 'none') {
                const checkbox = row.querySelector('.check-item');
                if (checkbox) checkbox.checked = checkAll.checked;
            }
        });
        updateMassalButton();
    });

    checkItems.forEach((item) => {
        item.addEventListener('click', function (e) {
            let inBetween = false;
            const visibleCheckboxes = Array.from(checkItems).filter(cb => cb.closest('tr').style.display !== 'none');

            if (e.shiftKey && lastChecked && lastChecked !== this && lastChecked.closest('tr').style.display !== 'none') {
                visibleCheckboxes.forEach(checkbox => {
                    if (checkbox === this || checkbox === lastChecked) {
                        inBetween = !inBetween;
                    }
                    if (inBetween) {
                        checkbox.checked = this.checked;
                    }
                });
            }
            lastChecked = this;
            updateMassalButton();
        });
    });

    // Otomatis perbarui Tanggal & Jam persis saat dialog Print/Export PDF dibuka
    window.addEventListener('beforeprint', function () {
        const now = new Date();
        const tgl = String(now.getDate()).padStart(2, '0');
        const bln = String(now.getMonth() + 1).padStart(2, '0');
        const thn = now.getFullYear();
        const jam = String(now.getHours()).padStart(2, '0');
        const mnt = String(now.getMinutes()).padStart(2, '0');

        const formattedTime = `${tgl}/${bln}/${thn} ${jam}:${mnt} WIB`;
        
        const elTgl = document.getElementById('tglCetak');
        if (elTgl) {
            elTgl.innerText = formattedTime;
        }
        
        updatePrintTitle();
    });

    filterTable();

    searchInput.addEventListener('input', filterTable);
    filterCluster.addEventListener('change', filterTable);
    statusChecks.forEach(cb => cb.addEventListener('change', updateStatusLabel));
    document.getElementById('btnTerapkanStatus').addEventListener('click', function () {
        bootstrap.Dropdown.getInstance(document.getElementById('filterStatusBtn')).hide();
        filterTable();
    });
    // Terapkan otomatis saat dropdown ditutup (klik di luar/ESC)
    document.getElementById('filterStatusBtn').addEventListener('hidden.bs.dropdown', filterTable);

    btnReset.addEventListener('click', function () {
        searchInput.value = '';
        filterCluster.value = '';
        statusChecks.forEach(cb => cb.checked = false);
        sessionStorage.removeItem('pembangunan_search');
        sessionStorage.removeItem('pembangunan_cluster');
        sessionStorage.removeItem('pembangunan_status');
        filterTable();
    });
});
</script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
