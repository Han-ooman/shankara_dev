<?php
include __DIR__ . "/koneksi.php";

session_start();

// Proteksi CSRF global untuk semua aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
}

if (isset($_POST['simpan'])) {
    $tgl_booking    = $_POST['tgl_booking'];
    $id_konsumen    = $_POST['id_konsumen'];
    $id_rumah       = $_POST['id_rumah'];
    $skema_bayar    = $_POST['skema_bayar'];
    $status_konsumen = $_POST['status_konsumen'];
    
    // Penanganan Tanggal Akad (Jika kosong diisi NULL)
    $tanggal_akad   = !empty($_POST['tanggal_akad']) ? "'".$_POST['tanggal_akad']."'" : "NULL";

    // Query Insert Transaksi
    $insert = mysqli_query($koneksi, "INSERT INTO data_transaksi (tgl_booking, id_konsumen, id_rumah, skema_bayar, status_konsumen, tanggal_akad) 
                                     VALUES ('$tgl_booking', '$id_konsumen', '$id_rumah', '$skema_bayar', '$status_konsumen', $tanggal_akad)");

    if ($insert) {
        // Logika update status unit rumah otomatis
        $status_unit = ($status_konsumen == 'ACC - AKAD') ? 'TERJUAL' : 'TERBOOKING';
        mysqli_query($koneksi, "UPDATE data_rumah SET status_unit = '$status_unit' WHERE id_rumah = '$id_rumah'");

        // Redirect kembali ke dashboard utama
        header("Location: ../index.php");
        exit();
    } else {
        echo "<script>alert('Gagal menyimpan transaksi!'); window.location.href='../index.php';</script>";
    }
}
?>