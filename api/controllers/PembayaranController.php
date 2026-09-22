<?php
// api/controllers/PembayaranController.php - Pembayaran management

require_once __DIR__ . '/BaseController.php';

class PembayaranController extends BaseController {
    public function index(): void {
        $this->auth();
        $transaksiId = Request::input('id_transaksi');
        $jenis = Request::input('jenis_pembayaran');
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];
        if ($transaksiId) {
            $where[] = "p.id_transaksi = ?";
            $params[] = $transaksiId;
        }
        if ($jenis) {
            $where[] = "p.jenis_pembayaran = ?";
            $params[] = $jenis;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT p.*, t.id_transaksi, t.tgl_booking, k.name_lengkap, r.no_blok, c.cluster_name, r.tipe_rumah
             FROM data_pembayaran p
             JOIN data_transaksi t ON t.id_transaksi = p.id_transaksi
             JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
             JOIN data_rumah r ON r.id_rumah = t.id_rumah
             JOIN data_cluster c ON c.id_cluster = r.id_cluster
             $whereSql ORDER BY p.tgl_bayar DESC, p.id_pembayaran DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT p.*, t.id_transaksi, t.tgl_booking, k.name_lengkap, r.no_blok, c.cluster_name, r.tipe_rumah
            FROM data_pembayaran p
            JOIN data_transaksi t ON t.id_transaksi = p.id_transaksi
            JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE p.id_pembayaran = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Pembayaran');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin', 'Keuangan']);
        $data = $this->validate([
            'id_transaksi' => 'required|integer',
            'no_kwitansi' => 'required|max:50',
            'jenis_pembayaran' => 'required|in:Booking Fee,Uang Muka (DP),Cicilan,Pelunasan',
            'jumlah_bayar' => 'required|numeric',
            'tgl_bayar' => 'required|date',
            'metode_pembayaran' => 'in:Transfer,Cash',
            'keterangan' => 'max:255'
        ]);

        // Cek transaksi exists
        $stmt = $this->db->prepare("SELECT 1 FROM data_transaksi WHERE id_transaksi = ?");
        $stmt->bind_param('i', $data['id_transaksi']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) Response::notFound('Transaksi');

        // Cek no_kwitansi unik
        $stmt = $this->db->prepare("SELECT 1 FROM data_pembayaran WHERE no_kwitansi = ?");
        $stmt->bind_param('s', $data['no_kwitansi']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) Response::error('Nomor kwitansi sudah digunakan', 400);

        $stmt = $this->db->prepare("INSERT INTO data_pembayaran (id_transaksi, no_kwitansi, jenis_pembayaran, jumlah_bayar, tgl_bayar, metode_pembayaran, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssdss', $data['id_transaksi'], $data['no_kwitansi'], $data['jenis_pembayaran'], $data['jumlah_bayar'], $data['tgl_bayar'], $data['metode_pembayaran'] ?? 'Transfer', $data['keterangan'] ?? '');
        $stmt->execute();

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'Pembayaran', "Pembayaran {$data['jenis_pembayaran']} KW: {$data['no_kwitansi']}", ['id' => $id, 'transaksi' => $data['id_transaksi']]);
        Response::success(['id_pembayaran' => $id], 'Pembayaran dicatat', 201);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'jenis_pembayaran' => 'in:Booking Fee,Uang Muka (DP),Cicilan,Pelunasan',
            'jumlah_bayar' => 'numeric',
            'tgl_bayar' => 'date',
            'metode_pembayaran' => 'in:Transfer,Cash',
            'keterangan' => 'max:255'
        ]);

        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "$k = ?";
            $params[] = $v;
        }
        if (!$set) Response::error('Tidak ada data untuk diupdate', 400);
        $params[] = $id;

        $stmt = $this->db->prepare("UPDATE data_pembayaran SET " . implode(', ', $set) . " WHERE id_pembayaran = ?");
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Pembayaran');
        $this->logActivity('UPDATE', 'Pembayaran', "Update pembayaran id $id", $data);
        Response::success(null, 'Pembayaran diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_pembayaran WHERE id_pembayaran = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Pembayaran');
        $this->logActivity('DELETE', 'Pembayaran', "Hapus pembayaran id $id");
        Response::success(null, 'Pembayaran dihapus');
    }

    public function byTransaksi(int $transaksiId): void {
        $this->auth();
        $stmt = $this->db->prepare("SELECT * FROM data_pembayaran WHERE id_transaksi = ? ORDER BY tgl_bayar, id_pembayaran");
        $stmt->bind_param('i', $transaksiId);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public function summary(int $transaksiId): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN jenis_pembayaran = 'Booking Fee' THEN jumlah_bayar ELSE 0 END), 0) as total_booking_fee,
                COALESCE(SUM(CASE WHEN jenis_pembayaran = 'Uang Muka (DP)' THEN jumlah_bayar ELSE 0 END), 0) as total_dp,
                COALESCE(SUM(CASE WHEN jenis_pembayaran = 'Cicilan' THEN jumlah_bayar ELSE 0 END), 0) as total_cicilan,
                COALESCE(SUM(CASE WHEN jenis_pembayaran = 'Pelunasan' THEN jumlah_bayar ELSE 0 END), 0) as total_pelunasan,
                COALESCE(SUM(jumlah_bayar), 0) as grand_total
            FROM data_pembayaran WHERE id_transaksi = ?
        ");
        $stmt->bind_param('i', $transaksiId);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_assoc());
    }
}