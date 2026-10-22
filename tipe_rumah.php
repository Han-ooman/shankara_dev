<?php
include "includes/koneksi.php";

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
require_once __DIR__ . '/includes/role_access.php';
role_guard();

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// LOGIKA TAMBAH
if (isset($_POST['tambah_tipe'])) {
    $tipe_rumah  = sdb_real_escape_string($koneksi, $_POST['tipe_rumah']);
    $harga       = str_replace(['.', ' '], '', $_POST['harga']);
    $dp_persen   = str_replace(',', '.', $_POST['dp_persen']);
    $dp_nominal  = str_replace(['.', ' '], '', $_POST['dp_nominal']);
    $persyaratan = sdb_real_escape_string($koneksi, $_POST['persyaratan']);
    $keterangan  = sdb_real_escape_string($koneksi, $_POST['keterangan']);
    $harga_int   = intval($harga);
    $dp_nom_int  = intval($dp_nominal);
    if ($harga_int > 0 && $dp_nom_int > 0) { $dp_persen = round(($dp_nom_int / $harga_int) * 100, 2); }

    $q = "INSERT INTO data_tipe_rumah (tipe_rumah, harga, dp_persen, dp_nominal, persyaratan, keterangan)
          VALUES ('$tipe_rumah', " . intval($harga) . ", " . floatval($dp_persen) . ", " . intval($dp_nominal) . ", '$persyaratan', '$keterangan')";

    if (sdb_query($koneksi, $q)) {
        $id_baru = sdb_insert_id($koneksi);
        $tenors = $_POST['tenor_bulan'] ?? [];
        $periodes = $_POST['periode'] ?? [];
        $angsurans = $_POST['angsuran'] ?? [];
        for ($i = 0; $i < count($tenors); $i++) {
            $tb  = intval($tenors[$i]);
            $per = sdb_real_escape_string($koneksi, $periodes[$i] ?? '');
            $ag  = str_replace(['.', ' '], '', $angsurans[$i] ?? '0');
            if ($tb > 0) {
                sdb_query($koneksi, "INSERT INTO data_angsuran (id_tipe, tenor_bulan, periode, angsuran) VALUES ($id_baru, $tb, '$per', " . intval($ag) . ")");
            }
        }
        header("Location: tipe_rumah.php");
        exit();
    } else {
        echo "<script>alert('Gagal menambah data: " . sdb_error($koneksi) . "');</script>";
    }
}

// LOGIKA EDIT
if (isset($_POST['edit_tipe'])) {
    $id_tipe     = sdb_real_escape_string($koneksi, $_POST['id_tipe']);
    $tipe_rumah  = sdb_real_escape_string($koneksi, $_POST['tipe_rumah']);
    $harga       = str_replace(['.', ' '], '', $_POST['harga']);
    $dp_persen   = str_replace(',', '.', $_POST['dp_persen']);
    $dp_nominal  = str_replace(['.', ' '], '', $_POST['dp_nominal']);
    $persyaratan = sdb_real_escape_string($koneksi, $_POST['persyaratan']);
    $keterangan  = sdb_real_escape_string($koneksi, $_POST['keterangan']);
    $harga_int   = intval($harga);
    $dp_nom_int  = intval($dp_nominal);
    if ($harga_int > 0 && $dp_nom_int > 0) { $dp_persen = round(($dp_nom_int / $harga_int) * 100, 2); }

    $q = "UPDATE data_tipe_rumah SET
          tipe_rumah = '$tipe_rumah',
          harga = " . intval($harga) . ",
          dp_persen = " . floatval($dp_persen) . ",
          dp_nominal = " . intval($dp_nominal) . ",
          persyaratan = '$persyaratan',
          keterangan = '$keterangan'
          WHERE id_tipe = '$id_tipe'";

    if (sdb_query($koneksi, $q)) {
        sdb_query($koneksi, "DELETE FROM data_angsuran WHERE id_tipe = '$id_tipe'");
        $tenors = $_POST['tenor_bulan'] ?? [];
        $periodes = $_POST['periode'] ?? [];
        $angsurans = $_POST['angsuran'] ?? [];
        for ($i = 0; $i < count($tenors); $i++) {
            $tb  = intval($tenors[$i]);
            $per = sdb_real_escape_string($koneksi, $periodes[$i] ?? '');
            $ag  = str_replace(['.', ' '], '', $angsurans[$i] ?? '0');
            if ($tb > 0) {
                sdb_query($koneksi, "INSERT INTO data_angsuran (id_tipe, tenor_bulan, periode, angsuran) VALUES ($id_tipe, $tb, '$per', " . intval($ag) . ")");
            }
        }
        header("Location: tipe_rumah.php");
        exit();
    } else {
        echo "<script>alert('Gagal mengupdate data: " . sdb_error($koneksi) . "');</script>";
    }
}

// LOGIKA HAPUS
if (isset($_GET['hapus'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = sdb_real_escape_string($koneksi, $_GET['hapus']);
    sdb_query($koneksi, "DELETE FROM data_tipe_rumah WHERE id_tipe = '$id_hapus'");
    header("Location: tipe_rumah.php");
    exit();
}

$q_tipe = sdb_query($koneksi, "SELECT * FROM data_tipe_rumah ORDER BY CAST(LEFT(tipe_rumah, 3) AS UNSIGNED) ASC, id_tipe ASC");
$data_tipe = [];
if ($q_tipe) { while ($r = sdb_fetch_assoc($q_tipe)) { $data_tipe[] = $r; } }

$q_ang = sdb_query($koneksi, "SELECT * FROM data_angsuran ORDER BY tenor_bulan ASC, id_angsuran ASC");
$data_ang = [];
if ($q_ang) { while ($r = sdb_fetch_assoc($q_ang)) { $data_ang[$r['id_tipe']][] = $r; } }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Tipe Rumah & Paket Pembayaran - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* KARTU TIPE */
        .tipe-card {
            background: #ffffff;
            border-radius: var(--card-radius);
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .tipe-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(30, 58, 138, 0.12);
            border-color: #bfe3cf;
        }

        .tipe-card-head {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            color: #ffffff;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .tipe-card-head .tipe-label {
            font-size: 1.05rem;
            font-weight: 700;
        }
        .tipe-card-head .tipe-ico {
            font-size: 1.6rem;
            opacity: 0.9;
        }

        .tipe-card-body {
            padding: 18px 20px;
            flex: 1;
        }

        .tipe-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 6px 0;
            border-bottom: 1px dashed #eef2f5;
        }
        .tipe-row:last-child {
            border-bottom: none;
        }
        .tipe-row .lbl {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .tipe-row .val {
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--text-dark);
        }
        .tipe-row .val.success { color: #2563eb; }
        .tipe-row .val.warning { color: #b45309; }

        /* BLOK SKEMA ANGSURAN */
        .skema-block {
            margin-top: 12px;
        }
        .skema-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .skema-tenor {
            border: 1px solid #eef2f5;
            border-radius: 10px;
            padding: 8px 10px;
            margin-bottom: 8px;
            background: #fafcfa;
        }
        .skema-tenor .tenor-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .skema-tenor .tenor-label i { color: var(--accent-blue); }
        .skema-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 2px 0;
            font-size: 0.8rem;
        }
        .skema-row .per { color: var(--text-muted); }
        .skema-row .ag { font-weight: 700; }

        .tipe-card-foot {
            padding: 12px 20px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 8px;
        }

        .persyaratan-list {
            margin: 10px 0 0;
            padding-left: 18px;
            font-size: 0.82rem;
            color: var(--text-dark);
            max-height: 130px;
            overflow-y: auto;
        }
        .persyaratan-list li {
            margin-bottom: 4px;
        }

        /* ROW DINAMIS ANGSURAN DI MODAL */
        .ang-item {
            background: #fafcfa;
            border: 1px solid #eef2f5;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 8px;
        }
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
                <h4 class="fw-bold mb-1">Tipe Rumah & Paket Pembayaran</h4>
                <p class="text-muted small mb-0">Kelola harga, DP, skema angsuran multi-tenor, dan persyaratan administrasi per tipe rumah.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="print/cetak_tipe_rumah.php" target="_blank" class="btn btn-outline-success px-4 py-2 rounded-3"><i class="bi bi-filetype-pdf me-1"></i> Cetak PDF</a>
                <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambah">+ Tambah Tipe / Paket</button>
            </div>
        </div>

        <?php if (empty($data_tipe)): ?>
            <div class="content-card text-center py-5">
                <i class="bi bi-houses display-3 text-muted d-block mb-3"></i>
                <p class="text-muted mb-0">Belum ada data tipe rumah. Klik "Tambah Tipe / Paket" untuk memulai.</p>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($data_tipe as $t): ?>
            <?php
                $persyaratan = array_values(array_filter(array_map('trim', explode("\n", $t['persyaratan'] ?? ''))));
                $skema = $data_ang[$t['id_tipe']] ?? [];
                $tenor_group = [];
                foreach ($skema as $row) {
                    $tenor_group[$row['tenor_bulan']][] = $row;
                }
                ksort($tenor_group);
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="tipe-card">
                    <div class="tipe-card-head">
                        <span class="tipe-label"><i class="bi bi-house-fill tipe-ico me-2"></i>Tipe <?= htmlspecialchars($t['tipe_rumah']) ?></span>
                        <a href="tipe_rumah.php?hapus=<?= $t['id_tipe'] ?>&token=<?= htmlspecialchars(csrf_token()) ?>" class="text-white-50 text-decoration-none" onclick="return confirm('Hapus paket Tipe <?= htmlspecialchars($t['tipe_rumah']) ?>?')" title="Hapus"><i class="bi bi-trash3"></i></a>
                    </div>
                    <div class="tipe-card-body">
                        <div class="tipe-row">
                            <span class="lbl">Harga Jual</span>
                            <span class="val success">Rp <?= number_format($t['harga'], 0, ',', '.') ?></span>
                        </div>
                        <div class="tipe-row">
                            <span class="lbl">Uang Muka (DP)</span>
                            <span class="val warning"><?= rtrim(rtrim(number_format($t['dp_persen'], 2, ',', '.'), '0'), ',') ?>% / Rp <?= number_format($t['dp_nominal'], 0, ',', '.') ?></span>
                        </div>

                        <?php if (!empty($tenor_group)): ?>
                        <div class="skema-block">
                            <div class="skema-title"><i class="bi bi-calculator me-1"></i>Skema Angsuran / Bulan</div>
                            <?php foreach ($tenor_group as $tb => $rows): ?>
                                <div class="skema-tenor">
                                    <div class="tenor-label"><i class="bi bi-clock-history"></i>Tenor <?= $tb ?> bulan (<?= intval($tb / 12) ?> tahun)</div>
                                    <?php if (count($rows) == 1 && empty($rows[0]['periode'])): ?>
                                        <div class="skema-row">
                                            <span class="per">Angsuran tetap</span>
                                            <span class="ag">Rp <?= number_format($rows[0]['angsuran'], 0, ',', '.') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($rows as $row): ?>
                                        <div class="skema-row">
                                            <span class="per"><?= htmlspecialchars($row['periode']) ?: 'Angsuran' ?></span>
                                            <span class="ag">Rp <?= number_format($row['angsuran'], 0, ',', '.') ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($t['keterangan'])): ?>
                        <div class="small mt-2 text-muted"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($t['keterangan']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($persyaratan)): ?>
                        <div class="small fw-semibold mt-3 mb-0" style="color: var(--primary-blue);"><i class="bi bi-clipboard2-check me-1"></i>Persyaratan Administrasi:</div>
                        <ul class="persyaratan-list">
                            <?php foreach ($persyaratan as $s): ?>
                                <li><?= htmlspecialchars($s) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="small text-muted mt-3"><i class="bi bi-clipboard me-1"></i>Belum ada persyaratan.</div>
                        <?php endif; ?>
                    </div>
                    <div class="tipe-card-foot">
                        <button class="btn btn-sm btn-outline-success rounded-pill flex-fill" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $t['id_tipe'] ?>">
                            <i class="bi bi-pencil-square me-1"></i> Edit Paket
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODAL EDIT -->
            <div class="modal fade" id="modalEdit<?= $t['id_tipe'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content rounded-4 border-0">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit Paket Tipe <?= htmlspecialchars($t['tipe_rumah']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
<form action="tipe_rumah.php" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_tipe" value="<?= $t['id_tipe'] ?>">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Tipe Rumah</label>
                                        <input type="text" name="tipe_rumah" class="form-control" value="<?= htmlspecialchars($t['tipe_rumah']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Harga Jual (Rp)</label>
                                        <input type="text" name="harga" class="form-control rupiah-input" value="<?= number_format($t['harga'], 0, ',', '.') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">DP (%)</label>
                                        <input type="number" step="0.01" name="dp_persen" class="form-control" value="<?= rtrim(rtrim(number_format($t['dp_persen'], 2, '.', ''), '0'), '.') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">DP Nominal (Rp)</label>
                                        <input type="text" name="dp_nominal" class="form-control rupiah-input" value="<?= number_format($t['dp_nominal'], 0, ',', '.') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Keterangan</label>
                                        <input type="text" name="keterangan" class="form-control" value="<?= htmlspecialchars($t['keterangan'] ?? '') ?>" placeholder="Contoh: Harga promo, termasuk biaya AJB, dll.">
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label small fw-semibold mb-0">Skema Angsuran / Bulan</label>
                                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="addAngRow(this)"><i class="bi bi-plus-lg me-1"></i>Tambah Tenor</button>
                                        </div>
                                        <div class="ang-list">
                                            <?php foreach ($tenor_group as $tb => $rows): ?>
                                                <?php foreach ($rows as $ri => $row): ?>
                                                <div class="ang-item">
                                                    <div class="row g-2 align-items-end">
                                                        <div class="col-md-3">
                                                            <label class="form-label small text-muted mb-1">Tenor (bulan)</label>
                                                            <input type="number" name="tenor_bulan[]" class="form-control" value="<?= intval($row['tenor_bulan']) ?>" min="1" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small text-muted mb-1">Periode / Tahun</label>
                                                            <input type="text" name="periode[]" class="form-control" value="<?= htmlspecialchars($row['periode']) ?>" placeholder="Contoh: Tahun 1-2">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small text-muted mb-1">Angsuran (Rp)</label>
                                                            <input type="text" name="angsuran[]" class="form-control rupiah-input" value="<?= number_format($row['angsuran'], 0, ',', '.') ?>" required>
                                                        </div>
                                                        <div class="col-md-2 d-flex justify-content-end">
                                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="removeAngRow(this)"><i class="bi bi-trash3"></i></button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Persyaratan Administrasi (satu per baris)</label>
                                        <textarea name="persyaratan" class="form-control" rows="5" placeholder="KTP Calon Kreditur&#10;KK&#10;Slip Gaji 3 bulan terakhir&#10;..." required><?= htmlspecialchars($t['persyaratan'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="edit_tipe" class="btn text-white px-4" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tambah Tipe Rumah / Paket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
<form action="tipe_rumah.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tipe Rumah</label>
                            <input type="text" name="tipe_rumah" class="form-control" placeholder="Contoh: 36, 45 (Subsidi), Komersial" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Harga Jual (Rp)</label>
                            <input type="text" name="harga" class="form-control rupiah-input" placeholder="Contoh: 150000000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">DP (%)</label>
                            <input type="number" step="0.01" name="dp_persen" class="form-control" placeholder="Contoh: 10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">DP Nominal (Rp)</label>
                            <input type="text" name="dp_nominal" class="form-control rupiah-input" placeholder="Contoh: 15000000">
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-semibold mb-0">Skema Angsuran / Bulan</label>
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="addAngRow(this)"><i class="bi bi-plus-lg me-1"></i>Tambah Tenor</button>
                            </div>
                            <div class="ang-list">
                                <div class="ang-item">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted mb-1">Tenor (bulan)</label>
                                            <input type="number" name="tenor_bulan[]" class="form-control" min="1" placeholder="120" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small text-muted mb-1">Periode / Tahun</label>
                                            <input type="text" name="periode[]" class="form-control" placeholder="Contoh: Tahun 1-5">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted mb-1">Angsuran (Rp)</label>
                                            <input type="text" name="angsuran[]" class="form-control rupiah-input" placeholder="1200000" required>
                                        </div>
                                        <div class="col-md-2 d-flex justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="removeAngRow(this)"><i class="bi bi-trash3"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Keterangan</label>
                            <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Harga promo, termasuk biaya AJB, dll.">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Persyaratan Administrasi (satu per baris)</label>
                            <textarea name="persyaratan" class="form-control" rows="5" placeholder="KTP Calon Kreditur&#10;KK&#10;Slip Gaji 3 bulan terakhir&#10;..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="tambah_tipe" class="btn text-white px-4" style="background-color: var(--primary-blue);">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function addAngRow(btn) {
        const list = btn.closest('.col-12').querySelector('.ang-list');
        const wrap = document.createElement('div');
        wrap.className = 'ang-item';
        wrap.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Tenor (bulan)</label>
                    <input type="number" name="tenor_bulan[]" class="form-control" min="1" placeholder="120" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Periode / Tahun</label>
                    <input type="text" name="periode[]" class="form-control" placeholder="Contoh: Tahun 1-5">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Angsuran (Rp)</label>
                    <input type="text" name="angsuran[]" class="form-control rupiah-input" placeholder="1200000" required>
                </div>
                <div class="col-md-2 d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="removeAngRow(this)"><i class="bi bi-trash3"></i></button>
                </div>
            </div>`;
        list.appendChild(wrap);
        bindRupiah(wrap);
    }

    function removeAngRow(btn) {
        const item = btn.closest('.ang-item');
        const list = item.parentElement;
        if (list.querySelectorAll('.ang-item').length > 1) {
            item.remove();
        } else {
            const inputs = item.querySelectorAll('input');
            inputs.forEach(i => { i.value = ''; });
        }
    }

    function bindRupiah(root) {
        root.querySelectorAll('.rupiah-input').forEach(el => {
            if (!el.dataset.bound) {
                el.dataset.bound = '1';
                el.addEventListener('input', function () {
                    let v = this.value.replace(/\D/g, '');
                    this.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ang-list').forEach(list => bindRupiah(list));
    });
</script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
