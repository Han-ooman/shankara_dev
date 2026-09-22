<?php
include "includes/koneksi.php";
require_once __DIR__ . '/helper_log.php';

session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid.");
}

$dari  = isset($_GET['dari']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari']) ? $_GET['dari'] : '';
$sampai = isset($_GET['sampai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai']) ? $_GET['sampai'] : '';
$filterAksi  = isset($_GET['aksi']) ? mysqli_real_escape_string($koneksi, $_GET['aksi']) : '';
$filterModul = isset($_GET['modul']) ? mysqli_real_escape_string($koneksi, $_GET['modul']) : '';
$keyword     = isset($_GET['q']) ? mysqli_real_escape_string($koneksi, $_GET['q']) : '';

$where = "1=1";
if (!empty($dari))     $where .= " AND DATE(l.created_at) >= '$dari'";
if (!empty($sampai))   $where .= " AND DATE(l.created_at) <= '$sampai'";
if ($filterAksi !== '')  $where .= " AND l.aksi = '$filterAksi'";
if ($filterModul !== '') $where .= " AND l.modul = '$filterModul'";
if ($keyword !== '') {
    $where .= " AND (l.deskripsi LIKE '%$keyword%' OR l.ip_address LIKE '%$keyword%' OR u.nama_lengkap LIKE '%$keyword%' OR u.username LIKE '%$keyword%' OR l.detail LIKE '%$keyword%')";
}

// Daftar aksi & modul unik untuk dropdown filter
$opsiAksi = mysqli_query($koneksi, "SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi");
$opsiModul = mysqli_query($koneksi, "SELECT DISTINCT modul FROM log_aktivitas ORDER BY modul");

// Statistik
$stat = ['total' => 0, 'hari_ini' => 0, 'login' => 0, 'gagal' => 0, 'perubahan' => 0];
$q_stat = mysqli_query($koneksi, "SELECT
    (SELECT COUNT(*) FROM log_aktivitas) AS total,
    (SELECT COUNT(*) FROM log_aktivitas WHERE DATE(created_at) = CURDATE()) AS hari_ini,
    (SELECT COUNT(*) FROM log_aktivitas WHERE aksi = 'LOGIN' AND DATE(created_at) = CURDATE()) AS login,
    (SELECT COUNT(*) FROM log_aktivitas WHERE aksi IN ('LOGIN_GAGAL','LOGIN_PENUH') AND DATE(created_at) = CURDATE()) AS gagal,
    (SELECT COUNT(*) FROM log_aktivitas WHERE aksi IN ('TAMBAH','UBAH','UBAH_MASSAL','HAPUS') AND DATE(created_at) = CURDATE()) AS perubahan");
if ($s = mysqli_fetch_assoc($q_stat)) {
    $stat['total']     = intval($s['total']);
    $stat['hari_ini']  = intval($s['hari_ini']);
    $stat['login']     = intval($s['login']);
    $stat['gagal']     = intval($s['gagal']);
    $stat['perubahan'] = intval($s['perubahan']);
}

// Pagination
$perHal = 25;
$curHal = isset($_GET['hlm']) ? max(1, intval($_GET['hlm'])) : 1;
$offset = ($curHal - 1) * $perHal;
$q_total = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM log_aktivitas l LEFT JOIN users u ON l.user_id = u.id WHERE $where");
$jml_data = (int) mysqli_fetch_assoc($q_total)['jml'];
$jmlHal = max(1, ceil($jml_data / $perHal));

$query = "SELECT l.aksi, l.modul, l.deskripsi, l.detail, l.ip_address, l.created_at,
                 u.nama_lengkap, u.username
          FROM log_aktivitas l
          LEFT JOIN users u ON l.user_id = u.id
          WHERE $where
          ORDER BY l.created_at DESC, l.id_log DESC
          LIMIT $perHal OFFSET $offset";
$hasil = mysqli_query($koneksi, $query);

// Parameter tambahan untuk dipertahankan pada link pagination
$qs = [];
foreach (['q', 'aksi', 'modul', 'dari', 'sampai'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $qs[$k] = $_GET[$k];
}
$qsStr = count($qs) ? '&' . http_build_query($qs) : '';

function aksi_html($aksi) {
    $peta = [
        'LOGIN'          => ['bg-success', 'bi-box-arrow-in-right'],
        'LOGIN_GAGAL'    => ['bg-danger',  'bi-x-octagon'],
        'LOGIN_PENUH'    => ['bg-warning text-dark', 'bi-x-circle'],
        'LOGOUT'         => ['bg-secondary', 'bi-box-arrow-right'],
        'LOGOUT_TIMEOUT' => ['bg-info text-dark', 'bi-hourglass-split'],
        'TAMBAH'         => ['bg-primary',  'bi-plus-circle'],
        'UBAH'           => ['bg-primary',  'bi-pencil-square'],
        'UBAH_MASSAL'    => ['bg-primary',  'bi-layers'],
        'HAPUS'          => ['bg-danger',   'bi-trash'],
    ];
    $p = $peta[$aksi] ?? ['bg-secondary', 'bi-info-circle'];
    return '<span class="badge ' . $p[0] . ' rounded-3 px-2 py-1"><i class="bi ' . $p[1] . ' me-1"></i>' . htmlspecialchars($aksi) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">
    <title>Data Log - Shankara Dev</title>
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

        .log-detail {
            max-width: 320px;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.75rem;
        }
    </style>
</head>
<body class="bg-light">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
    <div class="app-layout">
        <?php include "sidebar.php"; ?>
        <main class="main-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Data Log Aktivitas</h4>
                    <p class="text-muted small mb-0">Rekaman perubahan data, login user, dan aktivitas sistem.</p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="stat-card d-block d-flex align-items-center gap-3">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);"><i class="bi bi-journal-check"></i></div>
                        <div><div class="fs-4 fw-bold"><?= $stat['total'] ?></div><div class="text-muted small fw-medium">Total Catatan</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card d-block d-flex align-items-center gap-3">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #1d4ed8, #3b82f6);"><i class="bi bi-calendar-check"></i></div>
                        <div><div class="fs-4 fw-bold"><?= $stat['hari_ini'] ?></div><div class="text-muted small fw-medium">Hari Ini</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card d-block d-flex align-items-center gap-3">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #047857, #10b981);"><i class="bi bi-box-arrow-in-right"></i></div>
                        <div><div class="fs-4 fw-bold"><?= $stat['login'] ?></div><div class="text-muted small fw-medium">Login Hari Ini</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card d-block d-flex align-items-center gap-3">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #b91c1c, #ef4444);"><i class="bi bi-x-octagon"></i></div>
                        <div><div class="fs-4 fw-bold"><?= $stat['gagal'] ?></div><div class="text-muted small fw-medium">Login Gagal Hari Ini</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card d-block d-flex align-items-center gap-3">
                        <div class="stat-icon text-white" style="background: linear-gradient(135deg, #7c3aed, #a78bfa);"><i class="bi bi-pencil-square"></i></div>
                        <div><div class="fs-4 fw-bold"><?= $stat['perubahan'] ?></div><div class="text-muted small fw-medium">Perubahan Hari Ini</div></div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <form method="GET" action="log_aktivitas.php" class="row g-2 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Cari</label>
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute text-muted" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" class="form-control filter-input ps-5 shadow-none" placeholder="Kata kunci / nama / IP...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Aksi</label>
                        <select name="aksi" class="form-select filter-input shadow-none">
                            <option value="">Semua Aksi</option>
                            <?php while ($a = mysqli_fetch_assoc($opsiAksi)): ?>
                                <option value="<?= htmlspecialchars($a['aksi']) ?>" <?= $filterAksi === $a['aksi'] ? 'selected' : '' ?>><?= htmlspecialchars($a['aksi']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Modul</label>
                        <select name="modul" class="form-select filter-input shadow-none">
                            <option value="">Semua Modul</option>
                            <?php while ($m = mysqli_fetch_assoc($opsiModul)): ?>
                                <option value="<?= htmlspecialchars($m['modul']) ?>" <?= $filterModul === $m['modul'] ? 'selected' : '' ?>><?= htmlspecialchars($m['modul']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Dari</label>
                        <input type="date" name="dari" value="<?= htmlspecialchars($dari) ?>" class="form-control filter-input shadow-none">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Sampai</label>
                        <input type="date" name="sampai" value="<?= htmlspecialchars($sampai) ?>" class="form-control filter-input shadow-none">
                    </div>
                    <div class="col-md-1 d-grid gap-2">
                        <button type="submit" class="btn btn-green rounded-pill"><i class="bi bi-funnel"></i></button>
                        <a href="log_aktivitas.php" class="btn btn-outline-secondary rounded-pill" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-muted small fw-semibold">Waktu</th>
                                <th class="text-muted small fw-semibold">User</th>
                                <th class="text-muted small fw-semibold">Aksi</th>
                                <th class="text-muted small fw-semibold">Modul</th>
                                <th class="text-muted small fw-semibold">Deskripsi</th>
                                <th class="text-muted small fw-semibold">Detail</th>
                                <th class="text-muted small fw-semibold">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($hasil && mysqli_num_rows($hasil) > 0): ?>
                                <?php while ($r = mysqli_fetch_assoc($hasil)): ?>
                                    <tr>
                                        <td class="small text-nowrap"><?= date('d/m/Y H:i:s', strtotime($r['created_at'])) ?></td>
                                        <td><?= !empty($r['nama_lengkap']) ? htmlspecialchars($r['nama_lengkap']) : '<span class="badge bg-light text-muted rounded-pill">Sistem</span>' ?></td>
                                        <td><?= aksi_html($r['aksi']) ?></td>
                                        <td><span class="small fw-medium"><?= htmlspecialchars($r['modul']) ?></span></td>
                                        <td class="small"><?= htmlspecialchars($r['deskripsi']) ?></td>
                                        <td class="small text-muted log-detail" title="<?= htmlspecialchars($r['detail'] ?? '') ?>"><?= htmlspecialchars($r['detail'] ?? '') ?></td>
                                        <td class="small text-muted text-nowrap"><?= htmlspecialchars($r['ip_address'] ?? '') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada catatan log.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($jmlHal > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div class="small text-muted">Menampilkan <?= $jml_data ?> catatan &bull; hal <?= $curHal ?> dari <?= $jmlHal ?></div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($curHal > 1): ?>
                                <li class="page-item"><a class="page-link" href="log_aktivitas.php?hlm=<?= $curHal - 1 ?><?= $qsStr ?>">&laquo;</a></li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $curHal - 2); $i <= min($jmlHal, $curHal + 2); $i++): ?>
                                <li class="page-item <?= $i === $curHal ? 'active' : '' ?>"><a class="page-link" href="log_aktivitas.php?hlm=<?= $i ?><?= $qsStr ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($curHal < $jmlHal): ?>
                                <li class="page-item"><a class="page-link" href="log_aktivitas.php?hlm=<?= $curHal + 1 ?><?= $qsStr ?>">&raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>