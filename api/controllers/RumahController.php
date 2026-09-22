<?php
// api/controllers/RumahController.php - Unit Rumah management

require_once __DIR__ . '/BaseController.php';

class RumahController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $clusterId = Request::input('cluster_id');
        $status = Request::input('status_unit');
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];

        if ($search) {
            $where[] = "(r.no_blok LIKE ? OR r.tipe_rumah LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term;
        }
        if ($clusterId) {
            $where[] = "r.id_cluster = ?";
            $params[] = $clusterId;
        }
        if ($status) {
            $where[] = "r.status_unit = ?";
            $params[] = $status;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT r.id_rumah, r.no_blok, r.id_cluster, c.cluster_name, r.tipe_rumah,
                    r.luas_tanah, r.luas_bangunan, r.harga_jual, r.status_unit, r.no_shm
             FROM data_rumah r
             JOIN data_cluster c ON c.id_cluster = r.id_cluster
             $whereSql ORDER BY c.cluster_name, r.no_blok",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT r.*, c.cluster_name
            FROM data_rumah r
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE r.id_rumah = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Unit Rumah');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'no_blok' => 'required|max:20',
            'id_cluster' => 'required|integer',
            'tipe_rumah' => 'required|max:50',
            'luas_tanah' => 'required|integer',
            'luas_bangunan' => 'required|integer',
            'harga_jual' => 'required|numeric',
            'status_unit' => 'in:tersedia,booking,terjual',
            'no_shm' => 'max:50'
        ]);

        $stmt = $this->db->prepare("INSERT INTO data_rumah (no_blok, id_cluster, tipe_rumah, luas_tanah, luas_bangunan, harga_jual, status_unit, no_shm) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sisiidss', $data['no_blok'], $data['id_cluster'], $data['tipe_rumah'], $data['luas_tanah'], $data['luas_bangunan'], $data['harga_jual'], $data['status_unit'] ?? 'tersedia', $data['no_shm'] ?? '');
        $stmt->execute();

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'Rumah', "Tambah unit: {$data['no_blok']}", ['id' => $id]);
        Response::success(['id_rumah' => $id], 'Unit rumah dibuat', 201);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'no_blok' => 'required|max:20',
            'id_cluster' => 'required|integer',
            'tipe_rumah' => 'required|max:50',
            'luas_tanah' => 'required|integer',
            'luas_bangunan' => 'required|integer',
            'harga_jual' => 'required|numeric',
            'status_unit' => 'in:tersedia,booking,terjual',
            'no_shm' => 'max:50'
        ]);

        $stmt = $this->db->prepare("UPDATE data_rumah SET no_blok=?, id_cluster=?, tipe_rumah=?, luas_tanah=?, luas_bangunan=?, harga_jual=?, status_unit=?, no_shm=? WHERE id_rumah=?");
        $stmt->bind_param('sisiidssi', $data['no_blok'], $data['id_cluster'], $data['tipe_rumah'], $data['luas_tanah'], $data['luas_bangunan'], $data['harga_jual'], $data['status_unit'] ?? 'tersedia', $data['no_shm'] ?? '', $id);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Unit Rumah');
        $this->logActivity('UPDATE', 'Rumah', "Update unit id $id", $data);
        Response::success(null, 'Unit rumah diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_rumah WHERE id_rumah = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Unit Rumah');
        $this->logActivity('DELETE', 'Rumah', "Hapus unit id $id");
        Response::success(null, 'Unit rumah dihapus');
    }

    public function available(): void {
        $this->auth();
        $clusterId = Request::input('cluster_id');

        $where = ["r.status_unit = 'tersedia'"];
        $params = [];
        if ($clusterId) { $where[] = "r.id_cluster = ?"; $params[] = $clusterId; }

        $stmt = $this->db->prepare("
            SELECT r.id_rumah, r.no_blok, c.cluster_name, r.tipe_rumah, r.harga_jual
            FROM data_rumah r
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE " . implode(' AND ', $where) . " ORDER BY c.cluster_name, r.no_blok
        ");
        if ($params) {
            $types = str_repeat('i', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        Response::success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public function updateStatus(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'status_unit' => 'required|in:tersedia,booking,terjual'
        ]);

        $stmt = $this->db->prepare("UPDATE data_rumah SET status_unit = ? WHERE id_rumah = ?");
        $stmt->bind_param('si', $data['status_unit'], $id);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Unit Rumah');
        $this->logActivity('UPDATE_STATUS', 'Rumah', "Ubah status unit id $id", $data);
        Response::success(null, 'Status unit diperbarui');
    }
}