<?php
/**
 * Helper: Status HOLD pada cluster untuk memblokir booking & pembayaran.
 *
 * Aturan:
 * - cluster_status = 'hold'  -> cluster DIHOLD, tidak bisa booking/pembayaran baru.
 * - Status lain                -> normal, boleh.
 */

// Normalisasi nama status dari DB
if (!function_exists('cluster_status_normalize')) {
    function cluster_status_normalize(?string $status): string
    {
        return strtolower(trim((string)$status));
    }
}

// True bila status cluster menandakan HOLD
if (!function_exists('cluster_is_hold')) {
    function cluster_is_hold(?string $status): bool
    {
        return in_array(cluster_status_normalize($status), ['hold', 'di hold', 'hold by sistem'], true);
    }
}

/**
 * Ambil id_cluster dari id_rumah
 */
if (!function_exists('cluster_id_dari_rumah')) {
    function cluster_id_dari_rumah($koneksi, int $id_rumah): int
    {
        if ($id_rumah <= 0) return 0;
        $res = sdb_query($koneksi, "SELECT id_cluster FROM data_rumah WHERE id_rumah = " . (int)$id_rumah . " LIMIT 1");
        if ($res && sdb_num_rows($res) > 0) {
            $row = sdb_fetch_assoc($res);
            return (int)($row['id_cluster'] ?? 0);
        }
        return 0;
    }
}

/**
 * Ambil info cluster (id + nama + status) dari id_rumah
 * @return array{id:int, nama:string, status:string, hold:bool}
 */
if (!function_exists('cluster_info_dari_rumah')) {
    function cluster_info_dari_rumah($koneksi, int $id_rumah): array
    {
        $default = ['id' => 0, 'nama' => '', 'status' => '', 'hold' => false];
        if ($id_rumah <= 0) return $default;

        $res = sdb_query(
            $koneksi,
            "SELECT c.id_cluster, COALESCE(c.cluster_name,'-') AS cluster_name, COALESCE(c.cluster_status,'') AS cluster_status
             FROM data_rumah r
             JOIN data_cluster c ON r.id_cluster = c.id_cluster
             WHERE r.id_rumah = " . (int)$id_rumah . " LIMIT 1"
        );
        if (!$res || sdb_num_rows($res) === 0) return $default;

        $row = sdb_fetch_assoc($res);
        return [
            'id'     => (int)($row['id_cluster'] ?? 0),
            'nama'   => (string)($row['cluster_name'] ?? '-'),
            'status' => (string)($row['cluster_status'] ?? ''),
            'hold'   => cluster_is_hold($row['cluster_status'] ?? ''),
        ];
    }
}

/**
 * Pesan notifikasi yang seluruhnya sudah di-escape agar aman ditampilkan.
 */
if (!function_exists('cluster_hold_pesan')) {
    function cluster_hold_pesan(string $nama_cluster): string
    {
        return 'Transaksi pada Cluster ' . $nama_cluster
             . ' sedang di HOLD oleh sistem, sehingga booking dan pembayaran baru tidak dapat dilakukan. '
             . 'Silakan hubungi Super Admin untuk membuka kembali cluster ini.';
    }
}

/**
 * Redirect + exit bila cluster di hold.
 * Dipakai di handler POST sebelum menyimpan data.
 */
if (!function_exists('cluster_hold_cek_rumah')) {
    function cluster_hold_cek_rumah($koneksi, int $id_rumah, string $redirect = 'transaksi.php'): void
    {
        $c = cluster_info_dari_rumah($koneksi, $id_rumah);
        if ($c['hold']) {
            header('Location: ' . $redirect . '?status=hold&cluster=' . rawurlencode($c['nama']));
            exit();
        }
    }
}
