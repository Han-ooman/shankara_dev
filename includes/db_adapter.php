<?php
// includes/db_adapter.php — Adapter kompatibilitas sdb -> Supabase PostgreSQL (PDO pgsql)
// Menyediakan API mysqli-ish (sdb_connect, sdb_query, $koneksi->prepare, dll)
// di atas Supabase agar 60+ file PHP aplikasi tidak perlu diubah besar-besaran.
// Catatan penting: ekstensi sdb HARUS dinonaktifkan di php.ini agar definisi
// fungsi/class di sini tidak fatal error karena redeclare.

if (defined('DB_ADAPTER_VERSION')) return;
define('DB_ADAPTER_VERSION', '1.0-supabase');

if (!defined('MYSQLI_ASSOC')) { define('MYSQLI_ASSOC', 1); define('MYSQLI_NUM', 2); define('MYSQLI_BOTH', 3); }

// Kredensial Supabase, urutan prioritas:
// 1. config/local.php (lokal, di-gitignore)
// 2. Environment variables: SUPABASE_DSN, SUPABASE_USER, SUPABASE_PASSWORD (dipakai di Vercel)
// Contoh tersedia di config/local.example.php.
$__local_config = dirname(__DIR__) . '/config/local.php';
if (!defined('SUPABASE_DSN') && is_file($__local_config)) {
    require_once $__local_config;
}
if (!defined('SUPABASE_DSN') && getenv('SUPABASE_DSN')) {
    define('SUPABASE_DSN', getenv('SUPABASE_DSN'));
    define('SUPABASE_USER', getenv('SUPABASE_USER'));
    define('SUPABASE_PASSWORD', getenv('SUPABASE_PASSWORD'));
}
if (!defined('SUPABASE_DSN') || !defined('SUPABASE_USER') || !defined('SUPABASE_PASSWORD')) {
    throw new RuntimeException('Kredensial database belum diatur. Salin config/local.example.php menjadi config/local.php lalu isi kredensialnya.');
}
unset($__local_config);

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

function dbx_pgq(string $v): string {
    return "'" . str_replace("\\", "\\\\", str_replace("'", "''", $v)) . "'";
}

// Belah isi argumen berimbang berdasarkan koma level atas
function dbx_split_args(string $inner): array {
    $out = []; $cur = ''; $depth = 0; $instr = false;
    for ($i = 0, $L = strlen($inner); $i < $L; $i++) {
        $c = $inner[$i];
        if ($instr) { $cur .= $c; if ($c === "'") $instr = false; continue; }
        if ($c === "'") { $instr = true; $cur .= $c; continue; }
        if ($c === '(') $depth++;
        elseif ($c === ')') $depth--;
        if ($c === ',' && $depth === 0) { $out[] = $cur; $cur = ''; continue; }
        $cur .= $c;
    }
    if (trim($cur) !== '') $out[] = $cur;
    return $out;
}

// Pindai SQL untuk memanggil $cb(isi_luar) di setiap pemanggilan FUNGSI(...),
// lalu hasilnya mengganti seluruh pemanggilan. Utk kelas utama DATE_FORMAT.
function dbx_scan_replace(string $sql, string $name, callable $cb): string {
    if (!preg_match('/\b' . preg_quote($name, '/') . '\s*\(/i', $sql)) return $sql;
    $out = ''; $i = 0; $L = strlen($sql);
    while ($i < $L) {
        $ok = preg_match('/\b' . preg_quote($name, '/') . '\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE, $i);
        if (!$ok) { $out .= substr($sql, $i); break; }
        $j = $m[0][1];
        $out .= substr($sql, $i, $j - $i);
        $open = $j + strlen($m[0][0]) - 1;
        $b = dbx_balanced($sql, $open);
        if ($b === null) { $out .= substr($sql, $j, strlen($m[0][0])); $i = $j + strlen($m[0][0]); continue; }
        list($inner, $close) = $b;
        $out .= $cb($inner);
        $i = $close + 1;
    }
    return $out;
}

/* =========================================================================
 *  KATALOG PG (resolusi ON CONFLICT + backup di pengaturan.php)
 * ========================================================================= */
// Peta view applikasi yg dipakai untuk menulis data sebagai target event:
// aplikasi menulis lewat nama view (MySQL auto-updatable); PG tidak bisa,
// dinulis ulang ke tabel dasar di adapter.
function dbx_view_write_map(): array {
    static $map = null;
    if ($map !== null) return $map;
    $map = [
        'data_cluster'    => ['base' => 'cluster',        'alias' => []],
        'data_rumah'      => ['base' => 'rumah',          'alias' => []],
        'data_konsumen'   => ['base' => 'konsumen',       'alias' => []],
        'data_prospek'    => ['base' => 'prospek',        'alias' => ['catatan' => 'keterangan']],
        'data_pembayaran' => ['base' => 'pembayaran',     'alias' => []],
        'data_bast'       => ['base' => 'serah_terima',   'alias' => []],
        'data_pembangunan'=> ['base' => 'pembangunan',    'alias' => []],
        'data_pembatalan' => ['base' => 'pembatalan_unit','alias' => []],
        'data_bi_checking'=> ['base' => 'bi_checking',    'alias' => [
            'id_bi_checking' => 'id_bi',
            'tgl_cek'        => 'tgl_pengajuan',
            'hasil_bi'       => 'hasil',
            'catatan'        => 'keterangan',
        ]],
    ];
    return $map;
}

// Ambil indeks unique: [[cols => [..], primary => bool], ...]
function dbx_unique_indexes(PDO $pdo, string $table): array {
    static $cache = [];
    $k = strtolower($table);
    if (isset($cache[$k])) return $cache[$k];
    $res = [];
    try {
        $st = $pdo->prepare(
            "SELECT i.indexrelid::regclass::text AS idx, i.indisprimary AS isprim, a.attname AS col
             FROM pg_index i
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
             JOIN pg_class c ON c.oid = i.indrelid
             WHERE c.relname = ? AND i.indisunique AND a.attisdropped = false
             ORDER BY 1, a.attnum"
        );
        $st->execute([$table]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $res[$r['idx']]['cols'][] = $r['col'];
            $res[$r['idx']]['primary'] = ($r['isprim'] === 't' || $r['isprim'] === true);
        }
    } catch (Throwable $e) { $res = []; }
    $cache[$k] = $res;
    return $res;
}

/* Tulis-ulang statement yang menyasar view aplikasi -> tabel dasar PG.
 * Mendukung INSERT INTO <view> / UPDATE <view> / DELETE FROM <view> dan
 * pemetaan alias kolom (mis. data_bi_checking: tgl_cek -> tgl_pengajuan). */
function dbx_rewrite_views(string $sql): string {
    foreach (dbx_view_write_map() as $view => $conf) {
        $base = $conf['base'];
        $alias = $conf['alias'];
        $hit = preg_match('/^\s*(INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+"?' . preg_quote($view, '/') . '"?\b/is', $sql);
        if (!$hit) continue;

        // 1. target -> tabel dasar
        $sql = preg_replace('/^(\s*(?:INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+)"?' . preg_quote($view, '/') . '"?\b/i',
            '$1"' . $base . '"', $sql);

        // 2. alias kolom di daftar kolom INSERT/REPLACE
        if ($alias) {
            if (preg_match('/^\s*INSERT\s+INTO\s+"?' . preg_quote($base, '/') . '"?\s*\(([^)]*)\)/is', $sql, $cm, PREG_OFFSET_CAPTURE)) {
                list($colsRaw, $colsOff) = $cm[1];
                $cols = array_map(function ($c) use ($alias) {
                    $c = trim($c);
                    $k = strtolower(trim($c, '"`'));
                    return array_key_exists($k, $alias) ? $alias[$k] : trim($c, '"`');
                }, explode(',', $colsRaw));
                $sql = substr_replace($sql, implode(', ', $cols), $colsOff, strlen($colsRaw));
            }
            // 3. alias di sisi kiri `kolom =` dan `= kolom` pada SET/WHERE
            foreach ($alias as $a => $b) {
                $sql = preg_replace('/\b' . preg_quote($a, '/') . '\b(?=\s*=)/i', $b, $sql);
                $sql = preg_replace('/(?<==)\s*' . preg_quote($a, '/') . '\b/i', $b, $sql);
            }
        }
        // 4. pemeriksaan lain (ORDER BY/LIKE) biarkan apa adanya
    }
    return $sql;
}

function dbx_translate(string $sql, ?PDO $pdo = null): string {
    $sql = trim($sql);
    if ($sql === '') return '';

    // ---- 0. ON DUPLICATE KEY UPDATE (statement MySQL, selalu di akhir) ----
    $upd = null;
    if (preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
        $start = $m[0][1];
        $upd = trim(substr($sql, $start + strlen($m[0][0])));
        $sql = rtrim(substr($sql, 0, $start));
    }

    // ---- 1. SHOW TABLES / SHOW CREATE TABLE ----
    if (preg_match('/^\s*SHOW\s+TABLES\s*;?\s*$/is', $sql)) {
        return "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY 1";
    }
    if (preg_match('/^\s*SHOW\s+CREATE\s+TABLE\s+(`?[\w]+`?)\s*;?\s*$/is', $sql, $mm)) {
        $t = trim($mm[1], '`');
        return dbx_pg_describe_table($t) ?? "SELECT null::text AS \"Create Table\" LIMIT 0";
    }

    // ---- 2. CREATE TABLE / CREATE TABLE IF NOT EXISTS (pengaturan.php) ----
    if (preg_match('/^\s*CREATE\s+TABLE\b/i', $sql)) {
        $sql = str_replace('`', '"', $sql);
        $sql = preg_replace('/\bAUTO_INCREMENT\s*=\s*\d+\s*/i', '', $sql);
        // kolom INT ... PRIMARY KEY AUTO_INCREMENT -> identity
        $sql = preg_replace(
            '/\bINT(?:EGER)?(?:\s*\(\d+\))?\s*(NOT NULL\s+)?PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i',
            'INTEGER ${1}GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY', $sql);
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'GENERATED BY DEFAULT AS IDENTITY', $sql);
        $sql = preg_replace('/\bENUM\s*\(([^)]*)\)/i', 'VARCHAR(64)', $sql);
        $sql = preg_replace('/\)$/', ');', rtrim(trim($sql), ';'));
        return $sql;
    }

    // ---- 3. Backtick -> double quote ----
    $sql = str_replace('`', '"', $sql);

    // ---- 3b. Menulis lewat nama view aplikasi -> tabel dasar ----
    $sql = dbx_rewrite_views($sql);

    // ---- 4. SET time_zone MySQL -> SET timezone PG ----
    $sql = preg_replace("/SET\s+time_zone\s*=\s*'\+07:00'/i", "SET timezone = 'Asia/Jakarta'", $sql);

    // ---- 5. DATE_FORMAT(x, 'fmt') -> TO_CHAR(x, 'pgfmt') ----
    $sql = dbx_scan_replace($sql, 'DATE_FORMAT', function ($inner) {
        $firstComma = strpos($inner, ',');
        $field = ($firstComma !== false) ? trim(substr($inner, 0, $firstComma)) : $inner;
        $fmt = preg_match("/'((?:\\\\.|[^'\\\\])*)'\s*\$/", $inner, $fm) ? $fm[1] : 'YYYY-MM-DD';
        return "TO_CHAR($field, '" . dbx_datefmt_to_pg($fmt) . "')";
    });

    // ---- 6. GROUP_CONCAT(...) -> string_agg(...) ----
    $sql = dbx_scan_replace($sql, 'GROUP_CONCAT', function ($inner) {
        $distinct = (bool)preg_match('/^\s*DISTINCT\s+/is', $inner);
        $body = trim(preg_replace('/^\s*DISTINCT\s+/is', '', $inner));
        $sep = ',';
        if (preg_match("/\bSEPARATOR\s+'((?:\\\\.|[^'\\\\])*)'/is", $body, $sm)) {
            $sep = stripcslashes($sm[1]);
            $body = trim(preg_replace("/\bSEPARATOR\s+'((?:\\\\.|[^'\\\\])*)'/is", '', $body));
        }
        // potong bagian "ORDER BY ..." dari argumen (punya SEPARATOR/atau di akhir)
        if (preg_match('/\bORDER\s+BY\s+([\s\S]+)$/i', $body, $om)) {
            $order = ' ORDER BY ' . trim($om[1]);
            $body = trim(substr($body, 0, strlen($body) - strlen($om[0])));
        } else $order = '';
        return 'string_agg(' . ($distinct ? 'DISTINCT ' : '') . rtrim($body) . ', ' . dbx_pgq($sep) . $order . ')';
    });

    // ---- 7. DATE_ADD / DATE_SUB(..., INTERVAL n UNIT) ----
    $sql = dbx_scan_replace($sql, 'DATE_ADD', function ($inner) {
        if (!preg_match('/^(.*?)\s*,\s*INTERVAL\s+([\S]+)\s+(\w+)\s*$/is', $inner, $m))
            return "($inner)"; // tidak dipahami — biarkan SQL murni
        return "({$m[1]} + INTERVAL '{$m[2]} " . strtolower($m[3]) . "')";
    });
    $sql = dbx_scan_replace($sql, 'DATE_SUB', function ($inner) {
        if (!preg_match('/^(.*?)\s*,\s*INTERVAL\s+([\S]+)\s+(\w+)\s*$/is', $inner, $m))
            return "($inner)";
        return "({$m[1]} - INTERVAL '{$m[2]} " . strtolower($m[3]) . "')";
    });

    // ---- 8. INTERVAL n UNIT umum (mis. NOW() - INTERVAL 30 MINUTE) ----
    $sql = preg_replace('/\bINTERVAL\s+(\d+)\s+(DAY|WEEK|MONTH|YEAR|HOUR|MINUTE|SECOND)\b/i',
        "INTERVAL '$1 $2'", $sql);

    // ---- 8b. TIMESTAMPDIFF(SECOND, a, b) -> EXTRACT(EPOCH FROM (b - a))::int ----
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
            case 'MONTH':  $div = 2629800; break; // perkiraan 30.44 hari
            case 'YEAR':   $div = 31557600; break;
            default:       $div = 1;
        }
        if ($div === 1) return "(EXTRACT(EPOCH FROM ($b) - ($a))::int)";
        return "((EXTRACT(EPOCH FROM ($b) - ($a)) / $div)::int)";
    });

    // ---- 8c. CAST(x AS UNSIGNED) -> CAST(x AS INTEGER) ----
    $sql = preg_replace('/\bAS\s+UNSIGNED\b/i', 'AS INTEGER', $sql);

    // ---- 8d. FIELD(x, v1, v2, ...) -> CASE — urutan kategori MySQL ----
    $sql = dbx_scan_replace($sql, 'FIELD', function ($inner) {
        $args = dbx_split_args($inner);
        if (count($args) < 2) return "FIELD($inner)";
        $expr = trim($args[0]);
        $out = "CASE WHEN $expr IS NULL THEN NULL";
        $i = 1;
        foreach (array_slice($args, 1) as $v) {
            $out .= " WHEN $expr = " . trim($v) . " THEN $i";
            $i++;
        }
        return $out . " ELSE 0 END";
    });

    // ---- 9. Fungsi tanggal sederhana ----
    $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $sql);
    $sql = preg_replace('/\bCURTIME\s*\(\s*\)/i', 'CURRENT_TIME', $sql);
    $sql = preg_replace('/\bUNIX_TIMESTAMP\s*\(\s*\)/i', 'extract(epoch from now())::bigint', $sql);

    // ---- 10. SUM(col = 'val') -> SUM(CASE WHEN col = 'val' THEN 1 ELSE 0 END) ----
    for ($k = 0; $k < 5; $k++) {
        $new = preg_replace("/\bSUM\(\s*([A-Za-z_][\w.]*)\s*=\s*('(?:\\\\.|[^'\\\\])*')\s*\)/i",
            'SUM(CASE WHEN $1 = $2 THEN 1 ELSE 0 END)', $sql);
        if ($new === $sql || $new === null) break;
        $sql = $new;
    }

    // ---- 10b. SUM(boolean / SUM(expr IN (...)) -> SUM(CASE WHEN ... THEN 1 ELSE 0 END) ----
    $sql = dbx_scan_replace($sql, 'SUM', function ($inner) {
        $t = trim($inner);
        if (preg_match('/^CASE\b/i', $t)) return "SUM($inner)";        // sudah CASE
        if (preg_match('/^DISTINCT\b/i', $t)) return "SUM($inner)";    // SUM(DISTINCT x)
        if (preg_match('/^(.+?)\bIN\s*\(([^()]*)\)\s*$/is', $t, $m)) {
            return "SUM(CASE WHEN {$m[1]} IN ({$m[2]}) THEN 1 ELSE 0 END)";
        }
        // ekspresi perbandingan/OR/IS NULL -> boolean
        if (preg_match('/\bOR\b|\bAND\b|IS\s+NOT\s+NULL\b|IS\s+NULL\b|<>|!=|<|>|=/i', $t)) {
            return "SUM(CASE WHEN $t THEN 1 ELSE 0 END)";
        }
        return "SUM($inner)";
    });

    // ---- 10c. Literal tanggal nol MySQL -> semantik NULL PostgreSQL ----
    // X NOT IN ('', '0000-00-00')  -> X IS NOT NULL
    $sql = preg_replace("/\b([\w.]+)\s+NOT\s+IN\s*\(\s*''\s*,\s*'0000-00-00'\s*\)/i", '$1 IS NOT NULL', $sql);
    // X != '0000-00-00' / X <> '0000-00-00' -> TRUE (tanggal nol tidak mungkin ada di PG)
    $sql = preg_replace("/\b([\w.]+)\s*(?:!=|<>)\s*'0000-00-00'/i", 'TRUE', $sql);
    // kolom tanggal dibandingkan dengan '' -> X IS NOT NULL
    $sql = preg_replace("/\b((?:[A-Za-z_]+\.)?(?:tgl_\w+|tanggal_\w+|deadline_\w*|\w*_akad\b|\w*wawancara\b))\s*(?:!=|<>)\s*''/i",
        '$1 IS NOT NULL', $sql);

    // ---- 11. IFNULL / RAND / LAST_INSERT_ID ----
    $sql = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $sql);
    $sql = preg_replace('/\bRAND\s*\(\s*\)/i', 'RANDOM()', $sql);
    $sql = preg_replace('/\bLAST_INSERT_ID\s*\(\s*\)/i', 'lastval()', $sql);

    // ---- 12. DATE(expr) -> (expr)::date ; MONTH/YEAR/HOUR ----
    //       DATE_FORMAT, DATE_ADD, DATE_SUB sudah dipindah di atas (balans scan).
    $sql = dbx_scan_replace($sql, 'DATE', function ($inner) { return "($inner)::date"; });
    foreach (['MONTH' => 'MONTH', 'YEAR' => 'YEAR', 'HOUR' => 'HOUR'] as $fn => $part) {
        $sql = dbx_scan_replace($sql, $fn, function ($inner) use ($part) {
            return "(EXTRACT($part FROM $inner)::int)";
        });
    }

    // ---- 13. ON DUPLICATE KEY UPDATE -> ON CONFLICT ... DO UPDATE SET ----
    if ($upd !== null) {
        $sql = dbx_apply_upsert($sql, $upd, $pdo);
    }
    return $sql;
}

/* Resolusi ON DUPLICATE KEY UPDATE -> ON CONFLICT */
function dbx_apply_upsert(string $sql, string $upd, ?PDO $pdo): string {
    if ($pdo === null) return $sql; // tanpa PDO (unexpected): biarkan apa adanya
    if (!preg_match('/(?i)INSERT\s+INTO\s+"?([\w]+)"?/', $sql, $tm)) return $sql;
    $table = $tm[1];

    // kolom yang dimasukkan
    $insCols = [];
    if (preg_match('/^\s*INSERT\s+INTO\s+"?[\w]+"?\s*\(([^)]*)\)/is', $sql, $cm)) {
        $insCols = array_map('trim', explode(',', $cm[1]));
        $insCols = array_map(function ($c) { return trim($c, '"` '); }, $insCols);
    }
    $insLower = array_map('strtolower', $insCols);

    // pilih unique index yang seluruh kolomnya ada di daftar INSERT
    $target = null;
    foreach (dbx_unique_indexes($pdo, $table) as $meta) {
        if (empty($meta['cols'])) continue;
        $need = array_map('strtolower', $meta['cols']);
        if (empty(array_diff($need, $insLower))) { $target = $meta['cols']; break; }
    }
    // Tanpa unique-index yang cocok -> perilaku MySQL = INSERT biasa
    if ($target === null) return $sql;

    // terjemahan assignment: VALUES(col) -> EXCLUDED.col, lainnya apa adanya
    $assigns = [];
    foreach (preg_split('/,(?![^\'(]*[\')])/', $upd) as $as) {
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
    $cols = implode(', ', array_map(fn($c) => '"' . $c . '"', $target));
    return $sql . ' ON CONFLICT (' . $cols . ') DO UPDATE SET ' . implode(', ', $assigns);
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
 *  CLASS sdb (compat) — connect adalah Supabase, bukan MySQL lama
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
    private bool $inTx     = false;

    public function __construct($host = null, $user = null, $pass = null, $db = null,
                                $port = null, $socket = null) {
        // Semua parameter lama MySQL diabaikan; koneksi tetap ke Supabase.
        // Koneksi dibuat LAZY pada query/prepare pertama (menghemat ±1,3s
        // pada halaman yang tidak menyentuh DB, mis. GET login.php di Vercel).
        $this->ensurePDO();
    }

    private ?PDO $pdo = null;
    private bool $pdoTried = false;

    private function ensurePDO(): ?PDO {
        if ($this->pdoTried) return $this->pdo;
        $this->pdoTried = true;
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
            $this->connect_errno = 2002;
            $this->error         = $e->getMessage();
            $this->errno         = 2002;
            error_log('db_adapter: koneksi Supabase gagal: ' . $e->getMessage());
        }
        return $this->pdo;
    }

    public function getPDO(): ?PDO { return $this->ensurePDO(); }
    public function inTransaction(): bool { return $this->inTx; }

    private function markError(Throwable $e): void {
        $this->error = $e->getMessage();
        $this->errno = 1000 + ((int)$e->getCode() & 0xFFFF);
        error_log('db_adapter: ' . $e->getMessage());
    }

    public function query($sql, $resultmode = 0) {
        $pdo = $this->ensurePDO();
        if ($pdo === null) return false;
        $raw = (string)$sql;
        try {
            $t = dbx_translate($raw, $pdo);
            $isSelect = preg_match('/^(SELECT|WITH|SHOW)\b/im', $t) || preg_match('/\bRETURNING\b/i', $t);
            if ($isSelect) {
                $stmt = $pdo->prepare($t);
                // '?'-placeholder tak boleh tanpa bind; MySQL $koneksi->query sangat jarang pakai ?
                if (preg_match_all('/\?/', $t) > 0) {
                    $this->error = 'Placeholder ? pada ->query() atau ->prepare() tidak didukung tanpa bind param.';
                    return false;
                }
                $stmt->execute();
                return new sdb_result($stmt);
            }
            // UPDATE / INSERT / DELETE / DDL
            $st = $pdo->exec($t);
            $this->affected_rows = $st;
            if (preg_match('/^\s*INSERT\b/i', $t)) {
                try { $this->insert_id = (int)$pdo->query('SELECT lastval()')->fetchColumn(); }
                catch (Throwable $e) { /* INSERT tanpa sequence: dia biarkan */ }
            }
            return true;
        } catch (Throwable $e) {
            $this->markError($e);
            return false;
        }
    }

    public function prepare($sql) {
        $pdo = $this->ensurePDO();
        if ($pdo === null) return false;
        try {
            $t = dbx_translate((string)$sql, $pdo);
            return new sdb_stmt($this, $t);
        } catch (Throwable $e) {
            $this->markError($e);
            return false;
        }
    }

    // Escape gaya PostgreSQL: string literal didirikan standar MySQL dgn ' -> ''.
    // Backslash dan NUL dibersihkan dulu untuk aman (MySQL-escape tidak dipakai).
    public function real_escape_string($s) {
        $s = (string)$s;
        $s = str_replace(["\\", "\0"], ["\\\\", '\0'], $s);
        return str_replace("'", "''", $s);
    }
    public function escape_string($s) { return $this->real_escape_string($s); }

    public function set_charset($cs) { return true; }
    public function character_set_name() { return 'utf8mb4'; }

    public function begin_transaction($flags = 0, $name = null) {
        $pdo = $this->ensurePDO();
        if ($pdo === null) return false;
        if ($this->inTx) return true;
        try { $pdo->beginTransaction(); $this->inTx = true; return true; }
        catch (Throwable $e) { $this->markError($e); return false; }
    }
    public function commit($flags = 0, $name = null) {
        if (!$this->inTx) return true;
        try { $this->ensurePDO()?->commit(); $this->inTx = false; return true; }
        catch (Throwable $e) { $this->markError($e); return false; }
    }
    public function rollback($flags = 0, $name = null) {
        if (!$this->inTx) return true;
        try { $this->ensurePDO()?->rollBack(); $this->inTx = false; return true; }
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
    public function ping() { if (!$this->pdoTried) $this->ensurePDO(); return $this->pdo !== null; }
    public function ssl_set() { return true; }
    public function stmt_init() { return new sdb_stmt($this, ''); }
    public function close() { $this->pdo = null; $this->pdoTried = true; return true; }
    public function __destruct() { $this->pdo = null; }
}

/* =========================================================================
 *  CLASS sdb_result (compat)
 * ========================================================================= */
class sdb_result implements IteratorAggregate {
    public int $num_rows      = 0;
    public int $field_count   = 0;
    public int $current_field = 0;
    /** @var array<int,object> metadata kolom { name, type, table } */
    public array $fields      = [];
    /** @var array<int,array> semua baris (assoc) */
    private array $rows       = [];
    /** @var array<int,list>   semua baris (numerik) */
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

    public function fetch_assoc() {
        if ($this->cur >= $this->num_rows) return null;
        return $this->rows[$this->cur++];
    }
    public function fetch_row() {
        if ($this->cur >= $this->num_rows) return null;
        return $this->rowList[$this->cur++];
    }
    public function fetch_object($class = null, array $ctor_args = []) {
        $a = $this->fetch_assoc();
        if ($a === null) return null;
        return $class ? new $class(...array_values($a)) : (object)$a;
    }
    public function fetch_array($mode = MYSQLI_BOTH) {
        $a = $this->fetch_assoc();
        if ($a === null) return null;
        if ($mode === MYSQLI_ASSOC) return array_filter($a, 'is_string', ARRAY_FILTER_USE_KEY);
        if ($mode === MYSQLI_NUM)   return array_filter($a, 'is_int', ARRAY_FILTER_USE_KEY);
        $out = []; $i = 0;
        foreach ($a as $k => $v) { $out[$k] = $v; $out[$i++] = $v; }
        return $out;
    }
    public function fetch_all($mode = MYSQLI_ASSOC) {
        if ($mode === MYSQLI_ASSOC) return $this->rows;
        if ($mode === MYSQLI_NUM)   return $this->rowList;
        $out = [];
        foreach ($this->rows as $r) {
            $row = []; $i = 0;
            foreach ($r as $k => $v) { $row[$k] = $v; $row[$i++] = $v; }
            $out[] = $row;
        }
        return $out;
    }
    public function fetch_fields() { return $this->fields; }
    public function fetch_field() {
        return array_key_exists($this->current_field, $this->fields)
            ? $this->fields[$this->current_field++] : false;
    }
    public function fetch_field_direct($i) { return $this->fields[$i] ?? false; }
    public function field_seek($i) { $this->current_field = (int)$i; return true; }
    public function data_seek($i) { $this->cur = max(0, (int)$i); return true; }
    public function free() {}
    public function free_result() {}
    public function close() {}
    public function getIterator(): Iterator { return new ArrayIterator($this->rows); }
}

/* =========================================================================
 *  CLASS sdb_stmt (compat)
 * ========================================================================= */
class sdb_stmt {
    public string $error         = '';
    public int    $errno         = 0;
    public int    $affected_rows = 0;
    public int    $insert_id     = 0;
    public int    $num_rows      = 0;
    public int    $param_count   = 0;
    public int    $field_count   = 0;
    private sdb $owner;
    private string $sql;
    /** @var array<int,mixed> nilai param (menyimpan referensi) */
    private array $params        = [];
    private array $types         = [];
    private ?sdb_result $res  = null;
    private bool $executed       = false;

    public function __construct(sdb $owner, string $sql) {
        $this->owner = $owner;
        $this->sql = $sql;
        $this->param_count = preg_match_all('/\?/', $sql);
    }

    private function markErr(Throwable $e): void {
        $this->error = $e->getMessage();
        $this->errno = 2001 + ((int)$e->getCode() & 0xFF);
        error_log('db_adapter stmt: ' . $e->getMessage());
    }

    public function bind_param(string $types, &...$vars): bool {
        $this->types  = str_split($types);
        $this->params = $vars;
        return true;
    }
    public function bind_result(&...$vars): bool { return true; }

    public function execute(): bool {
        $pdo = $this->owner->getPDO();
        if ($pdo === null) return false;
        $this->res = null; $this->executed = false;
        $sql = $this->sql;
        try {
            $st = $pdo->prepare($sql);
            $nph = preg_match_all('/\?/', $sql);
            $n = min($nph, count($this->params));
            for ($i = 0; $i < $n; $i++) {
                $v = $this->params[$i];
                $ty = ($this->types[$i] ?? 's');
                $st->bindValue($i + 1, $v,
                    $v === null ? PDO::PARAM_NULL
                    : ($ty === 'i' && is_int($v) ? PDO::PARAM_INT
                       : ($ty === 'd' && is_float($v) ? PDO::PARAM_STR : PDO::PARAM_STR)));
            }
            // Parameter ganda (banyak param > placeholder, mis. upsert MySQL):
            // yang tak terpakai diabaikan — sesuai perilaku ON DUPLICATE KEY UPDATE.
            $st->execute();
            $this->executed = true;
            if (preg_match('/^(SELECT|WITH|SHOW)\b/i', ltrim($sql)) || preg_match('/\bRETURNING\b/i', $sql)) {
                $this->res = new sdb_result($st);
                $this->num_rows = $this->res->num_rows;
                $this->affected_rows = $this->res->num_rows;
            } else {
                $this->affected_rows = $st->rowCount();
                if (preg_match('/^\s*INSERT\b/i', $sql)) {
                    try {
                        $v = $pdo->query('SELECT lastval()')->fetchColumn();
                        $this->insert_id = (int)$v;
                    } catch (Throwable $e) { /* INSERT tanpa nextval: lastval tak tersedia */ }
                }
                $this->owner->affected_rows = $this->affected_rows;
                $this->owner->insert_id = $this->insert_id;
            }
            return true;
        } catch (Throwable $e) {
            $this->markErr($e);
            $this->owner->error = $e->getMessage();
            return false;
        }
    }

    public function get_result() {
        if (!$this->executed) return false;
        if ($this->res === null) return new sdb_result([], ['']);
        return $this->res;
    }
    public function store_result(): bool {
        if ($this->res) { $this->num_rows = $this->res->num_rows; return true; }
        return false;
    }
    public function fetch() { return $this->res ? $this->res->fetch_assoc() : null; }
    public function fetch_assoc() { return $this->fetch(); }
    public function free_result() {}
    public function data_seek($i) { return true; }
    public function close(): bool { $this->params = []; $this->res = null; return true; }
}

/* =========================================================================
 *  FUNGSI PROSEDURAL sdb_* (compat)
 * ========================================================================= */
function sdb_connect($host = null, $user = null, $pass = null, $db = null, $port = null) {
    return new sdb($host, $user, $pass, $db, $port);
}
function sdb_connect_error() { return ''; }
function sdb_select_db(sdb $c, string $db): bool { return $c->select_db($db); }
function sdb_query(sdb $c, string $sql, int $mode = 0) { return $c->query($sql, $mode); }
function sdb_real_escape_string(sdb $c, string $s) { return $c->real_escape_string($s); }
function sdb_escape_string(sdb $c, string $s) { return $c->real_escape_string($s); }
function sdb_set_charset(sdb $c, string $cs): bool { return $c->set_charset($cs); }
function sdb_error(sdb $c): string { return $c->error; }
function sdb_errno(sdb $c): int { return $c->errno; }
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
function sdb_insert_id(sdb $c): int { return $c->insert_id; }
function sdb_affected_rows(sdb $c): int { return $c->affected_rows; }
function sdb_prepare(sdb $c, string $sql) { return $c->prepare($sql); }
function sdb_stmt_bind_param(sdb_stmt $st, string $types, &...$args): bool { return $st->bind_param($types, ...$args); }
function sdb_stmt_execute(sdb_stmt $st): bool { return $st->execute(); }
function sdb_stmt_get_result(sdb_stmt $st) { return $st->get_result(); }
function sdb_stmt_fetch(sdb_stmt $st) { return $st->fetch(); }
function sdb_stmt_close(sdb_stmt $st): bool { return $st->close(); }
function sdb_stmt_free_result(sdb_stmt $st): void { $st->free_result(); }
function sdb_stmt_num_rows(sdb_stmt $st): int { return $st->num_rows; }
function sdb_stmt_errno(sdb_stmt $st): int { return $st->errno; }
function sdb_stmt_error(sdb_stmt $st): string { return $st->error; }
function sdb_stmt_insert_id(sdb_stmt $st): int { return $st->insert_id; }
function sdb_stmt_affected_rows(sdb_stmt $st): int { return $st->affected_rows; }
function sdb_begin_transaction(sdb $c): bool { return $c->begin_transaction(); }
function sdb_commit(sdb $c): bool { return $c->commit(); }
function sdb_rollback(sdb $c): bool { return $c->rollback(); }
function sdb_autocommit(sdb $c, bool $m): bool { return $c->autocommit($m); }
function sdb_close(sdb $c): bool { return $c->close(); }
function sdb_ping(sdb $c): bool { return $c->ping(); }
