<?php
require 'includes/koneksi.php';
$res2 = $koneksi->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_user_sesi_user_id ON user_sesi(user_id, session_id)");
echo "user_sesi index result: " . ($res2 ? "OK" : "FAIL: " . $koneksi->error) . "\n";
?>