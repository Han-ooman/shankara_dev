<?php
require 'includes/koneksi.php';
$res = $koneksi->query('SELECT COUNT(*) as cnt FROM php_sessions');
if ($res) {
    $row = $res->fetch_assoc();
    echo "php_sessions count: " . $row['cnt'] . "\n";
} else {
    echo "Error querying php_sessions: " . $koneksi->error . "\n";
}
?>