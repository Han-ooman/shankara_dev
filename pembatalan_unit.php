<?php

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";

include "includes/koneksi.php";
require_once __DIR__ . '/includes/helper_log.php';

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// ===== TAMBAH SURAT PEMBATALAN =====
if (isset($_POST['simpan_pembatalan'])) {
    $id_transaksi       = intval($_POST['id_transaksi']);
    $no_surat           = mysqli_real_escape_string($koneksi, trim($_POST['no_surat']));
    $tgl_surat          = mysqli_real_escape_string($koneksi, $_POST['tgl_surat']);
    $alasan_pembatalan  = mysqli_real_escape_string($koneksi, trim($_POST['alasan_pembatalan'] ?? ''));
    $booking_fee        = max(0, (int) str_replace(['.', ' '], '', $_POST['booking_fee'] ?? '0'));
    $rekening_nama      = mysqli_real_escape_string($koneksi, trim($_POST['rekening_nama'] ?? ''));
    $rekening_bank      = mysqli_real_escape_string($koneksi, trim($_POST['rekening_bank'] ?? ''));
    $rekening_no        = mysqli_real_escape_string($koneksi, trim($_POST['rekening_no'] ?? ''));
    $catatan            = mysqli_real_escape_string($koneksi, trim($_POST['catatan'] ?? ''));

    // Cegah duplikasi untuk transaksi yang sama yang belum dibatalkan
    $cek = mysqli_query($koneksi, "SELECT id_pembatalan FROM data_pembatalan WHERE id_transaksi = $id_transaksi AND status_pembatalan = 'Disetujui'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        header("Location: pembatalan_unit.php?pesan=duplicate");
        exit();
    }

    mysqli_query($koneksi, "INSERT INTO data_pembatalan (id_transaksi, no_surat, tgl_surat, alasan_pembatalan, booking_fee, rekening_nama, rekening_bank, rekening_no, catatan)
                            VALUES ($id_transaksi, '$no_surat', '$tgl_surat', '$alasan_pembatalan', $booking_fee, '$rekening_nama', '$rekening_bank', '$rekening_no', '$catatan')");
    log_aktivitas($koneksi, 'TAMBAH', 'Pembatalan Unit', 'Tambah surat pembatalan unit: ' . $no_surat);
    header("Location: pembatalan_unit.php?pesan=simpan");
    exit();
}

// ===== EDIT SURAT PEMBATALAN =====
if (isset($_POST['edit_pembatalan'])) {
    $id_pembatalan      = intval($_POST['id_pembatalan']);
    $id_transaksi       = intval($_POST['id_transaksi']);
    $no_surat           = mysqli_real_escape_string($koneksi, trim($_POST['no_surat']));
    $tgl_surat          = mysqli_real_escape_string($koneksi, $_POST['tgl_surat']);
    $alasan_pembatalan  = mysqli_real_escape_string($koneksi, trim($_POST['alasan_pembatalan'] ?? ''));
    $booking_fee        = max(0, (int) str_replace(['.', ' '], '', $_POST['booking_fee'] ?? '0'));
    $rekening_nama      = mysqli_real_escape_string($koneksi, trim($_POST['rekening_nama'] ?? ''));
    $rekening_bank      = mysqli_real_escape_string($koneksi, trim($_POST['rekening_bank'] ?? ''));
    $rekening_no        = mysqli_real_escape_string($koneksi, trim($_POST['rekening_no'] ?? ''));
    $catatan            = mysqli_real_escape_string($koneksi, trim($_POST['catatan'] ?? ''));

    // Hanya surat berstatus Draft yang bisa diedit
    mysqli_query($koneksi, "UPDATE data_pembatalan SET
                            id_transaksi = $id_transaksi,
                            no_surat = '$no_surat',
                            tgl_surat = '$tgl_surat',
                            alasan_pembatalan = '$alasan_pembatalan',
                            booking_fee = $booking_fee,
                            rekening_nama = '$rekening_nama',
                            rekening_bank = '$rekening_bank',
                            rekening_no = '$rekening_no',
                            catatan = '$catatan'
                            WHERE id_pembatalan = $id_pembatalan AND status_pembatalan = 'Draft'");
    log_aktivitas($koneksi, 'UBAH', 'Pembatalan Unit', 'Ubah surat pembatalan unit: ' . $no_surat);
    header("Location: pembatalan_unit.php?pesan=simpan");
    exit();
}

// ===== SETUJUI SURAT PEMBATALAN (otomatis unit kembali TERSEDIA & transaksi BATAL) =====
if (isset($_POST['setujui_pembatalan'])) {
    $id_pembatalan = intval($_POST['id_pembatalan']);

    $q = mysqli_query($koneksi, "SELECT p.*, t.id_rumah, t.id_konsumen FROM data_pembatalan p
                                 LEFT JOIN data_transaksi t ON p.id_transaksi = t.id_transaksi
                                 WHERE p.id_pembatalan = $id_pembatalan AND p.status_pembatalan = 'Draft'");
    $row = $q ? mysqli_fetch_assoc($q) : null;

    if ($row) {
        mysqli_query($koneksi, "UPDATE data_pembatalan SET status_pembatalan = 'Disetujui' WHERE id_pembatalan = $id_pembatalan");
        // Unit kembali tersedia
        if (!empty($row['id_rumah'])) {
            mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERSEDIA' WHERE id_rumah = " . intval($row['id_rumah']));
        }
        // Transaksi ditandai BATAL
        if (!empty($row['id_transaksi'])) {
            mysqli_query($koneksi, "UPDATE data_transaksi SET status_konsumen = 'BATAL' WHERE id_transaksi = " . intval($row['id_transaksi']));
        }
        log_aktivitas($koneksi, 'UBAH', 'Pembatalan Unit', 'Setujui surat pembatalan unit: ' . $row['no_surat']);
        header("Location: pembatalan_unit.php?pesan=setuju");
        exit();
    }
    header("Location: pembatalan_unit.php?pesan=error");
    exit();
}

// ===== HAPUS SURAT PEMBATALAN =====
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = intval($_GET['id']);
    mysqli_query($koneksi, "DELETE FROM data_pembatalan WHERE id_pembatalan = $id_hapus AND status_pembatalan = 'Draft'");
    log_aktivitas($koneksi, 'HAPUS', 'Pembatalan Unit', 'Hapus surat pembatalan unit ID: ' . $id_hapus);
    header("Location: pembatalan_unit.php?pesan=hapus");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Pembatalan Unit - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <input type="hidden" id="csrf_global" value="<?= htmlspecialchars(csrf_token()) ?>">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Surat Pembatalan Unit</h4>
                <p class="text-muted small mb-0">Pencatatan permohonan pembatalan unit perumahan dan persetujuannya.</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalPembatalan">+ Pembatalan Baru</button>
        </div>

        <?php if (isset($_GET['pesan'])): ?>
            <?php if ($_GET['pesan'] === 'simpan'): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 small" role="alert"><i class="bi bi-check-circle-fill me-1"></i> Data surat pembatalan berhasil disimpan.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php elseif ($_GET['pesan'] === 'setuju'): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 small" role="alert"><i class="bi bi-check-circle-fill me-1"></i> Surat pembatalan disetujui. Unit kembali <strong>TERSEDIA</strong> dan transaksi ditandai <strong>BATAL</strong>.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php elseif ($_GET['pesan'] === 'hapus'): ?>
                <div class="alert alert-secondary alert-dismissible fade show rounded-4 small" role="alert"><i class="bi bi-trash me-1"></i> Surat pembatalan dihapus.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php elseif ($_GET['pesan'] === 'duplicate'): ?>
                <div class="alert alert-warning alert-dismissible fade show rounded-4 small" role="alert"><i class="bi bi-exclamation-triangle-fill me-1"></i> Transaksi tersebut sudah memiliki surat pembatalan yang disetujui.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php else: ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 small" role="alert"><i class="bi bi-x-circle me-1"></i> Aksi tidak dapat diproses. Muat ulang halaman lalu coba lagi.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="text-muted">
                            <th>No. Surat</th>
                            <th>Konsumen</th>
                            <th>Unit</th>
                            <th>Tgl. Surat</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q = mysqli_query($koneksi, "SELECT p.*,
                                                            k.name_lengkap,
                                                            r.no_blok, r.tipe_rumah, r.no_shm,
                                                            t.status_konsumen
                                                     FROM data_pembatalan p
                                                     LEFT JOIN data_transaksi t ON p.id_transaksi = t.id_transaksi
                                                     LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                                     LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                                     ORDER BY p.id_pembatalan DESC");

                        if ($q && mysqli_num_rows($q) > 0) {
                            while ($r = mysqli_fetch_assoc($q)) {
                                $badge = $r['status_pembatalan'] === 'Disetujui' ? 'bg-success' : 'bg-warning text-dark';
                                $draft = $r['status_pembatalan'] === 'Draft';
                        ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($r['no_surat']) ?></td>
                            <td><?= htmlspecialchars($r['name_lengkap'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-dark">Blok <?= htmlspecialchars($r['no_blok'] ?? '-') ?></span>
                                <span class="small text-muted d-block"><?= htmlspecialchars($r['tipe_rumah'] ?? '-') ?> &bull; <?= htmlspecialchars($r['no_shm'] ?? '-') ?></span>
                            </td>
                            <td><?= !empty($r['tgl_surat']) && $r['tgl_surat'] !== '0000-00-00' ? date('d/m/Y', strtotime($r['tgl_surat'])) : '-' ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($r['alasan_pembatalan'] ?? '-') ?></td>
                            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($r['status_pembatalan']) ?></span></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <!-- Cetak -->
                                    <a href="print/cetak_pembatalan.php?id=<?= $r['id_pembatalan'] ?>" target="_blank" class="btn btn-sm btn-outline-primary border-0" title="Cetak / Simpan PDF">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                    <?php if ($draft): ?>
                                        <!-- Setujui -->
                                        <button type="button" class="btn btn-sm btn-outline-success border-0" data-bs-toggle="modal" data-bs-target="#modalSetuju<?= $r['id_pembatalan'] ?>" title="Setujui pembatalan">
                                            <i class="bi bi-check2-circle"></i>
                                        </button>
                                        <!-- Edit -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $r['id_pembatalan'] ?>" title="Edit surat pembatalan">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <!-- Hapus -->
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalHapus" data-id="<?= $r['id_pembatalan'] ?>" data-info="<?= htmlspecialchars($r['no_surat']) ?>" title="Hapus surat pembatalan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($draft): ?>
                                <!-- MODAL SETUJUI -->
                                <div class="modal fade text-start" id="modalSetuju<?= $r['id_pembatalan'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-success text-white rounded-top-4">
                                                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-check2-circle me-2"></i>Setujui Pembatalan</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="pembatalan_unit.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_pembatalan" value="<?= $r['id_pembatalan'] ?>">
                                                <div class="modal-body text-center py-4">
                                                    <i class="bi bi-exclamation-triangle text-warning display-4 mb-3 d-block"></i>
                                                    <h6 class="fw-bold">Setujui surat pembatalan ini?</h6>
                                                    <p class="text-muted small mb-0">
                                                        Unit <strong>Blok <?= htmlspecialchars($r['no_blok'] ?? '-') ?></strong> (<?= htmlspecialchars($r['name_lengkap'] ?? '-') ?>) akan kembali menjadi
                                                        <strong class="text-success">TERSEDIA</strong> dan transaksi ditandai <strong>BATAL</strong>.
                                                    </p>
                                                </div>
                                                <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4">
                                                    <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="setujui_pembatalan" class="btn btn-success px-4 rounded-3">Ya, Setujui</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL EDIT -->
                                <div class="modal fade text-start" id="modalEdit<?= $r['id_pembatalan'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Surat Pembatalan</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="pembatalan_unit.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_pembatalan" value="<?= $r['id_pembatalan'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Pilih Transaksi / Konsumen</label>
                                                        <select name="id_transaksi" class="form-select" required>
                                                            <option value="">-- Pilih Transaksi --</option>
                                                            <?php
                                                            $qt = mysqli_query($koneksi, "SELECT t.id_transaksi, k.name_lengkap, r.no_blok
                                                                                          FROM data_transaksi t
                                                                                          LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                                                                          LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah");
                                                            while ($st = mysqli_fetch_assoc($qt)) {
                                                                $sel2 = ($st['id_transaksi'] == $r['id_transaksi']) ? 'selected' : '';
                                                                echo "<option value='" . $st['id_transaksi'] . "' $sel2>Blok " . ($st['no_blok'] ?? '-') . " - " . ($st['name_lengkap'] ?? '-') . "</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Nomor Surat</label>
                                                            <input type="text" name="no_surat" class="form-control" value="<?= htmlspecialchars($r['no_surat']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Tanggal Surat</label>
                                                            <input type="date" name="tgl_surat" class="form-control" value="<?= $r['tgl_surat'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Alasan Pembatalan</label>
                                                        <textarea name="alasan_pembatalan" class="form-control" rows="2" placeholder="Contoh: calon konsumen mengundurkan diri dua pihak..."><?= htmlspecialchars($r['alasan_pembatalan']) ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Booking Fee (Uang Pemesanan)</label>
                                                        <input type="text" name="booking_fee" class="form-control booking-fee" value="<?= number_format($r['booking_fee'], 0, ',', '.') ?>">
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Rekening Tujuan (Nama)</label>
                                                            <input type="text" name="rekening_nama" class="form-control" value="<?= htmlspecialchars($r['rekening_nama']) ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Bank</label>
                                                            <input type="text" name="rekening_bank" class="form-control" value="<?= htmlspecialchars($r['rekening_bank']) ?>">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">No. Rekening</label>
                                                        <input type="text" name="rekening_no" class="form-control" value="<?= htmlspecialchars($r['rekening_no']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Catatan</label>
                                                        <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan jika ada..."><?= htmlspecialchars($r['catatan']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_pembatalan" class="btn text-white px-4 rounded-3" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center text-muted py-4'>Belum ada surat pembatalan.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash text-danger display-4 mb-3 d-block"></i>
                <h6 class="fw-bold fs-5">Apakah Anda yakin ingin menghapus surat pembatalan ini?</h6>
                <p class="text-muted small mb-0" id="infoPembatalanHapus">-</p>
            </div>
            <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnConfirmDelete" class="btn btn-danger px-4 rounded-3">Ya, Hapus Data</a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalPembatalan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tambah Surat Pembatalan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="pembatalan_unit.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Transaksi / Konsumen</label>
                        <select name="id_transaksi" class="form-select" required>
                            <option value="">-- Pilih Transaksi --</option>
                            <?php
                            $qt = mysqli_query($koneksi, "SELECT t.id_transaksi,
                                                                 k.name_lengkap,
                                                                 r.no_blok,
                                                                 t.status_konsumen
                                                          FROM data_transaksi t
                                                          LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                                          LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah");
                            while ($st = mysqli_fetch_assoc($qt)) {
                                echo "<option value='" . $st['id_transaksi'] . "'>Blok " . ($st['no_blok'] ?? '-') . " - " . ($st['name_lengkap'] ?? '-') . " (" . ($st['status_konsumen'] ?? '-') . ")</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor Surat</label>
                            <input type="text" name="no_surat" class="form-control" placeholder="SPB/2026/001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tanggal Surat</label>
                            <input type="date" name="tgl_surat" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alasan Pembatalan</label>
                        <textarea name="alasan_pembatalan" class="form-control" rows="2" placeholder="Contoh: calon konsumen mengundurkan diri dua pihak..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Booking Fee (Uang Pemesanan)</label>
                        <input type="text" name="booking_fee" class="form-control booking-fee" placeholder="Rp. 2.000.000" value="2.000.000">
                        <div class="form-text">Terkirim otomatis dari harga unit pada transaksi terpilih (bisa diubah).</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Rekening Tujuan (Nama)</label>
                            <input type="text" name="rekening_nama" class="form-control" placeholder="Nama pemilik rekening">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bank</label>
                            <input type="text" name="rekening_bank" class="form-control" placeholder="Bank BNI">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. Rekening</label>
                        <input type="text" name="rekening_no" class="form-control" placeholder="Nomor rekening">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan jika ada..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_pembatalan" class="btn text-white px-4" style="background-color: var(--primary-blue);">Simpan Surat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalHapus = document.getElementById('modalHapus');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var info = button.getAttribute('data-info');
            document.getElementById('infoPembatalanHapus').innerText = 'No. Surat: ' + info;
            document.getElementById('btnConfirmDelete').href = 'pembatalan_unit.php?action=hapus&id=' + id + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }

    var hargaByTransaksi = <?= json_encode(array_column(mysqli_fetch_all(mysqli_query($koneksi, "SELECT t.id_transaksi, r.harga_jual FROM data_transaksi t JOIN data_rumah r ON t.id_rumah = r.id_rumah"), MYSQLI_ASSOC), 'harga_jual', 'id_transaksi')) ?>;

    function formatRibuan(v) {
        var n = String(v).replace(/[^\d]/g, '');
        return n ? n.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    }

    document.querySelectorAll('select[name="id_transaksi"]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var fee = this.closest('form').querySelector('.booking-fee');
            if (fee && hargaByTransaksi[this.value]) {
                fee.value = formatRibuan(hargaByTransaksi[this.value]);
            }
        });
    });

    document.querySelectorAll('.booking-fee').forEach(function (input) {
        input.addEventListener('input', function () {
            var pos = this.selectionStart;
            var raw = this.value.replace(/[^\d]/g, '');
            this.value = formatRibuan(raw);
            this.setSelectionRange ? this.setSelectionRange(pos, pos) : null;
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>