<?php
// api/routes/api.php - Route definitions

$routes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'DELETE' => [],
];

// Auth
$routes['POST']['/auth/login'] = ['AuthController', 'login'];
$routes['POST']['/auth/logout'] = ['AuthController', 'logout'];
$routes['POST']['/auth/refresh'] = ['AuthController', 'refresh'];
$routes['GET']['/auth/me'] = ['AuthController', 'me'];
$routes['GET']['/auth/check'] = ['AuthController', 'checkSession'];

// Cluster
$routes['GET']['/clusters'] = ['ClusterController', 'index'];
$routes['GET']['/clusters/options'] = ['ClusterController', 'options'];
$routes['GET']['/clusters/stats'] = ['ClusterController', 'stats'];
$routes['GET']['/clusters/{id:\d+}'] = ['ClusterController', 'show'];
$routes['POST']['/clusters'] = ['ClusterController', 'store'];
$routes['PUT']['/clusters/{id:\d+}'] = ['ClusterController', 'update'];
$routes['DELETE']['/clusters/{id:\d+}'] = ['ClusterController', 'destroy'];

// Tipe Rumah
$routes['GET']['/tipe-rumah'] = ['TipeRumahController', 'index'];
$routes['GET']['/tipe-rumah/by-cluster/{clusterId:\d+}'] = ['TipeRumahController', 'byCluster'];
$routes['GET']['/tipe-rumah/{id:\d+}'] = ['TipeRumahController', 'show'];
$routes['POST']['/tipe-rumah'] = ['TipeRumahController', 'store'];
$routes['PUT']['/tipe-rumah/{id:\d+}'] = ['TipeRumahController', 'update'];
$routes['DELETE']['/tipe-rumah/{id:\d+}'] = ['TipeRumahController', 'destroy'];

// Rumah (Unit)
$routes['GET']['/rumah'] = ['RumahController', 'index'];
$routes['GET']['/rumah/available'] = ['RumahController', 'available'];
$routes['GET']['/rumah/{id:\d+}'] = ['RumahController', 'show'];
$routes['POST']['/rumah'] = ['RumahController', 'store'];
$routes['PUT']['/rumah/{id:\d+}'] = ['RumahController', 'update'];
$routes['PUT']['/rumah/{id:\d+}/status'] = ['RumahController', 'updateStatus'];
$routes['DELETE']['/rumah/{id:\d+}'] = ['RumahController', 'destroy'];

// Konsumen
$routes['GET']['/konsumen'] = ['KonsumenController', 'index'];
$routes['GET']['/konsumen/search'] = ['KonsumenController', 'search'];
$routes['GET']['/konsumen/{id:\d+}'] = ['KonsumenController', 'show'];
$routes['GET']['/konsumen/{id:\d+}/transaksi'] = ['KonsumenController', 'withTransactions'];
$routes['POST']['/konsumen'] = ['KonsumenController', 'store'];
$routes['PUT']['/konsumen/{id:\d+}'] = ['KonsumenController', 'update'];
$routes['DELETE']['/konsumen/{id:\d+}'] = ['KonsumenController', 'destroy'];

// Transaksi
$routes['GET']['/transaksi'] = ['TransaksiController', 'index'];
$routes['GET']['/transaksi/stats'] = ['TransaksiController', 'stats'];
$routes['GET']['/transaksi/{id:\d+}'] = ['TransaksiController', 'show'];
$routes['GET']['/transaksi/konsumen/{id:\d+}'] = ['TransaksiController', 'byKonsumen'];
$routes['POST']['/transaksi'] = ['TransaksiController', 'store'];
$routes['PUT']['/transaksi/{id:\d+}'] = ['TransaksiController', 'update'];
$routes['PUT']['/transaksi/{id:\d+}/status'] = ['TransaksiController', 'updateStatus'];
$routes['DELETE']['/transaksi/{id:\d+}'] = ['TransaksiController', 'destroy'];

// Pembayaran
$routes['GET']['/pembayaran'] = ['PembayaranController', 'index'];
$routes['GET']['/pembayaran/transaksi/{id:\d+}'] = ['PembayaranController', 'byTransaksi'];
$routes['GET']['/pembayaran/transaksi/{id:\d+}/summary'] = ['PembayaranController', 'summary'];
$routes['GET']['/pembayaran/{id:\d+}'] = ['PembayaranController', 'show'];
$routes['POST']['/pembayaran'] = ['PembayaranController', 'store'];
$routes['PUT']['/pembayaran/{id:\d+}'] = ['PembayaranController', 'update'];
$routes['DELETE']['/pembayaran/{id:\d+}'] = ['PembayaranController', 'destroy'];

// Pembatalan
$routes['GET']['/pembatalan'] = ['PembatalanController', 'index'];
$routes['GET']['/pembatalan/{id:\d+}'] = ['PembatalanController', 'show'];
$routes['GET']['/pembatalan/{id:\d+}/print'] = ['PembatalanController', 'printData'];
$routes['POST']['/pembatalan'] = ['PembatalanController', 'store'];
$routes['PUT']['/pembatalan/{id:\d+}'] = ['PembatalanController', 'update'];
$routes['DELETE']['/pembatalan/{id:\d+}'] = ['PembatalanController', 'destroy'];

// Pemberkasan
$routes['GET']['/pemberkasan'] = ['PemberkasanController', 'index'];
$routes['GET']['/pemberkasan/stats'] = ['PemberkasanController', 'stats'];
$routes['GET']['/pemberkasan/{id:\d+}'] = ['PemberkasanController', 'show'];
$routes['PUT']['/pemberkasan/{id:\d+}'] = ['PemberkasanController', 'update'];
$routes['PUT']['/pemberkasan/{id:\d+}/berkas'] = ['PemberkasanController', 'updateBerkas'];

// Laporan / Dashboard
$routes['GET']['/laporan/dashboard'] = ['LaporanController', 'dashboard'];
$routes['GET']['/laporan/penjualan'] = ['LaporanController', 'penjualan'];
$routes['GET']['/laporan/pembayaran'] = ['LaporanController', 'pembayaran'];
$routes['GET']['/laporan/pembangunan'] = ['LaporanController', 'pembangunan'];
$routes['GET']['/laporan/harian'] = ['LaporanController', 'harian'];

return $routes;