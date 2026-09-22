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
        $stmt = mysqli_prepare($koneksi, "UPDATE data_transaksi SET
                                    status_bphtb = ?, status_tapera = ?, status_trilogi = ?, status_sikumbang = ?
                                    WHERE id_transaksi = ?");
        if ($stmt) {
            foreach ($ids as $id) {
                mysqli_stmt_bind_param($stmt, 'ssssi', $val['bphtb'], $val['tapera'], $val['trilogi'], $val['sikumbang'], $id);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        log_aktivitas($koneksi, 'UBAH', 'Status KPR', 'Update status KPR ' . count($ids) . ' transaksi (#' . implode(',', $ids) . ')', $val);
    }

    $f = in_array($_POST['f'] ?? '', ['bphtb', 'tapera', 'trilogi', 'sikumbang']) ? $_POST['f'] : '';
    header("Location: status_kpr.php" . ($f ? "?f=$f" : ''));
    exit();
}

// ==========================================
// FILTER
// ==========================================
$keyword = isset($_GET['k']) ? mysqli_real_escape_string($koneksi, $_GET['k']) : '';
$filterF = isset($_GET['f']) ? $_GET['f'] : '';
$filterF = in_array($filterF, ['bphtb', 'tapera', 'trilogi', 'sikumbang']) ? $filterF : '';

$whereBase = "t.status_konsumen != 'BATAL' AND (t.skema_bayar = 'KPR Subsidi' OR r.tipe_rumah = '36') AND t.skema_bayar NOT IN ('Cash Tempo', 'Cash Keras')";
if ($keyword !== '') {
    $whereBase .= " AND (k.name_lengkap LIKE '%$keyword%' OR r.no_blok LIKE '%$keyword%' OR c.cluster_name LIKE '%$keyword%')";
}

// Dedup: 1 transaksi terbaru per (konsumen + unit), utamakan yang sudah punya tanggal akad
$durPart = "ROW_NUMBER() OVER (PARTITION BY t.id_konsumen, t.id_rumah ORDER BY (t.tanggal_akad IS NULL) ASC, t.id_transaksi DESC) AS rn";

$whereFilt = '';
if ($filterF === 'bphtb')      { $whereFilt = " AND (status_bphtb = 'Belum Dikerjakan' OR status_bphtb IS NULL OR status_bphtb = '')"; }
elseif ($filterF === 'tapera') { $whereFilt = " AND (status_tapera = 'Belum Daftar' OR status_tapera IS NULL OR status_tapera = '')"; }
elseif ($filterF === 'trilogi'){ $whereFilt = " AND (status_trilogi = 'Belum Diaplikasi' OR status_trilogi IS NULL OR status_trilogi = '')"; }
elseif ($filterF === 'sikumbang'){ $whereFilt = " AND (status_sikumbang = 'Belum' OR status_sikumbang IS NULL OR status_sikumbang = '')"; }

// ==========================================
// STATISTIK (dedup tanpa filter status, sesuai makna kartu "Belum")
// ==========================================
$stat = ['total' => 0, 'bphtb' => 0, 'tapera' => 0, 'trilogi' => 0, 'sikumbang' => 0];
$q_stat = mysqli_query($koneksi, "SELECT
    COUNT(*) AS total,
    SUM(bphtb0) AS bphtb,
    SUM(tapera0) AS tapera,
    SUM(trilogi0) AS trilogi,
    SUM(sikumbang0) AS sikumbang
FROM (
    SELECT (t.status_bphtb = 'Belum Dikerjakan' OR t.status_bphtb IS NULL OR t.status_bphtb = '') AS bphtb0,
           (t.status_tapera = 'Belum Daftar' OR t.status_tapera IS NULL OR t.status_tapera = '') AS tapera0,
           (t.status_trilogi = 'Belum Diaplikasi' OR t.status_trilogi IS NULL OR t.status_trilogi = '') AS trilogi0,
           (t.status_sikumbang = 'Belum' OR t.status_sikumbang IS NULL OR t.status_sikumbang = '') AS sikumbang0,
           $durPart
    FROM data_transaksi t
    JOIN data_rumah r ON t.id_rumah = r.id_rumah
    WHERE $whereBase
) sub
WHERE sub.rn = 1");
if ($s = mysqli_fetch_assoc($q_stat)) {
    $stat['total']     = intval($s['total']);
    $stat['bphtb']     = intval($s['bphtb']);
    $stat['tapera']    = intval($s['tapera']);
    $stat['trilogi']   = intval($s['trilogi']);
    $stat['sikumbang'] = intval($s['sikumbang']);
}

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
                <div class="row g-2 mb-4 align-items-center filter-section">
                    <div class="col-md-4">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute text-muted" style="left: 16px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" id="searchInput" class="form-control filter-input ps-5 shadow-none" placeholder="Cari konsumen / unit rumah...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <form method="GET" action="status_kpr.php" class="d-flex align-items-center gap-2">
                            <select name="f" class="form-select filter-input shadow-none" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="bphtb" <?= $filterF === 'bphtb' ? 'selected' : '' ?>>BPHTB Belum Dikerjakan</option>
                                <option value="tapera" <?= $filterF === 'tapera' ? 'selected' : '' ?>>Tapera Belum Daftar</option>
                                <option value="trilogi" <?= $filterF === 'trilogi' ? 'selected' : '' ?>>Trilogi Belum Diaplikasi</option>
                                <option value="sikumbang" <?= $filterF === 'sikumbang' ? 'selected' : '' ?>>Sikumbang Belum Hijau</option>
                            </select>
                        </form>
                    </div>
                    <div class="col-md-3 text-end ms-auto d-flex gap-2 align-items-center justify-content-end">
                        <button id="btnMassal" type="button" class="btn btn-success rounded-pill btn-sm fw-medium d-none">
                            <i class="bi bi-pencil-square me-1"></i> Ubah Massal (<span id="massalCount">0</span>)
                        </button>
                        <button id="btnReset" class="btn btn-outline-secondary rounded-pill btn-sm fw-medium">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
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
                            $q = mysqli_query($koneksi, "SELECT * FROM (
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
                            $ada = $q && mysqli_num_rows($q) > 0;
                            if ($ada) {
                                while ($r = mysqli_fetch_assoc($q)) {
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
                <input type="hidden" name="f" id="modalF" value="<?= htmlspecialchars($filterF) ?>">
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
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('#tableKpr tbody tr');
            const btnReset = document.getElementById('btnReset');
            const rowChecks = document.querySelectorAll('.row-check');
            const selectAll = document.getElementById('selectAll');
            const btnMassal = document.getElementById('btnMassal');
            const massalCount = document.getElementById('massalCount');
            const statusModalEl = document.getElementById('statusModal');
            const modalIds = document.getElementById('modalIds');
            const modalF = document.getElementById('modalF');
            const modalTitle = document.getElementById('statusModalTitle');
            const modChecks = document.querySelectorAll('.mod-check');

            function visibleRows() {
                return Array.from(tableRows);
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
                modalF.value = new URLSearchParams(location.search).get('f') || '';
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

            if (sessionStorage.getItem('status_kpr_search')) {
                searchInput.value = sessionStorage.getItem('status_kpr_search');
            }

            function filterTable() {
                const query = searchInput.value.toLowerCase().trim();
                sessionStorage.setItem('status_kpr_search', searchInput.value);
                tableRows.forEach(row => {
                    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
                });
            }

            filterTable();
            searchInput.addEventListener('input', function () {
                filterTable();
                const hasHidden = Array.from(tableRows).some(tr => tr.style.display === 'none');
                if (hasHidden && selectAll.checked) selectAll.checked = false;
            });
            btnReset.addEventListener('click', function () {
                searchInput.value = '';
                sessionStorage.removeItem('status_kpr_search');
                filterTable();
                window.location.href = 'status_kpr.php';
            });

            refreshMassal();
        });
    </script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>