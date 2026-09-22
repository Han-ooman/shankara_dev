<?php
// api/helpers/Log.php - Activity logging

require_once __DIR__ . '/Database.php';

class Log {
    public static function activity(
        string $aksi,
        string $modul,
        string $deskripsi,
        ?array $detail = null,
        ?int $userId = null
    ): void {
        try {
            $db = Database::getInstance();
            $userId = $userId ?? ($_SESSION['user_id'] ?? null);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'API';

            $detailJson = $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;

            $stmt = $db->prepare(
                "INSERT INTO log_aktivitas (user_id, aksi, modul, deskripsi, detail, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('issssss', $userId, $aksi, $modul, $deskripsi, $detailJson, $ip, $ua);
            $stmt->execute();
        } catch (Throwable) {
            // Silent fail for logging
        }
    }

    public static function api(string $method, string $endpoint, ?int $userId = null, array $params = []): void {
        self::activity(
            'API_' . strtoupper($method),
            'API',
            "Endpoint: $method $endpoint",
            array_merge(['params' => $params], $params),
            $userId
        );
    }
}