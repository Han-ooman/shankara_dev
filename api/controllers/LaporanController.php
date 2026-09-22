<?php
// api/controllers/LaporanController.php - Dashboard & Reports

require_once __DIR__ . '/BaseController.php';

class LaporanController extends BaseController {
    public function dashboard(): void {
        $this->auth();

        // Ringkasan transaksi
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_transaksi,
                SUM(CASE WHEN status_konsumen = 'PROSES' THEN 1 ELSE 0 END) as proses,
                SUM(CASE WHEN status_konsumen = 'ACC - AKAD' THEN 1 ELSE 0 END) as acc_akad,
                SUM(CASE WHEN status_konsumen = 'AKAD BANK' THEN 1 ELSE 0 END) as akad_bank,
                SUM(CASE WHEN status_konsumen = 'BATAL' THEN 1 ELSE 0 END) as batal
            FROM data_transaksi
        ");
        $transaksi = $stmt->fetch_assoc();

        // Ringkasan unit
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_unit,
                SUM(CASE WHEN status_unit = 'tersedia' THEN 1 ELSE 0 END) as tersedia,
                SUM(CASE WHEN status_unit = 'booking' THEN 1 ELSE 0 END) as booking,
                SUM(CASE WHEN status_unit = 'terjual' THEN 1 ELSE 0 END) as terjual
            FROM data_rumah
        ");
        $unit = $stmt->fetch_assoc();

        // Ringkasan pembayaran bulan ini
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(jumlah_bayar), 0) as total_bulan_ini
            FROM data_pembayaran
            WHERE MONTH(tgl_bayar) = MONTH(CURDATE()) AND YEAR(tgl_bayar) = YEAR(CURDATE())
        ");
        $pembayaran = $stmt->fetch_assoc();

        // Ringkasan per cluster
        $stmt = $this->db->query("
            SELECT c.cluster_name,
                COUNT(r.id_rumah) as total_unit,
                SUM(CASE WHEN r.status_unit = 'tersedia' THEN 1 ELSE 0 END) as tersedia,
                SUM(CASE WHEN r.status_unit = 'booking' THEN 1 ELSE 0 END) as booking,
                SUM(CASE WHEN r.status_unit = 'terjual' THEN 1 ELSE 0 END) as terjual
            FROM data_cluster c
            LEFT JOIN data_rumah r ON r.id_cluster = c.id_cluster
            GROUP BY c.id_cluster
        ");
        $cluster = $stmt->fetch_all(MYSQLI_ASSOC);

        // Transaksi terbaru
        $stmt = $this->db->query("
            SELECT t.id_transaksi, t.tgl_booking, k.name_lengkap, r.no_blok, c.cluster_name, t.status_konsumen
            FROM data_transaksi t
            JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            ORDER BY t.id_transaksi DESC LIMIT 10
        ");
        $recent = $stmt->fetch_all(MYSQLI_ASSOC);

        Response::success([
            'transaksi' => $transaksi,
            'unit' => $unit,
            'pembayaran_bulan_ini' => $pembayaran,
            'cluster' => $cluster,
            'recent_transactions' => $recent
        ]);
    }

    public function penjualan(): void {
        $this->auth();
        $bulan = Request::input('bulan', date('m'));
        $tahun = Request::input('tahun', date('Y'));

        $stmt = $this->db->prepare("
            SELECT DATE(tgl_booking) as tanggal, COUNT(*) as jumlah, SUM(r.harga_jual) as nilai
            FROM data_transaksi t
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            WHERE MONTH(tgl_booking) = ? AND YEAR(tgl_booking) = ? AND t.status_konsumen != 'BATAL'
            GROUP BY DATE(tgl_booking)
            ORDER BY tanggal
        ");
        $stmt->bind_param('ii', $bulan, $tahun);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public function pembayaran(): void {
        $this->auth();
        $bulan = Request::input('bulan', date('m'));
        $tahun = Request::input('tahun', date('Y'));

        $stmt = $this->db->prepare("
            SELECT DATE(tgl_bayar) as tanggal,
                SUM(CASE WHEN jenis_pembayaran = 'Booking Fee' THEN jumlah_bayar ELSE 0 END) as booking_fee,
                SUM(CASE WHEN jenis_pembayaran = 'Uang Muka (DP)' THEN jumlah_bayar ELSE 0 END) as dp,
                SUM(CASE WHEN jenis_pembayaran = 'Cicilan' THEN jumlah_bayar ELSE 0 END) as cicilan,
                SUM(CASE WHEN jenis_pembayaran = 'Pelunasan' THEN jumlah_bayar ELSE 0 END) as pelunasan,
                SUM(jumlah_bayar) as total
            FROM data_pembayaran
            WHERE MONTH(tgl_bayar) = ? AND YEAR(tgl_bayar) = ?
            GROUP BY DATE(tgl_bayar)
            ORDER BY tanggal
        ");
        $stmt->bind_param('ii', $bulan, $tahun);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public function pembangunan(): void {
        $this->auth();
        $clusterId = Request::input('cluster_id');
        $status = Request::input('status');

        $where = [];
        $params = [];
        if ($clusterId) { $where[] = "r.id_cluster = ?"; $params[] = $clusterId; }
        if ($status) { $where[] = "p.status_pembangunan = ?"; $params[] = $status; }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("
            SELECT p.*, r.no_blok, c.cluster_name, r.tipe_rumah, k.name_lengkap
            FROM data_pembangunan p
            JOIN data_rumah r ON r.id_rumah = p.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            LEFT JOIN data_transaksi tr ON tr.id_rumah = r.id_rumah AND tr.status_konsumen != 'BATAL'
            LEFT JOIN data_konsumen k ON k.id_konsumen = tr.id_konsumen
            $whereSql ORDER BY p.id_pembangunan DESC
        ");
        if ($params) {
            $types = str_repeat('i', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public function harian(): void {
        $this->auth();
        $tanggal = Request::input('tanggal', date('Y-m-d'));

        // Transaksi harian
        $stmt = $this->db->prepare("
            SELECT t.*, k.name_lengkap, r.no_blok, c.cluster_name
            FROM data_transaksi t
            JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE DATE(t.tgl_booking) = ?
            ORDER BY t.id_transaksi DESC
        ");
        $stmt->bind_param('s', $tanggal);
        $stmt->execute();
        $transaksi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Pembayaran harian
        $stmt = $this->db->prepare("
            SELECT p.*, t.id_transaksi, k.name_lengkap, r.no_blok
            FROM data_pembayaran p
            JOIN data_transaksi t ON t.id_transaksi = p.id_transaksi
            JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            WHERE DATE(p.tgl_bayar) = ?
            ORDER BY p.id_pembayaran DESC
        ");
        $stmt->bind_param('s', $tanggal);
        $stmt->execute();
        $pembayaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Summary
        $stmt = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM data_transaksi WHERE DATE(tgl_booking) = ?) as jml_transaksi,
                (SELECT COALESCE(SUM(jumlah_bayar),0) FROM data_pembayaran WHERE DATE(tgl_bayar) = ?) as total_pembayaran
        ");
        $stmt->bind_param('ss', $tanggal, $tanggal);
        $stmt->execute();
        $summary = $stmt->fetch_assoc();

        Response::success([
            'tanggal' => $tanggal,
            'summary' => $summary,
            'transaksi' => $transaksi,
            'pembayaran' => $pembayaran
        ]);
    }
}