<?php
// api/helpers/Database.php - Singleton database connection

class Database {
    private static ?sdb $instance = null;
    private static array $config = [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'db'   => 'db_perumahan',
        'charset' => 'utf8mb4'
    ];

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    public static function getInstance(): sdb {
        if (self::$instance === null) {
            self::$instance = new sdb(
                self::$config['host'],
                self::$config['user'],
                self::$config['pass'],
                self::$config['db']
            );
            if (self::$instance->connect_error) {
                error_log("API DB Connect Error: " . self::$instance->connect_error);
                throw new Exception("Database connection failed", 500);
            }
            self::$instance->set_charset(self::$config['charset']);
            self::$instance->query("SET time_zone = '+07:00'");
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): sdb_result|bool {
        $db = self::getInstance();
        if ($params) {
            $stmt = $db->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $db->error, 500);
            $types = '';
            foreach ($params as $p) {
                $types .= match (true) {
                    is_int($p) => 'i',
                    is_float($p) => 'd',
                    default => 's'
                };
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        }
        return $db->query($sql);
    }

    public static function execute(string $sql, array $params = []): bool {
        $db = self::getInstance();
        if ($params) {
            $stmt = $db->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $db->error, 500);
            $types = '';
            foreach ($params as $p) {
                $types .= match (true) {
                    is_int($p) => 'i',
                    is_float($p) => 'd',
                    default => 's'
                };
            }
            $stmt->bind_param($types, ...$params);
            return $stmt->execute();
        }
        return $db->query($sql);
    }

    public static function insertId(): int {
        return self::getInstance()->insert_id;
    }

    public static function affectedRows(): int {
        return self::getInstance()->affected_rows;
    }

    public static function escape(string $value): string {
        return self::getInstance()->real_escape_string($value);
    }

    public static function beginTransaction(): bool {
        return self::getInstance()->begin_transaction();
    }

    public static function commit(): bool {
        return self::getInstance()->commit();
    }

    public static function rollback(): bool {
        return self::getInstance()->rollback();
    }
}