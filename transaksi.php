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

// ==========================================
// 1. LOGIKA HAPUS / BATAL TRANSAKSI
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_transaksi = intval($_GET['id']);

    // Step A: Cari id_rumah dari transaksi yang akan dihapus
    $get_rumah = mysqli_query($koneksi, "SELECT id_rumah FROM data_transaksi WHERE id_transaksi = $id_transaksi");
    $data = mysqli_fetch_assoc($get_rumah);

    if ($data) {
        $id_rumah = $data['id_rumah'];

        // Step B: Hapus data transaksi
        $delete_query = "DELETE FROM data_transaksi WHERE id_transaksi = $id_transaksi";
        if (mysqli_query($koneksi, $delete_query)) {
            
            // Step C: Kembalikan status unit rumah menjadi 'TERSEDIA'
            if (!empty($id_rumah)) {
                mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERSEDIA' WHERE id_rumah = $id_rumah");
            }

            header("Location: transaksi.php?status=deleted");
            exit();
        } else {
            echo "<script>alert('Gagal menghapus transaksi: " . mysqli_error($koneksi) . "');</script>";
        }
    }
}

// ==========================================
// 2. LOGIKA SIMPAN TRANSAKSI BARU
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan_transaksi']) || isset($_POST['simpan']))) {
    $tgl_booking     = mysqli_real_escape_string($koneksi, $_POST['tgl_booking']);
    $id_konsumen     = intval($_POST['id_konsumen']);
    $id_rumah        = intval($_POST['id_rumah']);
    $skema_bayar     = mysqli_real_escape_string($koneksi, $_POST['skema_bayar']);
    $skema_akad      = mysqli_real_escape_string($koneksi, $_POST['skema_akad'] ?? '');
    $status_konsumen = mysqli_real_escape_string($koneksi, $_POST['status_konsumen']);
    
    $tgl_akad_input  = $_POST['tanggal_akad'] ?? $_POST['tgl_akad'] ?? '';
    $tgl_akad_val    = !empty($tgl_akad_input) ? "'".mysqli_real_escape_string($koneksi, $tgl_akad_input)."'" : "NULL";
    $skema_akad_sql  = !empty($skema_akad) ? "'$skema_akad'" : "NULL";

    $sql_transaksi = "INSERT INTO data_transaksi (tgl_booking, id_konsumen, id_rumah, skema_bayar, skema_akad, status_konsumen, tanggal_akad) 
                      VALUES ('$tgl_booking', $id_konsumen, $id_rumah, '$skema_bayar', $skema_akad_sql, '$status_konsumen', $tgl_akad_val)";

    if (mysqli_query($koneksi, $sql_transaksi)) {
        // Cek status konsumen: Jika BATAL maka status unit TERSEDIA, selain itu TERBOOKING
        if ($status_konsumen === 'BATAL') {
            mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERSEDIA' WHERE id_rumah = $id_rumah");
        } else {
            mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERBOOKING' WHERE id_rumah = $id_rumah");
        }

        header("Location: transaksi.php?status=success");
        exit();
    } else {
        echo "<script>alert('Gagal menyimpan transaksi: " . mysqli_error($koneksi) . "');</script>";
    }
}

// ==========================================
// 3. LOGIKA EDIT TRANSAKSI
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaksi'])) {
    $id_transaksi    = intval($_POST['id_transaksi']);
    $tgl_booking     = mysqli_real_escape_string($koneksi, $_POST['tgl_booking']);
    $id_konsumen     = intval($_POST['id_konsumen']);
    $id_rumah_baru   = intval($_POST['id_rumah']);
    $skema_bayar     = mysqli_real_escape_string($koneksi, $_POST['skema_bayar']);
    $skema_akad      = mysqli_real_escape_string($koneksi, $_POST['skema_akad'] ?? '');
    $status_konsumen = mysqli_real_escape_string($koneksi, $_POST['status_konsumen']);
    
    $tgl_akad_input  = $_POST['tanggal_akad'] ?? $_POST['tgl_akad'] ?? '';
    $tgl_akad_val    = !empty($tgl_akad_input) ? "'".mysqli_real_escape_string($koneksi, $tgl_akad_input)."'" : "NULL";
    $skema_akad_sql  = !empty($skema_akad) ? "'$skema_akad'" : "NULL";

    // Step A: Dapatkan id_rumah lama dari transaksi ini
    $get_rumah = mysqli_query($koneksi, "SELECT id_rumah FROM data_transaksi WHERE id_transaksi = $id_transaksi");
    $data_r = mysqli_fetch_assoc($get_rumah);
    $id_rumah_lama = $data_r['id_rumah'] ?? 0;

    $update_query = "UPDATE data_transaksi SET 
                     tgl_booking = '$tgl_booking',
                     id_konsumen = $id_konsumen,
                     id_rumah = $id_rumah_baru,
                     skema_bayar = '$skema_bayar',
                     skema_akad = $skema_akad_sql,
                     status_konsumen = '$status_konsumen',
                     tanggal_akad = $tgl_akad_val
                     WHERE id_transaksi = $id_transaksi";

    if (mysqli_query($koneksi, $update_query)) {
        // Kembalikan unit lama ke TERSEDIA jika beda unit
        if ($id_rumah_lama > 0 && $id_rumah_lama != $id_rumah_baru) {
            mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERSEDIA' WHERE id_rumah = $id_rumah_lama");
        }

        // Update status unit baru
        if ($id_rumah_baru > 0) {
            if ($status_konsumen === 'BATAL') {
                mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERSEDIA' WHERE id_rumah = $id_rumah_baru");
            } elseif ($status_konsumen === 'ACC - AKAD') {
                mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERJUAL' WHERE id_rumah = $id_rumah_baru");
            } else {
                mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERBOOKING' WHERE id_rumah = $id_rumah_baru");
            }
        }

        header("Location: transaksi.php?status=updated");
        exit();
    } else {
        echo "<script>alert('Gagal mengupdate transaksi: " . mysqli_error($koneksi) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">    <title>Data Transaksi - Shankara Dev</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        .table-shankara {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-shankara th {
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            padding: 14px 18px;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-shankara td {
            padding: 14px 18px;
            font-size: 0.875rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-shankara tr:last-child td {
            border-bottom: none;
        }

        .tr-filtered {
            display: none;
        }
    </style>
</head>
<body>
    <input type="hidden" id="csrf_global" value="<?= htmlspecialchars(csrf_token()) ?>">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <!-- KONTEN UTAMA FULL WIDTH -->
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Riwayat & Input Transaksi</h4>
                <p class="text-muted small mb-0">Kelola booking, status akad, dan skema pembiayaan konsumen.</p>
            </div>
            <button class="btn btn-green" style="color:#fff !important" data-bs-toggle="modal" data-bs-target="#modalTransaksi">
                <i class="bi bi-cart-plus me-1"></i> Input Transaksi
            </button>
        </div>

        <!-- TABEL FULL WIDTH -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span id="filterCounter" class="text-muted small fw-semibold">Memuat data...</span>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetFilter">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Filter
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-shankara align-middle mb-0" id="tabelTransaksi">
                    <thead>
                        <tr>
                            <th>Tgl Booking</th>
                            <th>Nama Konsumen</th>
                            <th>Unit Rumah</th>
                            <th>Skema Bayar</th>
                            <th>Status Konsumen</th>
                            <th>Tgl Akad</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                        <tr class="filters-row">
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="0" list="dlTglBooking" placeholder="dd/mm/yyyy">
                                <datalist id="dlTglBooking"></datalist>
                            </th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="1" list="dlNama" placeholder="Cari nama...">
                                <datalist id="dlNama"></datalist>
                            </th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="2" list="dlUnit" placeholder="Cari unit / blok...">
                                <datalist id="dlUnit"></datalist>
                            </th>
                            <th>
                                <select class="form-select form-select-sm" data-col="3">
                                    <option value="">Semua Skema</option>
                                </select>
                            </th>
                            <th>
                                <select class="form-select form-select-sm" data-col="4">
                                    <option value="">Semua Status</option>
                                </select>
                            </th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="5" list="dlTglAkad" placeholder="dd/mm/yyyy">
                                <datalist id="dlTglAkad"></datalist>
                            </th>
                            <th class="text-center" style="min-width:80px;">
                                <span class="text-muted small">-</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q_trx = mysqli_query($koneksi, "SELECT t.*, 
                                                               COALESCE(k.name_lengkap) AS konsumen_nama,
                                                               r.no_blok,
                                                               c.cluster_name
                                                         FROM data_transaksi t
                                                         LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                                         LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                                         LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                                         ORDER BY t.tgl_booking DESC");

                        // Pre-load data untuk dropdown edit modal
                        $arr_konsumen = [];
                        $qk = mysqli_query($koneksi, "SELECT id_konsumen, name_lengkap, pekerjaan FROM data_konsumen ORDER BY name_lengkap ASC");
                        while ($row = mysqli_fetch_assoc($qk)) $arr_konsumen[] = $row;

                        $arr_rumah = [];
                        $qr = mysqli_query($koneksi, "SELECT r.id_rumah, r.no_blok, r.tipe_rumah, r.status_unit, c.cluster_name 
                                                      FROM data_rumah r JOIN data_cluster c ON r.id_cluster = c.id_cluster 
                                                      ORDER BY c.cluster_name, r.no_blok ASC");
                        while ($row = mysqli_fetch_assoc($qr)) $arr_rumah[] = $row;

                        while ($t = mysqli_fetch_assoc($q_trx)) {
                            // Formatting Status Badge
                            $status = $t['status_konsumen'];
                            $badge_status = 'bg-warning text-dark';
                            if ($status == 'ACC - AKAD') {
                                $badge_status = 'bg-success text-white';
                            } elseif ($status == 'BATAL') {
                                $badge_status = 'bg-danger text-white';
                            }

                            // Handling Tanggal Akad
                            $tgl_akad = '-';
                            if (!empty($t['tanggal_akad']) && $t['tanggal_akad'] != '0000-00-00') {
                                $tgl_akad = date('d/m/Y', strtotime($t['tanggal_akad']));
                            }
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($t['tgl_booking'])) ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($t['konsumen_nama'] ?? 'Tanpa Nama') ?></td>
                            <td><?= htmlspecialchars($t['cluster_name'] ?? 'Shankara Residence') ?> - <strong>Blok <?= htmlspecialchars($t['no_blok']) ?></strong></td>
                            <td><span class="badge bg-light text-secondary border px-2 py-1"><?= htmlspecialchars($t['skema_bayar']) ?></span></td>
                            <td><span class="badge <?= $badge_status ?> px-2 py-1"><?= htmlspecialchars($t['status_konsumen']) ?></span></td>
                            <td><?= $tgl_akad ?></td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <!-- TOMBOL EDIT -->
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-warning text-dark" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditTransaksi<?= $t['id_transaksi']; ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <!-- TOMBOL HAPUS -->
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalHapusTransaksi"
                                            data-id="<?= $t['id_transaksi']; ?>"
                                            data-info="<?= htmlspecialchars($t['konsumen_nama'] ?? 'Konsumen') ?> (Blok <?= htmlspecialchars($t['no_blok']) ?>)">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>

                                <!-- MODAL EDIT TRANSAKSI -->
                                <div class="modal fade text-start" id="modalEditTransaksi<?= $t['id_transaksi']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                                            <div class="modal-header text-white" style="background-color: var(--primary-blue);">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Transaksi</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="transaksi.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_transaksi" value="<?= $t['id_transaksi']; ?>">
                                                <div class="modal-body p-4" style="background-color: #f8f9fa;">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">TANGGAL BOOKING</label>
                                                            <input type="date" name="tgl_booking" class="form-control border-0 shadow-sm" style="border-radius: 8px;" value="<?= $t['tgl_booking'] ?>" required>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">PILIH KONSUMEN</label>
                                                            <select name="id_konsumen" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                                                <option value="">-- Pilih Konsumen --</option>
                                                                <?php foreach ($arr_konsumen as $k): ?>
                                                                    <?php $sel = ($k['id_konsumen'] == $t['id_konsumen']) ? 'selected' : ''; ?>
                                                                    <option value="<?= $k['id_konsumen'] ?>" $sel><?= htmlspecialchars($k['name_lengkap']) ?> - (<?= htmlspecialchars($k['pekerjaan']) ?>)</option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-12">
                                                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">PILIH RUMAH / UNIT</label>
                                                            <select name="id_rumah" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                                                <option value="">-- Pilih Unit Rumah --</option>
                                                                <?php foreach ($arr_rumah as $r): ?>
                                                                    <?php $sel = ($r['id_rumah'] == $t['id_rumah']) ? 'selected' : ''; ?>
                                                                    <option value="<?= $r['id_rumah'] ?>" $sel><?= htmlspecialchars($r['cluster_name']) ?> - Blok <?= htmlspecialchars($r['no_blok']) ?> (Tipe <?= htmlspecialchars($r['tipe_rumah']) ?>) - Status: <?= strtoupper($r['status_unit']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">SKEMA BAYAR</label>
                                                            <select name="skema_bayar" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                                                <option value="KPR Subsidi" <?= ($t['skema_bayar'] == 'KPR Subsidi') ? 'selected' : ''; ?>>KPR Subsidi</option>
                                                                <option value="KPR Komersil" <?= ($t['skema_bayar'] == 'KPR Komersil') ? 'selected' : ''; ?>>KPR Komersil</option>
                                                                <option value="Cash Bertahap" <?= ($t['skema_bayar'] == 'Cash Bertahap') ? 'selected' : ''; ?>>Cash Bertahap</option>
                                                                <option value="Cash Keras" <?= ($t['skema_bayar'] == 'Cash Keras') ? 'selected' : ''; ?>>Cash Keras</option>
                                                                <option value="Cash Tempo" <?= ($t['skema_bayar'] == 'Cash Tempo') ? 'selected' : ''; ?>>Cash Tempo</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">SKEMA AKAD (OPSIONAL)</label>
                                                            <?php $skema_akad_val = $t['skema_akad'] ?? ''; ?>
                                                            <select name="skema_akad" class="form-select border-0 shadow-sm" style="border-radius: 8px;">
                                                                <option value="">-- Pilih Skema Akad --</option>
                                                                <option value="Cash" <?= ($skema_akad_val == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                                                <option value="Cash Tempo (Akad Developer)" <?= ($skema_akad_val == 'Cash Tempo (Akad Developer)') ? 'selected' : ''; ?>>Cash Tempo (Akad Developer)</option>
                                                                <option value="KPR Bank Syariah" <?= ($skema_akad_val == 'KPR Bank Syariah') ? 'selected' : ''; ?>>KPR Bank Syariah</option>
                                                                <option value="KPR Bank Konvensional" <?= ($skema_akad_val == 'KPR Bank Konvensional') ? 'selected' : ''; ?>>KPR Bank Konvensional</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">STATUS KONSUMEN</label>
                                                            <select name="status_konsumen" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                                                <option value="PROSES" <?= ($t['status_konsumen'] == 'PROSES' || $t['status_konsumen'] == 'BOOKING') ? 'selected' : ''; ?>>PROSES</option>
                                                                <option value="ACC - AKAD" <?= ($t['status_konsumen'] == 'ACC - AKAD') ? 'selected' : ''; ?>>ACC - AKAD</option>
                                                                <option value="BATAL" <?= ($t['status_konsumen'] == 'BATAL') ? 'selected' : ''; ?>>BATAL</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-12">
                                                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">TANGGAL AKAD (OPSIONAL)</label>
                                                            <input type="date" name="tanggal_akad" class="form-control border-0 shadow-sm" style="border-radius: 8px;" value="<?= htmlspecialchars($t['tanggal_akad'] ?? ''); ?>">
                                                            <small class="text-muted">Kosongkan jika belum akad.</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-white border-0 px-4 py-3">
                                                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                                                    <button type="submit" name="edit_transaksi" class="btn text-white px-4" style="background-color: #2d6a4f; border-radius: 8px;">
                                                        <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                                                    </button>
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
                <div id="emptyFilterRow" class="text-center text-muted py-5 d-none">
                    <i class="bi bi-search display-5 d-block mb-2 text-secondary"></i>
                    Tidak ada transaksi yang cocok dengan filter.
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal fade" id="modalHapusTransaksi" tabindex="-1" aria-labelledby="modalHapusLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalHapusLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash text-danger display-3 mb-3 d-block"></i>
                <h5 class="fw-bold">Apakah Anda yakin ingin menghapus transaksi ini?</h5>
                <p class="text-muted mb-3" id="infoTransaksiHapus">-</p>
                <div class="alert alert-warning text-start fs-7 mb-0" role="alert">
                    <i class="bi bi-info-circle me-1"></i> Status unit rumah akan otomatis dikembalikan menjadi <strong class="text-success">TERSEDIA</strong>.
                </div>
            </div>
            <div class="modal-footer justify-content-center bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Tidak, Batal
                </button>
                <a href="#" id="btnConfirmDelete" class="btn btn-danger px-4">
                    <i class="bi bi-check-circle me-1"></i> Ya, Hapus
                </a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH TRANSAKSI -->
<div class="modal fade" id="modalTransaksi" tabindex="-1" aria-labelledby="modalTransaksiLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: var(--primary-blue);">
                <h5 class="modal-title fw-bold" id="modalTransaksiLabel">
                    <i class="bi bi-cart-plus me-2"></i>Tambah Transaksi Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="transaksi.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4" style="background-color: #f8f9fa;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">TANGGAL BOOKING</label>
                            <input type="date" name="tgl_booking" class="form-control border-0 shadow-sm" style="border-radius: 8px;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">PILIH KONSUMEN</label>
                            <select name="id_konsumen" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                <option value="">-- Pilih Konsumen --</option>
                                <?php foreach ($arr_konsumen as $k): ?>
                                    <option value="<?= $k['id_konsumen'] ?>"><?= htmlspecialchars($k['name_lengkap']) ?> - (<?= htmlspecialchars($k['pekerjaan']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">PILIH RUMAH / UNIT</label>
                            <select name="id_rumah" id="select_rumah" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required onchange="cekStatusUnit(this)">
                                <option value="" data-status="" data-pemesan="">-- Pilih Unit Rumah --</option>
                                <?php
                                $query_rumah = "SELECT r.*, c.cluster_name, k.name_lengkap AS pemesan 
                                                FROM data_rumah r 
                                                JOIN data_cluster c ON r.id_cluster = c.id_cluster 
                                                LEFT JOIN data_transaksi t ON r.id_rumah = t.id_rumah AND t.status_konsumen IN ('PROSES', 'ACC - AKAD')
                                                LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                                ORDER BY c.cluster_name, r.no_blok ASC";
                                
                                $rumah = mysqli_query($koneksi, $query_rumah);
                                while ($r = mysqli_fetch_assoc($rumah)) {
                                    $status = strtolower($r['status_unit']);
                                    $pemesan = !empty($r['pemesan']) ? $r['pemesan'] : 'Konsumen Lain';
                                    echo "<option value='".$r['id_rumah']."' data-status='".$status."' data-pemesan='".$pemesan."'>";
                                    echo $r['cluster_name']." - Blok ".$r['no_blok']." (Tipe ".$r['tipe_rumah'].") - Status: ".strtoupper($r['status_unit']);
                                    echo "</option>";
                                }
                                ?>
                            </select>
                            
                            <div id="statusAlert" class="alert border-0 mt-2 shadow-sm d-none" role="alert" style="border-radius: 8px;">
                                <i id="alertIcon" class="bi me-2"></i>
                                <span id="alertMessage"></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">SKEMA BAYAR</label>
                            <select name="skema_bayar" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                <option value="KPR Subsidi">KPR Subsidi</option>
                                <option value="KPR Komersil">KPR Komersil</option>
                                <option value="Cash Bertahap">Cash Bertahap</option>
                                <option value="Cash Keras">Cash Keras</option>
                                <option value="Cash Tempo">Cash Tempo</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">SKEMA AKAD (OPSIONAL)</label>
                            <select name="skema_akad" class="form-select border-0 shadow-sm" style="border-radius: 8px;">
                                <option value="">-- Pilih Skema Akad --</option>
                                <option value="Cash">Cash</option>
                                <option value="Cash Tempo (Akad Developer)">Cash Tempo (Akad Developer)</option>
                                <option value="KPR Bank Syariah">KPR Bank Syariah</option>
                                <option value="KPR Bank Konvensional">KPR Bank Konvensional</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">STATUS KONSUMEN</label>
                            <select name="status_konsumen" class="form-select border-0 shadow-sm" style="border-radius: 8px;" required>
                                <option value="PROSES">PROSES</option>
                                <option value="ACC - AKAD">ACC - AKAD</option>
                                <option value="BATAL">BATAL</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">TANGGAL AKAD (OPSIONAL)</label>
                            <input type="date" name="tanggal_akad" class="form-control border-0 shadow-sm" style="border-radius: 8px;">
                        </div>

                    </div>
                </div>
                
                <div class="modal-footer bg-white border-0 px-4 py-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                    <button type="submit" name="simpan_transaksi" class="btn text-white px-4" style="background-color: #2d6a4f; border-radius: 8px;">
                        <i class="bi bi-check-lg me-1"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cekStatusUnit(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const status = selectedOption.getAttribute('data-status');
    const pemesan = selectedOption.getAttribute('data-pemesan');
    
    const alertBox = document.getElementById('statusAlert');
    const alertIcon = document.getElementById('alertIcon');
    const alertMessage = document.getElementById('alertMessage');
    
    alertBox.className = 'alert border-0 mt-2 shadow-sm';
    
    if (status === 'terbooking') {
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

document.addEventListener('DOMContentLoaded', function () {
    const modalHapus = document.getElementById('modalHapusTransaksi');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const idTransaksi = button.getAttribute('data-id');
            const infoTransaksi = button.getAttribute('data-info');
            
            document.getElementById('infoTransaksiHapus').innerText = 'Transaksi: ' + infoTransaksi;
            document.getElementById('btnConfirmDelete').href = 'transaksi.php?action=hapus&id=' + idTransaksi + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }

    // ==========================================
    // FILTER CERDAS PER KOLOM
    // ==========================================
    const table = document.getElementById('tabelTransaksi');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const filters = Array.from(table.querySelectorAll('.filters-row input, .filters-row select'));
    const counter = document.getElementById('filterCounter');
    const emptyRow = document.getElementById('emptyFilterRow');

    const cellText = (row, col) => {
        const cell = row.cells[col];
        return cell ? cell.textContent.trim().toLowerCase() : '';
    };

    const distinctValues = (col) => {
        const seen = new Set();
        rows.forEach(r => {
            const v = cellText(r, col);
            if (v) seen.add(v);
        });
        return Array.from(seen).sort();
    };

    // Isi otomatis dropdown (Skema = col 3, Status = col 4)
    filters.filter(el => el.tagName === 'SELECT').forEach(sel => {
        const col = parseInt(sel.dataset.col);
        distinctValues(col).forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            sel.appendChild(opt);
        });
    });

    // Isi otomatis datalist (autocomplete) untuk kolom teks
    const fillDatalist = (col, listId) => {
        const dl = document.getElementById(listId);
        if (!dl) return;
        distinctValues(col).forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            dl.appendChild(opt);
        });
    };
    fillDatalist(0, 'dlTglBooking');
    fillDatalist(1, 'dlNama');
    fillDatalist(2, 'dlUnit');
    fillDatalist(5, 'dlTglAkad');

    const applyFilters = () => {
        let shown = 0;
        rows.forEach(row => {
            let show = true;
            filters.forEach(el => {
                if (!show) return;
                const q = el.value.trim().toLowerCase();
                if (!q) return;
                const col = parseInt(el.dataset.col);
                const val = cellText(row, col);
                if (el.tagName === 'SELECT') {
                    if (val !== q) show = false;
                } else {
                    if (val.indexOf(q) === -1) show = false;
                }
            });
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        if (counter) counter.textContent = 'Menampilkan ' + shown + ' dari ' + rows.length + ' transaksi';
        if (emptyRow) emptyRow.classList.toggle('d-none', shown !== 0);
    };

    filters.forEach(el => {
        el.addEventListener('input', applyFilters);
        el.addEventListener('change', applyFilters);
    });

    const btnReset = document.getElementById('btnResetFilter');
    if (btnReset) {
        btnReset.addEventListener('click', () => {
            filters.forEach(el => { el.value = ''; });
            applyFilters();
        });
    }

    applyFilters();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
