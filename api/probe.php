<?php
// api/probe.php — SEMENTARA (dihapus setelah verifikasi): info koneksi DB deployment
require_once __DIR__ . '/../includes/db_adapter.php';

header('Content-Type: text/plain');
echo "DB_MODE=" . DB_MODE . "\n";
$dsn = defined('SUPABASE_DSN') ? SUPABASE_DSN : '(tidak terdefinisi)';
echo "SUPABASE_DSN=" . $dsn . "\n";
echo "SUPABASE_USER=" . (defined('SUPABASE_USER') ? SUPABASE_USER : '(tidak ada)') . "\n";
echo "MYSQL_HOST=" . MYSQL_HOST . ":" . MYSQL_PORT . "/" . MYSQL_DATABASE . "\n";

if (defined('SUPABASE_DSN') && SUPABASE_DSN) {
    $t0 = microtime(true);
    $c = sdb_connect_pgsql();
    $ms = number_format((microtime(true) - $t0) * 1000);
    if ($c && !($c instanceof sdb ? $c->connect_errno : false)) {
        echo "connect_pg: OK ({$ms} ms) host=" . (method_exists($c, 'host_info') ? $c->host_info() : '?') . "\n";
        $r = $c->query("SELECT count(*) AS jml FROM user_sesi WHERE user_id = 1");
        $row = $r ? $r->fetch_assoc() : null;
        echo "user_sesi user1 = " . ($row ? $row['jml'] : '-1') . "\n";
    } else {
        echo "connect_pg: GAGAL ({$ms} ms)\n";
    }
} else {
    echo "connect_pg: skip (DSN tidak ada)\n";
}
