<?php
// api/controllers/ClusterController.php - Cluster management

require_once __DIR__ . '/BaseController.php';

class ClusterController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(cluster_name LIKE ? OR lokasi LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT id_cluster, cluster_name, lokasi, total_unit, cluster_status
             FROM data_cluster $whereSql ORDER BY id_cluster DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("SELECT * FROM data_cluster WHERE id_cluster = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Cluster');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'cluster_name' => 'required|min:2|max:150',
            'lokasi' => 'required|max:150',
            'total_unit' => 'integer',
            'cluster_status' => 'in:on going,done,not started'
        ]);

        $stmt = $this->db->prepare("INSERT INTO data_cluster (cluster_name, lokasi, total_unit, cluster_status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssis', $data['cluster_name'], $data['lokasi'], $data['total_unit'] ?? 0, $data['cluster_status'] ?? 'on going');
        $stmt->execute();

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'Cluster', "Tambah cluster: {$data['cluster_name']}", ['id' => $id]);
        Response::success(['id_cluster' => $id], 'Cluster dibuat', 201);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'cluster_name' => 'required|min:2|max:150',
            'lokasi' => 'required|max:150',
            'total_unit' => 'integer',
            'cluster_status' => 'in:on going,done,not started'
        ]);

        $stmt = $this->db->prepare("UPDATE data_cluster SET cluster_name=?, lokasi=?, total_unit=?, cluster_status=? WHERE id_cluster=?");
        $stmt->bind_param('ssisi', $data['cluster_name'], $data['lokasi'], $data['total_unit'] ?? 0, $data['cluster_status'] ?? 'on going', $id);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Cluster');
        $this->logActivity('UPDATE', 'Cluster', "Update cluster id $id", $data);
        Response::success(null, 'Cluster diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_cluster WHERE id_cluster = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Cluster');
        $this->logActivity('DELETE', 'Cluster', "Hapus cluster id $id");
        Response::success(null, 'Cluster dihapus');
    }

    public function options(): void {
        $this->auth();
        $stmt = $this->db->query("SELECT id_cluster, cluster_name FROM data_cluster WHERE cluster_status != 'not started' ORDER BY cluster_name");
        $items = $stmt->fetch_all(MYSQLI_ASSOC);
        Response::success($items);
    }

    public function stats(): void {
        $this->auth();
        $stmt = $this->db->query("
            SELECT
                c.id_cluster, c.cluster_name,
                COUNT(r.id_rumah) as total_rumah,
                SUM(CASE WHEN r.status_rumah = 'tersedia' THEN 1 ELSE 0 END) as tersedia,
                SUM(CASE WHEN r.status_rumah = 'booking' THEN 1 ELSE 0 END) as booking,
                SUM(CASE WHEN r.status_rumah = 'terjual' THEN 1 ELSE 0 END) as terjual
            FROM data_cluster c
            LEFT JOIN data_rumah r ON r.id_cluster = c.id_cluster
            GROUP BY c.id_cluster
        ");
        Response::success($stmt->fetch_all(MYSQLI_ASSOC));
    }
}