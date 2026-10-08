<?php
// cli_push_gen.php - Generate VAPID & ephemeral key untuk web push.
// Jalankan via CLI:  php cli_push_gen.php  (di direktori htdocs/shandev)
// (butuh OPENSSL_CONF di environment saat generate kunci EC)
if (PHP_SAPI !== 'cli') { http_response_code(403); die('CLI only'); }
error_reporting(E_ALL);
require __DIR__ . '/includes/koneksi.php';
require __DIR__ . '/includes/push_helper.php';

function gen_ec() {
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$k) { echo "GAGAL generate EC: " . openssl_error_string() . "\n"; return null; }
    openssl_pkey_export($k, $privPem);
    $d = openssl_pkey_get_details($k);
    $pubRaw = hex2bin('04' . bin2hex($d['ec']['x'] . $d['ec']['y']));
    return ['priv' => $privPem, 'pubRaw' => $pubRaw];
}

$vapid = gen_ec();
if (!$vapid) exit(1);
pengaturan_set($koneksi, 'vapid_priv', $vapid['priv']);
pengaturan_set($koneksi, 'vapid_pub', base64_encode($vapid['pubRaw']));
echo "vapid_priv / vapid_pub tersimpan\n";

$eph = gen_ec();
if (!$eph) exit(1);
pengaturan_set($koneksi, 'push_ephem_priv', $eph['priv']);
echo "push_ephem_priv tersimpan\n";

$tpl = [
  'pengingat_tpl_deadline'   => "Assalamualaikum Kak *{nama}*, kami mengingatkan berkas KPR unit *{unit}* ({cluster}) wajib selesai pada *{tanggal}* ({sisa}). Mohon kirim kelengkapan berkas sebelum deadline ya. Terima kasih.",
  'pengingat_tpl_wawancara'  => "Assalamualaikum Kak *{nama}*, jadwal wawancara bank KPR unit *{unit}* ({cluster}) pada *{tanggal}* ({sisa}). Mohon hadir tepat waktu dan bawa dokumen asli. Terima kasih.",
  'pengingat_tpl_akad'       => "Assalamualaikum Kak *{nama}*, jadwal akad KPR unit *{unit}* ({cluster}) pada *{tanggal}* ({sisa}). Mohon menyiapkan dokumen dan DP sesuai ketentuan. Terima kasih.",
];
foreach ($tpl as $k => $v) pengaturan_set($koneksi, $k, $v);
echo "template default pengingat tersimpan\n";

echo "\nVAPID PUBLIC KEY (base64url) utk JS:\n" . push_b64url_encode($vapid['pubRaw']) . "\n";
echo "\nSELESAI\n";