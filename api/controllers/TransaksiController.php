<?php
// api/controllers/TransaksiController.php - Transaksi management

require_once __DIR__ . '/BaseController.php';

class TransaksiController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $clusterId = Request::input('cluster_id');
        $status = Request::input('status_konsumen');
        $skema = Request::input('skema_bayar');
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];

        if ($search) {
            $where[] = "(k.name_lengkap LIKE ? OR r.no_blok LIKE ? OR t.id_transaksi LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($clusterId) {
            $where[] = "r.id_cluster = ?";
            $params[] = $clusterId;
        }
        if ($status) {
            $where[] = "t.status_konsumen = ?";
            $params[] = $status;
        }
        if ($skema) {
            $where[] = "t.skema_bayar = ?";
            $params[] = $skema;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT t.id_transaksi, t.tgl_booking, t.id_konsumen, k.name_lengkap, t.id_rumah, r.no_blok,
                    c.cluster_name, r.tipe_rumah, r.luas_tanah, r.luas_bangunan, r.harga_jual,
                    t.skema_bayar, t.skema_akad, t.status_konsumen, t.status_bank,
                    t.tanggal_akad, t.deadline_berkas
             FROM data_transaksi t
             JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
             JOIN data_rumah r ON r.id_rumah = t.id_rumah
             JOIN data_cluster c ON c.id_cluster = r.id_cluster
             $whereSql ORDER BY t.id_transaksi DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT t.*, k.name_lengkap, k.no_ktp, k.no_whatsapp, k.pekerjaan, k.alamat_ktp,
                   r.no_blok, c.cluster_name, r.tipe_rumah, r.luas_tanah, r.luas_bangunan, r.harga_jual
            FROM data_transaksi t
            JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE t.id_transaksi = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Transaksi');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'tgl_booking' => 'required|date',
            'id_konsumen' => 'required|integer',
            'id_rumah' => 'required|integer',
            'skema_bayar' => 'required|in:Cash Tempo,KPR Komersil,KPR Subsidi',
            'skema_akad' => 'required|max:100',
            'status_konsumen' => 'in:PROSES,ACC - AKAD,AKAD BANK,BATAL',
            'status_bank' => 'max:100',
            'bank_tujuan' => 'max:100',
            'deadline_berkas' => 'date',
            'tgl_wawancara' => 'date'
        ]);

        // Cek unit masih tersedia
        $stmt = $this->db->prepare("SELECT status_unit FROM data_rumah WHERE id_rumah = ?");
        $stmt->bind_param('i', $data['id_rumah']);
        $stmt->execute();
        $rumah = $stmt->get_result()->fetch_assoc();
        if (!$rumah) Response::error('Unit rumah tidak ditemukan', 404);
        if ($rumah['status_unit'] !== 'tersedia') Response::error('Unit rumah tidak tersedia', 400);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO data_transaksi (tgl_booking, id_konsumen, id_rumah, skema_bayar, skema_akad, status_konsumen, status_bank, bank_tujuan, deadline_berkas, tgl_wawancara) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('siisssssss', $data['tgl_booking'], $data['id_konsumen'], $data['id_rumah'], $data['skema_bayar'], $data['skema_akad'], $data['status_konsumen'] ?? 'PROSES', $data['status_bank'] ?? '', $data['bank_tujuan'] ?? '', $data['deadline_berkas'] ?? null, $data['tgl_wawancara'] ?? null);
            $stmt->execute();

            $id = $this->db->insertId();

            // Update status rumah jadi booking
            $this->db->execute("UPDATE data_rumah SET status_unit = 'booking' WHERE id_rumah = ?", [$data['id_rumah']]);

            $this->db->commit();
            $this->logActivity('CREATE', 'Transaksi', "Booking baru: {$data['id_rumah']} untuk konsumen {$data['id_konsumen']}", ['id_transaksi' => $id]);
            Response::success(['id_transaksi' => $id], 'Transaksi dibuat', 201);
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'tgl_booking' => 'date',
            'skema_bayar' => 'in:Cash Tempo,KPR Komersil,KPR Subsidi',
            'skema_akad' => 'max:100',
            'status_konsumen' => 'in:PROSES,ACC - AKAD,AKAD BANK,BATAL',
            'status_bank' => 'max:100',
            'bank_tujuan' => 'max:100',
            'deadline_berkas' => 'date',
            'tgl_wawancara' => 'date',
            'tanggal_akad' => 'date'
        ]);

        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "$k = ?";
            $params[] = $v;
        }
        if (!$set) Response::error('Tidak ada data untuk diupdate', 400);
        $params[] = $id;

        $stmt = $this->db->prepare("UPDATE data_transaksi SET " . implode(', ', $set) . " WHERE id_transaksi = ?");
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Transaksi');
        $this->logActivity('UPDATE', 'Transaksi', "Update transaksi id $id", $data);
        Response::success(null, 'Transaksi diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        // Cek pembayaran
        $stmt = $this->db->prepare("SELECT 1 FROM data_pembayaran WHERE id_transaksi = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            Response::error('Tidak bisa hapus transaksi yang sudah punya pembayaran', 400);
        }

        // Get id_rumah before delete
        $stmt = $this->db->prepare("SELECT id_rumah FROM data_transaksi WHERE id_transaksi = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $idRumah = $row['id_rumah'] ?? null;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM data_transaksi WHERE id_transaksi = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($this->db->affectedRows() === 0) Response::notFound('Transaksi');

            // Update rumah kembali tersedia jika tidak ada transaksi lain
            if ($idRumah) {
                $stmt = $this->db->prepare("SELECT 1 FROM data_transaksi WHERE id_rumah = ?");
                $stmt->bind_param('i', $idRumah);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    $this->db->execute("UPDATE data_rumah SET status_unit = 'tersedia' WHERE id_rumah = ?", [$idRumah]);
                }
            }

            $this->db->commit();
            $this->logActivity('DELETE', 'Transaksi', "Hapus transaksi id $id");
            Response::success(null, 'Transaksi dihapus');
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateStatus(int $id): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'status_konsumen' => 'required|in:PROSES,ACC - AKAD,AKAD BANK,BATAL',
            'status_bank' => 'max:100',
            'tanggal_akad' => 'date'
        ]);

        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "$k = ?";
            $params[] = $v;
        }
        $params[] = $id;

        $stmt = $this->db->prepare("UPDATE data_transaksi SET " . implode(', ', $set) . " WHERE id_transaksi = ?");
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Transaksi');
        $this->logActivity('UPDATE_STATUS', 'Transaksi', "Ubah status transaksi id $id", $data);
        Response::success(null, 'Status transaksi diperbarui');
    }

    public function byKonsumen(int $idKonsumen): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT t.*, r.no_blok, c.cluster_name, r.tipe_rumah, r.harga_jual
            FROM data_transaksi t
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE t.id_konsumen = ?
            ORDER BY t.id_transaksi DESC
        ");
        $stmt->bind_param('i', $idKonsumen);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public function stats(): void {
        $this->auth();
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status_konsumen = 'PROSES' THEN 1 ELSE 0 END) as proses,
                SUM(CASE WHEN status_konsumen = 'ACC - AKAD' THEN 1 ELSE 0 END) as acc_akad,
                SUM(CASE WHEN status_konsumen = 'AKAD BANK' THEN 1 ELSE 0 END) as akad_bank,
                SUM(CASE WHEN status_konsumen = 'BATAL' THEN 1 ELSE 0 END) as batal
            FROM data_transaksi
        ");
        Response::success($stmt->fetch_assoc());
    }
}