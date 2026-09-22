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

// Tambah prospek
if (isset($_POST['tambah_prospek'])) {
    $nama    = mysqli_real_escape_string($koneksi, $_POST['name_lengkap']);
    $ktp     = mysqli_real_escape_string($koneksi, $_POST['no_ktp'] ?? '');
    $kerja   = mysqli_real_escape_string($koneksi, $_POST['pekerjaan'] ?? '');
    $wa      = mysqli_real_escape_string($koneksi, $_POST['no_whatsapp'] ?? '');
    $sumber  = mysqli_real_escape_string($koneksi, $_POST['sumber']);
    $cluster = mysqli_real_escape_string($koneksi, $_POST['cluster_minat'] ?? '');
    $unit    = mysqli_real_escape_string($koneksi, $_POST['unit_minat'] ?? '');
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');
    $status  = mysqli_real_escape_string($koneksi, $_POST['status_followup']);
    $tgl_fu  = !empty($_POST['tanggal_followup']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['tanggal_followup']) . "'" : 'NULL';

    mysqli_query($koneksi, "INSERT INTO data_prospek (name_lengkap, no_ktp, pekerjaan, no_whatsapp, sumber, cluster_minat, unit_minat, catatan, status_followup, tanggal_followup)
        VALUES ('$nama','$ktp','$kerja','$wa','$sumber','$cluster','$unit','$catatan','$status',$tgl_fu)");
    header("Location: prospek.php");
    exit();
}

// Edit prospek
if (isset($_POST['edit_prospek'])) {
    $id = intval($_POST['id_prospek']);
    $nama    = mysqli_real_escape_string($koneksi, $_POST['name_lengkap']);
    $ktp     = mysqli_real_escape_string($koneksi, $_POST['no_ktp'] ?? '');
    $kerja   = mysqli_real_escape_string($koneksi, $_POST['pekerjaan'] ?? '');
    $wa      = mysqli_real_escape_string($koneksi, $_POST['no_whatsapp'] ?? '');
    $sumber  = mysqli_real_escape_string($koneksi, $_POST['sumber']);
    $cluster = mysqli_real_escape_string($koneksi, $_POST['cluster_minat'] ?? '');
    $unit    = mysqli_real_escape_string($koneksi, $_POST['unit_minat'] ?? '');
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');
    $status  = mysqli_real_escape_string($koneksi, $_POST['status_followup']);
    $tgl_fu  = !empty($_POST['tanggal_followup']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['tanggal_followup']) . "'" : 'NULL';

    mysqli_query($koneksi, "UPDATE data_prospek SET
        name_lengkap='$nama', no_ktp='$ktp', pekerjaan='$kerja', no_whatsapp='$wa',
        sumber='$sumber', cluster_minat='$cluster', unit_minat='$unit', catatan='$catatan',
        status_followup='$status', tanggal_followup=$tgl_fu WHERE id_prospek=$id");
    header("Location: prospek.php");
    exit();
}

// Hapus prospek (dengan token CSRF di URL)
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = intval($_GET['id']);
    mysqli_query($koneksi, "DELETE FROM data_prospek WHERE id_prospek = $id_hapus");
    header("Location: prospek.php");
    exit();
}

// TANDAI DEAL â†’ pindah ke data_konsumen
if (isset($_POST['deal_prospek'])) {
    $id = intval($_POST['id_prospek']);
    $q = mysqli_query($koneksi, "SELECT * FROM data_prospek WHERE id_prospek = $id");
    if ($p = mysqli_fetch_assoc($q)) {
        $nama    = mysqli_real_escape_string($koneksi, $p['name_lengkap']);
        $ktp     = mysqli_real_escape_string($koneksi, $p['no_ktp'] ?? '');
        $kerja   = mysqli_real_escape_string($koneksi, $p['pekerjaan'] ?? '');
        $wa      = mysqli_real_escape_string($koneksi, $p['no_whatsapp'] ?? '');

        // Cegah duplikat berdasarkan no_ktp atau no_whatsapp
        $dup = false;
        if (!empty($ktp)) {
            $cek = mysqli_query($koneksi, "SELECT id_konsumen FROM data_konsumen WHERE no_ktp = '$ktp'");
            if ($cek && mysqli_num_rows($cek) > 0) $dup = true;
        }
        if (!$dup && !empty($wa)) {
            $cek = mysqli_query($koneksi, "SELECT id_konsumen FROM data_konsumen WHERE no_whatsapp = '$wa'");
            if ($cek && mysqli_num_rows($cek) > 0) $dup = true;
        }

        if ($dup) {
            // Sudah jadi konsumen, cukup tandai sebagai Deal (tidak insert ganda)
            mysqli_query($koneksi, "UPDATE data_prospek SET status_followup='Deal' WHERE id_prospek=$id");
            header("Location: prospek.php?pesan=duplikat");
            exit();
        }

        mysqli_query($koneksi, "INSERT INTO data_konsumen (name_lengkap, no_ktp, pekerjaan, no_whatsapp)
            VALUES ('$nama', '$ktp', '$kerja', '$wa')");
        $new_id = mysqli_insert_id($koneksi);
        mysqli_query($koneksi, "UPDATE data_prospek SET status_followup='Deal' WHERE id_prospek=$id");
        header("Location: prospek.php?pesan=deal&id=$new_id");
        exit();
    }
    header("Location: prospek.php");
    exit();
}

// FILTER
$filter_status  = $_GET['status']  ?? '';
$filter_sumber  = $_GET['sumber']  ?? '';
$filter_keyword = $_GET['keyword'] ?? '';

$where = "1=1";
if (!empty($filter_status))  $where .= " AND status_followup = '" . mysqli_real_escape_string($koneksi, $filter_status) . "'";
if (!empty($filter_sumber))  $where .= " AND sumber = '" . mysqli_real_escape_string($koneksi, $filter_sumber) . "'";
if (!empty($filter_keyword)) {
    $kw = mysqli_real_escape_string($koneksi, $filter_keyword);
    $where .= " AND (name_lengkap LIKE '%$kw%' OR no_whatsapp LIKE '%$kw%' OR pekerjaan LIKE '%$kw%' OR cluster_minat LIKE '%$kw%')";
}

$q_prospek = mysqli_query($koneksi, "SELECT * FROM data_prospek WHERE $where ORDER BY
    FIELD(status_followup,'Baru','Sudah Dihubungi','Minat','Sudah Survey','Deal','Tidak Lanjut'), tanggal_followup ASC, id_prospek DESC");
$jml_data = $q_prospek ? mysqli_num_rows($q_prospek) : 0;

// Statistik
$stat = ['total'=>0,'baru'=>0,'dihubungi'=>0,'minat'=>0,'survey'=>0,'deal'=>0,'tidak'=>0];
$q_stat = mysqli_query($koneksi, "SELECT
    COUNT(*) AS total,
    SUM(status_followup='Baru') AS baru,
    SUM(status_followup='Sudah Dihubungi') AS dihubungi,
    SUM(status_followup='Minat') AS minat,
    SUM(status_followup='Sudah Survey') AS survey,
    SUM(status_followup='Deal') AS deal,
    SUM(status_followup='Tidak Lanjut') AS tidak
FROM data_prospek");
if ($s = mysqli_fetch_assoc($q_stat)) {
    foreach ($stat as $k => $v) $stat[$k] = intval($s[$k] ?? 0);
}

// Daftar cluster untuk dropdown (dimuat sekali ke array agar tidak habis terpakai dalam loop)
$klaster = [];
$q_cluster = mysqli_query($koneksi, "SELECT cluster_name FROM data_cluster ORDER BY cluster_name");
while ($_cl = mysqli_fetch_assoc($q_cluster)) $klaster[] = $_cl['cluster_name'];

// Kumpulan modal (edit / deal) yang dibuat per baris
$modals = '';

// Notifikasi pesan
$pesan = '';
if (isset($_GET['pesan']) && $_GET['pesan'] === 'deal') {
    $pesan = '<div class="alert alert-success alert-dismissible fade show rounded-4 small mb-3" role="alert">
        <i class="bi bi-check-circle me-1"></i> Prospek ditandai <strong>Deal</strong> dan berhasil dipindahkan ke <strong>Data Konsumen</strong>.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
} elseif (isset($_GET['pesan']) && $_GET['pesan'] === 'duplikat') {
    $pesan = '<div class="alert alert-warning alert-dismissible fade show rounded-4 small mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> Data sudah ada di konsumen (duplikat). Prospek hanya ditandai Deal tanpa menambah data ganda.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <?php include "pwa_head.php"; ?>
    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Prospek Konsumen - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .badge-fu-baru { background: #fee2e2; color: #dc2626; }
        .badge-fu-hubungi { background: #dbeafe; color: #1d4ed8; }
        .badge-fu-minat { background: #fef9c3; color: #a16207; }
        .badge-fu-survey { background: #ede9fe; color: #7c3aed; }
        .badge-fu-deal { background: #dbeafe; color: #2563eb; }
        .badge-fu-tidak { background: #f1f5f9; color: #64748b; }
        .badge-sumber-pameran { background: #fce7f3; color: #db2777; }
        .badge-sumber-walkin { background: #cffafe; color: #0e7490; }
        .badge-sumber-ig { background: #ede9fe; color: #7c3aed; }
        .badge-sumber-fb { background: #dbeafe; color: #1d4ed8; }
        .badge-sumber-wa { background: #dbeafe; color: #2563eb; }
        .badge-sumber-ref { background: #fef3c7; color: #b45309; }
        .badge-sumber-lain { background: #f1f5f9; color: #475569; }
        @media print { .sidebar, .btn, .no-print { display: none !important; } .main-wrapper { margin-left: 0 !important; } }
    </style>
</head>
<body>
<?php include "sidebar.php"; ?>
<div class="app-overlay" onclick="document.body.classList.toggle('sidebar-open')"></div>
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Prospek Konsumen</h4>
            <p class="text-muted small mb-0">Calon konsumen yang belum menjadi pembeli. Tandai Deal untuk memindahkan ke Data Konsumen.</p>
        </div>
        <button class="btn btn-green" style="color:#fff !important" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-person-plus me-1"></i> Tambah Prospek
        </button>
    </div>

    <?= $pesan ?>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['Total','total','#1e3a8a','bi-people'],
            ['Baru','baru','#dc2626','bi-person-plus'],
            ['Dihubungi','dihubungi','#1d4ed8','bi-telephone'],
            ['Minat','minat','#a16207','bi-hand-thumbs-up'],
            ['Survey','survey','#7c3aed','bi-house-check'],
            ['Deal','deal','#2563eb','bi-check-circle'],
            ['Tidak Lanjut','tidak','#64748b','bi-x-octagon'],
        ];
        foreach ($statCards as $sc):
        ?>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="stat-card text-center">
                <i class="bi <?= $sc[2] ?> fs-4 mb-1" style="color:<?= $sc[3] ?>"></i>
                <div class="fw-bold fs-4" style="color:<?= $sc[3] ?>"><?= $stat[$sc[1]] ?></div>
                <div class="text-muted small"><?= $sc[0] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FILTER -->
    <div class="content-card mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status Follow-up</label>
                <select name="status" class="form-select form-select-sm rounded-3">
                    <option value="">Semua</option>
                    <?php foreach (['Baru','Sudah Dihubungi','Minat','Sudah Survey','Deal','Tidak Lanjut'] as $st): ?>
                    <option value="<?= $st ?>" <?= $filter_status === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Sumber</label>
                <select name="sumber" class="form-select form-select-sm rounded-3">
                    <option value="">Semua</option>
                    <?php foreach (['Pameran','Walk-in Kantor','Instagram','Facebook','WhatsApp','Referensi','Lainnya'] as $sb): ?>
                    <option value="<?= $sb ?>" <?= $filter_sumber === $sb ? 'selected' : '' ?>><?= $sb ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Keyword</label>
                <input type="text" name="keyword" class="form-control form-control-sm rounded-3" placeholder="Cari nama / WA / pekerjaan..." value="<?= htmlspecialchars($filter_keyword) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-search me-1"></i> Filter</button>
                <a href="prospek.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- TABEL -->
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small fw-semibold"><?= $jml_data ?> prospek ditemukan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Nama Prospek</th>
                        <th>Sumber</th>
                        <th>Minat Unit</th>
                        <th>Status</th>
                        <th>Follow-up</th>
                        <th class="text-center" style="width:150px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($jml_data > 0):
                        $no = 1;
                        $fuClasses = ['Baru'=>'badge-fu-baru','Sudah Dihubungi'=>'badge-fu-hubungi','Minat'=>'badge-fu-minat','Sudah Survey'=>'badge-fu-survey','Deal'=>'badge-fu-deal','Tidak Lanjut'=>'badge-fu-tidak'];
                        $sumberClasses = ['Pameran'=>'badge-sumber-pameran','Walk-in Kantor'=>'badge-sumber-walkin','Instagram'=>'badge-sumber-ig','Facebook'=>'badge-sumber-fb','WhatsApp'=>'badge-sumber-wa','Referensi'=>'badge-sumber-ref','Lainnya'=>'badge-sumber-lain'];
                        while ($p = mysqli_fetch_assoc($q_prospek)):
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($p['name_lengkap']) ?></div>
                            <div class="text-muted small">
                                <?php if (!empty($p['no_whatsapp'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $p['no_whatsapp']) ?>" target="_blank" class="text-success text-decoration-none">
                                        <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($p['no_whatsapp']) ?>
                                    </a>
                                <?php else: ?>-<?php endif; ?>
                                <?php if (!empty($p['pekerjaan'])): ?> &bull; <span class="text-uppercase small"><?= htmlspecialchars($p['pekerjaan']) ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge rounded-pill px-2 <?= $sumberClasses[$p['sumber']] ?? 'badge-sumber-lain' ?>"><?= $p['sumber'] ?></span></td>
                        <td>
                            <div class="fw-semibold small"><?= htmlspecialchars($p['cluster_minat'] ?? '-') ?></div>
                            <?php if (!empty($p['unit_minat'])): ?>
                                <div class="text-muted small"><?= htmlspecialchars($p['unit_minat']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge rounded-pill px-2 <?= $fuClasses[$p['status_followup']] ?? 'badge-fu-baru' ?>"><?= $p['status_followup'] ?></span></td>
                        <td>
                            <div class="small"><?= $p['tanggal_followup'] ? date('d M Y', strtotime($p['tanggal_followup'])) : '-' ?></div>
                            <?php if (!empty($p['catatan'])): ?>
                                <div class="text-muted small text-truncate" style="max-width:140px;"><?= htmlspecialchars($p['catatan']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <?php if ($p['status_followup'] !== 'Deal'): ?>
                                <button class="btn btn-sm btn-success rounded-pill px-2 fw-semibold" title="Tandai Deal" data-bs-toggle="modal" data-bs-target="#modalDeal<?= $p['id_prospek'] ?>">
                                    <i class="bi bi-check-circle me-1"></i>Deal
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $p['id_prospek'] ?>" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="prospek.php?action=hapus&id=<?= $p['id_prospek'] ?>&token=<?= htmlspecialchars(csrf_token()) ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Hapus prospek ini?')" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>

                            <?php ob_start(); ?>
                            <!-- MODAL DEAL -->
                            <div class="modal fade" id="modalDeal<?= $p['id_prospek'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-success"><i class="bi bi-check-circle me-2"></i>Konfirmasi Deal</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center py-3">
                                            <i class="bi bi-person-check text-success display-4"></i>
                                            <h6 class="fw-bold mt-2"><?= htmlspecialchars($p['name_lengkap']) ?></h6>
                                            <p class="text-muted small mb-0">Tandai prospek ini sebagai <strong>Deal</strong>.<br>Data akan otomatis dipindahkan ke <strong>Data Konsumen</strong>.</p>
                                        </div>
                                        <div class="modal-footer border-0 justify-content-center">
                                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                            <form method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="deal_prospek" value="1">
                                                <input type="hidden" name="id_prospek" value="<?= $p['id_prospek'] ?>">
                                                <button type="submit" class="btn btn-success rounded-pill px-4">Ya, Deal</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MODAL EDIT -->
                            <div class="modal fade" id="modalEdit<?= $p['id_prospek'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Prospek</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="edit_prospek" value="1">
                                            <input type="hidden" name="id_prospek" value="<?= $p['id_prospek'] ?>">
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                                                        <input type="text" name="name_lengkap" class="form-control rounded-3" value="<?= htmlspecialchars($p['name_lengkap']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold">No. KTP</label>
                                                        <input type="text" name="no_ktp" class="form-control rounded-3" value="<?= htmlspecialchars($p['no_ktp'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold">Pekerjaan</label>
                                                        <input type="text" name="pekerjaan" class="form-control rounded-3" value="<?= htmlspecialchars($p['pekerjaan'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold">No. WhatsApp</label>
                                                        <input type="text" name="no_whatsapp" class="form-control rounded-3" value="<?= htmlspecialchars($p['no_whatsapp'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold">Sumber</label>
                                                        <select name="sumber" class="form-select rounded-3">
                                                            <?php foreach (['Pameran','Walk-in Kantor','Instagram','Facebook','WhatsApp','Referensi','Lainnya'] as $sb): ?>
                                                            <option value="<?= $sb ?>" <?= $p['sumber'] === $sb ? 'selected' : '' ?>><?= $sb ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold">Cluster Minat</label>
                                                        <select name="cluster_minat" class="form-select rounded-3">
                                                            <option value="">-- Pilih --</option>
                                                            <?php foreach ($klaster as $kln): ?>
                                                            <option value="<?= htmlspecialchars($kln) ?>" <?= $p['cluster_minat'] === $kln ? 'selected' : '' ?>><?= htmlspecialchars($kln) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold">Unit Minat</label>
                                                        <input type="text" name="unit_minat" class="form-control rounded-3" value="<?= htmlspecialchars($p['unit_minat'] ?? '') ?>" placeholder="Contoh: Blok A1 / Tipe 36">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold">Status Follow-up</label>
                                                        <select name="status_followup" class="form-select rounded-3">
                                                            <?php foreach (['Baru','Sudah Dihubungi','Minat','Sudah Survey','Deal','Tidak Lanjut'] as $st): ?>
                                                            <option value="<?= $st ?>" <?= $p['status_followup'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold">Tanggal Follow-up</label>
                                                        <input type="date" name="tanggal_followup" class="form-control rounded-3" value="<?= $p['tanggal_followup'] ?? '' ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold">Catatan</label>
                                                        <input type="text" name="catatan" class="form-control rounded-3" value="<?= htmlspecialchars($p['catatan'] ?? '') ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-green px-4 rounded-pill" style="color:#fff !important">Simpan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $modals .= ob_get_clean();
                            ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-people fs-1 d-block mb-2"></i>Belum ada data prospek.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $modals ?? '' ?>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Prospek Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="tambah_prospek" value="1">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Lengkap</label>
                            <input type="text" name="name_lengkap" class="form-control rounded-3" placeholder="Nama calon konsumen" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">No. KTP</label>
                            <input type="text" name="no_ktp" class="form-control rounded-3" placeholder="Contoh: 6101011811940001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Pekerjaan</label>
                            <input type="text" name="pekerjaan" class="form-control rounded-3" placeholder="Contoh: Karyawan Swasta">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">No. WhatsApp</label>
                            <input type="text" name="no_whatsapp" class="form-control rounded-3" placeholder="Contoh: 08123456789">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Sumber Prospek</label>
                            <select name="sumber" class="form-select rounded-3">
                                <?php foreach (['Pameran','Walk-in Kantor','Instagram','Facebook','WhatsApp','Referensi','Lainnya'] as $sb): ?>
                                <option value="<?= $sb ?>"><?= $sb ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Cluster Minat</label>
                            <select name="cluster_minat" class="form-select rounded-3">
                                <option value="">-- Pilih --</option>
                                <?php foreach ($klaster as $kln2): ?>
                                <option value="<?= htmlspecialchars($kln2) ?>"><?= htmlspecialchars($kln2) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Unit Minat</label>
                            <input type="text" name="unit_minat" class="form-control rounded-3" placeholder="Contoh: Blok A1 / Tipe 36">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status Awal</label>
                            <select name="status_followup" class="form-select rounded-3">
                                <option value="Baru">Baru</option>
                                <option value="Sudah Dihubungi">Sudah Dihubungi</option>
                                <option value="Minat">Minat</option>
                                <option value="Sudah Survey">Sudah Survey</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tanggal Follow-up</label>
                            <input type="date" name="tanggal_followup" class="form-control rounded-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Catatan</label>
                            <input type="text" name="catatan" class="form-control rounded-3" placeholder="Catatan awal (opsional)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-green px-4 rounded-pill" style="color:#fff !important">Simpan Prospek</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
