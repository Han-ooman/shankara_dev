<?php
require_once __DIR__ . '/../includes/session_db.php'; // sesi via DB (Vercel-safe)

session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../login.php");
    exit();
}
include __DIR__ . "/../includes/cek_sesi.php";

include __DIR__ . "/../includes/koneksi.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = sdb_query($koneksi, "SELECT b.*, 
                                       k.name_lengkap, k.no_ktp, k.pekerjaan, k.no_whatsapp, k.alamat_ktp,
                                       r.no_blok, r.tipe_rumah, r.luas_bangunan, r.luas_tanah,
                                       c.cluster_name,
                                       c.lokasi AS alamat_cluster,
                                       t.skema_bayar, t.bank_tujuan, t.tanggal_akad
                                FROM data_bast b
                                INNER JOIN data_transaksi t ON b.id_transaksi = t.id_transaksi
                                INNER JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                INNER JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                WHERE b.id_bast = '$id'");

$data = sdb_fetch_assoc($query);

if (!$data) {
    die("Data BAST tidak ditemukan.");
}

$nama_cluster  = !empty($data['cluster_name']) ? strtoupper($data['cluster_name']) : 'SHANKARA RESIDENCE';
$alamat_rumah  = !empty($data['alamat_cluster']) ? $data['alamat_cluster'] : 'Jl. Raya Poros Kartiasa, Komplek Shankara Residence';

function tgl_indo($tanggal) {
    $bulan = array (
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

function hari_indo($tanggal) {
    $hari = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    return $hari[(int)date('w', strtotime($tanggal))];
}

$tgl_serah  = tgl_indo($data['tgl_serah_terima']);
$hari_serah = hari_indo($data['tgl_serah_terima']);
$nama_konsumen = !empty($data['name_lengkap']) ? $data['name_lengkap'] : '_____________________';
$no_ktp = !empty($data['no_ktp']) ? $data['no_ktp'] : '_____________________';
$alamat_ktp = !empty($data['alamat_ktp']) ? $data['alamat_ktp'] : '________________________________________';
$tipe_rumah = !empty($data['tipe_rumah']) ? strtoupper($data['tipe_rumah']) : '______';
$luas_bangunan = !empty($data['luas_bangunan']) ? $data['luas_bangunan'] . ' m²' : '______ m²';
$luas_tanah = !empty($data['luas_tanah']) ? $data['luas_tanah'] . ' m²' : '______ m²';

// ===== Jenis pembelian: CASH atau KREDIT (berdasarkan skema_bayar transaksi) =====
$skema_bayar = strtoupper(trim((string)($data['skema_bayar'] ?? '')));
$adalah_kredit = (str_contains($skema_bayar, 'KPR') || str_contains($skema_bayar, 'KREDIT'));
$adalah_cash   = (str_contains($skema_bayar, 'CASH') || str_contains($skema_bayar, 'TUNAI'));
$tipe_beli     = $adalah_kredit ? 'KREDIT' : ($adalah_cash ? 'CASH' : 'CASH');
$adalah_tempo  = (str_contains($skema_bayar, 'TEMPO') || str_contains($skema_bayar, 'BERTAHAP'));

// Label skema pembayaran pada dokumen
$label_bayar = '';
if ($adalah_kredit) { $label_bayar = 'Kredit (KPR)'; }
elseif (str_contains($skema_bayar, 'TEMPO')) { $label_bayar = 'Cash Tempo (Angsuran)'; }
elseif (str_contains($skema_bayar, 'BERTAHAP')) { $label_bayar = 'Cash Bertahap (Angsuran)'; }
else if ($adalah_cash) { $label_bayar = 'Cash Keras (Lunas)'; }

// Kalimat dasar pembelian (menggantikan kalimat KPR statis)
$kalimat_dasar = $adalah_kredit
    ? 'Berdasarkan perjanjian kredit pemilikan rumah (KPR)'
    . (empty($data['bank_tujuan']) ? '' : ' melalui bank ' . $data['bank_tujuan'])
    . ' dengan nomor <strong>' . htmlspecialchars($data['no_bast']) . '</strong>'
    : 'Berdasarkan perjanjian jual beli rumah secara' . ($adalah_tempo ? ' cash tempo (angsuran)' : ' cash keras (lunas)')
    . ' dengan nomor <strong>' . htmlspecialchars($data['no_bast']) . '</strong>';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <link rel="icon" type="image/png" href="../assets/images/favicon/favicon.png">    <title>BAST_<?= htmlspecialchars($data['no_bast']) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', 'Segoe UI', serif;
            background-color: #f4f7f5;
            margin: 0;
            padding: 10px 0 0 0;
            color: #000;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .bast-card {
            background: #fff;
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 45px;
            border: 1px solid #d1d5db;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        /* HEADER PERUSAHAAN */
        .header-company {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header-company .kop-logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .header-company .kop-text {
            text-align: center;
            flex: 1;
        }

        .header-company h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 1px;
        }

        .header-company p {
            font-size: 12px;
            margin: 3px 0 0 0;
            font-style: italic;
        }

        /* JUDUL DOKUMEN */
        .doc-title {
            text-align: center;
            margin: 16px 0 6px 0;
        }

        .doc-title h2 {
            font-size: 16px;
            font-weight: 700;
            text-decoration: underline;
            margin: 0;
            letter-spacing: 1px;
        }

        .doc-title .no-doc {
            font-size: 13px;
            margin-top: 2px;
        }

        .doc-body {
            margin-top: 14px;
            font-size: 13px;
            line-height: 1.6;
            text-align: justify;
        }

        .pihak-title {
            margin: 12px 0 2px 0;
            font-weight: 700;
            font-size: 13px;
        }

        table.pihak {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table.pihak td {
            padding: 2px 4px;
            vertical-align: top;
        }

        table.pihak .lbl {
            width: 90px;
        }

        table.pihak .col {
            width: 12px;
        }

        .detail-unit {
            margin: 10px 0;
            font-size: 13px;
        }

        .detail-unit td {
            padding: 1px 4px;
            vertical-align: top;
        }

        .detail-unit .lbl {
            width: 180px;
        }

        .detail-unit .col {
            width: 12px;
        }

        .pasal {
            margin-top: 12px;
        }

        .pasal h3 {
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 2px 0;
        }

        .pasal p {
            margin: 0 0 4px 0;
        }

        .sign-wrapper {
            display: flex;
            justify-content: space-between;
            margin-top: 45px;
        }

        .sign-block {
            width: 42%;
            text-align: center;
            font-size: 13px;
            line-height: 1.6;
        }

        .sign-space {
            height: 65px;
        }

        .sign-name {
            font-weight: 700;
            text-decoration: underline;
        }

        .no-print-btn {
            text-align: center;
            margin-bottom: 15px;
        }

        .btn-print {
            background-color: #1e3a8a;
            color: #fff;
            border: none;
            padding: 10px 26px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            html, body {
                background: #fff;
                padding: 0 !important;
                margin: 0 !important;
            }
            .bast-card {
                box-shadow: none;
                border: none;
                max-width: 100%;
                padding: 0;
            }
            .no-print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="no-print-btn">
    <button onclick="window.print()" class="btn-print">&#128424; Cetak / Simpan PDF</button>
</div>

<div class="bast-card">
    <!-- HEADER PERUSAHAAN -->
    <div class="header-company">
        <img src="../format_bast/logo/logo_kop.png" alt="Logo Shankara Group" class="kop-logo">
        <div class="kop-text">
            <h1>PT. GOLDEN SHANKARA INDONESIA</h1>
            <p>Alamat: Jalan Lintas Kalimantan Poros Utara Kartiasa No. 86, Sambas-Kalimantan Barat</p>
        </div>
    </div>

    <!-- JUDUL -->
    <div class="doc-title">
        <h2>SURAT SERAH TERIMA KUNCI RUMAH</h2>
        <div class="no-doc">Nomor: <?= htmlspecialchars($data['no_bast']) ?></div>
    </div>

    <!-- ISI -->
    <div class="doc-body">
        <p>Yang bertanda tangan di bawah ini:</p>

        <div class="pihak-title">Pihak Pertama</div>
        <table class="pihak">
            <tr>
                <td class="lbl">Nama</td>
                <td class="col">:</td>
                <td>Akramin, S.H., M.H</td>
            </tr>
            <tr>
                <td class="lbl">Alamat</td>
                <td class="col">:</td>
                <td>Dusun Sukamantri Rt/Rw 011/003, Kel/Des Dalam Kaum, Kec. Sambas</td>
            </tr>
            <tr>
                <td class="lbl">No KTP</td>
                <td class="col">:</td>
                <td>6101011811940001</td>
            </tr>
        </table>

        <div class="pihak-title">Pihak Kedua</div>
        <table class="pihak">
            <tr>
                <td class="lbl">Nama</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($nama_konsumen) ?></td>
            </tr>
            <tr>
                <td class="lbl">Alamat</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($alamat_ktp) ?></td>
            </tr>
            <tr>
                <td class="lbl">No. KTP</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($no_ktp) ?></td>
            </tr>
        </table>

        <p style="margin-top:8px;">
            <?= $kalimat_dasar ?>
            antara <strong>PT. Golden Shankara Indonesia</strong> dan Pihak Kedua, bersama ini Pihak Pertama sebagai pihak
            pengembang menyerahkan kunci rumah kepada Pihak Kedua sebagai pemilik rumah dengan detail sebagai berikut:
        </p>

        <table class="detail-unit">
            <tr>
                <td class="lbl">Alamat Rumah</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($alamat_rumah) ?> (Blok <?= htmlspecialchars($data['no_blok']) ?>)</td>
            </tr>
            <tr>
                <td class="lbl">Tipe Rumah</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($tipe_rumah) ?></td>
            </tr>
            <tr>
                <td class="lbl">Luas Bangunan</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($luas_bangunan) ?></td>
            </tr>
            <tr>
                <td class="lbl">Luas Tanah</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($luas_tanah) ?></td>
            </tr>
            <tr>
                <td class="lbl">Pembayaran</td>
                <td class="col">:</td>
                <td><?= htmlspecialchars($label_bayar !== '' ? $tipe_beli . ($label_bayar !== $tipe_beli ? ' - ' . $label_bayar : '') : '______') ?></td>
            </tr>
        </table>

        <div class="pasal">
            <h3>Pasal 1</h3>
            <p><strong>Keadaan Rumah</strong></p>
            <p>Pihak Pertama menyatakan bahwa rumah dalam kondisi baik, sesuai dengan spesifikasi yang disepakati dalam perjanjian jual beli. Pihak Kedua telah memeriksa dan menerima rumah dalam keadaan sebagaimana adanya pada tanggal serah terima ini.</p>
        </div>

        <div class="pasal">
            <h3>Pasal 2</h3>
            <p><strong>Hak dan Kewajiban</strong></p>
            <p style="margin:0;">
                1. Setelah serah terima ini, Pihak Kedua bertanggung jawab sepenuhnya atas pemeliharaan, keamanan, dan kebersihan rumah.<br>
                2. Pihak Pertama bertanggung jawab terhadap kerusakan konstruksi (jika ada) sesuai dengan masa garansi yang telah ditentukan dalam perjanjian (3 bulan).
            </p>
        </div>

        <?php if ($tipe_beli === 'CASH'): ?>
        <div class="pasal">
            <h3>Pasal 3</h3>
            <p><strong>Status Pembayaran (Pembelian CASH)</strong></p>
            <p style="margin:0;">
                1. Pihak Kedua menyatakan bahwa pembayaran pembelian rumah ini telah dilunasi secara<?= $adalah_tempo ? ' cash tempo (angsuran) dan seluruh angsuran telah dibayar' : ' cash keras (lunas)' ?>.<br>
                2. Pihak Pertama menyatakan telah menerima pembayaran secara penuh dan tidak ada tagihan atau kewajiban pembayaran yang tersisa.<br>
                3. Bukti pelunasan pembayaran telah diserahkan kepada Pihak Kedua.
            </p>
        </div>

        <div class="pasal">
            <h3>Pasal 4</h3>
            <p><strong>Penyerahan Kunci</strong></p>
            <p>Pada hari ini, <strong><?= $hari_serah ?></strong>, <?= $tgl_serah ?>, Pihak Pertama menyerahkan kunci rumah kepada Pihak Kedua yang menandakan berakhirnya kewajiban Pihak Pertama atas penyerahan fisik rumah kepada Pihak Kedua.</p>
        </div>
        <?php else: ?>
        <div class="pasal">
            <h3>Pasal 3</h3>
            <p><strong>Status Pembayaran (Pembelian KREDIT)</strong></p>
            <p style="margin:0;">
                1. Pembelian rumah ini dilakukan secara kredit melalui perjanjian Kredit Pemilikan Rumah (KPR)<?= empty($data['bank_tujuan']) ? '' : ' pada bank ' . $data['bank_tujuan'] ?>.<br>
                2. Pihak Kedua wajib melunasi seluruh kewajiban pembayaran kepada bank pemberi kredit sesuai jadwal yang telah disepakati.<br>
                3. Pihak Pertama tidak bertanggung jawab atas sengketa maupun keterlambatan pembayaran yang terjadi antara Pihak Kedua dan bank pemberi kredit.<br>
                4. Apabila terjadi pembatalan kredit atau wanprestasi oleh Pihak Kedua, maka penyelesaianya mengikuti peraturan yang berlaku pada perjanjian kredit dan ketentuan hukum.
            </p>
        </div>

        <div class="pasal">
            <h3>Pasal 4</h3>
            <p><strong>Penyerahan Kunci</strong></p>
            <p>Pada hari ini, <strong><?= $hari_serah ?></strong>, <?= $tgl_serah ?>, Pihak Pertama menyerahkan kunci rumah kepada Pihak Kedua yang menandakan berakhirnya kewajiban Pihak Pertama atas penyerahan fisik rumah kepada Pihak Kedua.</p>
        </div>
        <?php endif; ?>

        <p>
            Demikian surat serah terima ini dibuat dan ditandatangani oleh kedua belah pihak dalam keadaan sadar tanpa adanya paksaan dari pihak manapun.
        </p>
    </div>

    <!-- TANDA TANGAN -->
    <div style="text-align: right; font-size: 13px; margin-top: 20px;">
        Sambas, <?= date('d-m-Y', strtotime($data['tgl_serah_terima'])) ?>
    </div>
    <div class="sign-wrapper">
        <div class="sign-block">
            <div><strong>Pihak I</strong></div>
            <div class="sign-space"></div>
            <div class="sign-name">Akramin, S.H., M.H</div>
        </div>
        <div class="sign-block">
            <div><strong>Pihak II</strong></div>
            <div class="sign-space"></div>
            <div class="sign-name"><?= htmlspecialchars($nama_konsumen) ?></div>
        </div>
    </div>
</div>

</body>
</html>
