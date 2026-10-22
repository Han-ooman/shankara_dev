<?php

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include __DIR__ . "/../includes/cek_sesi.php";

include __DIR__ . "/../includes/koneksi.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Query detail pembayaran. LEFT JOIN agar kwitansi di luar unit rumah (tanpa transaksi) tetap bisa dicetak.
$query = sdb_query($koneksi, "SELECT p.*, 
                                       COALESCE(k.name_lengkap, p.nama_pembayar, 'Pembayar') AS name_lengkap,
                                       r.no_blok, r.tipe_rumah,
                                       c.cluster_name,
                                       c.lokasi AS alamat_cluster
                                FROM data_pembayaran p
                                LEFT JOIN data_transaksi t ON p.id_transaksi = t.id_transaksi
                                LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                WHERE p.id_pembayaran = '$id'");

$data = sdb_fetch_assoc($query);

if (!$data) {
    die("Data kwitansi tidak ditemukan.");
}

// Query semua blok milik kwitansi ini (kwitansi bisa memuat 2+ blok dari 1 orang)
$blok_list = [];
$q_blok = sdb_query($koneksi, "SELECT r.no_blok, r.tipe_rumah, c.cluster_name, c.lokasi, k.name_lengkap
                                  FROM data_pembayaran_detail pd
                                  JOIN data_transaksi t ON pd.id_transaksi = t.id_transaksi
                                  LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                  LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                  LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                  WHERE pd.id_pembayaran = '$id'
                                  ORDER BY c.cluster_name, r.no_blok");
if ($q_blok) {
    while ($b = sdb_fetch_assoc($q_blok)) {
        $blok_list[] = $b;
    }
}

// Kwitansi di luar unit rumah: tanpa blok / cluster, arah uang PT -> Konsumen
$is_non_unit = empty($data['id_transaksi']);
$jenis_arah  = $is_non_unit ? 'Pengeluaran' : 'Pembayaran';

// Blok ditampilkan dari detail; fallback ke transaksi utama bila detail kosong
$jumlah_blok = count($blok_list);
if ($jumlah_blok === 0 && !$is_non_unit && !empty($data['no_blok'])) {
    $blok_list[] = [
        'no_blok'       => $data['no_blok'],
        'tipe_rumah'    => $data['tipe_rumah'] ?? '',
        'cluster_name'  => $data['cluster_name'] ?? '',
        'lokasi'        => $data['alamat_cluster'] ?? '',
        'name_lengkap'  => $data['name_lengkap'] ?? '',
    ];
    $jumlah_blok = 1;
}
$daftar_blok_txt = implode(', ', array_column($blok_list, 'no_blok'));

// Menentukan Nama Cluster dan Alamat Sesuai Data Cluster
$cluster_blok = $blok_list[0]['cluster_name'] ?? $data['cluster_name'] ?? '';
$nama_cluster   = !empty($cluster_blok) ? strtoupper($cluster_blok) : 'SHANKARA RESIDENCE';
$alamat_lokasi  = !empty($blok_list[0]['lokasi']) ? $blok_list[0]['lokasi'] : (!empty($data['alamat_cluster']) ? $data['alamat_cluster'] : 'Jln. Sukaramai, Dalam Kaum');

// Satuan properti menyesuaikan jenis cluster (kavling vs unit rumah) dan jumlah blok
$satuan_properti = $is_non_unit ? '' : ((stripos($nama_cluster, 'KAVLING') !== false) ? ($jumlah_blok . ' kavling') : ($jumlah_blok . ' unit rumah'));

// Catatan / keterangan pembayaran, ditampilkan setelah kalimat "Untuk Pembayaran"
$catatan = trim(preg_replace('/\s+/', ' ', (string)($data['keterangan'] ?? '')));

// Fungsi Terbilang Indonesia
function terbilang($angka) {
    $angka = abs($angka);
    $baca = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $terbilang = "";
    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } else if ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " Belas";
    } else if ($angka < 100) {
        $terbilang = terbilang($angka / 10) . " Puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $terbilang = " Seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $terbilang = terbilang($angka / 100) . " Ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $terbilang = " Seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $terbilang = terbilang($angka / 1000) . " Ribu" . terbilang($angka % 1000);
    } else if ($angka < 1000000000) {
        $terbilang = terbilang($angka / 1000000) . " Juta" . terbilang($angka % 1000000);
    }
    return $terbilang;
}

// Format Tanggal Indonesia
function tgl_indo($tanggal){
    $bulan = array (
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

$tgl_pembayaran = tgl_indo($data['tgl_bayar']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <link rel="icon" type="image/png" href="../assets/images/favicon/favicon.png">    <title>Kwitansi<?= $is_non_unit ? '_NonUnit' : '_Blok_' . htmlspecialchars($data['no_blok'] ?? '-') ?></title>
    <style>
    /* PENGATURAN KERTAS PORTRAIT FULL PAGE */
    @page {
        size: A4 portrait;
        margin: 5mm 8mm;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
        background-color: #f4f7f5;
        padding: 10px 0 0 0;
        margin: 0;
        color: #111;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* CARD KWITANSI FULL WIDTH */
    .kwitansi-card {
        background: #fff;
        border: 2px solid #1e3a8a;
        width: 100%;
        max-width: 1000px;
        margin: 0 auto;
        padding: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        position: relative;
    }

    /* HEADER */
    .header-bg {
        background-color: #1e3a8a !important;
        color: #fff;
        text-align: center;
        padding: 20px 15px;
        border-bottom: 2px solid #1e3a8a;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .header-company {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin: 0;
        color: #fff;
    }

    .header-cluster {
        font-size: 20px;
        font-weight: 700;
        margin: 6px 0 3px 0;
        text-transform: uppercase;
        color: #ffcc00;
    }

    .header-address {
        font-size: 14px;
        font-style: italic;
        margin: 0;
        color: #e2e8f0;
    }

    .body-content {
        padding: 28px 35px;
    }

    /* BARIS ATAS: NO BLOK & KWITANSI */
    .row-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .no-blok-box {
        font-size: 16px;
        font-weight: 600;
    }

    .no-blok-val {
        display: inline-block;
        border-bottom: 2px solid #000;
        min-width: 90px;
        text-align: center;
        font-size: 18px;
        font-weight: 700;
    }

    .kwitansi-title {
        font-size: 22px;
        font-weight: 700;
        text-decoration: underline;
        text-align: center;
        flex-grow: 1;
        margin-right: 110px;
        letter-spacing: 1.5px;
    }

    /* ISIAN TABEL */
    .kwitansi-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 16px;
    }

    .kwitansi-table td {
        padding: 8px 4px;
        vertical-align: middle;
    }

    .label-col {
        width: 180px;
        font-size: 15px;
        font-weight: 600;
    }

    .colon-col {
        width: 15px;
        font-weight: 600;
    }

    .value-box {
        border: 2px solid #000;
        padding: 8px 12px;
        font-weight: 700;
        text-transform: uppercase;
        display: block;
        letter-spacing: 0.5px;
    }

    .yellow-highlight {
        background-color: #ffcc00 !important;
        padding: 8px 12px;
        font-weight: 700;
        font-style: italic;
        display: block;
        color: #000;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .underline-text {
        border-bottom: 1px solid #000;
        display: block;
        padding-bottom: 4px;
        line-height: 1.6;
        font-size: 15px;
    }

    .blank-line {
        border-bottom: 1px solid #000;
        height: 22px;
        margin-top: 4px;
    }

    /* FOOTER NOMINAL & TTD */
    .footer-row {
        margin-top: 35px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    .nominal-wrapper {
        display: flex;
        align-items: center;
        font-size: 22px;
        font-weight: 700;
    }

    .rp-text {
        margin-right: 15px;
    }

    .nominal-box {
        border: 2px solid #000;
        background-color: #ffcc00 !important;
        padding: 8px 28px;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 1px;
        color: #000;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .ttd-wrapper {
        text-align: center;
        width: 260px;
    }

    .ttd-kota {
        font-size: 15px;
        border-bottom: 1px solid #000;
        padding-bottom: 3px;
        display: inline-block;
        margin-bottom: 55px;
        min-width: 190px;
    }

    .ttd-nama {
        font-size: 15px;
        font-weight: 700;
        border-bottom: 1px solid #000;
        display: inline-block;
        padding: 0 10px;
    }

    .ttd-jabatan {
        font-size: 14px;
        margin-top: 3px;
    }

    .no-print-btn {
        text-align: center;
        margin-bottom: 15px;
    }

    .btn-print {
        background-color: #1e3a8a;
        color: #fff;
        border: none;
        padding: 10px 24px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
    }

    /* ATURAN SAAT DICETAK / EXPORT PDF */
    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        html, body {
            background-color: #fff;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }
        .kwitansi-card {
            box-shadow: none;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            border: 2px solid #1e3a8a;
            /* Scale 72% hanya saat cetak / export PDF, desain di layar tetap asli */
            zoom: 0.72;
        }
        .no-print-btn {
            display: none;
        }
    }
</style>
</head>
<body>

<div class="no-print-btn">
        <button onclick="window.print()" class="btn-print">🖨 Cetak / Simpan PDF</button>
</div>

<div class="kwitansi-card">
    <!-- HEADER -->
    <div class="header-bg">
        <h1 class="header-company">PT. GOLDEN SHANKARA INDONESIA</h1>
        <div class="header-cluster"><?= htmlspecialchars($nama_cluster) ?></div>
        <!-- Alamat otomatis menyesuaikan kolom lokasi pada tabel data_cluster -->
        <p class="header-address"><i class="bi bi-geo-alt-fill me-1"></i> Lokasi: <?= htmlspecialchars($alamat_lokasi) ?></p>
    </div>

    <!-- BODY CONTENT -->
    <div class="body-content">
        <!-- BARIS NO BLOK & KWITANSI -->
        <div class="row-top">
            <div class="no-blok-box">
                <?php if ($is_non_unit): ?>
                    BUKAN UNIT RUMAH <span class="no-blok-val">-</span>
                <?php else: ?>
                    NO. BLOK <span class="no-blok-val"><?= htmlspecialchars($daftar_blok_txt ?: '-') ?></span>
                <?php endif; ?>
            </div>
            <div class="kwitansi-title">
                <u>KWITANSI <?= $jenis_arah ?></u>
            </div>
        </div>

        <!-- TABEL ISIAN -->
        <table class="kwitansi-table">
            <tr>
                <td class="label-col"><?= $is_non_unit ? 'Telah dibayarkan kepada' : 'Telah terima dari' ?></td>
                <td class="colon-col">:</td>
                <td>
                    <div class="value-box">
                        <?= htmlspecialchars($data['name_lengkap']) ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="label-col" style="padding-top: 10px;">Uang sejumlah</td>
                <td class="colon-col" style="padding-top: 10px;">:</td>
                <td style="padding-top: 10px;">
                    <div class="yellow-highlight">
                        <?= trim(terbilang($data['jumlah_bayar'])) ?> Rupiah
                    </div>
                </td>
            </tr>
            <tr>
                <td class="label-col" style="padding-top: 12px;">Untuk <?= $jenis_arah ?></td>
                <td class="colon-col" style="padding-top: 12px;">:</td>
                <td style="padding-top: 12px;">
                    <div class="underline-text">
                        <?php if ($is_non_unit): ?>
                            Pengeluaran <?= htmlspecialchars($data['jenis_pembayaran']) ?> (di luar transaksi unit rumah) kepada <?= htmlspecialchars($data['name_lengkap']) ?> pada tanggal <?= $tgl_pembayaran ?>
                        <?php else: ?>
                            Pembayaran <?= htmlspecialchars($data['jenis_pembayaran']) ?> pembelian <?= $satuan_properti ?> di <?= htmlspecialchars($nama_cluster) ?> Blok <?= htmlspecialchars($daftar_blok_txt ?: '-') ?> pada tanggal <?= $tgl_pembayaran ?>
                        <?php endif; ?>
                        <?php if ($catatan !== ''): ?>
                            <br>- (<?= htmlspecialchars($catatan) ?>)
                        <?php endif; ?>
                    </div>
                    <div class="blank-line"></div>
                </td>
            </tr>
        </table>

        <!-- FOOTER NOMINAL & TTD -->
        <div class="footer-row">
            <div class="nominal-wrapper">
                <span class="rp-text">Rp</span>
                <div class="nominal-box">
                    <?= number_format($data['jumlah_bayar'], 0, ',', '.') ?>
                </div>
            </div>

            <div class="ttd-wrapper">
                <div class="ttd-kota">
                    Sambas, <?= $tgl_pembayaran ?>
                </div>
                <br>
                <div class="ttd-nama">
                    <?php if ($is_non_unit): ?>
                        ( <?= htmlspecialchars($data['name_lengkap']) ?> )
                    <?php else: ?>
                        ( Hanapi, S.Tr.Kom )
                    <?php endif; ?>
                </div>
                <div class="ttd-jabatan">
                    <?= $is_non_unit ? 'Penerima' : 'Penerima / Administrator' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.onload = function() {
        window.print();
    }
</script>
</body>
</html>