<?php
// api/controllers/PemberkasanController.php - Pemberkasan management

require_once __DIR__ . '/BaseController.php';

class PemberkasanController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $status = Request::input('status_bank');
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(k.name_lengkap LIKE ? OR r.no_blok LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term;
        }
        if ($status) {
            $where[] = "t.status_bank = ?";
            $params[] = $status;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT t.id_transaksi, t.tgl_booking, k.name_lengkap, r.no_blok, c.cluster_name, r.tipe_rumah,
                    t.skema_bayar, t.skema_akad, t.status_konsumen, t.status_bank, t.bank_tujuan,
                    t.deadline_berkas, t.tgl_wawancara, t.tanggal_akad,
                    t.status_bphtb, t.status_tapera, t.status_trilogi, t.status_sikumbang
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
            SELECT t.*, k.name_lengkap, k.no_ktp, k.no_whatsapp, k.alamat_ktp, k.pekerjaan,
                   r.no_blok, c.cluster_name, r.tipe_rumah, r.harga_jual
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
        if ($row['pemberkasan_json']) $row['pemberkasan'] = json_decode($row['pemberkasan_json'], true);
        Response::success($row);
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Keuangan', 'Legal']);
        $data = $this->validate([
            'status_bank' => 'max:100',
            'bank_tujuan' => 'max:100',
            'deadline_berkas' => 'date',
            'tgl_wawancara' => 'date',
            'tanggal_akad' => 'date',
            'status_bphtb' => 'max:120',
            'status_tapera' => 'max:120',
            'status_trilogi' => 'max:120',
            'status_sikumbang' => 'max:120',
            'pemberkasan' => 'array'
        ]);

        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            if ($k === 'pemberkasan') {
                $set[] = "pemberkasan_json = ?";
                $params[] = json_encode($v, JSON_UNESCAPED_UNICODE);
            } else {
                $set[] = "$k = ?";
                $params[] = $v;
            }
        }
        if (!$set) Response::error('Tidak ada data untuk diupdate', 400);
        $params[] = $id;

        $stmt = $this->db->prepare("UPDATE data_transaksi SET " . implode(', ', $set) . " WHERE id_transaksi = ?");
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Transaksi');
        $this->logActivity('UPDATE', 'Pemberkasan', "Update pemberkasan transaksi id $id", $data);
        Response::success(null, 'Pemberkasan diperbarui');
    }

    public function updateBerkas(int $id): void {
        $this->authRole(['Super Admin', 'Admin', 'Keuangan', 'Legal']);
        $data = $this->validate([
            'pemberkasan' => 'required|array'
        ]);
        $json = json_encode($data['pemberkasan'], JSON_UNESCAPED_UNICODE);
        $stmt = $this->db->prepare("UPDATE data_transaksi SET pemberkasan_json = ? WHERE id_transaksi = ?");
        $stmt->bind_param('si', $json, $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Transaksi');
        $this->logActivity('UPDATE_BERKAS', 'Pemberkasan', "Update berkas transaksi id $id", $data['pemberkasan']);
        Response::success(null, 'Berkas diperbarui');
    }

    public function stats(): void {
        $this->auth();
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status_bank = 'Wawancara' THEN 1 ELSE 0 END) as wawancara,
                SUM(CASE WHEN status_bank = 'Pemberkasan' THEN 1 ELSE 0 END) as pemberkasan,
                SUM(CASE WHEN status_bank = 'ACC SP3K' THEN 1 ELSE 0 END) as acc_sp3k,
                SUM(CASE WHEN status_bphtb = 'Lunas' THEN 1 ELSE 0 END) as bphtb_lunas,
                SUM(CASE WHEN status_tapera = 'Lunas' THEN 1 ELSE 0 END) as tapera_lunas
            FROM data_transaksi
            WHERE status_konsumen IN ('PROSES', 'ACC - AKAD')
        ");
        Response::success($stmt->fetch_assoc());
    }
}