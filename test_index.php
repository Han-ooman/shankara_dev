<?php
require 'includes/koneksi.php';
$res = $koneksi->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='php_sessions'");
if ($res) {
    while($r = $res->fetch_assoc()) {
        print_r($r);
    }
} else {
    echo "Error: " . $koneksi->error . "\n";
}

// Try adding primary key
$res2 = $koneksi->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_php_sessions_id ON php_sessions(session_id)");
echo "Index result: " . ($res2 ? "OK" : "FAIL: " . $koneksi->error) . "\n";
?>