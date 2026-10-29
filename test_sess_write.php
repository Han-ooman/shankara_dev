<?php
require 'includes/koneksi.php';
session_start();
$_SESSION['test_val'] = 'hello_turso';
echo "Session ID: " . session_id() . "\n";
echo "Session Val: " . $_SESSION['test_val'] . "\n";
?>