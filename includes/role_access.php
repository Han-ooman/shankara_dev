<?php

// ============================================================
// KONTROL AKSES BERDASARKAN ROLE USER
// Digunakan oleh sidebar.php untuk menyaring menu, dan sebagai
// guard otomatis agar halaman di luar role tidak bisa diakses
// langsung lewat URL.
// ============================================================

if (!function_exists('role_peta_halaman')) {
    /**
     * Peta halaman -> role yang boleh mengakses.
     * 'index.php' (Dashboard) boleh untuk semua role aktif.
     */
    function role_peta_halaman(): array {
        return [
            'index.php'              => ['Super Admin', 'Pengawas Lapangan', 'Kasir Keuangan', 'Admin Pemberkasan'],
            'prospek.php'            => ['Super Admin', 'Admin Pemberkasan'],
            'konsumen.php'           => ['Super Admin', 'Admin Pemberkasan'],
            'cluster.php'            => ['Super Admin', 'Pengawas Lapangan', 'Kasir Keuangan'],
            'unit.php'               => ['Super Admin', 'Pengawas Lapangan', 'Kasir Keuangan'],
            'tipe_rumah.php'         => ['Super Admin', 'Pengawas Lapangan', 'Admin Pemberkasan', 'Kasir Keuangan'],
            'transaksi.php'          => ['Super Admin', 'Kasir Keuangan'],
            'rencana_akad.php'       => ['Super Admin', 'Admin Pemberkasan', 'Kasir Keuangan'],
            'bi_checking.php'        => ['Super Admin', 'Admin Pemberkasan', 'Kasir Keuangan'],
            'laporan_harian.php'     => ['Super Admin', 'Admin Pemberkasan'],
            'laporan_mingguan.php'   => ['Super Admin', 'Kasir Keuangan', 'Admin Pemberkasan'],
            'pemberkasan.php'        => ['Super Admin', 'Admin Pemberkasan'],
            'status_kpr.php'         => ['Super Admin', 'Admin Pemberkasan'],
            'pembangunan.php'        => ['Super Admin', 'Pengawas Lapangan'],
            'laporan_pembangunan.php'=> ['Super Admin', 'Pengawas Lapangan'],
            'serah_terima.php'       => ['Super Admin', 'Pengawas Lapangan'],
            'pembayaran.php'         => ['Super Admin', 'Kasir Keuangan'],
            'keuangan.php'           => ['Super Admin', 'Kasir Keuangan'],
            'marketing.php'          => ['Super Admin', 'Kasir Keuangan'],
            'pembatalan_unit.php'    => ['Super Admin', 'Kasir Keuangan'],
            'laporan.php'            => ['Super Admin', 'Kasir Keuangan'],
            'karyawan.php'           => ['Super Admin'],
            'gaji.php'               => ['Super Admin', 'Kasir Keuangan'],
            'pengaturan.php'         => ['Super Admin'],
            'pengingat.php'          => ['Super Admin', 'Admin Pemberkasan', 'Kasir Keuangan'],
            'log_aktivitas.php'      => ['Super Admin'],
            'sinkronisasi.php'       => ['Super Admin'],
        ];
    }
}

if (!function_exists('role_boleh_akses')) {
    /**
     * Cek apakah $page boleh diakses oleh $role.
     * Role 'Super Admin' selalu boleh untuk semua halaman yang sudah terdaftar.
     */
    function role_boleh_akses(?string $role, string $page): bool {
        if (empty($role)) return false;
        $role = trim($role);
        $map  = role_peta_halaman();

        // Halaman yang tidak terdaftar di peta: fallback -> hanya Super Admin.
        if (!isset($map[$page])) return $role === 'Super Admin';

        $allow = $map[$page];
        // Super Admin masuk otomatis walau tidak dicantumkan eksplisit.
        if ($role === 'Super Admin') return true;

        return in_array($role, $allow, true);
    }
}

if (!function_exists('role_guard')) {
    /**
     * Guard: cek halaman saat ini harus sesuai role sesi.
     * Dipanggil dari sidebar.php (yang di-include hampir semua halaman).
     * Jika tidak berhak -> lempar ke dashboard (index.php) agar tidak blank.
     */
    function role_guard(): void {
        if (empty($_SESSION['status_login']) || $_SESSION['status_login'] !== true) return;

        $role = $_SESSION['role'] ?? '';
        $page = basename($_SERVER['SCRIPT_FILENAME'] ?? '');

        // Halaman login/logout/sesi & aset CSS/JS tidak dicek.
        $bypass = ['login.php', 'logout.php', 'cek_sesi.php', 'koneksi.php'];
        if (in_array($page, $bypass, true)) return;

        if (!role_boleh_akses($role, $page)) {
            // Tidak boleh: redirect ke dashboard (atau 403 jika sudah di dashboard).
            if ($page !== 'index.php') {
                header("Location: index.php");
                exit();
            }
            http_response_code(403);
            exit('403 Forbidden');
        }
    }
}

if (!function_exists('role_menu_izin')) {
    /**
     * Bungkus item menu sidebar: hanya render jika role berhak.
     * Super Admin melihat semua.
     */
    function role_menu_izin(?string $role, string $page): bool {
        $role = $_SESSION['role'] ?? $role;
        return role_boleh_akses($role, $page);
    }
}