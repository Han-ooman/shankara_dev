<?php

require_once __DIR__ . '/includes/security.php'; // handler sesi DB harus aktif SEBELUM session_start
session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
include "includes/koneksi.php";

function rupiah($n) { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

// Tambah Transaksi
if (isset($_POST['tambah_transaksi'])) {
    $tanggal  = sdb_real_escape_string($koneksi, $_POST['tanggal']);
    $jenis    = sdb_real_escape_string($koneksi, $_POST['jenis']);
    $kategori = sdb_real_escape_string($koneksi, $_POST['kategori']);
    $cluster  = intval($_POST['cluster_id'] ?? 0) ?: 'NULL';
    $desk     = sdb_real_escape_string($koneksi, $_POST['deskripsi']);
    $nominal  = floatval($_POST['nominal']);
    $metode   = sdb_real_escape_string($koneksi, $_POST['metode_bayar']);
    $noref    = sdb_real_escape_string($koneksi, $_POST['no_ref']);

    sdb_query($koneksi, "INSERT INTO data_keuangan (tanggal, jenis, kategori, cluster_id, deskripsi, nominal, metode_bayar, no_ref)
                            VALUES ('$tanggal','$jenis','$kategori',$cluster,'$desk',$nominal,'$metode','$noref')");
    header("Location: keuangan.php");
    exit();
}

// Edit Transaksi
if (isset($_POST['edit_transaksi'])) {
    $id       = intval($_POST['id_keuangan']);
    $tanggal  = sdb_real_escape_string($koneksi, $_POST['tanggal']);
    $jenis    = sdb_real_escape_string($koneksi, $_POST['jenis']);
    $kategori = sdb_real_escape_string($koneksi, $_POST['kategori']);
    $cluster  = intval($_POST['cluster_id'] ?? 0) ?: 'NULL';
    $desk     = sdb_real_escape_string($koneksi, $_POST['deskripsi']);
    $nominal  = floatval($_POST['nominal']);
    $metode   = sdb_real_escape_string($koneksi, $_POST['metode_bayar']);
    $noref    = sdb_real_escape_string($koneksi, $_POST['no_ref']);

    sdb_query($koneksi, "UPDATE data_keuangan SET tanggal='$tanggal', jenis='$jenis', kategori='$kategori', cluster_id=$cluster, deskripsi='$desk', nominal=$nominal, metode_bayar='$metode', no_ref='$noref' WHERE id_keuangan=$id");
    header("Location: keuangan.php");
    exit();
}

// Hapus Transaksi (dengan token CSRF di URL)
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = intval($_GET['id']);
    sdb_query($koneksi, "DELETE FROM data_keuangan WHERE id_keuangan = '$id_hapus'");
    header("Location: keuangan.php");
    exit();
}

// Statistik
$stat = ['pemasukan'=>0, 'pengeluaran'=>0, 'saldo'=>0, 'jml_transaksi'=>0];
$q = sdb_query($koneksi, "SELECT jenis, COUNT(*) AS jml, SUM(nominal) AS total FROM data_keuangan GROUP BY jenis");
while ($r = sdb_fetch_assoc($q)) {
    if ($r['jenis'] === 'Pemasukan') { $stat['pemasukan'] = floatval($r['total']); $stat['jml_transaksi'] += $r['jml']; }
    else { $stat['pengeluaran'] = floatval($r['total']); $stat['jml_transaksi'] += $r['jml']; }
}
$stat['saldo'] = $stat['pemasukan'] - $stat['pengeluaran'];

// Pemasukan & Pengeluaran bulan ini
$bulan_ini = date('Y-m');
$qbi = sdb_query($koneksi, "SELECT COALESCE(SUM(nominal),0) AS t FROM data_keuangan WHERE jenis='Pemasukan' AND DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini'");
$masuk_bulan = floatval(sdb_fetch_assoc($qbi)['t']);
$qbo = sdb_query($koneksi, "SELECT COALESCE(SUM(nominal),0) AS t FROM data_keuangan WHERE jenis='Pengeluaran' AND DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini'");
$keluar_bulan = floatval(sdb_fetch_assoc($qbo)['t']);

$kategori_pemasukan = ['Penjualan Unit', 'DP Rumah', 'Angsuran', 'Booking Fee', 'Biaya Provisi', 'Lainnya'];
$kategori_pengeluaran = ['Gaji Karyawan', 'Biaya Operasional', 'Biaya Marketing', 'Perawatan', 'Material Bangunan', 'Fee Broker', 'Pajak', 'Lainnya'];
$metode_bayar_list = ['Tunai', 'Transfer BCA', 'Transfer BRI', 'Transfer Mandiri', 'Transfer BSI', 'Lainnya'];

$clusters = [];
$qcl = sdb_query($koneksi, "SELECT id_cluster, cluster_name FROM data_cluster ORDER BY cluster_name");
while ($cl = sdb_fetch_assoc($qcl)) $clusters[] = $cl;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Keuangan Perusahaan - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .nominal-in  { color: #2563eb; font-weight: 700; }
        .nominal-out { color: #dc2626; font-weight: 700; }
    </style>
</head>
<body>
    <input type="hidden" id="csrf_global" value="<?= htmlspecialchars(csrf_token()) ?>">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>

    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1">Keuangan Perusahaan</h4>
                <p class="text-muted small mb-0">Catat semua pemasukan dan pengeluaran perusahaan.</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i> Tambah Transaksi
            </button>
        </div>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;"><i class="bi bi-arrow-down-circle-fill"></i></div>
                        <div><div class="stat-value nominal-in"><?= rupiah($stat['pemasukan']) ?></div><div class="stat-label">Total Pemasukan</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#fef2f2; color:#dc2626;"><i class="bi bi-arrow-up-circle-fill"></i></div>
                        <div><div class="stat-value nominal-out"><?= rupiah($stat['pengeluaran']) ?></div><div class="stat-label">Total Pengeluaran</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:<?= $stat['saldo']>=0?'#dbeafe':'#fef2f2' ?>; color:<?= $stat['saldo']>=0?'#2563eb':'#dc2626' ?>;"><i class="bi bi-wallet2"></i></div>
                        <div><div class="stat-value" style="color:<?= $stat['saldo']>=0?'#2563eb':'#dc2626' ?>"><?= rupiah($stat['saldo']) ?></div><div class="stat-label">Saldo / Selisih</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i class="bi bi-receipt-cutoff"></i></div>
                        <div>
                            <div class="stat-value" style="color:var(--text-dark)"><?= $stat['jml_transaksi'] ?></div>
                            <div class="stat-label">Total Transaksi</div>
                            <small class="text-muted" style="font-size:0.65rem;">Bulan ini: <?= rupiah($masuk_bulan) ?> / <?= rupiah($keluar_bulan) ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span id="filterCounter" class="text-muted small fw-semibold">Memuat data...</span>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-success rounded-pill px-3" id="btnExportPdf"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetFilter"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</button>
                </div>
            </div>

            <div id="filterChips" class="d-flex flex-wrap gap-2 mb-3"></div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="keuTable" style="font-size: 0.88rem;">
                    <thead>
                        <tr class="text-muted">
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Kategori</th>
                            <th>Cluster</th>
                            <th>Deskripsi</th>
                            <th>Nominal</th>
                            <th>Metode Bayar</th>
                            <th>No. Ref</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                        <tr class="filters-row">
                            <th><input type="date" class="form-control form-control-sm" data-col="0"></th>
                            <th><select class="form-select form-select-sm" data-col="1"><option value="">Semua</option><option value="Pemasukan">Pemasukan</option><option value="Pengeluaran">Pengeluaran</option></select></th>
                            <th><input type="text" class="form-control form-control-sm" data-col="2" list="dlKategori" placeholder="Cari kategori..."><datalist id="dlKategori"></datalist></th>
                            <th><select class="form-select form-select-sm" data-col="3" id="filterCluster"><option value="">Semua Cluster</option><?php foreach ($clusters as $cl): ?><option value="<?= htmlspecialchars(strtolower($cl['cluster_name'])) ?>"><?= htmlspecialchars($cl['cluster_name']) ?></option><?php endforeach; ?></select></th>
                            <th><input type="text" class="form-control form-control-sm" data-col="4" placeholder="Cari deskripsi..."></th>
                            <th></th>
                            <th><input type="text" class="form-control form-control-sm" data-col="6" placeholder="Cari metode..."></th>
                            <th><input type="text" class="form-control form-control-sm" data-col="7" placeholder="Ref..."></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $qk = sdb_query($koneksi, "SELECT k.*, c.cluster_name FROM data_keuangan k LEFT JOIN data_cluster c ON k.cluster_id = c.id_cluster ORDER BY k.tanggal DESC, k.id_keuangan DESC");
                        if ($qk && sdb_num_rows($qk) > 0) {
                            while ($k = sdb_fetch_assoc($qk)) {
                                $badge_j = $k['jenis'] === 'Pemasukan' ? 'bg-success' : 'bg-danger';
                                $nom_cls = $k['jenis'] === 'Pemasukan' ? 'nominal-in' : 'nominal-out';
                                $nom_prefix = $k['jenis'] === 'Pemasukan' ? '+' : '-';
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= date('d M Y', strtotime($k['tanggal'])) ?></td>
                            <td><span class="badge <?= $badge_j ?> px-3 py-2 rounded-pill"><?= $k['jenis'] ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($k['kategori']) ?></span></td>
                            <td><small class="text-muted"><?= htmlspecialchars($k['cluster_name'] ?? '-') ?></small></td>
                            <td class="small"><?= htmlspecialchars($k['deskripsi'] ?? '-') ?></td>
                            <td class="<?= $nom_cls ?>"><?= $nom_prefix ?> <?= rupiah($k['nominal']) ?></td>
                            <td><small><?= htmlspecialchars($k['metode_bayar']) ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars($k['no_ref'] ?: '-') ?></small></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $k['id_keuangan'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalHapus" data-id="<?= $k['id_keuangan'] ?>" data-info="<?= date('d/m/Y', strtotime($k['tanggal'])) ?> - <?= htmlspecialchars($k['kategori']) ?> <?= rupiah($k['nominal']) ?>" title="Hapus"><i class="bi bi-trash"></i></button>
                                </div>

                                <!-- MODAL EDIT -->
                                <div class="modal fade text-start" id="modalEdit<?= $k['id_keuangan'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Transaksi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <form action="keuangan.php" method="POST">
                                                <input type="hidden" name="id_keuangan" value="<?= $k['id_keuangan'] ?>">
                                                <?= csrf_field() ?>
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Tanggal *</label><input type="date" name="tanggal" class="form-control" value="<?= $k['tanggal'] ?>" required></div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Jenis *</label><select name="jenis" class="form-select edit-jenis" data-id="<?= $k['id_keuangan'] ?>" required><option value="Pemasukan" <?= $k['jenis']==='Pemasukan'?'selected':'' ?>>Pemasukan</option><option value="Pengeluaran" <?= $k['jenis']==='Pengeluaran'?'selected':'' ?>>Pengeluaran</option></select></div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Kategori *</label>
                                                            <select name="kategori" class="form-select edit-kategori" data-id="<?= $k['id_keuangan'] ?>">
                                                                <?php foreach (($k['jenis']==='Pemasukan' ? $kategori_pemasukan : $kategori_pengeluaran) as $kg): ?>
                                                                    <option value="<?= $kg ?>" <?= $k['kategori']===$kg?'selected':'' ?>><?= $kg ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Cluster</label>
                                                            <select name="cluster_id" class="form-select">
                                                                <option value="0">-- Umum / Tidak Ada --</option>
                                                                <?php foreach ($clusters as $cl): ?><option value="<?= $cl['id_cluster'] ?>" <?= intval($k['cluster_id'])===$cl['id_cluster']?'selected':'' ?>><?= htmlspecialchars($cl['cluster_name']) ?></option><?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Nominal (Rp) *</label><input type="number" name="nominal" class="form-control" value="<?= $k['nominal'] ?>" min="0" required></div>
                                                        <div class="col-12"><label class="form-label small fw-semibold">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="2"><?= htmlspecialchars($k['deskripsi']) ?></textarea></div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">Metode Bayar</label>
                                                            <select name="metode_bayar" class="form-select">
                                                                <?php foreach ($metode_bayar_list as $mb): ?><option value="<?= $mb ?>" <?= $k['metode_bayar']===$mb?'selected':'' ?>><?= $mb ?></option><?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6"><label class="form-label small fw-semibold">No. Ref / Bukti</label><input type="text" name="no_ref" class="form-control" value="<?= htmlspecialchars($k['no_ref']) ?>" placeholder="INV-001..."></div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0"><button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button><button type="submit" name="edit_transaksi" class="btn text-white px-4 rounded-3" style="background-color:var(--primary-blue);">Simpan Perubahan</button></div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } } else { ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada transaksi keuangan.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow">
        <div class="modal-header bg-danger text-white rounded-top-4"><h5 class="modal-title fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center py-4"><i class="bi bi-trash text-danger display-4 mb-3 d-block"></i><h6 class="fw-bold">Hapus transaksi ini?</h6><p class="text-muted small mb-0" id="infoHapus">-</p></div>
        <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4"><button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button><a href="#" id="btnConfirmDelete" class="btn btn-danger px-4 rounded-3">Ya, Hapus</a></div>
    </div></div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Transaksi Keuangan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="keuangan.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-semibold">Tanggal *</label><input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Jenis *</label><select name="jenis" class="form-select" id="jenisBaru" required><option value="Pemasukan">Pemasukan</option><option value="Pengeluaran">Pengeluaran</option></select></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Kategori *</label><select name="kategori" class="form-select" id="kategoriBaru" required></select></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Cluster</label><select name="cluster_id" class="form-select" id="clusterBaru"><option value="0">-- Umum / Tidak Ada --</option><?php foreach ($clusters as $cl): ?><option value="<?= $cl['id_cluster'] ?>"><?= htmlspecialchars($cl['cluster_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Nominal (Rp) *</label><input type="number" name="nominal" class="form-control" min="0" required placeholder="0"></div>
                        <div class="col-12"><label class="form-label small fw-semibold">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="2" placeholder="Deskripsi transaksi..."></textarea></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">Metode Bayar</label><select name="metode_bayar" class="form-select"><?php foreach ($metode_bayar_list as $mb): ?><option value="<?= $mb ?>"><?= $mb ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label small fw-semibold">No. Ref / Bukti</label><input type="text" name="no_ref" class="form-control" placeholder="INV-001..."></div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button><button type="submit" name="tambah_transaksi" class="btn text-white px-4 rounded-3" style="background-color:var(--primary-blue);">Simpan Transaksi</button></div>
            </form>
        </div>
    </div>
</div>

<script>
var KAT_P = <?= json_encode($kategori_pemasukan) ?>;
var KAT_R = <?= json_encode($kategori_pengeluaran) ?>;

document.addEventListener('DOMContentLoaded', function () {
    // Kategori dropdown
    function fillKategori(jenis, targetEl) {
        var arr = jenis === 'Pemasukan' ? KAT_P : KAT_R;
        targetEl.innerHTML = '';
        arr.forEach(function(k){ var o = document.createElement('option'); o.value = k; o.textContent = k; targetEl.appendChild(o); });
    }

    var jenisBaru = document.getElementById('jenisBaru');
    var kategoriBaru = document.getElementById('kategoriBaru');
    if (jenisBaru && kategoriBaru) {
        fillKategori(jenisBaru.value, kategoriBaru);
        jenisBaru.addEventListener('change', function(){ fillKategori(this.value, kategoriBaru); });
    }

    document.querySelectorAll('.edit-jenis').forEach(function(sel) {
        var id = sel.dataset.id;
        var katSel = document.querySelector('.edit-kategori[data-id="'+id+'"]');
        if (!katSel) return;
        sel.addEventListener('change', function() {
            var cur = katSel.value;
            fillKategori(this.value, katSel);
            for (var i = 0; i < katSel.options.length; i++) { if (katSel.options[i].value === cur) { katSel.selectedIndex = i; break; } }
        });
    });

    // Filter table
    var table = document.getElementById('keuTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var filters = Array.from(table.querySelectorAll('.filters-row input, .filters-row select'));
    var counter = document.getElementById('filterCounter');

    var cellText = function(row, col) {
        var cell = row.cells[col];
        return cell ? cell.textContent.trim().toLowerCase() : '';
    };

    // Fill datalist
    var dlKat = document.getElementById('dlKategori');
    if (dlKat) {
        var seen = new Set();
        rows.forEach(function(r){ var v = cellText(r, 2); if (v) seen.add(v); });
        Array.from(seen).sort().forEach(function(v){ var o = document.createElement('option'); o.value = v; dlKat.appendChild(o); });
    }

    var applyFilters = function() {
        var shown = 0;
        rows.forEach(function(row) {
            var show = true;
            filters.forEach(function(el) {
                if (!show) return;
                var q = el.value.trim().toLowerCase();
                if (!q) return;
                var col = parseInt(el.dataset.col);
                var val = cellText(row, col);
                if (el.tagName === 'SELECT') { if (val !== q) show = false; }
                else if (el.type === 'date') {
                    if (col === 0) {
                        var rowDate = cellText(row, 0).replace(/(\d{2})\s(\w{3})\s(\d{4})/, '$3-$2-$1');
                        var months = {jan:'01',feb:'02',mar:'03',apr:'04',mei:'05',jun:'06',jul:'07',aug:'08',sep:'09',okt:'10',nov:'11',des:'12'};
                        Object.keys(months).forEach(function(m){ rowDate = rowDate.replace(m, months[m]); });
                        if (q && rowDate.indexOf(q) === -1) show = false;
                    }
                } else { if (val.indexOf(q) === -1) show = false; }
            });
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        if (counter) counter.textContent = 'Menampilkan ' + shown + ' dari ' + rows.length + ' data';
    };

    filters.forEach(function(el) { el.addEventListener('input', applyFilters); el.addEventListener('change', applyFilters); });

    var btnReset = document.getElementById('btnResetFilter');
    if (btnReset) btnReset.addEventListener('click', function() { filters.forEach(function(el){ el.value = ''; }); applyFilters(); });

    applyFilters();

    // Modal hapus
    var modalHapus = document.getElementById('modalHapus');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function(event) {
            var btn = event.relatedTarget;
            document.getElementById('infoHapus').innerText = btn.getAttribute('data-info');
            document.getElementById('btnConfirmDelete').href = 'keuangan.php?action=hapus&id=' + btn.getAttribute('data-id') + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }

    // Export PDF
    var btnExport = document.getElementById('btnExportPdf');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            var params = new URLSearchParams();
            filters.forEach(function(el) {
                var val = el.value.trim();
                if (val) params.set('f' + el.dataset.col, val);
            });
            window.open('print/cetak_keuangan.php?' + params.toString(), '_blank');
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
