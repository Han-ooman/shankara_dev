<?php
// api/controllers/AuthController.php - JWT Authentication endpoints

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../helpers/JwtAuth.php';

class AuthController extends BaseController {
    public function login(): void {
        $data = $this->validate([
            'username' => 'required|min:3|max:50',
            'password' => 'required|min:4'
        ]);

        $stmt = $this->db->prepare("SELECT id, username, password, nama_lengkap, role, status FROM users WHERE username = ? AND status = 'Aktif'");
        $stmt->bind_param('s', $data['username']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            $this->logActivity('LOGIN_FAILED', 'Auth', "Gagal login: {$data['username']}");
            Response::unauthorized('Username atau password salah');
        }

        // Generate JWT tokens
        $accessToken = JwtAuth::generateAccessToken($user);
        $refreshToken = JwtAuth::generateRefreshToken((int)$user['id']);

        // Batasi device: hapus sesi lama user ini (kecuali yang paling baru)
        // supaya max device = 2 (admin & trainee) / 1 (lainnya)
        $limit_device = in_array((int)$user['id'], [1, 5], true) ? 2 : 1;
        Database::execute(
            "DELETE FROM user_sesi
             WHERE user_id = ? AND id_sesi NOT IN (
                 SELECT id_sesi FROM (
                     SELECT id_sesi FROM user_sesi WHERE user_id = ? ORDER BY last_active DESC LIMIT ?
                 ) AS t
             )",
            [$user['id'], $user['id'], $limit_device - 1]
        );

        // Simpan refresh token hash ke DB
        $refreshHash = password_hash($refreshToken, PASSWORD_DEFAULT);
        Database::execute(
            "INSERT INTO user_sesi (user_id, session_id, login_at, last_active) VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE session_id = ?, last_active = NOW()",
            [$user['id'], $refreshHash, $refreshHash]
        );

        $this->logActivity('LOGIN', 'Auth', "User {$user['username']} login (JWT)");
        Response::success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role' => $user['role']
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ], 'Login berhasil');
    }

    public function logout(): void {
        // Client-side logout: hapus token lokal. Server bisa blacklist refresh token jika perlu.
        Response::success(null, 'Logout berhasil (hapus token di aplikasi)');
    }

    public function me(): void {
        $user = $this->auth();
        Response::success($user);
    }

    public function checkSession(): void {
        Response::success(['valid' => Auth::check()]);
    }

    public function refresh(): void {
        $data = $this->validate(['refresh_token' => 'required']);
        $newAccess = JwtAuth::refreshAccessToken($data['refresh_token']);
        if (!$newAccess) {
            Response::unauthorized('Refresh token tidak valid atau kadaluarsa');
        }
        Response::success([
            'access_token' => $newAccess,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ], 'Token diperbarui');
    }
}