<?php
// api/controllers/PengaturanController.php - Pengaturan Akun & Sistem

require_once __DIR__ . '/BaseController.php';

class PengaturanController extends BaseController {
    // ===== PENGATURAN SISTEM =====
    public function getSettings(): void {
        $this->auth();
        $stmt = $this->db->query("SELECT kunci, nilai FROM pengaturan_sistem");
        $items = $stmt->fetch_all(MYSQLI_ASSOC);
        $map = [];
        foreach ($items as $item) $map[$item['kunci']] = $item['nilai'];
        Response::success($map);
    }

    public function updateSettings(): void {
        $this->authRole(['Super Admin']);
        $data = $this->validate([
            'nama_perusahaan' => 'max:150',
            'pimpinan_perusahaan' => 'max:100',
            'telepon_perusahaan' => 'max:50',
            'email_perusahaan' => 'max:150',
            'rekening_perusahaan' => 'max:200',
            'alamat_perusahaan' => 'max:500',
            'master_bank' => 'max:300',
        ]);

        foreach ($data as $kunci => $nilai) {
            Database::execute(
                "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE nilai=?",
                [$kunci, $nilai ?? '', $nilai ?? '']
            );
        }
        $this->logActivity('UPDATE', 'Pengaturan', 'Update profil perusahaan');
        Response::success(null, 'Pengaturan disimpan');
    }

    // ===== MANAJEMEN USER =====
    public function indexUsers(): void {
        $this->authRole(['Super Admin']);
        $search = Request::search();
        $page = Request::page();
        $perPage = Request::perPage();

        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(name_lengkap LIKE ? OR username LIKE ? OR role LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = $this->paginate(
            "SELECT id, nama_lengkap, username, role, status
             FROM users $whereSql ORDER BY id DESC",
            $params, $page, $perPage
        );
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    public function showUser(int $id): void {
        $this->authRole(['Super Admin']);
        $stmt = $this->db->prepare("SELECT id, nama_lengkap, username, role, status FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::notFound('User');
        Response::success($row);
    }

    public function storeUser(): void {
        $this->authRole(['Super Admin']);
        $data = $this->validate([
            'nama_lengkap' => 'required|min:2|max:150',
            'username' => 'required|min:3|max:50',
            'password' => 'required|min:4',
            'role' => 'required|in:Super Admin,Admin,Marketing,Pengawas Lapangan,Kasir Keuangan,Admin Pemberkasan',
            'status' => 'required|in:Aktif,Nonaktif',
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO users (nama_lengkap, username, password, role, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssss',
            $data['nama_lengkap'],
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
            $data['status']
        );
        $stmt->execute();

        if ($this->db->errno === 1062) {
            Response::validationError(['username' => ['Username sudah digunakan']]);
        }

        $id = $this->db->insertId();
        $this->logActivity('CREATE', 'User', "Tambah user: {$data['username']}", ['id' => $id]);
        Response::success(['id' => $id], 'User dibuat', 201);
    }

    public function updateUser(int $id): void {
        $this->authRole(['Super Admin']);
        // Prevent self-modification of role/status (safety)
        $currentUser = $this->auth();
        if ($currentUser['id'] === $id) {
            Response::forbidden('Tidak bisa mengubah akun sendiri');
        }

        $data = $this->validate([
            'nama_lengkap' => 'required|min:2|max:150',
            'role' => 'required|in:Super Admin,Admin,Marketing,Pengawas Lapangan,Kasir Keuangan,Admin Pemberkasan',
            'status' => 'required|in:Aktif,Nonaktif',
            'password' => 'min:4',
        ]);

        if (!empty($data['password'])) {
            $stmt = $this->db->prepare("
                UPDATE users SET nama_lengkap=?, role=?, status=?, password=?
                WHERE id=?
            ");
            $stmt->bind_param('ssssi',
                $data['nama_lengkap'],
                $data['role'],
                $data['status'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $id
            );
        } else {
            $stmt = $this->db->prepare("
                UPDATE users SET nama_lengkap=?, role=?, status=?
                WHERE id=?
            ");
            $stmt->bind_param('sssi',
                $data['nama_lengkap'],
                $data['role'],
                $data['status'],
                $id
            );
        }
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('User');
        $this->logActivity('UPDATE', 'User', "Update user id $id", $data);
        Response::success(null, 'User diperbarui');
    }

    public function destroyUser(int $id): void {
        $this->authRole(['Super Admin']);
        $currentUser = $this->auth();
        if ($currentUser['id'] === $id) {
            Response::forbidden('Tidak bisa menghapus akun sendiri');
        }

        // Prevent deleting main admin
        $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['username'] === 'admin') {
            Response::forbidden('Akun Admin Utama (admin) tidak dapat dihapus');
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($this->db->affectedRows() === 0) Response::notFound('User');
        $this->logActivity('DELETE', 'User', "Hapus user id $id");
        Response::success(null, 'User dihapus');
    }

    public function roleOptions(): void {
        $this->auth();
        Response::success([
            'Super Admin',
            'Admin',
            'Marketing',
            'Pengawas Lapangan',
            'Kasir Keuangan',
            'Admin Pemberkasan'
        ]);
    }
}