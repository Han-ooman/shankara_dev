<?php
// api/controllers/PembatalanController.php - Pembatalan management

require_once __DIR__ . '/BaseController.php';

class PembatalanController extends BaseController {
    public function index(): void {
        $this->auth();
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(p.no_surat LIKE ? OR k.name_lengkap LIKE ? OR r.no_blok LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT p.*, t.id_transaksi, k.name_lengkap, r.no_blok, c.cluster_name, r.tipe_rumah
             FROM data_pembatalan p
             JOIN data_transaksi t ON t.id_transaksi = p.id_transaksi
             JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
             JOIN data_rumah r ON r.id_rumah = t.id_rumah
             JOIN data_cluster c ON c.id_cluster = r.id_cluster
             $whereSql ORDER BY p.tgl_surat DESC, p.id_pembatalan DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function show(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT p.*, t.id_transaksi, t.tgl_booking, t.skema_bayar, t.skema_akad,
                   k.name_lengkap, k.no_ktp, k.no_whatsapp, k.alamat_ktp, k.pekerjaan,
                   r.no_blok, c.cluster_name, r.tipe_rumah, r.luas_tanah, r.luas_bangunan, r.harga_jual
            FROM data_pembatalan p
            JOIN data_transaksi t ON t.id_transaksi = p.id_transaksi
            JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE p.id_pembatalan = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Pembatalan');
        Response::success($row);
    }

    public function store(): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'id_transaksi' => 'required|integer',
            'no_surat' => 'required|max:50',
            'tgl_surat' => 'required|date',
            'alasan_pembatalan' => 'required|max:500',
            'booking_fee' => 'numeric',
            'rekening_nama' => 'max:150',
            'rekening_bank' => 'max:100',
            'rekening_no' => 'max:50'
        ]);

        // Cek transaksi
        $stmt = $this->db->prepare("SELECT t.*, r.no_blok, r.id_rumah FROM data_transaksi t JOIN data_rumah r ON r.id_rumah = t.id_rumah WHERE t.id_transaksi = ?");
        $stmt->bind_param('i', $data['id_transaksi']);
        $stmt->execute();
        $trx = $stmt->get_result()->fetch_assoc();
        if (!$trx) Response::notFound('Transaksi');

        // Cek no_surat unik
        $stmt = $this->db->prepare("SELECT 1 FROM data_pembatalan WHERE no_surat = ?");
        $stmt->bind_param('s', $data['no_surat']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) Response::error('Nomor surat sudah digunakan', 400);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO data_pembatalan (id_transaksi, no_surat, tgl_surat, alasan_pembatalan, booking_fee, rekening_nama, rekening_bank, rekening_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssisss', $data['id_transaksi'], $data['no_surat'], $data['tgl_surat'], $data['alasan_pembatalan'], $data['booking_fee'] ?? 0, $data['rekening_nama'] ?? '', $data['rekening_bank'] ?? '', $data['rekening_no'] ?? '');
            $stmt->execute();

            $id = $this->db->insertId();

            // Update transaksi status BATAL
            Database::execute("UPDATE data_transaksi SET status_konsumen = 'BATAL' WHERE id_transaksi = ?", [$data['id_transaksi']]);

            // Update rumah kembali tersedia
            Database::execute("UPDATE data_rumah SET status_unit = 'tersedia' WHERE id_rumah = ?", [$trx['id_rumah']]);

            $this->db->commit();
            $this->logActivity('CREATE', 'Pembatalan', "Pembatalan unit {$trx['no_blok']}", ['id_pembatalan' => $id, 'transaksi' => $data['id_transaksi']]);
            Response::success(['id_pembatalan' => $id], 'Pembatalan dicatat', 201);
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function update(int $id): void {
        $this->authRole(['Super Admin', 'Admin']);
        $data = $this->validate([
            'no_surat' => 'required|max:50',
            'tgl_surat' => 'required|date',
            'alasan_pembatalan' => 'required|max:500',
            'booking_fee' => 'numeric',
            'rekening_nama' => 'max:150',
            'rekening_bank' => 'max:100',
            'rekening_no' => 'max:50'
        ]);

        $stmt = $this->db->prepare("UPDATE data_pembatalan SET no_surat=?, tgl_surat=?, alasan_pembatalan=?, booking_fee=?, rekening_nama=?, rekening_bank=?, rekening_no=? WHERE id_pembatalan=?");
        $stmt->bind_param('sssisssi', $data['no_surat'], $data['tgl_surat'], $data['alasan_pembatalan'], $data['booking_fee'] ?? 0, $data['rekening_nama'] ?? '', $data['rekening_bank'] ?? '', $data['rekening_no'] ?? '', $id);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('Pembatalan');
        $this->logActivity('UPDATE', 'Pembatalan', "Update pembatalan id $id", $data);
        Response::success(null, 'Pembatalan diperbarui');
    }

    public function destroy(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("DELETE FROM data_pembatalan WHERE id_pembatalan = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($this->db->affectedRows() === 0) Response::notFound('Pembatalan');
        $this->logActivity('DELETE', 'Pembatalan', "Hapus pembatalan id $id");
        Response::success(null, 'Pembatalan dihapus');
    }

    public function printData(int $id): void {
        $this->auth();
        $stmt = $this->db->prepare("
            SELECT p.*, t.tgl_booking, t.skema_bayar, t.skema_akad,
                   k.name_lengkap, k.no_ktp, k.no_whatsapp, k.alamat_ktp, k.pekerjaan,
                   r.no_blok, c.cluster_name, r.tipe_rumah, r.luas_tanah, r.luas_bangunan, r.harga_jual
            FROM data_pembatalan p
            JOIN data_transaksi t ON t.id_transaksi = p.id_transaksi
            JOIN data_konsumen k ON k.id_konsumen = t.id_konsumen
            JOIN data_rumah r ON r.id_rumah = t.id_rumah
            JOIN data_cluster c ON c.id_cluster = r.id_cluster
            WHERE p.id_pembatalan = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('Pembatalan');

        // Terbilang function
        $row['terbilang'] = $this->terbilang($row['booking_fee']);
        Response::success($row);
    }

    private function terbilang(int $angka): string {
        $huruf = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        if ($angka < 12) return $huruf[$angka] . ' Rupiah';
        elseif ($angka < 20) return $this->terbilang($angka - 10) . ' Belas Rupiah';
        elseif ($angka < 100) return $this->terbilang($angka / 10) . ' Puluh ' . $this->terbilang($angka % 10) . ' Rupiah';
        elseif ($angka < 200) return 'Seratus ' . $this->terbilang($angka - 100);
        elseif ($angka < 1000) return $this->terbilang($angka / 100) . ' Ratus ' . $this->terbilang($angka % 100);
        elseif ($angka < 2000) return 'Seribu ' . $this->terbilang($angka - 1000);
        elseif ($angka < 1000000) return $this->terbilang($angka / 1000) . ' Ribu ' . $this->terbilang($angka % 1000);
        elseif ($angka < 1000000000) return $this->terbilang($angka / 1000000) . ' Juta ' . $this->terbilang($angka % 1000000);
        else return $this->terbilang($angka / 1000000000) . ' Miliar ' . $this->terbilang($angka % 1000000000);
    }
}