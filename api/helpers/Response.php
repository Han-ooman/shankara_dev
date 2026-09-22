<?php
// api/helpers/Response.php - Standardized API responses

class Response {
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    public static function error(string $message = 'Error', int $code = 400, mixed $errors = null): void {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    public static function paginated(array $items, int $total, int $page, int $perPage, string $message = 'Success'): void {
        $lastPage = (int)ceil($total / $perPage);
        self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]
        ], $message);
    }

    public static function validationError(array $errors): void {
        self::error('Validasi gagal', 422, $errors);
    }

    public static function notFound(string $resource = 'Resource'): void {
        self::error("$resource tidak ditemukan", 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void {
        self::error($message, 403);
    }

    public static function serverError(string $message = 'Internal Server Error'): void {
        self::error($message, 500);
    }
}