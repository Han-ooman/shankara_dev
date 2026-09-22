<?php

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}

include "includes/koneksi.php";
include "includes/berkas_helper.php";

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// Logika Update Pemberkasan
if (isset($_POST['update_pemberkasan'])) {
    $id_transaksi    = mysqli_real_escape_string($koneksi, $_POST['id_transaksi']);
    $status_bank     = mysqli_real_escape_string($koneksi, $_POST['status_bank']);
    $bank_tujuan     = mysqli_real_escape_string($koneksi, $_POST['bank_tujuan'] ?? '');
    $deadline_berkas = mysqli_real_escape_string($koneksi, $_POST['deadline_berkas']);
    $tgl_wawancara   = mysqli_real_escape_string($koneksi, $_POST['tgl_wawancara'] ?? '');
    
    $berkas_input = isset($_POST['berkas']) ? $_POST['berkas'] : [];
    $pemberkasan_json = mysqli_real_escape_string($koneksi, json_encode($berkas_input));

    $qInfo = mysqli_query($koneksi, "SELECT skema_bayar, pekerjaan FROM view_database_utama WHERE id_transaksi = '$id_transaksi'");
    if ($qInfo && $rowInfo = mysqli_fetch_assoc($qInfo)) {
        $skema_info = $rowInfo['skema_bayar'] ?? 'Cash';
        $progres = hitungProgresBerkas($berkas_input, $skema_info, $master_dokumen);
        if ($progres['lengkap']) { $deadline_berkas = ''; }
    }

    $deadline_sql = !empty($deadline_berkas) ? "'$deadline_berkas'" : "NULL";
    $bank_sql = !empty($bank_tujuan) ? "'$bank_tujuan'" : "NULL";
    $wawancara_sql = !empty($tgl_wawancara) ? "'$tgl_wawancara'" : "NULL";
    $skema_akad = mysqli_real_escape_string($koneksi, $_POST['skema_akad'] ?? '');
    $skema_akad_sql = !empty($skema_akad) ? "'$skema_akad'" : "NULL";

    $queryUpdate = "UPDATE data_transaksi SET 
                    status_bank = '$status_bank',
                    bank_tujuan = $bank_sql,
                    deadline_berkas = $deadline_sql,
                    tgl_wawancara = $wawancara_sql,
                    skema_akad = $skema_akad_sql,
                    pemberkasan_json = '$pemberkasan_json'
                    WHERE id_transaksi = '$id_transaksi'";

    mysqli_query($koneksi, $queryUpdate);
    $scrollId = intval($_POST['scroll_to'] ?? 0);
    header("Location: pemberkasan.php?s=1&sid=" . $scrollId);
    exit();
}

// Logika Bulk Update Pemberkasan
if (isset($_POST['bulk_update_pemberkasan'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    if (!empty($ids)) {
        $id_list = array_map('intval', $ids);
        $id_str = implode(',', $id_list);
        $fields = [];
        if (!empty($_POST['bulk_skema_akad'])) { $v = mysqli_real_escape_string($koneksi, $_POST['bulk_skema_akad']); $fields[] = "skema_akad = '$v'"; }
        if (!empty($_POST['bulk_status_bank'])) { $v = mysqli_real_escape_string($koneksi, $_POST['bulk_status_bank']); $fields[] = "status_bank = '$v'"; }
        if (!empty($_POST['bulk_bank_tujuan'])) { $v = mysqli_real_escape_string($koneksi, $_POST['bulk_bank_tujuan']); $fields[] = "bank_tujuan = '$v'"; }
        if (!empty($_POST['bulk_deadline_berkas'])) { $v = mysqli_real_escape_string($koneksi, $_POST['bulk_deadline_berkas']); $fields[] = "deadline_berkas = '$v'"; }
        if (!empty($_POST['bulk_tgl_wawancara'])) { $v = mysqli_real_escape_string($koneksi, $_POST['bulk_tgl_wawancara']); $fields[] = "tgl_wawancara = '$v'"; }
        if (!empty($fields)) {
            $sql = "UPDATE data_transaksi SET " . implode(', ', $fields) . " WHERE id_transaksi IN ($id_str)";
            mysqli_query($koneksi, $sql);
        }
    }
    header("Location: pemberkasan.php?s=1");
    exit();
}

include "includes/cek_sesi.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">    <title>Tracking Pemberkasan KPR - Shankara Dev</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        .checklist-group-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary-blue);
            margin-top: 12px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        /* MULTISELECT FILTER */
        .ms-btn {
            background-color: #ffffff;
            border: 1px solid #d7e0e8;
            border-radius: 8px;
            font-size: 0.8rem;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            color: var(--text-dark);
            min-width: 110px;
        }

        .ms-btn:hover {
            border-color: #9db4a8;
        }

        .ms-btn:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            outline: none;
        }

        .dropdown-menu.ms-menu {
            min-width: 250px;
            border-radius: 12px;
            border-color: #e2e8f0;
        }

        .ms-menu .ms-list {
            max-height: 220px;
            overflow-y: auto;
        }

        .ms-menu .dropdown-item {
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 0.8rem;
        }

        .ms-menu .dropdown-item:hover,
        .ms-menu .dropdown-item:active {
            background-color: var(--light-blue);
            color: var(--primary-blue);
        }
    </style>
</head>
<body class="bg-light">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <!-- KONTEN UTAMA -->
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Tracking Pemberkasan & Dokumen</h4>
                <p class="text-muted small mb-0">Kelola kelengkapan 29 berkas KPR Subsidi/Komersil, verifikasi bank, & deadline.</p>
            </div>
        </div>

        <!-- TABEL DATA PEMBERKASAN -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span id="filterCounter" class="text-muted small fw-semibold">Memuat data...</span>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 d-none" id="btnBulkEdit" onclick="openBulkEditModal()">
                        <i class="bi bi-pencil-square me-1"></i> Edit Massal (<span id="bulkCount">0</span>)
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-success rounded-pill px-3" id="btnExportPdf">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetFilter">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Filter
                    </button>
                </div>
            </div>

            <div id="filterChips" class="d-flex flex-wrap gap-2 mb-3"></div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="berkasTable" style="font-size: 0.88rem;">
                    <thead>
                        <tr class="text-muted">
                            <th style="width:40px;" class="text-center">
                                <input type="checkbox" class="form-check-input" id="selectAllBulk" title="Pilih Semua">
                            </th>
                            <th>Konsumen</th>
                            <th>Cluster</th>
                            <th>Unit & Skema</th>
                            <th>Skema Akad</th>
                            <th>Kelengkapan Berkas</th>
                            <th>Status Bank</th>
                            <th>Bank Tujuan</th>
                            <th>Deadline Berkas</th>
                            <th>Tgl Wawancara</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                        <tr class="filters-row">
                            <th></th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="1" list="dlKonsumen" placeholder="Cari nama / WA...">
                                <datalist id="dlKonsumen"></datalist>
                            </th>
                            <th>
                                <div class="dropdown ms-widget" data-col="2" data-label="Cluster" data-default="Semua Cluster">
                                    <button type="button" class="ms-btn w-100 text-start" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="ms-label">Semua Cluster</span>
                                        <i class="bi bi-chevron-down small ms-caret"></i>
                                    </button>
                                    <div class="dropdown-menu ms-menu p-2 shadow">
                                        <div class="ms-list"></div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-danger ms-clear">Kosongkan</button>
                                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 ms-done" data-bs-dismiss="dropdown">Selesai</button>
                                        </div>
                                    </div>
                                </div>
                            </th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="3" list="dlUnitSkema" placeholder="Cari unit / skema...">
                                <datalist id="dlUnitSkema"></datalist>
                            </th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="5" placeholder="Cari kelengkapan...">
                            </th>
                            <th>
                                <div class="dropdown ms-widget" data-col="6" data-label="Status Bank" data-default="Semua Status">
                                    <button type="button" class="ms-btn w-100 text-start" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="ms-label">Semua Status</span>
                                        <i class="bi bi-chevron-down small ms-caret"></i>
                                    </button>
                                    <div class="dropdown-menu ms-menu p-2 shadow">
                                        <div class="ms-list"></div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-danger ms-clear">Kosongkan</button>
                                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 ms-done" data-bs-dismiss="dropdown">Selesai</button>
                                        </div>
                                    </div>
                                </div>
                            </th>
                            <th>
                                <div class="dropdown ms-widget" data-col="7" data-label="Bank Tujuan" data-default="Semua Bank">
                                    <button type="button" class="ms-btn w-100 text-start" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="ms-label">Semua Bank</span>
                                        <i class="bi bi-chevron-down small ms-caret"></i>
                                    </button>
                                    <div class="dropdown-menu ms-menu p-2 shadow">
                                        <div class="ms-list"></div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-danger ms-clear">Kosongkan</button>
                                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 ms-done" data-bs-dismiss="dropdown">Selesai</button>
                                        </div>
                                    </div>
                                </div>
                            </th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="8" list="dlDeadline" placeholder="dd/mm/yyyy">
                                <datalist id="dlDeadline"></datalist>
                            </th>
                            <th>
                                <input type="text" class="form-control form-control-sm" data-col="9" list="dlWawancara" placeholder="dd/mm/yyyy">
                                <datalist id="dlWawancara"></datalist>
                            </th>
                            <th class="text-center" style="min-width:80px;">
                                <span class="text-muted small">-</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM view_database_utama ORDER BY id_transaksi DESC";
                        $result = mysqli_query($koneksi, $query);

                        if ($result && mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $skema = $row['skema_bayar'] ?? 'Cash';
                                $pekerjaan = $row['pekerjaan'] ?? '';
                                $saved_docs = !empty($row['pemberkasan_json']) ? json_decode($row['pemberkasan_json'], true) : [];
                                if (!is_array($saved_docs)) $saved_docs = [];

                                // Dokumen default dicentang sesuai jenis pekerjaan (usaha/karyawan)
                                $default_docs = dokumenPekerjaan($pekerjaan);

                                // Dokumen Wajib Berdasarkan Skema Bayar
                                $required_docs = hitungDokumenWajib($skema, $master_dokumen);

                                // Hitung Progres
                                $progres = hitungProgresBerkas($saved_docs, $skema, $master_dokumen);
                                $total_required = $progres['total'];
                                $total_checked = $progres['checked'];
                                $persen = $progres['persen'];
                                $berkas_lengkap = $progres['lengkap'];

                                // Status Bank Badge
                                $status_bank = $row['status_bank'] ?? 'Pemberkasan';
                                $badge_bank = 'bg-secondary';
                                if ($status_bank == 'Wawancara') $badge_bank = 'bg-info text-dark';
                                elseif ($status_bank == 'OTS') $badge_bank = 'bg-primary';
                                elseif ($status_bank == 'ACC SP3K') $badge_bank = 'bg-success';
                                elseif ($status_bank == 'Ditolak') $badge_bank = 'bg-danger';

                                // Deadline Pengingat (hilang otomatis jika berkas sudah lengkap)
                                $deadline = $row['deadline_berkas'] ?? null;
                                $deadline_html = '<span class="text-muted">-</span>';
                                if ($berkas_lengkap) {
                                    $deadline_html = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Berkas Lengkap</span>';
                                } elseif (!empty($deadline) && $deadline != '0000-00-00') {
                                    $tgl_deadline = strtotime($deadline);
                                    $sisa_hari = floor(($tgl_deadline - strtotime(date('Y-m-d'))) / (60 * 60 * 24));

                                    if ($sisa_hari < 0) {
                                        $deadline_html = '<span class="badge bg-danger">Lewat Deadline ('.date('d M Y', $tgl_deadline).')</span>';
                                    } elseif ($sisa_hari <= 3) {
                                        $deadline_html = '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>H-'.$sisa_hari.' ('.date('d M Y', $tgl_deadline).')</span>';
                                    } else {
                                        $deadline_html = '<span class="fw-semibold text-dark">'.date('d M Y', $tgl_deadline).'</span>';
                                    }
                                }
                        ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-bulk-cb" value="<?= $row['id_transaksi'] ?>">
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['name_lengkap'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['no_whatsapp'] ?? '-') ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($row['cluster_name'] ?? 'Shankara Residence') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-dark">Blok <?= htmlspecialchars($row['no_blok'] ?? '-') ?></span>
                                <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($skema) ?></span>
                            </td>
                            <td>
                                <?php $skema_akad_cell = $row['skema_akad'] ?? ''; ?>
                                <?php if (!empty($skema_akad_cell)): ?>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($skema_akad_cell) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div class="progress flex-grow-1" style="height: 7px;">
                                        <div class="progress-bar <?= ($persen == 100)?'bg-success':'bg-warning' ?>" style="width: <?= $persen ?>%;"></div>
                                    </div>
                                    <span class="fw-bold text-dark" style="font-size: 0.78rem;"><?= $total_checked ?>/<?= $total_required ?> (<?= $persen ?>%)</span>
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">
                                    <?= ($persen == 100) ? '<i class="bi bi-check-circle-fill text-success me-1"></i>Berkas Lengkap' : 'Progres Kelengkapan Dokumen' ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge <?= $badge_bank ?> px-3 py-2 rounded-pill"><?= htmlspecialchars($status_bank) ?></span>
                            </td>
                            <td>
                                <?php $bank_tujuan = $row['bank_tujuan'] ?? ''; ?>
                                <?php if (!empty($bank_tujuan)): ?>
                                    <span class="badge bg-dark px-3 py-2 rounded-pill"><i class="bi bi-bank me-1"></i><?= htmlspecialchars($bank_tujuan) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $deadline_html ?></td>
                            <td>
                                <?php
                                $tgl_wawancara = $row['tgl_wawancara'] ?? null;
                                if (!empty($tgl_wawancara) && $tgl_wawancara != '0000-00-00') {
                                    $tgl_ww = strtotime($tgl_wawancara);
                                    $hari_ini = strtotime(date('Y-m-d'));
                                    if ($tgl_ww < $hari_ini) {
                                        echo '<span class="badge bg-danger">Wawancara '.date('d M Y', $tgl_ww).'</span>';
                                    } elseif (date('Y-m-d', $tgl_ww) == date('Y-m-d')) {
                                        echo '<span class="badge bg-warning text-dark">Hari Ini '.date('d M Y', $tgl_ww).'</span>';
                                    } else {
                                        echo '<span class="fw-semibold text-dark">'.date('d M Y', $tgl_ww).'</span>';
                                    }
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-success border-0" data-bs-toggle="modal" data-bs-target="#modalBerkas<?= $row['id_transaksi'] ?>">
                                    <i class="bi bi-pencil-square fs-6"></i> Edit Berkas
                                </button>

                                <!-- MODAL UPDATE PEMBERKASAN -->
                                <div class="modal fade" id="modalBerkas<?= $row['id_transaksi'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg text-start">
                                        <div class="modal-content border-0 rounded-4 shadow">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold">Checklist Dokumen Pemberkasan</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="pemberkasan.php" method="POST" id="formBerkas<?= $row['id_transaksi'] ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_transaksi" value="<?= $row['id_transaksi'] ?>">
                                                <input type="hidden" name="scroll_to" value="0">
                                                <div class="modal-body py-3">
                                                    <div class="d-flex justify-content-between bg-light p-3 rounded-3 mb-3 border">
                                                        <div>
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['name_lengkap'] ?? '-') ?></div>
                                                            <small class="text-muted">Unit: Blok <?= htmlspecialchars($row['no_blok'] ?? '-') ?> | Skema: <strong><?= htmlspecialchars($skema) ?></strong></small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-success mb-1"><?= $total_checked ?> / <?= $total_required ?> Terisi</span>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3" onclick="checkAllBerkas(<?= $row['id_transaksi'] ?>)">
                                                            <i class="bi bi-check2-all me-1"></i> Checklist Semua
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="uncheckAllBerkas(<?= $row['id_transaksi'] ?>)">
                                                            <i class="bi bi-x-circle me-1"></i> Kosongkan Semua
                                                        </button>
                                                        <?php if (isPekerjaanUsaha($pekerjaan)): ?>
                                                            <span class="badge bg-light text-dark border align-self-center"><i class="bi bi-briefcase me-1"></i>Pekerjaan Usaha: Rek Koran, SKU/NIB, Bon/Nota</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="row g-2 overflow-auto" style="max-height: 400px; padding-right: 5px;">
                                                        
                                                        <!-- KELOMPOK 1: IDENTITAS -->
                                                        <div class="col-12"><div class="checklist-group-title"><i class="bi bi-person-vcard me-1"></i> I. Identitas & Legalitas Diri</div></div>
                                                        <?php 
                                                        $cat_1 = ['doc_1', 'doc_2', 'doc_3', 'doc_4', 'doc_11'];
                                        foreach ($cat_1 as $k) {
                                            $checked = (isset($saved_docs[$k]) && $saved_docs[$k] == 1) ? 'checked' : '';
                                            if (empty($saved_docs) && in_array($k, $default_docs)) $checked = 'checked';
                                        ?>
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input berkas-cb" type="checkbox" name="berkas[<?= $k ?>]" value="1" id="<?= $k ?>_<?= $row['id_transaksi'] ?>" <?= $checked ?>>
                                                                <label class="form-check-label small" for="<?= $k ?>_<?= $row['id_transaksi'] ?>"><?= $master_dokumen[$k] ?></label>
                                                            </div>
                                                        </div>
                                                        <?php } ?>

                                                        <!-- KELOMPOK 2: PEKERJAAN -->
                                                        <div class="col-12"><div class="checklist-group-title"><i class="bi bi-briefcase me-1"></i> II. Pekerjaan & Penghasilan</div></div>
                                                        <?php 
                                                        $cat_2 = ['doc_5', 'doc_6', 'doc_7', 'doc_8', 'doc_9', 'doc_10'];
                                        foreach ($cat_2 as $k) {
                                            $checked = (isset($saved_docs[$k]) && $saved_docs[$k] == 1) ? 'checked' : '';
                                            if (empty($saved_docs) && in_array($k, $default_docs)) $checked = 'checked';
                                        ?>
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input berkas-cb" type="checkbox" name="berkas[<?= $k ?>]" value="1" id="<?= $k ?>_<?= $row['id_transaksi'] ?>" <?= $checked ?>>
                                                                <label class="form-check-label small" for="<?= $k ?>_<?= $row['id_transaksi'] ?>"><?= $master_dokumen[$k] ?></label>
                                                            </div>
                                                        </div>
                                                        <?php } ?>

                                                        <!-- KELOMPOK 3: KHUSUS FLPP / SUBSIDI -->
                                                        <?php if (strpos(strtolower($skema), 'subsidi') !== false): ?>
                                                        <div class="col-12"><div class="checklist-group-title"><i class="bi bi-file-earmark-text me-1"></i> III. Persyaratan FLPP / KPR Subsidi</div></div>
                                                        <?php 
                                                        $cat_3 = ['doc_12', 'doc_13', 'doc_14', 'doc_15', 'doc_16', 'doc_17', 'doc_18', 'doc_19', 'doc_20', 'doc_21', 'doc_22', 'doc_23'];
                                        foreach ($cat_3 as $k) {
                                            $checked = (isset($saved_docs[$k]) && $saved_docs[$k] == 1) ? 'checked' : '';
                                            if (empty($saved_docs) && in_array($k, $default_docs)) $checked = 'checked';
                                        ?>
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input berkas-cb" type="checkbox" name="berkas[<?= $k ?>]" value="1" id="<?= $k ?>_<?= $row['id_transaksi'] ?>" <?= $checked ?>>
                                                                <label class="form-check-label small" for="<?= $k ?>_<?= $row['id_transaksi'] ?>"><?= $master_dokumen[$k] ?></label>
                                                            </div>
                                                        </div>
                                                        <?php } ?>
                                                        <?php endif; ?>

                                                        <!-- KELOMPOK 4: LEGALITAS & TRANSAKSI -->
                                                        <div class="col-12"><div class="checklist-group-title"><i class="bi bi-building-check me-1"></i> IV. Legalitas Bangunan & Administrasi</div></div>
                                                        <?php 
                                                        $cat_4 = ['doc_24', 'doc_25', 'doc_26', 'doc_27', 'doc_28', 'doc_29'];
                                        foreach ($cat_4 as $k) {
                                            $checked = (isset($saved_docs[$k]) && $saved_docs[$k] == 1) ? 'checked' : '';
                                            if (empty($saved_docs) && in_array($k, $default_docs)) $checked = 'checked';
                                        ?>
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input berkas-cb" type="checkbox" name="berkas[<?= $k ?>]" value="1" id="<?= $k ?>_<?= $row['id_transaksi'] ?>" <?= $checked ?>>
                                                                <label class="form-check-label small" for="<?= $k ?>_<?= $row['id_transaksi'] ?>"><?= $master_dokumen[$k] ?></label>
                                                            </div>
                                                        </div>
                                                        <?php } ?>

                                                    </div>

                                                    <hr class="my-3">

                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Status Progres Bank</label>
                                                            <select name="status_bank" class="form-select rounded-3">
                                                                <option value="Pemberkasan" <?= ($status_bank == 'Pemberkasan') ? 'selected' : '' ?>>Pemberkasan Konsumen</option>
                                                                <option value="Wawancara" <?= ($status_bank == 'Wawancara') ? 'selected' : '' ?>>Proses Wawancara Bank</option>
                                                                <option value="OTS" <?= ($status_bank == 'OTS') ? 'selected' : '' ?>>On The Spot (OTS) / Survey</option>
                                                                <option value="ACC SP3K" <?= ($status_bank == 'ACC SP3K') ? 'selected' : '' ?>>ACC - Terbit SP3K</option>
                                                                <option value="Ditolak" <?= ($status_bank == 'Ditolak') ? 'selected' : '' ?>>Ditolak Bank / Batal</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Arahkan Berkas Ke Bank</label>
                                                            <select name="bank_tujuan" class="form-select rounded-3">
                                                                <option value="">-- Pilih Bank Tujuan --</option>
                                                                <?php 
                                                                $daftar_bank = ['Bank BNI', 'BRI', 'BSI', 'Bank Kalbar Syariah', 'Bank Mandiri'];
                                                                foreach ($daftar_bank as $bk) {
                                                                    $sel = ($bank_tujuan == $bk) ? 'selected' : '';
                                                                    echo "<option value=\"" . htmlspecialchars($bk) . "\" $sel>" . htmlspecialchars($bk) . "</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Deadline Penyerahan Berkas</label>
                                                            <input type="date" name="deadline_berkas" id="deadline_berkas_<?= $row['id_transaksi'] ?>" class="form-control rounded-3" value="<?= $row['deadline_berkas'] ?? '' ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Tanggal Wawancara Bank</label>
                                                            <input type="date" name="tgl_wawancara" id="tgl_wawancara_<?= $row['id_transaksi'] ?>" class="form-control rounded-3" value="<?= $row['tgl_wawancara'] ?? '' ?>">
                                                             <small class="text-muted">Kosongkan jika belum dijadwalkan.</small>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Skema Akad</label>
                                                            <select name="skema_akad" class="form-select rounded-3">
                                                                <option value="">-- Pilih Skema Akad --</option>
                                                                <?php
                                                                $opsi_akad = ['Cash', 'Cash Tempo (Akad Developer)', 'KPR Bank Syariah', 'KPR Bank Konvensional'];
                                                                $skema_akad_now = $row['skema_akad'] ?? '';
                                                                foreach ($opsi_akad as $oa) {
                                                                    $sel = ($skema_akad_now == $oa) ? 'selected' : '';
                                                                    echo "<option value=\"".htmlspecialchars($oa)."\" $sel>".htmlspecialchars($oa)."</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="update_pemberkasan" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">Simpan Perubahan Berkas</button>
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
                            echo "<tr><td colspan='11' class='text-center text-muted py-4'>Belum ada data transaksi.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div id="emptyFilterRow" class="text-center text-muted py-5 d-none">
                    <i class="bi bi-search display-5 d-block mb-2 text-secondary"></i>
                    Tidak ada data pemberkasan yang cocok dengan filter.
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODAL BULK EDIT -->
<div class="modal fade" id="modalBulkEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Massal Pemberkasan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="pemberkasan.php" method="POST" id="formBulkEdit">
                <?= csrf_field() ?>
                <input type="hidden" name="bulk_update_pemberkasan" value="1">
                <input type="hidden" name="scroll_to" value="0">
                <div class="modal-body py-3">
                    <div class="alert alert-info small rounded-3 mb-3" id="bulkInfo">
                        <i class="bi bi-info-circle me-1"></i> Akan diterapkan ke <strong id="bulkCountInfo">0</strong> data yang dipilih.
                        <br><small class="text-muted">Kosongkan field yang tidak ingin diubah.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Skema Akad</label>
                            <select name="bulk_skema_akad" class="form-select rounded-3">
                                <option value="">-- Tidak Diubah --</option>
                                <option value="Cash">Cash</option>
                                <option value="Cash Tempo (Akad Developer)">Cash Tempo (Akad Developer)</option>
                                <option value="KPR Bank Syariah">KPR Bank Syariah</option>
                                <option value="KPR Bank Konvensional">KPR Bank Konvensional</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Status Bank</label>
                            <select name="bulk_status_bank" class="form-select rounded-3">
                                <option value="">-- Tidak Diubah --</option>
                                <option value="Pemberkasan">Pemberkasan Konsumen</option>
                                <option value="Wawancara">Proses Wawancara Bank</option>
                                <option value="OTS">On The Spot (OTS) / Survey</option>
                                <option value="ACC SP3K">ACC - Terbit SP3K</option>
                                <option value="Ditolak">Ditolak Bank / Batal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bank Tujuan</label>
                            <select name="bulk_bank_tujuan" class="form-select rounded-3">
                                <option value="">-- Tidak Diubah --</option>
                                <?php
                                $daftar_bank = ['Bank BNI', 'BRI', 'BSI', 'Bank Kalbar Syariah', 'Bank Mandiri'];
                                foreach ($daftar_bank as $bk) {
                                    echo "<option value=\"".htmlspecialchars($bk)."\">".htmlspecialchars($bk)."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Deadline Berkas</label>
                            <input type="date" name="bulk_deadline_berkas" class="form-control rounded-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tanggal Wawancara</label>
                            <input type="date" name="bulk_tgl_wawancara" class="form-control rounded-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white rounded-3 px-4" style="background-color: var(--primary-blue);">
                        <i class="bi bi-check-lg me-1"></i> Terapkan ke Semua
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT FILTER REALTIME -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // FILTER CERDAS PER KOLOM (multi-select)
    // ==========================================
    const table = document.getElementById('berkasTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const filters = Array.from(table.querySelectorAll('.filters-row input[type="text"]'));
    const counter = document.getElementById('filterCounter');
    const emptyRow = document.getElementById('emptyFilterRow');
    const chipsBox = document.getElementById('filterChips');

    const COL_LABELS = { 0: 'Checkbox', 1: 'Konsumen', 2: 'Cluster', 3: 'Unit & Skema', 4: 'Skema Akad', 5: 'Kelengkapan', 6: 'Status Bank', 7: 'Bank Tujuan', 8: 'Deadline', 9: 'Wawancara' };

    const cellText = (row, col) => {
        const cell = row.cells[col];
        return cell ? cell.textContent.trim().toLowerCase() : '';
    };

    const distinctValues = (col) => {
        const seen = new Set();
        rows.forEach(r => {
            const v = cellText(r, col);
            if (v && v !== '-') seen.add(v);
        });
        return Array.from(seen).sort();
    };

    // Isi otomatis datalist (autocomplete) untuk kolom teks
    const fillDatalist = (col, listId) => {
        const dl = document.getElementById(listId);
        if (!dl) return;
        distinctValues(col).forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            dl.appendChild(opt);
        });
    };
    fillDatalist(1, 'dlKonsumen');
    fillDatalist(3, 'dlUnitSkema');
    fillDatalist(8, 'dlDeadline');
    fillDatalist(9, 'dlWawancara');

    // Bangun widget multi-select (checkbox) dari nilai unik kolom
    const buildMultiselects = () => {
        document.querySelectorAll('.ms-widget').forEach(widget => {
            const col = parseInt(widget.dataset.col);
            const list = widget.querySelector('.ms-list');
            if (!list) return;
            list.innerHTML = '';
            distinctValues(col).forEach(v => {
                const label = document.createElement('label');
                label.className = 'dropdown-item d-flex align-items-center gap-2 py-1';
                label.style.cursor = 'pointer';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'form-check-input mt-0 ms-cb';
                cb.value = v;
                const span = document.createElement('span');
                span.textContent = v;
                label.appendChild(cb);
                label.appendChild(span);
                list.appendChild(label);
                cb.addEventListener('change', applyFilters);
            });
            const clearBtn = widget.querySelector('.ms-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    widget.querySelectorAll('.ms-cb').forEach(cb => { cb.checked = false; });
                    applyFilters();
                });
            }
        });
    };

    const msSelected = (widget) => {
        return Array.from(widget.querySelectorAll('.ms-cb:checked')).map(cb => cb.value.toLowerCase());
    };

    const updateMsLabels = () => {
        document.querySelectorAll('.ms-widget').forEach(widget => {
            const labelEl = widget.querySelector('.ms-label');
            if (!labelEl) return;
            const checked = widget.querySelectorAll('.ms-cb:checked').length;
            labelEl.textContent = checked === 0 ? (widget.dataset.default || 'Semua') : (checked + ' dipilih');
        });
    };

    const makeChip = (label, value, onRemove) => {
        const span = document.createElement('span');
        span.className = 'filter-chip badge rounded-pill d-inline-flex align-items-center gap-1';
        const text = document.createElement('span');
        text.innerHTML = label + ': <strong>' + value.replace(/</g, '&lt;') + '</strong>';
        span.appendChild(text);
        const x = document.createElement('button');
        x.type = 'button';
        x.className = 'btn-close';
        x.setAttribute('aria-label', 'Hapus filter');
        x.addEventListener('click', onRemove);
        span.appendChild(x);
        return span;
    };

    const updateChips = () => {
        if (!chipsBox) return;
        chipsBox.innerHTML = '';
        filters.forEach(el => {
            const q = el.value.trim();
            if (!q) return;
            chipsBox.appendChild(makeChip(COL_LABELS[el.dataset.col], q, () => { el.value = ''; applyFilters(); }));
        });
        document.querySelectorAll('.ms-widget').forEach(widget => {
            const col = parseInt(widget.dataset.col);
            const label = widget.dataset.label || COL_LABELS[col];
            widget.querySelectorAll('.ms-cb:checked').forEach(cb => {
                chipsBox.appendChild(makeChip(label, cb.value, () => { cb.checked = false; applyFilters(); }));
            });
        });
        chipsBox.classList.toggle('d-none', chipsBox.childElementCount === 0);
    };

    const applyFilters = () => {
        const widgets = Array.from(document.querySelectorAll('.ms-widget'));
        let shown = 0;
        rows.forEach(row => {
            let show = true;
            for (const el of filters) {
                const q = el.value.trim().toLowerCase();
                if (!q) continue;
                if (cellText(row, parseInt(el.dataset.col)).indexOf(q) === -1) { show = false; break; }
            }
            if (show) {
                for (const widget of widgets) {
                    const sel = msSelected(widget);
                    if (sel.length === 0) continue;
                    if (sel.indexOf(cellText(row, parseInt(widget.dataset.col))) === -1) { show = false; break; }
                }
            }
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        updateChips();
        updateMsLabels();
        if (counter) counter.textContent = 'Menampilkan ' + shown + ' dari ' + rows.length + ' data';
        if (emptyRow) emptyRow.classList.toggle('d-none', shown !== 0);
    };

    filters.forEach(el => {
        el.addEventListener('input', applyFilters);
        el.addEventListener('change', applyFilters);
    });

    const btnReset = document.getElementById('btnResetFilter');
    if (btnReset) {
        btnReset.addEventListener('click', () => {
            filters.forEach(el => { el.value = ''; });
            document.querySelectorAll('.ms-cb').forEach(cb => { cb.checked = false; });
            applyFilters();
        });
    }

    const btnExport = document.getElementById('btnExportPdf');
    if (btnExport) {
        btnExport.addEventListener('click', () => {
            const params = new URLSearchParams();
            filters.forEach(el => {
                const q = el.value.trim();
                if (q) params.set('q' + el.dataset.col, q);
            });
            document.querySelectorAll('.ms-widget').forEach(widget => {
                const sel = Array.from(widget.querySelectorAll('.ms-cb:checked')).map(cb => cb.value);
                if (sel.length) params.set('c' + widget.dataset.col, sel.join(','));
            });
            window.open('print/cetak_pemberkasan.php?' + params.toString(), '_blank');
        });
    }

    buildMultiselects();
    applyFilters();

    // ==========================================
    // BULK EDIT CHECKBOXES
    // ==========================================
    const selectAllCb = document.getElementById('selectAllBulk');
    const bulkCbs = document.querySelectorAll('.row-bulk-cb');
    const btnBulk = document.getElementById('btnBulkEdit');
    const bulkCountEl = document.getElementById('bulkCount');

    if (selectAllCb) {
        selectAllCb.addEventListener('change', function () {
            document.querySelectorAll('.row-bulk-cb').forEach(cb => {
                const tr = cb.closest('tr');
                if (tr && tr.style.display !== 'none') cb.checked = selectAllCb.checked;
            });
            updateBulkCount();
        });
    }
    bulkCbs.forEach(cb => {
        cb.addEventListener('change', updateBulkCount);
    });
});

function openBulkEditModal() {
    var checked = document.querySelectorAll('.row-bulk-cb:checked');
    if (checked.length === 0) return;
    var form = document.getElementById('formBulkEdit');
    form.querySelectorAll('input[name="bulk_ids[]"]').forEach(e => e.remove());
    checked.forEach(cb => {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'bulk_ids[]';
        inp.value = cb.value;
        form.appendChild(inp);
    });
    document.getElementById('bulkCountInfo').textContent = checked.length;
    var modal = new bootstrap.Modal(document.getElementById('modalBulkEdit'));
    modal.show();
}

function checkAllBerkas(id) {
    var form = document.getElementById('formBerkas' + id);
    if (form) {
        var boxes = form.querySelectorAll('input[type="checkbox"]');
        boxes.forEach(function(box) { box.checked = true; });
        clearDeadline(id);
    }
}

function uncheckAllBerkas(id) {
    var form = document.getElementById('formBerkas' + id);
    if (form) {
        var boxes = form.querySelectorAll('input[type="checkbox"]');
        boxes.forEach(function(box) { box.checked = false; });
    }
}

function clearDeadline(id) {
    var input = document.getElementById('deadline_berkas_' + id);
    if (input) {
        input.value = '';
        input.placeholder = 'Tercentang semua otomatis tanpa deadline';
    }
}

function updateBulkCount() {
    var checked = document.querySelectorAll('.row-bulk-cb:checked');
    var n = checked.length;
    var bulkCountEl = document.getElementById('bulkCount');
    var btnBulk = document.getElementById('btnBulkEdit');
    if (bulkCountEl) bulkCountEl.textContent = n;
    if (btnBulk) btnBulk.classList.toggle('d-none', n === 0);
}

// Shift+click: checklist range (main table + modal)
document.addEventListener('DOMContentLoaded', function () {
    var lastClicked = {};
    var lastState = {};

    document.addEventListener('mousedown', function (e) {
        var cb = e.target;
        if (!cb.classList.contains('row-bulk-cb') && !cb.classList.contains('berkas-cb')) return;
        var scope = cb.closest('.modal') || document;
        var key = scope.id || '_main';
        var allCbs = Array.from(scope.querySelectorAll(cb.classList.contains('row-bulk-cb') ? '.row-bulk-cb' : '.berkas-cb'));
        var idx = allCbs.indexOf(cb);
        lastClicked[key] = idx;
        lastState[key] = cb.checked;
    }, true);

    document.addEventListener('click', function (e) {
        var cb = e.target;
        if (!cb.classList.contains('row-bulk-cb') && !cb.classList.contains('berkas-cb')) return;
        var scope = cb.closest('.modal') || document;
        var key = scope.id || '_main';
        var allCbs = Array.from(scope.querySelectorAll(cb.classList.contains('row-bulk-cb') ? '.row-bulk-cb' : '.berkas-cb'));
        var idx = allCbs.indexOf(cb);
        if (e.shiftKey && lastClicked[key] !== undefined) {
            var prev = lastClicked[key];
            var start = Math.min(prev, idx);
            var end = Math.max(prev, idx);
            var state = lastState[key];
            for (var i = start; i <= end; i++) {
                allCbs[i].checked = !state;
            }
            updateBulkCount();
        }
        lastClicked[key] = idx;
        lastState[key] = allCbs[idx].checked;
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Save scroll position before form submit
    document.querySelectorAll('form[id^="formBerkas"], #formBulkEdit').forEach(function (form) {
        form.addEventListener('submit', function () {
            var scrollInput = form.querySelector('input[name="scroll_to"]');
            if (scrollInput) scrollInput.value = window.scrollY;
        });
    });

    // Restore scroll position after redirect
    var params = new URLSearchParams(window.location.search);
    if (params.has('sid')) {
        window.scrollTo(0, parseInt(params.get('sid')) || 0);
        history.replaceState(null, '', 'pemberkasan.php');
    } else if (params.has('s')) {
        history.replaceState(null, '', 'pemberkasan.php');
    }

    // Success toast
    if (params.has('s')) {
        showToast('Berhasil diperbarui!', 'success');
    }
});

function showToast(msg, type) {
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + type + ' position-fixed shadow';
    toast.style.cssText = 'top:20px;right:20px;z-index:9999;min-width:250px;opacity:0;transition:opacity 0.3s';
    toast.innerHTML = '<i class="bi bi-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>' + msg;
    document.body.appendChild(toast);
    setTimeout(function () { toast.style.opacity = '1'; }, 10);
    setTimeout(function () { toast.style.opacity = '0'; setTimeout(function () { toast.remove(); }, 300); }, 2500);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
