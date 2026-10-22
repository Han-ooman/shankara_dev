<?php
include "includes/koneksi.php";
require_once __DIR__ . '/includes/helper_log.php';

session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

$doneBphtb   = 'Sudah Dikerjakan - Data Masuk';
$doneTapera  = 'Sudah Daftar - Sudah Pengajuan KPR';
$doneTrilogi = 'Sudah Trilogi';
$doneSikumbang = 'Sudah Hijau';

// ==========================================
// LOGIKA UPDATE STATUS KPR (modal checkbox, tunggal & massal)
// ==========================================
if (isset($_POST['update_status_kpr'])) {
    $raw_ids = $_POST['ids'] ?? '';
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)$raw_ids)), function ($v) { return $v > 0; }));

    $val = [
        'bphtb'   => isset($_POST['chk_bphtb']) ? $doneBphtb : null,
        'tapera'  => isset($_POST['chk_tapera']) ? $doneTapera : null,
        'trilogi' => isset($_POST['chk_trilogi']) ? $doneTrilogi : null,
        'sikumbang' => isset($_POST['chk_sikumbang']) ? $doneSikumbang : null,
    ];

    if (!empty($ids)) {
        $stmt = sdb_prepare($koneksi, "UPDATE data_transaksi SET
                                    status_bphtb = ?, status_tapera = ?, status_trilogi = ?, status_sikumbang = ?
                                    WHERE id_transaksi = ?");
        if ($stmt) {
            foreach ($ids as $id) {
                sdb_stmt_bind_param($stmt, 'ssssi', $val['bphtb'], $val['tapera'], $val['trilogi'], $val['sikumbang'], $id);
                sdb_stmt_execute($stmt);
            }
            sdb_stmt_close($stmt);
        }
        log_aktivitas($koneksi, 'UBAH', 'Status KPR', 'Update status KPR ' . count($ids) . ' transaksi (#' . implode(',', $ids) . ')', $val);
    }

    $allowedParams = ['k', 'f', 'f_cluster', 'f_status', 'f_skema', 'f_akad_dari', 'f_akad_sampai', 'f_bphtb', 'f_tapera', 'f_trilogi', 'f_sikumb'];
    parse_str((string)($_POST['redirect'] ?? ''), $q);
    $keep = [];
    foreach ($allowedParams as $key) {
        if (isset($q[$key]) && $q[$key] !== '') { $keep[$key] = $q[$key]; }
    }
    $qs = http_build_query($keep);
    header("Location: status_kpr.php" . ($qs !== '' ? '?' . $qs : ''));
    exit();
}

// ==========================================
// FILTER LENGKAP & PINTAR
// ==========================================
$keyword = isset($_GET['k']) ? sdb_real_escape_string($koneksi, $_GET['k']) : '';
$filterF = isset($_GET['f']) ? $_GET['f'] : '';
$filterF = in_array($filterF, ['bphtb', 'tapera', 'trilogi', 'sikumbang']) ? $filterF : '';

// Filter tahap KPR per modul
$filterBphtb   = in_array($_GET['f_bphtb']   ?? '', ['belum', 'sudah']) ? $_GET['f_bphtb']   : '';
$filterTapera  = in_array($_GET['f_tapera']  ?? '', ['belum', 'sudah']) ? $_GET['f_tapera']  : '';
$filterTrilogi = in_array($_GET['f_trilogi'] ?? '', ['belum', 'sudah']) ? $_GET['f_trilogi'] : '';
$filterSikumb  = in_array($_GET['f_sikumb']  ?? '', ['belum', 'sudah']) ? $_GET['f_sikumb']  : '';

// Filter tambahan
$filterCluster   = isset($_GET['f_cluster'])   ? sdb_real_escape_string($koneksi, $_GET['f_cluster'])   : '';
$filterStatusK   = isset($_GET['f_status'])    ? sdb_real_escape_string($koneksi, $_GET['f_status'])    : '';
$filterSkema     = isset($_GET['f_skema'])     ? sdb_real_escape_string($koneksi, $_GET['f_skema'])     : '';
$filterAkadDari  = isset($_GET['f_akad_dari']) ? sdb_real_escape_string($koneksi, $_GET['f_akad_dari']) : '';
$filterAkadSampai= isset($_GET['f_akad_sampai']) ? sdb_real_escape_string($koneksi, $_GET['f_akad_sampai']) : '';

$whereBase = "t.status_konsumen != 'BATAL' AND (t.skema_bayar = 'KPR Subsidi' OR r.tipe_rumah = '36') AND t.skema_bayar NOT IN ('Cash Tempo', 'Cash Keras')";

if ($keyword !== '') {
    $whereBase .= " AND (k.name_lengkap LIKE '%$keyword%' OR r.no_blok LIKE '%$keyword%' OR c.cluster_name LIKE '%$keyword%')";
}
if ($filterCluster) {
    $whereBase .= " AND c.cluster_name = '$filterCluster'";
}
if ($filterStatusK) {
    $whereBase .= " AND t.status_konsumen = '$filterStatusK'";
}
if ($filterSkema) {
    $whereBase .= " AND t.skema_bayar = '$filterSkema'";
}
if ($filterAkadDari) {
    $whereBase .= " AND t.tanggal_akad >= '$filterAkadDari'";
}
if ($filterAkadSampai) {
    $whereBase .= " AND t.tanggal_akad <= '$filterAkadSampai'";
}

// Filter tahap KPR (sudah/belum) - ditambahkan ke whereBase
if ($filterBphtb === 'belum')   { $whereBase .= " AND (t.status_bphtb = 'Belum Dikerjakan' OR t.status_bphtb IS NULL OR t.status_bphtb = '')"; }
elseif ($filterBphtb === 'sudah') { $whereBase .= " AND NOT (t.status_bphtb = 'Belum Dikerjakan' OR t.status_bphtb IS NULL OR t.status_bphtb = '')"; }
if ($filterTapera === 'belum')  { $whereBase .= " AND (t.status_tapera = 'Belum Daftar' OR t.status_tapera IS NULL OR t.status_tapera = '')"; }
elseif ($filterTapera === 'sudah'){ $whereBase .= " AND NOT (t.status_tapera = 'Belum Daftar' OR t.status_tapera IS NULL OR t.status_tapera = '')"; }
if ($filterTrilogi === 'belum') { $whereBase .= " AND (t.status_trilogi = 'Belum Diaplikasi' OR t.status_trilogi IS NULL OR t.status_trilogi = '')"; }
elseif ($filterTrilogi === 'sudah'){ $whereBase .= " AND NOT (t.status_trilogi = 'Belum Diaplikasi' OR t.status_trilogi IS NULL OR t.status_trilogi = '')"; }
if ($filterSikumb === 'belum')  { $whereBase .= " AND (t.status_sikumbang = 'Belum' OR t.status_sikumbang IS NULL OR t.status_sikumbang = '')"; }
elseif ($filterSikumb === 'sudah'){ $whereBase .= " AND NOT (t.status_sikumbang = 'Belum' OR t.status_sikumbang IS NULL OR t.status_sikumbang = '')"; }

// Legacy filter f (BPHTB/Tapera/Trilogi/Sikumbang Belum) - backward compat
$whereFilt = '';
if ($filterF === 'bphtb')      { $whereFilt = " AND (status_bphtb = 'Belum Dikerjakan' OR status_bphtb IS NULL OR status_bphtb = '')"; }
elseif ($filterF === 'tapera') { $whereFilt = " AND (status_tapera = 'Belum Daftar' OR status_tapera IS NULL OR status_tapera = '')"; }
elseif ($filterF === 'trilogi'){ $whereFilt = " AND (status_trilogi = 'Belum Diaplikasi' OR status_trilogi IS NULL OR status_trilogi = '')"; }
elseif ($filterF === 'sikumbang'){ $whereFilt = " AND (status_sikumbang = 'Belum' OR status_sikumbang IS NULL OR status_sikumbang = '')"; }

// Dedup: 1 transaksi terbaru per (konsumen + unit), utamakan yang sudah punya tanggal akad
$durPart = "ROW_NUMBER() OVER (PARTITION BY t.id_konsumen, t.id_rumah ORDER BY (t.tanggal_akad IS NULL) ASC, t.id_transaksi DESC) AS rn";

// ==========================================
// STATISTIK (dedup tanpa filter status, sesuai makna kartu "Belum")
// ==========================================
$stat = ['total' => 0, 'bphtb' => 0, 'tapera' => 0, 'trilogi' => 0, 'sikumbang' => 0];
$q_stat = sdb_query($koneksi, "SELECT
    COUNT(*) AS total,
    SUM(bphtb0) AS bphtb,
    SUM(tapera0) AS tapera,
    SUM(trilogi0) AS trilogi,
    SUM(sikumbang0) AS sikumbang
FROM (
    SELECT (t.status_bphtb = 'Belum Dikerjakan' OR t.status_bphtb IS NULL OR t.status_bphtb = '')::int AS bphtb0,
           (t.status_tapera = 'Belum Daftar' OR t.status_tapera IS NULL OR t.status_tapera = '')::int AS tapera0,
           (t.status_trilogi = 'Belum Diaplikasi' OR t.status_trilogi IS NULL OR t.status_trilogi = '')::int AS trilogi0,
           (t.status_sikumbang = 'Belum' OR t.status_sikumbang IS NULL OR t.status_sikumbang = '')::int AS sikumbang0,
           $durPart
    FROM data_transaksi t
    JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
    JOIN data_rumah r ON t.id_rumah = r.id_rumah
    JOIN data_cluster c ON r.id_cluster = c.id_cluster
    WHERE $whereBase
) sub
WHERE sub.rn = 1");
if ($s = sdb_fetch_assoc($q_stat)) {
    $stat['total']     = intval($s['total']);
    $stat['bphtb']     = intval($s['bphtb']);
    $stat['tapera']    = intval($s['tapera']);
    $stat['trilogi']   = intval($s['trilogi']);
    $stat['sikumbang'] = intval($s['sikumbang']);
}

// Ambil daftar untuk dropdown filter
$clusters = [];
$q_cl = sdb_query($koneksi, "SELECT DISTINCT cluster_name FROM data_cluster ORDER BY cluster_name");
while ($r = sdb_fetch_assoc($q_cl)) $clusters[] = $r['cluster_name'];

$skemas = [];
$q_sk = sdb_query($koneksi, "SELECT DISTINCT skema_bayar FROM data_transaksi WHERE skema_bayar IS NOT NULL AND skema_bayar != '' ORDER BY skema_bayar");
while ($r = sdb_fetch_assoc($q_sk)) $skemas[] = $r['skema_bayar'];

$statuses = [];
$q_st = sdb_query($koneksi, "SELECT DISTINCT status_konsumen FROM data_transaksi WHERE status_konsumen IS NOT NULL AND status_konsumen != '' ORDER BY status_konsumen");
while ($r = sdb_fetch_assoc($q_st)) $statuses[] = $r['status_konsumen'];

function status_nilai_html($v) {
    if ($v === null || $v === '' || strpos($v, 'Belum') === 0) {
        return '<span class="text-muted small fw-medium">Belum</span>';
    }
    if (strpos($v, 'Sudah') === 0) {
        return '<span class="badge bg-success rounded-pill px-3 py-2">' . htmlspecialchars($v) . '</span>';
    }
    return '<span class="badge bg-secondary rounded-pill px-3 py-2">' . htmlspecialchars($v) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Status KPR - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .filter-input {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 0.875rem;
            padding: 8px 16px;
            transition: all 0.2s ease;
        }

        .filter-input:focus {
            background-color: #ffffff;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .stat-card.is-active {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.15);
        }

        .row-check:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .mod-check {
            width: 1.25em;
            height: 1.25em;
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
    <div class="app-layout">
        <?php include "sidebar.php"; ?>
        <main class="main-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Status KPR</h4>
                    <p class="text-muted small mb-0">Tracking BPHTB, Tapera Mobile, Trilogi &amp; Sikumbang per konsumen.</p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <a href="status_kpr.php" class="text-decoration-none stat-card d-block d-flex align-items-center gap-3">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= $stat['total'] ?></div>
                            <div class="text-muted small fw-medium">Total KPR</div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="status_kpr.php?f=bphtb" class="text-decoration-none stat-card d-block d-flex align-items-center gap-3 <?= $filterF === 'bphtb' ? 'is-active' : '' ?>">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #a16207, #eab308);">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= $stat['bphtb'] ?></div>
                            <div class="text-muted small fw-medium">BPHTB Belum Dikerjakan</div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="status_kpr.php?f=tapera" class="text-decoration-none stat-card d-block d-flex align-items-center gap-3 <?= $filterF === 'tapera' ? 'is-active' : '' ?>">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #1d4ed8, #3b82f6);">
                            <i class="bi bi-phone"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= $stat['tapera'] ?></div>
                            <div class="text-muted small fw-medium">Tapera Belum Daftar</div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="status_kpr.php?f=trilogi" class="text-decoration-none stat-card d-block d-flex align-items-center gap-3 <?= $filterF === 'trilogi' ? 'is-active' : '' ?>">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #7c3aed, #a78bfa);">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= $stat['trilogi'] ?></div>
                            <div class="text-muted small fw-medium">Trilogi Belum Diaplikasi</div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="status_kpr.php?f=sikumbang" class="text-decoration-none stat-card d-block d-flex align-items-center gap-3 <?= $filterF === 'sikumbang' ? 'is-active' : '' ?>">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #b91c1c, #ef4444);">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= $stat['sikumbang'] ?></div>
                            <div class="text-muted small fw-medium">Sikumbang Belum Hijau</div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="content-card">
                <form method="GET" action="status_kpr.php" id="filterForm">
                <div class="row g-2 mb-3 align-items-center filter-section">
                    <div class="col-md-3">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute text-muted" style="left: 16px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" id="searchInput" name="k" class="form-control filter-input ps-5 shadow-none" placeholder="Cari konsumen / blok / cluster..." value="<?= htmlspecialchars($keyword) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="f" class="form-select filter-input shadow-none">
                            <option value="">Semua Status</option>
                            <option value="bphtb" <?= $filterF === 'bphtb' ? 'selected' : '' ?>>BPHTB Belum</option>
                            <option value="tapera" <?= $filterF === 'tapera' ? 'selected' : '' ?>>Tapera Belum</option>
                            <option value="trilogi" <?= $filterF === 'trilogi' ? 'selected' : '' ?>>Trilogi Belum</option>
                            <option value="sikumbang" <?= $filterF === 'sikumbang' ? 'selected' : '' ?>>Sikumbang Belum</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="f_cluster" class="form-select filter-input shadow-none">
                            <option value="">Semua Cluster</option>
                            <?php foreach ($clusters as $cl): ?>
                            <option value="<?= htmlspecialchars($cl) ?>" <?= $filterCluster === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="f_status" class="form-select filter-input shadow-none">
                            <option value="">Status Konsumen</option>
                            <?php foreach ($statuses as $st): ?>
                            <option value="<?= htmlspecialchars($st) ?>" <?= $filterStatusK === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="f_skema" class="form-select filter-input shadow-none">
                            <option value="">Skema Bayar</option>
                            <?php foreach ($skemas as $sk): ?>
                            <option value="<?= htmlspecialchars($sk) ?>" <?= $filterSkema === $sk ? 'selected' : '' ?>><?= htmlspecialchars($sk) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Filter tahap KPR (Semua / Belum / Sudah) -->
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold mb-1">Filter Tahap KPR</label>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="f_bphtb" class="form-select filter-input shadow-none">
                            <option value="">BPHTB: Semua</option>
                            <option value="belum" <?= $filterBphtb === 'belum' ? 'selected' : '' ?>>Belum</option>
                            <option value="sudah" <?= $filterBphtb === 'sudah' ? 'selected' : '' ?>>Sudah</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="f_tapera" class="form-select filter-input shadow-none">
                            <option value="">Tapera: Semua</option>
                            <option value="belum" <?= $filterTapera === 'belum' ? 'selected' : '' ?>>Belum</option>
                            <option value="sudah" <?= $filterTapera === 'sudah' ? 'selected' : '' ?>>Sudah</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="f_trilogi" class="form-select filter-input shadow-none">
                            <option value="">Trilogi: Semua</option>
                            <option value="belum" <?= $filterTrilogi === 'belum' ? 'selected' : '' ?>>Belum</option>
                            <option value="sudah" <?= $filterTrilogi === 'sudah' ? 'selected' : '' ?>>Sudah</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="f_sikumb" class="form-select filter-input shadow-none">
                            <option value="">Sikumbang: Semua</option>
                            <option value="belum" <?= $filterSikumb === 'belum' ? 'selected' : '' ?>>Belum</option>
                            <option value="sudah" <?= $filterSikumb === 'sudah' ? 'selected' : '' ?>>Sudah</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="date" name="f_akad_dari" class="form-control filter-input shadow-none" value="<?= htmlspecialchars($filterAkadDari) ?>" title="Akad dari">
                            <span class="input-group-text bg-white border-end-0">s/d</span>
                            <input type="date" name="f_akad_sampai" class="form-control filter-input shadow-none" value="<?= htmlspecialchars($filterAkadSampai) ?>" title="Akad sampai">
                        </div>
                    </div>
                    <div class="col-md-8 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill btn-sm px-4 fw-medium"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button>
                        <a href="status_kpr.php" class="btn btn-outline-secondary rounded-pill btn-sm px-4 fw-medium"><i class="bi bi-x-lg me-1"></i>Reset</a>
                    </div>
                </div>
            </form>

                <!-- Active Filter Badges -->
                <div class="mb-3">
                    <div class="d-flex flex-wrap gap-2" id="activeFilters">
                        <?php
                        $baseParams = $_GET;
                        $activeFilters = [];
                        if ($keyword) { $p = $baseParams; unset($p['k']); $activeFilters[] = ['label' => "Cari: $keyword", 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterF) { $labels = ['bphtb'=>'BPHTB Belum','tapera'=>'Tapera Belum','trilogi'=>'Trilogi Belum','sikumbang'=>'Sikumbang Belum']; $p = $baseParams; unset($p['f']); $activeFilters[] = ['label' => "Status: {$labels[$filterF]}", 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterCluster) { $p = $baseParams; unset($p['f_cluster']); $activeFilters[] = ['label' => "Cluster: $filterCluster", 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterStatusK) { $p = $baseParams; unset($p['f_status']); $activeFilters[] = ['label' => "Status: $filterStatusK", 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterSkema) { $p = $baseParams; unset($p['f_skema']); $activeFilters[] = ['label' => "Skema: $filterSkema", 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterAkadDari || $filterAkadSampai) { $p = $baseParams; unset($p['f_akad_dari'], $p['f_akad_sampai']); $activeFilters[] = ['label' => "Akad: " . ($filterAkadDari?:'-') . " s/d " . ($filterAkadSampai?:'-'), 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterBphtb) { $p = $baseParams; unset($p['f_bphtb']); $activeFilters[] = ['label' => "BPHTB: " . ($filterBphtb==='belum'?'Belum':'Sudah'), 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterTapera) { $p = $baseParams; unset($p['f_tapera']); $activeFilters[] = ['label' => "Tapera: " . ($filterTapera==='belum'?'Belum':'Sudah'), 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterTrilogi) { $p = $baseParams; unset($p['f_trilogi']); $activeFilters[] = ['label' => "Trilogi: " . ($filterTrilogi==='belum'?'Belum':'Sudah'), 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        if ($filterSikumb) { $p = $baseParams; unset($p['f_sikumb']); $activeFilters[] = ['label' => "Sikumbang: " . ($filterSikumb==='belum'?'Belum':'Sudah'), 'url' => 'status_kpr.php?' . http_build_query($p)]; }
                        foreach ($activeFilters as $af):
                        ?>
                        <a href="<?= htmlspecialchars($af['url']) ?>" class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill px-3 py-2 small d-inline-flex align-items-center gap-1 text-decoration-none" title="Hapus filter ini">
                            <?= htmlspecialchars($af['label']) ?>
                            <i class="bi bi-x-circle text-primary"></i>
                        </a>
                        <?php endforeach; ?>
                        <?php if ($activeFilters): ?>
                        <a href="status_kpr.php" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle rounded-pill px-3 py-2 small d-inline-flex align-items-center gap-1 text-decoration-none" title="Bersihkan semua filter">
                            <i class="bi bi-x-lg me-1"></i> Bersihkan Semua
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center gap-2">
                            <button id="btnMassal" type="button" class="btn btn-success rounded-pill btn-sm fw-medium d-none">
                                <i class="bi bi-pencil-square me-1"></i> Ubah Massal (<span id="massalCount">0</span>)
                            </button>
                            <button id="btnReset" class="btn btn-outline-secondary rounded-pill btn-sm fw-medium">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua
                            </button>
                        </div>
                    </div>
                    <div class="col-md-8 text-end">
                        <small class="text-muted">
                            Menampilkan <strong id="rowCount">0</strong> data KPR
                        </small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableKpr">
                        <thead>
                            <tr class="text-muted">
                                <th style="width: 36px;"><input type="checkbox" class="form-check-input" id="selectAll" title="Pilih semua"></th>
                                <th style="width: 40px;">No</th>
                                <th>Konsumen</th>
                                <th>Unit Rumah</th>
                                <th>Tanggal Akad</th>
                                <th>Status</th>
                                <th>Aksi</th>
                                <th>BPHTB</th>
                                <th>Tapera Mobile</th>
                                <th>Trilogi</th>
                                <th>Sikumbang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $q = sdb_query($koneksi, "SELECT * FROM (
                                SELECT t.id_transaksi, t.skema_bayar, t.skema_akad, t.status_konsumen, t.tanggal_akad,
                                       t.status_bphtb, t.status_tapera, t.status_trilogi, t.status_sikumbang,
                                       k.name_lengkap, k.no_whatsapp, r.no_blok, r.tipe_rumah, c.cluster_name,
                                       $durPart
                                FROM data_transaksi t
                                JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                WHERE $whereBase
                            ) sub
                            WHERE sub.rn = 1$whereFilt
                            ORDER BY IFNULL(sub.tanggal_akad, '9999-12-31') ASC, sub.id_transaksi DESC");
                            $no = 1;
                            $ada = $q && sdb_num_rows($q) > 0;
                            if ($ada) {
                                while ($r = sdb_fetch_assoc($q)) {
                                    $status = $r['status_konsumen'];
                                    if ($status === 'ACC - AKAD') { $badge = 'bg-success'; }
                                    elseif ($status === 'BATAL')  { $badge = 'bg-danger'; }
                                    elseif ($status === 'PROSES') { $badge = 'bg-warning text-dark'; }
                                    else { $badge = 'bg-secondary'; }
                                    $idTx = (int)$r['id_transaksi'];
                            ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input row-check" value="<?= $idTx ?>" title="Pilih baris"></td>
                                <td class="fw-semibold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($r['name_lengkap']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($r['no_whatsapp'] ?? '-') ?></div>
                                </td>
                                <td><span class="badge bg-dark rounded-pill px-3 py-2"><?= htmlspecialchars($r['cluster_name']) ?> - Blok <?= htmlspecialchars($r['no_blok']) ?> (Tipe <?= htmlspecialchars($r['tipe_rumah']) ?>)</span></td>
                                <td class="small">
                                    <?php if (!empty($r['tanggal_akad'])): ?>
                                        <span class="fw-semibold"><?= date('d/m/Y', strtotime($r['tanggal_akad'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Belum diisi</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $badge ?> rounded-pill px-3 py-2"><?= htmlspecialchars($status) ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-circle edit-btn"
                                        data-id="<?= $idTx ?>"
                                        data-bphtb="<?= htmlspecialchars((string)($r['status_bphtb'] ?? '')) ?>"
                                        data-tapera="<?= htmlspecialchars((string)($r['status_tapera'] ?? '')) ?>"
                                        data-trilogi="<?= htmlspecialchars((string)($r['status_trilogi'] ?? '')) ?>"
                                        data-sikumbang="<?= htmlspecialchars((string)($r['status_sikumbang'] ?? '')) ?>"
                                        title="Edit status">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                                <td><?= status_nilai_html($r['status_bphtb']) ?></td>
                                <td><?= status_nilai_html($r['status_tapera']) ?></td>
                                <td><?= status_nilai_html($r['status_trilogi']) ?></td>
                                <td><?= status_nilai_html($r['status_sikumbang']) ?></td>
                            </tr>
                            <?php } } else { ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <i class="bi bi-calendar-x text-muted" style="font-size: 2.5rem;"></i>
                                    <div class="fw-semibold text-dark mt-2">Tidak ada data KPR ditemukan</div>
                                    <p class="text-muted small mb-3">Ubah kata kunci / filter pencarian, atau tambahkan transaksi baru.</p>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Ubah Status (checkbox) -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="status_kpr.php" id="statusForm">
                <?= csrf_field() ?>
                <input type="hidden" name="update_status_kpr" value="1">
                <input type="hidden" name="ids" id="modalIds" value="">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="statusModalTitle">Ubah Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input mod-check" type="checkbox" id="chk_bphtb" name="chk_bphtb" value="1">
                            <label class="form-check-label fw-semibold" for="chk_bphtb">BPHTB &mdash; Sudah Dikerjakan (Data Masuk)</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input mod-check" type="checkbox" id="chk_tapera" name="chk_tapera" value="1">
                            <label class="form-check-label fw-semibold" for="chk_tapera">Tapera Mobile &mdash; Sudah Daftar &amp; Sudah Pengajuan KPR</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input mod-check" type="checkbox" id="chk_trilogi" name="chk_trilogi" value="1">
                            <label class="form-check-label fw-semibold" for="chk_trilogi">Trilogi &mdash; Sudah Trilogi</label>
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input mod-check" type="checkbox" id="chk_sikumbang" name="chk_sikumbang" value="1">
                            <label class="form-check-label fw-semibold" for="chk_sikumbang">Sikumbang &mdash; Sudah Hijau</label>
                        </div>
                        <div class="alert alert-light border small mb-0 rounded-4">
                            <i class="bi bi-info-circle me-1 text-success"></i>
                            Dicentang = status <strong>selesai</strong>. Dikosongkan = status <strong>belum dikerjakan</strong>.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm fw-medium" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success rounded-pill btn-sm fw-medium">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableRows = document.querySelectorAll('#tableKpr tbody tr');
            const btnReset = document.getElementById('btnReset');
            const rowChecks = document.querySelectorAll('.row-check');
            const selectAll = document.getElementById('selectAll');
            const btnMassal = document.getElementById('btnMassal');
            const massalCount = document.getElementById('massalCount');
            const statusModalEl = document.getElementById('statusModal');
            const modalIds = document.getElementById('modalIds');
            const modalTitle = document.getElementById('statusModalTitle');
            const modChecks = document.querySelectorAll('.mod-check');
            const rowCountEl = document.getElementById('rowCount');

            function visibleRows() {
                return Array.from(tableRows).filter(tr => tr.style.display !== 'none');
            }

            function updateRowCount() {
                if (rowCountEl) {
                    rowCountEl.textContent = visibleRows().length;
                }
            }

            function refreshMassal() {
                const n = Array.from(rowChecks).filter(c => c.checked).length;
                btnMassal.classList.toggle('d-none', n === 0);
                massalCount.textContent = n;
            }

            selectAll.addEventListener('change', function () {
                const checked = this.checked;
                visibleRows().forEach(tr => {
                    const c = tr.querySelector('.row-check');
                    if (c) c.checked = checked;
                });
                refreshMassal();
            });

            rowChecks.forEach(c => c.addEventListener('change', function () {
                const all = visibleRows().every(tr => {
                    const cc = tr.querySelector('.row-check');
                    return cc ? cc.checked : true;
                });
                selectAll.checked = all;
                refreshMassal();
            }));

            function openModal(ids, prefill) {
                modalIds.value = ids.join(',');
                modalTitle.textContent = ids.length > 1
                    ? ('Ubah Status Massal (' + ids.length + ' data)')
                    : 'Ubah Status';
                modChecks.forEach(cb => {
                    cb.checked = !!prefill[cb.name];
                });
                new bootstrap.Modal(statusModalEl).show();
            }

            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const pre = {};
                    pre.chk_bphtb = (this.dataset.bphtb || '').indexOf('Sudah') === 0;
                    pre.chk_tapera = (this.dataset.tapera || '').indexOf('Sudah') === 0;
                    pre.chk_trilogi = (this.dataset.trilogi || '').indexOf('Sudah') === 0;
                    pre.chk_sikumbang = (this.dataset.sikumbang || '').indexOf('Sudah') === 0;
                    openModal([parseInt(this.dataset.id, 10)], pre);
                });
            });

            btnMassal.addEventListener('click', function () {
                const ids = Array.from(rowChecks).filter(c => c.checked).map(c => parseInt(c.value, 10));
                if (!ids.length) return;
                openModal(ids, {});
            });

            btnReset.addEventListener('click', function () {
                window.location.href = 'status_kpr.php';
            });

            // Update row count on load
            updateRowCount();
            refreshMassal();
        });
    </script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>