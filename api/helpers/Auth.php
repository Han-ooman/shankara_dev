<?php
// api/helpers/Auth.php - Authentication & authorization

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

class Auth {
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function check(): bool {
        self::init();
        return isset($_SESSION['status_login']) && $_SESSION['status_login'] === true
            && !empty($_SESSION['user_id']);
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return [
            'id' => (int)$_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
            'role' => $_SESSION['role'] ?? ''
        ];
    }

    public static function requireAuth(): array {
        if (!self::check()) {
            Response::unauthorized('Sesi tidak valid atau telah berakhir');
        }
        return self::user();
    }

    public static function requireRole(string|array $roles): array {
        $user = self::requireAuth();
        $allowed = is_array($roles) ? $roles : [$roles];
        if (!in_array($user['role'], $allowed, true)) {
            Response::forbidden('Akses ditolak: peran tidak sesuai');
        }
        return $user;
    }

    public static function getSessionId(): string {
        return session_id();
    }

    public static function validateSessionInDB(): bool {
        if (!self::check()) return false;
        $db = Database::getInstance();
        $uid = (int)$_SESSION['user_id'];
        $sid = $db->real_escape_string(session_id());
        $q = $db->query("SELECT 1 FROM user_sesi WHERE user_id = $uid AND session_id = '$sid'");
        return $q && $q->num_rows > 0;
    }
}