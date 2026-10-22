<?php

session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
include "includes/koneksi.php";

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// AUTO-UPDATE: Belum Dikerjakan → Sedang Berlangsung saat jam_mulai tiba
$now_h = date('H:i:s');
$today = date('Y-m-d');
$koneksi->query("UPDATE data_laporan_harian SET status='Sedang Berlangsung', updated_at=NOW()
    WHERE tanggal='$today' AND jam_mulai IS NOT NULL AND jam_mulai <= '$now_h'
    AND (jam_selesai IS NULL OR jam_selesai > '$now_h')
    AND status='Belum Dikerjakan'");

// AUTO-UPDATE: Sedang Berlangsung / Belum → Selesai saat jam_selesai tiba
$koneksi->query("UPDATE data_laporan_harian SET status='Selesai', updated_at=NOW()
    WHERE tanggal='$today' AND jam_selesai IS NOT NULL AND jam_selesai <= '$now_h'
    AND status IN ('Sedang Berlangsung','Belum Dikerjakan')");

// Tambah Laporan
if (isset($_POST['tambah_laporan'])) {
    $tanggal    = sdb_real_escape_string($koneksi, $_POST['tanggal']);
    $kategori   = sdb_real_escape_string($koneksi, $_POST['kategori']);
    $nama       = sdb_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $judul      = sdb_real_escape_string($koneksi, $_POST['judul_kegiatan']);
    $detail     = sdb_real_escape_string($koneksi, $_POST['detail_kegiatan']);
    $jam_mulai  = !empty($_POST['jam_mulai']) ? "'" . sdb_real_escape_string($koneksi, $_POST['jam_mulai']) . "'" : 'NULL';
    $jam_selesai= !empty($_POST['jam_selesai']) ? "'" . sdb_real_escape_string($koneksi, $_POST['jam_selesai']) . "'" : 'NULL';
    $status     = sdb_real_escape_string($koneksi, $_POST['status']);
    $catatan    = sdb_real_escape_string($koneksi, $_POST['catatan']);

    sdb_query($koneksi, "INSERT INTO data_laporan_harian (tanggal, kategori, nama_lengkap, judul_kegiatan, detail_kegiatan, jam_mulai, jam_selesai, status, catatan)
                            VALUES ('$tanggal','$kategori','$nama','$judul','$detail',$jam_mulai,$jam_selesai,'$status','$catatan')");
    header("Location: laporan_harian.php");
    exit();
}

// Edit Laporan
if (isset($_POST['edit_laporan'])) {
    $id         = intval($_POST['id_laporan']);
    $tanggal    = sdb_real_escape_string($koneksi, $_POST['tanggal']);
    $kategori   = sdb_real_escape_string($koneksi, $_POST['kategori']);
    $nama       = sdb_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $judul      = sdb_real_escape_string($koneksi, $_POST['judul_kegiatan']);
    $detail     = sdb_real_escape_string($koneksi, $_POST['detail_kegiatan']);
    $jam_mulai  = !empty($_POST['jam_mulai']) ? "'" . sdb_real_escape_string($koneksi, $_POST['jam_mulai']) . "'" : 'NULL';
    $jam_selesai= !empty($_POST['jam_selesai']) ? "'" . sdb_real_escape_string($koneksi, $_POST['jam_selesai']) . "'" : 'NULL';
    $status     = sdb_real_escape_string($koneksi, $_POST['status']);
    $catatan    = sdb_real_escape_string($koneksi, $_POST['catatan']);

    sdb_query($koneksi, "UPDATE data_laporan_harian SET tanggal='$tanggal', kategori='$kategori', nama_lengkap='$nama', judul_kegiatan='$judul', detail_kegiatan='$detail', jam_mulai=$jam_mulai, jam_selesai=$jam_selesai, status='$status', catatan='$catatan' WHERE id=$id");
    header("Location: laporan_harian.php");
    exit();
}

// Hapus Laporan
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = intval($_GET['id']);
    sdb_query($koneksi, "DELETE FROM data_laporan_harian WHERE id = $id_hapus");
    header("Location: laporan_harian.php");
    exit();
}

// Filter
$where = "1=1";
$filter_tanggal   = $_GET['tanggal']   ?? '';
$filter_kategori  = $_GET['kategori']  ?? '';
$filter_status    = $_GET['status']    ?? '';
$filter_keyword   = $_GET['keyword']   ?? '';

if (!empty($filter_tanggal))  $where .= " AND tanggal = '" . sdb_real_escape_string($koneksi, $filter_tanggal) . "'";
if (!empty($filter_kategori)) $where .= " AND kategori = '" . sdb_real_escape_string($koneksi, $filter_kategori) . "'";
if (!empty($filter_status))   $where .= " AND status = '" . sdb_real_escape_string($koneksi, $filter_status) . "'";
if (!empty($filter_keyword))  {
    $kw = sdb_real_escape_string($koneksi, $filter_keyword);
    $where .= " AND (nama_lengkap LIKE '%$kw%' OR judul_kegiatan LIKE '%$kw%' OR detail_kegiatan LIKE '%$kw%')";
}

$q_laporan = sdb_query($koneksi, "SELECT * FROM data_laporan_harian WHERE $where ORDER BY tanggal DESC, jam_mulai DESC");
$jml_data = sdb_num_rows($q_laporan);

// Statistik
$stat = ['total'=>0, 'selesai'=>0, 'proses'=>0, 'terhambat'=>0, 'belum'=>0];
$q_stat = sdb_query($koneksi, "SELECT 
    COUNT(*) AS total,
    SUM(status='Selesai') AS selesai,
    SUM(status='Sedang Berlangsung') AS proses,
    SUM(status='Terhambat') AS terhambat,
    SUM(status='Belum Dikerjakan') AS belum
FROM data_laporan_harian");
if ($s = sdb_fetch_assoc($q_stat)) {
    $stat['total']    = intval($s['total']);
    $stat['selesai']  = intval($s['selesai']);
    $stat['proses']   = intval($s['proses']);
    $stat['terhambat']= intval($s['terhambat']);
    $stat['belum']    = intval($s['belum']);
}

// Query params untuk link PDF
$filter_params = http_build_query(array_filter([
    'tanggal'  => $filter_tanggal,
    'kategori' => $filter_kategori,
    'status'   => $filter_status,
    'keyword'  => $filter_keyword,
]));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Laporan Harian - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .badge-status-selesai { background: #dbeafe; color: #2563eb; }
        .badge-status-proses { background: #dbeafe; color: #1d4ed8; }
        .badge-status-terhambat { background: #fee2e2; color: #dc2626; }
        .badge-status-belum { background: #fef9c3; color: #a16207; }
        .badge-kategori-admin { background: #ede9fe; color: #7c3aed; }
        .badge-kategori-marketing { background: #fce7f3; color: #db2777; }
        .badge-kategori-konten { background: #cffafe; color: #0e7490; }
        .badge-kategori-keuangan { background: #dbeafe; color: #2563eb; }
        .badge-kategori-lapangan { background: #fef3c7; color: #b45309; }
        .badge-kategori-operasional { background: #e2e8f0; color: #475569; }
        .kategori-badge { padding: 4px 12px; }
        @media print {
            .sidebar, .sidebar-toggle-btn, .app-overlay, .btn, .no-print { display: none !important; }
            .main-wrapper { margin-left: 0 !important; padding: 10px !important; }
        }
    </style>
</head>
<body>

<?php include "sidebar.php"; ?>

<div class="app-overlay" onclick="document.body.classList.toggle('sidebar-open')"></div>
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Laporan Harian Pekerjaan</h4>
            <p class="text-muted small mb-0">Catatan aktivitas harian Admin & Marketing Agent Property.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="print/cetak_laporan_harian.php?<?= $filter_params ?>" target="_blank" class="btn btn-outline-danger rounded-pill px-3">
                <i class="bi bi-file-earmark-pdf me-1"></i> Unduh PDF
            </a>
            <button class="btn btn-green" style="color:#fff !important" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-circle me-1"></i> Tambah Laporan
            </button>
        </div>
    </div>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4 text-dark" id="stat-total"><?= $stat['total'] ?></div>
                <div class="text-muted small">Total</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#2563eb" id="stat-selesai"><?= $stat['selesai'] ?></div>
                <div class="text-muted small">Selesai</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#1d4ed8" id="stat-proses"><?= $stat['proses'] ?></div>
                <div class="text-muted small">Berlangsung</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#dc2626" id="stat-terhambat"><?= $stat['terhambat'] ?></div>
                <div class="text-muted small">Terhambat</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card text-center">
                <div class="fw-bold fs-4" style="color:#a16207" id="stat-belum"><?= $stat['belum'] ?></div>
                <div class="text-muted small">Belum Dikerjakan</div>
            </div>
        </div>
    </div>

    <!-- FILTER -->
    <div class="content-card mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal</label>
                <input type="date" name="tanggal" class="form-control form-control-sm rounded-3" value="<?= htmlspecialchars($filter_tanggal) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Kategori</label>
                <select name="kategori" class="form-select form-select-sm rounded-3">
                    <option value="">Semua</option>
                    <?php foreach (['Admin','Marketing','Konten Kreator','Keuangan','Lapangan','Operasional'] as $kat): ?>
                    <option value="<?= $kat ?>" <?= $filter_kategori === $kat ? 'selected' : '' ?>><?= $kat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm rounded-3">
                    <option value="">Semua</option>
                    <option value="Selesai" <?= $filter_status === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Sedang Berlangsung" <?= $filter_status === 'Sedang Berlangsung' ? 'selected' : '' ?>>Berlangsung</option>
                    <option value="Terhambat" <?= $filter_status === 'Terhambat' ? 'selected' : '' ?>>Terhambat</option>
                    <option value="Belum Dikerjakan" <?= $filter_status === 'Belum Dikerjakan' ? 'selected' : '' ?>>Belum Dikerjakan</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Keyword</label>
                <input type="text" name="keyword" class="form-control form-control-sm rounded-3" placeholder="Cari nama / kegiatan..." value="<?= htmlspecialchars($filter_keyword) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-search me-1"></i> Filter</button>
                <a href="laporan_harian.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- TABEL -->
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small fw-semibold"><?= $jml_data ?> data ditemukan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Nama</th>
                        <th>Kegiatan</th>
                        <th>Jam</th>
                        <th>Status</th>
                        <th>Catatan</th>
                        <th style="width:80px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($jml_data > 0): ?>
                        <?php $no = 1; while ($row = sdb_fetch_assoc($q_laporan)): ?>
                            <?php
                                $badgeStatus = 'badge-status-selesai';
                                if ($row['status'] === 'Sedang Berlangsung') $badgeStatus = 'badge-status-proses';
                                elseif ($row['status'] === 'Terhambat') $badgeStatus = 'badge-status-terhambat';
                                elseif ($row['status'] === 'Belum Dikerjakan') $badgeStatus = 'badge-status-belum';

                                $katBadges = [
                                    'Admin' => 'badge-kategori-admin',
                                    'Marketing' => 'badge-kategori-marketing',
                                    'Konten Kreator' => 'badge-kategori-konten',
                                    'Keuangan' => 'badge-kategori-keuangan',
                                    'Lapangan' => 'badge-kategori-lapangan',
                                    'Operasional' => 'badge-kategori-operasional',
                                ];
                                $badgeKategori = $katBadges[$row['kategori']] ?? 'badge-kategori-operasional';
                                $jam = '';
                                if ($row['jam_mulai'] && $row['jam_selesai']) {
                                    $jam = date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai']));
                                } elseif ($row['jam_mulai']) {
                                    $jam = date('H:i', strtotime($row['jam_mulai'])) . ' - ...';
                                }
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><span class="fw-semibold"><?= date('d M Y', strtotime($row['tanggal'])) ?></span></td>
                                <td><span class="badge <?= $badgeKategori ?> rounded-pill px-2"><?= $row['kategori'] ?></span></td>
                                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['judul_kegiatan']) ?></div>
                                    <?php if (!empty($row['detail_kegiatan'])): ?>
                                        <div class="text-muted small text-truncate" style="max-width:300px;"><?= htmlspecialchars($row['detail_kegiatan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted small"><?= $jam ?: '-' ?></span></td>
                                <td><span class="badge <?= $badgeStatus ?> rounded-pill px-2"><?= $row['status'] ?></span></td>
                                <td><span class="text-muted small text-truncate" style="max-width:150px; display:block;"><?= htmlspecialchars($row['catatan'] ?? '') ?></span></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary rounded-pill px-2" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id'] ?>" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="laporan_harian.php?action=hapus&id=<?= $row['id'] ?>&token=<?= htmlspecialchars(csrf_token()) ?>" class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="return confirm('Yakin hapus laporan ini?')" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>

                                    <!-- Modal Edit -->
                                    <div class="modal fade" id="modalEdit<?= $row['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
                                                <div class="modal-header text-white" style="background-color: var(--primary-blue);">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Laporan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="laporan_harian.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="edit_laporan" value="1">
                                                    <input type="hidden" name="id_laporan" value="<?= $row['id'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold">Tanggal</label>
                                                                <input type="date" name="tanggal" class="form-control rounded-3" value="<?= $row['tanggal'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold">Kategori</label>
                                                                <select name="kategori" class="form-select rounded-3" required>
                                                                    <?php foreach (['Admin','Marketing','Konten Kreator','Keuangan','Lapangan','Operasional'] as $kat): ?>
                                                                    <option value="<?= $kat ?>" <?= $row['kategori']===$kat?'selected':'' ?>><?= $kat ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold">Nama</label>
                                                                <input type="text" name="nama_lengkap" class="form-control rounded-3" value="<?= htmlspecialchars($row['nama_lengkap']) ?>" required>
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label small fw-semibold">Judul Kegiatan</label>
                                                                <input type="text" name="judul_kegiatan" class="form-control rounded-3" value="<?= htmlspecialchars($row['judul_kegiatan']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold">Status</label>
                                                                <select name="status" class="form-select rounded-3" required>
                                                                    <option value="Selesai" <?= $row['status']==='Selesai'?'selected':'' ?>>Selesai</option>
                                                                    <option value="Sedang Berlangsung" <?= $row['status']==='Sedang Berlangsung'?'selected':'' ?>>Sedang Berlangsung</option>
                                                                    <option value="Terhambat" <?= $row['status']==='Terhambat'?'selected':'' ?>>Terhambat</option>
                                                                    <option value="Belum Dikerjakan" <?= $row['status']==='Belum Dikerjakan'?'selected':'' ?>>Belum Dikerjakan</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label small fw-semibold">Detail Kegiatan</label>
                                                                <textarea name="detail_kegiatan" class="form-control rounded-3" rows="3"><?= htmlspecialchars($row['detail_kegiatan'] ?? '') ?></textarea>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label small fw-semibold">Jam Mulai</label>
                                                                <input type="time" name="jam_mulai" class="form-control rounded-3" value="<?= $row['jam_mulai'] ? date('H:i', strtotime($row['jam_mulai'])) : '' ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label small fw-semibold">Jam Selesai</label>
                                                                <input type="time" name="jam_selesai" class="form-control rounded-3" value="<?= $row['jam_selesai'] ? date('H:i', strtotime($row['jam_selesai'])) : '' ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Catatan</label>
                                                                <input type="text" name="catatan" class="form-control rounded-3" value="<?= htmlspecialchars($row['catatan'] ?? '') ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-green px-4 rounded-pill" style="color:#fff !important">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Belum ada laporan harian.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
            <div class="modal-header text-white" style="background-color: var(--primary-blue);">
                <h5 class="modal-title fw-bold"><i class="bi bi-clipboard-plus me-2"></i>Tambah Laporan Harian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="laporan_harian.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="tambah_laporan" value="1">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tanggal</label>
                            <input type="date" name="tanggal" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Kategori</label>
                            <select name="kategori" class="form-select rounded-3" required>
                                <?php foreach (['Admin','Marketing','Konten Kreator','Keuangan','Lapangan','Operasional'] as $kat): ?>
                                <option value="<?= $kat ?>"><?= $kat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nama</label>
                            <input type="text" name="nama_lengkap" class="form-control rounded-3" placeholder="Nama pelapor" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Judul Kegiatan</label>
                            <input type="text" name="judul_kegiatan" class="form-control rounded-3" placeholder="Contoh: Koordinasi pemberkasan KPR konsumen" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select rounded-3" required>
                                <option value="Selesai">Selesai</option>
                                <option value="Sedang Berlangsung">Sedang Berlangsung</option>
                                <option value="Terhambat">Terhambat</option>
                                <option value="Belum Dikerjakan">Belum Dikerjakan</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Detail Kegiatan</label>
                            <textarea name="detail_kegiatan" class="form-control rounded-3" rows="3" placeholder="Jelaskan detail kegiatan yang dilakukan..."></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Jam Mulai</label>
                            <input type="time" name="jam_mulai" class="form-control rounded-3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Jam Selesai</label>
                            <input type="time" name="jam_selesai" class="form-control rounded-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Catatan</label>
                            <input type="text" name="catatan" class="form-control rounded-3" placeholder="Catatan tambahan (opsional)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-green px-4 rounded-pill" style="color:#fff !important">Simpan Laporan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
(function() {
    function updateStats(data) {
        if (!data || !data.stats) return;
        var s = data.stats;
        var el;
        el = document.getElementById('stat-total');    if (el) el.textContent = s.total;
        el = document.getElementById('stat-selesai');  if (el) el.textContent = s.selesai;
        el = document.getElementById('stat-proses');   if (el) el.textContent = s.proses;
        el = document.getElementById('stat-terhambat');if (el) el.textContent = s.terhambat;
        el = document.getElementById('stat-belum');    if (el) el.textContent = s.belum;
    }

    function poll() {
        fetch('includes/auto_update_laporan.php', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) { updateStats(d); })
            .catch(function() {});
    }

    setInterval(poll, 30000);
})();
</script>
</body>
</html>
