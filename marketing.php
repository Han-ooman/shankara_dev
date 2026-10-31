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

if (isset($_POST['tambah_marketing'])) {
    $nama = sdb_real_escape_string($koneksi, $_POST['nama_marketing']);
    $no_hp = sdb_real_escape_string($koneksi, $_POST['no_hp']);
    $email = sdb_real_escape_string($koneksi, $_POST['email']);

    sdb_query($koneksi, "INSERT INTO data_marketing (nama_marketing, no_hp, email) VALUES ('$nama', '$no_hp', '$email')");
    header("Location: marketing.php");
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
    <title>Tim Marketing - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Tim Marketing / Sales</h4>
                <p class="text-muted small mb-0">Kelola data agen dan tim penjualan proyek perumahan.</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalMarketing"><i class="bi bi-plus-lg me-1"></i> Tambah Marketing</button>
        </div>

        <?php
        $qm = sdb_query($koneksi, "SELECT * FROM data_marketing ORDER BY id_marketing DESC");
        $jml_aktif = 0;
        $jml_total = 0;
        $marketing_list = [];
        while ($m = sdb_fetch_assoc($qm)) {
            $marketing_list[] = $m;
            $jml_total++;
            if ($m['status_aktif'] == 'Aktif') $jml_aktif++;
        }
        ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="content-card text-center py-3">
                    <div class="fw-bold fs-4" style="color: var(--primary-blue);"><?= $jml_total ?></div>
                    <small class="text-muted">Total Marketing</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card text-center py-3">
                    <div class="fw-bold fs-4 text-success"><?= $jml_aktif ?></div>
                    <small class="text-muted">Status Aktif</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card text-center py-3">
                    <div class="fw-bold fs-4 text-secondary"><?= $jml_total - $jml_aktif ?></div>
                    <small class="text-muted">Nonaktif</small>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <?php
            $warna = ['#1e3a8a', '#1565c0', '#6a1b9a', '#c62828', '#ef6c00', '#00838f', '#2563eb', '#ad1457'];
            foreach ($marketing_list as $idx => $m) {
                $inisial = '';
                $nama_parts = explode(' ', trim($m['nama_marketing']));
                foreach ($nama_parts as $np) {
                    if (strlen($np) > 0) $inisial .= mb_strtoupper(mb_substr($np, 0, 1));
                    if (mb_strlen($inisial) >= 2) break;
                }
                $bg = $warna[$idx % count($warna)];
                $status = $m['status_aktif'] ?? 'Aktif';
                $badge_cls = ($status == 'Aktif') ? 'bg-success' : 'bg-secondary';
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="content-card h-100" style="border-left: 4px solid <?= $bg ?>;">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:48px;height:48px;background:<?= $bg ?>;font-size:1.1rem;">
                            <?= $inisial ?>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($m['nama_marketing']) ?></h6>
                                <span class="badge <?= $badge_cls ?> rounded-pill ms-2 flex-shrink-0"><?= $status ?></span>
                            </div>
                            <small class="text-muted">ID: <?= $m['id_marketing'] ?></small>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-whatsapp text-success"></i>
                            <?php $wa_clean = preg_replace('/[^0-9]/', '', $m['no_hp']); ?>
                            <a href="https://wa.me/<?= $wa_clean ?>" target="_blank" class="text-decoration-none text-dark small">
                                <?= htmlspecialchars($m['no_hp'] ?: '-') ?>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-envelope text-primary"></i>
                            <span class="small text-muted text-truncate"><?= htmlspecialchars($m['email'] ?: '-') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <?php if (empty($marketing_list)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people display-4 d-block mb-2"></i>
                Belum ada data marketing.
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- MODAL TAMBAH MARKETING -->
<div class="modal fade" id="modalMarketing" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tambah Tim Marketing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="marketing.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                        <input type="text" name="nama_marketing" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. HP / WhatsApp</label>
                        <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="tambah_marketing" class="btn text-white px-4" style="background-color: var(--primary-blue);">Simpan Data</button>
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
