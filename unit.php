<?php
include "includes/koneksi.php";

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

// LOGIKA TAMBAH UNIT BARU
if (isset($_POST['tambah_unit'])) {
    $id_cluster = sdb_real_escape_string($koneksi, $_POST['id_cluster']);
    $no_blok    = sdb_real_escape_string($koneksi, $_POST['no_blok']);
    $tipe_rumah = sdb_real_escape_string($koneksi, $_POST['tipe_rumah']);
    $no_shm     = sdb_real_escape_string($koneksi, $_POST['no_shm']);
    $luas_tanah = sdb_real_escape_string($koneksi, $_POST['luas_tanah']);
    $luas_bangunan = sdb_real_escape_string($koneksi, $_POST['luas_bangunan']);
    $harga_jual = sdb_real_escape_string($koneksi, $_POST['harga_jual']);
    $status_unit= sdb_real_escape_string($koneksi, $_POST['status_unit']);

    $queryInsert = "INSERT INTO data_rumah (id_cluster, no_blok, tipe_rumah, no_shm, luas_tanah, luas_bangunan, harga_jual, status_unit) 
                    VALUES ('$id_cluster', '$no_blok', '$tipe_rumah', '$no_shm', '$luas_tanah', '$luas_bangunan', '$harga_jual', '$status_unit')";
    
    if (sdb_query($koneksi, $queryInsert)) {
        header("Location: unit.php");
        exit();
    } else {
        echo "<script>alert('Gagal menambah unit: " . sdb_error($koneksi) . "');</script>";
    }
}

// LOGIKA EDIT UNIT
if (isset($_POST['edit_unit'])) {
    $id_rumah   = sdb_real_escape_string($koneksi, $_POST['id_rumah']);
    $id_cluster = sdb_real_escape_string($koneksi, $_POST['id_cluster']);
    $no_blok    = sdb_real_escape_string($koneksi, $_POST['no_blok']);
    $tipe_rumah = sdb_real_escape_string($koneksi, $_POST['tipe_rumah']);
    $no_shm     = sdb_real_escape_string($koneksi, $_POST['no_shm']);
    $luas_tanah = sdb_real_escape_string($koneksi, $_POST['luas_tanah']);
    $luas_bangunan = sdb_real_escape_string($koneksi, $_POST['luas_bangunan']);
    $harga_jual = sdb_real_escape_string($koneksi, $_POST['harga_jual']);
    $status_unit= sdb_real_escape_string($koneksi, $_POST['status_unit']);

    $queryUpdate = "UPDATE data_rumah SET 
                    id_cluster = '$id_cluster',
                    no_blok = '$no_blok',
                    tipe_rumah = '$tipe_rumah',
                    no_shm = '$no_shm',
                    luas_tanah = '$luas_tanah',
                    luas_bangunan = '$luas_bangunan',
                    harga_jual = '$harga_jual',
                    status_unit = '$status_unit'
                    WHERE id_rumah = '$id_rumah'";

    if (sdb_query($koneksi, $queryUpdate)) {
        header("Location: unit.php");
        exit();
    } else {
        echo "<script>alert('Gagal mengupdate unit: " . sdb_error($koneksi) . "');</script>";
    }
}

// LOGIKA HAPUS UNIT
if (isset($_GET['hapus'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = sdb_real_escape_string($koneksi, $_GET['hapus']);
    sdb_query($koneksi, "DELETE FROM data_rumah WHERE id_rumah = '$id_hapus'");
    header("Location: unit.php");
    exit();
}

// LOGIKA PENANGKAPAN FILTER
$filter_keyword = isset($_GET['keyword']) ? sdb_real_escape_string($koneksi, $_GET['keyword']) : '';
$filter_cluster = isset($_GET['cluster']) ? sdb_real_escape_string($koneksi, $_GET['cluster']) : '';
$filter_status  = isset($_GET['status']) ? sdb_real_escape_string($koneksi, $_GET['status']) : '';
$filter_tipe    = isset($_GET['tipe']) ? sdb_real_escape_string($koneksi, $_GET['tipe']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Pengelolaan Data Unit Rumah - Shankara Dev</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        /* STYLE BAR FILTER FULL ROUNDED (PILL-SHAPED) SESUAI GAMBAR */
        .filter-card {
            background-color: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 12px 16px;
        }

        .filter-input-pill {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 50rem !important;
            font-size: 0.875rem;
            color: #374151;
            padding: 8px 16px 8px 38px;
            box-shadow: none !important;
        }

        .filter-input-pill:focus {
            border-color: var(--primary-blue);
        }

        .filter-select-pill {
            background-color: #f8fafc;
            border: 1px solid transparent;
            border-radius: 50rem !important;
            font-size: 0.875rem;
            color: #374151;
            padding: 8px 16px;
            box-shadow: none !important;
        }

        .filter-select-pill:focus {
            border-color: var(--primary-blue);
            background-color: #ffffff;
        }

        .filter-icon-pill {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
            z-index: 5;
        }

        .btn-filter-pill {
            background-color: var(--primary-blue);
            color: #ffffff;
            border-radius: 50rem !important;
            font-weight: 500;
            font-size: 0.875rem;
            padding: 8px 20px;
            border: none;
            transition: background-color 0.2s ease;
        }

        .btn-filter-pill:hover {
            background-color: #172554;
            color: #ffffff;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Pengelolaan Data Unit Rumah</h4>
                <p class="text-muted small mb-0">Kelola ketersediaan, tipe, SHM, ukuran tanah, dan harga unit perumahan.</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3 fw-semibold" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambahUnit">
                + Tambah Unit
            </button>
        </div>

        <!-- BAR FILTER ROUNDED PILL (SAMA PERSIS DENGAN GAMBAR) -->
        <div class="filter-card shadow-sm mb-4">
            <form method="GET" action="unit.php" class="row g-2 align-items-center">
                
                <!-- 1. Input Cari Kata Kunci -->
                <div class="col-lg-3 col-md-6">
                    <div class="position-relative">
                        <i class="bi bi-search filter-icon-pill"></i>
                        <input type="text" name="keyword" class="form-control filter-input-pill" 
                               placeholder="Cari Blok, Tipe, SHM..." value="<?= htmlspecialchars($filter_keyword) ?>">
                    </div>
                </div>

                <!-- 2. Filter Cluster -->
                <div class="col-lg-3 col-md-6">
                    <select name="cluster" class="form-select filter-select-pill">
                        <option value="">-- Semua Cluster --</option>
                        <?php
                        $q_cluster = sdb_query($koneksi, "SELECT * FROM data_cluster ORDER BY cluster_name ASC");
                        while ($cl = sdb_fetch_assoc($q_cluster)) {
                            $selected = ($filter_cluster == $cl['id_cluster']) ? 'selected' : '';
                            echo "<option value='".$cl['id_cluster']."' $selected>".htmlspecialchars($cl['cluster_name'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- 3. Filter Status Unit -->
                <div class="col-lg-2 col-md-4">
                    <select name="status" class="form-select filter-select-pill">
                        <option value="">-- Semua Status --</option>
                        <option value="TERSEDIA" <?= ($filter_status == 'TERSEDIA') ? 'selected' : '' ?>>TERSEDIA</option>
                        <option value="TERBOOKING" <?= ($filter_status == 'TERBOOKING') ? 'selected' : '' ?>>TERBOOKING</option>
                        <option value="TERJUAL" <?= ($filter_status == 'TERJUAL') ? 'selected' : '' ?>>TERJUAL</option>
                    </select>
                </div>

                <!-- 4. Filter Tipe Rumah -->
                <div class="col-lg-2 col-md-4">
                    <select name="tipe" class="form-select filter-select-pill">
                        <option value="">-- Semua Tipe --</option>
                        <?php
                        $q_tipe = sdb_query($koneksi, "SELECT DISTINCT tipe_rumah FROM data_rumah WHERE tipe_rumah IS NOT NULL AND tipe_rumah != '' ORDER BY tipe_rumah ASC");
                        while ($tp = sdb_fetch_assoc($q_tipe)) {
                            $selected = ($filter_tipe == $tp['tipe_rumah']) ? 'selected' : '';
                            echo "<option value='".htmlspecialchars($tp['tipe_rumah'])."' $selected>Tipe ".htmlspecialchars($tp['tipe_rumah'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- 5. Tombol Filter & Reset -->
                <div class="col-lg-2 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-filter-pill w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; min-width: 38px;" id="btnExportPdfUnit" title="Export PDF">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </button>
                    <?php if (!empty($filter_keyword) || !empty($filter_cluster) || !empty($filter_status) || !empty($filter_tipe)): ?>
                        <a href="unit.php" class="btn btn-outline-secondary d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; min-width: 38px;" title="Reset Filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="unitTable" style="font-size: 0.88rem;">
                    <thead>
                        <tr class="text-muted">
                            <th>Cluster</th>
                            <th>No. Blok</th>
                            <th>Tipe Rumah</th>
                            <th>No. SHM</th>
                            <th>Luas Tanah</th>
                            <th>Luas Bangunan</th>
                            <th>Harga (Rp)</th>
                            <th>Status Unit</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // CONSTRUCT QUERY WITH FILTERS
                        $query = "SELECT r.*, c.cluster_name 
                                  FROM data_rumah r 
                                  LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster 
                                  WHERE 1=1";

                        if (!empty($filter_keyword)) {
                            $query .= " AND (r.no_blok LIKE '%$filter_keyword%' 
                                       OR r.tipe_rumah LIKE '%$filter_keyword%' 
                                       OR r.no_shm LIKE '%$filter_keyword%' 
                                       OR c.cluster_name LIKE '%$filter_keyword%')";
                        }

                        if (!empty($filter_cluster)) {
                            $query .= " AND r.id_cluster = '$filter_cluster'";
                        }

                        if (!empty($filter_status)) {
                            $query .= " AND UPPER(r.status_unit) = '$filter_status'";
                        }

                        if (!empty($filter_tipe)) {
                            $query .= " AND r.tipe_rumah = '$filter_tipe'";
                        }

                        $query .= " ORDER BY c.cluster_name ASC, r.no_blok ASC";
                        $result = sdb_query($koneksi, $query);

                        if ($result && sdb_num_rows($result) > 0) {
                            while ($row = sdb_fetch_assoc($result)) {
                                $statusRaw = $row['status_unit'] ?? 'TERSEDIA';
                                $status = strtoupper(trim($statusRaw));

                                if ($status === 'TERJUAL') {
                                    $badge = 'bg-danger text-white';
                                } elseif ($status === 'TERBOOKING') {
                                    $badge = 'bg-warning text-dark';
                                } else {
                                    $badge = 'bg-success text-white';
                                }
                        ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['cluster_name'] ?? 'SHANKARA RESIDENCE') ?></td>
                            <td><span class="badge bg-dark">Blok <?= htmlspecialchars($row['no_blok']) ?></span></td>
                            <td><?= htmlspecialchars($row['tipe_rumah']) ?></td>
                            <td>
                                <?= !empty($row['no_shm']) ? '<span class="fw-semibold text-primary"><i class="bi bi-file-earmark-text me-1"></i>'.htmlspecialchars($row['no_shm']).'</span>' : '<span class="text-muted small">-</span>' ?>
                            </td>
                            <td>
                                <?= !empty($row['luas_tanah']) ? htmlspecialchars($row['luas_tanah']).' m²' : '<span class="text-muted small">-</span>' ?>
                            </td>
                            <td>
                                <?= !empty($row['luas_bangunan']) ? htmlspecialchars($row['luas_bangunan']).' m²' : '<span class="text-muted small">-</span>' ?>
                            </td>
                            <td class="fw-semibold">Rp <?= number_format($row['harga_jual'] ?? 0, 0, ',', '.') ?></td>
                            <td><span class="badge <?= $badge ?> px-3 py-1 rounded-2"><?= htmlspecialchars($status) ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-link text-warning p-0 me-2" data-bs-toggle="modal" data-bs-target="#modalEditUnit<?= $row['id_rumah'] ?>">
                                    <i class="bi bi-pencil-square fs-6"></i>
                                </button>
                                <a href="unit.php?hapus=<?= $row['id_rumah'] ?>&token=<?= htmlspecialchars(csrf_token()) ?>" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Apakah Anda yakin ingin menghapus unit ini?')">
                                    <i class="bi bi-trash fs-6"></i>
                                </a>

                                <!-- MODAL EDIT UNIT -->
                                <div class="modal fade" id="modalEditUnit<?= $row['id_rumah'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content border-0 rounded-4 shadow">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold">Edit Data Unit</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="unit.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_rumah" value="<?= $row['id_rumah'] ?>">
                                                <div class="modal-body py-3">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Cluster Perumahan</label>
                                                        <select name="id_cluster" class="form-select rounded-3" required>
                                                            <?php
                                                            $qCluster = sdb_query($koneksi, "SELECT * FROM data_cluster");
                                                            while ($c = sdb_fetch_assoc($qCluster)) {
                                                                $selected = ($c['id_cluster'] == $row['id_cluster']) ? 'selected' : '';
                                                                echo "<option value='".$c['id_cluster']."' $selected>".htmlspecialchars($c['cluster_name'])."</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">No. Blok</label>
                                                            <input type="text" name="no_blok" class="form-control rounded-3" value="<?= htmlspecialchars($row['no_blok']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Tipe Rumah</label>
                                                            <input type="text" name="tipe_rumah" class="form-control rounded-3" value="<?= htmlspecialchars($row['tipe_rumah']) ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Nomor SHM</label>
                                                            <input type="text" name="no_shm" class="form-control rounded-3" placeholder="Contoh: SHM 01234" value="<?= htmlspecialchars($row['no_shm'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Luas Tanah (m²)</label>
                                                            <input type="number" name="luas_tanah" class="form-control rounded-3" placeholder="Contoh: 72" value="<?= htmlspecialchars($row['luas_tanah'] ?? '') ?>">
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Luas Bangunan (m²)</label>
                                                            <input type="number" name="luas_bangunan" class="form-control rounded-3" placeholder="Contoh: 36" value="<?= htmlspecialchars($row['luas_bangunan'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Harga Jual (Rp)</label>
                                                            <input type="number" name="harga_jual" class="form-control rounded-3" value="<?= htmlspecialchars($row['harga_jual'] ?? 0) ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Status Unit</label>
                                                            <select name="status_unit" class="form-select rounded-3" required>
                                                                <option value="TERSEDIA" <?= ($status === 'TERSEDIA') ? 'selected' : '' ?>>TERSEDIA</option>
                                                                <option value="TERBOOKING" <?= ($status === 'TERBOOKING') ? 'selected' : '' ?>>TERBOOKING</option>
                                                                <option value="TERJUAL" <?= ($status === 'TERJUAL') ? 'selected' : '' ?>>TERJUAL</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_unit" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center text-muted py-4'>Tidak ada data unit rumah yang sesuai dengan kriteria filter.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL TAMBAH UNIT -->
<div class="modal fade" id="modalTambahUnit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Unit Rumah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="unit.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cluster Perumahan</label>
                        <select name="id_cluster" class="form-select rounded-3" required>
                            <option value="">-- Pilih Cluster --</option>
                            <?php
                            $qCluster = sdb_query($koneksi, "SELECT * FROM data_cluster");
                            while ($c = sdb_fetch_assoc($qCluster)) {
                                echo "<option value='".$c['id_cluster']."'>".htmlspecialchars($c['cluster_name'])."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">No. Blok</label>
                            <input type="text" name="no_blok" class="form-control rounded-3" placeholder="Misal: A-01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tipe Rumah</label>
                            <input type="text" name="tipe_rumah" class="form-control rounded-3" placeholder="Misal: 36" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor SHM</label>
                            <input type="text" name="no_shm" class="form-control rounded-3" placeholder="Contoh: SHM 01234">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Luas Tanah (m²)</label>
                            <input type="number" name="luas_tanah" class="form-control rounded-3" placeholder="Contoh: 72">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Luas Bangunan (m²)</label>
                            <input type="number" name="luas_bangunan" class="form-control rounded-3" placeholder="Contoh: 36">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Status Unit</label>
                            <select name="status_unit" class="form-select rounded-3">
                                <option value="TERSEDIA">TERSEDIA</option>
                                <option value="TERBOOKING">TERBOOKING</option>
                                <option value="TERJUAL">TERJUAL</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Harga Jual (Rp)</label>
                            <input type="number" name="harga_jual" class="form-control rounded-3" placeholder="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_unit" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btnExport = document.getElementById('btnExportPdfUnit');
            if (btnExport) {
                btnExport.addEventListener('click', function () {
                    const form = btnExport.closest('form');
                    if (form) {
                        const params = new URLSearchParams(new FormData(form));
                        window.open('print/cetak_unit.php?' + params.toString(), '_blank');
                    } else {
                        window.open('print/cetak_unit.php', '_blank');
                    }
                });
            }
        });
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
