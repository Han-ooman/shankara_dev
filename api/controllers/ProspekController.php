<?php
// api/controllers/ProspekController.php - Prospek management

require_once __DIR__ . '/BaseController.php';

class ProspekController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();
        $status = Request::input('status');
        $sumber = Request::input('sumber');

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(name_lengkap LIKE ? OR no_whatsapp LIKE ? OR pekerjaan LIKE ? OR cluster_minat LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($status) {
            $where[] = "status_followup = ?";
            $params[] = $status;
        }
        if ($sumber) {
            $where[] = "sumber = ?";
            $params[] = $sumber;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT id_prospek, name_lengkap, no_ktp, pekerjaan, no_whatsapp, sumber,
                    cluster_minat, unit_minat, catatan, status_followup, tanggal_followup,
                    created_at, updated_at
             FROM data_prospek $whereSql ORDER BY id_prospek DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("SELECT * FROM data_prospek WHERE id_prospek = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Prospek');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'name_lengkap' => 'required|min:2|max:150',
            'no_ktp' => 'max:30',
            'pekerjaan' => 'max:100',
            'no_whatsapp' => 'max:30',
            'sumber' => 'required|in:Pameran,Walk-in Kantor,Instagram,Facebook,WhatsApp,Referensi,Lainnya',
            'cluster_minat' => 'max:150',
            'unit_minat' => 'max:150',
            'catatan' => 'max:1000',
            'status_followup' => 'in:Baru,Sudah Dihubungi,Minat,Sudah Survey,Deal,Tidak Lanjut',
            'tanggal_followup' => 'date',
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO data_prospek (name_lengkap, no_ktp, pekerjaan, no_whatsapp, sumber,
                                      cluster_minat, unit_minat, catatan, status_followup, tanggal_followup)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssssssssss',
            $data['name_lengkap'],
            $data['no_ktp'] ?? '',
            $data['pekerjaan'] ?? '',
            $data['no_whatsapp'] ?? '',
            $data['sumber'],
            $data['cluster_minat'] ?? '',
            $data['unit_minat'] ?? '',
            $data['catatan'] ?? '',
            $data['status_followup'] ?? 'Baru',
            $data['tanggal_followup'] ?? null
        );
        $stmt->execute();

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'Prospek', "Tambah prospek: {$data['name_lengkap']}", ['id' => $id]);
        Response::success(['id_prospek' => $id], 'Prospek dibuat', 201);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'name_lengkap' => 'required|min:2|max:150',
            'no_ktp' => 'max:30',
            'pekerjaan' => 'max:100',
            'no_whatsapp' => 'max:30',
            'sumber' => 'required|in:Pameran,Walk-in Kantor,Instagram,Facebook,WhatsApp,Referensi,Lainnya',
            'cluster_minat' => 'max:150',
            'unit_minat' => 'max:150',
            'catatan' => 'max:1000',
            'status_followup' => 'in:Baru,Sudah Dihubungi,Minat,Sudah Survey,Deal,Tidak Lanjut',
            'tanggal_followup' => 'date',
        ]);

        $stmt = $this->db->prepare("
            UPDATE data_prospek SET
                name_lengkap=?, no_ktp=?, pekerjaan=?, no_whatsapp=?,
                sumber=?, cluster_minat=?, unit_minat=?, catatan=?,
                status_followup=?, tanggal_followup=?
            WHERE id_prospek=?
        ");
        $stmt->bind_param('ssssssssssi',
            $data['name_lengkap'],
            $data['no_ktp'] ?? '',
            $data['pekerjaan'] ?? '',
            $data['no_whatsapp'] ?? '',
            $data['sumber'],
            $data['cluster_minat'] ?? '',
            $data['unit_minat'] ?? '',
            $data['catatan'] ?? '',
            $data['status_followup'] ?? 'Baru',
            $data['tanggal_followup'] ?? null,
            $id
        );
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Prospek');
        $this->logActivity('UPDATE', 'Prospek', "Update prospek id $id", $data);
        Response::success(null, 'Prospek diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_prospek WHERE id_prospek = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Prospek');
        $this->logActivity('DELETE', 'Prospek', "Hapus prospek id $id");
        Response::success(null, 'Prospek dihapus');
    }

    // Deal prospek → pindah ke konsumen (mirip web)
    public function deal(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $stmt = $this->db->prepare("SELECT * FROM data_prospek WHERE id_prospek = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if (!$p) Response::notFound('Prospek');

        // Cek duplikat konsumen
        $dup = false;
        if (!empty($p['no_ktp'])) {
            $stmt = $this->db->prepare("SELECT id_konsumen FROM data_konsumen WHERE no_ktp = ?");
            $stmt->bind_param('s', $p['no_ktp']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $dup = true;
        }
        if (!$dup && !empty($p['no_whatsapp'])) {
            $stmt = $this->db->prepare("SELECT id_konsumen FROM data_konsumen WHERE no_whatsapp = ?");
            $stmt->bind_param('s', $p['no_whatsapp']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $dup = true;
        }

        if ($dup) {
            // Sudah jadi konsumen, hanya update status
            $stmt = $this->db->prepare("UPDATE data_prospek SET status_followup='Deal' WHERE id_prospek=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            Response::success(['duplikat' => true], 'Prospek sudah menjadi konsumen, status diupdate ke Deal');
            return;
        }

        // Insert ke konsumen
        $stmt = $this->db->prepare("
            INSERT INTO data_konsumen (name_lengkap, no_ktp, pekerjaan, no_whatsapp)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('ssss',
            $p['name_lengkap'],
            $p['no_ktp'] ?? '',
            $p['pekerjaan'] ?? '',
            $p['no_whatsapp'] ?? ''
        );
        $stmt->execute();
        $newId = $this->db->insertId();

        // Update status prospek
        $stmt = $this->db->prepare("UPDATE data_prospek SET status_followup='Deal' WHERE id_prospek=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $this->logActivity('DEAL', 'Prospek', "Prospek {$p['name_lengkap']} menjadi konsumen", ['konsumen_id' => $newId]);
        Response::success(['konsumen_id' => $newId], 'Prospek di-deal dan jadi konsumen');
    }

    public function stats(): void {
        $this->auth();
        $stmt = $this->db->query("
            SELECT
                status_followup,
                COUNT(*) as jumlah
            FROM data_prospek
            GROUP BY status_followup
        ");
        Response::success($stmt->fetch_all(MYSQLI_ASSOC));
    }
}