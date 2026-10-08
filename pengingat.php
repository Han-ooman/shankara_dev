<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
include "includes/koneksi.php";
require_once __DIR__ . '/includes/push_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// ===== Filter =====
$fJenias = $_GET['jenis'] ?? 'semua';
if (!in_array($fJenias, ['semua', 'deadline', 'wawancara', 'akad', 'followup'], true)) $fJenias = 'semua';
$fStatus = $_GET['status'] ?? 'semua';
if (!in_array($fStatus, ['semua', 'telat', 'hari_ini', 'h3', 'h7'], true)) $fStatus = 'semua';

function reminder_status(int $sisa): array {
    if ($sisa < 0)  return ['label' => 'Telat', 'badge' => 'bg-danger'];
    if ($sisa === 0) return ['label' => 'Hari ini', 'badge' => 'bg-warning text-dark'];
    if ($sisa <= 3) return ['label' => 'H-' . $sisa, 'badge' => 'bg-danger'];
    return ['label' => 'H-' . $sisa, 'badge' => 'bg-primary'];
}

function reminder_wa_number(string $no): string {
    $no = preg_replace('/\D/', '', $no);
    if ($no === '') return '';
    if (str_starts_with($no, '0')) return '62' . substr($no, 1);
    if (str_starts_with($no, '8')) return '62' . $no;
    return $no;
}

function reminder_message(string $template, array $vars): string {
    $map = ['{nama}' => $vars['nama'] ?? '', '{unit}' => $vars['unit'] ?? '', '{cluster}' => $vars['cluster'] ?? '', '{tanggal}' => $vars['tanggal'] ?? '', '{sisa}' => $vars['sisa'] ?? '', '{bank}' => $vars['bank'] ?? ''];
    return strtr($template, $map);
}

function wa_link(string $no, string $message): string {
    $num = reminder_wa_number($no);
    if ($num === '') return '#';
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($message);
}

// ===== Kumpulkan item pengingat =====
$tpl_deadline = pengaturan_get($koneksi, 'pengingat_tpl_deadline', '');
$tpl_wawancara = pengaturan_get($koneksi, 'pengingat_tpl_wawancara', '');
$tpl_akad = pengaturan_get($koneksi, 'pengingat_tpl_akad', '');
$tpl_followup = pengaturan_get($koneksi, 'pengingat_tpl_followup', '');

$items = [];
$today = date('Y-m-d');
$t = strtotime($today);

function add_item(array &$items, string $jenis, string $nama, string $no, string $unit, string $cluster, ?string $tanggal, string $bank, string $tpl, string $label) {
    if (empty($tanggal) || $tanggal === '0000-00-00') return;
    $sisa = intdiv(strtotime($tanggal) - strtotime(date('Y-m-d')), 86400);
    if ($sisa > 7) return; // Hanya H-7 ke atas yang tampil
    $st = reminder_status($sisa);
    $items[] = [
        'jenis'   => $jenis,
        'label'   => $label,
        'nama'    => $nama,
        'no'      => $no,
        'unit'    => $unit,
        'cluster' => $cluster,
        'tanggal' => $tanggal,
        'sisa'    => $sisa,
        'bank'    => $bank,
        'st_label'=> $st['label'],
        'st_badge'=> $st['badge'],
        'tpl'     => $tpl,
        'wa'      => wa_link($no, reminder_message($tpl, [
            'nama' => $nama, 'unit' => $unit, 'cluster' => $cluster,
            'tanggal' => $tanggal !== '' ? date('d/m/Y', strtotime($tanggal)) : '',
            'sisa' => $sisa < 0 ? 'sudah telat ' . abs($sisa) . ' hari' : 'sisa ' . $sisa . ' hari', 'bank' => $bank,
        ])),
    ];
}

// 1. Deadline Pemberkasan (berkas belum lengkap)
$q1 = mysqli_query($koneksi, "SELECT * FROM view_database_utama
        WHERE deadline_berkas IS NOT NULL AND deadline_berkas != '' AND deadline_berkas != '0000-00-00'
          AND status_konsumen != 'BATAL' AND status_konsumen != 'ACC - AKAD'
          AND deadline_berkas <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY deadline_berkas ASC");
if ($q1) {
    while ($r = mysqli_fetch_assoc($q1)) {
        $saved = !empty($r['pemberkasan_json']) ? json_decode($r['pemberkasan_json'], true) : [];
        if (!is_array($saved)) $saved = [];
        // Lewati yang berkasnya sudah lengkap (deadline biasanya dikosongkan, tetapi amankan)
        add_item($items, 'deadline', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', ($r['cluster_name'] ?? '') . ' - Blok ' . ($r['no_blok'] ?? ''), $r['cluster_name'] ?? '', $r['deadline_berkas'], $r['bank_tujuan'] ?? '', $tpl_deadline, 'Deadline Pemberkasan');
    }
}

// 2. Wawancara Bank
$q2 = mysqli_query($koneksi, "SELECT * FROM view_database_utama
        WHERE tgl_wawancara IS NOT NULL AND tgl_wawancara != '' AND tgl_wawancara != '0000-00-00'
          AND status_konsumen != 'BATAL' AND status_konsumen != 'ACC - AKAD'
          AND tgl_wawancara <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY tgl_wawancara ASC");
if ($q2) {
    while ($r = mysqli_fetch_assoc($q2)) {
        add_item($items, 'wawancara', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', ($r['cluster_name'] ?? '') . ' - Blok ' . ($r['no_blok'] ?? ''), $r['cluster_name'] ?? '', $r['tgl_wawancara'], $r['bank_tujuan'] ?? '', $tpl_wawancara, 'Wawancara Bank');
    }
}

// 3. Akad (hanya yang belum ACC/AKAD selesai)
$q3 = mysqli_query($koneksi, "SELECT * FROM view_database_utama
        WHERE tanggal_akad IS NOT NULL AND tanggal_akad != '' AND tanggal_akad != '0000-00-00'
          AND status_konsumen NOT IN ('BATAL', 'ACC - AKAD')
          AND tanggal_akad <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY tanggal_akad ASC");
if ($q3) {
    while ($r = mysqli_fetch_assoc($q3)) {
        add_item($items, 'akad', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', ($r['cluster_name'] ?? '') . ' - Blok ' . ($r['no_blok'] ?? ''), $r['cluster_name'] ?? '', $r['tanggal_akad'], $r['bank_tujuan'] ?? '', $tpl_akad, 'Akad KPR');
    }
}

// 4. Follow-up Prospek
$q4 = mysqli_query($koneksi, "SELECT * FROM data_prospek
        WHERE tanggal_followup IS NOT NULL AND tanggal_followup != ''
          AND status_followup NOT IN ('Deal', 'Tidak Lanjut')
          AND tanggal_followup <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY tanggal_followup ASC");
if ($q4) {
    while ($r = mysqli_fetch_assoc($q4)) {
        add_item($items, 'followup', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', trim(($r['cluster_minat'] ?? '') . ' ' . ($r['unit_minat'] ?? '')), $r['cluster_minat'] ?? '', $r['tanggal_followup'], '', $tpl_followup, 'Follow-up Prospek');
    }
}

// Filter status
if ($fStatus !== 'semua' || $fJenias !== 'semua') {
    $items = array_values(array_filter($items, function ($it) use ($fStatus, $fJenias) {
        if ($fJenias !== 'semua' && $it['jenis'] !== $fJenias) return false;
        $s = $it['sisa'];
        if ($fStatus === 'telat') return $s < 0;
        if ($fStatus === 'hari_ini') return $s === 0;
        if ($fStatus === 'h3') return $s >= 1 && $s <= 3;
        if ($fStatus === 'h7') return $s >= 4 && $s <= 7;
        return true;
    }));
}

// Urutkan: yang telat/hari ini dulu
usort($items, function ($a, $b) {
    return $a['sisa'] <=> $b['sisa'];
});

$jml = count($items);
$jml_telat = count(array_filter($items, fn($i) => $i['sisa'] < 0));
$jml_hariIni = count(array_filter($items, fn($i) => $i['sisa'] === 0));
$jml_terdekat = count(array_filter($items, fn($i) => $i['sisa'] >= 1 && $i['sisa'] <= 7));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Pengingat - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .rem-badge-telat { background-color: #fee2e2; color: #dc2626; }
        .rem-badge-hariini { background-color: #fef9c3; color: #a16207; }
        .wa-btn { background-color: #25D366; border: none; color: #fff; }
        .wa-btn:hover { background-color: #1eb958; color: #fff; }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1">Pengingat & Notifikasi</h4>
                <p class="text-muted small mb-0">Follow-up otomatis H-7 hingga telat, lengkap dengan tombol WhatsApp.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-end" data-push-area>
                <span data-push-status class="small text-muted align-self-center"></span>
                <button type="button" data-push-subscribe class="btn text-white rounded-pill fw-semibold px-3 shadow-sm d-inline-flex" style="background-color: var(--primary-blue);">
                    <i class="bi bi-bell me-1"></i> Aktifkan Notifikasi
                </button>
                <button type="button" data-push-unsubscribe class="btn btn-outline-secondary rounded-pill fw-semibold px-3 d-none">
                    <i class="bi bi-bell-slash me-1"></i> Nonaktifkan
                </button>
                <button type="button" data-push-test class="btn btn-outline-primary rounded-pill fw-semibold px-3 d-none">
                    <i class="bi bi-send me-1"></i> Kirim Uji Push
                </button>
            </div>
        </div>

        <!-- STAT -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-3">
                        <div class="small text-muted">Total Pengingat</div>
                        <div class="fs-3 fw-bold"><?= $jml ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-3">
                        <div class="small text-danger fw-semibold">Telat</div>
                        <div class="fs-3 fw-bold text-danger"><?= $jml_telat ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-3">
                        <div class="small text-warning fw-semibold">Hari Ini</div>
                        <div class="fs-3 fw-bold text-warning"><?= $jml_hariIni ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-3">
                        <div class="small text-primary fw-semibold">Terdekat (1-7 hari)</div>
                        <div class="fs-3 fw-bold text-primary"><?= $jml_terdekat ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER -->
        <div class="content-card">
            <form method="GET" class="row g-2 mb-4 filter-section no-print align-items-center">
                <div class="col-md-3">
                    <select name="jenis" class="form-select filter-input shadow-none">
                        <option value="semua" <?= $fJenias === 'semua' ? 'selected' : '' ?>>Semua Jenis</option>
                        <option value="deadline" <?= $fJenias === 'deadline' ? 'selected' : '' ?>>Deadline Pemberkasan</option>
                        <option value="wawancara" <?= $fJenias === 'wawancara' ? 'selected' : '' ?>>Wawancara Bank</option>
                        <option value="akad" <?= $fJenias === 'akad' ? 'selected' : '' ?>>Akad KPR</option>
                        <option value="followup" <?= $fJenias === 'followup' ? 'selected' : '' ?>>Follow-up Prospek</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select filter-input shadow-none">
                        <option value="semua" <?= $fStatus === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="telat" <?= $fStatus === 'telat' ? 'selected' : '' ?>>Telat</option>
                        <option value="hari_ini" <?= $fStatus === 'hari_ini' ? 'selected' : '' ?>>Hari Ini</option>
                        <option value="h3" <?= $fStatus === 'h3' ? 'selected' : '' ?>>H-3 ke bawah (1-3)</option>
                        <option value="h7" <?= $fStatus === 'h7' ? 'selected' : '' ?>>H-7 (4-7)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm rounded-pill px-3" style="background-color: var(--primary-blue); color:#fff;">Terapkan</button>
                        <a href="pengingat.php" class="btn btn-outline-secondary rounded-pill btn-sm fw-medium"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableReminder">
                    <thead>
                        <tr class="text-muted">
                            <th style="width:50px;">No</th>
                            <th>Jenis</th>
                            <th>Konsumen</th>
                            <th>Unit / Keterangan</th>
                            <th>Tanggal</th>
                            <th>Sisa</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jml === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-calendar-check text-muted" style="font-size:2.5rem;"></i>
                                <div class="fw-semibold text-dark mt-2">Tidak ada pengingat</div>
                                <p class="text-muted small mb-0">Semua kegiatan dalam 7 hari ke depan sudah terpantau.</p>
                            </td>
                        </tr>
                        <?php else: $no = 1; foreach ($items as $it): ?>
                        <tr>
                            <td class="fw-semibold text-muted"><?= $no++ ?></td>
                            <td><span class="badge bg-light border text-dark"><?= htmlspecialchars($it['label']) ?></span></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($it['nama']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($it['no']) ?></div>
                            </td>
                            <td class="small"><?= htmlspecialchars($it['unit']) ?: '<span class="text-muted">-</span>' ?></td>
                            <td class="small fw-semibold"><?= date('d/m/Y', strtotime($it['tanggal'])) ?></td>
                            <td class="small"><?= $it['sisa'] < 0 ? '+' . abs($it['sisa']) . ' hari' : ($it['sisa'] === 0 ? 'Hari ini' : 'H-' . $it['sisa']) ?></td>
                            <td>
                                <span class="badge <?= $it['sisa'] < 0 ? 'rem-badge-telat' : ($it['sisa'] === 0 ? 'rem-badge-hariini' : 'bg-primary') ?> rounded-pill px-3 py-2"><?= htmlspecialchars($it['st_label']) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($it['wa'] !== '#'): ?>
                                <a href="<?= $it['wa'] ?>" target="_blank" rel="noopener" class="btn btn-sm wa-btn rounded-pill px-3" title="Kirim WhatsApp"><i class="bi bi-whatsapp me-1"></i> WA</a>
                                <?php else: ?>
                                <span class="text-muted small">No WA kosong</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/idle.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('#tableReminder tbody tr').forEach(function (row) {
                row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});
</script>
<?php include "includes/modal_hold.php"; ?>
<script src="assets/js/app.js"></script>
</body>
</html>