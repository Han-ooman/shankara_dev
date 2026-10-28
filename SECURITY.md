# Kebijakan Keamanan (Security Policy)

## Kontak / Cara Melaporkan

**JANGAN laporkan celah keamanan melalui Issue publik.**

Gunakan fitur **Private Vulnerability Reporting** GitHub:
buka tab **Security** di repositori ini → **Report a vulnerability**.
Laporan akan sampai langsung ke pemilik repositori secara privat.

Alternatif: hubungi pemilik repositori sesuai kontak di profil GitHub.

## Lingkup yang Dilaporkan (In-Scope)

- Aplikasi web "shankara_dev" (PHP + PostgreSQL/Supabase, deploy Vercel)
  — bug keamanan seperti: injeksi SQL, XSS, CSRF, bypass login/otorisasi
  role, kebocoran sesi, exposure file sensitif.
- Skrip deployment (`vercel.json`, `api/index.php` front controller).
- Kebocoran kredensial/rahasia di repositori (mis. token, password database).

## Di Luar Lingkup (Out-of-Scope)

- Serangan ke fisik / rekayasa sosial ke karyawan.
- Spam, DoS tanpa bukti dampak keamanan.
- Versi aplikasi yang sudah tidak dipakai produksi.
- Auto-logout idle 30 menit yang memang dirancang (by design),
  bukan celah keamanan.

## Kredensial & Rahasia

- Kredensial database **tidak** disimpan di repositori
  (lihat `config/local.example.php` untuk contoh struktur).
- Kredensial produksi disimpan di environment: `config/local.php`
  (di-git-ignore) di mesin lokal dan **Environment Variables Vercel**.
- Jika kamu menemukan tampilnya kredensial di mana pun di repositori
  ini, lapor SEGERA melalui jalur privat di atas — akan segera
  diputar (rotate) di layanan terkait.

## Tanggapan & Waktu

| Jenis | Estimasi tanggapan |
| --- | --- |
| Kritikal (bypass login, kebocoran data) | ≤ 24 jam |
| Tinggi (SQLi/XSS/CSRF) | ≤ 3 hari |
| Sedang/Rendah | ≤ 1 minggu |

Laporan yang valid akan diakui; publikasi detail ditunda sampai
perbaikan sudah di-deploy.
