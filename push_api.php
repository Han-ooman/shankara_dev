<?php
// push_api.php - Endpoint Web Push Subscription
//  ?a=key         -> JSON {vapid_pub} (base64url public key VAPID)
//  ?a=subscribe   -> POST {endpoint, p256dh, auth}  (CSRF + login)
//  ?a=unsubscribe -> POST {endpoint}                (CSRF + login)
//  ?a=test        -> POST {}                        (kirim tes ke sub aktif saya, CSRF + login)
// Dipanggil oleh service worker push_client.js.
session_start();
include "includes/koneksi.php";
require_once __DIR__ . '/includes/push_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_GET['a'] ?? '';

// ---- a=key: publik, tanpa login ----
if ($action === 'key') {
    $vapidPubRaw = base64_decode(pengaturan_get($koneksi, 'vapid_pub', '') ?? '', true);
    if ($vapidPubRaw === false || $vapidPubRaw === '') {
        echo json_encode(['success' => false, 'message' => 'VAPID key belum dibuat. Jalankan cli_push_gen.php.']);
        exit();
    }
    echo json_encode(['success' => true, 'vapid_pub' => push_b64url_encode($vapidPubRaw)]);
    exit();
}

// ---- Semua aksi lain wajib login ----
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login diperlukan.']);
    exit();
}

// CSRF check untuk POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/security.php';
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid.']);
        exit();
    }
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$ua  = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

switch ($action) {
    case 'subscribe': {
        $endpoint = trim($_POST['endpoint'] ?? '');
        $p256dh   = trim($_POST['p256dh'] ?? '');
        $auth     = trim($_POST['auth'] ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            echo json_encode(['success' => false, 'message' => 'Data subscription tidak lengkap.']);
            exit();
        }
        if (strlen($endpoint) > 512 || strlen($p256dh) > 128 || strlen($auth) > 64) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data subscription melebihi batas.']);
            exit();
        }
        // Validasi format base64url agar aman disimpan
        $decP = push_b64url_decode($p256dh);
        $decA = push_b64url_decode($auth);
        if (strlen($decP) !== 65 || strlen($decA) !== 16) {
            echo json_encode(['success' => false, 'message' => 'Format kunci subscription tidak valid (p256dh 65B, auth 16B).']);
            exit();
        }

        $e = sdb_real_escape_string($koneksi, $endpoint);
        $p = sdb_real_escape_string($koneksi, $p256dh);
        $s = sdb_real_escape_string($koneksi, $auth);
        $u = sdb_real_escape_string($koneksi, $ua);
        $q = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, active)
              VALUES ($uid, '$e', '$p', '$s', '$u', 1)
              ON DUPLICATE KEY UPDATE p256dh = '$p', auth = '$s', user_agent = '$u', active = 1, created_at = created_at";
        $ok = sdb_query($koneksi, $q);
        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Subscription tersimpan.' : 'Gagal menyimpan subscription.']);
        exit();
    }

    case 'unsubscribe': {
        $endpoint = trim($_POST['endpoint'] ?? '');
        if ($endpoint === '') {
            echo json_encode(['success' => false, 'message' => 'Endpoint kosong.']);
            exit();
        }
        $e = sdb_real_escape_string($koneksi, $endpoint);
        sdb_query($koneksi, "DELETE FROM push_subscriptions WHERE user_id = $uid AND endpoint = '$e'");
        echo json_encode(['success' => true, 'message' => 'Subscription dihapus.']);
        exit();
    }

    case 'test': {
        if (!is_dir(__DIR__ . '/logs')) @mkdir(__DIR__ . '/logs', 0777, true);
        require_once __DIR__ . '/includes/push_helper.php';
        $q = sdb_query($koneksi, "SELECT * FROM push_subscriptions WHERE user_id = $uid AND active = 1");
        $n = $q ? sdb_num_rows($q) : 0;
        if ($n === 0) {
            echo json_encode(['success' => false, 'message' => 'Belum ada perangkat terdaftar untuk akun ini. Klik \'Aktifkan Notifikasi\' dulu.']);
            exit();
        }
        $sent = 0; $dead = 0; $last = '';
        while ($row = sdb_fetch_assoc($q)) {
            $res = push_kirim_subscription($koneksi, $row, 'Test notifikasi Shankara Dev',
                    ($_SESSION['username'] ?? 'shk') . ' test');
            if ($res['ok']) $sent++; else { $dead++; $last = $res['msg']; }
        }
        echo json_encode([
            'success' => $sent > 0,
            'message' => $sent > 0 ? "Push tes terkirim ke $sent perangkat." : "Gagal ($dead perangkat). $last",
            'sent'    => $sent,
            'dead'    => $dead,
        ]);
        exit();
    }

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
        exit();
}