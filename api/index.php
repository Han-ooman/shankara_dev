<?php
// api/index.php — Front controller untuk Vercel + vercel-php.
// Men-dispatch setiap request ke file PHP di root project.
// File PHP root tidak bisa dijadikan serverless function langsung, jadi
// semua path (di luar passthrough asset) diarahkan ke sini lewat vercel.json.

declare(strict_types=1);

$root = dirname(__DIR__);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = urldecode($path);
$path = str_replace('\\', '/', $path);

// Blokir traversal
if (str_contains($path, '..') || str_contains($path, "\0")) {
    http_response_code(400);
    exit('Bad request');
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
