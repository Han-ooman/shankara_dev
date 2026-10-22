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

function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

// Tambah slip gaji
if (isset($_POST['tambah_gaji'])) {
    $id_karyawan = intval($_POST['id_karyawan'] ?? 0);
    $periode     = sdb_real_escape_string($koneksi, $_POST['periode'] ?? '');
    $tanggal     = !empty($_POST['tanggal_gaji']) ? "'" . sdb_real_escape_string($koneksi, $_POST['tanggal_gaji']) . "'" : 'NULL';
    $pokok       = (float)str_replace(['.', ','], '', $_POST['gaji_pokok'] ?? 0);
    $makan       = (float)str_replace(['.', ','], '', $_POST['tunjangan_makan'] ?? 0);
    $transport   = (float)str_replace(['.', ','], '', $_POST['tunjangan_transport'] ?? 0);
    $jabatan     = (float)str_replace(['.', ','], '', $_POST['tunjangan_jabatan'] ?? 0);
    $bonus       = (float)str_replace(['.', ','], '', $_POST['bonus'] ?? 0);
    $potongan    = (float)str_replace(['.', ','], '', $_POST['potongan'] ?? 0);
    $ket_pot     = sdb_real_escape_string($koneksi, trim($_POST['keterangan_potongan'] ?? ''));
    $keterangan  = sdb_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));

    if ($id_karyawan > 0 && !empty($periode)) {
        sdb_query($koneksi, "INSERT INTO data_gaji (id_karyawan, periode, gaji_pokok, tunjangan_makan, tunjangan_transport, tunjangan_jabatan, bonus, potongan, keterangan_potongan, tanggal_gaji, keterangan)
                                VALUES ($id_karyawan, '$periode', $pokok, $makan, $transport, $jabatan, $bonus, $potongan, " . ($ket_pot !== '' ? "'$ket_pot'" : 'NULL') . ", $tanggal, " . ($keterangan !== '' ? "'$keterangan'" : 'NULL') . ")
                                ON DUPLICATE KEY UPDATE gaji_pokok=VALUES(gaji_pokok), tunjangan_makan=VALUES(tunjangan_makan), tunjangan_transport=VALUES(tunjangan_transport), tunjangan_jabatan=VALUES(tunjangan_jabatan), bonus=VALUES(bonus), potongan=VALUES(potongan), keterangan_potongan=VALUES(keterangan_potongan), tanggal_gaji=VALUES(tanggal_gaji), keterangan=VALUES(keterangan)");
    }
    header("Location: gaji.php");
    exit();
}

// Edit slip gaji
if (isset($_POST['edit_gaji'])) {
    $id_gaji     = intval($_POST['id_gaji']);
    $periode     = sdb_real_escape_string($koneksi, $_POST['periode'] ?? '');
    $tanggal     = !empty($_POST['tanggal_gaji']) ? "'" . sdb_real_escape_string($koneksi, $_POST['tanggal_gaji']) . "'" : 'NULL';
    $pokok       = (float)str_replace(['.', ','], '', $_POST['gaji_pokok'] ?? 0);
    $makan       = (float)str_replace(['.', ','], '', $_POST['tunjangan_makan'] ?? 0);
    $transport   = (float)str_replace(['.', ','], '', $_POST['tunjangan_transport'] ?? 0);
    $jabatan     = (float)str_replace(['.', ','], '', $_POST['tunjangan_jabatan'] ?? 0);
    $bonus       = (float)str_replace(['.', ','], '', $_POST['bonus'] ?? 0);
    $potongan    = (float)str_replace(['.', ','], '', $_POST['potongan'] ?? 0);
    $ket_pot     = sdb_real_escape_string($koneksi, trim($_POST['keterangan_potongan'] ?? ''));
    $keterangan  = sdb_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));

    sdb_query($koneksi, "UPDATE data_gaji SET periode='$periode', gaji_pokok=$pokok, tunjangan_makan=$makan, tunjangan_transport=$transport, tunjangan_jabatan=$jabatan, bonus=$bonus, potongan=$potongan, keterangan_potongan=" . ($ket_pot !== '' ? "'$ket_pot'" : 'NULL') . ", tanggal_gaji=$tanggal, keterangan=" . ($keterangan !== '' ? "'$keterangan'" : 'NULL') . " WHERE id_gaji=$id_gaji");
    header("Location: gaji.php");
    exit();
}

// Hapus slip gaji
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    sdb_query($koneksi, "DELETE FROM data_gaji WHERE id_gaji = " . intval($_GET['id']));
    header("Location: gaji.php");
    exit();
}

// Filter periode
$f_periode = trim($_GET['periode'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $f_periode)) $f_periode = '';

// Statistik
$stat = ['total'=>0, 'bulan'=>0, 'nominal'=>0];
$q = sdb_query($koneksi, "SELECT COUNT(*) AS jml, COALESCE(SUM(gaji_pokok+tunjangan_makan+tunjangan_transport+tunjangan_jabatan+bonus-potongan),0) AS total FROM data_gaji");
if (($r = sdb_fetch_assoc($q))) { $stat['total'] = (int)$r['jml']; $stat['nominal'] = (float)$r['total']; }

$bulan_ini = date('Y-m');
$q = sdb_query($koneksi, "SELECT COUNT(*) AS jml FROM data_gaji WHERE periode = '$bulan_ini'");
if (($r = sdb_fetch_assoc($q))) $stat['bulan'] = (int)$r['jml'];

// Data karyawan untuk dropdown
$karyawan_opts = [];
$qk = sdb_query($koneksi, "SELECT id_karyawan, nama_lengkap, jabatan FROM data_karyawan WHERE status='Aktif' ORDER BY nama_lengkap");
while ($rk = sdb_fetch_assoc($qk)) $karyawan_opts[] = $rk;

// Daftar periode unik
$periode_unik = [];
$qp = sdb_query($koneksi, "SELECT DISTINCT periode FROM data_gaji ORDER BY periode DESC");
while ($rp = sdb_fetch_assoc($qp)) $periode_unik[] = $rp['periode'];

// Nama bulan Indonesia
function nama_bulan($ym) {
    $b = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    $p = explode('-', $ym);
    return $b[$p[1]] . ' ' . $p[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Slip Gaji - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                <h4 class="fw-bold mb-1">Slip Gaji Karyawan</h4>
                <p class="text-muted small mb-0">Kelola dan cetak slip gaji karyawan per bulan.</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i> Tambah Slip Gaji
            </button>
        </div>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#eff6ff; color:var(--primary-blue);"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div><div class="stat-value"><?= $stat['total'] ?></div><div class="stat-label">Total Slip</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;"><i class="bi bi-calendar-check"></i></div>
                        <div><div class="stat-value"><?= $stat['bulan'] ?></div><div class="stat-label">Slip <?= $bulan_ini ?></div></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;"><i class="bi bi-wallet2"></i></div>
                        <div><div class="stat-value" style="font-size:1.05rem;"><?= rp($stat['nominal']) ?></div><div class="stat-label">Total Seluruh Penggajian</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span class="text-muted small fw-semibold" id="filterCounter">Memuat data...</span>
                <div class="d-flex gap-2 align-items-center">
                    <form method="GET" action="gaji.php" class="d-flex gap-2 align-items-center">
                        <select name="periode" class="form-select form-select-sm" style="width:auto;">
                            <option value="">Semua Periode</option>
                            <?php foreach ($periode_unik as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>" <?= $f_periode === $p ? 'selected' : '' ?>><?= nama_bulan($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-funnel me-1"></i>Filter</button>
                    </form>
                    <?php if ($f_periode !== ''): ?>
                        <a href="gaji.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="gajiTable" style="font-size: 0.88rem;">
                    <thead>
                        <tr class="text-muted">
                            <th>Periode</th>
                            <th>Karyawan</th>
                            <th>Gaji Pokok</th>
                            <th>Tunjangan</th>
                            <th>Bonus</th>
                            <th>Potongan</th>
                            <th class="text-end">Total Diterima</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $qg = "SELECT g.*, k.nama_lengkap, k.jabatan FROM data_gaji g
                               LEFT JOIN data_karyawan k ON g.id_karyawan = k.id_karyawan";
                        if ($f_periode !== '') $qg .= " WHERE g.periode = '" . sdb_real_escape_string($koneksi, $f_periode) . "'";
                        $qg .= " ORDER BY g.periode DESC, k.nama_lengkap ASC";
                        $rg = sdb_query($koneksi, $qg);

                        if ($rg && sdb_num_rows($rg) > 0) {
                            while ($g = sdb_fetch_assoc($rg)) {
                                $tunj = (float)$g['tunjangan_makan'] + (float)$g['tunjangan_transport'] + (float)$g['tunjangan_jabatan'];
                                $total = (float)$g['gaji_pokok'] + $tunj + (float)$g['bonus'] - (float)$g['potongan'];
                        ?>
                        <tr>
                            <td class="fw-semibold"><span class="badge bg-dark"><?= nama_bulan($g['periode']) ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($g['nama_lengkap'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($g['jabatan'] ?? '') ?></small>
                            </td>
                            <td><?= rp($g['gaji_pokok']) ?></td>
                            <td class="text-success"><?= rp($tunj) ?></td>
                            <td><?= rp($g['bonus']) ?></td>
                            <td class="text-danger"><?= rp($g['potongan']) ?></td>
                            <td class="text-end fw-bold" style="color:var(--primary-blue);"><?= rp($total) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="print/cetak_slip_gaji.php?id=<?= $g['id_gaji'] ?>&token=<?= urlencode(csrf_token()) ?>" target="_blank" class="btn btn-sm btn-outline-secondary border-0" title="Cetak Slip"><i class="bi bi-printer"></i></a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $g['id_gaji'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalHapus" data-id="<?= $g['id_gaji'] ?>" data-info="<?= htmlspecialchars(($g['nama_lengkap'] ?? '') . ' - ' . nama_bulan($g['periode'])) ?>" title="Hapus"><i class="bi bi-trash"></i></button>
                                </div>

                                <!-- MODAL EDIT -->
                                <div class="modal fade text-start" id="modalEdit<?= $g['id_gaji'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Slip Gaji</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">
                                                <p class="small text-muted mb-3"><strong>Karyawan:</strong> <?= htmlspecialchars($g['nama_lengkap']) ?> &mdash; <?= htmlspecialchars($g['jabatan'] ?? '-') ?></p>
                                                <form action="gaji.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id_gaji" value="<?= $g['id_gaji'] ?>">
                                                    <div class="row g-3">
                                                        <div class="col-md-4"><label class="form-label small fw-semibold">Periode (Bulan-Tahun) *</label><input type="month" name="periode" class="form-control" value="<?= htmlspecialchars($g['periode']) ?>" required></div>
                                                        <div class="col-md-4"><label class="form-label small fw-semibold">Tgl Gaji Dibayar</label><input type="date" name="tanggal_gaji" class="form-control" value="<?= htmlspecialchars($g['tanggal_gaji'] ?? '') ?>"></div>
                                                        <div class="col-md-4"><label class="form-label small fw-semibold">Gaji Pokok</label><input type="text" name="gaji_pokok" class="form-control rupiah-input" value="<?= number_format((float)$g['gaji_pokok'], 0, ',', '.') ?>"></div>
                                                        <div class="col-md-3"><label class="form-label small fw-semibold">Tunjangan Makan</label><input type="text" name="tunjangan_makan" class="form-control rupiah-input" value="<?= number_format((float)$g['tunjangan_makan'], 0, ',', '.') ?>"></div>
                                                        <div class="col-md-3"><label class="form-label small fw-semibold">Tunjangan Transport</label><input type="text" name="tunjangan_transport" class="form-control rupiah-input" value="<?= number_format((float)$g['tunjangan_transport'], 0, ',', '.') ?>"></div>
                                                        <div class="col-md-3"><label class="form-label small fw-semibold">Tunjangan Jabatan</label><input type="text" name="tunjangan_jabatan" class="form-control rupiah-input" value="<?= number_format((float)$g['tunjangan_jabatan'], 0, ',', '.') ?>"></div>
                                                        <div class="col-md-3"><label class="form-label small fw-semibold">Bonus / Insentif</label><input type="text" name="bonus" class="form-control rupiah-input" value="<?= number_format((float)$g['bonus'], 0, ',', '.') ?>"></div>
                                                        <div class="col-md-4"><label class="form-label small fw-semibold">Potongan</label><input type="text" name="potongan" class="form-control rupiah-input" value="<?= number_format((float)$g['potongan'], 0, ',', '.') ?>"></div>
                                                        <div class="col-md-8"><label class="form-label small fw-semibold">Keterangan Potongan</label><input type="text" name="keterangan_potongan" class="form-control" value="<?= htmlspecialchars($g['keterangan_potongan'] ?? '') ?>" placeholder="Kasbon, BPJS, dll."></div>
                                                        <div class="col-12"><label class="form-label small fw-semibold">Keterangan</label><textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($g['keterangan'] ?? '') ?></textarea></div>
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2 mt-4">
                                                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit_gaji" class="btn text-white px-4 rounded-3" style="background-color:var(--primary-blue);">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } } else { ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data slip gaji<?= $f_periode !== '' ? ' untuk periode terpilih' : '' ?>.</td></tr>
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
        <div class="modal-body text-center py-4"><i class="bi bi-trash text-danger display-4 mb-3 d-block"></i><h6 class="fw-bold">Hapus slip gaji ini?</h6><p class="text-muted small mb-0" id="infoHapus">-</p></div>
        <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4"><button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button><a href="#" id="btnConfirmDelete" class="btn btn-danger px-4 rounded-3">Ya, Hapus</a></div>
    </div></div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Slip Gaji</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="gaji.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Karyawan *</label>
                            <select name="id_karyawan" class="form-select" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($karyawan_opts as $ko): ?>
                                    <option value="<?= $ko['id_karyawan'] ?>"><?= htmlspecialchars($ko['nama_lengkap'] . ($ko['jabatan'] ? ' (' . $ko['jabatan'] . ')' : '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label small fw-semibold">Periode (Bulan-Tahun) *</label><input type="month" name="periode" class="form-control" required value="<?= date('Y-m') ?>"></div>
                        <div class="col-md-3"><label class="form-label small fw-semibold">Tgl Gaji Dibayar</label><input type="date" name="tanggal_gaji" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold">Gaji Pokok</label><input type="text" name="gaji_pokok" class="form-control rupiah-input" placeholder="0"></div>
                        <div class="col-md-3"><label class="form-label small fw-semibold">Tunjangan Makan</label><input type="text" name="tunjangan_makan" class="form-control rupiah-input" placeholder="0"></div>
                        <div class="col-md-3"><label class="form-label small fw-semibold">Tunjangan Transport</label><input type="text" name="tunjangan_transport" class="form-control rupiah-input" placeholder="0"></div>
                        <div class="col-md-3"><label class="form-label small fw-semibold">Tunjangan Jabatan</label><input type="text" name="tunjangan_jabatan" class="form-control rupiah-input" placeholder="0"></div>
                        <div class="col-md-3"><label class="form-label small fw-semibold">Bonus / Insentif</label><input type="text" name="bonus" class="form-control rupiah-input" placeholder="0"></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold">Potongan</label><input type="text" name="potongan" class="form-control rupiah-input" placeholder="0"></div>
                        <div class="col-md-8"><label class="form-label small fw-semibold">Keterangan Potongan</label><input type="text" name="keterangan_potongan" class="form-control" placeholder="Kasbon, BPJS, dll."></div>
                        <div class="col-12"><label class="form-label small fw-semibold">Keterangan</label><textarea name="keterangan" class="form-control" rows="2"></textarea></div>
                        <div class="col-12"><small class="text-muted">Jika periode &amp; karyawan sama sudah ada, data otomatis diperbarui (tidak dobel).</small></div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button><button type="submit" name="tambah_gaji" class="btn text-white px-4 rounded-3" style="background-color:var(--primary-blue);">Simpan Slip</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Format input rupiah (1.000.000)
    document.querySelectorAll('.rupiah-input').forEach(function(input) {
        input.addEventListener('input', function() {
            var v = this.value.replace(/[^\d]/g, '');
            if (v === '') { this.value = ''; return; }
            this.value = new Intl.NumberFormat('id-ID').format(parseInt(v, 10));
        });
    });

    var table = document.getElementById('gajiTable');
    if (table) {
        var counter = document.getElementById('filterCounter');
        var rows = Array.from(table.querySelectorAll('tbody tr'));
        var shown = rows.filter(function(r){ return r.style.display !== 'none'; }).length;
        if (counter) counter.textContent = 'Menampilkan ' + (shown || (rows.length ? rows.length : 0)) + ' data';
    }

    // Modal hapus
    var modalHapus = document.getElementById('modalHapus');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function(event) {
            var btn = event.relatedTarget;
            document.getElementById('infoHapus').innerText = 'Slip gaji: ' + btn.getAttribute('data-info');
            document.getElementById('btnConfirmDelete').href = 'gaji.php?action=hapus&id=' + btn.getAttribute('data-id') + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>