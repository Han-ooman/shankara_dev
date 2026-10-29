<?php
require 'includes/koneksi.php';
$res = $koneksi->query("PRAGMA table_info(php_sessions)");
if ($res) {
    while($r = $res->fetch_assoc()) {
        print_r($r);
    }
} else {
    echo "Error: " . $koneksi->error . "\n";
}
?>