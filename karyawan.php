<?php

require_once __DIR__ . '/includes/security.php'; // handler sesi DB harus aktif SEBELUM session_start
session_start();
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

// Auto-update status magang yang sudah selesai
sdb_query($koneksi, "UPDATE data_karyawan SET status = 'Selesai Magang' WHERE jenis_karyawan = 'Magang' AND tgl_akhir IS NOT NULL AND tgl_akhir < CURDATE() AND status = 'Aktif'");

// Tambah Karyawan
if (isset($_POST['tambah_karyawan'])) {
    $nama       = sdb_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $jabatan    = sdb_real_escape_string($koneksi, $_POST['jabatan']);
    $no_hp      = sdb_real_escape_string($koneksi, $_POST['no_hp']);
    $email      = sdb_real_escape_string($koneksi, $_POST['email']);
    $alamat     = sdb_real_escape_string($koneksi, $_POST['alamat']);
    $jenis      = sdb_real_escape_string($koneksi, $_POST['jenis_karyawan']);
    $ket_magang = sdb_real_escape_string($koneksi, $_POST['keterangan_magang']);
    $tgl_mulai  = sdb_real_escape_string($koneksi, $_POST['tgl_mulai']);
    $durasi     = intval($_POST['durasi_bulan'] ?? 0);

    $tgl_akhir = 'NULL';
    $status    = 'Aktif';
    if ($jenis === 'Magang' && $durasi > 0 && !empty($tgl_mulai)) {
        $end = date('Y-m-d', strtotime("$tgl_mulai +$durasi months"));
        $tgl_akhir = "'$end'";
    } elseif ($jenis === 'Tetap') {
        $tgl_akhir = 'NULL';
        $durasi = 0;
    } elseif ($jenis === 'Kontrak' && !empty($_POST['tgl_akhir'])) {
        $tgl_akhir = "'" . sdb_real_escape_string($koneksi, $_POST['tgl_akhir']) . "'";
    }

    sdb_query($koneksi, "INSERT INTO data_karyawan (nama_lengkap, jabatan, no_hp, email, alamat, jenis_karyawan, keterangan_magang, tgl_mulai, tgl_akhir, durasi_bulan, status)
                            VALUES ('$nama','$jabatan','$no_hp','$email','$alamat','$jenis','$ket_magang'," . ($tgl_mulai ? "'$tgl_mulai'" : "NULL") . ",$tgl_akhir," . ($durasi ?: 'NULL') . ",'$status')");
    header("Location: karyawan.php");
    exit();
}

// Edit Karyawan
if (isset($_POST['edit_karyawan'])) {
    $id         = intval($_POST['id_karyawan']);
    $nama       = sdb_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $jabatan    = sdb_real_escape_string($koneksi, $_POST['jabatan']);
    $no_hp      = sdb_real_escape_string($koneksi, $_POST['no_hp']);
    $email      = sdb_real_escape_string($koneksi, $_POST['email']);
    $alamat     = sdb_real_escape_string($koneksi, $_POST['alamat']);
    $jenis      = sdb_real_escape_string($koneksi, $_POST['jenis_karyawan']);
    $ket_magang = sdb_real_escape_string($koneksi, $_POST['keterangan_magang']);
    $tgl_mulai  = sdb_real_escape_string($koneksi, $_POST['tgl_mulai']);
    $status     = sdb_real_escape_string($koneksi, $_POST['status']);
    $durasi     = intval($_POST['durasi_bulan'] ?? 0);

    $tgl_akhir = 'NULL';
    if ($jenis === 'Magang' && $durasi > 0 && !empty($tgl_mulai)) {
        $end = date('Y-m-d', strtotime("$tgl_mulai +$durasi months"));
        $tgl_akhir = "'$end'";
    } elseif ($jenis === 'Kontrak' && !empty($_POST['tgl_akhir'])) {
        $tgl_akhir = "'" . sdb_real_escape_string($koneksi, $_POST['tgl_akhir']) . "'";
    }

    sdb_query($koneksi, "UPDATE data_karyawan SET nama_lengkap='$nama', jabatan='$jabatan', no_hp='$no_hp', email='$email', alamat='$alamat', jenis_karyawan='$jenis', keterangan_magang='$ket_magang', tgl_mulai=" . ($tgl_mulai ? "'$tgl_mulai'" : "NULL") . ", tgl_akhir=$tgl_akhir, durasi_bulan=" . ($durasi ?: 'NULL') . ", status='$status' WHERE id_karyawan=$id");
    header("Location: karyawan.php");
    exit();
}

// Hapus Karyawan
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = intval($_GET['id']);
    sdb_query($koneksi, "DELETE FROM data_karyawan WHERE id_karyawan = '$id_hapus'");
    header("Location: karyawan.php");
    exit();
}

// Hitung statistik
$stat = ['total'=>0, 'tetap'=>0, 'kontrak'=>0, 'magang'=>0, 'aktif'=>0, 'selesai'=>0];
$q = sdb_query($koneksi, "SELECT jenis_karyawan, status, COUNT(*) AS jml FROM data_karyawan GROUP BY jenis_karyawan, status");
while ($r = sdb_fetch_assoc($q)) {
    $stat['total'] += $r['jml'];
    if ($r['jenis_karyawan'] === 'Tetap') $stat['tetap'] += $r['jml'];
    elseif ($r['jenis_karyawan'] === 'Kontrak') $stat['kontrak'] += $r['jml'];
    elseif ($r['jenis_karyawan'] === 'Magang') $stat['magang'] += $r['jml'];
    if ($r['status'] === 'Aktif') $stat['aktif'] += $r['jml'];
    if ($r['status'] === 'Selesai Magang') $stat['selesai'] += $r['jml'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Data Karyawan - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .magang-progress { height: 6px; border-radius: 3px; background: #e2e8f0; }
        .magang-progress-bar { height: 100%; border-radius: 3px; transition: width 0.3s; }
    </style>
</head>
<body>
    <input type="hidden" id="csrf_global" value="<?= htmlspecialchars(csrf_token()) ?>">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>

    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1">Data Karyawan & Magang</h4>
                <p class="text-muted small mb-0">Kelola data karyawan tetap, kontrak, dan magang perusahaan.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="gaji.php" class="btn btn-outline-success px-4 py-2 rounded-3">
                    <i class="bi bi-cash-coin me-1"></i> Slip Gaji
                </a>
                <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Karyawan
                </button>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#eff6ff; color:var(--primary-blue);"><i class="bi bi-people-fill"></i></div>
                        <div><div class="stat-value"><?= $stat['total'] ?></div><div class="stat-label">Total</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;"><i class="bi bi-person-check-fill"></i></div>
                        <div><div class="stat-value"><?= $stat['aktif'] ?></div><div class="stat-label">Aktif</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;"><i class="bi bi-person-badge-fill"></i></div>
                        <div><div class="stat-value"><?= $stat['tetap'] ?></div><div class="stat-label">Tetap</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#e0e7ff; color:#4f46e5;"><i class="bi bi-file-earmark-person-fill"></i></div>
                        <div><div class="stat-value"><?= $stat['kontrak'] ?></div><div class="stat-label">Kontrak</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i class="bi bi-mortarboard-fill"></i></div>
                        <div><div class="stat-value"><?= $stat['magang'] ?></div><div class="stat-label">Magang</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#f3f4f6; color:#6b7280;"><i class="bi bi-check-circle-fill"></i></div>
                        <div><div class="stat-value"><?= $stat['selesai'] ?></div><div class="stat-label">Selesai</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span id="filterCounter" class="text-muted small fw-semibold">Memuat data...</span>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetFilter">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="karyawanTable" style="font-size: 0.88rem;">
                    <thead>
                        <tr class="text-muted">
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Jenis</th>
                            <th>No. HP</th>
                            <th>Masa Magang</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                        <tr class="filters-row">
                            <th><input type="text" class="form-control form-control-sm" data-col="0" placeholder="Cari nama..."></th>
                            <th><input type="text" class="form-control form-control-sm" data-col="1" placeholder="Cari jabatan..."></th>
                            <th><select class="form-select form-select-sm" data-col="2"><option value="">Semua Jenis</option></select></th>
                            <th><input type="text" class="form-control form-control-sm" data-col="3" placeholder="Cari HP..."></th>
                            <th><select class="form-select form-select-sm" data-col="4"><option value="">Semua Status</option></select></th>
                            <th><select class="form-select form-select-sm" data-col="5"><option value="">Semua Status</option></select></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $qk = sdb_query($koneksi, "SELECT * FROM data_karyawan ORDER BY
                            CASE status WHEN 'Aktif' THEN 0 WHEN 'Selesai Magang' THEN 1 ELSE 2 END,
                            CASE jenis_karyawan WHEN 'Tetap' THEN 0 WHEN 'Kontrak' THEN 1 WHEN 'Magang' THEN 2 END,
                            id_karyawan DESC");

                        if ($qk && sdb_num_rows($qk) > 0) {
                            while ($k = sdb_fetch_assoc($qk)) {
                                $badge_jenis = 'bg-primary';
                                if ($k['jenis_karyawan'] === 'Magang') $badge_jenis = 'bg-warning text-dark';
                                elseif ($k['jenis_karyawan'] === 'Tetap') $badge_jenis = 'bg-success';

                                $badge_status = 'bg-success';
                                if ($k['status'] === 'Nonaktif') $badge_status = 'bg-secondary';
                                elseif ($k['status'] === 'Selesai Magang') $badge_status = 'bg-info text-dark';

                                // Progress magang
                                $magang_html = '<span class="text-muted">-</span>';
                                if ($k['jenis_karyawan'] === 'Magang' && !empty($k['tgl_mulai']) && !empty($k['tgl_akhir'])) {
                                    $mulai = strtotime($k['tgl_mulai']);
                                    $akhir = strtotime($k['tgl_akhir']);
                                    $now = time();
                                    $total_days = ($akhir - $mulai) / 86400;
                                    $elapsed = max(0, ($now - $mulai) / 86400);
                                    $persen = min(100, round(($elapsed / max($total_days, 1)) * 100));
                                    $sisa_hari = max(0, ceil(($akhir - $now) / 86400));

                                    $bar_color = $persen < 60 ? 'bg-success' : ($persen < 85 ? 'bg-warning' : 'bg-danger');
                                    if ($k['status'] === 'Selesai Magang') { $persen = 100; $bar_color = 'bg-info'; }

                                    $magang_html = '<div class="mb-1">';
                                    $magang_html .= '<small class="text-muted">' . date('d M Y', $mulai) . ' s/d ' . date('d M Y', $akhir) . '</small>';
                                    $magang_html .= '</div><div class="magang-progress"><div class="magang-progress-bar ' . $bar_color . '" style="width:' . $persen . '%"></div></div>';
                                    $magang_html .= '<small class="fw-semibold" style="font-size:0.72rem;">' . ($k['status'] === 'Selesai Magang' ? 'Selesai' : $sisa_hari . ' hari lagi') . ' (' . $persen . '%)</small>';
                                }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($k['nama_lengkap']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($k['email'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($k['jabatan'] ?: '-') ?></td>
                            <td>
                                <span class="badge <?= $badge_jenis ?> px-3 py-2 rounded-pill"><?= $k['jenis_karyawan'] ?></span>
                                <?php if ($k['jenis_karyawan'] === 'Magang' && !empty($k['keterangan_magang'])): ?>
                                    <div><small class="text-muted"><?= htmlspecialchars($k['keterangan_magang']) ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($k['no_hp'] ?: '-') ?></td>
                            <td><?= $magang_html ?></td>
                            <td><span class="badge <?= $badge_status ?> px-3 py-2 rounded-pill"><?= $k['status'] ?></span></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $k['id_karyawan'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalHapus" data-id="<?= $k['id_karyawan'] ?>" data-info="<?= htmlspecialchars($k['nama_lengkap']) ?>" title="Hapus"><i class="bi bi-trash"></i></button>
                                </div>

                                <!-- MODAL EDIT -->
                                <div class="modal fade text-start" id="modalEdit<?= $k['id_karyawan'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Karyawan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <form action="karyawan.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_karyawan" value="<?= $k['id_karyawan'] ?>">
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Nama Lengkap *</label><input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($k['nama_lengkap']) ?>" required></div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Jabatan</label><input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($k['jabatan']) ?>" placeholder="Manager, Staff, Admin..."></div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">No. HP</label><input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($k['no_hp']) ?>"></div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($k['email']) ?>"></div>
                                                        <div class="col-12"><label class="form-label small fw-semibold">Alamat</label><textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($k['alamat']) ?></textarea></div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Jenis Karyawan</label>
                                                            <select name="jenis_karyawan" class="form-select jenis-select" data-id="<?= $k['id_karyawan'] ?>">
                                                                <option value="Tetap" <?= $k['jenis_karyawan']==='Tetap'?'selected':'' ?>>Tetap</option>
                                                                <option value="Kontrak" <?= $k['jenis_karyawan']==='Kontrak'?'selected':'' ?>>Kontrak</option>
                                                                <option value="Magang" <?= $k['jenis_karyawan']==='Magang'?'selected':'' ?>>Magang</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="Aktif" <?= $k['status']==='Aktif'?'selected':'' ?>>Aktif</option>
                                                                <option value="Nonaktif" <?= $k['status']==='Nonaktif'?'selected':'' ?>>Nonaktif</option>
                                                                <option value="Selesai Magang" <?= $k['status']==='Selesai Magang'?'selected':'' ?>>Selesai Magang</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Tgl Mulai</label><input type="date" name="tgl_mulai" class="form-control" value="<?= $k['tgl_mulai'] ?>"></div>
                                                        <div class="col-md-6 field-magang<?= $k['jenis_karyawan']==='Magang'?'':' d-none' ?>" data-target="<?= $k['id_karyawan'] ?>">
                                                            <label class="form-label small fw-semibold">Durasi Magang (Bulan)</label>
                                                            <input type="number" name="durasi_bulan" class="form-control" min="1" max="24" value="<?= $k['durasi_bulan'] ?: '' ?>" placeholder="Contoh: 4">
                                                            <?php if (!empty($k['tgl_akhir'])): ?><small class="text-muted">Berakhir: <?= date('d M Y', strtotime($k['tgl_akhir'])) ?></small><?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6 field-kontrak<?= $k['jenis_karyawan']==='Kontrak'?'':' d-none' ?>" data-target="<?= $k['id_karyawan'] ?>">
                                                            <label class="form-label small fw-semibold">Tgl Akhir Kontrak</label>
                                                            <input type="date" name="tgl_akhir" class="form-control" value="<?= $k['tgl_akhir'] ?>">
                                                        </div>
                                                        <div class="col-md-6 field-magang<?= $k['jenis_karyawan']==='Magang'?'':' d-none' ?>" data-target="<?= $k['id_karyawan'] ?>">
                                                            <label class="form-label small fw-semibold">Keterangan Magang</label>
                                                            <input type="text" name="keterangan_magang" class="form-control" value="<?= htmlspecialchars($k['keterangan_magang']) ?>" placeholder="Magang Industri, Magang Kerja...">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0"><button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button><button type="submit" name="edit_karyawan" class="btn text-white px-4 rounded-3" style="background-color:var(--primary-blue);">Simpan Perubahan</button></div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } } else { ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data karyawan.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow">
        <div class="modal-header bg-danger text-white rounded-top-4"><h5 class="modal-title fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center py-4"><i class="bi bi-trash text-danger display-4 mb-3 d-block"></i><h6 class="fw-bold">Hapus karyawan ini?</h6><p class="text-muted small mb-0" id="infoHapus">-</p></div>
        <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4"><button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button><a href="#" id="btnConfirmDelete" class="btn btn-danger px-4 rounded-3">Ya, Hapus</a></div>
    </div></div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Karyawan / Magang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="karyawan.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-semibold">Nama Lengkap *</label><input type="text" name="nama_lengkap" class="form-control" required placeholder="Nama karyawan"></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Jabatan</label><input type="text" name="jabatan" class="form-control" placeholder="Manager, Staff, Marketing..."></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">No. HP</label><input type="text" name="no_hp" class="form-control" placeholder="08xxx"></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" class="form-control" placeholder="nama@email.com"></div>
                        <div class="col-12"><label class="form-label small fw-semibold">Alamat</label><textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap..."></textarea></div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Jenis Karyawan</label>
                            <select name="jenis_karyawan" class="form-select jenis-select" id="jenisBaru">
                                <option value="Tetap">Karyawan Tetap</option>
                                <option value="Kontrak">Karyawan Kontrak</option>
                                <option value="Magang">Anak Magang</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select"><option value="Aktif">Aktif</option><option value="Nonaktif">Nonaktif</option></select>
                        </div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Tgl Mulai</label><input type="date" name="tgl_mulai" class="form-control"></div>

                        <!-- FIELD MAGANG -->
                        <div class="col-md-6 field-magang-baru" id="fieldDurasiMagang">
                            <label class="form-label small fw-semibold">Durasi Magang (Bulan) *</label>
                            <input type="number" name="durasi_bulan" class="form-control" min="1" max="24" placeholder="Contoh: 4">
                            <small class="text-muted">Tgl akhir otomatis dihitung dari durasi.</small>
                        </div>
                        <div class="col-md-6 field-magang-baru" id="fieldKetMagang">
                            <label class="form-label small fw-semibold">Keterangan Magang</label>
                            <input type="text" name="keterangan_magang" class="form-control" placeholder="Magang Industri, Magang Kerja...">
                        </div>

                        <!-- FIELD KONTRAK -->
                        <div class="col-md-6 d-none" id="fieldTglAkhir">
                            <label class="form-label small fw-semibold">Tgl Akhir Kontrak</label>
                            <input type="date" name="tgl_akhir" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button><button type="submit" name="tambah_karyawan" class="btn text-white px-4 rounded-3" style="background-color:var(--primary-blue);">Simpan Karyawan</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle field berdasarkan jenis karyawan
    function toggleFields(val, prefix) {
        var m = prefix === 'new' ? document.querySelectorAll('.field-magang-baru') : document.querySelectorAll('.field-magang[data-target="'+prefix+'"]');
        var k = prefix === 'new' ? [document.getElementById('fieldTglAkhir')] : document.querySelectorAll('.field-kontrak[data-target="'+prefix+'"]');
        m.forEach(function(el){ el.classList.toggle('d-none', val !== 'Magang'); });
        k.forEach(function(el){ el.classList.toggle('d-none', val !== 'Kontrak'); });
    }

    document.querySelectorAll('.jenis-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var id = this.dataset.id || 'new';
            toggleFields(this.value, id);
        });
    });

    var jenisBaru = document.getElementById('jenisBaru');
    if (jenisBaru) toggleFields(jenisBaru.value, 'new');

    // Filter table
    var table = document.getElementById('karyawanTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var filters = Array.from(table.querySelectorAll('.filters-row input, .filters-row select'));
    var counter = document.getElementById('filterCounter');

    var cellText = function(row, col) {
        var cell = row.cells[col];
        return cell ? cell.textContent.trim().toLowerCase() : '';
    };

    filters.filter(function(el){ return el.tagName === 'SELECT'; }).forEach(function(sel) {
        var col = parseInt(sel.dataset.col);
        var seen = new Set();
        rows.forEach(function(r){ var v = cellText(r, col); if (v) seen.add(v); });
        Array.from(seen).sort().forEach(function(v){
            var opt = document.createElement('option'); opt.value = v; opt.textContent = v; sel.appendChild(opt);
        });
    });

    var applyFilters = function() {
        var shown = 0;
        rows.forEach(function(row) {
            var show = true;
            filters.forEach(function(el) {
                if (!show) return;
                var q = el.value.trim().toLowerCase();
                if (!q) return;
                var col = parseInt(el.dataset.col);
                var val = cellText(row, col);
                if (el.tagName === 'SELECT') { if (val !== q) show = false; }
                else { if (val.indexOf(q) === -1) show = false; }
            });
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        if (counter) counter.textContent = 'Menampilkan ' + shown + ' dari ' + rows.length + ' data';
    };

    filters.forEach(function(el) { el.addEventListener('input', applyFilters); el.addEventListener('change', applyFilters); });

    var btnReset = document.getElementById('btnResetFilter');
    if (btnReset) btnReset.addEventListener('click', function() { filters.forEach(function(el){ el.value = ''; }); applyFilters(); });

    applyFilters();

    // Modal hapus
    var modalHapus = document.getElementById('modalHapus');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function(event) {
            var btn = event.relatedTarget;
            document.getElementById('infoHapus').innerText = 'Karyawan: ' + btn.getAttribute('data-info');
            document.getElementById('btnConfirmDelete').href = 'karyawan.php?action=hapus&id=' + btn.getAttribute('data-id') + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
