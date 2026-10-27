<?php
require_once __DIR__ . '/includes/role_access.php';
$currentPage = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$__role = $_SESSION['role'] ?? '';
role_guard();
?>
<!-- SIDEBAR LEFT -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="brand-logo">
            <img src="assets/images/favicon/favicon.png" alt="Shankara Dev" style="width: 30px; height: 30px; object-fit: contain;">
            <span>Shankara Dev</span>
        </div>
    </div>

    <div class="sidebar-content">
        <!-- KELOMPOK 1: MENU UTAMA -->
        <div class="text-uppercase px-3 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Menu Utama</div>
        <?php if (role_menu_izin($__role, 'index.php')): ?>
        <a href="./" class="nav-link-custom <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'prospek.php')): ?>
        <a href="prospek.php" class="nav-link-custom <?= $currentPage === 'prospek.php' ? 'active' : '' ?>">
            <i class="bi bi-person-plus"></i> Prospek Konsumen
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'konsumen.php')): ?>
        <a href="konsumen.php" class="nav-link-custom <?= $currentPage === 'konsumen.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Konsumen
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'cluster.php')): ?>
        <a href="cluster.php" class="nav-link-custom <?= $currentPage === 'cluster.php' ? 'active' : '' ?>">
            <i class="bi bi-geo-alt"></i> Data Cluster
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'unit.php')): ?>
        <a href="unit.php" class="nav-link-custom <?= $currentPage === 'unit.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door"></i> Data Unit
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'tipe_rumah.php')): ?>
        <a href="tipe_rumah.php" class="nav-link-custom <?= $currentPage === 'tipe_rumah.php' ? 'active' : '' ?>"><i class="bi bi-houses"></i> Tipe Rumah & Paket</a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'simulasi_kredit.php')): ?>
        <a href="simulasi_kredit.php" class="nav-link-custom <?= $currentPage === 'simulasi_kredit.php' ? 'active' : '' ?>">
            <i class="bi bi-calculator"></i> Simulasi Kredit KPR
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'transaksi.php')): ?>
        <a href="transaksi.php" class="nav-link-custom <?= $currentPage === 'transaksi.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text"></i> Transaksi
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'rencana_akad.php')): ?>
        <a href="rencana_akad.php" class="nav-link-custom <?= $currentPage === 'rencana_akad.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-event"></i> Rencana Akad
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'bi_checking.php')): ?>
        <a href="bi_checking.php" class="nav-link-custom <?= $currentPage === 'bi_checking.php' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-check"></i> BI Checking
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'laporan_harian.php')): ?>
        <a href="laporan_harian.php" class="nav-link-custom <?= $currentPage === 'laporan_harian.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Laporan Harian
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'laporan_mingguan.php')): ?>
        <a href="laporan_mingguan.php" class="nav-link-custom <?= $currentPage === 'laporan_mingguan.php' || $currentPage === 'cetak_laporan_mingguan.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-week"></i> Laporan Mingguan
        </a>
        <?php endif; ?>

        <!-- KELOMPOK 2: PEMBERKASAN & LAPANGAN -->
        <?php if (role_menu_izin($__role, 'pemberkasan.php') || role_menu_izin($__role, 'status_kpr.php') || role_menu_izin($__role, 'pembangunan.php') || role_menu_izin($__role, 'laporan_pembangunan.php') || role_menu_izin($__role, 'serah_terima.php')): ?>
        <div class="text-uppercase px-3 mt-4 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Pemberkasan & Lapangan</div>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'pemberkasan.php')): ?>
        <a href="pemberkasan.php" class="nav-link-custom <?= $currentPage === 'pemberkasan.php' ? 'active' : '' ?>">
            <i class="bi bi-folder-check"></i> Pemberkasan KPR
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'status_kpr.php')): ?>
        <a href="status_kpr.php" class="nav-link-custom <?= $currentPage === 'status_kpr.php' ? 'active' : '' ?>">
            <i class="bi bi-patch-check"></i> Status KPR
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'pembangunan.php')): ?>
        <a href="pembangunan.php" class="nav-link-custom <?= $currentPage === 'pembangunan.php' ? 'active' : '' ?>">
            <i class="bi bi-hammer"></i> Progres Pembangunan
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'laporan_pembangunan.php')): ?>
        <a href="laporan_pembangunan.php" class="nav-link-custom <?= $currentPage === 'laporan_pembangunan.php' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-data"></i> Laporan Status Pembangunan
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'serah_terima.php')): ?>
        <a href="serah_terima.php" class="nav-link-custom <?= $currentPage === 'serah_terima.php' ? 'active' : '' ?>">
            <i class="bi bi-key"></i> Serah Terima (BAST)
        </a>
        <?php endif; ?>

        <!-- KELOMPOK 3: KEUANGAN & MARKETING -->
        <?php if (role_menu_izin($__role, 'pembayaran.php') || role_menu_izin($__role, 'keuangan.php') || role_menu_izin($__role, 'marketing.php') || role_menu_izin($__role, 'pembatalan_unit.php') || role_menu_izin($__role, 'laporan.php')): ?>
        <div class="text-uppercase px-3 mt-4 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Keuangan & Marketing</div>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'keuangan.php')): ?>
        <a href="keuangan.php" class="nav-link-custom <?= $currentPage === 'keuangan.php' ? 'active' : '' ?>"><i class="bi bi-cash-stack"></i> Keuangan Perusahaan</a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'pembayaran.php')): ?>
        <a href="pembayaran.php" class="nav-link-custom <?= $currentPage === 'pembayaran.php' ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Kwitansi & Tagihan
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'marketing.php')): ?>
        <a href="marketing.php" class="nav-link-custom <?= $currentPage === 'marketing.php' ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i> Tim Marketing
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'pembatalan_unit.php')): ?>
        <a href="pembatalan_unit.php" class="nav-link-custom <?= $currentPage === 'pembatalan_unit.php' || $currentPage === 'cetak_pembatalan.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-x"></i> Pembatalan Unit
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'laporan.php')): ?>
        <a href="laporan.php" class="nav-link-custom <?= $currentPage === 'laporan.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Laporan Penjualan
        </a>
        <?php endif; ?>

        <!-- KELOMPOK 4: PENGATURAN -->
        <?php if (role_menu_izin($__role, 'karyawan.php') || role_menu_izin($__role, 'gaji.php') || role_menu_izin($__role, 'pengaturan.php') || role_menu_izin($__role, 'log_aktivitas.php') || role_menu_izin($__role, 'pengingat.php') || role_menu_izin($__role, 'sinkronisasi.php')): ?>
        <div class="text-uppercase px-3 mt-4 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Pengaturan</div>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'karyawan.php')): ?>
        <a href="karyawan.php" class="nav-link-custom <?= $currentPage === 'karyawan.php' ? 'active' : '' ?>"><i class="bi bi-person-gear"></i> Data Karyawan</a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'gaji.php')): ?>
        <a href="gaji.php" class="nav-link-custom <?= in_array($currentPage, ['gaji.php', 'cetak_slip_gaji.php']) ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i> Slip Gaji</a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'pengaturan.php')): ?>
        <a href="pengaturan.php" class="nav-link-custom <?= $currentPage === 'pengaturan.php' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> Pengaturan Sistem
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'log_aktivitas.php')): ?>
        <a href="log_aktivitas.php" class="nav-link-custom <?= $currentPage === 'log_aktivitas.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Data Log
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'pengingat.php')): ?>
        <a href="pengingat.php" class="nav-link-custom <?= $currentPage === 'pengingat.php' ? 'active' : '' ?>">
            <i class="bi bi-bell"></i> Pengingat & Notifikasi
        </a>
        <?php endif; ?>
        <?php if (role_menu_izin($__role, 'sinkronisasi.php')): ?>
        <a href="sinkronisasi.php" class="nav-link-custom <?= $currentPage === 'sinkronisasi.php' ? 'active' : '' ?>">
            <i class="bi bi-arrow-left-right"></i> Sinkronisasi Data
        </a>
        <?php endif; ?>

        <!-- KELOMPOK 5: AKUN -->
        <div class="text-uppercase px-3 mt-4 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Akun</div>
        <a href="login.php" class="nav-link-custom text-danger" data-bs-toggle="modal" data-bs-target="#modalLogout">
            <i class="bi bi-box-arrow-right"></i> Keluar / Logout
        </a>
    </div>

    <!-- FOOTER SIDEBAR -->
    <div class="sidebar-footer p-3 text-white rounded-4 mt-3" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
        <i class="bi bi-shield-check fs-3 mb-2 d-block"></i>
        <h6 class="fw-bold mb-1" style="font-size: 0.9rem;">Sistem Database</h6>
        <p class="mb-0" style="font-size: 0.75rem; opacity: 0.8;">Data terintegrasi penuh ke MySQL.</p>
    </div>
</aside>
<!-- MODAL KONFIRMASI LOGOUT -->
<div class="modal fade" id="modalLogout" tabindex="-1" aria-labelledby="modalLogoutLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2.5rem;"></i>
                <h5 class="fw-bold mt-3">Yakin mau keluar?</h5>
                <p class="text-muted">Sesi Anda akan diakhiri.</p>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <button class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <a href="logout.php" class="btn btn-danger rounded-pill px-4">Ya, Keluar</a>
                </div>
            </div>
        </div>
    </div>
</div>
