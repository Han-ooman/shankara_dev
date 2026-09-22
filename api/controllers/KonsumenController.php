<?php
// api/controllers/KonsumenController.php - Konsumen management

require_once __DIR__ . '/BaseController.php';

class KonsumenController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(name_lengkap LIKE ? OR no_ktp LIKE ? OR no_whatsapp LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT id_konsumen, name_lengkap, no_ktp, pekerjaan, no_whatsapp, alamat_ktp
             FROM data_konsumen $whereSql ORDER BY name_lengkap",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("SELECT * FROM data_konsumen WHERE id_konsumen = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Konsumen');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'name_lengkap' => 'required|min:2|max:150',
            'no_ktp' => 'max:30',
            'pekerjaan' => 'max:100',
            'no_whatsapp' => 'max:30',
            'alamat_ktp' => 'max:500'
        ]);

        $stmt = $this->db->prepare("INSERT INTO data_konsumen (name_lengkap, no_ktp, pekerjaan, no_whatsapp, alamat_ktp) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $data['name_lengkap'], $data['no_ktp'] ?? '', $data['pekerjaan'] ?? '', $data['no_whatsapp'] ?? '', $data['alamat_ktp'] ?? '');
        $stmt->execute();

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'Konsumen', "Tambah konsumen: {$data['name_lengkap']}", ['id' => $id]);
        Response::success(['id_konsumen' => $id], 'Konsumen dibuat', 201);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'name_lengkap' => 'required|min:2|max:150',
            'no_ktp' => 'max:30',
            'pekerjaan' => 'max:100',
            'no_whatsapp' => 'max:30',
            'alamat_ktp' => 'max:500'
        ]);

        $stmt = $this->db->prepare("UPDATE data_konsumen SET name_lengkap=?, no_ktp=?, pekerjaan=?, no_whatsapp=?, alamat_ktp=? WHERE id_konsumen=?");
        $stmt->bind_param('sssssi', $data['name_lengkap'], $data['no_ktp'] ?? '', $data['pekerjaan'] ?? '', $data['no_whatsapp'] ?? '', $data['alamat_ktp'] ?? '', $id);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Konsumen');
        $this->logActivity('UPDATE', 'Konsumen', "Update konsumen id $id", $data);
        Response::success(null, 'Konsumen diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_konsumen WHERE id_konsumen = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Konsumen');
        $this->logActivity('DELETE', 'Konsumen', "Hapus konsumen id $id");
        Response::success(null, 'Konsumen dihapus');
    }

    public function withTransactions(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT t.*, r.no_blok, c.cluster_name, t2.tipe_rumah
            FROM data_transaksi t
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            JOIN data_tipe_rumah t2 ON t2.tipe_rumah = r.tipe_rumah
            WHERE t.id_konsumen = ?
            ORDER BY t.tgl_booking DESC
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public function search(): void {
        $this->auth();
        $q = Request::search();
        if (mb_strlen($q) < 2) Response::success([]);
        $stmt = $this->db->prepare("SELECT id_konsumen, name_lengkap, no_ktp, no_whatsapp FROM data_konsumen WHERE name_lengkap LIKE ? OR no_ktp LIKE ? LIMIT 20");
        $term = "%$q%";
        $stmt->bind_param('ss', $term, $term);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }
}