<?php
// ============================================================
// SINKRONISASI DATA - CLI
//   php sync.php status              ringkasan data di kedua sisi
//   php sync.php push                OFFLINE (MySQL lokal) -> ONLINE (Supabase)
//   php sync.php pull                ONLINE (Supabase)      -> OFFLINE (MySQL)
//   php sync.php both                pull lalu push (dua arah)
//   php sync.php fix                 perbaiki auto increment MySQL lokal
//
// Opsi:
//   --tables=a,b,c        hanya tabel tersebut (pisah koma)
//   --policy=overwrite    timpa baris tujuan (default)
//   --policy=skip         baris yang sudah ada tidak disentuh
//   --policy=newest       data dengan waktu paling baru menang
//   --delete-missing      hapus baris tujuan yang tidak ada di sumber
//   --dry-run             hanya menghitung, tidak menulis
//   --all                 ikut sinkronkan tabel opsional (users, transaksi)
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("File ini hanya bisa dijalankan melalui command line.\n");
}

chdir(__DIR__);
require_once __DIR__ . '/includes/db_adapter.php';
require_once __DIR__ . '/includes/sync_engine.php';

date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------------------
// Parse argumen
// ------------------------------------------------------------------
$perintah = strtolower(trim((string)($argv[1] ?? 'status')));
$opsi = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z0-9_-]+)(?:=(.*))?$/i', (string)$arg, $m)) {
        $opsi[strtolower($m[1])] = isset($m[2]) && $m[2] !== '' ? $m[2] : true;
    }
}

$policy      = (string)($opsi['policy'] ?? SyncEngine::POLICY_OVERWRITE);
$hapus       = isset($opsi['delete-missing']) || isset($opsi['hapus']);
$dry         = isset($opsi['dry-run']) || isset($opsi['dry']);
$semua       = isset($opsi['all']);
$filterTabel = [];
if (!empty($opsi['tables']) && is_string($opsi['tables'])) {
    $filterTabel = explode(',', $opsi['tables']);
}

// ------------------------------------------------------------------
// Koneksi
// ------------------------------------------------------------------
function sync_berhenti(string $pesan): void {
    fwrite(STDERR, "ERROR: $pesan\n");
    exit(1);
}

$offline = sdb_connect_mysql();
$online  = sdb_connect_pgsql();

if (!$offline->ping()) sync_berhenti('Koneksi MySQL lokal (offline) gagal: ' . $offline->connect_error);
if (!$online->ping())  sync_berhenti('Koneksi Supabase (online) gagal: ' . $online->connect_error);

$label = [
    'offline' => 'OFFLINE (MySQL lokal)',
    'online'  => 'ONLINE  (Supabase)',
];

echo str_repeat('=', 78) . PHP_EOL;
echo " SINKRONISASI DATA  |  OFFLINE (MySQL lokal)  <-->  ONLINE (Supabase)" . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;
echo " Perintah   : $perintah" . PHP_EOL;
echo " Waktu      : " . date('Y-m-d H:i:s') . PHP_EOL;

// ------------------------------------------------------------------
// Perintah FIX : auto increment MySQL lokal
// ------------------------------------------------------------------
if ($perintah === 'fix') {
    $hasil = sync_perbaiki_auto_increment($offline);
    if ($hasil) {
        echo " Auto increment ditambahkan pada:" . PHP_EOL;
        foreach ($hasil as $h) echo "   - $h" . PHP_EOL;
    } else {
        echo " Semua primary key MySQL lokal sudah AUTO_INCREMENT." . PHP_EOL;
    }
    exit(0);
}

// ------------------------------------------------------------------
// Daftar tabel
// ------------------------------------------------------------------
$tabel = sync_tabel_aktif($semua, $filterTabel);
if (!$tabel) {
    echo " Tidak ada tabel yang cocok." . PHP_EOL;
    exit(1);
}

// ------------------------------------------------------------------
// STATUS
// ------------------------------------------------------------------
if ($perintah === 'status') {
    echo " Mode DB    : " . strtoupper(DB_MODE) . PHP_EOL . PHP_EOL;

    $status = sync_status_tabel($offline, $online, $tabel);
    printf("%-24s %10s %10s %8s  %-20s %-20s\n", 'TABEL', 'OFFLINE', 'ONLINE', 'SELISIH', 'PUSH TERAKHIR', 'PULL TERAKHIR');
    echo str_repeat('-', 118) . PHP_EOL;
    $totalOff = 0; $totalOn = 0;
    foreach ($status as $s) {
        $totalOff += (int)$s['offline'];
        $totalOn  += (int)$s['online'];
        printf("%-24s %10s %10s %8s  %-20s %-20s\n",
            $s['tabel'],
            number_format((int)$s['offline']),
            number_format((int)$s['online']),
            number_format(((int)$s['offline']) - ((int)$s['online'])),
            $s['push_at'] ? substr((string)$s['push_at'], 0, 19) : '-',
            $s['pull_at'] ? substr((string)$s['pull_at'], 0, 19) : '-'
        );
    }
    echo str_repeat('-', 118) . PHP_EOL;
    printf("%-24s %10s %10s\n", 'TOTAL', number_format($totalOff), number_format($totalOn));
    echo PHP_EOL . " Catatan: perbedaan jumlah baris belum tentu data hilang (bisa juga data baru di sisi lain)." . PHP_EOL;
    exit(0);
}

// ------------------------------------------------------------------
// PUSH / PULL / BOTH
// ------------------------------------------------------------------
if (!in_array($perintah, ['push', 'pull', 'both'], true)) {
    sync_berhenti('Perintah tidak dikenal. Gunakan: status | push | pull | both | fix');
}

$jalankan = function (string $arah) use ($offline, $online, $policy, $hapus, $dry, $tabel) {
    if ($arah === 'push') {
        $src = $offline; $dst = $online;  $sisi = ['OFFLINE (MySQL lokal)', 'ONLINE  (Supabase)'];
    } else {
        $src = $online;  $dst = $offline; $sisi = ['ONLINE  (Supabase)', 'OFFLINE (MySQL lokal)'];
    }

    echo PHP_EOL . " >> ARAH: {$sisi[0]}  ->  {$sisi[1]}" . PHP_EOL;

    $engine = new SyncEngine($src, $dst, $arah, $policy, $hapus, $dry, $offline);
    $hasil  = $engine->jalankan($tabel);

    printf("%-24s %9s %9s %8s %8s %8s %8s  %s\n", 'TABEL', 'SUMBER', 'TUJUAN', 'SALIN', 'INSERT', 'UPDATE', 'LEWATI', 'STATUS');
    echo str_repeat('-', 104) . PHP_EOL;
    $tSalin = 0; $tIns = 0; $tUpd = 0; $tLew = 0; $tHapus = 0; $gagal = 0;
    foreach ($hasil as $t => $r) {
        $tSalin += (int)$r['tersalin'];
        $tIns   += (int)$r['terinsert'];
        $tUpd   += (int)$r['terupdate'];
        $tLew   += (int)$r['dilewati'];
        $tHapus += (int)$r['dihapus'];
        $st = strtoupper($r['status']);
        if ($r['status'] === 'gagal') $gagal++;
        printf("%-24s %9s %9s %8s %8s %8s %8s  %s%s\n",
            $t,
            number_format((int)$r['sumber']),
            number_format((int)$r['tujuan']),
            number_format((int)$r['tersalin']),
            number_format((int)$r['terinsert']),
            number_format((int)$r['terupdate']),
            number_format((int)$r['dilewati']),
            $st,
            $r['pesan'] !== '' ? ' - ' . $r['pesan'] : ''
        );
    }
    echo str_repeat('-', 104) . PHP_EOL;
    printf("%-24s %9s %9s %8s %8s %8s %8s\n", 'TOTAL', '', '', number_format($tSalin), number_format($tIns), number_format($tUpd), number_format($tLew));
    if ($tHapus > 0) echo " Baris dihapus (tidak ada di sumber): " . number_format($tHapus) . PHP_EOL;
    if ($gagal > 0) echo " Tabel gagal: $gagal" . PHP_EOL;
    return $hasil;
};

echo " Kebijakan  : $policy" . PHP_EOL;
echo " Hapus data : " . ($hapus ? 'YA (baris yang tidak ada di sumber dihapus)' : 'tidak') . PHP_EOL;
echo " Dry run    : " . ($dry ? 'YA (tidak ada yang ditulis)' : 'tidak') . PHP_EOL;
echo " Tabel      : " . count($tabel) . " (" . implode(', ', $tabel) . ")" . PHP_EOL;

if ($perintah === 'both') {
    // pull dulu (ambil data online terbaru ke lokal), lalu push (publikasikan lokal ke online)
    $jalankan('pull');
    $jalankan('push');
} else {
    $jalankan($perintah);
}

echo PHP_EOL . " Selesai: " . date('Y-m-d H:i:s') . PHP_EOL;
