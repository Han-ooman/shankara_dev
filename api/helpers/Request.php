<?php
// api/helpers/Request.php - Input handling & validation

class Request {
    private static ?array $jsonData = null;

    public static function all(): array {
        return $_POST + $_GET;
    }

    public static function input(string $key, mixed $default = null): mixed {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function json(): array {
        if (self::$jsonData === null) {
            $raw = file_get_contents('php://input');
            self::$jsonData = json_decode($raw, true) ?? [];
        }
        return self::$jsonData;
    }

    public static function jsonInput(string $key, mixed $default = null): mixed {
        return self::json()[$key] ?? $default;
    }

    public static function query(string $key, mixed $default = null): mixed {
        return $_GET[$key] ?? $default;
    }

    public static function has(string $key): bool {
        return isset($_POST[$key]) || isset($_GET[$key]) || array_key_exists($key, self::json());
    }

    public static function only(array $keys): array {
        $data = self::all();
        return array_intersect_key($data, array_flip($keys));
    }

    public static function except(array $keys): array {
        $data = self::all();
        return array_diff_key($data, array_flip($keys));
    }

    public static function validate(array $rules): array {
        $data = self::all() + self::json();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $parts = explode('|', $rule);
            foreach ($parts as $part) {
                if ($part === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = "$field wajib diisi";
                }
                if ($value === null || $value === '') continue;

                if (preg_match('/^min:(\d+)$/', $part, $m)) {
                    if (is_string($value) && mb_strlen($value) < (int)$m[1]) {
                        $errors[$field][] = "$field minimal {$m[1]} karakter";
                    }
                }
                if (preg_match('/^max:(\d+)$/', $part, $m)) {
                    if (is_string($value) && mb_strlen($value) > (int)$m[1]) {
                        $errors[$field][] = "$field maksimal {$m[1]} karakter";
                    }
                }
                if ($part === 'numeric' && !is_numeric($value)) {
                    $errors[$field][] = "$field harus berupa angka";
                }
                if ($part === 'integer' && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $errors[$field][] = "$field harus bilangan bulat";
                }
                if ($part === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "$field format email tidak valid";
                }
                if ($part === 'date' && !self::isValidDate($value)) {
                    $errors[$field][] = "$field format tanggal tidak valid (YYYY-MM-DD)";
                }
                if (preg_match('/^in:(.+)$/', $part, $m)) {
                    $allowed = explode(',', $m[1]);
                    if (!in_array($value, $allowed, true)) {
                        $errors[$field][] = "$field nilai tidak diizinkan";
                    }
                }
                if (preg_match('/^regex:(.+)$/', $part, $m)) {
                    if (!preg_match($m[1], $value)) {
                        $errors[$field][] = "$field format tidak sesuai";
                    }
                }
            }
        }

        if ($errors) {
            Response::validationError($errors);
        }
        return $data;
    }

    private static function isValidDate(string $date): bool {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    public static function page(int $default = 1): int {
        return max(1, (int)(self::query('page', $default)));
    }

    public static function perPage(int $default = 20, int $max = 100): int {
        return min($max, max(1, (int)(self::query('per_page', $default))));
    }

    public static function search(string $default = ''): string {
        return trim(self::query('search', $default));
    }

    public static function sort(string $default = 'id', string $order = 'DESC'): array {
        $sort = self::query('sort', $default);
        $direction = strtoupper(self::query('order', $order));
        if (!in_array($direction, ['ASC', 'DESC'])) $direction = 'DESC';
        return [$sort, $direction];
    }
}