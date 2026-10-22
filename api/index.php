<?php
// api/index.php — Front controller untuk Vercel + vercel-php.
// Men-dispatch setiap request ke file PHP di root project.
// File PHP root tidak bisa dijadikan serverless function langsung, jadi
// semua path diarahkan ke sini lewat vercel.json (routes).

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

// Halaman beranda
if ($path === '/' || $path === '/index.php') {
    $target = $root . '/index.php';
} else {
    $target = $root . $path;
}

// Hanya file .php yang boleh dieksekusi
if (!is_file($target) || strtolower(substr($target, -4)) !== '.php') {
    http_response_code(404);
    exit('Halaman tidak ditemukan');
}

$real = realpath($target);
if ($real === false || strpos($real, $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR) === 0) {
    http_response_code(404);
    exit('Halaman tidak ditemukan');
}

// cwd = root project agar include 'includes/xxx.php' tetap bekerja
chdir($root);

// Supaya script yang membaca SCRIPT_NAME tetap melihat nama halaman aslinya,
// contoh: beranda → /index.php, konsumen → /konsumen.php
$_SERVER['SCRIPT_NAME'] = $path;
if ($path === '/') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = $real;

require $real;
