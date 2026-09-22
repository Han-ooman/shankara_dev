<?php

// Cek & refresh sesi login aktif (batas perangkat per akun).
// Dipanggil di setiap halaman setelah session_start + cek login.

// ==== AUTO LOGOUT: sesi idle lebih dari 30 menit langsung diakhiri ====
$TIMEOUT_MENIT = 30;

if (isset($_SESSION['status_login']) && $_SESSION['status_login'] === true && !empty($_SESSION['user_id'])) {
    include_once "koneksi.php";
    require_once __DIR__ . '/helper_log.php';
    $uid = intval($_SESSION['user_id']);
    $sid = mysqli_real_escape_string($koneksi, session_id());

    // Ambil waktu aktif terakhir dari tabel user_sesi
    // (selisih dihitung di SQL agar konsisten dgn timezone server DB)
    $q = mysqli_query($koneksi, "SELECT TIMESTAMPDIFF(SECOND, last_active, NOW()) AS diff_idle FROM user_sesi WHERE user_id = $uid AND session_id = '$sid'");
    $row = $q ? mysqli_fetch_assoc($q) : null;

    if ($row && isset($row['diff_idle']) && $row['diff_idle'] !== null) {
        // Jika sudah idle melebihi batas -> logout paksa
        if ((int)$row['diff_idle'] > ($TIMEOUT_MENIT * 60)) {
            log_aktivitas($koneksi, 'LOGOUT_TIMEOUT', 'Logout', 'Sesi berakhir timeout (tidak ada aktivitas): ' . (isset($_SESSION['username']) ? $_SESSION['username'] : ''));
            mysqli_query($koneksi, "DELETE FROM user_sesi WHERE user_id = $uid AND session_id = '$sid'");
            session_unset();
            session_destroy();
            header("Location: login.php?pesan=timeout");
            exit();
        }
    }

    // Perbarui waktu aktif terakhir (semua aktivitas halaman meng-reset timeout)
    mysqli_query($koneksi, "UPDATE user_sesi SET last_active = NOW() WHERE user_id = $uid AND session_id = '$sid'");
}
