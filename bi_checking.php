<?php
include "includes/koneksi.php";

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

// ==========================================
// LOGIKA TAMBAH BI CHECKING
// ==========================================
if (isset($_POST['simpan_bi'])) {
    $id_konsumen = intval($_POST['id_konsumen']);
    $id_marketing = !empty($_POST['id_marketing']) ? intval($_POST['id_marketing']) : 'NULL';
    $tgl_cek     = mysqli_real_escape_string($koneksi, $_POST['tgl_cek']);
    $lembaga     = mysqli_real_escape_string($koneksi, $_POST['lembaga']);
    $no_pengajuan = mysqli_real_escape_string($koneksi, $_POST['no_pengajuan']);
    $hasil_bi    = mysqli_real_escape_string($koneksi, $_POST['hasil_bi']);
    $catatan     = mysqli_real_escape_string($koneksi, $_POST['catatan']);

    mysqli_query($koneksi, "INSERT INTO data_bi_checking (id_konsumen, id_marketing, tgl_cek, lembaga, no_pengajuan, hasil_bi, catatan)
                            VALUES ($id_konsumen, $id_marketing, '$tgl_cek', '$lembaga', '$no_pengajuan', '$hasil_bi', '$catatan')");
    header("Location: bi_checking.php?status=success");
    exit();
}

// ==========================================
// LOGIKA EDIT BI CHECKING
// ==========================================
if (isset($_POST['edit_bi'])) {
    $id_bi        = intval($_POST['id_bi_checking']);
    $id_konsumen  = intval($_POST['id_konsumen']);
    $id_marketing = !empty($_POST['id_marketing']) ? intval($_POST['id_marketing']) : 'NULL';
    $tgl_cek      = mysqli_real_escape_string($koneksi, $_POST['tgl_cek']);
    $lembaga      = mysqli_real_escape_string($koneksi, $_POST['lembaga']);
    $no_pengajuan = mysqli_real_escape_string($koneksi, $_POST['no_pengajuan']);
    $hasil_bi     = mysqli_real_escape_string($koneksi, $_POST['hasil_bi']);
    $catatan      = mysqli_real_escape_string($koneksi, $_POST['catatan']);

    mysqli_query($koneksi, "UPDATE data_bi_checking SET
                            id_konsumen = $id_konsumen,
                            id_marketing = $id_marketing,
                            tgl_cek = '$tgl_cek',
                            lembaga = '$lembaga',
                            no_pengajuan = '$no_pengajuan',
                            hasil_bi = '$hasil_bi',
                            catatan = '$catatan'
                            WHERE id_bi_checking = $id_bi");
    header("Location: bi_checking.php?status=updated");
    exit();
}

// ==========================================
// LOGIKA HAPUS BI CHECKING
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = intval($_GET['id']);
    mysqli_query($koneksi, "DELETE FROM data_bi_checking WHERE id_bi_checking = $id_hapus");
    header("Location: bi_checking.php?status=deleted");
    exit();
}

// ==========================================
// FILTER
// ==========================================
$filter_keyword = isset($_GET['keyword']) ? mysqli_real_escape_string($koneksi, $_GET['keyword']) : '';
$filter_hasil   = isset($_GET['hasil']) ? mysqli_real_escape_string($koneksi, $_GET['hasil']) : '';
$filter_sort    = isset($_GET['sort']) ? mysqli_real_escape_string($koneksi, $_GET['sort']) : '';
$filter_marketing = isset($_GET['marketing']) ? intval($_GET['marketing']) : 0;

// Preselect konsumen dari halaman lain (mis. tombol "Cek BI" di halaman konsumen)
$preselect_konsumen = isset($_GET['konsumen']) ? intval($_GET['konsumen']) : 0;

// ==========================================
// STATISTIK (1 query Ð²Ð¼ÐµÑÑ‚Ð¾ 4)
// ==========================================
$stat = ['total'=>0, 'lulus'=>0, 'proses'=>0, 'tidak'=>0];
$q_stat = mysqli_query($koneksi, "SELECT 
    COUNT(*) AS total,
    SUM(hasil_bi = 'Lulus') AS lulus,
    SUM(hasil_bi = 'Dalam Proses') AS proses,
    SUM(hasil_bi IN ('Tidak Lulus','Dipertimbangkan')) AS tidak
FROM data_bi_checking");
if ($s = mysqli_fetch_assoc($q_stat)) {
    $stat['total']  = intval($s['total']);
    $stat['lulus']  = intval($s['lulus']);
    $stat['proses'] = intval($s['proses']);
    $stat['tidak']  = intval($s['tidak']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">    <title>BI Checking - Shankara Dev</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        .search-input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 5;
        }
    </style>
</head>
<body class="bg-light">
    <input type="hidden" id="csrf_global" value="<?= htmlspecialchars(csrf_token()) ?>">

    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>

    <!-- KONTEN UTAMA -->
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">BI Checking Konsumen</h4>
                <p class="text-muted small mb-0">Pemantauan hasil pengecekan kredit (SLIK / OJK / Bank) calon konsumen KPR.</p>
            </div>
            <button class="btn btn-green" style="color:#fff !important" data-bs-toggle="modal" data-bs-target="#modalBi">
                <i class="bi bi-clipboard-plus me-1"></i> Tambah BI Checking
            </button>
        </div>

        <!-- STATISTIK -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $stat['total'] ?></div>
                        <div class="text-muted small fw-medium">Total Pengecekan</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #2563eb, #3b82f6);">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $stat['lulus'] ?></div>
                        <div class="text-muted small fw-medium">Lulus BI Checking</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #a16207, #eab308);">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $stat['proses'] ?></div>
                        <div class="text-muted small fw-medium">Dalam Proses</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #b91c1c, #ef4444);">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $stat['tidak'] ?></div>
                        <div class="text-muted small fw-medium">Tidak Lulus / Bermasalah</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BAR FILTER -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #ffffff;">
            <div class="card-body p-3">
                <form method="GET" action="bi_checking.php" class="row g-3 align-items-center">
                    <div class="col-lg-4 col-md-12">
                        <div class="position-relative">
                            <i class="bi bi-search search-input-icon"></i>
                            <input type="text" name="keyword" class="form-control rounded-pill search-input-pill"
                                   placeholder="Cari nama konsumen, lembaga, no pengajuan..." value="<?= htmlspecialchars($filter_keyword) ?>">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <select name="hasil" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">-- Semua Hasil BI Checking --</option>
                            <?php foreach (['Dalam Proses', 'Lulus', 'Dipertimbangkan', 'Tidak Lulus'] as $st): ?>
                                <option value="<?= $st ?>" <?= ($filter_hasil == $st) ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <select name="marketing" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">-- Semua Marketing --</option>
                            <?php
                            $q_mkt = mysqli_query($koneksi, "SELECT id_marketing, nama_marketing FROM data_marketing WHERE status_aktif = 'Aktif' ORDER BY nama_marketing ASC");
                            while ($mkt = mysqli_fetch_assoc($q_mkt)) {
                                $sel_mkt = ($filter_marketing == $mkt['id_marketing']) ? 'selected' : '';
                                echo "<option value='" . $mkt['id_marketing'] . "' $sel_mkt>" . htmlspecialchars($mkt['nama_marketing']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <select name="sort" class="form-select rounded-pill bg-light border-0 px-3 py-2">
                            <option value="">Urutkan: Tgl Cek Terbaru</option>
                            <option value="no_pengajuan_asc" <?= ($filter_sort == 'no_pengajuan_asc') ? 'selected' : '' ?>>No. Pengajuan A-Z</option>
                            <option value="no_pengajuan_desc" <?= ($filter_sort == 'no_pengajuan_desc') ? 'selected' : '' ?>>No. Pengajuan Z-A</option>
                            <option value="tgl_asc" <?= ($filter_sort == 'tgl_asc') ? 'selected' : '' ?>>Tgl Cek Terlama</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 d-flex gap-2">
                        <button type="submit" class="btn text-white w-100 rounded-pill" style="background-color: var(--primary-blue); padding: 8px;">
                            <i class="bi bi-funnel-fill me-1"></i> Filter
                        </button>
                        <?php if (!empty($filter_keyword) || !empty($filter_hasil) || !empty($filter_sort) || $filter_marketing > 0): ?>
                            <a href="bi_checking.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Reset Filter">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABEL -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Konsumen</th>
                            <th>Marketer</th>
                            <th>Tgl Cek</th>
                            <th>Lembaga / Sumber</th>
                            <th>No. Pengajuan</th>
                            <th>Hasil BI Checking</th>
                            <th>Catatan</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT b.*, k.name_lengkap, k.pekerjaan, k.no_whatsapp, m.nama_marketing
                                  FROM data_bi_checking b
                                  LEFT JOIN data_konsumen k ON b.id_konsumen = k.id_konsumen
                                  LEFT JOIN data_marketing m ON b.id_marketing = m.id_marketing
                                  WHERE 1=1";

                        if (!empty($filter_keyword)) {
                            $query .= " AND (k.name_lengkap LIKE '%$filter_keyword%'
                                       OR k.no_whatsapp LIKE '%$filter_keyword%'
                                       OR b.lembaga LIKE '%$filter_keyword%'
                                       OR b.no_pengajuan LIKE '%$filter_keyword%')";
                        }
                        if (!empty($filter_hasil)) {
                            $query .= " AND b.hasil_bi = '$filter_hasil'";
                        }
                        if ($filter_marketing > 0) {
                            $query .= " AND b.id_marketing = $filter_marketing";
                        }
                        switch ($filter_sort) {
                            case 'no_pengajuan_asc':
                                $query .= " ORDER BY b.no_pengajuan ASC, b.id_bi_checking DESC";
                                break;
                            case 'no_pengajuan_desc':
                                $query .= " ORDER BY b.no_pengajuan DESC, b.id_bi_checking DESC";
                                break;
                            case 'tgl_asc':
                                $query .= " ORDER BY b.tgl_cek ASC, b.id_bi_checking ASC";
                                break;
                            default:
                                $query .= " ORDER BY b.tgl_cek DESC, b.id_bi_checking DESC";
                        }

                        $q_bi = mysqli_query($koneksi, $query);

                        // Pre-load data untuk dropdown (hindari N+1 query)
                        $arr_konsumen = [];
                        $qk = mysqli_query($koneksi, "SELECT id_konsumen, name_lengkap, pekerjaan FROM data_konsumen ORDER BY name_lengkap ASC");
                        while ($row = mysqli_fetch_assoc($qk)) $arr_konsumen[] = $row;

                        $arr_marketing = [];
                        $qm = mysqli_query($koneksi, "SELECT id_marketing, nama_marketing FROM data_marketing WHERE status_aktif = 'Aktif' ORDER BY nama_marketing ASC");
                        while ($row = mysqli_fetch_assoc($qm)) $arr_marketing[] = $row;

                        if ($q_bi && mysqli_num_rows($q_bi) > 0) {
                            while ($b = mysqli_fetch_assoc($q_bi)) {
                                $badge = 'bg-warning text-dark';
                                $ikon  = 'bi-hourglass-split';
                                if ($b['hasil_bi'] == 'Lulus')            { $badge = 'bg-success text-white'; $ikon = 'bi-check-circle'; }
                                elseif ($b['hasil_bi'] == 'Dipertimbangkan') { $badge = 'bg-info text-dark'; $ikon = 'bi-question-circle'; }
                                elseif ($b['hasil_bi'] == 'Tidak Lulus')  { $badge = 'bg-danger text-white'; $ikon = 'bi-x-circle'; }
                        ?>
                        <tr>
                            <td><span class="badge bg-light text-secondary border px-2 py-1">#BI-<?= $b['id_bi_checking'] ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($b['name_lengkap'] ?? 'Tanpa Nama') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($b['pekerjaan'] ?? '-') ?></div>
                                <?php if (!empty($b['no_whatsapp']) && $b['no_whatsapp'] != '-'): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $b['no_whatsapp']) ?>" target="_blank" class="small text-success text-decoration-none">
                                        <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($b['no_whatsapp']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b['id_marketing'])): ?>
                                    <a href="marketing.php" class="text-decoration-none" title="Lihat Tim Marketing">
                                        <div class="fw-semibold text-dark d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-person-badge text-success"></i> <?= htmlspecialchars($b['nama_marketing'] ?? '-') ?>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($b['tgl_cek'])) ?></td>
                            <td><span class="text-muted small"><?= htmlspecialchars($b['lembaga'] ?? '-') ?></span></td>
                            <td><span class="text-muted small"><?= htmlspecialchars($b['no_pengajuan'] ?? '-') ?></span></td>
                            <td><span class="badge <?= $badge ?> px-2 py-1"><i class="bi <?= $ikon ?> me-1"></i><?= htmlspecialchars($b['hasil_bi']) ?></span></td>
                            <td class="text-muted small" style="max-width: 220px;"><?= htmlspecialchars($b['catatan'] ?? '-') ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEditBi<?= $b['id_bi_checking'] ?>" title="Edit BI Checking">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalHapusBi" data-id="<?= $b['id_bi_checking'] ?>" data-info="<?= htmlspecialchars($b['name_lengkap'] ?? 'Konsumen') ?>" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <!-- MODAL EDIT BI CHECKING -->
                                <div class="modal fade text-start" id="modalEditBi<?= $b['id_bi_checking'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-4 shadow">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit BI Checking</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="bi_checking.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_bi_checking" value="<?= $b['id_bi_checking'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">KONSUMEN</label>
                                                        <select name="id_konsumen" class="form-select rounded-3" required>
                                                            <option value="">-- Pilih Konsumen --</option>
                                                            <?php foreach ($arr_konsumen as $k): ?>
                                                                <?php $sel = ($k['id_konsumen'] == $b['id_konsumen']) ? 'selected' : ''; ?>
                                                                <option value="<?= $k['id_konsumen'] ?>" $sel><?= htmlspecialchars($k['name_lengkap']) ?> - (<?= htmlspecialchars($k['pekerjaan']) ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">MARKETER / SALES</label>
                                        <select name="id_marketing" class="form-select rounded-3">
                                            <option value="">-- Pilih Marketing --</option>
                                            <?php foreach ($arr_marketing as $mkt): ?>
                                                <?php $sel_mkt = ($b['id_marketing'] == $mkt['id_marketing']) ? 'selected' : ''; ?>
                                                <option value="<?= $mkt['id_marketing'] ?>" $sel_mkt><?= htmlspecialchars($mkt['nama_marketing']) ?></option>
                                            <?php endforeach; ?>
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">TANGGAL CEK</label>
                                        <input type="date" name="tgl_cek" class="form-control rounded-3" value="<?= $b['tgl_cek'] ?>" required>
                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">LEMBAGA / SUMBER</label>
                                                        <input type="text" name="lembaga" class="form-control rounded-3" value="<?= htmlspecialchars($b['lembaga']) ?>" placeholder="Contoh: SLIK OJK, Bank BTN, BI">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">NO. PENGAJUAN / REFERENSI</label>
                                                        <input type="text" name="no_pengajuan" class="form-control rounded-3" value="<?= htmlspecialchars($b['no_pengajuan']) ?>" placeholder="Contoh: SLK-2026-0001">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">HASIL BI CHECKING</label>
                                                        <select name="hasil_bi" class="form-select rounded-3" required>
                                                            <?php foreach (['Dalam Proses', 'Lulus', 'Dipertimbangkan', 'Tidak Lulus'] as $st): ?>
                                                                <option value="<?= $st ?>" <?= ($b['hasil_bi'] == $st) ? 'selected' : '' ?>><?= $st ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">CATATAN</label>
                                                        <textarea name="catatan" class="form-control rounded-3" rows="3" placeholder="Ringkasan hasil pengecekan..."><?= htmlspecialchars($b['catatan']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_bi" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='9' class='text-center text-muted py-4'>Tidak ada data BI checking yang sesuai kriteria filter.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL TAMBAH BI CHECKING -->
<div class="modal fade" id="modalBi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold"><i class="bi bi-clipboard-plus me-2"></i>Tambah BI Checking Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="bi_checking.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">KONSUMEN</label>
                        <select name="id_konsumen" id="select_konsumen_bi" class="form-select rounded-3" required>
                            <option value="">-- Pilih Konsumen --</option>
                            <?php foreach ($arr_konsumen as $k): ?>
                                <?php $sel = ($preselect_konsumen > 0 && $k['id_konsumen'] == $preselect_konsumen) ? 'selected' : ''; ?>
                                <option value="<?= $k['id_konsumen'] ?>" $sel><?= htmlspecialchars($k['name_lengkap']) ?> - (<?= htmlspecialchars($k['pekerjaan']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">MARKETER / SALES</label>
                        <select name="id_marketing" class="form-select rounded-3">
                            <option value="">-- Pilih Marketing --</option>
                            <?php foreach ($arr_marketing as $mkt): ?>
                                <option value="<?= $mkt['id_marketing'] ?>"><?= htmlspecialchars($mkt['nama_marketing']) ?></option>
                            <?php endforeach; ?>
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">TANGGAL CEK</label>
                        <input type="date" name="tgl_cek" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">LEMBAGA / SUMBER</label>
                        <input type="text" name="lembaga" class="form-control rounded-3" placeholder="Contoh: SLIK OJK, Bank BTN, BI">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">NO. PENGAJUAN / REFERENSI</label>
                        <input type="text" name="no_pengajuan" class="form-control rounded-3" placeholder="Contoh: SLK-2026-0001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">HASIL BI CHECKING</label>
                        <select name="hasil_bi" class="form-select rounded-3" required>
                            <option value="Dalam Proses">Dalam Proses</option>
                            <option value="Lulus">Lulus</option>
                            <option value="Dipertimbangkan">Dipertimbangkan</option>
                            <option value="Tidak Lulus">Tidak Lulus</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">CATATAN</label>
                        <textarea name="catatan" class="form-control rounded-3" rows="3" placeholder="Ringkasan hasil pengecekan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan_bi" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal fade" id="modalHapusBi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash text-danger display-4 mb-3 d-block"></i>
                <h6 class="fw-bold fs-5">Apakah Anda yakin ingin menghapus data BI Checking ini?</h6>
                <p class="text-muted small mb-0" id="infoBiHapus">-</p>
            </div>
            <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnConfirmDeleteBi" class="btn btn-danger px-4 rounded-3">Ya, Hapus Data</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalBi = document.getElementById('modalBi');
    if (modalBi) {
        var konsumenId = <?= $preselect_konsumen ?>;
        if (konsumenId > 0) {
            var sel = document.getElementById('select_konsumen_bi');
            if (sel) sel.value = String(konsumenId);
            var modalInst = bootstrap.Modal.getOrCreateInstance(modalBi);
            modalInst.show();
        }
    }

    var modalHapusBi = document.getElementById('modalHapusBi');
    if (modalHapusBi) {
        modalHapusBi.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var info = button.getAttribute('data-info');
            document.getElementById('infoBiHapus').innerText = 'Konsumen: ' + info + ' (#BI-' + id + ')';
            document.getElementById('btnConfirmDeleteBi').href = 'bi_checking.php?action=hapus&id=' + id + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
