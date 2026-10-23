<?php
// ============================================================
// SINKRONISASI DATA  -  OFFLINE (MySQL lokal) <-> ONLINE (Supabase)
// Hanya untuk Super Admin.
// ============================================================

include "includes/koneksi.php";
require_once __DIR__ . '/includes/role_access.php';
require_once __DIR__ . '/includes/sync_engine.php';

session_start();
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";

// Guard role (Super Admin)
if (!role_boleh_akses($_SESSION['role'] ?? '', 'sinkronisasi.php')) {
    http_response_code(403);
    die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>403</title></head>'
      . '<body style="font-family:sans-serif;padding:40px;text-align:center">'
      . '<h1>403 Forbidden</h1><p>Halaman sinkronisasi hanya dapat diakses oleh <b>Super Admin</b>.</p>'
      . '<a href="index.php">Kembali ke Dashboard</a></body></html>');
}

@set_time_limit(600);

$daftarTabel = sync_daftar_tabel();
$hasil   = null;
$pesan   = '';
$alert   = '';
$riwayat = [];

// ------------------------------------------------------------------
// Proses sinkronisasi
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        die("Token keamanan tidak valid.");
    }

    $aksi = (string)($_POST['aksi'] ?? '');
    $policy = (string)($_POST['policy'] ?? SyncEngine::POLICY_OVERWRITE);
    $hapus  = !empty($_POST['hapus']);
    $dry    = !empty($_POST['dry_run']);

    // Tabel terpilih (bisa berupa array atau daftar dipisah koma)
    if (isset($_POST['tabel']) && is_array($_POST['tabel'])) {
        $pilih = array_map('strval', $_POST['tabel']);
    } elseif (!empty($_POST['tabel']) && is_string($_POST['tabel'])) {
        $pilih = explode(',', $_POST['tabel']);
    } else {
        $pilih = [];
    }
    $pilih = array_values(array_filter(array_map('trim', $pilih), fn($v) => $v !== ''));

    // --- Perbaiki auto increment MySQL lokal ---
    if ($aksi === 'fix') {
        $fixed = sync_perbaiki_auto_increment($koneksi_offline);
        $alert = $fixed
            ? 'success'
            : 'info';
        $pesan = $fixed
            ? 'Auto increment ditambahkan pada: ' . implode(', ', $fixed)
            : 'Semua primary key di MySQL lokal sudah AUTO_INCREMENT.';
    }
    // --- Sinkronisasi ---
    elseif (in_array($aksi, ['push', 'pull', 'both'], true)) {
        if (!$pilih) {
            $alert = 'warning';
            $pesan = 'Pilih minimal satu tabel untuk disinkronkan.';
        } else {
            $tabelValid = array_values(array_intersect($pilih, array_keys($daftarTabel)));
            if (!$tabelValid) {
                $alert = 'warning';
                $pesan = 'Tabel yang dipilih tidak valid.';
            } else {
                $arahList = ($aksi === 'both') ? ['pull', 'push'] : [$aksi];
                $hasilAll = [];
                foreach ($arahList as $arah) {
                    if ($arah === 'push') {
                        $src = $koneksi_offline; $dst = $koneksi_online;
                        $ket = 'OFFLINE (MySQL lokal) &rarr; ONLINE (Supabase)';
                    } else {
                        $src = $koneksi_online;  $dst = $koneksi_offline;
                        $ket = 'ONLINE (Supabase) &rarr; OFFLINE (MySQL lokal)';
                    }
                    $engine = new SyncEngine($src, $dst, $arah, $policy, $hapus, $dry, $koneksi_offline);
                    $hasilAll[$arah] = ['ket' => $ket, 'tabel' => $engine->jalankan($tabelValid)];
                }
                $hasil = $hasilAll;
                $alert = 'success';
                $pesan = ($dry ? '[DRY RUN] ' : '') . 'Sinkronisasi selesai.';
            }
        }
    }
}

// ------------------------------------------------------------------
// Data untuk tampilan
// ------------------------------------------------------------------
$tabelAktif = sync_tabel_aktif(true);           // semua tabel (termasuk opsional)
$status     = sync_status_tabel($koneksi_offline, $koneksi_online, $tabelAktif);
$riwayat    = sync_riwayat($koneksi_offline, 15);

$totalOffline = 0;
$totalOnline  = 0;
$totalBeda    = 0;
foreach ($status as $s) {
    $totalOffline += (int)$s['offline'];
    $totalOnline  += (int)$s['online'];
    if ((int)$s['offline'] !== (int)$s['online']) $totalBeda++;
}

// Kelompokkan tabel untuk checkbox
$grupTabel = [];
foreach ($daftarTabel as $nama => $cfg) {
    $grupTabel[$cfg['grup']][] = $nama;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <?php include "pwa_head.php"; ?>
    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Sinkronisasi Data - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
<div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-layout">
    <?php include "sidebar.php"; ?>
    <main class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Sinkronisasi Data</h4>
                <p class="text-muted small mb-0">Menyalin data antara MySQL lokal (offline) dan Supabase (online).</p>
            </div>
            <span class="badge rounded-3 px-3 py-2 bg-<?= DB_MODE === 'online' ? 'info' : 'secondary' ?>">
                Mode aktif: <?= strtoupper(DB_MODE) ?>
            </span>
        </div>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-<?= $alert ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-1"></i><?= $pesan ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- RINGKASAN -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card d-block d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);"><i class="bi bi-hdd-network"></i></div>
                    <div>
                        <div class="fs-4 fw-bold"><?= number_format($totalOffline) ?></div>
                        <div class="text-muted small fw-medium">Baris Offline (MySQL)</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-block d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #0f766e, #14b8a6);"><i class="bi bi-cloud"></i></div>
                    <div>
                        <div class="fs-4 fw-bold"><?= number_format($totalOnline) ?></div>
                        <div class="text-muted small fw-medium">Baris Online (Supabase)</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-block d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #b45309, #f59e0b);"><i class="bi bi-arrow-left-right"></i></div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $totalBeda ?></div>
                        <div class="text-muted small fw-medium">Tabel Belum Sama</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-block d-flex align-items-center gap-3">
                    <div class="stat-icon text-white" style="background: linear-gradient(135deg, #4c1d95, #7c3aed);"><i class="bi bi-table"></i></div>
                    <div>
                        <div class="fs-4 fw-bold"><?= count($tabelAktif) ?></div>
                        <div class="text-muted small fw-medium">Tabel Dipantau</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- FORM SINKRONISASI -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Jalankan Sinkronisasi</h6>

                        <form method="post" id="formSync">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" id="inputAksi" value="pull">

                            <label class="form-label small fw-semibold">Pilih Tabel</label>
                            <?php foreach ($grupTabel as $grup => $list): ?>
                                <div class="mb-2">
                                    <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.5px;"><?= htmlspecialchars($grup) ?></div>
                                    <?php foreach ($list as $t): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tabel[]" value="<?= htmlspecialchars($t) ?>"
                                                   id="tabel_<?= htmlspecialchars($t) ?>" <?= !empty($daftarTabel[$t]['sync']) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="tabel_<?= htmlspecialchars($t) ?>">
                                                <?= htmlspecialchars($daftarTabel[$t]['label']) ?>
                                                <span class="text-muted">(<?= htmlspecialchars($t) ?>)</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>

                            <hr>
                            <label class="form-label small fw-semibold">Kebijakan Konflik</label>
                            <select class="form-select form-select-sm mb-2" name="policy">
                                <option value="overwrite">Timpa data tujuan (overwrite)</option>
                                <option value="skip">Jangan sentuh yang sudah ada (skip)</option>
                                <option value="newest">Yang paling baru menang (newest)</option>
                            </select>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="hapus" value="1" id="chkHapus">
                                <label class="form-check-label small" for="chkHapus">Hapus baris di tujuan yang tidak ada di sumber</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="dry_run" value="1" id="chkDry">
                                <label class="form-check-label small" for="chkDry">Dry run (hitung saja, tidak menulis)</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-primary rounded-pill" type="submit" onclick="setAksi('pull')">
                                    <i class="bi bi-cloud-arrow-down me-1"></i> Tarik Online &rarr; Offline
                                </button>
                                <button class="btn btn-success rounded-pill" type="submit" onclick="setAksi('push')">
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Kirim Offline &rarr; Online
                                </button>
                                <button class="btn btn-warning rounded-pill" type="submit" onclick="setAksi('both')">
                                    <i class="bi bi-arrow-left-right me-1"></i> Sinkron Dua Arah
                                </button>
                            </div>
                        </form>

                        <hr>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="fix">
                            <button class="btn btn-outline-secondary btn-sm rounded-pill w-100" type="submit">
                                <i class="bi bi-tools me-1"></i> Perbaiki Auto Increment MySQL Lokal
                            </button>
                        </form>
                        <div class="form-text mt-2">Pastikan primary key MySQL lokal memakai AUTO_INCREMENT (selaras dengan PostgreSQL).</div>
                    </div>
                </div>

                <?php if (!empty($riwayat)): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Sinkronisasi</h6>
                        <div class="small">
                            <?php foreach ($riwayat as $r): ?>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['tabel']) ?></div>
                                        <div class="text-muted" style="font-size:.72rem">
                                            <?= $r['arah'] === 'push' ? 'Offline &rarr; Online' : 'Online &rarr; Offline' ?>
                                            &middot; <?= (int)$r['rows_copied'] ?> baris
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?= $r['status'] === 'ok' ? 'success' : ($r['status'] === 'gagal' ? 'danger' : 'secondary') ?> rounded-3">
                                            <?= htmlspecialchars(strtoupper($r['status'])) ?>
                                        </span>
                                        <div class="text-muted" style="font-size:.7rem"><?= substr((string)$r['created_at'], 5, 14) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- STATUS TABEL -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-table me-2 text-primary"></i>Perbandingan Data per Tabel</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tabel</th>
                                        <th class="text-center">Offline</th>
                                        <th class="text-center">Online</th>
                                        <th class="text-center">Status</th>
                                        <th>Push Terakhir</th>
                                        <th>Pull Terakhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($status as $s): ?>
                                    <?php $beda = ((int)$s['offline'] !== (int)$s['online']); ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($s['label']) ?></div>
                                            <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($s['tabel']) ?></div>
                                        </td>
                                        <td class="text-center"><?= number_format((int)$s['offline']) ?></td>
                                        <td class="text-center"><?= number_format((int)$s['online']) ?></td>
                                        <td class="text-center">
                                            <?php if ($beda): ?>
                                                <span class="badge bg-warning text-dark rounded-3">Beda</span>
                                            <?php else: ?>
                                                <span class="badge bg-success rounded-3">Sama</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted" style="font-size:.75rem"><?= $s['push_at'] ? substr((string)$s['push_at'], 0, 16) : '-' ?></td>
                                        <td class="text-muted" style="font-size:.75rem"><?= $s['pull_at'] ? substr((string)$s['pull_at'], 0, 16) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text mt-2">View (data_rumah, data_cluster, data_konsumen, dll.) tidak disinkronkan karena datanya diambil dari tabel base.</div>
                    </div>
                </div>

                <!-- LAPORAN HASIL -->
                <?php if ($hasil): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2 text-primary"></i>Hasil Sinkronisasi</h6>
                        <?php foreach ($hasil as $arah => $blok): ?>
                            <div class="mb-3">
                                <div class="small fw-bold text-uppercase text-muted mb-1"><?= $blok['ket'] ?></div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0" style="font-size:.78rem">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tabel</th>
                                                <th class="text-center">Sumber</th>
                                                <th class="text-center">Tujuan</th>
                                                <th class="text-center">Salin</th>
                                                <th class="text-center">Insert</th>
                                                <th class="text-center">Update</th>
                                                <th class="text-center">Lewati</th>
                                                <th class="text-center">Hapus</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($blok['tabel'] as $t => $r): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($t) ?></td>
                                                <td class="text-center"><?= number_format((int)$r['sumber']) ?></td>
                                                <td class="text-center"><?= number_format((int)$r['tujuan']) ?></td>
                                                <td class="text-center"><?= number_format((int)$r['tersalin']) ?></td>
                                                <td class="text-center"><?= number_format((int)$r['terinsert']) ?></td>
                                                <td class="text-center"><?= number_format((int)$r['terupdate']) ?></td>
                                                <td class="text-center"><?= number_format((int)$r['dilewati']) ?></td>
                                                <td class="text-center"><?= number_format((int)$r['dihapus']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $r['status'] === 'ok' ? 'success' : ($r['status'] === 'gagal' ? 'danger' : 'secondary') ?> rounded-3">
                                                        <?= htmlspecialchars(strtoupper($r['status'])) ?>
                                                    </span>
                                                    <?php if ($r['pesan'] !== ''): ?>
                                                        <div class="text-danger" style="font-size:.7rem"><?= htmlspecialchars($r['pesan']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setAksi(v) {
    document.getElementById('inputAksi').value = v;
    var pesan = {
        push: 'Kirim data dari MySQL lokal ke Supabase?',
        pull: 'Tarik data dari Supabase ke MySQL lokal?',
        both: 'Sinkron dua arah (tarik lalu kirim)?'
    };
    if (v !== 'both' && !confirm(pesan[v])) {
        event.preventDefault();
        return false;
    }
    return true;
}
</script>
</body>
</html>
