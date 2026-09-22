<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "../includes/cek_sesi.php";
include "../includes/koneksi.php";

$id = intval($_GET['id'] ?? 0);
if (!csrf_verify($_GET['token'] ?? '')) {
    http_response_code(403);
    die("Token keamanan tidak valid.");
}

$q = "SELECT g.*, k.nama_lengkap, k.jabatan, k.no_hp, k.alamat
      FROM data_gaji g
      LEFT JOIN data_karyawan k ON g.id_karyawan = k.id_karyawan
      WHERE g.id_gaji = $id";
$result = mysqli_query($koneksi, $q);
$data = $result ? mysqli_fetch_assoc($result) : null;
if (!$data) die("Data slip gaji tidak ditemukan.");

function fmt($n) { return number_format((float)$n, 0, '.', ','); }

function nama_periode($ym) {
    $b = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    $p = explode('-', $ym);
    return $b[$p[1]] . ' ' . $p[0];
}

$tunj_makan     = (float)$data['tunjangan_makan'];
$tunj_transport = (float)$data['tunjangan_transport'];
$tunj_jabatan   = (float)$data['tunjangan_jabatan'];
$pokok          = (float)$data['gaji_pokok'];
$bonus          = (float)$data['bonus'];
$potongan       = (float)$data['potongan'];
$total_kotor    = $pokok + $tunj_makan + $tunj_transport + $tunj_jabatan + $bonus;
$total_terima   = $total_kotor - $potongan;

$baris = [];
$baris[] = ['label' => 'Gaji Pokok', 'nilai' => $pokok, 'class' => ''];
if ($tunj_makan > 0)     $baris[] = ['label' => 'Uang Makan', 'nilai' => $tunj_makan, 'class' => ''];
if ($tunj_transport > 0) $baris[] = ['label' => 'Transport', 'nilai' => $tunj_transport, 'class' => ''];
if ($tunj_jabatan > 0)   $baris[] = ['label' => 'Tunjangan', 'nilai' => $tunj_jabatan, 'class' => ''];
if ($bonus > 0)          $baris[] = ['label' => 'Bonus / Insentif', 'nilai' => $bonus, 'class' => ''];
if ($potongan > 0)       $baris[] = ['label' => 'Potongan' . (!empty($data['keterangan_potongan']) ? ' (' . $data['keterangan_potongan'] . ')' : ''), 'nilai' => -$potongan, 'class' => 'potongan-row'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji <?= htmlspecialchars($data['nama_lengkap'] ?? '') ?> - <?= nama_periode($data['periode']) ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
        }

        .container {
            width: 900px;
            background-color: #ffffff;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* Tombol cetak (tidak ikut tercetak) */
        .toolbar { text-align: center; margin-bottom: 18px; }
        .toolbar button, .toolbar a {
            border: none; padding: 10px 24px; font-size: 14px; font-weight: 600;
            border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 0 4px;
        }
        .btn-print { background-color: #b81d1d; color: #fff; }
        .btn-back { background-color: #64748b; color: #fff; }
        .btn-close { background-color: #ef4444; color: #fff; }

        /* Header Layout */
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .company-info {
            display: flex;
            flex-direction: column;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .logo-icon {
            width: 62px;
            height: auto;
            object-fit: contain;
        }

        .company-name-title {
            font-size: 14px;
            font-weight: bold;
            color: #1a2b4c;
            letter-spacing: 0.5px;
        }

        .company-subtitle {
            font-size: 11px;
            font-weight: bold;
            color: #777777;
            letter-spacing: 1px;
        }

        .company-details {
            font-size: 13px;
            font-weight: bold;
            color: #1a2b4c;
            line-height: 1.4;
        }

        .employee-info {
            text-align: right;
        }

        .doc-title {
            font-size: 28px;
            font-weight: 900;
            color: #1a2b4c;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .period {
            font-size: 13px;
            font-weight: bold;
            color: #333333;
            margin-bottom: 20px;
        }

        /* Info Karyawan Tall */
        .info-table {
            margin-left: auto;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 0;
            font-size: 13px;
            font-weight: bold;
            color: #1a2b4c;
        }

        .info-table td.label {
            text-align: right;
            padding-right: 8px;
        }

        .info-table td.colon {
            padding-right: 8px;
        }

        .info-table td.value {
            text-align: left;
        }

        /* Earnings Table */
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        .salary-table th {
            background-color: #b81d1d;
            color: white;
            text-align: left;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: bold;
        }

        .salary-table td {
            padding: 12px 15px;
            font-size: 13px;
            font-weight: bold;
            color: #1a2b4c;
            border-bottom: 1px solid #f0f0f0;
        }

        .salary-table td.amount {
            text-align: right;
        }

        .salary-table tr.total-row td {
            background-color: #fde8e8;
            color: #b81d1d;
            font-size: 14px;
            font-weight: bold;
            border-bottom: none;
        }

        .salary-table tr.potongan-row td {
            color: #b81d1d;
        }

        /* Footer Section */
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 20px;
        }

        .approval-section {
            font-size: 13px;
            font-weight: bold;
            color: #1a2b4c;
        }

        .approval-title {
            margin-bottom: 80px;
        }

        .take-home-pay-box {
            border: 2px solid #5a738e;
            padding: 15px 30px;
            text-align: center;
            min-width: 220px;
        }

        .thp-title {
            font-size: 11px;
            font-weight: bold;
            color: #1a2b4c;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .thp-amount {
            font-size: 22px;
            font-weight: 900;
            color: #1a2b4c;
        }

        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { background: #fff; padding: 0; margin: 0; }
            .container { box-shadow: none; padding: 25px; width: 100%; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()" class="btn-print">Cetak / Simpan PDF</button>
    <a href="gaji.php" class="btn-back">Kembali</a>
    <button onclick="window.close()" class="btn-close">Tutup</button>
</div>

<div class="container">
    <div class="header">
        <div class="company-info">
            <div class="logo-section">
                <img src="format_bast/logo/logo_kop.png" alt="Logo Shankara" class="logo-icon">
                <div>
                    <div class="company-name-title">SHANKARA RESIDENCE</div>
                    <div class="company-subtitle">REAL ESTATE</div>
                </div>
            </div>
            <div class="company-details">
                PT. GOLDEN SHANKARA INDONESIA<br>
                KARTIASA<br>
                Sambas
            </div>
        </div>

        <div class="employee-info">
            <div class="doc-title">SLIP GAJI</div>
            <div class="period"><?= htmlspecialchars(nama_periode($data['periode'])) ?></div>
            <table class="info-table">
                <tr>
                    <td class="label">Nama</td>
                    <td class="colon">:</td>
                    <td class="value"><?= htmlspecialchars($data['nama_lengkap'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td class="label">Jabatan</td>
                    <td class="colon">:</td>
                    <td class="value"><?= htmlspecialchars($data['jabatan'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td class="label">Departemen</td>
                    <td class="colon">:</td>
                    <td class="value">Keuangan</td>
                </tr>
            </table>
        </div>
    </div>

    <table class="salary-table">
        <thead>
            <tr>
                <th colspan="2">Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($baris as $b): ?>
            <tr class="<?= $b['class'] ?>">
                <td><?= htmlspecialchars($b['label']) ?></td>
                <td class="amount"><?= fmt($b['nilai']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>TOTAL PENERIMAAN</td>
                <td class="amount"><?= fmt($total_kotor) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div class="approval-section">
            <div class="approval-title">Disetujui Oleh:</div>
            <div class="approver-name"><?= htmlspecialchars($data['nama_lengkap'] ?? '') ?></div>
        </div>

        <div class="take-home-pay-box">
            <div class="thp-title">TAKE HOME PAY</div>
            <div class="thp-amount"><?= fmt($total_terima) ?></div>
        </div>
    </div>
</div>

<script>
    window.onload = function () { setTimeout(function () { window.print(); }, 400); };
</script>
</body>
</html>