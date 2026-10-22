<?php

session_start();
// Jika belum login, lempar kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include "../includes/cek_sesi.php";

include "../includes/koneksi.php";

function terbilang($angka)
{
    $angka = abs((int) $angka);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    if ($angka < 12) {
        return $huruf[$angka];
    } elseif ($angka < 20) {
        return terbilang($angka - 10) . " Belas";
    } elseif ($angka < 100) {
        return terbilang(intval($angka / 10)) . " Puluh " . terbilang($angka % 10);
    } elseif ($angka < 200) {
        return "Seratus " . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        return terbilang(intval($angka / 100)) . " Ratus " . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        return "Seribu " . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        return terbilang(intval($angka / 1000)) . " Ribu " . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        return terbilang(intval($angka / 1000000)) . " Juta " . terbilang($angka % 1000000);
    } else {
        return "Terlalu Besar";
    }
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$q = mysqli_query($koneksi, "SELECT p.*,
                                    k.name_lengkap, k.no_ktp, k.alamat_ktp, k.no_whatsapp,
                                    r.no_blok, r.tipe_rumah, r.luas_tanah, r.luas_bangunan, r.harga_jual, r.no_shm,
                                    c.cluster_name,
                                    t.status_konsumen
                             FROM data_pembatalan p
                             LEFT JOIN data_transaksi t ON p.id_transaksi = t.id_transaksi
                             LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                             LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
                             LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
                             WHERE p.id_pembatalan = $id");
$data = $q ? mysqli_fetch_assoc($q) : null;

if (!$data) {
    die("Data surat pembatalan tidak ditemukan.");
}

$booking_fee = (int) ($data['booking_fee'] ?? 0);
$nama        = trim($data['name_lengkap'] ?? '');
$alamat      = trim($data['alamat_ktp'] ?? '');
$nama_rek    = trim($data['rekening_nama'] ?? $nama);
$bank_rek    = trim($data['rekening_bank'] ?? '');
$no_rek      = trim($data['rekening_no'] ?? '');
$no_blok     = trim($data['no_blok'] ?? '');
$tgl = !empty($data['tgl_surat']) && $data['tgl_surat'] !== '0000-00-00'
    ? date('d F Y', strtotime($data['tgl_surat']))
    : '................';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Surat Pembatalan - Shankara Dev</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Times New Roman', Times, serif; color: #111; background: #f1f5f9; margin: 0; padding: 24px; }
        .sheet { background: #fff; max-width: 210mm; min-height: 297mm; margin: 0 auto; padding: 25mm 25mm 25mm 30mm; border: 1px solid #cbd5e1; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .toolbar { max-width: 210mm; margin: 0 auto 14px; text-align: right; }
        .btn-print, .btn-back { display: inline-block; padding: 10px 22px; font-family: Arial, sans-serif; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; text-decoration: none; }
        .btn-print { background: #1e3a8a; color: #fff; border: none; margin-right: 8px; }
        .btn-print:hover { background: #2563eb; }
        .btn-back { background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; }
        .btn-back:hover { background: #cbd5e1; }
        .kop { border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 20px; }
        .kop-inner { display: flex; align-items: center; justify-content: center; text-align: center; gap: 12px; flex-wrap: wrap; }
        .kop-logo { width: 70px; height: auto; object-fit: contain; }
        .kop-nama { font-size: 17px; font-weight: bold; color: #1e3a8a; letter-spacing: 0.5px; line-height: 1.3; }
        .kop-alamat { font-size: 12px; color: #475569; line-height: 1.4; }
        .judul { text-align: center; font-size: 16px; font-weight: bold; text-decoration: underline; color: #111; margin: 0 0 8px; }
        .no-surat { text-align: center; font-size: 13px; margin-bottom: 24px; }
        .konten { font-size: 14px; line-height: 1.7; text-align: justify; }
        .baris { display: block; }
        .isi { margin-top: 10px; }
        .blok { display: block; }
        .ttd { margin-top: 34px; text-align: right; }
        .ttd-tempat { font-size: 14px; margin-bottom: 12px; }
        .ttd-nama { font-weight: bold; text-decoration: underline; display: inline-block; }
        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff; padding: 0; }
            .sheet { border: none; box-shadow: none; max-width: none; min-height: 0; padding: 25mm 25mm 25mm 30mm; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
        <a class="btn-back" href="pembatalan_unit.php">Kembali</a>
    </div>
    <div class="sheet">

        <!-- KOP SURAT -->
        <div class="kop">
            <div class="kop-inner">
                <img src="../format_bast/logo/logo_kop.png" alt="Logo Shankara" class="kop-logo">
                <div>
                    <div class="kop-nama">PT. GOLDEN SHANKARA INDONESIA</div>
                    <div class="kop-alamat">Alamat : Jalan Lintas Kalimantan Poros Utara Kartiasa No. 86, Sambas &ndash; Kalimantan Barat</div>
                </div>
            </div>
        </div>

        <!-- JUDUL -->
        <div class="judul">SURAT PEMBATALAN PEMBELIAN UNIT RUMAH</div>
        <div class="no-surat">Nomor : <?= htmlspecialchars($data['no_surat']) ?></div>

        <!-- ISI -->
        <div class="konten">
            <span class="baris">Saya yang bertanda tangan di bawah ini :</span>
            <span class="baris" style="margin-top:8px;">&nbsp;&nbsp;&nbsp;Nama &nbsp;&nbsp;&nbsp;: <?= htmlspecialchars($nama) ?></span>
            <span class="baris">&nbsp;&nbsp;&nbsp;Alamat : <?= htmlspecialchars($alamat) ?></span>

            <div class="isi">
                Dengan ini menyatakan pembatalan pembelian <strong>1 (satu) Unit rumah</strong> (<?= htmlspecialchars($no_blok) ?>) di <strong>Perumahan SHANKARA RESIDENCE</strong>.
            </div>

            <div class="isi">Uang yang telah kami bayarkan adalah :</div>
            <div class="isi" style="padding-left: 20px;">
                Uang pemesanan (Booking Fee) untuk Blok <strong><?= htmlspecialchars($no_blok) ?></strong>
                sebesar <strong>Rp. <?= number_format($booking_fee, 0, ',', '.') ?></strong> (<?= terbilang($booking_fee) ?> Rupiah).
            </div>

            <div class="isi">
                Fotokopi kwitansi (Scan) pembayaran kami lampirkan sebagai bukti dan akan kami serahkan kwitansi asli ke
                pihak perusahaan jika dana tersebut di atas telah ditransfer / dibayarkan.
            </div>

            <div class="isi">
                Mohon uang booking fee dikirim ke nomor rekening sebagai berikut :
            </div>
            <span class="blok" style="padding-left: 20px; margin-top: 6px;">&nbsp;&nbsp;&nbsp;Nama &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= htmlspecialchars($nama_rek) ?></span>
            <span class="blok" style="padding-left: 20px;">&nbsp;&nbsp;&nbsp;Bank &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= htmlspecialchars($bank_rek) ?></span>
            <span class="blok" style="padding-left: 20px;">&nbsp;&nbsp;&nbsp;No. Rekening : <?= htmlspecialchars($no_rek) ?></span>

            <div class="isi">
                Demikian surat pembatalan ini saya sampaikan, atas bantuan dan kerja samanya kami ucapkan terima kasih.
            </div>
        </div>

        <!-- TANDA TANGAN -->
        <div class="ttd">
            <div class="ttd-tempat">Sambas, <?= htmlspecialchars($tgl) ?></div>
            <div>Hormat kami,</div>
            <div style="height: 90px;"></div>
            <div class="ttd-nama"><?= htmlspecialchars($nama) ?></div>
        </div>

    </div>
</body>
</html>