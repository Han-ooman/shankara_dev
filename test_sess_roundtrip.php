<?php
require 'includes/koneksi.php';
session_id('my_test_id');
session_start();
$_SESSION['test_val'] = 'persisted_value';
session_write_close();

// Now read it back with the same session_id
session_id('my_test_id');
session_start();
echo "Read back: " . ($_SESSION['test_val'] ?? 'NOT FOUND') . "\n";
?>