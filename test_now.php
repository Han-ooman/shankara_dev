<?php
require 'C:/xampp/htdocs/shandev/includes/koneksi.php';
echo "Koneksi berhasil!\n";

// Test NOW() translation
$res = $koneksi->query("SELECT NOW() as now_val, CURRENT_TIMESTAMP as ct");
if ($res) {
    $row = $res->fetch_assoc();
    echo "NOW() test: " . json_encode($row) . "\n";
} else {
    echo "ERROR: " . $koneksi->error;
}

// Test INSERT with NOW()
$res2 = $koneksi->query("INSERT INTO log_aktivitas (user_id, aksi, modul, deskripsi, ip_address) VALUES (1, 'test', 'test', 'test NOW', '127.0.0.1')");
if ($res2 === false) {
    echo "INSERT ERROR: " . $koneksi->error . "\n";
} else {
    echo "INSERT OK, ID: " . $koneksi->insert_id . "\n";
}

// Test SELECT data
$res3 = $koneksi->query("SELECT * FROM log_aktivitas WHERE id_log = " . $koneksi->insert_id);
if ($res3) {
    $row = $res3->fetch_assoc();
    echo "SELECT OK: " . json_encode($row) . "\n";
}