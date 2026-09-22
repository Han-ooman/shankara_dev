<?php
// api/helpers/Auth.php - JWT Bearer authentication & authorization
// (menggantikan PHPSESSID session cookie yang lama)

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/JwtAuth.php';

class Auth {
    public static function check(): bool {
        try {
            JwtAuth::requireAuth();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function user(): ?array {
        $token = JwtAuth::getBearerToken();
        if (!$token) return null;
        $payload = JwtAuth::decode($token);
        if (!$payload || ($payload['type'] ?? '') !== 'access') return null;
        return [
            'id' => (int)($payload['sub'] ?? 0),
            'username' => $payload['username'] ?? '',
            'nama_lengkap' => $payload['nama_lengkap'] ?? '',
            'role' => $payload['role'] ?? ''
        ];
    }

    public static function requireAuth(): array {
        return JwtAuth::requireAuth();
    }

    public static function requireRole(string|array $roles): array {
        return JwtAuth::requireRole($roles);
    }
}