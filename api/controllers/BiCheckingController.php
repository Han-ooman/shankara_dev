<?php
// api/controllers/BiCheckingController.php - BI Checking management

require_once __DIR__ . '/BaseController.php';

class BiCheckingController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();
        $hasil = Request::input('hasil');
        $marketingId = Request::input('marketing_id');

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(k.name_lengkap LIKE ? OR k.no_ktp LIKE ? OR b.no_pengajuan LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($hasil) {
            $where[] = "b.hasil_bi = ?";
            $params[] = $hasil;
        }
        if ($marketingId) {
            $where[] = "b.id_marketing = ?";
            $params[] = $marketingId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT b.*, k.name_lengkap, k.pekerjaan, k.no_whatsapp,
                    m.nama_marketing
             FROM data_bi_checking b
             LEFT JOIN data_konsumen k ON k.id_konsumen = b.id_konsumen
             LEFT JOIN data_marketing m ON m.id_marketing = b.id_marketing
             $whereSql ORDER BY b.id_bi_checking DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT b.*, k.name_lengkap, k.pekerjaan, k.no_whatsapp, k.no_ktp, k.alamat_ktp,
                   m.nama_marketing
            FROM data_bi_checking b
            LEFT JOIN data_konsumen k ON k.id_konsumen = b.id_konsumen
            LEFT JOIN data_marketing m ON m.id_marketing = b.id_marketing
            WHERE b.id_bi_checking = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('BI Checking');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'id_konsumen' => 'required|integer',
            'id_marketing' => 'integer',
            'tgl_cek' => 'required|date',
            'lembaga' => 'max:100',
            'no_pengajuan' => 'max:100',
            'hasil_bi' => 'required|in:Dalam Proses,Disetujui,Ditolak,Batal',
            'catatan' => 'max:1000',
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO data_bi_checking (id_konsumen, id_marketing, tgl_cek, lembaga,
                                          no_pengajuan, hasil_bi, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iisssss',
            $data['id_konsumen'],
            $data['id_marketing'] ?? null,
            $data['tgl_cek'],
            $data['lembaga'] ?? '',
            $data['no_pengajuan'] ?? '',
            $data['hasil_bi'],
            $data['catatan'] ?? ''
        );
        $stmt->execute();

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'BI Checking', "Tambah BI checking untuk konsumen {$data['id_konsumen']}", ['id' => $id]);
        Response::success(['id_bi_checking' => $id], 'BI Checking dibuat', 201);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Marketing']);
        $data = $this->validate([
            'id_konsumen' => 'required|integer',
            'id_marketing' => 'integer',
            'tgl_cek' => 'required|date',
            'lembaga' => 'max:100',
            'no_pengajuan' => 'max:100',
            'hasil_bi' => 'required|in:Dalam Proses,Disetujui,Ditolak,Batal',
            'catatan' => 'max:1000',
        ]);

        $stmt = $this->db->prepare("
            UPDATE data_bi_checking SET
                id_konsumen=?, id_marketing=?, tgl_cek=?, lembaga=?,
                no_pengajuan=?, hasil_bi=?, catatan=?
            WHERE id_bi_checking=?
        ");
        $stmt->bind_param('iisssssi',
            $data['id_konsumen'],
            $data['id_marketing'] ?? null,
            $data['tgl_cek'],
            $data['lembaga'] ?? '',
            $data['no_pengajuan'] ?? '',
            $data['hasil_bi'],
            $data['catatan'] ?? '',
            $id
        );
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('BI Checking');
        $this->logActivity('UPDATE', 'BI Checking', "Update BI checking id $id", $data);
        Response::success(null, 'BI Checking diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_bi_checking WHERE id_bi_checking = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('BI Checking');
        $this->logActivity('DELETE', 'BI Checking', "Hapus BI checking id $id");
        Response::success(null, 'BI Checking dihapus');
    }

    public function stats(): void {
        $this->auth();
        $stmt = $this->db->query("
            SELECT
                hasil_bi,
                COUNT(*) as jumlah
            FROM data_bi_checking
            GROUP BY hasil_bi
        ");
        Response::success($stmt->fetch_all(MYSQLI_ASSOC));
    }

    public function options(): void {
        $this->auth();
        // Konsumen untuk dropdown
        $stmt = $this->db->query("SELECT id_konsumen, name_lengkap FROM data_konsumen ORDER BY name_lengkap");
        $konsumen = $stmt->fetch_all(MYSQLI_ASSOC);

        // Marketing
        $stmt = $this->db->query("SELECT id_marketing, nama_marketing FROM data_marketing WHERE status_aktif='Aktif' ORDER BY nama_marketing");
        $marketing = $stmt->fetch_all(MYSQLI_ASSOC);

        Response::success([
            'konsumen' => $konsumen,
            'marketing' => $marketing,
            'hasil_options' => ['Dalam Proses', 'Disetujui', 'Ditolak', 'Batal']
        ]);
    }
}