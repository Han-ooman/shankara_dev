<?php

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";

include "includes/koneksi.php";
include "includes/cluster_hold.php";
include "includes/pembayaran_filter.php";

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
    $q_kw = sdb_query($koneksi, "SELECT no_kwitansi FROM data_pembayaran WHERE id_pembayaran = $id_pembayaran");
    $no_kw = ($q_kw && sdb_num_rows($q_kw) > 0) ? sdb_fetch_assoc($q_kw)['no_kwitansi'] : '';

    $query_hapus = "DELETE FROM data_pembayaran WHERE id_pembayaran = $id_pembayaran";
    if (sdb_query($koneksi, $query_hapus)) {
        // AUTO-SYNC hapus dari data_keuangan
        if ($no_kw) {
            sdb_query($koneksi, "DELETE FROM data_keuangan WHERE no_ref = '" . sdb_real_escape_string($koneksi, $no_kw) . "'");
        }
        header("Location: pembayaran.php?status=deleted");
        exit();
    } else {
        echo "<script>alert('Gagal menghapus riwayat pembayaran: " . sdb_error($koneksi) . "');</script>";
    }
}

// LOGIKA SIMPAN PEMBAYARAN (satu kwitansi bisa memuat beberapa transaksi/blok)
if (isset($_POST['simpan_pembayaran'])) {
    $sumber      = ($_POST['sumber_pembayaran'] ?? 'Unit Rumah') === 'Lainnya' ? 'Lainnya' : 'Unit Rumah';
    $nama_pembayar = trim($_POST['nama_pembayar'] ?? '');
    $no_kwitansi  = sdb_real_escape_string($koneksi, $_POST['no_kwitansi']);
    $jenis        = sdb_real_escape_string($koneksi, $_POST['jenis_pembayaran']);
    $jumlah       = sdb_real_escape_string($koneksi, $_POST['jumlah_bayar']);
    $tgl_bayar    = sdb_real_escape_string($koneksi, $_POST['tgl_bayar']);
    $metode       = sdb_real_escape_string($koneksi, $_POST['metode_pembayaran']);
    $keterangan   = sdb_real_escape_string($koneksi, $_POST['keterangan']);

    // Kumpulkan semua transaksi terpilih (kwitansi multi-blok)
    $trx_ids = [];
    if ($sumber === 'Unit Rumah' && isset($_POST['id_transaksi']) && is_array($_POST['id_transaksi'])) {
        foreach ($_POST['id_transaksi'] as $tid) {
            $tid = intval($tid);
            if ($tid > 0) $trx_ids[$tid] = $tid;
        }
    }
    $trx_ids = array_values($trx_ids);
    $id_transaksi = $trx_ids[0] ?? 0;

    // Validasi: wajib pilih minimal 1 transaksi bila sumber Unit Rumah
    if ($sumber === 'Unit Rumah' && count($trx_ids) === 0) {
        header("Location: pembayaran.php?status=err_transaksi");
        exit();
    }
    // Validasi: wajib isi nama pembayar bila sumber Lainnya
    if ($sumber === 'Lainnya' && $nama_pembayar === '') {
        header("Location: pembayaran.php?status=err_nama");
        exit();
    }

    // Validasi: semua transaksi dalam 1 kwitansi harus milik konsumen yang sama
    if ($sumber === 'Unit Rumah') {
        $in_trx = implode(',', array_map('intval', $trx_ids));
        $q_kons = sdb_query($koneksi, "SELECT DISTINCT id_konsumen FROM data_transaksi WHERE id_transaksi IN ($in_trx)");
        $jml_konsumen = $q_kons ? sdb_num_rows($q_kons) : 0;
        if ($jml_konsumen > 1) {
            header("Location: pembayaran.php?status=err_konsumen");
            exit();
        }
    }

    // Blokir pembayaran bila ada blok yang berada di cluster di HOLD
    if ($sumber === 'Unit Rumah') {
        foreach ($trx_ids as $tid) {
            $q_rumah_trx = sdb_query($koneksi, "SELECT id_rumah FROM data_transaksi WHERE id_transaksi = $tid LIMIT 1");
            $id_rumah_trx = ($q_rumah_trx && sdb_num_rows($q_rumah_trx) > 0) ? (int)(sdb_fetch_assoc($q_rumah_trx)['id_rumah'] ?? 0) : 0;
            cluster_hold_cek_rumah($koneksi, $id_rumah_trx, 'pembayaran.php');
        }
    }

    $nama_pembayar_sql = $nama_pembayar !== '' ? "'" . sdb_real_escape_string($koneksi, $nama_pembayar) . "'" : 'NULL';
    $id_trx_sql        = $id_transaksi > 0 ? "'$id_transaksi'" : 'NULL';

    // Simpan header kwitansi (id_transaksi = transaksi pertama sebagai referensi utama)
    $query = "INSERT INTO data_pembayaran (id_transaksi, nama_pembayar, sumber_pembayaran, no_kwitansi, jenis_pembayaran, jumlah_bayar, tgl_bayar, metode_pembayaran, keterangan)
              VALUES ($id_trx_sql, $nama_pembayar_sql, '$sumber', '$no_kwitansi', '$jenis', '$jumlah', '$tgl_bayar', '$metode', '$keterangan')";
    sdb_query($koneksi, $query);
    $id_pembayaran_baru = sdb_insert_id($koneksi);

    // Simpan detail blok (kwitansi multi-blok)
    if ($sumber === 'Unit Rumah' && $id_pembayaran_baru > 0) {
        foreach ($trx_ids as $tid) {
            sdb_query($koneksi, "INSERT INTO data_pembayaran_detail (id_pembayaran, id_transaksi) VALUES ($id_pembayaran_baru, $tid)");
        }
    }

    // AUTO-SYNC ke data_keuangan
    $map_kategori = [
        'Booking Fee'       => 'Booking Fee',
        'Uang Muka (DP)'    => 'DP Rumah',
        'Cicilan'           => 'Angsuran',
        'Pelunasan'         => 'Penjualan Unit',
    ];
    $kategori_keu = $map_kategori[$jenis] ?? $jenis;
    $metode_keu   = ($metode === 'Cash') ? 'Tunai' : 'Transfer';

    // Kwitansi di luar unit = uang KELUAR dari PT ke konsumen -> Pengeluaran
    $jenis_keuangan = ($sumber === 'Lainnya') ? 'Pengeluaran' : 'Pemasukan';

    // Cluster hanya berlaku untuk kwitansi berbasis unit rumah
    $cluster_id = 'NULL';
    if ($id_transaksi > 0) {
        $q_cluster = sdb_query($koneksi, "SELECT r.id_cluster FROM data_transaksi t LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah WHERE t.id_transaksi = '$id_transaksi'");
        if ($q_cluster && sdb_num_rows($q_cluster) > 0) {
            $cid = sdb_fetch_assoc($q_cluster)['id_cluster'];
            if (!empty($cid)) $cluster_id = "'" . intval($cid) . "'";
        }
    }

    // Ambil no blok untuk deskripsi kas (multi-blok)
    $daftar_blok_keu = '';
    if ($sumber === 'Unit Rumah') {
        $in_trx = implode(',', array_map('intval', $trx_ids));
        $q_blok = sdb_query($koneksi, "SELECT GROUP_CONCAT(r.no_blok ORDER BY r.no_blok SEPARATOR ', ') AS blok FROM data_transaksi t JOIN data_rumah r ON t.id_rumah = r.id_rumah WHERE t.id_transaksi IN ($in_trx)");
        if ($q_blok && $row = sdb_fetch_assoc($q_blok)) {
            $daftar_blok_keu = trim($row['blok'] ?? '');
        }
    }

    // Deskripsi: sertakan penerima untuk kwitansi pengeluaran (PT -> Konsumen)
    if ($sumber === 'Lainnya') {
        $desk = sdb_real_escape_string($koneksi, trim("$jenis - $nama_pembayar") . ($keterangan ? " ($keterangan)" : ''));
    } else {
        $base_desk = $daftar_blok_keu ? "$jenis (Blok $daftar_blok_keu)" : $jenis;
        $desk = sdb_real_escape_string($koneksi, $keterangan ? "$base_desk - $keterangan" : $base_desk);
    }

    sdb_query($koneksi, "INSERT INTO data_keuangan (tanggal, jenis, kategori, cluster_id, deskripsi, nominal, metode_bayar, no_ref)
                            VALUES ('$tgl_bayar', '$jenis_keuangan', '$kategori_keu', $cluster_id, '$desk', $jumlah, '$metode_keu', '$no_kwitansi')");

    header("Location: pembayaran.php");
    exit();
}

// AMBIL FILTER & DATA RIWAYAT KWITANSI (logic terpusat di includes/pembayaran_filter.php)
$filters        = pembayaran_get_filters();
$ada_filter     = pembayaran_filter_active($filters);
$label_aktif    = pembayaran_filter_labels($filters);
$daftar_jenis   = pembayaran_daftar_jenis($koneksi);

$rows_pembayaran = pembayaran_list($koneksi, $filters);
$jumlah_hasil    = count($rows_pembayaran);
$jumlah_total    = pembayaran_jumlah_total($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Kwitansi Pembayaran & Pengeluaran - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .search-input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 0.85rem;
            z-index: 5;
            pointer-events: none;
        }
    </style>
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
                <h4 class="fw-bold mb-1">Riwayat Kwitansi</h4>
                <p class="text-muted small mb-0">Catat transaksi masuk (Booking Fee, DP, Pelunasan) & keluar (Refund, Komisi, Bantuan).</p>
            </div>
            <button class="btn text-white px-4 py-2 rounded-3" style="background-color: var(--primary-blue);" data-bs-toggle="modal" data-bs-target="#modalBayar">+ Tambah Pembayaran</button>
        </div>

        <?php if (isset($_GET['status']) && in_array($_GET['status'], ['err_transaksi', 'err_nama', 'err_konsumen'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
            <?php
            $pesan_status = [
                'err_transaksi' => 'Pilih minimal 1 transaksi / blok terlebih dahulu.',
                'err_nama'      => 'Nama penerima wajib diisi untuk kwitansi di luar unit.',
                'err_konsumen'  => 'Semua blok dalam satu kwitansi harus milik konsumen yang sama.',
            ];
            echo htmlspecialchars($pesan_status[$_GET['status']]);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- BAR FILTER RIWAYAT KWITANSI -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #ffffff;">
            <div class="card-body p-3">
                <!-- BARIS 1: pencarian + dropdown (12/12 di layar kecil, 12 kolom di lg) -->
                <form method="GET" action="pembayaran.php" class="row g-2 g-md-3">

                    <!-- Keyword -->
                    <div class="col-12 col-lg-4">
                        <div class="position-relative">
                            <i class="bi bi-search search-input-icon"></i>
                            <input type="text" name="keyword" class="form-control rounded-pill search-input-pill"
                                   placeholder="Cari No. Kwitansi, Nama, Blok, Catatan..." value="<?= htmlspecialchars($filters['keyword']) ?>">
                        </div>
                    </div>

                    <!-- Arus Dana -->
                    <div class="col-6 col-lg-2">
                        <select name="arus" class="form-select rounded-pill bg-light border-0 px-3">
                            <option value="">-- Semua Arus --</option>
                            <option value="pemasukan" <?= ($filters['arus'] == 'pemasukan') ? 'selected' : '' ?>>Pemasukan</option>
                            <option value="pengeluaran" <?= ($filters['arus'] == 'pengeluaran') ? 'selected' : '' ?>>Pengeluaran</option>
                        </select>
                    </div>

                    <!-- Jenis Pembayaran -->
                    <div class="col-6 col-lg-2">
                        <select name="jenis" class="form-select rounded-pill bg-light border-0 px-3">
                            <option value="">-- Semua Jenis --</option>
                            <?php foreach ($daftar_jenis as $jn): ?>
                                <option value="<?= htmlspecialchars($jn) ?>" <?= ($filters['jenis'] == $jn) ? 'selected' : '' ?>><?= htmlspecialchars($jn) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Metode -->
                    <div class="col-6 col-lg-2">
                        <select name="metode" class="form-select rounded-pill bg-light border-0 px-3">
                            <option value="">-- Semua Metode --</option>
                            <option value="Transfer" <?= ($filters['metode'] == 'Transfer') ? 'selected' : '' ?>>Transfer Bank</option>
                            <option value="Cash" <?= ($filters['metode'] == 'Cash') ? 'selected' : '' ?>>Cash / Tunai</option>
                        </select>
                    </div>

                    <!-- Urutkan -->
                    <div class="col-6 col-lg-2">
                        <select name="sort" class="form-select rounded-pill bg-light border-0 px-3">
                            <option value="" <?= ($filters['sort'] == '') ? 'selected' : '' ?>>Urut: Terbaru</option>
                            <option value="kw_asc" <?= ($filters['sort'] == 'kw_asc') ? 'selected' : '' ?>>Urut: Terlama</option>
                            <option value="jumlah_desc" <?= ($filters['sort'] == 'jumlah_desc') ? 'selected' : '' ?>>Jumlah Terbesar</option>
                            <option value="jumlah_asc" <?= ($filters['sort'] == 'jumlah_asc') ? 'selected' : '' ?>>Jumlah Terkecil</option>
                        </select>
                    </div>

                    <!-- BARIS 2: rentang tanggal + tombol -->
                    <div class="col-6 col-lg-3">
                        <div class="input-group h-100">
                            <span class="input-group-text bg-light border-0 rounded-start-pill"><i class="bi bi-calendar3"></i></span>
                            <input type="date" name="tgl_dari" class="form-control bg-light border-0" aria-label="Tanggal mulai" value="<?= htmlspecialchars($filters['tgl_dari']) ?>">
                        </div>
                    </div>

                    <div class="col-6 col-lg-3">
                        <div class="input-group h-100">
                            <span class="input-group-text bg-light border-0 rounded-start-pill"><i class="bi bi-calendar3-week"></i></span>
                            <input type="date" name="tgl_sampai" class="form-control bg-light border-0" aria-label="Tanggal akhir" value="<?= htmlspecialchars($filters['tgl_sampai']) ?>">
                        </div>
                    </div>

                    <!-- Tombol Filter & Reset -->
                    <div class="col-12 col-lg-6 d-flex gap-2">
                        <button type="submit" class="btn text-white rounded-pill flex-grow-1" style="background-color: var(--primary-blue);">
                            <i class="bi bi-funnel-fill me-1"></i> Filter
                        </button>
                        <?php if ($ada_filter): ?>
                            <a href="pembayaran.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Reset Filter">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- RINGKASAN & FILTER AKTIF -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="fw-semibold text-muted small"><i class="bi bi-receipt me-1"></i>Menampilkan <b class="text-dark"><?= $jumlah_hasil ?></b> dari <?= $jumlah_total ?> kwitansi</span>
            <?php foreach ($label_aktif as $lb): ?>
                <span class="badge rounded-pill" style="background-color: var(--light-blue); color: var(--primary-blue);"><?= htmlspecialchars($lb) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
					<thead>
						<tr class="text-muted">
							<th>No. Kwitansi</th>
							<th>Penerima / Konsumen</th>
							<th>Arus</th>
							<th>Jenis Bayar</th>
							<th>Jumlah (Rp)</th>
							<th>Tgl. Bayar</th>
							<th>Metode</th>
							<th class="text-center">Aksi</th>
						</tr>
					</thead>
					<tbody>
<?php
						// Data sudah terfilter via includes/pembayaran_filter.php ($rows_pembayaran)
						foreach ($rows_pembayaran as $r) {
						?>
						<tr>
							<td class="fw-bold text-success"><i class="bi bi-receipt me-1"></i><?= htmlspecialchars($r['no_kwitansi']) ?></td>
							<td><?= htmlspecialchars($r['nama_pembayar_tampil']) ?>
								<?php if (!empty($r['daftar_blok'])): ?>
									<span class="badge bg-secondary ms-1">Blok <?= htmlspecialchars($r['daftar_blok']) ?></span>
								<?php else: ?>
									<span class="badge bg-warning text-dark ms-1">Non-Unit</span>
								<?php endif; ?>
							</td>
							<td>
								<?php $isKeluar = ($r['sumber_pembayaran'] === 'Lainnya'); ?>
								<span class="badge <?= $isKeluar ? 'bg-danger' : 'bg-success' ?>"><?= $isKeluar ? 'Pengeluaran' : 'Pemasukan' ?></span>
							</td>
							<td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['jenis_pembayaran']) ?></span></td>
							<td class="fw-bold <?= $isKeluar ? 'text-danger' : '' ?>"><?= ($isKeluar ? '- ' : '') ?>Rp <?= number_format($r['jumlah_bayar'], 0, ',', '.') ?></td>
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
											data-konsumen="<?= htmlspecialchars($r['nama_pembayar_tampil']); ?>">
										<i class="bi bi-trash"></i>
									</button>
								</div>
							</td>
						</tr>
						<?php 
						}
						if ($jumlah_hasil === 0) {
							$pesan_kosong = $ada_filter ? 'Tidak ada kwitansi yang cocok dengan filter. <a href="pembayaran.php">Reset filter</a>.' : 'Belum ada transaksi pembayaran.';
							echo '<tr><td colspan="8" class="text-center text-muted py-4">' . $pesan_kosong . '</td></tr>';
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
                        <label class="form-label small fw-semibold">Sumber Pembayaran</label>
                        <div class="btn-group w-100" role="group" aria-label="Sumber Pembayaran">
                            <input type="radio" class="btn-check" name="sumber_pembayaran" id="sumberUnit" value="Unit Rumah" checked>
                            <label class="btn btn-outline-primary flex-fill" for="sumberUnit"><i class="bi bi-house-door me-1"></i> Transaksi Unit Rumah</label>
                            <input type="radio" class="btn-check" name="sumber_pembayaran" id="sumberLainnya" value="Lainnya">
                            <label class="btn btn-outline-primary flex-fill" for="sumberLainnya"><i class="bi bi-arrow-left-right me-1"></i> Di Luar Unit (Pengeluaran)</label>
                        </div>
                    </div>
                    <div class="mb-3" id="wrapPilihTransaksi">
                        <label class="form-label small fw-semibold">Pilih Transaksi / Blok <span class="text-muted fw-normal">(boleh lebih dari 1)</span></label>
                        <input type="text" id="filterTrx" class="form-control form-control-sm mb-2" placeholder="Cari blok / nama konsumen..." autocomplete="off">
                        <div class="border rounded p-2" style="max-height:220px; overflow-y:auto;" id="daftarTrx">
							<?php
							$qt = sdb_query($koneksi, "SELECT t.id_transaksi, 
																 k.name_lengkap, 
																 r.no_blok,
																 c.cluster_name 
														  FROM data_transaksi t 
														  LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen
														  LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah
														  LEFT JOIN data_cluster c ON r.id_cluster = c.id_cluster
														  ORDER BY c.cluster_name, r.no_blok");
							while ($st = sdb_fetch_assoc($qt)) {
								echo "<div class='form-check trx-item'>"
								   . "<input class='form-check-input trx-chk' type='checkbox' name='id_transaksi[]' value='".$st['id_transaksi']."' id='trx_".$st['id_transaksi']."'>"
								   . "<label class='form-check-label w-100' for='trx_".$st['id_transaksi']."'>"
								   . "<span class='trx-label'>Blok ".($st['no_blok'] ?? '-')." - ".($st['name_lengkap'] ?? '-')."</span>"
								   . " <small class='text-muted trx-cluster'>(".($st['cluster_name'] ?? '-').")</small>"
								   . "</label></div>";
							}
							?>
						</div>
                        <div class="form-text" id="infoTrx">Belum ada transaksi dipilih.</div>
                    </div>
                    <div class="mb-3" id="wrapNamaPembayar" style="display:none;">
                        <label class="form-label small fw-semibold">Nama Penerima</label>
                        <input type="text" name="nama_pembayar" id="nama_pembayar" class="form-control" list="daftarKonsumen" placeholder="Pilih / ketik nama penerima..." autocomplete="off">
                        <datalist id="daftarKonsumen">
                            <?php
                            $qk = sdb_query($koneksi, "SELECT name_lengkap FROM data_konsumen WHERE name_lengkap IS NOT NULL AND name_lengkap <> '' ORDER BY name_lengkap");
                            while ($sk = sdb_fetch_assoc($qk)) {
                                echo '<option value="' . htmlspecialchars($sk['name_lengkap']) . '">';
                            }
                            ?>
                        </datalist>
                        <div class="form-text">Bisa pilih konsumen terdaftar atau ketik nama bebas.</div>
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
                            <select name="jenis_pembayaran" id="jenis_pembayaran" class="form-select">
                                <optgroup label="Unit Rumah" id="grpJenisUnit">
                                    <option value="Booking Fee">Booking Fee</option>
                                    <option value="Uang Muka (DP)">Uang Muka (DP)</option>
                                    <option value="Cicilan">Cicilan</option>
                                    <option value="Pelunasan">Pelunasan</option>
                                </optgroup>
                                <optgroup label="Di Luar Unit" id="grpJenisLainnya" disabled>
                                    <option value="Refund">Refund</option>
                                    <option value="Komisi">Komisi</option>
                                    <option value="Bantuan">Bantuan</option>
                                    <option value="Koreksi">Koreksi</option>
                                    <option value="Lainnya">Lainnya</option>
                                </optgroup>
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
    const sumberUnit    = document.getElementById('sumberUnit');
    const sumberLainnya = document.getElementById('sumberLainnya');
    const wrapTrx       = document.getElementById('wrapPilihTransaksi');
    const wrapNama      = document.getElementById('wrapNamaPembayar');
    const inputNama     = document.getElementById('nama_pembayar');
    const selectJenis   = document.getElementById('jenis_pembayaran');
    const grpUnit       = document.getElementById('grpJenisUnit');
    const grpLainnya    = document.getElementById('grpJenisLainnya');
    const daftarTrx     = document.getElementById('daftarTrx');
    const filterTrx     = document.getElementById('filterTrx');
    const infoTrx       = document.getElementById('infoTrx');

    const HakJenisNonUnit = ['Refund', 'Komisi', 'Bantuan', 'Koreksi', 'Lainnya'];
    const formBayar = document.querySelector('#modalBayar form');

    // Validasi submit: wajib pilih minimal 1 transaksi utk sumber Unit Rumah
    if (formBayar) {
        formBayar.addEventListener('submit', function (e) {
            if (sumberUnit.checked && daftarTrx.querySelectorAll('.trx-chk:checked').length === 0) {
                e.preventDefault();
                alert('Pilih minimal 1 transaksi/blok terlebih dahulu.');
            }
        });
    }

    function hitungPilih() {
        const n = daftarTrx.querySelectorAll('.trx-chk:checked').length;
        infoTrx.textContent = n === 0 ? 'Belum ada transaksi dipilih.' : n + ' blok terpilih. Satu kwitansi bisa memuat 2+ blok.';
        // Jika multi-blok terpilih, sesuaikan opsi jenis agar tetap masuk akal
        if (n >= 2) {
            const opt = selectJenis.options[selectJenis.selectedIndex];
            if (opt && opt.parentNode && opt.parentNode.disabled) {
                selectJenis.selectedIndex = 0;
            }
        }
    }

    function terapkanSumber() {
        const unit = sumberUnit.checked;
        wrapTrx.style.display  = unit ? '' : 'none';
        wrapNama.style.display = unit ? 'none' : '';
        daftarTrx.querySelectorAll('.trx-chk').forEach(function (c) {
            c.disabled = !unit;
            if (!unit) c.checked = false;
        });
        inputNama.disabled = unit;
        inputNama.required = !unit;

        grpUnit.disabled    = !unit;
        grpLainnya.disabled = unit;

        // Pastikan pilihan jenis selalu berada di optgroup yang aktif
        const opt = selectJenis.options[selectJenis.selectedIndex];
        if (opt && opt.parentNode && opt.parentNode.disabled) {
            selectJenis.selectedIndex = unit ? 0 : selectJenis.options.length - 1;
        }
        hitungPilih();
    }

    sumberUnit.addEventListener('change', terapkanSumber);
    sumberLainnya.addEventListener('change', terapkanSumber);
    terapkanSumber();

    daftarTrx.addEventListener('change', hitungPilih);

    if (filterTrx) {
        filterTrx.addEventListener('input', function () {
            const kata = filterTrx.value.toLowerCase().trim();
            daftarTrx.querySelectorAll('.trx-item').forEach(function (item) {
                const label = (item.querySelector('.trx-label')?.textContent || '').toLowerCase();
                const cluster = (item.querySelector('.trx-cluster')?.textContent || '').toLowerCase();
                item.style.display = (!kata || label.includes(kata) || cluster.includes(kata)) ? '' : 'none';
            });
        });
    }

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
<?php include "includes/modal_hold.php"; ?>
    <script src="assets/js/idle.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
