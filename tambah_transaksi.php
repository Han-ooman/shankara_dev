<?php
include 'includes/koneksi.php';

session_start();

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

$konsumen = sdb_query($koneksi, "SELECT * FROM data_konsumen ORDER BY name_lengkap ASC");
$rumah = sdb_query($koneksi, "SELECT r.*, c.cluster_name 
                                FROM data_rumah r 
                                JOIN data_cluster c ON r.id_cluster = c.id_cluster 
                                ORDER BY c.cluster_name, r.no_blok ASC");

if (isset($_POST['simpan'])) {
    $tgl_booking     = $_POST['tgl_booking'];
    $id_konsumen     = $_POST['id_konsumen'];
    $id_rumah        = $_POST['id_rumah'];
    $skema_bayar     = $_POST['skema_bayar'];
    $status_konsumen = $_POST['status_konsumen'];
    $tanggal_akad    = !empty($_POST['tanggal_akad']) ? "'".$_POST['tanggal_akad']."'" : "NULL";

    $insert = sdb_query($koneksi, "INSERT INTO data_transaksi (tgl_booking, id_konsumen, id_rumah, skema_bayar, status_konsumen, tanggal_akad) 
                                      VALUES ('$tgl_booking', '$id_konsumen', '$id_rumah', '$skema_bayar', '$status_konsumen', $tanggal_akad)");

    if ($insert) {
        $status_unit = ($status_konsumen == 'ACC - AKAD') ? 'terjual' : 'terbooking';
        sdb_query($koneksi, "UPDATE data_rumah SET status_unit = '$status_unit' WHERE id_rumah = '$id_rumah'");
        header("Location: index.php");
    } else {
        echo "<script>alert('Gagal menyimpan transaksi!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">    <title>Input Transaksi Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4" style="max-width: 600px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Tambah Transaksi Booking Baru</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Tanggal Booking</label>
                    <input type="date" name="tgl_booking" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Pilih Konsumen</label>
                    <select name="id_konsumen" class="form-select" required>
                        <option value="">-- Pilih Konsumen --</option>
                        <?php while ($k = sdb_fetch_assoc($konsumen)) { ?>
                            <option value="<?= $k['id_konsumen']; ?>"><?= $k['name_lengkap']; ?> (<?= $k['pekerjaan']; ?>)</option>
                        <?php } ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Pilih Rumah / Unit</label>
                    <select name="id_rumah" class="form-select" required>
                        <option value="">-- Pilih Unit Rumah --</option>
                        <?php while ($r = sdb_fetch_assoc($rumah)) { ?>
                            <option value="<?= $r['id_rumah']; ?>">
                                <?= $r['cluster_name']; ?> - Blok <?= $r['no_blok']; ?> (Tipe <?= $r['tipe_rumah']; ?>) - Status: <?= $r['status_unit']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Skema Pembayaran</label>
                    <select name="skema_bayar" class="form-select" required>
                        <option value="KPR Subsidi">KPR Subsidi</option>
                        <option value="KPR Komersil">KPR Komersil</option>
                        <option value="Cash Bertahap">Cash Bertahap</option>
                        <option value="Cash Keras">Cash Keras</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status Konsumen</label>
                    <select name="status_konsumen" class="form-select" required>
                        <option value="PROSES">PROSES</option>
                        <option value="ACC - AKAD">ACC - AKAD</option>
                        <option value="BATAL">BATAL</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tanggal Akad (Opsional)</label>
                    <input type="date" name="tanggal_akad" class="form-control">
                </div>

                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">Kembali</a>
                    <button type="submit" name="simpan" class="btn btn-success">Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="assets/js/idle.js"></script>
</body>
</html>