<?php
// Contoh konfigurasi lokal. Salin file ini menjadi config/local.php
// lalu isi dengan kredensial database yang sebenarnya.
// config/local.php TIDAK ikut di-commit (lihat .gitignore).
//
// PENTING: ENV var selalu MENANG atas nilai di file ini.
// Di Vercel, cukup set env var SUPABASE_PASSWORD (dan opsional SUPABASE_USER).
//
// Ada DUA mode koneksi:
//   - ONLINE  : Supabase (PostgreSQL)  -> dipakai di Vercel
//   - OFFLINE : MySQL lokal (XAMPP)

// ---- ONLINE : Supabase ----
// Kosongkan DSN = otomatis memakai Supabase Pooler (port 6543).
// Jika diisi, boleh berupa URI "postgresql://..." dari dashboard Supabase;
// akan dinormalisasi otomatis oleh includes/db_adapter.php.
$CONFIG_SUPABASE_DSN      = '';

// Username pooler wajib berformat "postgres.<project-ref>".
$CONFIG_SUPABASE_USER     = 'postgres.iwxbdnzglypdjimeuzht';
$CONFIG_SUPABASE_PASSWORD = 'ISI_PASSWORD_DATABASE_ANDA';

// ---- Mode koneksi aktif ----
// 'offline' : MySQL lokal (XAMPP)
// 'online'  : Supabase (deployment Vercel — atau set env DB_MODE=online)
$__db_mode = strtolower(trim((string)(getenv('DB_MODE') ?: 'offline')));
define('DB_MODE', in_array($__db_mode, ['online', 'offline'], true) ? $__db_mode : 'offline');
unset($__db_mode);
