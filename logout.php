<?php
session_start();
include "includes/koneksi.php";
require_once __DIR__ . '/includes/helper_log.php';

// Catat aktivitas logout (manual) sebelum sesi dihancurkan
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    log_aktivitas($koneksi, 'LOGOUT', 'Logout', 'Logout: ' . $_SESSION['username']);
}

// Hapus sesi aktif dari tabel user_sesi
if (isset($_SESSION['user_id']) && session_id() !== '') {
    mysqli_query($koneksi, "DELETE FROM user_sesi WHERE user_id = " . intval($_SESSION['user_id']) . " AND session_id = '" . mysqli_real_escape_string($koneksi, session_id()) . "'");
}

// Hapus semua variabel sesi
$_SESSION = array();

// Hapus cookie sesi jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan sesi secara total
session_destroy();

// Arahkan kembali ke halaman login (bawa pesan timeout jika ada)
$pesan = isset($_GET['pesan']) && $_GET['pesan'] === 'timeout' ? '?pesan=timeout' : '';
header("Location: login.php" . $pesan);
exit();
?>