<?php
require_once __DIR__ . '/security.php';
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include __DIR__ . "/cek_sesi.php";

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

$MAINTENANCE_PASS = 'shankara2026';
$mfile = __DIR__ . '/.maintenance';
$is_on = file_exists($mfile);
$pesan = '';
$hasil = '';

// Proses toggle
if (isset($_POST['toggle'])) {
    $pass = trim($_POST['password'] ?? '');
    if ($pass !== $MAINTENANCE_PASS) {
        $hasil = 'error';
    } else {
        if ($is_on) {
            unlink($mfile);
            $hasil = 'off';
            $is_on = false;
        } else {
            $msg = trim($_POST['message'] ?? '');
            file_put_contents($mfile, $msg);
            $hasil = 'on';
            $is_on = true;
        }
    }
}

if ($is_on && file_exists($mfile)) {
    $pesan = trim(file_get_contents($mfile));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/favicon/favicon.png">
    <title>Toggle Maintenance Mode - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f7f5; }
        .toggle-card { max-width: 480px; margin: 40px auto; border: none; border-radius: 16px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="card toggle-card shadow-sm mx-auto p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-tools me-2"></i>Maintenance Mode</h5>

        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 <?= $is_on ? 'bg-warning bg-opacity-10 border border-warning' : 'bg-success bg-opacity-10 border border-success' ?>">
            <div>
                <div class="fw-bold <?= $is_on ? 'text-warning' : 'text-success' ?>">
                    <?= $is_on ? 'MAINTENANCE AKTIF' : 'WEBSITE AKTIF' ?>
                </div>
                <small class="text-muted"><?= $is_on ? 'Pengunjung melihat halaman maintenance.' : 'Semua halaman berjalan normal.' ?></small>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" <?= $is_on ? 'checked' : '' ?> disabled style="cursor:default;">
            </div>
        </div>

        <?php if ($hasil === 'on'): ?>
            <div class="alert alert-success small py-2 rounded-3"><i class="bi bi-check-circle me-1"></i>Mode maintenance berhasil diaktifkan.</div>
        <?php elseif ($hasil === 'off'): ?>
            <div class="alert alert-info small py-2 rounded-3"><i class="bi bi-check-circle me-1"></i>Mode maintenance berhasil dimatikan. Website kembali normal.</div>
        <?php elseif ($hasil === 'error'): ?>
            <div class="alert alert-danger small py-2 rounded-3"><i class="bi bi-x-circle me-1"></i>Password salah.</div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Pesan Maintenance (opsional)</label>
                <textarea name="message" class="form-control rounded-3" rows="2" placeholder="Contoh: Update fitur baru hingga pukul 15:00 WIB..."><?= htmlspecialchars($pesan) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control rounded-3" placeholder="Masukkan password untuk konfirmasi" required>
            </div>
            <button type="submit" name="toggle" class="btn w-100 rounded-3 fw-semibold <?= $is_on ? 'btn-success' : 'btn-warning text-dark' ?>">
                <i class="bi <?= $is_on ? 'bi-play-circle' : 'bi-stop-circle' ?> me-1"></i>
                <?= $is_on ? 'Matikan Maintenance' : 'Aktifkan Maintenance' ?>
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="./" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
