<?php
// api/controllers/AuthController.php - Authentication endpoints

require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController {
    public function login(): void {
        $data = $this->validate([
            'username' => 'required|min:3|max:50',
            'password' => 'required|min:4'
        ]);

        $stmt = $this->db->prepare("SELECT id, username, password, nama_lengkap, role, status FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param('s', $data['username']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            $this->logActivity('LOGIN_FAILED', 'Auth', "Gagal login: {$data['username']}");
            Response::unauthorized('Username atau password salah');
        }

        session_regenerate_id(true);
        $_SESSION['status_login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role'] = $user['role'];

        $sid = session_id();
        $this->db->execute(
            "INSERT INTO user_sesi (user_id, session_id, login_at, last_active) VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE last_active = NOW()",
            [$user['id'], $sid]
        );

        $this->logActivity('LOGIN', 'Auth', "User {$user['username']} login");
        Response::success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role' => $user['role']
            ]
        ], 'Login berhasil');
    }

    public function logout(): void {
        $user = Auth::user();
        if ($user) {
            $sid = Auth::getSessionId();
            $this->db->execute("DELETE FROM user_sesi WHERE user_id = ? AND session_id = ?", [$user['id'], $sid]);
            $this->logActivity('LOGOUT', 'Auth', "User {$user['username']} logout");
        }
        session_unset();
        session_destroy();
        Response::success(null, 'Logout berhasil');
    }

    public function me(): void {
        $user = $this->auth();
        Response::success($user);
    }

    public function checkSession(): void {
        Response::success(['valid' => Auth::check()]);
    }
}