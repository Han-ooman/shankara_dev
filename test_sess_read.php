<?php
require 'includes/koneksi.php';
session_start();
echo "Session ID: " . session_id() . "\n";
echo "Session Val: " . ($_SESSION['test_val'] ?? 'NOT SET') . "\n";
?>