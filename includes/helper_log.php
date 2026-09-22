<?php

// Helper pencatatan aktivitas (data log).
// Dipanggil dari login/logout/cek_sesi dan halaman CRUD.
function log_aktivitas($koneksi, $aksi, $modul, $deskripsi, $detail = null, $user_id = null) {
    if (!$user_id && isset($_SESSION['user_id'])) {
        $user_id = intval($_SESSION['user_id']);
    }

    $uid = $user_id ? intval($user_id) : 'NULL';
    $aksi_e    = mysqli_real_escape_string($koneksi, (string) $aksi);
    $modul_e   = mysqli_real_escape_string($koneksi, (string) $modul);
    $desk_e    = mysqli_real_escape_string($koneksi, mb_substr((string) $deskripsi, 0, 255));
    $detail_e  = ($detail !== null && $detail !== '')
        ? "'" . mysqli_real_escape_string($koneksi, is_string($detail) ? mb_substr($detail, 0, 65535) : json_encode($detail, JSON_UNESCAPED_UNICODE)) . "'"
        : 'NULL';
    $ip        = mysqli_real_escape_string($koneksi, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $ua        = mysqli_real_escape_string($koneksi, isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '');

    mysqli_query($koneksi, "INSERT INTO log_aktivitas (user_id, aksi, modul, deskripsi, detail, ip_address, user_agent, created_at)
                            VALUES ($uid, '$aksi_e', '$modul_e', '$desk_e', $detail_e, '$ip', '$ua', NOW())");
}