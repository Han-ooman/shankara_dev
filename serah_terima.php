<?php

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
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

if (isset($_POST['simpan_bast'])) {
    $id_transaksi = mysqli_real_escape_string($koneksi, $_POST['id_transaksi']);
    $no_bast      = mysqli_real_escape_string($koneksi, $_POST['no_bast']);
    $tgl_serah    = mysqli_real_escape_string($koneksi, $_POST['tgl_serah_terima']);
    $status_r     = mysqli_real_escape_string($koneksi, $_POST['status_retensi']);
    $catatan      = mysqli_real_escape_string($koneksi, $_POST['catatan_komplain']);

    $query = "INSERT INTO data_bast (id_transaksi, no_bast, tgl_serah_terima, status_retensi, catatan_komplain)
              VALUES ('$id_transaksi', '$no_bast', '$tgl_serah', '$status_r', '$catatan')";
    mysqli_query($koneksi, $query);
    header("Location: serah_terima.php");
    exit();
}

// Logika Edit BAST
if (isset($_POST['edit_bast'])) {
    $id_bast      = intval($_POST['id_bast']);
    $id_transaksi = mysqli_real_escape_string($koneksi, $_POST['id_transaksi']);
    $no_bast      = mysqli_real_escape_string($koneksi, $_POST['no_bast']);
    $tgl_serah    = mysqli_real_escape_string($koneksi, $_POST['tgl_serah_terima']);
    $status_r     = mysqli_real_escape_string($koneksi, $_POST['status_retensi']);
    $catatan      = mysqli_real_escape_string($koneksi, $_POST['catatan_komplain']);

    mysqli_query($koneksi, "UPDATE data_bast SET
                            id_transaksi = '$id_transaksi',
                            no_bast = '$no_bast',
                            tgl_serah_terima = '$tgl_serah',
                            status_retensi = '$status_r',
                            catatan_komplain = '$catatan'
                            WHERE id_bast = '$id_bast'");
    header("Location: serah_terima.php");
    exit();
}

// Logika Hapus BAST
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_hapus = intval($_GET['id']);
    mysqli_query($koneksi, "DELETE FROM data_bast WHERE id_bast = '$id_hapus'");
    header("Location: serah_terima.php");
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
    <title>Serah Terima (BAST) - Shankara Dev</title>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Berita Acara Serah Terima (BAST)</h4>
                <p class="text-muted small mb-0">Pencatatan penyerahan kunci unit dan masa retensi garansi.</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalBast">+ BAST Baru</button>
        </div>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="text-muted">
                            <th>No. BAST</th>
                            <th>Konsumen</th>
                            <th>Unit</th>
                            <th>Tgl. Serah Terima</th>
                            <th>Status Retensi</th>
                            <th>Catatan Komplain</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
							$q = mysqli_query($koneksi, "SELECT b.*, 
																k.name_lengkap, 
																r.no_blok 
														 FROM data_bast b
														 LEFT JOIN data_transaksi t ON b.id_transaksi = t.id_transaksi
														 LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
														 LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
														 ORDER BY b.id_bast DESC");

							if ($q && mysqli_num_rows($q) > 0) {
								while ($r = mysqli_fetch_assoc($q)) {
									$badge = 'bg-warning text-dark';
									if ($r['status_retensi'] == 'Selesai Garansi') $badge = 'bg-success';
									elseif ($r['status_retensi'] == 'Ada Komplain') $badge = 'bg-danger';
							?>
							<tr>
								<td class="fw-bold"><?= htmlspecialchars($r['no_bast']) ?></td>
								<td><?= htmlspecialchars($r['name_lengkap'] ?? '-') ?></td>
								<td><span class="badge bg-dark">Blok <?= htmlspecialchars($r['no_blok'] ?? '-') ?></span></td>
								<td><?= date('d/m/Y', strtotime($r['tgl_serah_terima'])) ?></td>
								<td><span class="badge <?= $badge ?>"><?= htmlspecialchars($r['status_retensi']) ?></span></td>
								<td class="small text-muted"><?= htmlspecialchars($r['catatan_komplain'] ?? '-') ?></td>
								<td class="text-center">
									<div class="d-flex justify-content-center align-items-center gap-1">
										<!-- Cetak / Export PDF -->
										<a href="print/cetak_bast.php?id=<?= $r['id_bast'] ?>" target="_blank" class="btn btn-sm btn-outline-success border-0" title="Cetak / Simpan PDF">
											<i class="bi bi-file-earmark-pdf"></i>
										</a>
										<!-- Edit -->
										<button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalEditBast<?= $r['id_bast'] ?>" title="Edit BAST">
											<i class="bi bi-pencil"></i>
										</button>
										<!-- Hapus -->
										<button type="button" class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalHapusBast" data-id="<?= $r['id_bast'] ?>" data-info="<?= htmlspecialchars($r['no_bast']) ?>" title="Hapus BAST">
											<i class="bi bi-trash"></i>
										</button>
									</div>

									<!-- MODAL EDIT BAST -->
									<div class="modal fade text-start" id="modalEditBast<?= $r['id_bast'] ?>" tabindex="-1" aria-hidden="true">
										<div class="modal-dialog modal-dialog-centered">
											<div class="modal-content rounded-4 border-0 shadow">
												<div class="modal-header">
													<h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Berita Acara (BAST)</h5>
													<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
												</div>
<form action="serah_terima.php" method="POST">
				<?= csrf_field() ?>
				<input type="hidden" name="id_bast" value="<?= $r['id_bast'] ?>">
													<div class="modal-body">
														<div class="mb-3">
															<label class="form-label small fw-semibold">Pilih Transaksi / Konsumen</label>
															<select name="id_transaksi" class="form-select" required>
																<option value="">-- Pilih Transaksi --</option>
																<?php
																$qt = mysqli_query($koneksi, "SELECT t.id_transaksi, k.name_lengkap, r.no_blok
																							  FROM data_transaksi t
																							  LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
																							  LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah");
																while ($st = mysqli_fetch_assoc($qt)) {
																	$sel2 = ($st['id_transaksi'] == $r['id_transaksi']) ? 'selected' : '';
																	echo "<option value='".$st['id_transaksi']."' $sel2>Blok ".($st['no_blok'] ?? '-')." - ".($st['name_lengkap'] ?? '-')."</option>";
																}
																?>
															</select>
														</div>
														<div class="row g-2 mb-3">
															<div class="col-md-6">
																<label class="form-label small fw-semibold">Nomor BAST</label>
																<input type="text" name="no_bast" class="form-control" value="<?= htmlspecialchars($r['no_bast']) ?>" required>
															</div>
															<div class="col-md-6">
																<label class="form-label small fw-semibold">Tgl Serah Terima</label>
																<input type="date" name="tgl_serah_terima" class="form-control" value="<?= $r['tgl_serah_terima'] ?>" required>
															</div>
														</div>
														<div class="mb-3">
															<label class="form-label small fw-semibold">Status Retensi / Garansi</label>
															<select name="status_retensi" class="form-select">
																<option value="Masa Garansi" <?= ($r['status_retensi'] == 'Masa Garansi') ? 'selected' : '' ?>>Masa Garansi</option>
																<option value="Selesai Garansi" <?= ($r['status_retensi'] == 'Selesai Garansi') ? 'selected' : '' ?>>Selesai Garansi</option>
																<option value="Ada Komplain" <?= ($r['status_retensi'] == 'Ada Komplain') ? 'selected' : '' ?>>Ada Komplain</option>
															</select>
														</div>
														<div class="mb-3">
															<label class="form-label small fw-semibold">Catatan / Perbaikan</label>
															<textarea name="catatan_komplain" class="form-control" rows="2" placeholder="Catatan komplain fisik jika ada..."><?= htmlspecialchars($r['catatan_komplain']) ?></textarea>
														</div>
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
														<button type="submit" name="edit_bast" class="btn text-white px-4 rounded-3" style="background-color: var(--primary-blue);">Simpan Perubahan</button>
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
								echo "<tr><td colspan='7' class='text-center text-muted py-4'>Belum ada data BAST.</td></tr>";
							}
							?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL KONFIRMASI HAPUS BAST -->
<div class="modal fade" id="modalHapusBast" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash text-danger display-4 mb-3 d-block"></i>
                <h6 class="fw-bold fs-5">Apakah Anda yakin ingin menghapus BAST ini?</h6>
                <p class="text-muted small mb-0" id="infoBastHapus">-</p>
            </div>
            <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnConfirmDeleteBast" class="btn btn-danger px-4 rounded-3">Ya, Hapus Data</a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH BAST -->
<div class="modal fade" id="modalBast" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tambah Berita Acara (BAST)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="serah_terima.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Transaksi / Konsumen</label>
                        <select name="id_transaksi" class="form-select" required>
							<option value="">-- Pilih Transaksi --</option>
							<?php
							$qt = mysqli_query($koneksi, "SELECT t.id_transaksi, 
																 k.name_lengkap, 
																 r.no_blok 
														  FROM data_transaksi t 
														  LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
														  LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah");
							while ($st = mysqli_fetch_assoc($qt)) {
								echo "<option value='".$st['id_transaksi']."'>Blok ".($st['no_blok'] ?? '-')." - ".($st['name_lengkap'] ?? '-')."</option>";
							}
							?>
						</select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor BAST</label>
                            <input type="text" name="no_bast" class="form-control" placeholder="BAST/2026/001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tgl Serah Terima</label>
                            <input type="date" name="tgl_serah_terima" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status Retensi / Garansi</label>
                        <select name="status_retensi" class="form-select">
                            <option value="Masa Garansi">Masa Garansi</option>
                            <option value="Selesai Garansi">Selesai Garansi</option>
                            <option value="Ada Komplain">Ada Komplain</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan / Perbaikan</label>
                        <textarea name="catatan_komplain" class="form-control" rows="2" placeholder="Catatan komplain fisik jika ada..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_bast" class="btn text-white px-4" style="background-color: var(--primary-blue);">Simpan BAST</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalHapus = document.getElementById('modalHapusBast');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var info = button.getAttribute('data-info');
            document.getElementById('infoBastHapus').innerText = 'No. BAST: ' + info;
            document.getElementById('btnConfirmDeleteBast').href = 'serah_terima.php?action=hapus&id=' + id + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
