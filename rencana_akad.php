<?php
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
include "includes/koneksi.php";
include "includes/cluster_hold.php";
require_once __DIR__ . '/includes/helper_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

if (isset($_GET['ajax']) && isset($_GET['id_konsumen'])) {
    header('Content-Type: application/json; charset=utf-8');
    $idk = intval($_GET['id_konsumen']);
    $found = false;
    $data = null;
    $qq = mysqli_query($koneksi, "SELECT t.id_transaksi, t.tgl_booking, t.skema_bayar, t.skema_akad, t.bank_tujuan, t.status_konsumen,
                                         k.name_lengkap, r.id_rumah, r.no_blok, r.tipe_rumah, r.status_unit, c.cluster_name
                                  FROM data_transaksi t
                                  JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                  JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                  JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                  WHERE t.id_konsumen = $idk AND t.status_konsumen != 'BATAL'
                                  ORDER BY t.id_transaksi DESC LIMIT 1");
    if ($qq && $f = mysqli_fetch_assoc($qq)) {
        $found = true;
        $data = $f;
    }
    echo json_encode(['found' => $found, 'data' => $data]);
    exit();
}

$bulan = isset($_GET['bulan']) && preg_match('/^\d{4}-\d{2}$/', $_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$bulanPrev = date('Y-m', strtotime($bulan . '-01 -1 month'));
$bulanNext = date('Y-m', strtotime($bulan . '-01 +1 month'));
$blnPertama = $bulan . '-01';
$blnTerakhir = date('Y-m-t', strtotime($blnPertama));
$labelBulan = date('F Y', strtotime($blnPertama));

if (isset($_POST['simpan_rencana'])) {
    $tgl_booking     = date('Y-m-d');
    $id_konsumen     = intval($_POST['id_konsumen']);
    $id_rumah        = intval($_POST['id_rumah']);

    // Blokir rencana akad baru bila cluster sedang di HOLD
    cluster_hold_cek_rumah($koneksi, $id_rumah, "rencana_akad.php?bulan=$bulan");

    $skema_bayar     = mysqli_real_escape_string($koneksi, $_POST['skema_bayar']);
    $skema_akad      = mysqli_real_escape_string($koneksi, $_POST['skema_akad']);
    $bank_tujuan    = mysqli_real_escape_string($koneksi, $_POST['bank_tujuan'] ?? '');
    $status_konsumen = mysqli_real_escape_string($koneksi, $_POST['status_konsumen']);
    $tanggal_akad    = !empty($_POST['tanggal_akad']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['tanggal_akad']) . "'" : "NULL";

    $q = "INSERT INTO data_transaksi (tgl_booking, id_konsumen, id_rumah, skema_bayar, skema_akad, bank_tujuan, status_konsumen, tanggal_akad)
          VALUES ('$tgl_booking', '$id_konsumen', '$id_rumah', '$skema_bayar', '$skema_akad', '$bank_tujuan', '$status_konsumen', $tanggal_akad)";
    if (mysqli_query($koneksi, $q)) {
        $stUnit = ($status_konsumen === 'ACC - AKAD') ? 'TERJUAL' : 'TERBOOKING';
        mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = '$stUnit' WHERE id_rumah = '$id_rumah'");
        log_aktivitas($koneksi, 'TAMBAH', 'Rencana Akad', 'Tambah rencana akad: konsumen #' . $id_konsumen . ', unit #' . $id_rumah, ['id_konsumen' => $id_konsumen, 'id_rumah' => $id_rumah, 'skema_bayar' => $skema_bayar, 'tanggal_akad' => $_POST['tanggal_akad'] ?? null]);
    }
    header("Location: rencana_akad.php?bulan=$bulan");
    exit();
}

if (isset($_POST['ubah_rencana'])) {
    $id_transaksi    = intval($_POST['id_transaksi']);
    $skema_akad      = mysqli_real_escape_string($koneksi, $_POST['skema_akad']);
    $bank_tujuan    = mysqli_real_escape_string($koneksi, $_POST['bank_tujuan'] ?? '');
    $status_konsumen = mysqli_real_escape_string($koneksi, $_POST['status_konsumen']);
    $tanggal_akad    = !empty($_POST['tanggal_akad']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['tanggal_akad']) . "'" : "NULL";

    $q = "UPDATE data_transaksi SET skema_akad = '$skema_akad', bank_tujuan = '$bank_tujuan', status_konsumen = '$status_konsumen', tanggal_akad = $tanggal_akad WHERE id_transaksi = '$id_transaksi'";
    if (mysqli_query($koneksi, $q)) {
        $stUnit = ($status_konsumen === 'ACC - AKAD') ? 'TERJUAL' : 'TERBOOKING';
        mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = '$stUnit' WHERE id_rumah = (SELECT id_rumah FROM data_transaksi WHERE id_transaksi = '$id_transaksi')");
        log_aktivitas($koneksi, 'UBAH', 'Rencana Akad', 'Ubah rencana akad transaksi #' . $id_transaksi, ['id_transaksi' => $id_transaksi, 'skema_akad' => $skema_akad, 'status_konsumen' => $status_konsumen, 'tanggal_akad' => $_POST['tanggal_akad'] ?? null]);
    }
    header("Location: rencana_akad.php?bulan=$bulan");
    exit();
}

if (isset($_POST['selesai_akad'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    mysqli_query($koneksi, "UPDATE data_transaksi SET status_konsumen = 'ACC - AKAD' WHERE id_transaksi = '$id_transaksi'");
    mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERJUAL' WHERE id_rumah = (SELECT id_rumah FROM data_transaksi WHERE id_transaksi = '$id_transaksi')");
    log_aktivitas($koneksi, 'UBAH', 'Rencana Akad', 'Tandai akad selesai transaksi #' . $id_transaksi, ['id_transaksi' => $id_transaksi, 'status_konsumen' => 'ACC - AKAD']);
    header("Location: rencana_akad.php?bulan=$bulan");
    exit();
}

if (isset($_POST['hapus_rencana'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = 'TERBOOKING' WHERE id_rumah = (SELECT id_rumah FROM data_transaksi WHERE id_transaksi = '$id_transaksi')");
    mysqli_query($koneksi, "DELETE FROM data_transaksi WHERE id_transaksi = '$id_transaksi'");
    log_aktivitas($koneksi, 'HAPUS', 'Rencana Akad', 'Hapus rencana akad transaksi #' . $id_transaksi, ['id_transaksi' => $id_transaksi]);
    header("Location: rencana_akad.php?bulan=$bulan");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Rencana Akad Bulan Ini - Shankara Dev</title>
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

        .print-header {
            display: none;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 1.5cm;
            }

            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-size: 10pt;
            }

            .sidebar,
            .no-print,
            .btn,
            .modal,
            .filter-section,
            th:last-child,
            td:last-child {
                display: none !important;
            }

            .main-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .content-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            .print-header {
                display: block !important;
                border-bottom: 2px solid #1e3a8a;
                padding-bottom: 12px;
                margin-bottom: 20px;
            }

            .print-header h3 {
                margin: 0;
                font-weight: 700;
                color: #1e3a8a;
                text-transform: uppercase;
            }

            .print-header p {
                margin: 0;
                font-size: 9pt;
                color: #555;
            }

            .table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .table th,
            .table td {
                border: 1px solid #cbd5e1 !important;
                padding: 8px 10px !important;
                font-size: 9pt !important;
            }

            .table th {
                background-color: #f1f5f9 !important;
                color: #000 !important;
                font-weight: bold;
            }

            .badge {
                border: 1px solid #000 !important;
                color: #000 !important;
                background: transparent !important;
            }
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <main class="main-wrapper">
        <div class="print-header">
            <h3 id="printTitle">SHANKARA RESIDENCE</h3>
            <p>Daftar Rencana Akad Konsumen - <?= htmlspecialchars($labelBulan) ?></p>
            <p class="mt-1">Dicetak pada: <strong id="tglCetak">-</strong></p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1">Rencana Akad Bulan Ini</h4>
                <p class="text-muted small mb-0">Daftar konsumen yang direncanakan akad pada
                    <strong><?= htmlspecialchars($labelBulan) ?></strong>.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" onclick="window.print()" class="btn btn-outline-danger rounded-pill fw-semibold px-3">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                </button>
                <button type="button" class="btn text-white rounded-pill fw-semibold px-4 shadow-sm" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Rencana Akad
                </button>
            </div>
        </div>

        <div class="content-card">
            <div class="row g-2 mb-4 align-items-center filter-section no-print">
                <div class="col-md-4">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute text-muted" style="left: 16px; top: 50%; transform: translateY(-50%);"></i>
                        <input type="text" id="searchInput" class="form-control filter-input ps-5 shadow-none" placeholder="Cari konsumen / unit rumah...">
                    </div>
                </div>
                <div class="col-md-3">
                    <form method="GET" action="rencana_akad.php" class="d-flex align-items-center gap-2">
                        <input type="month" name="bulan" class="form-control filter-input shadow-none" value="<?= htmlspecialchars($bulan) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Pindah</button>
                    </form>
                </div>
                <div class="col-md-2 text-end ms-auto">
                    <div class="d-flex gap-2 justify-content-end">
                        <button id="btnReset" class="btn btn-outline-secondary rounded-pill btn-sm fw-medium">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
                        <a href="rencana_akad.php?bulan=<?= $bulanPrev ?>" class="btn btn-outline-secondary rounded-pill btn-sm fw-medium"><i class="bi bi-chevron-left me-1"></i>Sebelumnya</a>
                        <a href="rencana_akad.php?bulan=<?= $bulanNext ?>" class="btn btn-outline-secondary rounded-pill btn-sm fw-medium">Berikutnya<i class="bi bi-chevron-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableRencana">
                    <thead>
                        <tr class="text-muted">
                            <th style="width: 50px;">No</th>
                            <th>Konsumen</th>
                            <th>Unit Rumah</th>
                            <th>Skema Bayar</th>
                            <th>Skema Akad</th>
                            <th>Bank Tujuan</th>
                            <th>Tanggal Akad</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q = mysqli_query($koneksi, "SELECT t.id_transaksi, t.tgl_booking, t.skema_bayar, t.skema_akad, t.bank_tujuan, t.status_konsumen, t.tanggal_akad,
                                                             k.name_lengkap, k.no_whatsapp, r.no_blok, r.tipe_rumah, c.cluster_name
                                                      FROM data_transaksi t
                                                      JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
                                                      JOIN data_rumah r ON t.id_rumah = r.id_rumah
                                                      JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                                      WHERE DATE_FORMAT(t.tanggal_akad, '%Y-%m') = '$bulan'
                                                      ORDER BY t.tanggal_akad ASC, t.id_transaksi ASC");
                        $no = 1;
                        $ada = $q && mysqli_num_rows($q) > 0;
                        if ($ada) {
                            while ($r = mysqli_fetch_assoc($q)) {
                                $status = $r['status_konsumen'];
                                if ($status === 'ACC - AKAD') {
                                    $badge = 'bg-success';
                                } elseif ($status === 'BATAL') {
                                    $badge = 'bg-danger';
                                } elseif ($status === 'PROSES') {
                                    $badge = 'bg-warning text-dark';
                                } else {
                                    $badge = 'bg-secondary';
                                }
                        ?>
                        <tr>
                            <td class="fw-semibold text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($r['name_lengkap']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($r['no_whatsapp'] ?? '-') ?></div>
                            </td>
                            <td><span class="badge bg-dark rounded-pill px-3 py-2"><?= htmlspecialchars($r['cluster_name']) ?> - Blok <?= htmlspecialchars($r['no_blok']) ?></span></td>
                            <td class="small"><?= htmlspecialchars($r['skema_bayar'] ?? '-') ?></td>
                            <td class="small"><?= htmlspecialchars($r['skema_akad'] ?? '-') ?></td>
                            <td class="small"><span class="badge bg-light border text-dark"><?= htmlspecialchars($r['bank_tujuan'] ?? '-') ?></span></td>
                            <td>
                                <?php if (!empty($r['tanggal_akad'])): ?>
                                    <span class="fw-semibold"><?= date('d/m/Y', strtotime($r['tanggal_akad'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Belum diisi</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badge ?> rounded-pill px-3 py-2"><?= htmlspecialchars($status) ?></span></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if ($status !== 'ACC - AKAD'): ?>
                                    <form action="rencana_akad.php?bulan=<?= $bulan ?>" method="POST" class="d-inline" onsubmit="return confirm('Pastikan akad sudah benar-benar dilaksanakan? Unit akan ditandai TERJUAL.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_transaksi" value="<?= $r['id_transaksi'] ?>">
                                        <button type="submit" name="selesai_akad" class="btn btn-sm btn-success rounded-pill px-3" title="Tandai akad selesai"><i class="bi bi-check-lg"></i> Selesai</button>
                                    </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $r['id_transaksi'] ?>" title="Ubah tanggal akad"><i class="bi bi-pencil"></i></button>
                                    <form action="rencana_akad.php?bulan=<?= $bulan ?>" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus rencana akad ini?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_transaksi" value="<?= $r['id_transaksi'] ?>">
                                        <button type="submit" name="hapus_rencana" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Hapus rencana"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php } } else { ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 2.5rem;"></i>
                                <div class="fw-semibold text-dark mt-2">Belum ada rencana akad untuk <?= htmlspecialchars($labelBulan) ?></div>
                                <p class="text-muted small mb-3">Tambahkan rencana akad konsumen pada bulan ini.</p>
                                <button type="button" class="btn text-white rounded-pill px-4" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambah">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Rencana Akad
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php
$optSkemaAkad = ['KPR Bank Konvensional', 'KPR Bank Syariah', 'Cash Tempo (Akad Developer)', 'Cash'];
$optSkemaBayar = ['KPR Subsidi', 'KPR Komersil', 'Cash Bertahap', 'Cash Keras'];
$optBank = ['Bank BNI', 'BRI', 'BSI', 'Bank Mandiri', 'Bank Kalbar Syariah'];
$konsumenOpt = mysqli_query($koneksi, "SELECT * FROM data_konsumen ORDER BY name_lengkap ASC");
$rumahOpt = mysqli_query($koneksi, "SELECT r.id_rumah, r.no_blok, r.tipe_rumah, r.status_unit, c.cluster_name
                                    FROM data_rumah r
                                    JOIN data_cluster c ON r.id_cluster = c.id_cluster
                                    WHERE r.status_unit NOT IN ('TERJUAL')
                                    ORDER BY c.cluster_name, r.no_blok ASC");
?>

<!-- MODAL TAMBAH RENCANA AKAD -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-plus me-2"></i>Tambah Rencana Akad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="rencana_akad.php?bulan=<?= $bulan ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Konsumen</label>
                        <select name="id_konsumen" id="selKonsumen" class="form-select rounded-3" required>
                            <option value="">-- Pilih Konsumen --</option>
                            <?php while ($k = mysqli_fetch_assoc($konsumenOpt)) { ?>
                                <option value="<?= $k['id_konsumen'] ?>"><?= htmlspecialchars($k['name_lengkap']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div id="infoKonsumen" class="alert alert-light border small mb-3 d-none">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        <span id="infoKonsumenText"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Unit Rumah</label>
                        <select name="id_rumah" id="selRumah" class="form-select rounded-3" required>
                            <option value="">-- Pilih Unit --</option>
                            <?php while ($ru = mysqli_fetch_assoc($rumahOpt)) { ?>
                                <option value="<?= $ru['id_rumah'] ?>"><?= htmlspecialchars($ru['cluster_name']) ?> - Blok <?= htmlspecialchars($ru['no_blok']) ?> (Tipe <?= htmlspecialchars($ru['tipe_rumah']) ?>)</option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Skema Bayar</label>
                                <select name="skema_bayar" id="selSkemaBayar" class="form-select rounded-3" required>
                                    <?php foreach ($optSkemaBayar as $sb) { ?>
                                        <option value="<?= $sb ?>"><?= $sb ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Skema Akad</label>
                                <select name="skema_akad" id="selSkemaAkad" class="form-select rounded-3" required>
                                    <?php foreach ($optSkemaAkad as $sa) { ?>
                                        <option value="<?= $sa ?>"><?= $sa ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Bank Tujuan</label>
                                <select name="bank_tujuan" id="selBank" class="form-select rounded-3">
                                    <option value="">-- Pilih Bank --</option>
                                    <?php foreach ($optBank as $bn) { ?>
                                        <option value="<?= $bn ?>"><?= $bn ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Status Konsumen</label>
                                <select name="status_konsumen" class="form-select rounded-3">
                                    <option value="PROSES">PROSES</option>
                                    <option value="ACC - AKAD">ACC - AKAD</option>
                                    <option value="BATAL">BATAL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Akad</label>
                        <input type="date" name="tanggal_akad" class="form-control rounded-3" value="<?= date('Y-m-d', strtotime($blnPertama)) ?>" min="<?= $blnPertama ?>" max="<?= $blnTerakhir ?>" required>
                        <div class="form-text">Harus di bulan <strong><?= htmlspecialchars($labelBulan) ?></strong>.</div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_rencana" class="btn text-white px-4 rounded-pill" style="background-color: var(--primary-blue);">Simpan Rencana</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
mysqli_data_seek($q, 0);
if ($ada) {
    while ($r = mysqli_fetch_assoc($q)) {
        $st = $r['status_konsumen'];
?>
<!-- MODAL UBAH RENCANA -->
<div class="modal fade" id="modalEdit<?= $r['id_transaksi'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Ubah Rencana - <?= htmlspecialchars($r['name_lengkap']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="rencana_akad.php?bulan=<?= $bulan ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_transaksi" value="<?= $r['id_transaksi'] ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Akad</label>
                        <input type="date" name="tanggal_akad" class="form-control rounded-3" value="<?= $r['tanggal_akad'] ?>" min="<?= $blnPertama ?>" max="<?= $blnTerakhir ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Skema Akad</label>
                        <select name="skema_akad" class="form-select rounded-3">
                            <?php foreach ($optSkemaAkad as $sa) { ?>
                                <option value="<?= $sa ?>" <?= $r['skema_akad'] === $sa ? 'selected' : '' ?>><?= $sa ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bank Tujuan</label>
                        <select name="bank_tujuan" class="form-select rounded-3">
                            <option value="">-- Pilih Bank --</option>
                            <?php foreach ($optBank as $bn) { ?>
                                <option value="<?= $bn ?>" <?= ($r['bank_tujuan'] ?? '') === $bn ? 'selected' : '' ?>><?= $bn ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status Konsumen</label>
                        <select name="status_konsumen" class="form-select rounded-3">
                            <option value="PROSES" <?= $st === 'PROSES' ? 'selected' : '' ?>>PROSES</option>
                            <option value="ACC - AKAD" <?= $st === 'ACC - AKAD' ? 'selected' : '' ?>>ACC - AKAD</option>
                            <option value="BATAL" <?= $st === 'BATAL' ? 'selected' : '' ?>>BATAL</option>
                        </select>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="ubah_rencana" class="btn text-white px-4 rounded-pill" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php }} ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include "includes/modal_hold.php"; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#tableRencana tbody tr');
    const btnReset = document.getElementById('btnReset');
    const tglCetak = document.getElementById('tglCetak');

    if (sessionStorage.getItem('rencana_akad_search')) {
        searchInput.value = sessionStorage.getItem('rencana_akad_search');
    }

    function filterTable() {
        const query = searchInput.value.toLowerCase().trim();
        sessionStorage.setItem('rencana_akad_search', searchInput.value);
        tableRows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    }

    window.addEventListener('beforeprint', function () {
        const now = new Date();
        const tgl = String(now.getDate()).padStart(2, '0');
        const bln = String(now.getMonth() + 1).padStart(2, '0');
        const thn = now.getFullYear();
        const jam = String(now.getHours()).padStart(2, '0');
        const mnt = String(now.getMinutes()).padStart(2, '0');
        tglCetak.innerText = `${tgl}/${bln}/${thn} ${jam}:${mnt} WIB`;
    });

    btnReset.addEventListener('click', function () {
        searchInput.value = '';
        sessionStorage.removeItem('rencana_akad_search');
        filterTable();
    });

    filterTable();
    searchInput.addEventListener('input', filterTable);

    const selKonsumen = document.getElementById('selKonsumen');
    const infoKonsumen = document.getElementById('infoKonsumen');
    const infoKonsumenText = document.getElementById('infoKonsumenText');
    const selRumah = document.getElementById('selRumah');
    const selSkemaBayar = document.getElementById('selSkemaBayar');
    const selSkemaAkad = document.getElementById('selSkemaAkad');
    const selBank = document.getElementById('selBank');

    selKonsumen.addEventListener('change', function () {
        const id = this.value;
        if (!id) {
            selRumah.value = '';
            infoKonsumen.classList.add('d-none');
            return;
        }
        fetch('rencana_akad.php?ajax=1&id_konsumen=' + encodeURIComponent(id))
            .then(res => res.json())
            .then(data => {
                infoKonsumen.classList.remove('d-none');
                if (data.found && data.data) {
                    const d = data.data;
                    infoKonsumenText.innerText = d.name_lengkap + ' terdaftar di ' + d.cluster_name.trim() + ' - Blok ' + d.no_blok + ' (Tipe ' + d.tipe_rumah + ').';
                    selRumah.value = d.id_rumah;
                    if (selRumah.value !== d.id_rumah) {
                        infoKonsumenText.innerText += ' (unit tidak tersedia di daftar)';
                    }
                    if (d.skema_bayar) selSkemaBayar.value = d.skema_bayar;
                    if (d.skema_akad) selSkemaAkad.value = d.skema_akad;
                    if (d.bank_tujuan) selBank.value = d.bank_tujuan;
                } else {
                    infoKonsumenText.innerText = 'Belum ada data booking untuk konsumen ini. Silakan pilih unit secara manual.';
                    selRumah.value = '';
                }
            })
            .catch(() => {
                infoKonsumen.classList.remove('d-none');
                infoKonsumenText.innerText = 'Gagal memuat data konsumen.';
            });
    });
});
</script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>