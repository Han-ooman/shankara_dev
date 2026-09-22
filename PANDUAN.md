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

## MENU & FITUR

### 1. Dashboard (`index.php`)
Halaman utama yang menampilkan ringkasan seluruh data:

- **4 Kartu Statistik:** Total Unit, Terjual, Terbooking, Tersedia
- **4 Widget BI Checking:** Total, Lulus, Dalam Proses, Tidak Lulus
- **Notifikasi Deadline Pemberkasan:** Daftar konsumen yang deadline dokumennya kurang dari 7 hari, beserta progress bar dan countdown
- **10 Transaksi Terbaru:** Daftar booking terakhir dengan link WhatsApp langsung
- **Input Transaksi Cepat:** Tombol "Input Transaksi" di pojok kanan atas

---

### 2. Transaksi (`transaksi.php`)
Mengelola data booking, status akad, dan skema pembiayaan.

**Fitur utama:**
- **Tambah Transaksi:** Isi tanggal booking, pilih konsumen, pilih unit rumah, pilih skema bayar (KPR Subsidi/Komersil, Cash Bertahap/Keras), status konsumen
- **Edit Transaksi:** Klik tombol pensil pada baris data untuk mengubah semua field
- **Hapus Transaksi:** Klik tombol tempat sampah, konfirmasi di modal — unit otomatis kembali ke status TERSEDIA
- **Filter Kolom:** Setiap kolom tabel bisa difilter (tanggal, konsumen, blok, cluster, skema bayar, status)

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
- **Progress otomatis:** Persentase kelengkapan berdasarkan skema bayar dan pekerjaan konsumen
- **Status Bank:** Pipeline Pemberkasan → Wawancara → OTS → ACC SP3K / Ditolak
- **Deadline & Wawancara:** Tanggal jatuh tempo dan jadwal interview
- **Filter Lanjutan:** Cluster, Status Bank, Bank Tujuan (multi-select dropdown), filter chip
- **Export PDF:** Klik "Unduh PDF" untuk cetak laporan pemberkasan

**Cara mengisi checklist:**
1. Klik tombol checklist pada baris konsumen
2. Centang dokumen yang sudah lengkap
3. Klik "Simpan" — progress otomatis terhitung

---

### 4. BI Checking (`bi_checking.php`)
Memantau hasil pengecekan kredit konsumen (SLIK/OJK/Bank).

**Fitur utama:**
- **Tambah Data:** Pilih konsumen, marketing, tanggal cek, lembaga, nomor pengajuan, hasil (Lulus/Dalam Proses/Dipertimbangkan/Tidak Lulus)
- **Edit Data:** Klik ikon pensil pada baris
- **Hapus Data:** Klik ikon tempat sampah
- **Filter:** Keyword, hasil, marketing, urutan (A-Z/Z-A/Terbaru/Terlama)
- **Link dari Konsumen:** Bisa diakses langsung dari halaman Konsumen via tombol "Cek BI"

---

### 5. Pembangunan (`pembangunan.php`)
Tracking progress pembangunan fisik per unit.

**Fitur utama:**
- **Update Satuan:** Klik "Update" → isi persentase, status pembangunan, catatan lapangan
- **Update Massal:** Centang beberapa unit → klik "Update Massal" → isi nilai yang sama untuk semua
- **Visual Progress Bar:** Setiap unit menampilkan progress bar animasi
- **Filter:** Keyword, cluster, status pembangunan
- **Cetak PDF:** Klik "Cetak Laporan" untuk export ke PDF

**Status pembangunan:** Belum Dikerjakan, Pondasi, Struktur, Dinding, Atap, Finishing, Selesai

---

### 6. Data Konsumen (`konsumen.php`)
Mengelola data identitas dan kontak konsumen.

**Fitur utama:**
- **Tambah/Edit/Hapus:** CRUD lengkap via modal
- **Edit Massal:** Centang beberapa konsumen → ubah pekerjaan secara bersamaan
- **Filter:** Keyword, pekerjaan, status WhatsApp
- **Badge BI Checking:** Status pengecekan kredit terakhir per konsumen
- **Link WhatsApp:** Klik nomor WA untuk chat langsung
- **Tombol "Cek BI":** Langsung menuju halaman BI Checking dengan konsumen terpilih

---

### 7. Data Unit Rumah (`unit.php`)
Master data unit rumah (blok, tipe, SHM, luas, harga, status).

**Fitur utama:**
- **Tambah/Edit/Hapus:** CRUD lengkap via modal
- **Filter:** Keyword (blok/tipe/SHM/cluster), cluster, status, tipe rumah
- **Status Badge:** TERSEDIA (hijau), TERBOOKING (kuning), TERJUAL (merah)
- **Harga:** Ditampilkan dalam format Rupiah

---

### 8. Data Cluster (`cluster.php`)
Mengelola cluster perumahan beserta siteplan interaktif.

**Fitur utama:**
- **Tambah/Edit/Hapus:** CRUD lengkap
- **Siteplan Interaktif:** Grid visual berwarna — hijau (tersedia), kuning (terbooking), merah (terjual)
- **Tooltip:** Arahkan kursor ke kotak unit untuk melihat detail
- **Klik Kotak:** Menuju halaman unit dengan filter cluster tersebut
- **Statistik Per-Cluster:** Total, Tersedia, Terbooking, Terjual
- **Status Proyek:** On Going / Done / Not Started

---

### 9. Data Karyawan (`karyawan.php`)
Mengelola data karyawan tetap, kontrak, dan magang.

**Fitur utama:**
- **Tambah/Edit/Hapus:** CRUD lengkap
- **Tipe Karyawan:** Tetap, Kontrak, Magang
- **Auto Magang:** Durasi bulan otomatis → tanggal selesai otomatis → status berubah ke "Selesai Magang"
- **Countdown Magang:** Progress bar sisa hari magang
- **6 Kartu Statistik:** Total karyawan, per tipe, aktif, nonaktif
- **Filter:** Keyword, tipe, status

---

### 10. Keuangan Perusahaan (`keuangan.php`)
Buku besar pemasukan dan pengeluaran perusahaan.

**Fitur utama:**
- **Tambah/Edit/Hapus:** CRUD lengkap
- **Jenis:** Pemasukan / Pengeluaran
- **Kategori Dinamis:** Berubah otomatis berdasarkan jenis (contoh: Fee Broker, Komisi Marketing untuk pemasukan; Gaji Karyawan, Perawatan untuk pengeluaran)
- **Kluster:** Tag transaksi ke cluster tertentu
- **4 Kartu Statistik:** Total Pemasukan, Pengeluaran, Saldo, Jumlah Transaksi
- **Filter Kolom:** Tanggal, jenis, kategori, cluster, no referensi
- **Export PDF:** Klik "Unduh PDF" untuk cetak laporan keuangan

---

### 11. Pembayaran & Kwitansi (`pembayaran.php`)
Mencatat penerimaan pembayaran dari konsumen.

**Fitur utama:**
- **Tambah Pembayaran:** Pilih transaksi, isi tanggal bayar, jenis (Booking Fee/DP/Cicilan/Pelunasan), nominal, metode bayar, no kwitansi
- **Hapus Pembayaran:** Konfirmasi di modal — data keuangan terkait otomatis terhapus
- **Auto-Sync ke Keuangan:** Setiap pembayaran otomatis masuk ke data keuangan sebagai Pemasukan
- **Cetak Kwitansi:** Klik ikon printer untuk cetak kwitansi PDF per pembayaran

---

### 12. Laporan (`laporan.php`)
Dashboard laporan penjualan dan performa proyek.

**Fitur utama:**
- **3 Chart:**
  - Diagram Batang: Jualan per cluster
  - Donut: Status unit (Tersedia/Terbooking/Terjual)
  - Donut: Distribusi skema bayar
- **Tabel Rekap Per-Cluster:** Total unit, tersedia, booking, terjual, dana masuk
- **Export PDF:**
  - "Unduh PDF" — generate otomatis via html2canvas + jsPDF
  - "Cetak Laporan" — via browser print dialog

---

### 13. Tim Marketing (`marketing.php`)
Mengelola data tim penjualan/marketing.

**Fitur utama:**
- **Tambah Marketing:** Isi nama, email, no WhatsApp — status otomatis "Aktif"
- **Link WhatsApp:** Klik nomor WA untuk chat langsung
- **Catatan:** Saat ini hanya bisa tambah, belum ada edit/hapus

---

### 14. Tipe Rumah (`tipe_rumah.php`)
Mengelola tipe rumah dan paket pembayaran cicilan.

**Fitur utama:**
- **Tambah/Edit/Hapus:** CRUD lengkap dengan layout kartu
- **Harga & DP:** Input harga jual, auto-hitung nominal DP dari persentase
- **Paket Cicilan Dinamis:** Tambah/hapus baris tenor (12/24/36/48/60 bulan) beserta nominal per bulan
- **Persyaratan Administrasi:** Daftar dokumen yang diperlukan per paket
- **Cetak PDF:** Klik "Cetak PDF" untuk cetak daftar tipe rumah

---

### 15. Serah Terima / BAST (`serah_terima.php`)
Mencatat berita acara serah terima unit ke konsumen.

**Fitur utama:**
- **Tambah/Edit/Hapus:** CRUD lengkap
- **Tautan Transaksi:** Pilih konsumen + blok dari data transaksi yang sudah ACC
- **Masa Garansi:** Tracking masa retensi/garansi unit
- **Status Garansi:** Masa Garansi / Selesai Garansi / Ada Komplain
- **Cetak BAST:** Klik ikon printer untuk cetak BAST PDF

---

### 16. Pengaturan Sistem (`pengaturan.php`)
Konfigurasi umum sistem (5 tab):

| Tab | Fungsi |
|-----|--------|
| **Profil Developer** | Nama perusahaan, pimpinan, telepon, email, rekening, alamat (untuk kop surat/BAST) |
| **Manajemen User** | Tambah/edit/hapus akun pengguna, tetapkan role & status |
| **Master Data** | Kelola daftar bank & daftar dokumen KPR master |
| **Penomoran & Keuangan** | Format template nomor kwitansi, BAST, transaksi |
| **Backup & Restore** | Download backup database (.sql) / Upload restore dari file .sql |

**Role Pengguna:**
| Role | Akses |
|------|-------|
| Super Admin | Akses semua fitur |
| Admin Pemberkasan | Pemberkasan + Konsumen |
| Pengawas Lapangan | Pembangunan + Unit |
| Kasir Keuangan | Keuangan + Pembayaran |

---

## PENGGUNAAN HARI-HARI

### Workflow Harian Marketing:
1. Login → **Dashboard** untuk cek overview
2. **Data Konsumen** → tambah data konsumen baru
3. **Transaksi** → input transaksi booking
4. **Pembayaran** → catat pembayaran Booking Fee/DP → cetak kwitansi
5. **BI Checking** → input hasil pengecekan kredit

### Workflow Harian Admin Pemberkasan:
1. Login → cek **Deadline Pemberkasan** di Dashboard
2. **Pemberkasan KPR** → update checklist dokumen per konsumen
3. Update status bank (Wawancara → OTS → ACC/Ditolak)

### Workflow Harian Lapangan:
1. Login → **Pembangunan** → update progress fisik unit
2. Bisa update satuan atau massal

### Workflow Harian Keuangan:
1. Login → **Keuangan** → input pemasukan/pengeluaran
2. **Pembayaran** → verifikasi pembayaran konsumen
3. **Laporan** → cetak laporan bulanan

### Backup Rutin:
1. **Pengaturan** → tab Backup & Restore → klik "Download Backup"
2. Simpan file `.sql` secara berkala

---

## TIPS PENTING

- **Session timeout 10 menit** — simpan pekerjaan sebelum timeout
- **1 device per akun** — login di device lain akan logout device sebelumnya
- **Auto-refresh 30 detik** — halaman otomatis refresh setiap 30 detik (skip jika modal/form aktif)
- **WhatsApp integrasi** — klik nomor WA di mana saja untuk langsung chat konsumen
- **Filter chip** — di halaman Pemberkasan, filter cluster/bank muncul sebagai chip yang bisa dihapus satu per satu
- **Export PDF** — tersedia di Pemberkasan, Keuangan, Pembangunan, Laporan, Kwitansi, BAST, dan Tipe Rumah
