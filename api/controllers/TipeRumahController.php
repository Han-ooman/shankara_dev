<?php
// api/controllers/TipeRumahController.php - Tipe Rumah management

require_once __DIR__ . '/BaseController.php';

class TipeRumahController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(tipe_rumah LIKE ? OR keterangan LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT id_tipe, tipe_rumah, harga, dp_persen, dp_nominal, angsuran, tenor_bulan, persyaratan, keterangan, created_at
             FROM data_tipe_rumah $whereSql ORDER BY tipe_rumah",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("SELECT * FROM data_tipe_rumah WHERE id_tipe = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Tipe Rumah');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'tipe_rumah' => 'required|max:20',
            'harga' => 'required|numeric',
            'dp_persen' => 'numeric',
            'dp_nominal' => 'numeric',
            'angsuran' => 'numeric',
            'tenor_bulan' => 'integer',
            'persyaratan' => 'max:1000',
            'keterangan' => 'max:1000'
        ]);

        $stmt = $this->db->prepare("INSERT INTO data_tipe_rumah (tipe_rumah, harga, dp_persen, dp_nominal, angsuran, tenor_bulan, persyaratan, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sddddiss', $data['tipe_rumah'], $data['harga'], $data['dp_persen'] ?? 0, $data['dp_nominal'] ?? 0, $data['angsuran'] ?? 0, $data['tenor_bulan'] ?? 0, $data['persyaratan'] ?? '', $data['keterangan'] ?? '');
        $stmt->execute();

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'TipeRumah', "Tambah tipe: {$data['tipe_rumah']}", ['id' => $id]);
        Response::success(['id_tipe' => $id], 'Tipe rumah dibuat', 201);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'tipe_rumah' => 'required|max:20',
            'harga' => 'required|numeric',
            'dp_persen' => 'numeric',
            'dp_nominal' => 'numeric',
            'angsuran' => 'numeric',
            'tenor_bulan' => 'integer',
            'persyaratan' => 'max:1000',
            'keterangan' => 'max:1000'
        ]);

        $stmt = $this->db->prepare("UPDATE data_tipe_rumah SET tipe_rumah=?, harga=?, dp_persen=?, dp_nominal=?, angsuran=?, tenor_bulan=?, persyaratan=?, keterangan=? WHERE id_tipe=?");
        $stmt->bind_param('sddddissi', $data['tipe_rumah'], $data['harga'], $data['dp_persen'] ?? 0, $data['dp_nominal'] ?? 0, $data['angsuran'] ?? 0, $data['tenor_bulan'] ?? 0, $data['persyaratan'] ?? '', $data['keterangan'] ?? '', $id);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Tipe Rumah');
        $this->logActivity('UPDATE', 'TipeRumah', "Update tipe id $id", $data);
        Response::success(null, 'Tipe rumah diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_tipe_rumah WHERE id_tipe = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Tipe Rumah');
        $this->logActivity('DELETE', 'TipeRumah', "Hapus tipe id $id");
        Response::success(null, 'Tipe rumah dihapus');
    }

    public function byCluster(int $clusterId): void {
        $this->auth();
        // data_rumah.tipe_rumah matches data_tipe_rumah.tipe_rumah
        $stmt = $this->db->prepare("
            SELECT DISTINCT t.id_tipe, t.tipe_rumah, t.harga, t.dp_persen, t.dp_nominal, t.angsuran, t.tenor_bulan
            FROM data_tipe_rumah t
            JOIN data_rumah r ON r.tipe_rumah = t.tipe_rumah
            WHERE r.id_cluster = ?
            ORDER BY t.tipe_rumah
        ");
        $stmt->bind_param('i', $clusterId);
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }
}