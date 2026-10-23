<?php
// ============================================================
// KONFIGURASI SINKRONISASI DATA
//   OFFLINE : MySQL lokal (XAMPP)    -> $koneksi_offline
//   ONLINE  : Supabase (PostgreSQL)  -> $koneksi_online
//
// Hanya tabel BASE yang disinkronkan. View (data_rumah, data_cluster,
// data_konsumen, dll.) dibaca dari tabel base sehingga otomatis ikut terupdate.
//
// 'sync' => true  : ikut disinkronkan secara default
// 'sync' => false : opsional (aktifkan lewat --all di CLI / centang di UI)
// ============================================================

if (!function_exists('sync_daftar_tabel')) {
    /**
     * Daftar tabel + kebijakan sinkronisasi.
     */
    function sync_daftar_tabel(): array {
        static $daftar = [
            // ---------------- MASTER DATA ----------------
            'cluster' => [
                'label' => 'Data Cluster',
                'grup'  => 'Master Data',
                'sync'  => true,
            ],
            'rumah' => [
                'label' => 'Data Unit Rumah',
                'grup'  => 'Master Data',
                'sync'  => true,
            ],
            'data_tipe_rumah' => [
                'label' => 'Tipe Rumah',
                'grup'  => 'Master Data',
                'sync'  => true,
            ],
            'data_angsuran' => [
                'label' => 'Paket Angsuran',
                'grup'  => 'Master Data',
                'sync'  => true,
            ],
            'data_marketing' => [
                'label' => 'Tim Marketing',
                'grup'  => 'Master Data',
                'sync'  => true,
            ],
            'data_karyawan' => [
                'label' => 'Data Karyawan',
                'grup'  => 'Master Data',
                'sync'  => true,
            ],
            'data_gaji' => [
                'label' => 'Data Gaji Karyawan',
                'grup'  => 'Master Data',
                'sync'  => true,
            ],

            // ---------------- TRANSAKSI ----------------
            'konsumen' => [
                'label' => 'Data Konsumen',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'prospek' => [
                'label' => 'Data Prospek',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'data_transaksi' => [
                'label' => 'Data Transaksi',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'pembayaran' => [
                'label' => 'Pembayaran',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'data_pembayaran_detail' => [
                'label' => 'Detail Pembayaran',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'serah_terima' => [
                'label' => 'Serah Terima (BAST)',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'pembatalan_unit' => [
                'label' => 'Pembatalan Unit',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'pemberkasan' => [
                'label' => 'Pemberkasan KPR',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'rencana_akad' => [
                'label' => 'Rencana Akad',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'bi_checking' => [
                'label' => 'BI Checking',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],
            'pembangunan' => [
                'label' => 'Progres Pembangunan',
                'grup'  => 'Transaksi',
                'sync'  => true,
            ],

            // ---------------- KEUANGAN & LAPORAN ----------------
            'data_keuangan' => [
                'label' => 'Data Keuangan',
                'grup'  => 'Keuangan & Laporan',
                'sync'  => true,
            ],
            'data_laporan_harian' => [
                'label' => 'Laporan Harian',
                'grup'  => 'Keuangan & Laporan',
                'sync'  => true,
            ],

            // ---------------- SISTEM ----------------
            'pengaturan_sistem' => [
                'label' => 'Pengaturan Sistem',
                'grup'  => 'Sistem',
                'sync'  => true,
            ],
            'pengingat' => [
                'label' => 'Pengingat & Notifikasi',
                'grup'  => 'Sistem',
                'sync'  => true,
            ],
            'log_aktivitas' => [
                'label' => 'Log Aktivitas',
                'grup'  => 'Sistem',
                'sync'  => true,
            ],

            // ---------------- OPSIONAL (nonaktif default) ----------------
            'transaksi' => [
                'label' => 'Transaksi (tabel legacy)',
                'grup'  => 'Opsional',
                'sync'  => false,
            ],
            'users' => [
                'label' => 'User Login',
                'grup'  => 'Opsional',
                'sync'  => false,
            ],
            'user_sesi' => [
                'label' => 'Sesi Login',
                'grup'  => 'Opsional',
                'sync'  => false,
            ],
            'push_subscriptions' => [
                'label' => 'Notifikasi Push',
                'grup'  => 'Opsional',
                'sync'  => false,
            ],
        ];
        return $daftar;
    }
}

if (!function_exists('sync_kolom_waktu_preferensi')) {
    /**
     * Urutan prioritas kolom waktu, dipakai kebijakan konflik "newest".
     * Tabel tanpa kolom waktu -> fallback ke overwrite.
     */
    function sync_kolom_waktu_preferensi(): array {
        return [
            'updated_at', 'created_at', 'modified_at', 'last_updated',
            'tgl_update', 'tanggal_update', 'tgl_input', 'tgl_booking',
            'tanggal_akad', 'tgl_bayar', 'tgl_serah_terima', 'tgl_batal',
            'tgl_pengajuan', 'tanggal_gaji', 'tgl_mulai', 'tanggal',
            'tgl_daftar', 'tgl_check',
        ];
    }
}

if (!function_exists('sync_tabel_aktif')) {
    /**
     * Daftar nama tabel yang akan disinkronkan.
     * @param bool $semua true = ikut tabel opsional (users, transaksi, ...)
     * @param array $filter daftar tabel spesifik (opsional)
     */
    function sync_tabel_aktif(bool $semua = false, array $filter = []): array {
        $daftar = sync_daftar_tabel();
        $filter = array_values(array_filter(array_map('trim', $filter), fn($v) => $v !== ''));
        $out = [];
        foreach ($daftar as $nama => $cfg) {
            if ($filter) {
                if (!in_array($nama, $filter, true)) continue;
            } elseif (!$semua && empty($cfg['sync'])) {
                continue;
            }
            $out[] = $nama;
        }
        return $out;
    }
}

if (!function_exists('sync_label_tabel')) {
    function sync_label_tabel(string $nama): string {
        $daftar = sync_daftar_tabel();
        return $daftar[$nama]['label'] ?? $nama;
    }
}
