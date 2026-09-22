<?php
// api/controllers/PembangunanController.php - Progres Pembangunan management

require_once __DIR__ . '/BaseController.php';

class PembangunanController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();
        $status = Request::input('status');
        $clusterId = Request::input('cluster_id');

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(r.no_blok LIKE ? OR c.cluster_name LIKE ? OR r.tipe_rumah LIKE ? OR k.name_lengkap LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($status) {
            $where[] = "p.status_pembangunan = ?";
            $params[] = $status;
        }
        if ($clusterId) {
            $where[] = "r.id_cluster = ?";
            $params[] = $clusterId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT p.*, r.no_blok, r.tipe_rumah, c.cluster_name, k.name_lengkap
             FROM data_pembangunan p
             JOIN data_rumah r ON r.id_rumah = p.id_rumah
             JOIN data_cluster c ON c.id_cluster = r.id_cluster
             LEFT JOIN data_transaksi tr ON tr.id_rumah = r.id_rumah AND tr.status_konsumen != 'BATAL'
             LEFT JOIN data_konsumen k ON k.id_konsumen = tr.id_konsumen
             $whereSql ORDER BY p.id_pembangunan DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT p.*, r.no_blok, r.tipe_rumah, r.luas_tanah, r.luas_bangunan,
                   c.cluster_name, k.name_lengkap, k.no_whatsapp
            FROM data_pembangunan p
            JOIN data_rumah r ON r.id_rumah = p.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            LEFT JOIN data_transaksi tr ON tr.id_rumah = r.id_rumah AND tr.status_konsumen != 'BATAL'
            LEFT JOIN data_konsumen k ON k.id_konsumen = tr.id_konsumen
            WHERE p.id_pembangunan = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Pembangunan');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin', 'Pengawas Lapangan']);
        $data = $this->validate([
            'id_rumah' => 'required|integer',
            'persentase_progres' => 'required|integer',
            'status_pembangunan' => 'required|in:Persiapan,Pondasi,Struktur,Finishing,Selesai',
            'tgl_mulai' => 'date',
            'tgl_target_selesai' => 'date',
            'catatan_lapangan' => 'max:1000',
        ]);

        // Upsert: jika sudah ada progres untuk unit, update
        $stmt = $this->db->prepare("SELECT id_pembangunan FROM data_pembangunan WHERE id_rumah = ?");
        $stmt->bind_param('i', $data['id_rumah']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            // Update
            $stmt = $this->db->prepare("
                UPDATE data_pembangunan SET
                    persentase_progres=?, status_pembangunan=?,
                    tgl_mulai=?, tgl_target_selesai=?, catatan_lapangan=?
                WHERE id_rumah=?
            ");
            $stmt->bind_param('issssi',
                $data['persentase_progres'],
                $data['status_pembangunan'],
                $data['tgl_mulai'] ?? null,
                $data['tgl_target_selesai'] ?? null,
                $data['catatan_lapangan'] ?? '',
                $data['id_rumah']
            );
            $stmt->execute();
            $id = $existing['id_pembangunan'];
            $this->logActivity('UPDATE', 'Pembangunan', "Update progres id $id", $data);
        } else {
            // Insert
            $stmt = $this->db->prepare("
                INSERT INTO data_pembangunan (id_rumah, persentase_progres, status_pembangunan,
                                              tgl_mulai, tgl_target_selesai, catatan_lapangan)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iissss',
                $data['id_rumah'],
                $data['persentase_progres'],
                $data['status_pembangunan'],
                $data['tgl_mulai'] ?? null,
                $data['tgl_target_selesai'] ?? null,
                $data['catatan_lapangan'] ?? ''
            );
            $stmt->execute();
            $id = $this->db->insertId();
            $this->logActivity('CREATE', 'Pembangunan', "Tambah progres untuk rumah {$data['id_rumah']}", ['id' => $id]);
        }
        Response::success(['id_pembangunan' => $id], 'Progres disimpan');
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Pengawas Lapangan']);
        $data = $this->validate([
            'persentase_progres' => 'required|integer',
            'status_pembangunan' => 'required|in:Persiapan,Pondasi,Struktur,Finishing,Selesai',
            'tgl_mulai' => 'date',
            'tgl_target_selesai' => 'date',
            'catatan_lapangan' => 'max:1000',
        ]);

        $stmt = $this->db->prepare("
            UPDATE data_pembangunan SET
                persentase_progres=?, status_pembangunan=?,
                tgl_mulai=?, tgl_target_selesai=?, catatan_lapangan=?
            WHERE id_pembangunan=?
        ");
        $stmt->bind_param('issssi',
            $data['persentase_progres'],
            $data['status_pembangunan'],
            $data['tgl_mulai'] ?? null,
            $data['tgl_target_selesai'] ?? null,
            $data['catatan_lapangan'] ?? '',
            $id
        );
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Pembangunan');
        $this->logActivity('UPDATE', 'Pembangunan', "Update pembangunan id $id", $data);
        Response::success(null, 'Pembangunan diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_pembangunan WHERE id_pembangunan = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Pembangunan');
        $this->logActivity('DELETE', 'Pembangunan', "Hapus pembangunan id $id");
        Response::success(null, 'Pembangunan dihapus');
    }

    public function stats(): void {
        $this->auth();
        $stmt = $this->db->query("
            SELECT
                status_pembangunan,
                COUNT(*) as jumlah,
                AVG(persentase_progres) as rata_progres
            FROM data_pembangunan
            GROUP BY status_pembangunan
        ");
        Response::success($stmt->fetch_all(MYSQLI_ASSOC));
    }

    // Units without progress record yet (for dropdown)
    public function units(): void {
        $this->auth();
        $stmt = $this->db->query("
            SELECT r.id_rumah, r.no_blok, r.tipe_rumah, c.cluster_name, r.status_unit
            FROM data_rumah r
            LEFT JOIN data_cluster c ON c.id_cluster = r.id_cluster
            LEFT JOIN data_pembangunan p ON p.id_rumah = r.id_rumah
            WHERE p.id_pembangunan IS NULL
            ORDER BY c.cluster_name, r.no_blok
        ");
        Response::success($stmt->fetch_all(MYSQLI_ASSOC));
    }
}