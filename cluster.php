<?php

require_once __DIR__ . '/includes/security.php'; // handler sesi DB harus aktif SEBELUM session_start
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

// LOGIKA TAMBAH CLUSTER
if (isset($_POST['tambah_cluster'])) {
    $cluster_name   = sdb_real_escape_string($koneksi, $_POST['cluster_name']);
    $lokasi         = sdb_real_escape_string($koneksi, $_POST['lokasi']);
    $total_unit     = sdb_real_escape_string($koneksi, $_POST['total_unit']);
    $cluster_status = sdb_real_escape_string($koneksi, $_POST['cluster_status']);

    $qInsert = "INSERT INTO data_cluster (cluster_name, lokasi, total_unit, cluster_status) 
                VALUES ('$cluster_name', '$lokasi', '$total_unit', '$cluster_status')";
    if (sdb_query($koneksi, $qInsert)) {
        header("Location: cluster.php");
        exit();
    }
}

// LOGIKA EDIT CLUSTER
if (isset($_POST['edit_cluster'])) {
    $id_cluster     = sdb_real_escape_string($koneksi, $_POST['id_cluster']);
    $cluster_name   = sdb_real_escape_string($koneksi, $_POST['cluster_name']);
    $lokasi         = sdb_real_escape_string($koneksi, $_POST['lokasi']);
    $total_unit     = sdb_real_escape_string($koneksi, $_POST['total_unit']);
    $cluster_status = sdb_real_escape_string($koneksi, $_POST['cluster_status']);

    $qUpdate = "UPDATE data_cluster SET 
                cluster_name='$cluster_name', 
                lokasi='$lokasi', 
                total_unit='$total_unit', 
                cluster_status='$cluster_status' 
                WHERE id_cluster='$id_cluster'";
    if (sdb_query($koneksi, $qUpdate)) {
        header("Location: cluster.php");
        exit();
    }
}

// LOGIKA HAPUS CLUSTER
if (isset($_GET['hapus'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = sdb_real_escape_string($koneksi, $_GET['hapus']);
    sdb_query($koneksi, "DELETE FROM data_cluster WHERE id_cluster = '$id_hapus'");
    header("Location: cluster.php");
    exit();
}

// MENENTUKAN CLUSTER YANG SEDANG DIPILIH / AKTIF UNTUK SITEPLAN & STATISTIK
$selected_cluster_id = isset($_GET['active_cluster']) ? sdb_real_escape_string($koneksi, $_GET['active_cluster']) : '';

// Jika belum dipilih, ambil cluster pertama
if (empty($selected_cluster_id)) {
    $q_first = sdb_query($koneksi, "SELECT id_cluster FROM data_cluster ORDER BY id_cluster ASC LIMIT 1");
    if ($r_first = sdb_fetch_assoc($q_first)) {
        $selected_cluster_id = $r_first['id_cluster'];
    }
}

// HITUNG REALTIME STATISTIK UNIT BERDASARKAN CLUSTER YANG DIPILIH
$stat_total     = 0;
$stat_tersedia  = 0;
$stat_terbooking= 0;
$stat_terjual   = 0;

if (!empty($selected_cluster_id)) {
    $qStat = sdb_query($koneksi, "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN UPPER(status_unit) = 'TERSEDIA' THEN 1 ELSE 0 END) as tersedia,
        SUM(CASE WHEN UPPER(status_unit) = 'TERBOOKING' THEN 1 ELSE 0 END) as terbooking,
        SUM(CASE WHEN UPPER(status_unit) = 'TERJUAL' THEN 1 ELSE 0 END) as terjual
        FROM data_rumah WHERE id_cluster = '$selected_cluster_id'");
    
    if ($resStat = sdb_fetch_assoc($qStat)) {
        $stat_total      = $resStat['total'] ?? 0;
        $stat_tersedia   = $resStat['tersedia'] ?? 0;
        $stat_terbooking = $resStat['terbooking'] ?? 0;
        $stat_terjual    = $resStat['terjual'] ?? 0;
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
    <title>Data Cluster & Siteplan - Shankara Dev</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        /* GRID BLOK SITEPLAN INTERAKTIF */
        .unit-box {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
        }

        .unit-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .unit-box.status-tersedia {
            background-color: #dbeafe;
            color: #2563eb;
            border: 2px solid #93c5fd;
        }

        .unit-box.status-terbooking {
            background-color: #fef9c3;
            color: #a16207;
            border: 2px solid #fde047;
        }

        .unit-box.status-terjual {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 2px solid #fca5a5;
        }

        .select-pill {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 50rem;
            padding: 8px 18px;
            font-size: 0.9rem;
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
                <h4 class="fw-bold mb-1">Pengelolaan Data Cluster & Siteplan</h4>
                <p class="text-muted small mb-0">Visualisasi siteplan interaktif dan ketersediaan unit real-time per cluster.</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-pill fw-semibold" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambahCluster">
                <i class="bi bi-plus-circle me-1"></i> Tambah Cluster Baru
            </button>
        </div>

        <!-- SELECTOR CLUSTER PADA SITEPLAN -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-uppercase text-muted mb-1">Pilih Cluster Untuk Menampilkan Siteplan:</label>
                    <form method="GET" action="cluster.php" id="formSelectCluster">
                        <select name="active_cluster" class="form-select select-pill fw-semibold text-success border-success" onchange="document.getElementById('formSelectCluster').submit()">
                            <?php
                            $q_all_cluster = sdb_query($koneksi, "SELECT * FROM data_cluster ORDER BY id_cluster ASC");
                            while ($c = sdb_fetch_assoc($q_all_cluster)) {
                                $sel = ($c['id_cluster'] == $selected_cluster_id) ? 'selected' : '';
                                echo "<option value='".$c['id_cluster']."' $sel>&#127970; ".htmlspecialchars($c['cluster_name'])." (".$c['lokasi'].")</option>";
                            }
                            ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-7 d-flex flex-wrap justify-content-center justify-content-md-end gap-3 mt-3 mt-md-0">
                    <div class="text-center px-3 border-end">
                        <span class="d-block text-muted small fw-semibold">Total Unit</span>
                        <span class="fs-4 fw-bold text-dark"><?= $stat_total ?></span>
                    </div>
                    <div class="text-center px-3 border-end">
                        <span class="d-block text-success small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Tersedia</span>
                        <span class="fs-4 fw-bold text-success"><?= $stat_tersedia ?></span>
                    </div>
                    <div class="text-center px-3 border-end">
                        <span class="d-block text-warning small fw-semibold"><i class="bi bi-clock-history me-1"></i>Terbooking</span>
                        <span class="fs-4 fw-bold text-warning"><?= $stat_terbooking ?></span>
                    </div>
                    <div class="text-center px-3">
                        <span class="d-block text-danger small fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>Terjual</span>
                        <span class="fs-4 fw-bold text-danger"><?= $stat_terjual ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- VISUAL SITEPLAN & STATUS UNIT -->
        <div class="content-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-grid-3x3-gap-fill text-success fs-5"></i> Siteplan Grid Unit (Real-time)
                </h6>
                <a href="unit.php?cluster=<?= $selected_cluster_id ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">
                    Kelola Seluruh Unit <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            
            <!-- KETERANGAN WARNA STATUS -->
            <div class="d-flex flex-wrap gap-3 mb-4 p-2 bg-light rounded-3 small">
                <span class="d-flex align-items-center gap-1"><span class="badge bg-success rounded-circle p-1"> </span> TERSEDIA</span>
                <span class="d-flex align-items-center gap-1"><span class="badge bg-warning rounded-circle p-1"> </span> TERBOOKING</span>
                <span class="d-flex align-items-center gap-1"><span class="badge bg-danger rounded-circle p-1"> </span> TERJUAL</span>
            </div>

            <!-- BLOK INTERAKTIF (PERBAIKAN SINTAKS HTML & ATTRIBUTE TITLE) -->
			<div class="d-flex flex-wrap gap-2 overflow-auto p-2" style="max-height: 380px;">
				<?php
				$q_units = sdb_query($koneksi, "SELECT * FROM data_rumah WHERE id_cluster = '$selected_cluster_id' ORDER BY no_blok ASC");
				if ($q_units && sdb_num_rows($q_units) > 0) {
					while ($u = sdb_fetch_assoc($q_units)) {
						$st = strtoupper(trim($u['status_unit'] ?? 'TERSEDIA'));
						$class_st = 'status-tersedia';
						if ($st === 'TERBOOKING') $class_st = 'status-terbooking';
						if ($st === 'TERJUAL') $class_st = 'status-terjual';

						$no_blok    = htmlspecialchars($u['no_blok'] ?? '-');
						$tipe_rumah = htmlspecialchars($u['tipe_rumah'] ?? '-');
						$harga_jual = number_format($u['harga_jual'] ?? 0, 0, ',', '.');
						
						// Tooltip info yang aman dari kuotasi/sintaks break
						$tooltip_info = "Blok: {$no_blok} | Tipe: {$tipe_rumah} | Rp {$harga_jual}";
				?>
						<a href="unit.php?keyword=<?= urlencode($u['no_blok']) ?>" 
						   class="unit-box <?= $class_st ?>" 
						   title="<?= $tooltip_info ?>">
							<span style="font-size: 0.65rem; opacity: 0.8;">Blok</span>
							<span><?= $no_blok ?></span>
							<span style="font-size: 0.6rem; margin-top: 2px; font-weight: 500;"><?= $tipe_rumah ?></span>
						</a>
				<?php
					}
				} else {
					echo "<div class='text-center w-100 py-4 text-muted small'>Belum ada unit rumah terdaftar di cluster ini.</div>";
				}
				?>
			</div>
        </div>

        <!-- TABEL DATA CLUSTER (Sesuai Struktur Database Supabase / PostgreSQL Anda) -->
        <div class="content-card">
            <h5 class="fw-bold mb-3">Daftar Cluster Perumahan</h5>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead>
                        <tr class="text-muted">
                            <th width="80">ID</th>
                            <th>Nama Cluster</th>
                            <th>Lokasi / Alamat</th>
                            <th>Total Unit Target</th>
                            <th>Status Cluster</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q_table_cluster = sdb_query($koneksi, "SELECT * FROM data_cluster ORDER BY id_cluster ASC");
                        while ($row_c = sdb_fetch_assoc($q_table_cluster)) {
                            // Formatting status badge
                            $st_lower = strtolower(trim($row_c['cluster_status']));
if ($st_lower === 'done') {
                                 $badge_status = '<span class="badge bg-success-subtle text-success border border-success px-3 py-1 rounded-pill"><i class="bi bi-check-all me-1"></i>Done</span>';
                             } elseif ($st_lower === 'on going') {
                                 $badge_status = '<span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1 rounded-pill"><i class="bi bi-arrow-repeat me-1"></i>On Going</span>';
                             } elseif (in_array($st_lower, ['hold', 'di hold'], true)) {
                                 $badge_status = '<span class="badge bg-danger-subtle text-danger border border-danger px-3 py-1 rounded-pill"><i class="bi bi-pause-circle me-1"></i>Hold</span>';
                             } else {
                                $badge_status = '<span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-1 rounded-pill"><i class="bi bi-hourglass me-1"></i>Not Started</span>';
                            }
                        ?>
                        <tr>
                            <td><span class="badge bg-light text-secondary border">#CLS-<?= $row_c['id_cluster'] ?></span></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($row_c['cluster_name']) ?></td>
                            <td><i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($row_c['lokasi']) ?></td>
                            <td><span class="fw-semibold"><?= htmlspecialchars($row_c['total_unit']) ?> Unit</span></td>
                            <td><?= $badge_status ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-link text-warning p-0 me-2" data-bs-toggle="modal" data-bs-target="#modalEditCluster<?= $row_c['id_cluster'] ?>" title="Edit Cluster">
                                    <i class="bi bi-pencil-square fs-6"></i>
                                </button>
                                <a href="cluster.php?hapus=<?= $row_c['id_cluster'] ?>&token=<?= htmlspecialchars(csrf_token()) ?>" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Apakah Anda yakin ingin menghapus cluster ini?')" title="Hapus Cluster">
                                    <i class="bi bi-trash fs-6"></i>
                                </a>

                                <!-- MODAL EDIT CLUSTER -->
                                <div class="modal fade" id="modalEditCluster<?= $row_c['id_cluster'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content border-0 rounded-4 shadow">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold">Edit Data Cluster</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="cluster.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_cluster" value="<?= $row_c['id_cluster'] ?>">
                                                <div class="modal-body py-3">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Nama Cluster</label>
                                                        <input type="text" name="cluster_name" class="form-control rounded-3" value="<?= htmlspecialchars($row_c['cluster_name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Lokasi</label>
                                                        <input type="text" name="lokasi" class="form-control rounded-3" value="<?= htmlspecialchars($row_c['lokasi']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Total Unit</label>
                                                        <input type="number" name="total_unit" class="form-control rounded-3" value="<?= htmlspecialchars($row_c['total_unit']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Status Cluster</label>
                                                        <select name="cluster_status" class="form-select rounded-3" required>
                                                            <option value="on going" <?= ($row_c['cluster_status'] == 'on going') ? 'selected' : '' ?>>on going</option>
                                                            <option value="done" <?= ($row_c['cluster_status'] == 'done') ? 'selected' : '' ?>>done</option>
                                                            <option value="not started" <?= ($row_c['cluster_status'] == 'not started') ? 'selected' : '' ?>>not started</option>
                                                            <option value="hold" <?= ($row_c['cluster_status'] == 'hold') ? 'selected' : '' ?>>hold</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_cluster" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL TAMBAH CLUSTER -->
<div class="modal fade" id="modalTambahCluster" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Cluster Perumahan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="cluster.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Cluster</label>
                        <input type="text" name="cluster_name" class="form-control rounded-3" placeholder="Contoh: SHANKARA RESIDENCE 4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Lokasi</label>
                        <input type="text" name="lokasi" class="form-control rounded-3" placeholder="Contoh: Sambas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Total Unit</label>
                        <input type="number" name="total_unit" class="form-control rounded-3" placeholder="Contoh: 50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status Cluster</label>
                        <select name="cluster_status" class="form-select rounded-3" required>
                            <option value="on going">on going</option>
                            <option value="done">done</option>
                            <option value="not started">not started</option>
                            <option value="hold">hold</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_cluster" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Cluster</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
