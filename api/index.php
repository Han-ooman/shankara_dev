<?php
// api/index.php — Front controller untuk Vercel + vercel-php.

// Sesi PHP via DB — didaftarkan SEBELUM file target jalan (beberapa halaman
// memanggil session_start() sebelum include koneksi, mis. print/*.php).
require_once dirname(__DIR__) . '/includes/session_db.php';

// Men-dispatch setiap request ke file PHP di root project.
// File PHP root tidak bisa dijadikan serverless function langsung, jadi
// semua path (di luar passthrough asset) diarahkan ke sini lewat vercel.json.

$root = dirname(__DIR__);

// DEBUG: Log the actual request
error_log("DEBUG: REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? 'NOT_SET') . ", SCRIPT_NAME=" . ($_SERVER['SCRIPT_NAME'] ?? 'NOT_SET') . ", PHP_SELF=" . ($_SERVER['PHP_SELF'] ?? 'NOT_SET'));

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = urldecode($path);
$path = str_replace('\\', '/', $path);

error_log("DEBUG: Parsed path=$path");

// Blokir traversal
if (str_contains($path, '..') || str_contains($path, "\0")) {
    http_response_code(400);
    exit('Bad request');
}

// Cek apakah ini permintaan file statis (assets, manifest.json, sw.js, format_bast)
$filePath = $root . $path;
if ($path !== '/' && is_file($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        header('Cache-Control: public, max-age=31536000');
        readfile($filePath);
        exit();
    }
}

// Halaman beranda (juga saat user memanggil front controller langsung)
if ($path === '/' || $path === '/index.php' || $path === '/api/index.php') {
    $target = $root . '/index.php';
    $path   = '/index.php';
} else {
    $target = $root . $path;
}

// Hanya file .php yang boleh dieksekusi
if (!is_file($target) || strtolower(substr($target, -4)) !== '.php') {
    http_response_code(404);
    exit('Halaman tidak ditemukan');
}

$real = realpath($target);
if ($real === false) {
    http_response_code(404);
    exit('Halaman tidak ditemukan');
}
// Jangan izinkan eksekusi file internal include/library secara langsung
if (strpos(str_replace('\\', '/', $real), $root . '/includes/') === 0) {
    http_response_code(404);
    exit('Halaman tidak ditemukan');
}

// cwd = root project agar include 'includes/xxx.php' tetap bekerja
chdir($root);

// Supaya script yang membaca SCRIPT_NAME tetap melihat nama halaman aslinya
$_SERVER['SCRIPT_NAME'] = $path;
$_SERVER['PHP_SELF'] = $path;
$_SERVER['SCRIPT_FILENAME'] = $real;

require $real;