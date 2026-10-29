<?php
// includes/db_turso.php — Turso / libSQL HTTP Adapter untuk Shankara Dev
class TursoDB {
    private string $url;
    private string $token;
    public string $connect_error = '';
    public int $connect_errno = 0;
    public string $error = '';
    public int $errno = 0;
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $server_info = 'Turso (libSQL SQLite)';
    public string $driver = 'turso';

    public function getDriver(): string { return $this->driver; }

    public function __construct(string $url, string $token) {
        $host = parse_url($url, PHP_URL_HOST);
        // Jika libsql:// ubah ke https://
        $this->url = "https://{$host}/v2/pipeline";
        $this->token = $token;
    }

    private function translateSql(string $sql): string {
        // Terjemahkan sintaks PostgreSQL/MySQL ke SQLite
        $sql = trim($sql);
        
        // NOW() -> CURRENT_TIMESTAMP
        $sql = preg_replace('/\bNOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql);
        
        // ILIKE -> LIKE dengan LOWER()
        $sql = preg_replace('/\bILIKE\b/i', 'LIKE', $sql);
        
        // ::type cast -> CAST(x AS type)
        $sql = preg_replace('/(\w+)\s*::\s*(\w+)/', 'CAST($1 AS $2)', $sql);
        
        // MySQL ON DUPLICATE KEY UPDATE -> SQLite ON CONFLICT
        // INSERT INTO table (col1, col2) VALUES (?, ?) ON DUPLICATE KEY UPDATE col1 = VALUES(col1), col2 = VALUES(col2)
        // becomes:
        // INSERT INTO table (col1, col2) VALUES (?, ?) ON CONFLICT (col1) DO UPDATE SET col1 = excluded.col1, col2 = excluded.col2
        // Note: SQLite doesn't support VALUES() function, use excluded.column instead
        $sql = preg_replace_callback(
            '/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/i',
            function ($m) {
                $updates = $m[1];
                // Replace VALUES(col) with excluded.col
                $updates = preg_replace('/VALUES\s*\(\s*(\w+)\s*\)/i', 'excluded.$1', $updates);
                return 'ON CONFLICT DO UPDATE SET ' . $updates;
            },
            $sql
        );
        
        // DATE_TRUNC('unit', col) -> strftime('%Y-%m-%d %H:%M:%S', col) dsb
        // SQLite tidak punya DATE_TRUNC, gunakan strftime
        // Tetapi cukup kompleks, biarkan dulu
        
        // ON CONFLICT -> SQLite ON CONFLICT (sudah didukung)
        // RETURNING -> SQLite RETURNING (sudah didukung di SQLite 3.35+)
        
        // LIMIT ... OFFSET -> sudah didukung
        // AUTO_INCREMENT -> sudah ditangani di schema
        
        // timestamp with time zone -> TEXT
        // INTERVAL -> tidak didukung SQLite, biarkan
        
        return $sql;
    }

    public function query($sql, $resultmode = 0) {
        $sql = $this->translateSql($sql);
        $res = $this->rawRequest([
            ['type' => 'execute', 'stmt' => ['sql' => $sql, 'args' => []]]
        ]);

        if (isset($res['error'])) {
            $this->error = $res['error'];
            $this->errno = 1001;
            return false;
        }

        $resultObj = $res['results'][0] ?? null;
        if (!is_array($resultObj) || ($resultObj['type'] ?? '') !== 'ok') {
            $this->error = json_encode($resultObj);
            $this->errno = 1002;
            return false;
        }

        $execRes = $resultObj['response']['result'] ?? [];
        $this->affected_rows = $execRes['affected_row_count'] ?? 0;
        $this->insert_id = $execRes['last_insert_rowid'] ?? 0;

        // Cek apakah ada cols/rows (SELECT)
        $colsMeta = $execRes['cols'] ?? [];
        $rowsData = $execRes['rows'] ?? [];

        if (empty($colsMeta)) {
            return true; // Bukan query SELECT
        }

        $colNames = [];
        foreach ($colsMeta as $c) {
            $colNames[] = $c['name'];
        }

        $formattedRows = [];
        foreach ($rowsData as $r) {
            $rowAssoc = [];
            foreach ($r as $i => $cell) {
                $colName = $colNames[$i] ?? ('col' . $i);
                $rowAssoc[$colName] = $cell['value'] ?? null;
            }
            $formattedRows[] = $rowAssoc;
        }

        return new turso_result($formattedRows, $colNames);
    }

    public function prepare($sql) {
        return new turso_stmt($this, (string)$sql);
    }

    public function real_escape_string($s) {
        return str_replace("'", "''", (string)$s);
    }
    public function escape_string($s) { return $this->real_escape_string($s); }
    public function set_charset($cs) { return true; }
    public function character_set_name() { return 'utf8mb4'; }
    public function begin_transaction() { return $this->query("BEGIN TRANSACTION"); }
    public function commit() { return $this->query("COMMIT"); }
    public function rollback() { return $this->query("ROLLBACK"); }
    public function autocommit($mode) { return true; }
    public function select_db($db) { return true; }
    public function ping() { return true; }
    public function close() { return true; }

    public function rawRequest(array $requests) {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['requests' => $requests]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['error' => $err];
        }
        if ($code >= 400) {
            return ['error' => "HTTP Error {$code}: {$response}"];
        }
        return json_decode($response, true);
    }
}

class turso_result {
    public int $num_rows = 0;
    public int $field_count = 0;
    private array $rows = [];
    private array $rowList = [];
    private int $cur = 0;
    public array $fields = [];

    public function __construct(array $rows, array $colNames) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
        $this->field_count = count($colNames);
        foreach ($colNames as $i => $n) {
            $this->fields[$i] = (object)['name' => $n, 'table' => '', 'type' => 'string'];
            foreach ($rows as $r) {
                $this->rowList[] = array_values($r);
                break; // cukup ambil row pertama untuk list, tapi rowList harus lengkap per row
            }
        }
        $this->rowList = [];
        foreach ($rows as $r) {
            $this->rowList[] = array_values($r);
        }
    }

    public function fetch_assoc() {
        if ($this->cur >= $this->num_rows) return null;
        return $this->rows[$this->cur++];
    }
    public function fetch_row() {
        if ($this->cur >= $this->num_rows) return null;
        return $this->rowList[$this->cur++];
    }
    public function fetch_all($mode = MYSQLI_ASSOC) {
        if ($mode === MYSQLI_NUM) return $this->rowList;
        return $this->rows;
    }
    public function fetch_object($class = null, array $args = []) {
        $a = $this->fetch_assoc();
        if ($a === null) return null;
        return $class ? new $class(...array_values($a)) : (object)$a;
    }
    public function fetch_array($mode = MYSQLI_BOTH) {
        $a = $this->fetch_assoc();
        if ($a === null) return null;
        if ($mode === MYSQLI_ASSOC) return $a;
        if ($mode === MYSQLI_NUM) return array_values($a);
        $out = []; $i = 0;
        foreach ($a as $k => $v) { $out[$k] = $v; $out[$i++] = $v; }
        return $out;
    }
    public function free() {}
    public function free_result() {}
}

class turso_stmt {
    private TursoDB $db;
    private string $sql;
    private array $params = [];
    public string $error = '';
    public int $errno = 0;
    public int $affected_rows = 0;
    public int $insert_id = 0;
    public int $num_rows = 0;
    private ?turso_result $res = null;

    public function __construct(TursoDB $db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function bind_param(string $types, &...$vars): bool {
        $this->params = $vars;
        return true;
    }

    public function execute(): bool {
        // Ganti ? dengan nilai parameter secara aman (turso http mendukung positional args)
        $tursoArgs = [];
        foreach ($this->params as $p) {
            if ($p === null) {
                $tursoArgs[] = ['type' => 'null'];
            } elseif (is_int($p) || is_float($p)) {
                $tursoArgs[] = ['type' => 'float', 'value' => (string)$p]; // atau integer
            } else {
                $tursoArgs[] = ['type' => 'text', 'value' => (string)$p];
            }
        }

        // Ganti placeholder ? satu per satu dengan nilai asli atau kirim sbg args turso
        $sqlReplaced = $this->sql;
        $paramIdx = 0;
        $params = $this->params;
        $sqlReplaced = preg_replace_callback('/\?/', function($m) use (&$paramIdx, $params) {
            $val = $params[$paramIdx++] ?? null;
            if ($val === null) return 'NULL';
            if (is_numeric($val)) return $val;
            return "'" . str_replace("'", "''", (string)$val) . "'";
        }, $sqlReplaced);

        $res = $this->db->query($sqlReplaced);
        if ($res === false) {
            $this->error = $this->db->error;
            return false;
        }
        if ($res instanceof turso_result) {
            $this->res = $res;
            $this->num_rows = $res->num_rows;
            $this->affected_rows = $res->num_rows;
        } else {
            $this->affected_rows = $this->db->affected_rows;
            $this->insert_id = $this->db->insert_id;
        }
        return true;
    }

    public function get_result() {
        return $this->res ?? new turso_result([], []);
    }
    public function fetch() { return $this->res ? $this->res->fetch_assoc() : null; }
    public function fetch_assoc() { return $this->fetch(); }
    public function close() { return true; }
}
