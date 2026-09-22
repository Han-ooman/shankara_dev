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

// LOGIKA HAPUS PEMBAYARAN / KWITANSI
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }
    $id_pembayaran = intval($_GET['id']);

    // Ambil no_kwitansi sebelum hapus
    $q_kw = mysqli_query($koneksi, "SELECT no_kwitansi FROM data_pembayaran WHERE id_pembayaran = $id_pembayaran");
    $no_kw = ($q_kw && mysqli_num_rows($q_kw) > 0) ? mysqli_fetch_assoc($q_kw)['no_kwitansi'] : '';

    $query_hapus = "DELETE FROM data_pembayaran WHERE id_pembayaran = $id_pembayaran";
    if (mysqli_query($koneksi, $query_hapus)) {
        // AUTO-SYNC hapus dari data_keuangan
        if ($no_kw) {
            mysqli_query($koneksi, "DELETE FROM data_keuangan WHERE no_ref = '" . mysqli_real_escape_string($koneksi, $no_kw) . "'");
        }
        header("Location: pembayaran.php?status=deleted");
        exit();
    } else {
        echo "<script>alert('Gagal menghapus riwayat pembayaran: " . mysqli_error($koneksi) . "');</script>";
    }
}

// LOGIKA SIMPAN PEMBAYARAN
if (isset($_POST['simpan_pembayaran'])) {
    $id_transaksi = mysqli_real_escape_string($koneksi, $_POST['id_transaksi']);
    $no_kwitansi  = mysqli_real_escape_string($koneksi, $_POST['no_kwitansi']);
    $jenis        = mysqli_real_escape_string($koneksi, $_POST['jenis_pembayaran']);
    $jumlah       = mysqli_real_escape_string($koneksi, $_POST['jumlah_bayar']);
    $tgl_bayar    = mysqli_real_escape_string($koneksi, $_POST['tgl_bayar']);
    $metode       = mysqli_real_escape_string($koneksi, $_POST['metode_pembayaran']);
    $keterangan   = mysqli_real_escape_string($koneksi, $_POST['keterangan']);

    $query = "INSERT INTO data_pembayaran (id_transaksi, no_kwitansi, jenis_pembayaran, jumlah_bayar, tgl_bayar, metode_pembayaran, keterangan)
              VALUES ('$id_transaksi', '$no_kwitansi', '$jenis', '$jumlah', '$tgl_bayar', '$metode', '$keterangan')";
    mysqli_query($koneksi, $query);

    // AUTO-SYNC â†’ data_keuangan
    $id_pembayaran = mysqli_insert_id($koneksi);
    $map_kategori = [
        'Booking Fee'       => 'Booking Fee',
        'Uang Muka (DP)'    => 'DP Rumah',
        'Cicilan'           => 'Angsuran',
        'Pelunasan'         => 'Penjualan Unit',
    ];
    $kategori_keu = $map_kategori[$jenis] ?? $jenis;
    $metode_keu   = ($metode === 'Cash') ? 'Tunai' : 'Transfer';
    $q_cluster = mysqli_query($koneksi, "SELECT r.id_cluster FROM data_transaksi t LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah WHERE t.id_transaksi = '$id_transaksi'");
    $cluster_id = ($q_cluster && mysqli_num_rows($q_cluster) > 0) ? mysqli_fetch_assoc($q_cluster)['id_cluster'] : 'NULL';
    $desk = mysqli_real_escape_string($koneksi, $keterangan ? "$jenis - $keterangan" : $jenis);

    mysqli_query($koneksi, "INSERT INTO data_keuangan (tanggal, jenis, kategori, cluster_id, deskripsi, nominal, metode_bayar, no_ref)
                            VALUES ('$tgl_bayar', 'Pemasukan', '$kategori_keu', $cluster_id, '$desk', $jumlah, '$metode_keu', '$no_kwitansi')");

    header("Location: pembayaran.php");
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
    <title>Tagihan & Kwitansi - Shankara Dev</title>
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
                <h4 class="fw-bold mb-1">Riwayat Pembayaran & Kwitansi</h4>
                <p class="text-muted small mb-0">Catat transaksi masuk (Booking Fee, DP, dan Pelunasan).</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalBayar">+ Tambah Pembayaran</button>
        </div>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
					<thead>
						<tr class="text-muted">
							<th>No. Kwitansi</th>
							<th>Konsumen</th>
							<th>Jenis Bayar</th>
							<th>Jumlah (Rp)</th>
							<th>Tgl. Bayar</th>
							<th>Metode</th>
							<th class="text-center">Aksi</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$q = mysqli_query($koneksi, "SELECT p.*, 
															k.name_lengkap, 
															r.no_blok 
													 FROM data_pembayaran p
													 LEFT JOIN data_transaksi t ON p.id_transaksi = t.id_transaksi
													 LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
													 LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
													 ORDER BY p.id_pembayaran DESC");

						if ($q && mysqli_num_rows($q) > 0) {
							while ($r = mysqli_fetch_assoc($q)) {
						?>
						<tr>
							<td class="fw-bold text-success"><i class="bi bi-receipt me-1"></i><?= htmlspecialchars($r['no_kwitansi']) ?></td>
							<td><?= htmlspecialchars($r['name_lengkap'] ?? '-') ?> <span class="badge bg-secondary ms-1">Blok <?= htmlspecialchars($r['no_blok'] ?? '-') ?></span></td>
							<td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['jenis_pembayaran']) ?></span></td>
							<td class="fw-bold">Rp <?= number_format($r['jumlah_bayar'], 0, ',', '.') ?></td>
							<td><?= date('d/m/Y', strtotime($r['tgl_bayar'])) ?></td>
							<td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['metode_pembayaran']) ?></span></td>
							<td class="text-center">
								<div class="d-flex gap-1 justify-content-center">
									<!-- TOMBOL CETAK PDF -->
									<a href="print/cetak_kwitansi.php?id=<?= $r['id_pembayaran'] ?>" target="_blank" class="btn btn-sm btn-outline-danger rounded-3">
										<i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak PDF
									</a>
									<!-- TOMBOL HAPUS -->
									<button type="button" 
											class="btn btn-sm btn-outline-secondary rounded-3" 
											data-bs-toggle="modal" 
											data-bs-target="#modalHapusPembayaran"
											data-id="<?= $r['id_pembayaran']; ?>"
											data-kwitansi="<?= htmlspecialchars($r['no_kwitansi']); ?>"
											data-konsumen="<?= htmlspecialchars($r['name_lengkap'] ?? 'Konsumen'); ?>">
										<i class="bi bi-trash"></i>
									</button>
								</div>
							</td>
						</tr>
						<?php 
							}
						} else {
							echo "<tr><td colspan='7' class='text-center text-muted py-4'>Belum ada transaksi pembayaran.</td></tr>";
						}
						?>
					</tbody>
				</table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL PEMBAYARAN -->
<div class="modal fade" id="modalBayar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Input Pembayaran / Kwitansi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="pembayaran.php" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Transaksi</label>
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
                            <label class="form-label small fw-semibold">No. Kwitansi</label>
                            <input type="text" name="no_kwitansi" class="form-control" placeholder="KW/2026/001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tgl Pembayaran</label>
                            <input type="date" name="tgl_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Jenis Pembayaran</label>
                            <select name="jenis_pembayaran" class="form-select">
                                <option value="Booking Fee">Booking Fee</option>
                                <option value="Uang Muka (DP)">Uang Muka (DP)</option>
                                <option value="Cicilan">Cicilan</option>
                                <option value="Pelunasan">Pelunasan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Metode</label>
                            <select name="metode_pembayaran" class="form-select">
                                <option value="Transfer">Transfer Bank</option>
                                <option value="Cash">Cash / Tunai</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Jumlah Bayar (Rp)</label>
                        <input type="number" name="jumlah_bayar" class="form-control" placeholder="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Catatan tambahan...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_pembayaran" class="btn text-white px-4" style="background-color: var(--primary-blue);">Simpan Kwitansi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS PEMBAYARAN -->
<div class="modal fade" id="modalHapusPembayaran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Hapus Riwayat Pembayaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash text-danger display-4 mb-3 d-block"></i>
                <h6 class="fw-bold fs-5">Apakah Anda yakin ingin menghapus data pembayaran ini?</h6>
                <p class="text-muted small mb-0" id="infoKwitansiHapus">-</p>
            </div>
            <div class="modal-footer justify-content-center bg-light border-0 rounded-bottom-4">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Tidak, Batal</button>
                <a href="#" id="btnConfirmDeletePembayaran" class="btn btn-danger px-4 rounded-3">Ya, Hapus Data</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalHapus = document.getElementById('modalHapusPembayaran');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const kwitansi = button.getAttribute('data-kwitansi');
            const konsumen = button.getAttribute('data-konsumen');

            document.getElementById('infoKwitansiHapus').innerText = 'No. Kwitansi: ' + kwitansi + ' (' + konsumen + ')';
            document.getElementById('btnConfirmDeletePembayaran').href = 'pembayaran.php?action=hapus&id=' + id + '&token=' + encodeURIComponent(document.getElementById('csrf_global').value);
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
