# PANDUAN PENGGUNAAN SHANKARA DEV
## Sistem Manajemen Perumahan - PT. Golden Shankara Indonesia

---

## AKSES SISTEM

| Akses | URL |
|-------|-----|
| Lokal | `http://localhost/shandev/` |
| Tailscale | `http://100.70.141.62/shandev/` |
| Publik (Funnel) | `https://desktop-sojeo3b.tail716132.ts.net/shandev/` |

**Login:** Masukkan username dan password yang sudah didaftarkan oleh Admin.
**Session timeout:** Sesi otomatis berakhir setelah 10 menit tidak aktif.
**Batas device:** 1 akun = 1 device aktif (kecuali akun Traine: 2 device).

---

## STRUKTUR FOLDER PROJECT

```
shandev/
├── index.php, login.php, *.php          # 27 halaman utama
├── .htaccess, manifest.json, pwa_head.php, sidebar.php
├── PANDUAN.md                           # File ini
├── pengingat.php                        # Pengingat & Notifikasi Push
├── push_api.php                         # Endpoint Web Push (subscribe/test)
├── sw.js                                # Service Worker (push + cache)
├── cli_push_gen.php                     # Generate kunci VAPID (CLI only)
├── api/                                 # REST API (endpoint /api/)
│   ├── index.php                        # Router
│   ├── controllers/ (11)                # Auth, Cluster, TipeRumah, Rumah, Konsumen,
│   │                                     # Transaksi, Pembayaran, Pembatalan, Pemberkasan, Laporan
│   ├── helpers/ (5)                     # Database, Response, Auth, Request, Log
│   └── routes/api.php                   # Definisi route
├── includes/ (9)                        # Shared PHP
│   ├── koneksi.php, cek_sesi.php, security.php
│   ├── berkas_helper.php, helper_log.php, page_loader.php
│   ├── proses_transaksi.php, toggle_maintenance.php, auto_update_laporan.php
├── print/ (8)                           # Cetak PDF
│   ├── cetak_bast.php, cetak_keuangan.php, cetak_kwitansi.php
│   ├── cetak_laporan_harian.php, cetak_pembatalan.php
│   ├── cetak_pemberkasan.php, cetak_slip_gaji.php, cetak_tipe_rumah.php
├── assets/                              # Static assets
│   ├── css/app.css, style.css
│   ├── js/app.js, idle.js, push_client.js
│   └── images/favicon/
├── data/                                # CSV import/export (di-ignore git)
├── format_bast/                         # Template PDF & logo kop
└── logs/                                # php_errors.log, funnel_log.txt
```

---

## REST API ENDPOINT

Base URL: `http://localhost/shandev/api` (atau IP Tailscale)

| Modul | Endpoint |
|-------|----------|
| **Auth** | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| **Cluster** | `GET/POST /clusters`, `GET/PUT/DELETE /clusters/{id}`, `GET /clusters/options`, `GET /clusters/stats` |
| **Tipe Rumah** | `GET/POST /tipe-rumah`, `GET/PUT/DELETE /tipe-rumah/{id}`, `GET /tipe-rumah/by-cluster/{clusterId}` |
| **Rumah** | `GET/POST /rumah`, `GET/PUT/DELETE /rumah/{id}`, `GET /rumah/available`, `PUT /rumah/{id}/status` |
| **Konsumen** | `GET/POST /konsumen`, `GET/PUT/DELETE /konsumen/{id}`, `GET /konsumen/search`, `GET /konsumen/{id}/transaksi` |
| **Transaksi** | `GET/POST /transaksi`, `GET/PUT/DELETE /transaksi/{id}`, `GET /transaksi/stats`, `GET /transaksi/konsumen/{id}`, `PUT /transaksi/{id}/status` |
| **Pembayaran** | `GET/POST /pembayaran`, `GET/PUT/DELETE /pembayaran/{id}`, `GET /pembayaran/transaksi/{id}`, `GET /pembayaran/transaksi/{id}/summary` |
| **Pembatalan** | `GET/POST /pembatalan`, `GET/PUT/DELETE /pembatalan/{id}`, `GET /pembatalan/{id}/print` |
| **Pemberkasan** | `GET/PUT /pemberkasan`, `GET /pemberkasan/stats`, `GET/PUT /pemberkasan/{id}`, `PUT /pemberkasan/{id}/berkas` |
| **Laporan** | `GET /laporan/dashboard`, `GET /laporan/penjualan`, `GET /laporan/pembayaran`, `GET /laporan/pembangunan`, `GET /laporan/harian` |

**Fitur API:** Session-based auth (PHPSESSID), role-based access, pagination standar, validasi input, logging aktivitas, prepared statements.

---

## MENU & FITUR

### 1. Dashboard (`index.php`)
Halaman utama menampilkan ringkasan seluruh data:
- **4 Kartu Statistik:** Total Unit, Terjual, Terbooking, Tersedia
- **4 Widget BI Checking:** Total, Lulus, Dalam Proses, Tidak Lulus
- **Notifikasi Deadline Pemberkasan:** Daftar konsumen deadline < 7 hari + progress bar & countdown
- **10 Transaksi Terbaru:** Booking terakhir dengan link WhatsApp
- **Input Transaksi Cepat:** Tombol "Input Transaksi" pojok kanan atas

---

### 2. Transaksi (`transaksi.php`)
Mengelola data booking, status akad, skema pembiayaan.

**Fitur utama:**
- **Tambah Transaksi:** Tanggal booking, pilih konsumen, pilih unit, skema bayar (KPR Subsidi/Komersil, Cash Bertahap/Keras), status konsumen
- **Edit Transaksi:** Klik pensil pada baris
- **Hapus Transaksi:** Klik tempat sampah — unit otomatis kembali TERSEDIA
- **Filter Kolom:** Setiap kolom difilter (tanggal, konsumen, blok, cluster, skema, status)

**Status Otomatis:**
| Status Konsumen | Efek ke Unit |
|----------------|-------------|
| PROSES | Unit → TERBOOKING |
| ACC - AKAD | Unit → TERJUAL |
| BATAL | Unit → TERSEDIA |

---

### 3. Pemberkasan KPR (`pemberkasan.php`)
Tracking kelengkapan dokumen KPR konsumen.

**Fitur utama:**
- **29 Dokumen:** Checklist per kategori (Identitas, Pekerjaan, Keuangan, Properti, dll.)
- **Progress otomatis:** Persentase kelengkapan berdasarkan skema bayar & pekerjaan
- **Status Bank:** Pipeline Pemberkasan → Wawancara → OTS → ACC SP3K / Ditolak
- **Deadline & Wawancara:** Tanggal jatuh tempo & jadwal interview
- **Filter Lanjutan:** Cluster, Status Bank, Bank Tujuan (multi-select dropdown), filter chip
- **Export PDF:** Klik "Unduh PDF" untuk cetak laporan

---

### 4. BI Checking (`bi_checking.php`)
Memantau hasil pengecekan kredit konsumen (SLIK/OJK/Bank).

**Fitur:** Tambah/Edit/Hapus data BI, filter keyword/hasil/marketing/urutan, link dari halaman Konsumen.

---

### 5. Pembangunan (`pembangunan.php`)
Tracking progress pembangunan fisik per unit.

**Fitur:** Update satuan/massal, visual progress bar animasi, filter keyword/cluster/status, cetak PDF.

**Status:** Belum Dikerjakan, Pondasi, Struktur, Dinding, Atap, Finishing, Selesai

---

### 6. Data Konsumen (`konsumen.php`)
Mengelola identitas & kontak konsumen.

**Fitur:** CRUD via modal, edit massal pekerjaan, filter keyword/pekerjaan/status WA, badge BI Checking, link WhatsApp, tombol "Cek BI".

---

### 7. Data Unit Rumah (`unit.php`)
Master data unit (blok, tipe, SHM, luas, harga, status).

**Fitur:** CRUD via modal, filter keyword/cluster/status/tipe, status badge (TERSEDIA/TERBOOKING/TERJUAL), harga format Rupiah.

---

### 8. Data Cluster (`cluster.php`)
Mengelola cluster perumahan + siteplan interaktif.

**Fitur:** CRUD, siteplan grid berwarna (hijau/kuning/merah), tooltip detail, klik kotak → filter unit, statistik per-cluster, status proyek (On Going/Done/Not Started).

---

### 9. Data Karyawan (`karyawan.php`)
Mengelola karyawan tetap, kontrak, magang.

**Fitur:** CRUD, tipe karyawan, auto magang (durasi → tanggal selesai otomatis), countdown magang, 6 kartu statistik, filter.

---

### 10. Keuangan Perusahaan (`keuangan.php`)
Buku besar pemasukan & pengeluaran.

**Fitur:** CRUD, jenis Pemasukan/Pengeluaran, kategori dinamis per jenis, tag cluster, 4 kartu statistik, filter kolom, export PDF.

---

### 11. Pembayaran & Kwitansi (`pembayaran.php`) — **DIPERBARUI**
Mencatat pembayaran konsumen (pemasukan) & pengeluaran perusahaan (kwitansi di luar unit).

**Fitur:**
- **Kwitansi Pemasukan (Konsumen → PT):** Pilih transaksi, jenis (Booking Fee/DP/Cicilan/Pelunasan), nominal, metode, no kwitansi → auto-sync ke Keuangan, cetak kwitansi PDF. Tanda tangan penerima: `( Hanapi, S.Tr.Kom )` / "Penerima / Administrator".
- **Kwitansi Multi-Blok:** Satu kwitansi dapat memuat 2+ unit untuk 1 konsumen (checkbox + kotak cari "Cari blok / nama konsumen..." + informasi "N blok terpilih"). Daftar blok tampil sebagai badge di tabel riwayat ("Riwayat Kwitansi") dan dicetak lengkap di kwitansi (mis. `NO. BLOK A-07, A-08, A-09`, "pembelian 3 unit rumah"). Validasi otomatis: minimal 1 blok dipilih & seluruh blok harus atas konsumen yang sama.
- **Kwitansi Pengeluaran (PT → Konsumen):** Pilih **"Di Luar Unit (Pengeluaran)"** → input **Jenis** (Refund/Komisi/Bantuan/Koreksi/Lainnya) dan **Nama Penerima** → otomatis dicatat di Keuangan sebagai **Pengeluaran** dengan deskripsi `Refund - <penerima> (<keterangan>)`. Cetak: judul "KWITANSI Pengeluaran", "Telah dibayarkan kepada...", "Untuk Pengeluaran...", tanda tangan `( Nama Penerima )`.
- **Tabel Riwayat:** Kolom **Arus** (badge hijau *Pemasukan* / merah *Pengeluaran*) & kolom **Penerima / Konsumen**.
- Hapus pembayaran → auto-hapus keuangan terkait.

---

### 12. Laporan (`laporan.php`)
Dashboard laporan penjualan & performa.

**Fitur:** 3 chart (batang jualan per cluster, donut status unit, donut skema bayar), tabel rekap per-cluster, export PDF (html2canvas+jsPDF / browser print).

---

### 13. Tim Marketing (`marketing.php`)
Mengelola data tim penjualan.

**Fitur:** Tambah marketing (nama, email, WA, status otomatis Aktif), link WhatsApp. *Catatan: Belum ada edit/hapus.*

---

### 14. Tipe Rumah (`tipe_rumah.php`)
Mengelola tipe rumah & paket cicilan.

**Fitur:** CRUD kartu, harga & DP (auto-hitung nominal dari persentase), paket cicilan dinamis (tambah/hapus tenor 12-60 bln), persyaratan administrasi, cetak PDF.

---

### 15. Serah Terima / BAST (`serah_terima.php`)
Mencatat berita acara serah terima unit.

**Fitur:** CRUD, tautan transaksi (pilih konsumen+blok dari ACC), masa garansi tracking, status garansi, cetak BAST PDF.

---

### 16. Pembatalan Unit (`pembatalan_unit.php`) — **DIPERBARUI**
Mengelola pembatalan unit konsumen.

**Fitur Baru:**
- Field tambahan: **Booking Fee**, **Rekening** (bank, nomor, an, cabang), **Status Pembatalan** (Draft/Disetujui)
- **Modal Edit per Baris:** Klik pensil → modal edit field pembatalan
- **Autofill dari Transaksi:** JS otofill konsumen, blok, cluster, tipe, harga, booking fee
- **Cetak Surat PDF:** `cetak_pembatalan.php` — format PDF-compliant A4 (margin 25/25/25/30mm), kop logo, ttd rata kanan flush, terbilang Indonesia, teks "(no_blok)"

---

### 17. Laporan Pembangunan (`laporan_pembangunan.php`) — **DIPERBARUI**
**Filter Nama Dropdown Checkbox:** Custom dropdown (`#btnFilterNama`, `#namaFilterMenu`), badge counter, daftar nama dari `data-name` baris tampil, gabung filter blok/status/cluster/nama.

---

### 18. Pengaturan Sistem (`pengaturan.php`)
Konfigurasi umum (5 tab):

| Tab | Fungsi |
|-----|--------|
| **Profil Developer** | Nama perusahaan, pimpinan, telepon, email, rekening, alamat (kop surat/BAST) |
| **Manajemen User** | Tambah/edit/hapus akun, tetapkan role & status |
| **Master Data** | Kelola daftar bank & daftar dokumen KPR master |
| **Penomoran & Keuangan** | Format template nomor kwitansi, BAST, transaksi |
| **Backup & Restore** | Download backup database (.sql) / Upload restore dari .sql |

**Role Pengguna:**
| Role | Akses |
|------|-------|
| Super Admin | Akses semua fitur |
| Admin Pemberkasan | Pemberkasan + Konsumen |
| Pengawas Lapangan | Pembangunan + Unit |
| Kasir Keuangan | Keuangan + Pembayaran |

---

### 19. Status KPR (`status_kpr.php`)
Memantau & menyaring status KPR seluruh transaksi.

**Filter — satu form (pilih semua → klik "Terapkan Filter"):**
- **Pencarian:** kata kunci konsumen / blok / cluster (Enter atau tombol Terapkan)
- **Status:** BPHTB/Tapera/Trilogi/Sikumbang Belum, Cluster, Status konsumen, Skema bayar
- **Tahap KPR per modul:** BPHTB, Tapera, Trilogi, SIKUMB (Belum/Sudah)
- **Rentang tanggal akad:** dari – sampai
- **Badge Aktif:** filter yang dipilih tampil sebagai badge (hapus satu per satu / "Bersihkan Semua"), tombol **Reset**, penghitung jumlah baris hasil. Setelah edit status massal/tunggal, filter aktif tetap tersimpan.

---

## CLUSTER & DATA UPDATE TERBARU

### Split Cluster SHANKARA RESIDENCE 3 (Dalam Kaum)
| ID | Cluster | Unit | Blok |
|----|---------|------|------|
| 3 | SHANKARA RESIDENCE 3 TAHAP 1 | 17 | A-01 .. A-17 |
| 7 | SHANKARA RESIDENCE 3 TAHAP 2 | 8 | B-01 .. B-08 |
| **Total** | | **25** | |

### Split Cluster KARTIASA
| ID | Cluster | Unit | Blok |
|----|---------|------|------|
| 1 | SHANKARA RESIDENCE TAHAP 1 | 95 | A-01 .. A-17, dst |
| 6 | SHANKARA RESIDENCE TAHAP 2 | 37 | B≥30, K, L, M |

### Pembersihan Data Duplikat
- **RULY APRI:** Hapus id 85 (sisa id 4 A-01 PROSES, id 109 A-02 ACC-AKAD + KW/014)
- **FIDIN FATONI:** Hapus id 151 (sisa id 138 K-01 PROSES + KW/021 DP 29jt)

### Unit SHANKARA MIDTOWN RESIDENCE (id 4)
| Blok | Jumlah | Keterangan |
|------|--------|------------|
| A-01 .. A-24 | 24 | TERSEDIA, tipe/SHM/luas kosong, harga 0 |
| A-25 | 1 | TERBOOKING (tipe 36, 182jt) |
| A-26 | 1 | TERSEDIA, kosong |
| **Total** | | **26 unit (A-01 .. A-26)** |

### Tipe Rumah Baru
- **35 (Komersil)** (id 12): harga **Rp 184.000.000**, DP **Rp 2.000.000** (1,09%), plafon kredit Rp 182.000.000. Skema angsuran bertahap:
  - **Tenor 120 bln:** Thn 1 → 1.908.229 | 2-3 → 2.072.810 | 4 → 2.280.707 | 5-10 → 2.407.189
  - **Tenor 180 bln:** Thn 1 → 1.415.654 | 2-3 → 1.598.890 | 4-10 → 1.859.692 | 11-15 → 1.903.156

---

## NOTIFIKASI PUSH & PENGINGAT

### Halaman Pengingat (`pengingat.php`)
Menu **"Pengingat & Notifikasi"** (grup Pengaturan) menampilkan item yang perlu follow-up:
- **Deadline Pemberkasan** (berkas belum lengkap)
- **Wawancara Bank**
- **Akad KPR** (konsumen PROSES, belum ACC)
- **Follow-up Prospek** (status belum Deal/Tidak Lanjut)

Item tampil mulai **H-7** hingga **telat**, diurutkan dari yang paling mendesak. Setiap baris punya tombol **WA** yang membuka WhatsApp dengan pesan template otomatis (nama, unit, cluster, tanggal, sisa hari).

**Filter:** jenis pengingat + status (semua / telat / hari ini / H-1-3 / H-4-7).

### Template WhatsApp
Template pesan disimpan di `pengaturan_sistem` (kunci `pengingat_tpl_deadline`, `pengingat_tpl_wawancara`, `pengingat_tpl_akad`, `pengingat_tpl_followup`). Placeholder: `{nama}`, `{unit}`, `{cluster}`, `{tanggal}`, `{sisa}`, `{bank}`.
Ubah lewat SQL atau `pengaturan.php`.

### Web Push Notifications (PWA)
- **Syarat browser:** Chrome/Edge/Firefox (desktop+Android). Di **localhost** langsung bisa; di **Tailscale/public** butuh **HTTPS** agar Notification API jalan.
- **Aktifkan Notifikasi:** Klik tombol hijau "Aktifkan Notifikasi" di halaman Pengingat → izinkan permintaan browser → browser mendaftarkan subscription ke `push_subscriptions`.
- **Kirim Uji Push:** Tombol "Kirim Uji Push" mengirim notifikasi tes ke semua perangkat yang terdaftar untuk akun Anda.
- **Sumber server:** VAPID JWT (ES256, RFC 8292) + enkripsi payload (RFC 8291/8188, aes128gcm).
- **Kunci VAPID:** dibuat otomatis via `cli_push_gen.php` (dijalankan sekali lewat CLI, tersimpan di `pengaturan_sistem`). JANGAN generate di web runtime karena butuh `OPENSSL_CONF` di environment.

### Upload/Regenerasi Kunci (setelah pindah server)
```powershell
$env:OPENSSL_CONF = "C:\xampp\php\extras\ssl\openssl.cnf"
cd C:\xampp\htdocs\shandev
C:\xampp\php\php.exe cli_push_gen.php
Remove-Item Env:\OPENSSL_CONF
```
Kunci baru = semua subscription perangkat lama harus diaktifkan ulang.

---

## TEMA & UI GLOBAL

- **Modal Biru Global:** Semua modal header biru `#1e3a8a`, judul putih, close putih (via override `app.css:275-299`)
- **Bootstrap Success → Biru:** `.btn-success`, `.bg-success`, `.badge-success`, `.text-success` di-override ke `--primary-blue` (`app.css:226-249`)
- **CSS Specificity:** `.modal .modal-header` (0,2,0) mengalahkan utility Bootstrap (0,1,0)
- **PWA Ready:** `manifest.json`, `sw.js`, `idle.js`, `pwa_head.php`

---

## PENGGUNAAN HARI-HARI

### Workflow Harian Marketing:
1. Login → **Dashboard** overview
2. **Data Konsumen** → tambah konsumen baru
3. **Transaksi** → input booking
4. **Pembayaran** → catat Booking Fee/DP → cetak kwitansi
5. **BI Checking** → input hasil pengecekan kredit

### Workflow Harian Admin Pemberkasan:
1. Login → cek **Deadline Pemberkasan** di Dashboard
2. **Pemberkasan KPR** → update checklist dokumen
3. Update status bank (Wawancara → OTS → ACC/Ditolak)

### Workflow Harian Lapangan:
1. Login → **Pembangunan** → update progress fisik (satuan/massal)

### Workflow Harian Keuangan:
1. Login → **Keuangan** → input pemasukan/pengeluaran
2. **Pembayaran** → verifikasi pembayaran konsumen
3. **Laporan** → cetak laporan bulanan

### Backup Rutin:
1. **Pengaturan** → tab Backup & Restore → "Download Backup"
2. Simpan file `.sql` secara berkala

---

## TIPS PENTING

- **Session timeout 10 menit** — simpan pekerjaan sebelum timeout
- **1 device per akun** — login device lain = logout device sebelumnya
- **Auto-refresh 30 detik** — halaman refresh otomatis (skip jika modal/form aktif)
- **WhatsApp integrasi** — klik nomor WA di mana saja untuk chat langsung
- **Filter chip** — di Pemberkasan, filter cluster/bank sebagai chip hapus per item
- **Export PDF** — tersedia di Pemberkasan, Keuangan, Pembangunan, Laporan, Kwitansi, BAST, Tipe Rumah, **Pembatalan**
- **API Access** — gunakan endpoint `/api/` untuk integrasi eksternal (session cookie PHPSESSID)

---

## DEPLOYMENT & MAINTENANCE

### Git
```bash
cd C:\xampp\htdocs\shandev
git add .
git commit -m "Pesan commit"
git push origin main
```

### Database
- DB: `db_perumahan` (MariaDB)
- Koneksi: `includes/koneksi.php`
- Backup: `pengaturan.php` → tab Backup & Restore

### Logs
- `logs/php_errors.log` — PHP errors
- `logs/funnel_log.txt` — Funnel activity
- `log_aktivitas.php` — Audit trail user actions

---

*Terakhir diperbarui: 8 Oktober 2026*