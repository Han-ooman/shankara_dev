<?php
// api/controllers/BaseController.php - Base controller dengan helper umum

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Request.php';
require_once __DIR__ . '/../helpers/Log.php';

abstract class BaseController {
    protected mysqli $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    protected function auth(): array {
        return Auth::requireAuth();
    }

    protected function authRole(string|array $roles): array {
        return Auth::requireRole($roles);
    }

    protected function validate(array $rules): array {
        return Request::validate($rules);
    }

    protected function paginate(string $baseQuery, array $params = [], int $page = 1, int $perPage = 20): array {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        // Count total - use s modifier for multiline
        $countQuery = preg_replace('/^SELECT\s.+?\sFROM\s/is', 'SELECT COUNT(*) as total FROM ', $baseQuery);
        $countQuery = preg_replace('/\sORDER\sBY\s.+$/is', '', $countQuery);
        $countQuery = preg_replace('/\sLIMIT\s.+$/is', '', $countQuery);

        $countStmt = $this->db->prepare($countQuery);
        if (!$countStmt) {
            error_log("Count prepare failed: " . $this->db->error . " Query: $countQuery");
            $total = 0;
        } else {
            if ($params) {
                $types = '';
                foreach ($params as $p) $types .= match (true) {
                    is_int($p) => 'i', is_float($p) => 'd', default => 's'
                };
                $countStmt->bind_param($types, ...$params);
            }
            $executed = $countStmt->execute();
            if (!$executed) {
                error_log("Count execute failed: " . $countStmt->error . " Query: $countQuery");
                $total = 0;
            } else {
                $result = $countStmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                $total = (int)($row['total'] ?? 0);
            }
        }

        // Get items
        $query = "$baseQuery LIMIT $perPage OFFSET $offset";
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            error_log("Items prepare failed: " . $this->db->error . " Query: $query");
            $items = [];
        } else {
            if ($params) {
                $types = '';
                foreach ($params as $p) $types .= match (true) {
                    is_int($p) => 'i', is_float($p) => 'd', default => 's'
                };
                $stmt->bind_param($types, ...$params);
            }
            $executed = $stmt->execute();
            if (!$executed) {
                error_log("Items execute failed: " . $stmt->error . " Query: $query");
                $items = [];
            } else {
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int)ceil($total / $perPage)
        ];
    }

    protected function logActivity(string $aksi, string $modul, string $deskripsi, ?array $detail = null): void {
        Log::activity($aksi, $modul, $deskripsi, $detail);
    }

    public function logApi(string $method, string $endpoint, array $params = []): void {
        Log::api($method, $endpoint, $_SESSION['user_id'] ?? null, $params);
    }
}