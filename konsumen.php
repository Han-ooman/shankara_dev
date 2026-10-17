<?php
include "includes/koneksi.php";
require_once __DIR__ . '/includes/helper_log.php';
require_once __DIR__ . '/includes/konsumen_filter.php';

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

// Logika Tambah Konsumen
if (isset($_POST['simpan_konsumen'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['name_lengkap']);
    $no_ktp = mysqli_real_escape_string($koneksi, $_POST['no_ktp']);
    $pekerjaan = mysqli_real_escape_string($koneksi, $_POST['pekerjaan']);
    $telepon = mysqli_real_escape_string($koneksi, $_POST['no_whatsapp']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat_ktp'] ?? '');
    
    mysqli_query($koneksi, "INSERT INTO data_konsumen (name_lengkap, no_ktp, pekerjaan, no_whatsapp, alamat_ktp) VALUES ('$nama', '$no_ktp', '$pekerjaan', '$telepon', '$alamat')");
    log_aktivitas($koneksi, 'TAMBAH', 'Konsumen', 'Tambah konsumen: ' . $nama, ['id_konsumen' => (int) mysqli_insert_id($koneksi), 'no_ktp' => $no_ktp]);
    header("Location: konsumen.php");
    exit();
}

// Logika Edit Per-Konsumen
if (isset($_POST['edit_konsumen'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id_konsumen']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['name_lengkap']);
    $no_ktp = mysqli_real_escape_string($koneksi, $_POST['no_ktp']);
    $pekerjaan = mysqli_real_escape_string($koneksi, $_POST['pekerjaan']);
    $telepon = mysqli_real_escape_string($koneksi, $_POST['no_whatsapp']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat_ktp'] ?? '');

    mysqli_query($koneksi, "UPDATE data_konsumen SET name_lengkap='$nama', no_ktp='$no_ktp', pekerjaan='$pekerjaan', no_whatsapp='$telepon', alamat_ktp='$alamat' WHERE id_konsumen='$id'");
    log_aktivitas($koneksi, 'UBAH', 'Konsumen', 'Ubah konsumen #' . $id . ': ' . $nama, ['id_konsumen' => (int) $id]);
    header("Location: konsumen.php");
    exit();
}

// Logika Edit Massal (Ubah Pekerjaan Konsumen)
if (isset($_POST['edit_massal'])) {
    if (!empty($_POST['selected_ids'])) {
        $pekerjaan = mysqli_real_escape_string($koneksi, $_POST['pekerjaan_massal']);
        $ids = array_map(function($id) use ($koneksi) {
            return mysqli_real_escape_string($koneksi, $id);
        }, $_POST['selected_ids']);
        
        $ids_string = implode(',', $ids);
        mysqli_query($koneksi, "UPDATE data_konsumen SET pekerjaan='$pekerjaan' WHERE id_konsumen IN ($ids_string)");
        log_aktivitas($koneksi, 'UBAH_MASSAL', 'Konsumen', 'Ubah pekerjaan massal ' . count($ids) . ' konsumen menjadi: ' . $pekerjaan, ['ids' => array_map('intval', $ids), 'pekerjaan' => $pekerjaan]);
        header("Location: konsumen.php");
        exit();
    }
}

// Logika Hapus Data Konsumen
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = mysqli_real_escape_string($koneksi, $_GET['id']);
    $q_nama = mysqli_query($koneksi, "SELECT name_lengkap FROM data_konsumen WHERE id_konsumen = '$id_hapus'");
    $nama_hapus = $q_nama ? (string) mysqli_fetch_assoc($q_nama)['name_lengkap'] : '';
    mysqli_query($koneksi, "DELETE FROM data_konsumen WHERE id_konsumen = '$id_hapus'");
    log_aktivitas($koneksi, 'HAPUS', 'Konsumen', 'Hapus konsumen #' . $id_hapus . ': ' . $nama_hapus, ['id_konsumen' => (int) $id_hapus]);
    header("Location: konsumen.php");
    exit();
}

// LOGIKA PENANGKAPAN FILTER KONSUMEN
$filters = konsumen_get_filters();
$filter_keyword   = $filters['keyword'];
$filter_pekerjaan = $filters['pekerjaan'];
$filter_wa        = $filters['wa'];
$filter_ktp       = $filters['ktp'];
$filter_bi        = $filters['bi'];
$filter_transaksi = $filters['transaksi'];
$filter_sort      = $filters['sort'];
$ada_filter       = konsumen_filter_active($filters);
$daftar_pekerjaan = konsumen_daftar_pekerjaan($koneksi);

// Ambil data konsumen sesuai filter (logic terpusat di includes/konsumen_filter.php)
$konsumen_rows = konsumen_list($koneksi, $filters);
$jumlah_hasil  = count($konsumen_rows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">    <title>Data Konsumen - Shankara Dev</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        .search-input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 0.85rem;
            z-index: 5;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-light">
    <input type="hidden" id="csrf_global" value="<?= htmlspecialchars(csrf_token()) ?>">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <!-- KONTEN UTAMA FULL WIDTH -->
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Pengelolaan Data Konsumen</h4>
                <p class="text-muted small mb-0">Kelola identitas, riwayat, dan data kontak konsumen perumahan.</p>
            </div>
            <div>
                <!-- Tombol Edit Massal -->
                <button type="button" id="btnEditMassal" class="btn btn-warning text-dark rounded-3 px-3 py-2 fw-semibold d-none me-2" data-bs-toggle="modal" data-bs-target="#modalEditMassal">
                    <i class="bi bi-pencil-square me-1"></i> Edit Massal (<span id="selectedCount">0</span>)
                </button>
                <button class="btn btn-green" data-bs-toggle="modal" data-bs-target="#modalKonsumen">
                    <i class="bi bi-person-plus me-1"></i> Tambah Konsumen
                </button>
            </div>
        </div>

        <!-- BAR FILTER MULTI-KRITERIA KONSUMEN -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #ffffff;">
            <div class="card-body p-3">
                <form method="GET" action="konsumen.php" class="row g-3 align-items-center" id="formFilterKonsumen">

                    <!-- 1. Input Cari Kata Kunci -->
                    <div class="col-12 col-lg-4 col-md-12">
                        <div class="position-relative">
                            <i class="bi bi-search search-input-icon"></i>
                            <input type="text" name="keyword" class="form-control rounded-pill search-input-pill"
                                   placeholder="Cari ID, Nama, Pekerjaan, No WA, KTP, Alamat..." value="<?= htmlspecialchars($filter_keyword) ?>">
                        </div>
                    </div>

                    <!-- 2. Filter Pekerjaan -->
                    <div class="col-6 col-lg-3 col-md-6">
                        <select name="pekerjaan" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">-- Semua Pekerjaan --</option>
                            <?php foreach ($daftar_pekerjaan as $pk): ?>
                                <option value="<?= htmlspecialchars($pk) ?>" <?= ($filter_pekerjaan == $pk) ? 'selected' : '' ?>><?= htmlspecialchars($pk) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Filter Status BI Checking -->
                    <div class="col-6 col-lg-2 col-md-6">
                        <select name="bi" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">-- Semua BI --</option>
                            <option value="lulus" <?= ($filter_bi == 'lulus') ? 'selected' : '' ?>>BI Lulus</option>
                            <option value="dalam_proses" <?= ($filter_bi == 'dalam_proses') ? 'selected' : '' ?>>BI Dalam Proses</option>
                            <option value="dipertimbangkan" <?= ($filter_bi == 'dipertimbangkan') ? 'selected' : '' ?>>BI Dipertimbangkan</option>
                            <option value="tidak_lulus" <?= ($filter_bi == 'tidak_lulus') ? 'selected' : '' ?>>BI Tidak Lulus</option>
                            <option value="belum" <?= ($filter_bi == 'belum') ? 'selected' : '' ?>>Belum Dicek</option>
                        </select>
                    </div>

                    <!-- 4. Filter Status Transaksi -->
                    <div class="col-6 col-lg-3 col-md-6">
                        <select name="transaksi" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">-- Semua Status Transaksi --</option>
                            <option value="belum" <?= ($filter_transaksi == 'belum') ? 'selected' : '' ?>>Belum Ada Transaksi</option>
                            <option value="proses" <?= ($filter_transaksi == 'proses') ? 'selected' : '' ?>>Proses</option>
                            <option value="acc" <?= ($filter_transaksi == 'acc') ? 'selected' : '' ?>>ACC - Akad</option>
                            <option value="batal" <?= ($filter_transaksi == 'batal') ? 'selected' : '' ?>>Batal</option>
                        </select>
                    </div>

                    <!-- 5. Filter Status WhatsApp -->
                    <div class="col-6 col-lg-2 col-md-4">
                        <select name="wa" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">-- Semua Kontak --</option>
                            <option value="ada" <?= ($filter_wa == 'ada') ? 'selected' : '' ?>>Ada WhatsApp</option>
                            <option value="tidak" <?= ($filter_wa == 'tidak') ? 'selected' : '' ?>>Tidak Ada WhatsApp</option>
                        </select>
                    </div>

                    <!-- 6. Filter Status KTP -->
                    <div class="col-6 col-lg-2 col-md-4">
                        <select name="ktp" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">-- Semua KTP --</option>
                            <option value="ada" <?= ($filter_ktp == 'ada') ? 'selected' : '' ?>>Ada No. KTP</option>
                            <option value="tidak" <?= ($filter_ktp == 'tidak') ? 'selected' : '' ?>>Tidak Ada No. KTP</option>
                        </select>
                    </div>

                    <!-- 7. Urutkan -->
                    <div class="col-6 col-lg-3 col-md-4">
                        <select name="sort" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="" <?= ($filter_sort == '') ? 'selected' : '' ?>>Urut: Nama (A-Z)</option>
                            <option value="nama_desc" <?= ($filter_sort == 'nama_desc') ? 'selected' : '' ?>>Urut: Nama (Z-A)</option>
                            <option value="id_desc" <?= ($filter_sort == 'id_desc') ? 'selected' : '' ?>>Urut: Terbaru</option>
                            <option value="id_asc" <?= ($filter_sort == 'id_asc') ? 'selected' : '' ?>>Urut: Terlama</option>
                        </select>
                    </div>

                    <!-- 8. Tombol Filter, Export & Reset -->
                    <div class="col-12 col-lg-5 col-md-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn text-white rounded-pill flex-grow-1" style="background-color: var(--primary-blue); padding: 8px;">
                            <i class="bi bi-funnel-fill me-1"></i> Filter
                        </button>
                        <button type="button" class="btn btn-outline-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" id="btnExportPdfKonsumen" title="Export PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" id="btnExportExcelKonsumen" title="Export Excel">
                            <i class="bi bi-file-earmark-excel"></i>
                        </button>
                        <?php if ($ada_filter): ?>
                            <a href="konsumen.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Reset Filter">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <!-- TABEL FULL WIDTH -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span class="text-muted small fw-semibold">
                    Menampilkan <strong id="jumlahHasil"><?= $jumlah_hasil ?></strong> konsumen
                    <?php if ($ada_filter): ?>
                        dari filter aktif
                    <?php else: ?>
                        (semua data)
                    <?php endif; ?>
                </span>
                <?php if ($ada_filter): ?>
                    <span class="text-muted small">
                        <?= implode(' &nbsp;|&nbsp; ', array_map('htmlspecialchars', konsumen_filter_labels($filters))) ?>
                    </span>
                <?php endif; ?>
            </div>
            <form id="formMassal" action="konsumen.php" method="POST">
                <?= csrf_field() ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="konsumenTable">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input" onclick="toggleSelectAll(this)">
                                </th>
                                <th style="width: 130px;">ID Konsumen</th>
                                <th>Nama Lengkap</th>
                                <th>Pekerjaan</th>
                                <th>Alamat KTP</th>
                                <th>No. KTP</th>
                                <th>No. WhatsApp / HP</th>
                                <th>BI Checking</th>
                                <th>Status Transaksi</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $modalEditHTML = '';
                            if ($jumlah_hasil > 0) {
                                foreach ($konsumen_rows as $k) {
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="selected_ids[]" value="<?= $k['id_konsumen'] ?>" class="form-check-input konsumen-checkbox" onchange="updateMassalBtn()">
                                </td>
                                <td><span class="badge bg-light text-secondary border px-2 py-1">#KNS-<?= $k['id_konsumen'] ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($k['name_lengkap']) ?></td>
                                <td><span class="text-uppercase small fw-semibold text-muted"><?= htmlspecialchars($k['pekerjaan']) ?></span></td>
                                <td><span class="small text-dark"><?= $k['alamat_ktp'] ? htmlspecialchars($k['alamat_ktp']) : '<span class="text-muted">-</span>' ?></span></td>
                                <td><span class="small text-dark"><?= !empty($k['no_ktp']) && $k['no_ktp'] != '-' ? htmlspecialchars($k['no_ktp']) : '<span class="text-muted">-</span>' ?></span></td>
                                <td>
                                    <?php if (!empty($k['no_whatsapp']) && $k['no_whatsapp'] != '-'): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $k['no_whatsapp']) ?>" target="_blank" class="text-decoration-none text-success fw-medium">
                                            <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($k['no_whatsapp']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $bi_status = $k['bi_status'] ?? null;
                                    if ($bi_status == 'Lulus') { $bi_badge = 'bg-success text-white'; $bi_ikon = 'bi-check-circle'; }
                                    elseif ($bi_status == 'Dalam Proses') { $bi_badge = 'bg-warning text-dark'; $bi_ikon = 'bi-hourglass-split'; }
                                    elseif ($bi_status == 'Dipertimbangkan') { $bi_badge = 'bg-info text-dark'; $bi_ikon = 'bi-question-circle'; }
                                    elseif ($bi_status == 'Tidak Lulus') { $bi_badge = 'bg-danger text-white'; $bi_ikon = 'bi-x-circle'; }
                                    else { $bi_badge = 'bg-light text-secondary border'; $bi_ikon = 'bi-dash-circle'; }
                                    ?>
                                    <span class="badge <?= $bi_badge ?> px-2 py-1"><i class="bi <?= $bi_ikon ?> me-1"></i><?= $bi_status ? htmlspecialchars($bi_status) : 'Belum Dicek' ?></span>
                                    <?php if (!empty($k['bi_tgl']) && $k['bi_tgl'] != '0000-00-00'): ?>
                                        <div class="small text-muted mt-1"><?= date('d/m/Y', strtotime($k['bi_tgl'])) ?></div>
                                    <?php endif; ?>
                                    <a href="bi_checking.php?konsumen=<?= $k['id_konsumen'] ?>" class="small text-success text-decoration-none fw-medium d-inline-block mt-1">
                                        <i class="bi bi-clipboard-plus me-1"></i>Cek BI
                                    </a>
                                </td>
                                <td>
                                    <?php $tx = konsumen_status_badge_data($k['status_transaksi'] ?? null); ?>
                                    <span class="badge <?= $tx['bg'] ?> px-2 py-1"><i class="bi <?= $tx['ikon'] ?> me-1"></i><?= htmlspecialchars($tx['label']) ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-1">
                                        <!-- Tombol Trigger Modal Edit -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEditKonsumen<?= $k['id_konsumen'] ?>" title="Edit Konsumen">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Tombol Trigger Modal Hapus -->
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalHapusKonsumen" data-id="<?= $k['id_konsumen'] ?>" data-nama="<?= htmlspecialchars($k['name_lengkap']) ?>" title="Hapus Konsumen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <?php
                                    ob_start();
                                    ?>
                                    <!-- MODAL EDIT PER-KONSUMEN -->
                                    <div class="modal fade" id="modalEditKonsumen<?= $k['id_konsumen'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start">
                                            <div class="modal-content border-0 rounded-4 shadow">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title fw-bold">Edit Data Konsumen</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="konsumen.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id_konsumen" value="<?= $k['id_konsumen'] ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Nama Lengkap</label>
                                                            <input type="text" name="name_lengkap" class="form-control rounded-3" value="<?= htmlspecialchars($k['name_lengkap']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">No. KTP</label>
                                                            <input type="text" name="no_ktp" class="form-control rounded-3" value="<?= htmlspecialchars($k['no_ktp'] ?? '') ?>" placeholder="Contoh: 6101011811940001">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Pekerjaan</label>
                                                            <input type="text" name="pekerjaan" class="form-control rounded-3" value="<?= htmlspecialchars($k['pekerjaan']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Alamat KTP</label>
                                                            <textarea name="alamat_ktp" class="form-control rounded-3" rows="2" placeholder="Contoh: Dusun Sukamantri Rt 011 Rw 003, Desa Dalam Kaum, Kec. Sambas"><?= htmlspecialchars($k['alamat_ktp'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">No. WhatsApp / HP</label>
                                                            <input type="text" name="no_whatsapp" class="form-control rounded-3" value="<?= htmlspecialchars($k['no_whatsapp']) ?>">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit_konsumen" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $modalEditHTML .= ob_get_clean();
                                    ?>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center text-muted py-4'>Tidak ada data konsumen yang sesuai dengan kriteria filter.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- MODAL EDIT MASSAL -->
                <div class="modal fade" id="modalEditMassal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 rounded-4 shadow">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold">Edit Pekerjaan Konsumen Massal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-start py-3">
                                <p class="small text-muted mb-3">Pembaruan ini akan diterapkan pada semua konsumen yang dicentang.</p>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Ubah Pekerjaan Menjadi</label>
                                    <input type="text" name="pekerjaan_massal" class="form-control rounded-3" placeholder="Contoh: Wirausaha / PNS / Karyawan Swasta" required>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="edit_massal" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Terapkan Semua</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?= $modalEditHTML ?>
    </main>
</div>

<!-- MODAL TAMBAH KONSUMEN -->
<div class="modal fade" id="modalKonsumen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Tambah Konsumen Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="konsumen.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                        <input type="text" name="name_lengkap" class="form-control rounded-3" required placeholder="Contoh: Ahmad Yani">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. KTP</label>
                        <input type="text" name="no_ktp" class="form-control rounded-3" placeholder="Contoh: 6101011811940001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pekerjaan</label>
                        <input type="text" name="pekerjaan" class="form-control rounded-3" required placeholder="Contoh: Karyawan Swasta / PNS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat KTP</label>
                        <textarea name="alamat_ktp" class="form-control rounded-3" rows="2" placeholder="Contoh: Dusun Sukamantri Rt 011 Rw 003, Desa Dalam Kaum, Kec. Sambas"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. WhatsApp / HP</label>
                        <input type="text" name="no_whatsapp" class="form-control rounded-3" placeholder="Contoh: 08123456789">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan_konsumen" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS KONSUMEN -->
<div class="modal fade" id="modalHapusKonsumen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Konsumen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-person-x text-danger display-4 mb-3 d-block"></i>
                <h6 class="fw-bold fs-5">Apakah Anda yakin ingin menghapus data konsumen ini?</h6>
                <p class="text-muted small mb-0" id="infoNamaKonsumenHapus">-</p>
            </div>
            <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnConfirmDeleteKonsumen" class="btn btn-danger px-4 rounded-3">Ya, Hapus Data</a>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT CHECKBOX MASSAL & HANDLER MODAL HAPUS -->
<script>
function toggleSelectAll(source) {
    let checkboxes = document.querySelectorAll('.konsumen-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = source.checked;
        }
    });
    updateMassalBtn();
}

function updateMassalBtn() {
    let checkedCount = document.querySelectorAll('.konsumen-checkbox:checked').length;
    let btnEditMassal = document.getElementById('btnEditMassal');
    let selectedCountSpan = document.getElementById('selectedCount');

    if (checkedCount > 0) {
        btnEditMassal.classList.remove('d-none');
        selectedCountSpan.textContent = checkedCount;
    } else {
        btnEditMassal.classList.add('d-none');
        selectedCountSpan.textContent = 0;
    }
}

// Handler Oper Parameter ke Modal Hapus
document.addEventListener('DOMContentLoaded', function () {
    const modalHapusKonsumen = document.getElementById('modalHapusKonsumen');
    if (modalHapusKonsumen) {
        modalHapusKonsumen.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nama = button.getAttribute('data-nama');

            document.getElementById('infoNamaKonsumenHapus').innerText = 'Nama: ' + nama + ' (#KNS-' + id + ')';
            document.getElementById('btnConfirmDeleteKonsumen').href = 'konsumen.php?action=hapus&id=' + id + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }

    // Export PDF & Excel membawa filter aktif
    const formFilter = document.getElementById('formFilterKonsumen');
    function qsFilterAktif() {
        if (!formFilter) return '';
        const params = new URLSearchParams(new FormData(formFilter));
        // buang field kosong
        [...params.keys()].forEach(k => { if (params.get(k) === '') params.delete(k); });
        const s = params.toString();
        return s ? ('?' + s) : '';
    }
    const btnPdf = document.getElementById('btnExportPdfKonsumen');
    if (btnPdf) {
        btnPdf.addEventListener('click', function () {
            window.open('print/cetak_konsumen.php' + qsFilterAktif(), '_blank');
        });
    }
    const btnExcel = document.getElementById('btnExportExcelKonsumen');
    if (btnExcel) {
        btnExcel.addEventListener('click', function () {
            window.open('print/export_konsumen.php' + qsFilterAktif(), '_blank');
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
