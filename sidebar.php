<?php
$currentPage = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
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
        <a href="./" class="nav-link-custom <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <a href="prospek.php" class="nav-link-custom <?= $currentPage === 'prospek.php' ? 'active' : '' ?>">
            <i class="bi bi-person-plus"></i> Prospek Konsumen
        </a>
        <a href="konsumen.php" class="nav-link-custom <?= $currentPage === 'konsumen.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Konsumen
        </a>
        <a href="cluster.php" class="nav-link-custom <?= $currentPage === 'cluster.php' ? 'active' : '' ?>">
            <i class="bi bi-geo-alt"></i> Data Cluster
        </a>
        <a href="unit.php" class="nav-link-custom <?= $currentPage === 'unit.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door"></i> Data Unit
        </a>
        <a href="tipe_rumah.php" class="nav-link-custom <?= $currentPage === 'tipe_rumah.php' ? 'active' : '' ?>"><i class="bi bi-houses"></i> Tipe Rumah & Paket</a>
        <a href="transaksi.php" class="nav-link-custom <?= $currentPage === 'transaksi.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text"></i> Transaksi
        </a>
        <a href="rencana_akad.php" class="nav-link-custom <?= $currentPage === 'rencana_akad.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-event"></i> Rencana Akad
        </a>
        <a href="bi_checking.php" class="nav-link-custom <?= $currentPage === 'bi_checking.php' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-check"></i> BI Checking
        </a>
        <a href="laporan_harian.php" class="nav-link-custom <?= $currentPage === 'laporan_harian.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Laporan Harian
        </a>

        <!-- KELOMPOK 2: PEMBERKASAN & LAPANGAN -->
        <div class="text-uppercase px-3 mt-4 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Pemberkasan & Lapangan</div>
        <a href="pemberkasan.php" class="nav-link-custom <?= $currentPage === 'pemberkasan.php' ? 'active' : '' ?>">
            <i class="bi bi-folder-check"></i> Pemberkasan KPR
        </a>
        <a href="status_kpr.php" class="nav-link-custom <?= $currentPage === 'status_kpr.php' ? 'active' : '' ?>">
            <i class="bi bi-patch-check"></i> Status KPR
        </a>
        <a href="pembangunan.php" class="nav-link-custom <?= $currentPage === 'pembangunan.php' ? 'active' : '' ?>">
            <i class="bi bi-hammer"></i> Progres Pembangunan
        </a>
        <a href="laporan_pembangunan.php" class="nav-link-custom <?= $currentPage === 'laporan_pembangunan.php' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-data"></i> Laporan Status Pembangunan
        </a>
        <a href="serah_terima.php" class="nav-link-custom <?= $currentPage === 'serah_terima.php' ? 'active' : '' ?>">
            <i class="bi bi-key"></i> Serah Terima (BAST)
        </a>

        <!-- KELOMPOK 3: KEUANGAN & MARKETING -->
        <div class="text-uppercase px-3 mt-4 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Keuangan & Marketing</div>
        <a href="pembayaran.php" class="nav-link-custom <?= $currentPage === 'pembayaran.php' ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Tagihan & Kwitansi
        </a>
        <a href="keuangan.php" class="nav-link-custom <?= $currentPage === 'keuangan.php' ? 'active' : '' ?>"><i class="bi bi-cash-stack"></i> Keuangan Perusahaan</a>
        <a href="marketing.php" class="nav-link-custom <?= $currentPage === 'marketing.php' ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i> Tim Marketing
        </a>
        <a href="pembatalan_unit.php" class="nav-link-custom <?= $currentPage === 'pembatalan_unit.php' || $currentPage === 'cetak_pembatalan.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-x"></i> Pembatalan Unit
        </a>
        <a href="laporan.php" class="nav-link-custom <?= $currentPage === 'laporan.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Laporan Penjualan
        </a>

        <!-- KELOMPOK 4: PENGATURAN -->
        <div class="text-uppercase px-3 mt-4 mb-2 text-muted-custom" style="font-size: 0.7rem; letter-spacing: 1px;">Pengaturan</div>
        <a href="karyawan.php" class="nav-link-custom <?= $currentPage === 'karyawan.php' ? 'active' : '' ?>"><i class="bi bi-person-gear"></i> Data Karyawan</a>
        <a href="gaji.php" class="nav-link-custom <?= in_array($currentPage, ['gaji.php', 'cetak_slip_gaji.php']) ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i> Slip Gaji</a>
        <a href="pengaturan.php" class="nav-link-custom <?= $currentPage === 'pengaturan.php' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> Pengaturan Sistem
        </a>
        <a href="log_aktivitas.php" class="nav-link-custom <?= $currentPage === 'log_aktivitas.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Data Log
        </a>

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
