<?php
// includes/db_adapter.php — Adapter kompatibilitas mysqli -> Supabase PostgreSQL (PDO pgsql)
// Menyediakan API mysqli-ish (mysqli_connect, mysqli_query, $koneksi->prepare, dll)
// di atas Supabase agar 60+ file PHP aplikasi tidak perlu diubah besar-besaran.
// Catatan: Jika ekstensi mysqli native sudah terload (seperti di Vercel), kita gunakan
// nama class 'sdb' dan fungsi 'sdb_*' untuk menghindari redeclare fatal error.

if (defined('DB_ADAPTER_VERSION')) return;
define('DB_ADAPTER_VERSION', '1.0-supabase');

if (!defined('MYSQLI_ASSOC')) { define('MYSQLI_ASSOC', 1); define('MYSQLI_NUM', 2); define('MYSQLI_BOTH', 3); }

// Kredensial Supabase dibaca dari: (1) ENV vars Vercel, (2) config/local.php (local dev)
if (!defined('SUPABASE_DSN')) {
    $env_dsn = getenv('SUPABASE_DSN') ?: ($_ENV['SUPABASE_DSN'] ?? null);
    if ($env_dsn) define('SUPABASE_DSN', $env_dsn);
}
if (!defined('SUPABASE_USER')) {
    $env_user = getenv('SUPABASE_USER') ?: ($_ENV['SUPABASE_USER'] ?? null);
    if ($env_user) define('SUPABASE_USER', $env_user);
}
if (!defined('SUPABASE_PASSWORD')) {
    $env_pass = getenv('SUPABASE_PASSWORD') ?: ($_ENV['SUPABASE_PASSWORD'] ?? null);
    if ($env_pass) define('SUPABASE_PASSWORD', $env_pass);
}

// Fallback ke config/local.php untuk development lokal
$__local_config = dirname(__DIR__) . '/config/local.php';
if (!defined('SUPABASE_DSN') && is_file($__local_config)) {
    require_once $__local_config;
}
if (!defined('SUPABASE_DSN') || !defined('SUPABASE_USER') || !defined('SUPABASE_PASSWORD')) {
    throw new RuntimeException('Kredensial database belum diatur. Set env vars SUPABASE_DSN, SUPABASE_USER, SUPABASE_PASSWORD di Vercel, atau salin config/local.example.php menjadi config/local.php lalu isi kredensialnya.');
}
unset($__local_config);

// Deteksi apakah ekstensi mysqli native sudah terload
$mysqli_native = extension_loaded('mysqli');

// Nama class & fungsi yang akan dipakai
$className = $mysqli_native ? 'sdb' : 'mysqli';
$connectFunc = $mysqli_native ? 'sdb_connect' : 'mysqli_connect';
$errorFunc = $mysqli_native ? 'sdb_error' : 'mysqli_connect_error';

/* =========================================================================
 *  UTILITAS PARSER
 * ========================================================================= */
// Ambil isi kurung berimbang mulai dari $start (harus karakter '(').
// Return [isi_dalam_kurung, offset_kurung_tutup] atau null.
function dbx_balanced(string $s, int $start): ?array {
    $L = strlen($s);
    if ($start >= $L || $s[$start] !== '(') return null;
    $depth = 0; $instr = false;
    for ($i = $start; $i < $L; $i++) {
        $c = $s[$i];
        if ($instr) { if ($c === "'") $instr = false; continue; }
        if ($c === "'" && ($i === 0 || $s[$i - 1] !== '\\')) { $instr = true; continue; }
        if ($c === '(') $depth++;
        elseif ($c === ')') { $depth--; if ($depth === 0) return [substr($s, $start + 1, $i - $start - 1), $i]; }
    }
    return null;
}

// Konversi format DATE_FORMAT MySQL -> format TO_CHAR PostgreSQL
function dbx_datefmt_to_pg(string $f): string {
    $map = [
        '%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%d' => 'DD', '%H' => 'HH24',
        '%i' => 'MI', '%s' => 'SS', '%S' => 'SS', '%M' => 'FMMonth', '%D' => 'DDth',
        '%b' => 'Mon', '%e' => 'FMD', '%T' => 'HH24:MI:SS', '%p' => 'AM', '%%' => '%',
    ];
    return str_replace(array_keys($map), array_values($map), $f);
}

// Quote string untuk PostgreSQL (value literal)
function dbx_pgq(string $s): string {
    return "'" . str_replace("'", "''", $s) . "'";
}

// Split argumen fungsi dengan memperhatikan kurung bersarang & string
function dbx_split_args(string $s): array {
    $args = []; $cur = ''; $depth = 0; $instr = false;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($instr) {
            $cur .= $c;
            if ($c === "'" && ($i === 0 || $s[$i - 1] !== '\\')) $instr = false;
            continue;
        }
        if ($c === "'") { $cur .= $c; $instr = true; continue; }
        if ($c === '(' || $c === '[') { $depth++; $cur .= $c; continue; }
        if ($c === ')' || $c === ']') { $depth--; $cur .= $c; continue; }
        if ($c === ',' && $depth === 0) { $args[] = trim($cur); $cur = ''; continue; }
        $cur .= $c;
    }
    if ($cur !== '') $args[] = trim($cur);
    return $args;
}

// Scan & replace nama fungsi SQL (mis. DATE_FORMAT, GROUP_CONCAT) dengan callback
function dbx_scan_replace(string $sql, string $funcName, callable $cb): string {
    $len = strlen($sql);
    $fnLen = strlen($funcName);
    $out = '';
    $i = 0;
    while ($i < $len) {
        if ($i + $fnLen <= $len && strcasecmp(substr($sql, $i, $fnLen), $funcName) === 0) {
            $after = $i + $fnLen;
            $nextNonWs = $after;
            while ($nextNonWs < $len && $sql[$nextNonWs] === ' ') $nextNonWs++;
            if ($nextNonWs < $len && $sql[$nextNonWs] === '(') {
                $out .= substr($sql, $i, $nextNonWs - $i + 1);
                $i = $nextNonWs + 1;
                $depth = 1; $instr = false;
                $inner = '';
                while ($i < $len && $depth > 0) {
                    $c = $sql[$i];
                    if ($instr) {
                        $inner .= $c;
                        if ($c === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) $instr = false;
                        $i++; continue;
                    }
                    if ($c === "'") { $inner .= $c; $instr = true; $i++; continue; }
                    if ($c === '(') { $depth++; $inner .= $c; $i++; continue; }
                    if ($c === ')') { $depth--; if ($depth === 0) break; $inner .= $c; $i++; continue; }
                    $inner .= $c; $i++;
                }
                $out .= $cb($inner);
                $i++;
                continue;
            }
        }
        $out .= $sql[$i];
        $i++;
    }
    return $out;
}

// Rewrite view name -> base table
function dbx_rewrite_views(string $sql): string {
    $views = [
        'view_database_utama' => 'data_konsumen',
    ];
    foreach ($views as $v => $t) {
        $sql = preg_replace('/\b' . preg_quote($v, '/') . '\b/i', '"' . $t . '"', $sql);
    }
    return $sql;
}

/* =========================================================================
 *  TERJEMAHAN SQL MySQL -> PostgreSQL
 * ========================================================================= */
function dbx_translate_sql(string $sql): string {
    $sql = trim($sql);
    $origSql = $sql;

    // ---- 0. NOW() -> CURRENT_TIMESTAMP (PG supports NOW() too, but be explicit) ----
    $sql = preg_replace('/\bNOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql);

    // ---- 1. SET time_zone MySQL -> SET timezone PG ----
    $sql = preg_replace("/SET\s+time_zone\s*=\s*'\+07:00'/i", "SET timezone = 'Asia/Jakarta'", $sql);

    // ---- 2. DATE_FORMAT(x, 'fmt') -> TO_CHAR(x, 'pgfmt') ----
    $sql = dbx_scan_replace($sql, 'DATE_FORMAT', function ($inner) {
        $firstComma = strpos($inner, ',');
        $field = ($firstComma !== false) ? trim(substr($inner, 0, $firstComma)) : $inner;
        $fmt = preg_match("/'((?:\\\\.|[^'\\\\])*)'\s*\$/", $inner, $fm) ? $fm[1] : 'YYYY-MM-DD';
        return "TO_CHAR($field, '" . dbx_datefmt_to_pg($fmt) . "')";
    });

    // ---- 3. GROUP_CONCAT(...) -> string_agg(...) ----
    $sql = dbx_scan_replace($sql, 'GROUP_CONCAT', function ($inner) {
        $distinct = (bool)preg_match('/^\s*DISTINCT\s+/is', $inner);
        $body = trim(preg_replace('/^\s*DISTINCT\s+/is', '', $inner));
        $sep = ',';
        if (preg_match("/\bSEPARATOR\s+'((?:\\\\.|[^'\\\\])*)'/is", $body, $sm)) {
            $sep = stripcslashes($sm[1]);
            $body = trim(preg_replace("/\bSEPARATOR\s+'((?:\\\\.|[^'\\\\])*)'/is", '', $body));
        }
        if (preg_match('/\bORDER\s+BY\s+([\s\S]+)$/i', $body, $om)) {
            $order = ' ORDER BY ' . trim($om[1]);
            $body = trim(substr($body, 0, strlen($body) - strlen($om[0])));
        } else $order = '';
        return 'string_agg(' . ($distinct ? 'DISTINCT ' : '') . rtrim($body) . ', ' . dbx_pgq($sep) . $order . ')';
    });

    // ---- 4. DATE_ADD / DATE_SUB(..., INTERVAL n UNIT) ----
    $sql = dbx_scan_replace($sql, 'DATE_ADD', function ($inner) {
        if (!preg_match('/^(.*?)\s*,\s*INTERVAL\s+([\S]+)\s+(\w+)\s*$/is', $inner, $m))
            return "($inner)";
        return "({$m[1]} + INTERVAL '{$m[2]} " . strtolower($m[3]) . "')";
    });
    $sql = dbx_scan_replace($sql, 'DATE_SUB', function ($inner) {
        if (!preg_match('/^(.*?)\s*,\s*INTERVAL\s+([\S]+)\s+(\w+)\s*$/is', $inner, $m))
            return "($inner)";
        return "({$m[1]} - INTERVAL '{$m[2]} " . strtolower($m[3]) . "')";
    });

    // ---- 5. INTERVAL n UNIT umum (mis. NOW() - INTERVAL 30 MINUTE) ----
    $sql = preg_replace('/\bINTERVAL\s+(\d+)\s+(DAY|WEEK|MONTH|YEAR|HOUR|MINUTE|SECOND)\b/i',
        "INTERVAL '$1 $2'", $sql);

    // ---- 6. TIMESTAMPDIFF(SECOND, a, b) -> EXTRACT(EPOCH FROM (b - a))::int ----
    $sql = dbx_scan_replace($sql, 'TIMESTAMPDIFF', function ($inner) {
        $args = dbx_split_args($inner);
        if (count($args) < 3) return "TIMESTAMPDIFF($inner)";
        $unit = strtoupper(trim($args[0]));
        $a = trim($args[1]); $b = trim($args[2]);
        switch ($unit) {
            case 'SECOND': $div = 1; break;
            case 'MINUTE': $div = 60; break;
            case 'HOUR':   $div = 3600; break;
            case 'DAY':    $div = 86400; break;
            case 'MONTH':  $div = 2629800; break;
            case 'YEAR':   $div = 31557600; break;
            default:       $div = 1;
        }
        if ($div === 1) return "(EXTRACT(EPOCH FROM ($b) - ($a))::int)";
        return "((EXTRACT(EPOCH FROM ($b) - ($a)) / $div)::int)";
    });

    // ---- 7. CAST(x AS UNSIGNED) -> CAST(x AS INTEGER) ----
    $sql = preg_replace('/\bAS\s+UNSIGNED\b/i', 'AS INTEGER', $sql);

    // ---- 8. ILIKE -> LIKE (PG supports ILIKE, keep as is for case-insensitive) ----
    // Already supported by PG, no change needed

    // ---- 9. ON DUPLICATE KEY UPDATE -> ON CONFLICT DO UPDATE ----
    // Pattern: INSERT INTO ... VALUES ... ON DUPLICATE KEY UPDATE col=VALUES(col), ...
    // Need to find unique constraint columns to build ON CONFLICT (...)
    $sql = dbx_rewrite_on_duplicate_key($sql);

    // ---- 10. Rewrite view names ----
    $sql = dbx_rewrite_views($sql);

    return $sql;
}

/* Resolusi ON DUPLICATE KEY UPDATE -> ON CONFLICT */
function dbx_rewrite_on_duplicate_key(string $sql): string {
    // Hanya proses jika mengandung ON DUPLICATE KEY UPDATE
    if (!preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql)) return $sql;

    // Ekstrak bagian INSERT INTO table (cols) VALUES ...
    if (!preg_match('/^\s*INSERT\s+INTO\s+(\w+)\s*(?:\(([^)]+)\))?\s*VALUES\s*\((.+)\)\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/is', $sql, $m)) {
        return $sql;
    }

    $table = $m[1];
    $colsPart = $m[2] ?? '';
    $valsPart = $m[3];
    $updPart = $m[4];

    // Cari unique index / primary key dari tabel untuk build ON CONFLICT
    global $dbxLastPDO;
    $targetCols = null;
    if ($dbxLastPDO instanceof PDO) {
        try {
            $st = $dbxLastPDO->prepare(
                "SELECT a.attname
                 FROM pg_index i
                 JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                 WHERE i.indrelid = ?::regclass AND i.indisunique AND i.indisprimary
                 ORDER BY a.attnum");
            $st->execute([$table]);
            $targetCols = $st->fetchAll(PDO::FETCH_COLUMN);
            if (!$targetCols) {
                $st2 = $dbxLastPDO->prepare(
                    "SELECT a.attname
                     FROM pg_index i
                     JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                     WHERE i.indrelid = ?::regclass AND i.indisunique
                     ORDER BY a.attnum");
                $st2->execute([$table]);
                $targetCols = $st2->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Throwable $e) {
            $targetCols = null;
        }
    }
    if (!$targetCols && $colsPart) {
        $targetCols = array_map('trim', explode(',', $colsPart));
    }
    if (!$targetCols) return $origSql;

    // Terjemahkan assignment: VALUES(col) -> EXCLUDED.col
    $assigns = [];
    foreach (preg_split('/,(?![^\'(]*[\')])/', $updPart) as $as) {
        $as = trim($as);
        if ($as === '') continue;
        if (preg_match('/^(\w+)\s*=\s*VALUES\s*\(\s*(\w+)\s*\)$/is', $as, $am)) {
            $assigns[] = "\"{$am[1]}\" = EXCLUDED.\"{$am[2]}\"";
        } elseif (preg_match('/^(\w+)\s*=\s*\'((?:\\\\.|[^\'\\\\])*)\'$/is', $as, $am)) {
            $assigns[] = "\"{$am[1]}\" = " . dbx_pgq($am[2]);
        } elseif (preg_match('/^"?([\w]+)"?\s*=\s*/', $as)) {
            $assigns[] = preg_replace('/^"?([\w]+)"?\s*=\s*/', '"$1" = ', $as);
        } else {
            $assigns[] = $as;
        }
    }
    $cols = implode(', ', array_map(fn($c) => '"' . $c . '"', $targetCols));
    $prefix = preg_replace('/\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+.*$/is', '', $origSql);
    return $prefix . ' ON CONFLICT (' . $cols . ') DO UPDATE SET ' . implode(', ', $assigns);
}

/* Deskripsi tabel PG untuk fungsi backup di pengaturan.php */
function dbx_pg_describe_table(string $t): ?string {
    global $dbxLastPDO;
    if (!($dbxLastPDO instanceof PDO)) return null;
    try {
        $st = $dbxLastPDO->prepare(
            "SELECT column_name, data_type, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema='public' AND table_name=? ORDER BY ordinal_position");
        $st->execute([$t]);
        $cols = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$cols) return null;
        $defs = [];
        foreach ($cols as $c) {
            $def = '"' . $c['column_name'] . '" ' . strtoupper($c['data_type']);
            if ($c['column_default'] !== null) $def .= ' DEFAULT ' . $c['column_default'];
            if ($c['is_nullable'] === 'NO') $def .= ' NOT NULL';
            $defs[] = $def;
        }
        return "-- {$t} (PostgreSQL)\nCREATE TABLE \"{$t}\" (\n  " . implode(",\n  ", $defs) . "\n);";
    } catch (Throwable $e) { return null; }
}

/* =========================================================================
 *  CLASS sdb / mysqli (compat) — connect adalah Supabase, bukan MySQL lama
 * ========================================================================= */
class sdb {
    public $connect_error  = '';
    public $connect_errno  = 0;
    public $error          = '';
    public $errno          = 0;
    public $insert_id      = 0;
    public $affected_rows  = 0;
    public $server_info    = 'PostgreSQL 17 (Supabase)';
    public $client_version = 180002;
    public $info           = '';
    public $driver         = 'pgsql';
    private ?PDO $pdo      = null;
    private bool $inTx     = false;

    public function getDriver(): string { return $this->driver; }

    public function __construct($host = null, $user = null, $pass = null, $db = null,
                                $port = null, $socket = null) {
        // Semua parameter lama MySQL diabaikan; koneksi tetap ke Supabase
        try {
            $this->pdo = new PDO(SUPABASE_DSN, SUPABASE_USER, SUPABASE_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 20,
            ]);
            try { $this->pdo->exec("SET timezone = 'Asia/Jakarta'"); } catch (Throwable $e) {}
            global $dbxLastPDO;
            $dbxLastPDO = $this->pdo;
        } catch (Throwable $e) {
            $this->connect_error = $e->getMessage();
            $this->connect_errno = $e->getCode() ?: 1;
            $this->error = $e->getMessage();
            $this->errno = $this->connect_errno;
            $this->pdo = null;
        }
    }

    public function query($sql, $resultmode = 0) {
        if (!$this->pdo) { $this->error = 'No connection'; return false; }
        $this->error = ''; $this->errno = 0;
        $translated = dbx_translate_sql($sql);
        try {
            $stmt = $this->pdo->query($translated);
            $this->affected_rows = $stmt->rowCount();
            if (stripos(trim($translated), 'INSERT') === 0) {
                try { $this->insert_id = (int) $this->pdo->lastInsertId(); } catch (Throwable $e) {}
            }
            $cols = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                $n = (string)($meta['name'] ?? ('col' . $i));
                $cols[] = (object)[
                    'name'  => $n,
                    'table' => (string)($meta['table'] ?? ''),
                    'type'  => (string)($meta['driver:decl_type'] ?? 'string'),
                    'len'   => 0,
                    'flags' => 0,
                ];
            }
            return new sdb_result($stmt, $cols);
        } catch (Throwable $e) {
            $this->markError($e);
            return false;
        }
    }

    private function markError(Throwable $e) {
        $this->error = $e->getMessage();
        $this->errno = $e->getCode() ?: 1;
        error_log('db_adapter: ' . $this->driver . ': ' . $e->getMessage());
    }

    public function prepare($sql) {
        return new sdb_stmt($this, $sql);
    }

    public function real_escape_string($s) {
        if (!$this->pdo) return str_replace("'", "''", $s);
        // PDO::quote() menambahkan tanda petik, kita hanya perlu escape single quote
        return str_replace("'", "''", $s);
    }
    public function escape_string($s) { return $this->real_escape_string($s); }

    public function set_charset($cs) { return true; }
    public function character_set_name() { return 'utf8'; }
    public function get_charset() { return ['charset' => 'utf8']; }
    public function host_info() { return 'Supabase PostgreSQL via PDO'; }

    public function begin_transaction($flags = 0, $name = null) {
        if ($this->inTx) return true;
        try { $this->pdo->beginTransaction(); $this->inTx = true; return true; }
        catch (Throwable $e) { $this->markError($e); return false; }
    }
    public function commit($flags = 0, $name = null) {
        if (!$this->inTx) return true;
        try { $this->pdo->commit(); $this->inTx = false; return true; }
        catch (Throwable $e) { $this->markError($e); return false; }
    }
    public function rollback($flags = 0, $name = null) {
        if (!$this->inTx) return true;
        try { $this->pdo->rollBack(); $this->inTx = false; return true; }
        catch (Throwable $e) { $this->markError($e); return false; }
    }
    public function autocommit($mode) {
        if ($mode) return $this->commit();
        return $this->begin_transaction();
    }
    public function multi_query($sql) { return false; }
    public function store_result() { return true; }
    public function next_result() { return false; }
    public function more_results() { return false; }
    public function select_db($db) { return strtolower($db) === 'postgres'; }
    public function ping() { return $this->pdo !== null; }
    public function ssl_set() { return true; }
    public function stmt_init() { return new sdb_stmt($this, ''); }
    public function close() { $this->pdo = null; return true; }
    public function __destruct() { $this->pdo = null; }
}

/* =========================================================================
 *  CLASS sdb_result / mysqli_result (compat)
 * ========================================================================= */
class sdb_result implements IteratorAggregate {
    public int $num_rows      = 0;
    public int $field_count   = 0;
    public int $current_field = 0;
    public array $fields      = [];
    private array $rows       = [];
    private array $rowList    = [];
    private int $cur          = 0;

    public function __construct($rows, array $fieldNames = []) {
        if ($rows instanceof PDOStatement) {
            $cnt = max(0, $rows->columnCount());
            $names = [];
            for ($i = 0; $i < $cnt; $i++) {
                $meta = @($rows->getColumnMeta($i) ?: []);
                $n = (string)($meta['name'] ?? ('col' . $i));
                $names[$i] = $n;
                $this->fields[$i] = (object)[
                    'name'  => $n,
                    'table' => (string)($meta['table'] ?? ''),
                    'type'  => (string)($meta['driver:decl_type'] ?? 'string'),
                    'len'   => 0,
                    'flags' => 0,
                ];
            }
            $this->rows = $rows->fetchAll(PDO::FETCH_ASSOC);
            foreach ($this->rows as $r) $this->rowList[] = array_values($r);
        } elseif (is_array($rows)) {
            $this->rows = $rows;
            foreach ($fieldNames as $i => $n) {
                $this->fields[$i] = (object)['name' => $n, 'table' => '', 'type' => 'string', 'len' => 0, 'flags' => 0];
            }
            foreach ($this->rows as $r) $this->rowList[] = array_values($r);
        }
        $this->field_count = count($this->fields);
        $this->num_rows = count($this->rows);
    }

    public function getIterator(): Traversable { return new ArrayIterator($this->rows); }
    public function fetch_assoc() { return ($this->cur < $this->num_rows) ? $this->rows[$this->cur++] : null; }
    public function fetch_row()   { return ($this->cur < $this->num_rows) ? $this->rowList[$this->cur++] : null; }
    public function fetch_array($mode = MYSQLI_BOTH) {
        if ($this->cur >= $this->num_rows) return null;
        $assoc = $this->rows[$this->cur];
        $num   = $this->rowList[$this->cur];
        $this->cur++;
        if ($mode === MYSQLI_ASSOC) return $assoc;
        if ($mode === MYSQLI_NUM) return $num;
        return $assoc + $num;
    }
    public function fetch_object($class = 'stdClass', array $ctorArgs = []) {
        if ($this->cur >= $this->num_rows) return null;
        $obj = new $class(...$ctorArgs);
        foreach ($this->rows[$this->cur] as $k => $v) $obj->$k = $v;
        $this->cur++;
        return $obj;
    }
    public function fetch_all($mode = MYSQLI_ASSOC) {
        $out = [];
        for ($i = $this->cur; $i < $this->num_rows; $i++) {
            if ($mode === MYSQLI_ASSOC) $out[] = $this->rows[$i];
            elseif ($mode === MYSQLI_NUM) $out[] = $this->rowList[$i];
            else $out[] = $this->rows[$i] + $this->rowList[$i];
        }
        $this->cur = $this->num_rows;
        return $out;
    }
    public function data_seek($offset) { if ($offset >= 0 && $offset < $this->num_rows) { $this->cur = $offset; return true; } return false; }
    public function free() { $this->rows = []; $this->rowList = []; $this->fields = []; $this->num_rows = 0; $this->field_count = 0; }
    public function fetch_field() {
        if ($this->current_field >= $this->field_count) return false;
        return $this->fields[$this->current_field++];
    }
    public function fetch_field_direct($nr) {
        return ($nr >= 0 && $nr < $this->field_count) ? $this->fields[$nr] : false;
    }
    public function field_seek($nr) { if ($nr >= 0 && $nr < $this->field_count) { $this->current_field = $nr; return true; } return false; }
}

/* =========================================================================
 *  CLASS sdb_stmt / mysqli_stmt (compat)
 * ========================================================================= */
class sdb_stmt {
    private sdb $owner;
    private string $sql;
    private array $params = [];
    public string $error = '';
    public int $errno = 0;
    public int $affected_rows = 0;
    public int $insert_id = 0;
    public int $num_rows = 0;
    private ?sdb_result $res = null;

    public function __construct(sdb $owner, string $sql) {
        $this->owner = $owner;
        $this->sql = $sql;
    }

    public function bind_param(string $types, &...$vars): bool {
        $this->params = $vars;
        return true;
    }

    public function execute(): bool {
        $translated = dbx_translate_sql($this->sql);
        try {
            $st = $this->owner->pdo->prepare($translated);
            if (!$st) { $this->owner->markError(new Exception('Prepare failed')); return false; }
            foreach ($this->params as $i => $v) {
                $st->bindValue($i + 1, $v);
            }
            $ok = $st->execute();
            $this->affected_rows = $st->rowCount();
            try { $this->insert_id = (int) $this->owner->pdo->lastInsertId(); } catch (Throwable $e) {}
            $this->owner->affected_rows = $this->affected_rows;
            $this->owner->insert_id = $this->insert_id;
            if ($ok && stripos(trim($translated), 'SELECT') === 0) {
                $this->res = new sdb_result($st);
                $this->num_rows = $this->res->num_rows;
            }
            return $ok;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->errno = $e->getCode() ?: 1;
            $this->owner->markError($e);
            return false;
        }
    }

    public function get_result() { return $this->res; }
    public function fetch() { return $this->res ? $this->res->fetch_assoc() : null; }
    public function store_result(): bool { if ($this->res) { $this->num_rows = $this->res->num_rows; return true; } return false; }
    public function data_seek($i) { return $this->res ? $this->res->data_seek($i) : true; }
    public function free_result() { if ($this->res) $this->res->free(); }
    public function close(): bool { $this->params = []; $this->res = null; return true; }
}

/* =========================================================================
 *  FUNGSI PROSEDURAL sdb_* / mysqli_* (compat)
 * ========================================================================= */
function sdb_connect($host = null, $user = null, $pass = null, $db = null, $port = null) {
    return new sdb($host, $user, $pass, $db, $port);
}
function sdb_connect_error() { return ''; }
function sdb_select_db($c, string $db): bool { return $c->select_db($db); }
function sdb_query($c, string $sql, int $mode = 0) { return $c->query($sql, $mode); }
function sdb_real_escape_string($c, string $s) { return $c->real_escape_string($s); }
function sdb_escape_string($c, string $s) { return $c->real_escape_string($s); }
function sdb_set_charset($c, string $cs): bool { return $c->set_charset($cs); }
function sdb_error($c): string { return $c->error; }
function sdb_errno($c): int { return $c->errno; }
function sdb_fetch_assoc($r) { return ($r instanceof sdb_result) ? $r->fetch_assoc() : null; }
function sdb_fetch_row($r)   { return ($r instanceof sdb_result) ? $r->fetch_row() : null; }
function sdb_fetch_object($r, $class = null, array $ca = []) { return ($r instanceof sdb_result) ? $r->fetch_object($class, $ca) : null; }
function sdb_fetch_array($r, $m = MYSQLI_BOTH) { return ($r instanceof sdb_result) ? $r->fetch_array($m) : null; }
function sdb_fetch_all($r, $m = MYSQLI_ASSOC) { return ($r instanceof sdb_result) ? $r->fetch_all($m) : []; }
function sdb_num_rows($r): int { return ($r instanceof sdb_result) ? $r->num_rows : 0; }
function sdb_num_fields($r): int { return ($r instanceof sdb_result) ? $r->field_count : 0; }
function sdb_fetch_fields($r): array { return ($r instanceof sdb_result) ? $r->fields : []; }
function sdb_fetch_field($r) { return ($r instanceof sdb_result) ? $r->fetch_field() : false; }
function sdb_fetch_field_direct($r, $i) { return ($r instanceof sdb_result) ? $r->fetch_field_direct($i) : false; }
function sdb_field_seek($r, $i) { return ($r instanceof sdb_result) ? $r->field_seek($i) : false; }
function sdb_data_seek($r, $i) { return ($r instanceof sdb_result) ? $r->data_seek($i) : false; }
function sdb_free_result($r) { if ($r instanceof sdb_result) $r->free(); }
function sdb_insert_id($c): int { return $c->insert_id; }
function sdb_affected_rows($c): int { return $c->affected_rows; }
function sdb_prepare($c, string $sql) { return $c->prepare($sql); }
function sdb_stmt_bind_param($st, string $types, &...$args): bool { return $st->bind_param($types, ...$args); }
function sdb_stmt_execute($st): bool { return $st->execute(); }
function sdb_stmt_get_result($st) { return $st->get_result(); }
function sdb_stmt_fetch($st) { return $st->fetch(); }
function sdb_stmt_close($st): bool { return $st->close(); }
function sdb_stmt_free_result($st): void { $st->free_result(); }
function sdb_stmt_num_rows($st): int { return $st->num_rows; }
function sdb_stmt_errno($st): int { return $st->errno; }
function sdb_stmt_error($st): string { return $st->error; }
function sdb_stmt_insert_id($st): int { return $st->insert_id; }
function sdb_stmt_affected_rows($st): int { return $st->affected_rows; }
function sdb_begin_transaction($c): bool { return $c->begin_transaction(); }
function sdb_commit($c): bool { return $c->commit(); }
function sdb_rollback($c): bool { return $c->rollback(); }
function sdb_autocommit($c, bool $m): bool { return $c->autocommit($m); }
function sdb_close($c): bool { return $c->close(); }
function sdb_ping($c): bool { return $c->ping(); }

/* Alias mysqli_* ke sdb_* agar kode lama tetap jalan jika native mysqli TIDAK terload */
if (!$mysqli_native) {
    class_alias('sdb', 'mysqli', true);
    class_alias('sdb_result', 'mysqli_result', true);
    class_alias('sdb_stmt', 'mysqli_stmt', true);
    // Alias functions
    function mysqli_connect($host = null, $user = null, $pass = null, $db = null, $port = null) { return sdb_connect($host, $user, $pass, $db, $port); }
    function mysqli_connect_error() { return ''; }
    function mysqli_select_db($c, string $db): bool { return sdb_select_db($c, $db); }
    function mysqli_query($c, string $sql, int $mode = 0) { return sdb_query($c, $sql, $mode); }
    function mysqli_real_escape_string($c, string $s) { return sdb_real_escape_string($c, $s); }
    function mysqli_escape_string($c, string $s) { return sdb_escape_string($c, $s); }
    function mysqli_set_charset($c, string $cs): bool { return sdb_set_charset($c, $cs); }
    function mysqli_error($c): string { return sdb_error($c); }
    function mysqli_errno($c): int { return sdb_errno($c); }
    function mysqli_fetch_assoc($r) { return sdb_fetch_assoc($r); }
    function mysqli_fetch_row($r) { return sdb_fetch_row($r); }
    function mysqli_fetch_object($r, $class = null, array $ca = []) { return sdb_fetch_object($r, $class, $ca); }
    function mysqli_fetch_array($r, $m = MYSQLI_BOTH) { return sdb_fetch_array($r, $m); }
    function mysqli_fetch_all($r, $m = MYSQLI_ASSOC) { return sdb_fetch_all($r, $m); }
    function mysqli_num_rows($r): int { return sdb_num_rows($r); }
    function mysqli_num_fields($r): int { return sdb_num_fields($r); }
    function mysqli_fetch_fields($r): array { return sdb_fetch_fields($r); }
    function mysqli_fetch_field($r) { return sdb_fetch_field($r); }
    function mysqli_fetch_field_direct($r, $i) { return sdb_fetch_field_direct($r, $i); }
    function mysqli_field_seek($r, $i) { return sdb_field_seek($r, $i); }
    function mysqli_data_seek($r, $i) { return sdb_data_seek($r, $i); }
    function mysqli_free_result($r) { sdb_free_result($r); }
    function mysqli_insert_id($c): int { return sdb_insert_id($c); }
    function mysqli_affected_rows($c): int { return sdb_affected_rows($c); }
    function mysqli_prepare($c, string $sql) { return sdb_prepare($c, $sql); }
    function mysqli_stmt_bind_param($st, string $types, &...$args): bool { return sdb_stmt_bind_param($st, $types, ...$args); }
    function mysqli_stmt_execute($st): bool { return sdb_stmt_execute($st); }
    function mysqli_stmt_get_result($st) { return sdb_stmt_get_result($st); }
    function mysqli_stmt_fetch($st) { return sdb_stmt_fetch($st); }
    function mysqli_stmt_close($st): bool { return sdb_stmt_close($st); }
    function mysqli_stmt_free_result($st): void { sdb_stmt_free_result($st); }
    function mysqli_stmt_num_rows($st): int { return sdb_stmt_num_rows($st); }
    function mysqli_stmt_errno($st): int { return sdb_stmt_errno($st); }
    function mysqli_stmt_error($st): string { return sdb_stmt_error($st); }
    function mysqli_stmt_insert_id($st): int { return sdb_stmt_insert_id($st); }
    function mysqli_stmt_affected_rows($st): int { return sdb_stmt_affected_rows($st); }
    function mysqli_begin_transaction($c): bool { return sdb_begin_transaction($c); }
    function mysqli_commit($c): bool { return sdb_commit($c); }
    function mysqli_rollback($c): bool { return sdb_rollback($c); }
    function mysqli_autocommit($c, bool $m): bool { return sdb_autocommit($c, $m); }
    function mysqli_close($c): bool { return sdb_close($c); }
    function mysqli_ping($c): bool { return sdb_ping($c); }

    // DB_MODE-specific connect functions (for backward compatibility)
    function sdb_connect_pgsql() { return sdb_connect(); }
    function sdb_connect_mysql() { return sdb_connect(); }
}