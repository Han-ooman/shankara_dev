<?php
// Contoh konfigurasi lokal. Salin file ini menjadi config/local.php
// lalu isi dengan kredensial database yang sebenarnya.
// config/local.php TIDAK ikut di-commit (lihat .gitignore).
//
// Ada DUA koneksi database:
//   - ONLINE  : Supabase (PostgreSQL)
//   - OFFLINE : MySQL lokal (XAMPP)

// ---- ONLINE : Supabase ----
define('SUPABASE_DSN', 'pgsql:host=HOST;port=5432;dbname=postgres');
define('SUPABASE_USER', 'USER');
define('SUPABASE_PASSWORD', 'PASSWORD');

// ---- OFFLINE : MySQL lokal (XAMPP) ----
define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_PORT', 3306);
define('MYSQL_DATABASE', 'db_perumahan');
define('MYSQL_USER', 'root');
define('MYSQL_PASSWORD', '');
define('MYSQL_CHARSET', 'utf8mb4');

// ---- Mode koneksi aktif ----
// 'offline' : MySQL lokal (default, development di XAMPP)
// 'online'  : Supabase (deployment Vercel — atau set env DB_MODE=online)
$__db_mode = strtolower(trim((string)(getenv('DB_MODE') ?: 'offline')));
define('DB_MODE', in_array($__db_mode, ['online', 'offline'], true) ? $__db_mode : 'offline');
unset($__db_mode);
