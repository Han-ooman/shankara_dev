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

$pesan_sukses = "";
$pesan_gagal  = "";

// 1. AUTO-CREATE TABEL PENGATURAN & USER JIKA BELUM ADA
sdb_query($koneksi, "CREATE TABLE IF NOT EXISTS pengaturan_sistem (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kunci VARCHAR(50) UNIQUE,
    nilai TEXT
)");

sdb_query($koneksi, "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_lengkap VARCHAR(100),
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    role VARCHAR(50),
    status ENUM('Aktif', 'Nonaktif') DEFAULT 'Aktif'
)");

// Masukkan admin default jika tabel user masih kosong
$cek_user = sdb_query($koneksi, "SELECT id FROM users");
if ($cek_user && sdb_num_rows($cek_user) == 0) {
    $pass_default = password_hash('admin123', PASSWORD_DEFAULT);
    sdb_query($koneksi, "INSERT INTO users (nama_lengkap, username, password, role, status) VALUES 
        ('Admin Utama', 'admin', '$pass_default', 'Super Admin', 'Aktif'),
        ('Staf Lapangan', 'lapangan_shk', '$pass_default', 'Pengawas Lapangan', 'Aktif')");
}

// HELPER GET SETTING
function get_setting($koneksi, $kunci, $default = '') {
    $q = sdb_query($koneksi, "SELECT nilai FROM pengaturan_sistem WHERE kunci='$kunci'");
    if ($q && sdb_num_rows($q) > 0) {
        $d = sdb_fetch_assoc($q);
        return $d['nilai'];
    }
    return $default;
}

// 2. HANDLER: SIMPAN PROFIL PERUSAHAAN
if (isset($_POST['simpan_profil'])) {
    $data = [
        'nama_perusahaan'     => sdb_real_escape_string($koneksi, $_POST['nama_perusahaan']),
        'pimpinan_perusahaan' => sdb_real_escape_string($koneksi, $_POST['nama_pimpinan']),
        'telepon_perusahaan'  => sdb_real_escape_string($koneksi, $_POST['telepon']),
        'email_perusahaan'    => sdb_real_escape_string($koneksi, $_POST['email']),
        'rekening_perusahaan' => sdb_real_escape_string($koneksi, $_POST['bank_rekening']),
        'alamat_perusahaan'   => sdb_real_escape_string($koneksi, $_POST['alamat'])
    ];

    foreach ($data as $kunci => $nilai) {
        sdb_query($koneksi, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('$kunci', '$nilai') 
                                ON DUPLICATE KEY UPDATE nilai='$nilai'");
    }
    $pesan_sukses = "Profil perusahaan berhasil diperbarui!";
}

// 3. HANDLER: MANAJEMEN USER (TAMBAH, EDIT, & HAPUS)
if (isset($_POST['tambah_user'])) {
    $nama     = sdb_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $username = sdb_real_escape_string($koneksi, $_POST['username']);
    $role     = sdb_real_escape_string($koneksi, $_POST['role']);
    $status   = sdb_real_escape_string($koneksi, $_POST['status']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $simpan = sdb_query($koneksi, "INSERT INTO users (nama_lengkap, username, password, role, status) 
                                      VALUES ('$nama', '$username', '$password', '$role', '$status')");
    if ($simpan) {
        $pesan_sukses = "User baru berhasil ditambahkan!";
    } else {
        $pesan_gagal = "Gagal menambah user! Username mungkin sudah digunakan.";
    }
}

if (isset($_POST['edit_user'])) {
    $id_user  = intval($_POST['id_user']);
    $nama     = sdb_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $role     = sdb_real_escape_string($koneksi, $_POST['role']);
    $status   = sdb_real_escape_string($koneksi, $_POST['status']);

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        sdb_query($koneksi, "UPDATE users SET nama_lengkap='$nama', role='$role', status='$status', password='$password' WHERE id=$id_user");
    } else {
        sdb_query($koneksi, "UPDATE users SET nama_lengkap='$nama', role='$role', status='$status' WHERE id=$id_user");
    }
    $pesan_sukses = "Data user berhasil diperbarui!";
}

// FUNGSI HAPUS USER
if (isset($_POST['hapus_user'])) {
    $id_user = intval($_POST['id_user']);
    
    // Cek username user yang akan dihapus
    $q_cek = sdb_query($koneksi, "SELECT username FROM users WHERE id=$id_user");
    $u_cek = sdb_fetch_assoc($q_cek);

    if ($u_cek && $u_cek['username'] === 'admin') {
        $pesan_gagal = "Akun Admin Utama (admin) tidak dapat dihapus demi keamanan sistem!";
    } else {
        $hapus = sdb_query($koneksi, "DELETE FROM users WHERE id=$id_user");
        if ($hapus) {
            $pesan_sukses = "Pengguna berhasil dihapus dari sistem!";
        } else {
            $pesan_gagal = "Gagal menghapus pengguna!";
        }
    }
}

// 4. HANDLER: MASTER DATA (BANK & SYARAT DOKUMEN)
if (isset($_POST['tambah_bank'])) {
    $bank_baru = trim($_POST['nama_bank']);
    if (!empty($bank_baru)) {
        $list_bank = get_setting($koneksi, 'master_bank', 'Bank BTN, Bank Mandiri, Bank BRI, Bank BNI');
        $arr_bank  = array_map('trim', explode(',', $list_bank));
        if (!in_array($bank_baru, $arr_bank)) {
            $arr_bank[] = $bank_baru;
            $new_val    = sdb_real_escape_string($koneksi, implode(', ', $arr_bank));
            sdb_query($koneksi, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('master_bank', '$new_val') ON DUPLICATE KEY UPDATE nilai='$new_val'");
            $pesan_sukses = "Bank baru berhasil ditambahkan!";
        }
    }
}

if (isset($_POST['hapus_bank'])) {
    $bank_hapus = $_POST['bank_name'];
    $list_bank  = get_setting($koneksi, 'master_bank', 'Bank BTN, Bank Mandiri, Bank BRI, Bank BNI');
    $arr_bank   = array_map('trim', explode(',', $list_bank));
    $arr_bank   = array_diff($arr_bank, array($bank_hapus));
    $new_val    = sdb_real_escape_string($koneksi, implode(', ', $arr_bank));
    sdb_query($koneksi, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('master_bank', '$new_val') ON DUPLICATE KEY UPDATE nilai='$new_val'");
    $pesan_sukses = "Bank berhasil dihapus!";
}

if (isset($_POST['tambah_dokumen'])) {
    $dok_baru = trim($_POST['nama_dokumen']);
    if (!empty($dok_baru)) {
        $list_dok = get_setting($koneksi, 'master_dokumen', 'KTP Suami/Istri, NPWP, Slip Gaji 3 Bulan, Rekening Koran');
        $arr_dok  = array_map('trim', explode(',', $list_dok));
        if (!in_array($dok_baru, $arr_dok)) {
            $arr_dok[] = $dok_baru;
            $new_val   = sdb_real_escape_string($koneksi, implode(', ', $arr_dok));
            sdb_query($koneksi, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('master_dokumen', '$new_val') ON DUPLICATE KEY UPDATE nilai='$new_val'");
            $pesan_sukses = "Syarat dokumen berhasil ditambahkan!";
        }
    }
}

if (isset($_POST['hapus_dokumen'])) {
    $dok_hapus = $_POST['dok_name'];
    $list_dok  = get_setting($koneksi, 'master_dokumen', 'KTP Suami/Istri, NPWP, Slip Gaji 3 Bulan, Rekening Koran');
    $arr_dok   = array_map('trim', explode(',', $list_dok));
    $arr_dok   = array_diff($arr_dok, array($dok_hapus));
    $new_val   = sdb_real_escape_string($koneksi, implode(', ', $arr_dok));
    sdb_query($koneksi, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('master_dokumen', '$new_val') ON DUPLICATE KEY UPDATE nilai='$new_val'");
    $pesan_sukses = "Syarat dokumen berhasil dihapus!";
}

// 5. HANDLER: PENOMORAN FORMAT
if (isset($_POST['simpan_penomoran'])) {
    $data = [
        'fmt_kwitansi' => sdb_real_escape_string($koneksi, $_POST['fmt_kwitansi']),
        'fmt_bast'     => sdb_real_escape_string($koneksi, $_POST['fmt_bast']),
        'fmt_trx'      => sdb_real_escape_string($koneksi, $_POST['fmt_trx'])
    ];
    foreach ($data as $kunci => $nilai) {
        sdb_query($koneksi, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('$kunci', '$nilai') 
                                ON DUPLICATE KEY UPDATE nilai='$nilai'");
    }
    $pesan_sukses = "Format penomoran berhasil disimpan!";
}

// 6. HANDLER: BACKUP & RESTORE DATABASE
if (isset($_POST['download_backup'])) {
    $tables = array();
    $result = sdb_query($koneksi, "SHOW TABLES");
    while ($row = sdb_fetch_row($result)) { $tables[] = $row[0]; }

    $return = "-- BACKUP DATABASE SHANKARA DEV\n-- Tanggal: " . date('Y-m-d H:i:s') . "\n\n";
    foreach ($tables as $table) {
        $result = sdb_query($koneksi, "SELECT * FROM " . $table);
        $num_fields = sdb_num_fields($result);
        $return .= "DROP TABLE IF EXISTS " . $table . ";";
        $row2 = sdb_fetch_row(sdb_query($koneksi, "SHOW CREATE TABLE " . $table));
        $return .= "\n\n" . $row2[1] . ";\n\n";

        while ($row = sdb_fetch_row($result)) {
            $return .= "INSERT INTO " . $table . " VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = preg_replace("/\n/m", "\\n", $row[$j]);
                if (isset($row[$j])) { $return .= '"' . $row[$j] . '"'; } else { $return .= '""'; }
                if ($j < ($num_fields - 1)) { $return .= ','; }
            }
            $return .= ");\n";
        }
        $return .= "\n\n\n";
    }
    $filename = 'backup_shankara_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $return;
    exit();
}

if (isset($_POST['restore_database'])) {
    if ($_FILES['file_sql']['error'] == 0) {
        $filename = $_FILES['file_sql']['tmp_name'];
        $sql_lines = file($filename);
        $query = '';
        foreach ($sql_lines as $line) {
            if (substr($line, 0, 2) == '--' || $line == '') continue;
            $query .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                sdb_query($koneksi, $query);
                $query = '';
            }
        }
        $pesan_sukses = "Database berhasil di-restore!";
    } else {
        $pesan_gagal = "Pilih file .sql yang valid!";
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
    <title>Pengaturan Sistem - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .tab-btn {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: var(--text-muted);
            font-weight: 500;
            border-radius: 30px;
            padding: 10px 22px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background-color: var(--primary-blue) !important;
            color: #ffffff !important;
            border-color: var(--primary-blue) !important;
            font-weight: 600;
        }

        .tab-pane-custom {
            display: none;
        }

        .tab-pane-custom.active {
            display: block;
        }

        .form-control-custom {
            border-radius: 10px;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            font-size: 0.9rem;
        }

        .form-control-custom:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
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
        <div class="mb-4">
            <h4 class="fw-bold mb-1">Pengaturan Sistem</h4>
            <p class="text-muted small mb-0">Kelola identitas perusahaan, pengguna, penomoran, dan pemeliharaan database.</p>
        </div>

        <?php if (!empty($pesan_sukses)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= $pesan_sukses ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($pesan_gagal)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pesan_gagal ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- NAV TAB TOMBOL -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" class="tab-btn active" onclick="switchTab(this, 'tab-profil')"><i class="bi bi-building me-2"></i>Profil Developer</button>
            <button type="button" class="tab-btn" onclick="switchTab(this, 'tab-user')"><i class="bi bi-people me-2"></i>Manajemen User</button>
            <button type="button" class="tab-btn" onclick="switchTab(this, 'tab-master')"><i class="bi bi-sliders me-2"></i>Master Data</button>
            <button type="button" class="tab-btn" onclick="switchTab(this, 'tab-keuangan')"><i class="bi bi-cash-stack me-2"></i>Penomoran & Keuangan</button>
            <button type="button" class="tab-btn" onclick="switchTab(this, 'tab-database')"><i class="bi bi-database me-2"></i>Backup & Restore</button>
        </div>

        <div class="content-card">
            
            <!-- TAB 1: PROFIL PERUSAHAAN -->
            <div id="tab-profil" class="tab-pane-custom active">
                <h6 class="fw-bold mb-3" style="color: var(--primary-blue);">Identitas Perusahaan & Kop Laporan</h6>
                <form action="pengaturan.php" method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Developer / Perusahaan</label>
                            <input type="text" name="nama_perusahaan" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'nama_perusahaan', 'PT Shankara Development')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Pimpinan / Direktur (TTD BAST)</label>
                            <input type="text" name="nama_pimpinan" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'pimpinan_perusahaan', 'Direktur Utama')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor Telepon / WA Operational</label>
                            <input type="text" name="telepon" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'telepon_perusahaan', '081234567890')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Resmi Perusahaan</label>
                            <input type="email" name="email" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'email_perusahaan', 'info@shankaradevelopment.com')) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Informasi Rekening Perusahaan (Kwitansi)</label>
                            <input type="text" name="bank_rekening" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'rekening_perusahaan', 'Bank Mandiri: 123-00-0000000-0 a/n PT Shankara Development')) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Alamat Kantor Operasional</label>
                            <textarea name="alamat" class="form-control form-control-custom" rows="3"><?= htmlspecialchars(get_setting($koneksi, 'alamat_perusahaan', 'Jl. Utama Shankara Residence No. 01')) ?></textarea>
                        </div>
                        <div class="col-12 text-end mt-4">
                            <button type="submit" name="simpan_profil" class="btn text-white rounded-pill px-4" style="background-color: var(--primary-blue);">
                                <i class="bi bi-save me-1"></i> Simpan Profil Perusahaan
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB 2: MANAJEMEN USER -->
            <div id="tab-user" class="tab-pane-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="color: var(--primary-blue);">Daftar Pengguna Sistem</h6>
                    <button type="button" class="btn btn-sm text-white rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalTambahUser" style="background-color: var(--primary-blue);">
                        <i class="bi bi-person-plus me-1"></i> Tambah User Baru
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Level Akses</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $q_user = sdb_query($koneksi, "SELECT * FROM users ORDER BY id ASC");
                            while ($u = sdb_fetch_assoc($q_user)):
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><span class="badge bg-success rounded-pill"><?= htmlspecialchars($u['role']) ?></span></td>
                                <td>
                                    <span class="badge <?= $u['status'] == 'Aktif' ? 'bg-primary' : 'bg-danger' ?> rounded-pill">
                                        <?= htmlspecialchars($u['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalEditUser<?= $u['id'] ?>">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </button>
                                    
                                    <!-- TOMBOL HAPUS (DIPROTEKSI JIKA AKUN ADMIN) -->
                                    <?php if ($u['username'] !== 'admin'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 ms-1" data-bs-toggle="modal" data-bs-target="#modalHapusUser<?= $u['id'] ?>">
                                            <i class="bi bi-trash me-1"></i>Hapus
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-light border text-muted rounded-pill px-3 ms-1" disabled title="Utama / Dikunci">
                                            <i class="bi bi-shield-lock me-1"></i>Hapus
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- MODAL EDIT USER -->
                            <div class="modal fade" id="modalEditUser<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content rounded-4">
                                        <form action="pengaturan.php" method="POST">
                                            <?= csrf_field() ?>
                                            <div class="modal-header border-0">
                                                <h6 class="modal-title fw-bold">Edit User: <?= htmlspecialchars($u['username']) ?></h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Nama Lengkap</label>
                                                    <input type="text" name="nama_lengkap" class="form-control form-control-custom" value="<?= htmlspecialchars($u['nama_lengkap']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Level Akses (Role)</label>
                                                    <select name="role" class="form-select form-control-custom">
                                                        <option value="Super Admin" <?= $u['role'] == 'Super Admin' ? 'selected' : '' ?>>Super Admin</option>
                                                        <option value="Admin Pemberkasan" <?= $u['role'] == 'Admin Pemberkasan' ? 'selected' : '' ?>>Admin Pemberkasan</option>
                                                        <option value="Pengawas Lapangan" <?= $u['role'] == 'Pengawas Lapangan' ? 'selected' : '' ?>>Pengawas Lapangan</option>
                                                        <option value="Kasir Keuangan" <?= $u['role'] == 'Kasir Keuangan' ? 'selected' : '' ?>>Kasir Keuangan</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Status Akun</label>
                                                    <select name="status" class="form-select form-control-custom">
                                                        <option value="Aktif" <?= $u['status'] == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                                                        <option value="Nonaktif" <?= $u['status'] == 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Password Baru (Kosongkan jika tidak diganti)</label>
                                                    <input type="password" name="password" class="form-control form-control-custom" placeholder="******">
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="submit" name="edit_user" class="btn text-white rounded-pill px-4" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- MODAL KONFIRMASI HAPUS USER -->
                            <div class="modal fade" id="modalHapusUser<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0">
                                        <form action="pengaturan.php" method="POST">
                                            <?= csrf_field() ?>
                                            <div class="modal-body text-center p-4">
                                                <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-2 d-block"></i>
                                                <h6 class="fw-bold mb-2">Konfirmasi Hapus Pengguna</h6>
                                                <p class="text-muted small mb-3">Apakah Anda yakin ingin menghapus pengguna <strong><?= htmlspecialchars($u['nama_lengkap']) ?></strong> (<?= htmlspecialchars($u['username']) ?>)? Tindakan ini tidak dapat dibatalkan.</p>
                                                <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                                <div class="d-flex justify-content-center gap-2">
                                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_user" class="btn btn-danger rounded-pill px-4">Hapus Pengguna</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: MASTER DATA -->
            <div id="tab-master" class="tab-pane-custom">
                <h6 class="fw-bold mb-3" style="color: var(--primary-blue);">Master Pilihan System (Dropdown Options)</h6>
                <div class="row g-4">
                    <!-- BANK KPR -->
                    <div class="col-md-6">
                        <div class="p-3 border rounded-4 bg-light">
                            <h6 class="fw-semibold mb-2">Daftar Bank KPR Pilihan</h6>
                            <p class="text-muted small mb-3">Bank yang bekerjasama untuk proses pengajuan KPR Konsumen.</p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php
                                $list_bank = get_setting($koneksi, 'master_bank', 'Bank BTN, Bank Mandiri, Bank BRI, Bank BNI');
                                $arr_bank = array_map('trim', explode(',', $list_bank));
                                foreach ($arr_bank as $b):
                                    if(empty($b)) continue;
                                ?>
                                    <form action="pengaturan.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="bank_name" value="<?= htmlspecialchars($b) ?>">
                                        <span class="badge bg-white text-dark border rounded-pill p-2 px-3 align-items-center gap-1">
                                            <?= htmlspecialchars($b) ?>
                                            <button type="submit" name="hapus_bank" class="btn-close ms-1" style="font-size: 0.65rem;" onclick="return confirm('Hapus bank ini?')"></button>
                                        </span>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                            <form action="pengaturan.php" method="POST" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="text" name="nama_bank" class="form-control form-control-custom" placeholder="Nama Bank Baru..." required>
                                <button type="submit" name="tambah_bank" class="btn btn-sm btn-outline-success rounded-pill px-3 text-nowrap">+ Tambah Bank</button>
                            </form>
                        </div>
                    </div>

                    <!-- SYARAT DOKUMEN -->
                    <div class="col-md-6">
                        <div class="p-3 border rounded-4 bg-light">
                            <h6 class="fw-semibold mb-2">Daftar Checklist Dokumen KPR</h6>
                            <p class="text-muted small mb-3">Persyaratan wajib untuk modul Pemberkasan KPR.</p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php
                                $list_dok = get_setting($koneksi, 'master_dokumen', 'KTP Suami/Istri, NPWP, Slip Gaji 3 Bulan, Rekening Koran');
                                $arr_dok = array_map('trim', explode(',', $list_dok));
                                foreach ($arr_dok as $d):
                                    if(empty($d)) continue;
                                ?>
                                    <form action="pengaturan.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="dok_name" value="<?= htmlspecialchars($d) ?>">
                                        <span class="badge bg-white text-dark border rounded-pill p-2 px-3 align-items-center gap-1">
                                            <?= htmlspecialchars($d) ?>
                                            <button type="submit" name="hapus_dokumen" class="btn-close ms-1" style="font-size: 0.65rem;" onclick="return confirm('Hapus dokumen ini?')"></button>
                                        </span>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                            <form action="pengaturan.php" method="POST" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="text" name="nama_dokumen" class="form-control form-control-custom" placeholder="Nama Dokumen Baru..." required>
                                <button type="submit" name="tambah_dokumen" class="btn btn-sm btn-outline-success rounded-pill px-3 text-nowrap">+ Tambah Syarat</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PENOMORAN & KEUANGAN -->
            <div id="tab-keuangan" class="tab-pane-custom">
                <h6 class="fw-bold mb-3" style="color: var(--primary-blue);">Format Penomoran Dokumen Otomatis</h6>
                <form action="pengaturan.php" method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Format Nomor Kwitansi</label>
                            <input type="text" name="fmt_kwitansi" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'fmt_kwitansi', 'KW/SHK/{YYYY}/{NO}')) ?>" required>
                            <div class="form-text small">Contoh Output: KW/SHK/2026/001</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Format Nomor BAST</label>
                            <input type="text" name="fmt_bast" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'fmt_bast', 'BAST/SHK/{YYYY}/{NO}')) ?>" required>
                            <div class="form-text small">Contoh Output: BAST/SHK/2026/001</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Format Kode Transaksi</label>
                            <input type="text" name="fmt_trx" class="form-control form-control-custom" value="<?= htmlspecialchars(get_setting($koneksi, 'fmt_trx', 'TRX/{YYYY}/{NO}')) ?>" required>
                            <div class="form-text small">Contoh Output: TRX/2026/001</div>
                        </div>
                        <div class="col-12 text-end mt-4">
                            <button type="submit" name="simpan_penomoran" class="btn text-white rounded-pill px-4" style="background-color: var(--primary-blue);">
                                <i class="bi bi-save me-1"></i> Simpan Format Penomoran
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB 5: BACKUP & RESTORE DATABASE -->
            <div id="tab-database" class="tab-pane-custom">
                <h6 class="fw-bold mb-2" style="color: var(--primary-blue);">Pemeliharaan & Keamanan Database</h6>
                <p class="text-muted small mb-4">Lakukan backup database secara rutin untuk mengamankan data transaksi, unit, dan konsumen Shankara Dev.</p>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 text-center bg-light">
                            <i class="bi bi-download text-success fs-1 mb-2 d-block"></i>
                            <h6 class="fw-bold">Download Backup Database (.SQL)</h6>
                            <p class="text-muted small mb-3">Unduh seluruh tabel dan isi database Supabase dalam 1 klik.</p>
                            <form action="pengaturan.php" method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" name="download_backup" class="btn text-white rounded-pill px-4" style="background-color: var(--primary-blue);">
                                    <i class="bi bi-cloud-arrow-down me-1"></i> Download Backup Sekarang
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 text-center bg-light">
                            <i class="bi bi-upload text-warning fs-1 mb-2 d-block"></i>
                            <h6 class="fw-bold">Restore Database</h6>
                            <p class="text-muted small mb-3">Unggah berkas `.sql` untuk mengembalikan struktur data.</p>
                            <form action="pengaturan.php" method="POST" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="file" name="file_sql" class="form-control form-control-custom mb-3" accept=".sql" required>
                                <button type="submit" name="restore_database" class="btn btn-warning text-dark rounded-pill px-4" onclick="return confirm('Restore akan menimpa data yang ada. Lanjutkan?')">
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload & Restore
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- MODAL TAMBAH USER BARU -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">
            <form action="pengaturan.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header border-0">
                    <h6 class="modal-title fw-bold">Tambah Pengguna Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Level Akses (Role)</label>
                        <select name="role" class="form-select form-control-custom">
                            <option value="Super Admin">Super Admin</option>
                            <option value="Admin Pemberkasan">Admin Pemberkasan</option>
                            <option value="Pengawas Lapangan">Pengawas Lapangan</option>
                            <option value="Kasir Keuangan">Kasir Keuangan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status Akun</label>
                        <select name="status" class="form-select form-control-custom">
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="tambah_user" class="btn text-white rounded-pill px-4" style="background-color: var(--primary-blue);">Tambah User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(btnElement, tabId) {
    const panes = document.querySelectorAll(".tab-pane-custom");
    panes.forEach(pane => pane.classList.remove("active"));

    const btns = document.querySelectorAll(".tab-btn");
    btns.forEach(btn => btn.classList.remove("active"));

    const activePane = document.getElementById(tabId);
    if (activePane) activePane.classList.add("active");
    
    btnElement.classList.add("active");
}
</script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
