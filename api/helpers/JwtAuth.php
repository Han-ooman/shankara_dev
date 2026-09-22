<?php
// api/helpers/JwtAuth.php - JWT authentication & authorization

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth {
    private static ?string $secret = null;
    private static int $ttl = 3600;      // 1 jam access token
    private static int $refreshTtl = 604800; // 7 hari refresh token

    public static function init(): void {
        if (self::$secret === null) {
            self::$secret = require_once __DIR__ . '/../config/jwt_secret.php';
        }
    }

    public static function generateAccessToken(array $user): string {
        self::init();
        $now = time();
        $payload = [
            'iss' => 'shankara-dev-api',
            'aud' => 'shankara-dev-app',
            'iat' => $now,
            'exp' => $now + self::$ttl,
            'type' => 'access',
            'sub' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'nama_lengkap' => $user['nama_lengkap'],
        ];
        return JWT::encode($payload, self::$secret, 'HS256');
    }

    public static function generateRefreshToken(int $userId): string {
        self::init();
        $now = time();
        $payload = [
            'iss' => 'shankara-dev-api',
            'aud' => 'shankara-dev-app',
            'iat' => $now,
            'exp' => $now + self::$refreshTtl,
            'type' => 'refresh',
            'sub' => $userId,
        ];
        return JWT::encode($payload, self::$secret, 'HS256');
    }

    public static function decode(string $token): ?array {
        self::init();
        try {
            $decoded = JWT::decode($token, new Key(self::$secret, 'HS256'));
            return (array)$decoded;
        } catch (\Exception) {
            return null;
        }
    }

    public static function getBearerToken(): ?string {
        // Apache (terutama Windows/CGI) mungkin memindahkan header ke key lain
        $auth = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!$auth) {
            $headers = getallheaders() ?? [];
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public static function requireAuth(): array {
        $token = self::getBearerToken();
        if (!$token) {
            Response::unauthorized('Token tidak ditemukan');
        }
        $payload = self::decode($token);
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            Response::unauthorized('Token tidak valid atau kadaluarsa');
        }
        return [
            'id' => (int)$payload['sub'],
            'username' => $payload['username'] ?? '',
            'role' => $payload['role'] ?? '',
            'nama_lengkap' => $payload['nama_lengkap'] ?? '',
        ];
    }

    public static function requireRole(string|array $roles): array {
        $user = self::requireAuth();
        $allowed = is_array($roles) ? $roles : [$roles];
        if (!in_array($user['role'], $allowed, true)) {
            Response::forbidden('Akses ditolak: peran tidak sesuai');
        }
        return $user;
    }

    public static function refreshAccessToken(string $refreshToken): ?string {
        self::init();
        $payload = self::decode($refreshToken);
        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            return null;
        }
        // Ambil user fresh dari DB
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, username, nama_lengkap, role FROM users WHERE id = ? AND status = 'Aktif'");
        $stmt->bind_param('i', $payload['sub']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) return null;
        return self::generateAccessToken($user);
    }
}