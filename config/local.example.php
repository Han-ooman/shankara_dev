<?php
// Contoh konfigurasi lokal. Salin file ini menjadi config/local.php
// lalu isi dengan kredensial database yang sebenarnya.
// config/local.php TIDAK ikut di-commit (lihat .gitignore).

define('SUPABASE_DSN', 'pgsql:host=HOST;port=5432;dbname=postgres');
define('SUPABASE_USER', 'USER');
define('SUPABASE_PASSWORD', 'PASSWORD');
