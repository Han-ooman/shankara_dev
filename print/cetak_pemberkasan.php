<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "../includes/cek_sesi.php";
include "../includes/koneksi.php";
include "../includes/berkas_helper.php";

// Ambil parameter filter (dari URL yang dibuat tombol Export PDF)
// Column indices (setelah penambahan kolom checkbox):
// 1=Konsumen, 2=Cluster, 3=UnitSkema, 5=Kelengkapan, 6=StatusBank, 7=BankTujuan, 8=Deadline, 9=Wawancara
$q = [];
foreach ([1, 3, 5, 8, 9] as $c) { $q['q' . $c] = trim($_GET['q' . $c] ?? ''); }
$m = [];
foreach ([2, 6, 7] as $c) {
    $raw = $_GET['c' . $c] ?? '';
    $m['c' . $c] = $raw !== '' ? array_values(array_filter(array_map('trim', explode(',', $raw)))) : [];
}

// Kumpulkan data yang cocok dengan filter
$query = "SELECT * FROM view_database_utama ORDER BY id_transaksi DESC";
$result = mysqli_query($koneksi, $query);
$rows = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $skema = $row['skema_bayar'] ?? 'Cash';
        $pekerjaan = $row['pekerjaan'] ?? '';
        $saved_docs = !empty($row['pemberkasan_json']) ? json_decode($row['pemberkasan_json'], true) : [];
        if (!is_array($saved_docs)) $saved_docs = [];

        $progres = hitungProgresBerkas($saved_docs, $skema, $master_dokumen);
        $total_required = $progres['total'];
        $total_checked = $progres['checked'];
        $persen = $progres['persen'];
        $lengkap = $progres['lengkap'];

        $status_bank = $row['status_bank'] ?? 'Pemberkasan';
        $bank_tujuan = trim($row['bank_tujuan'] ?? '');
        if ($bank_tujuan === '') $bank_tujuan = '-';

        // Deadline & Wawancara display (untuk pencarian & tampilan)
        $deadline = $row['deadline_berkas'] ?? null;
        if ($lengkap) {
            $deadline_display = 'Berkas Lengkap';
        } elseif (!empty($deadline) && $deadline != '0000-00-00') {
            $deadline_display = date('d M Y', strtotime($deadline));
        } else {
            $deadline_display = '-';
        }

        $tgl_ww = $row['tgl_wawancara'] ?? null;
        if (!empty($tgl_ww) && $tgl_ww != '0000-00-00') {
            $wawancara_display = date('d M Y', strtotime($tgl_ww));
        } else {
            $wawancara_display = '-';
        }

        // ---- Filter ----
        $match = true;

        if ($q['q1'] !== '') {
            $needle = strtolower($q['q1']);
            $hay = strtolower(($row['name_lengkap'] ?? '') . ' ' . ($row['no_whatsapp'] ?? ''));
            if (strpos($hay, $needle) === false) $match = false;
        }
        if (!empty($m['c2'])) {
            $v = strtolower(trim($row['cluster_name'] ?? 'Shankara Residence'));
            if (!in_array($v, array_map('strtolower', $m['c2']))) $match = false;
        }
        if ($q['q3'] !== '') {
            $needle = strtolower($q['q3']);
            $hay = strtolower('Blok ' . ($row['no_blok'] ?? '-') . ' ' . $skema);
            if (strpos($hay, $needle) === false) $match = false;
        }
        if ($q['q5'] !== '') {
            $needle = strtolower($q['q5']);
            $hay = strtolower($total_checked . '/' . $total_required . ' (' . $persen . '%)' . ($lengkap ? ' Berkas Lengkap' : ' Progres Kelengkapan Dokumen'));
            if (strpos($hay, $needle) === false) $match = false;
        }
        if (!empty($m['c6'])) {
            $v = strtolower(trim($status_bank));
            if (!in_array($v, array_map('strtolower', $m['c6']))) $match = false;
        }
        if (!empty($m['c7'])) {
            $v = strtolower($bank_tujuan);
            if (!in_array($v, array_map('strtolower', $m['c7']))) $match = false;
        }
        if ($q['q8'] !== '') {
            $needle = strtolower($q['q8']);
            if (strpos(strtolower($deadline_display), $needle) === false) $match = false;
        }
        if ($q['q9'] !== '') {
            $needle = strtolower($q['q9']);
            if (strpos(strtolower($wawancara_display), $needle) === false) $match = false;
        }

        if ($match) {
            $rows[] = array_merge($row, [
                'skema' => $skema,
                'persen' => $persen,
                'checked' => $total_checked,
                'total' => $total_required,
                'lengkap' => $lengkap,
                'status_bank' => $status_bank,
                'bank_tujuan' => $bank_tujuan,
                'deadline_display' => $deadline_display,
                'wawancara_display' => $wawancara_display,
            ]);
        }
    }
}

function label_filter($label, $values) {
    if (empty($values)) return '';
    return '<span class="fchip"><strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars(implode(', ', $values)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Pemberkasan KPR - Shankara Dev</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            background-color: #f4f7f5;
            padding: 10px 0;
            margin: 0;
            color: #111;
        }

        .no-print-btn { text-align: center; margin-bottom: 15px; }

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
        .btn-back {
            background-color: #64748b;
            color: #fff;
            border: none;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 8px;
            text-decoration: none;
            display: inline-block;
        }

        .sheet {
            background: #fff;
            border: 2px solid #1e3a8a;
            border-radius: 12px;
            padding: 20px 24px;
            margin: 0 auto 18px;
            max-width: 297mm;
        }

        .sheet-title {
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 4px;
            border-bottom: 3px double #1e3a8a;
            padding-bottom: 8px;
        }
        .sheet-sub {
            text-align: center;
            font-size: 11px;
            color: #475569;
            margin-bottom: 12px;
        }

        .filter-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
            padding: 8px 10px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            font-size: 11px;
        }
        .filter-summary .fchip {
            background: #fff;
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            padding: 3px 10px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #1e3a8a;
            padding: 5px 6px;
            text-align: left;
            vertical-align: middle;
        }
        .data-table th {
            background: #eff6ff;
            color: #1e3a8a;
            font-weight: 700;
            text-align: center;
        }
        .data-table .center { text-align: center; }
        .data-table .right { text-align: right; }

        .b-status { padding: 2px 8px; border-radius: 10px; font-weight: 700; color: #fff; display: inline-block; font-size: 9.5px; }
        .b-status.gray   { background: #6c757d; }
        .b-status.info   { background: #0dcaf0; color: #1e3a8a; }
        .b-status.blue   { background: #0d6efd; }
        .b-status.green  { background: #2563eb; }
        .b-status.red    { background: #dc3545; }

        .deadline-ok    { font-weight: 700; color: #111; }
        .deadline-lewat { font-weight: 700; color: #dc3545; }
        .deadline-sisa  { font-weight: 700; color: #b45309; }
        .lengkap-text   { font-weight: 700; color: #2563eb; }

        .footer-note {
            margin-top: 12px;
            font-size: 10px;
            color: #475569;
            text-align: right;
        }

        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            html, body { background-color: #fff; padding: 0 !important; margin: 0 !important; }
            .sheet { box-shadow: none; border: 2px solid #1e3a8a; margin: 0 !important; }
            .no-print-btn { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print-btn">
    <button onclick="window.print()" class="btn-print">Cetak / Simpan PDF</button>
    <a href="pemberkasan.php" class="btn-back">Kembali</a>
</div>

<div class="sheet">
    <div class="sheet-title">Laporan Tracking Pemberkasan KPR</div>
    <div class="sheet-sub">Shankara Dev &bull; Dicetak <?= date('d M Y H:i') ?> &bull; <?= count($rows) ?> data</div>

    <?php
    $fchips = [];
    if ($q['q1'] !== '') $fchips[] = label_filter('Konsumen', [$q['q1']]);
    if (!empty($m['c2'])) $fchips[] = label_filter('Cluster', $m['c2']);
    if ($q['q3'] !== '') $fchips[] = label_filter('Unit & Skema', [$q['q3']]);
    if ($q['q5'] !== '') $fchips[] = label_filter('Kelengkapan', [$q['q5']]);
    if (!empty($m['c6'])) $fchips[] = label_filter('Status Bank', $m['c6']);
    if (!empty($m['c7'])) $fchips[] = label_filter('Bank Tujuan', $m['c7']);
    if ($q['q8'] !== '') $fchips[] = label_filter('Deadline', [$q['q8']]);
    if ($q['q9'] !== '') $fchips[] = label_filter('Wawancara', [$q['q9']]);
    if (!empty($fchips)): ?>
        <div class="filter-summary"><strong style="margin-right:2px;">Filter Aktif:</strong> <?= implode('', $fchips) ?></div>
    <?php endif; ?>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:24px;">No</th>
                <th>Konsumen</th>
                <th>Cluster</th>
                <th>Unit &amp; Skema</th>
                <th>Kelengkapan</th>
                <th>Status Bank</th>
                <th>Bank Tujuan</th>
                <th>Deadline Berkas</th>
                <th>Tgl Wawancara</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="center" style="padding:18px; color:#64748b;">Tidak ada data yang cocok dengan filter.</td></tr>
        <?php else: $no = 1; foreach ($rows as $rw): ?>
            <?php
                $cls = 'gray';
                if ($rw['status_bank'] == 'Wawancara') $cls = 'info';
                elseif ($rw['status_bank'] == 'OTS') $cls = 'blue';
                elseif ($rw['status_bank'] == 'ACC SP3K') $cls = 'green';
                elseif ($rw['status_bank'] == 'Ditolak') $cls = 'red';

                $dl_cls = '';
                if ($rw['lengkap']) {
                    $dl_cls = 'lengkap-text';
                } elseif (in_array($rw['deadline_display'], ['-', 'Berkas Lengkap'])) {
                    $dl_cls = '';
                } else {
                    $sisa = floor((strtotime($rw['deadline_berkas']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24));
                    $dl_cls = ($sisa < 0) ? 'deadline-lewat' : (($sisa <= 3) ? 'deadline-sisa' : 'deadline-ok');
                }
            ?>
            <tr>
                <td class="center"><?= $no++ ?></td>
                <td>
                    <strong><?= htmlspecialchars($rw['name_lengkap'] ?? '-') ?></strong><br>
                    <span style="color:#475569; font-size:9.5px;"><?= htmlspecialchars($rw['no_whatsapp'] ?? '-') ?></span>
                </td>
                <td><?= htmlspecialchars($rw['cluster_name'] ?? 'Shankara Residence') ?></td>
                <td>
                    Blok <?= htmlspecialchars($rw['no_blok'] ?? '-') ?><br>
                    <span style="color:#475569; font-size:9.5px;"><?= htmlspecialchars($rw['skema']) ?></span>
                </td>
                <td class="center">
                    <strong><?= $rw['checked'] ?>/<?= $rw['total'] ?> (<?= $rw['persen'] ?>%)</strong><br>
                    <span style="font-size:9px; color:#475569;"><?= $rw['lengkap'] ? 'Berkas Lengkap' : 'Progres Dokumen' ?></span>
                </td>
                <td class="center"><span class="b-status <?= $cls ?>"><?= htmlspecialchars($rw['status_bank']) ?></span></td>
                <td class="center"><?= $rw['bank_tujuan'] == '-' ? '<span style="color:#94a3b8;">-</span>' : htmlspecialchars($rw['bank_tujuan']) ?></td>
                <td class="center <?= $dl_cls ?>"><?= htmlspecialchars($rw['deadline_display']) ?></td>
                <td class="center"><?= htmlspecialchars($rw['wawancara_display']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="footer-note">Dokumen ini dihasilkan otomatis oleh Sistem Database Shankara Dev.</div>
</div>

<script>
    window.onload = function () {
        setTimeout(function () { window.print(); }, 400);
    };
</script>
</body>
</html>
