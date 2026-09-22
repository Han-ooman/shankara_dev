<?php
// security.php - Bantuan keamanan bersama (CSRF, hardening sesi, kontrol error)

// ===== HARDENING SESI (panggil SEBELUM session_start) =====
// Aman dipanggil berulang; hanya berlaku jika sesi belum berjalan & header belum terkirim.
function harden_session() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (strpos($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') !== false);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,   // cookie hanya lewat HTTPS
            'httponly' => true,     // tidak bisa diakses JavaScript (anti-XSS)
            'samesite' => 'Lax',    // mitigasi CSRF dasar
        ]);
        // CATATAN: TIDAK mengganti session_name() agar nama sesi konsisten
        // (PHPSESSID) di semua halaman. Mengubah nama sesi membuat halaman yang
        // memanggil session_start() SEBELUM include koneksi.php tidak mengenali
        // sesi login yang dibuat dengan nama berbeda, sehingga redirect ke login.
    }
}

// Panggil otomatis bila dimuat sebelum sesi dimulai (di koneksi.php etc.)
harden_session();

// ===== ERROR HANDLING: jangan bocorkan detail ke pengguna akhir =====
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

// ===== FUNGSI CSRF =====
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify($token = null) {
    if ($token === null) $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ===== BRUTE FORCE / RATE LIMIT LOGIN =====
// Gabungkan counter per IP + per username.
function login_fail_key($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return ($username !== '' ? 'lf_u_' . md5(strtolower(trim($username))) . '_' : 'lf_ip_') . md5($ip);
}

function login_fails($key) {
    return (int)($_SESSION[$key] ?? 0);
}

function record_login_fail($key) {
    $_SESSION[$key] = ((int)($_SESSION[$key] ?? 0)) + 1;
}

function reset_login_fails($key) {
    unset($_SESSION[$key]);
}

function login_locked($key, $max = 5) {
    return (($_SESSION[$key] ?? 0) >= $max);
}
