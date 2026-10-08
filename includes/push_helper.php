<?php
// push_helper.php - Web Push (VAPID RFC 8292 + enkripsi RFC 8291/8188)
// Bantuan untuk: pengingat otomatis, notifikasi PWA, dan tes push.
//
// CATATAN LINGKUNGAN:
// * openssl_pkey_new() (pembuatan kunci EC) MEMERLUKAN OPENSSL_CONF.
//   Di CLI/CLItest/opencode set: putenv/export OPENSSL_CONF=C:\xampp\php\extras\ssl\openssl.cnf
//   Di roots web (Apache) biasanya TIDAK tersedia -> pembuatan kunci dilakukan
//   via skrip CLI (cli_push_gen.php) lalu kunci disimpan di pengaturan_sistem.
// * openssl_pkey_derive(), openssl_sign(), load PEM: bekerja TANPA config file.
// * urutan argumen openssl_pkey_derive() = (PUBLIC_KEY, PRIVATE_KEY, &len)
//   (urutan ini sesuai source PHP: param pertama peer_pub_key).

if (!function_exists('push_b64url_encode')) {
    function push_b64url_encode(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}

if (!function_exists('push_b64url_decode')) {
    function push_b64url_decode(string $s): string {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        return base64_decode($s, true);
    }
}

// ===== Simpan/baca pengaturan_sistem (key/value) =====
if (!function_exists('pengaturan_get')) {
    function pengaturan_get($koneksi, string $kunci, $default = null) {
        if (!$koneksi) return $default;
        $k = mysqli_real_escape_string($koneksi, $kunci);
        $q = mysqli_query($koneksi, "SELECT nilai FROM pengaturan_sistem WHERE kunci = '$k' LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $r = mysqli_fetch_assoc($q);
            return $r['nilai'];
        }
        return $default;
    }
}

if (!function_exists('pengaturan_set')) {
    function pengaturan_set($koneksi, string $kunci, $nilai): bool {
        if (!$koneksi) return false;
        $k = mysqli_real_escape_string($koneksi, $kunci);
        $v = mysqli_real_escape_string($koneksi, (string)$nilai);
        $q = mysqli_query($koneksi, "SELECT id FROM pengaturan_sistem WHERE kunci = '$k' LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            return (bool)mysqli_query($koneksi, "UPDATE pengaturan_sistem SET nilai = '$v' WHERE kunci = '$k'");
        }
        return (bool)mysqli_query($koneksi, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('$k', '$v')");
    }
}

// ===== DER kecil (untuk membuat PEM publik EC dari raw point 65 byte) =====
if (!function_exists('push_der_len')) {
    function push_der_len(int $n): string {
        if ($n < 0x80) return chr($n);
        $b = '';
        while ($n > 0) { $b = chr($n & 0xFF) . $b; $n >>= 8; }
        $b = chr(0x80 | strlen($b)) . $b;
        return $b;
    }
}

if (!function_exists('push_spki_pem')) {
    /**
     * Bangun PEM SubjectPublicKeyInfo (EC P-256) dari raw 65 byte (0x04||x||y).
     */
    function push_spki_pem(string $raw65): ?string {
        if (strlen($raw65) !== 65 || $raw65[0] !== "\x04") return null;

        // OID id-ecPublicKey + prime256v1
        $oidEc   = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
        $oidP256 = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $alg = "\x30" . push_der_len(strlen($oidEc) + strlen($oidP256)) . $oidEc . $oidP256;

        $bitStr = "\x03" . push_der_len(strlen($raw65) + 1) . "\x00" . $raw65;
        $spki   = "\x30" . push_der_len(strlen($alg) + strlen($bitStr)) . $alg . $bitStr;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($spki), 64, "\n")
             . "-----END PUBLIC KEY-----";
        return $pem;
    }
}

// ===== Bangun PEM EC private key dari scalar 32 byte (+ opsional pub raw) =====
// Dipakai utk tes vektor & keperluan dev (membuat kunci dari value tertentu).
if (!function_exists('push_ec_priv_pem')) {
    function push_ec_priv_pem(string $scalar32, ?string $pubRaw65 = null): ?string {
        if (strlen($scalar32) !== 32) return null;

        $int = function (string $b) {
            // DER INTEGER: prepend 0x00 bila bit terdepan set
            if ((ord($b[0]) & 0x80) !== 0) $b = "\x00" . $b;
            return "\x02" . push_der_len(strlen($b)) . $b;
        };

        $version = $int("\x01");
        $privOctet = "\x04" . push_der_len(32) . $scalar32;
        $oidP256 = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $params = "\xa0" . push_der_len(strlen($oidP256)) . $oidP256;

        $body = $version . $privOctet . $params;
        if ($pubRaw65 !== null) {
            $bitStr = "\x03" . push_der_len(66) . "\x00" . $pubRaw65;
            $body .= "\xa1" . push_der_len(strlen($bitStr)) . $bitStr;
        }

        $der = "\x30" . push_der_len(strlen($body)) . $body;
        return "-----BEGIN EC PRIVATE KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END EC PRIVATE KEY-----";
    }
}

// ===== Opsional: dapatkan raw public key (65 byte) dari PEM private =====
if (!function_exists('push_pub_raw_from_priv')) {
    function push_pub_raw_from_priv(string $privPem): ?string {
        $k = openssl_pkey_get_private($privPem);
        if (!$k) return null;
        $d = openssl_pkey_get_details($k);
        if (empty($d['ec']['x']) || empty($d['ec']['y'])) return null;
        return hex2bin('04' . bin2hex($d['ec']['x'] . $d['ec']['y']));
    }
}

// ===== ECDH: shared secret (32 byte X coordinate) =====
if (!function_exists('push_ecdh_shared')) {
    /**
     * $privPem: PEM private key (EC P-256).
     * $peerPubRaw: raw 65 byte public key milik subscriber.
     * return string 32 byte shared secret, atau false.
     */
    function push_ecdh_shared(string $privPem, string $peerPubRaw) {
        $pubPem = push_spki_pem($peerPubRaw);
        if ($pubPem === null) return false;
        $pub = openssl_pkey_get_public($pubPem);
        $priv = openssl_pkey_get_private($privPem);
        if (!$pub || !$priv) return false;
        $len = 0;
        $secret = openssl_pkey_derive($pub, $priv, $len);
        return ($secret !== false) ? $secret : false;
    }
}

// ===== HKDF (RFC 5869) - versi satu-blok (L <= 32) =====
if (!function_exists('push_hkdf_single')) {
    function push_hkdf_single(string $prk, string $info, int $len): string {
        return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $len);
    }
}

// ===== Enkripsi aes128gcm (RFC 8188) — generic =====
if (!function_exists('push_aes128gcm_build')) {
    /**
     * Bangun body pesan aes128gcm.
     * @param string $plaintext  isi pesan (pada web push: text payload)
     * @param string $ikm        input-keying material (web push: hasil HKDF RFC 8291)
     * @param string $salt       16 byte salt (header) — boleh override utk tes
     * @param string $keyid      raw keyid (web push: ephemeral pub 65 byte)
     * @param int    $rs         record size (web push: 4096)
     */
    function push_aes128gcm_build(string $plaintext, string $ikm, string $salt, string $keyid = '', int $rs = 4096): ?string {
        if ($salt === '') $salt = random_bytes(16);
        if (strlen($salt) !== 16) return null;

        // PRK = HMAC(salt, IKM)
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        // CEK & NONCE (info diakhiri 0x00)
        $cek     = push_hkdf_single($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce   = push_hkdf_single($prk, "Content-Encoding: nonce\x00", 12);

        // Single record: plaintext = data || 0x02 (padding delimiter record terakhir)
        $record = $plaintext . "\x02";

        $cipher = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '');
        if ($cipher === false) return null;

        // header = salt(16) || rs(4 network) || idlen(1) || keyid(idlen)
        $header = $salt . pack('N', $rs) . chr(strlen($keyid)) . $keyid;

        return $header . $cipher . $tag;
    }
}

// ===== Derivaasi kunci web push (RFC 8291) + enkripsi =====
if (!function_exists('push_encrypt_webpush')) {
    /**
     * Enkripsi payload utk satu subscription.
     * @param string $plaintext   teks pesan (ASCII/UTF-8, maks ~3993 byte)
     * @param string $subP256     raw 65 byte public key subscriber
     * @param string $subAuth     auth secret (16 byte) subscriber
     * @param string $ephemPrivPem PEM private key ephemeral (milik aplikasi)
     * @param string|null $saltOverride 16 byte salt (utama utk tes vektor)
     * @return array{body:string, pubRaw:string}|false  body + public key utk keyid/k-header
     */
    function push_encrypt_webpush(string $plaintext, string $subP256, string $subAuth, string $ephemPrivPem, ?string $saltOverride = null) {
        // ecdh_secret via ECDH(privat aplikasi, publik subscriber)
        $ecdhSecret = push_ecdh_shared($ephemPrivPem, $subP256);
        if ($ecdhSecret === false) return false;

        // public key aplikasi (harus untuk key_info) — ambil dari kunci privat
        $privKey = openssl_pkey_get_private($ephemPrivPem);
        if (!$privKey) return false;
        $details = openssl_pkey_get_details($privKey);
        $asPublicRaw = hex2bin('04' . bin2hex($details['ec']['x'] . $details['ec']['y']));

        // PRK_key = HMAC(auth_secret, ecdh_secret)
        $prkKey = hash_hmac('sha256', $ecdhSecret, $subAuth, true);
        // key_info = "WebPush: info" || 0x00 || ua_public || as_public
        $keyInfo = "WebPush: info\x00" . $subP256 . $asPublicRaw;
        // IKM = HKDF-Expand(PRK_key, key_info, 32) = HMAC(prkKey, key_info||0x01)[0:32]
        $ikm = push_hkdf_single($prkKey, $keyInfo, 32);

        $salt = ($saltOverride !== null) ? $saltOverride : random_bytes(16);
        $body = push_aes128gcm_build($plaintext, $ikm, $salt, $asPublicRaw, 4096);
        if ($body === null) return false;

        return ['body' => $body, 'pubRaw' => $asPublicRaw];
    }
}

// ===== ECDSA DER -> raw (untuk JWT ES256) =====
if (!function_exists('push_der_sig_to_raw')) {
    function push_der_sig_to_raw(string $der): ?string {
        // ECDSA-Sig-Value ::= SEQUENCE { r INTEGER, s INTEGER }
        if ($der === '' || $der[0] !== "\x30") return null;
        $p = 2;
        $readLen = function () use ($der, &$p) {
            $l = ord($der[$p]); $p++;
            if ($l & 0x80) { $n = $l & 0x7F; $l = 0; while ($n--) { $l = ($l << 8) | ord($der[$p++]); } }
            return $l;
        };
        $total = $readLen();
        $r = $readLen(); $rInt = substr($der, $p, $r); $p += $r;
        $s = $readLen(); $sInt = substr($der, $p, $s); $p += $s;

        $to32 = function ($b) {
            if (strlen($b) > 32) $b = substr($b, -32); // hapus leading 0x00
            return str_pad($b, 32, "\x00", STR_PAD_LEFT);
        };
        return $to32($rInt) . $to32($sInt);
    }
}

// ===== VAPID: buat JWT + header Authorization =====
if (!function_exists('push_vapid_sign')) {
    /**
     * @param string $privPem PEM private key VAPID
     * @param string $aud     origin push service (asin dari endpoint, misal https://fcm.googleapis.com)
     * @param string $subject mailto:
     * @param int    $ttlDetik masa berlaku token (<= 86400)
     * @return array{jwt:string, pubB64:string, authHeader:string}
     */
    function push_vapid_sign(string $privPem, string $aud, string $subject = 'mailto:admin@shankara.local', int $ttlDetik = 21600): ?array {
        $privKey = openssl_pkey_get_private($privPem);
        if (!$privKey) return null;
        $details = openssl_pkey_get_details($privKey);
        $pubB64  = push_b64url_encode(hex2bin('04' . bin2hex($details['ec']['x'] . $details['ec']['y'])));

        $header = push_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = push_b64url_encode(json_encode([
            'aud' => $aud,
            'exp' => time() + $ttlDetik,
            'sub' => $subject,
        ]));
        $signingInput = $header . '.' . $payload;

        if (!openssl_sign($signingInput, $derSig, $privKey, OPENSSL_ALGO_SHA256)) return null;
        $rawSig = push_der_sig_to_raw($derSig);
        if ($rawSig === null) return null;

        $jwt = $signingInput . '.' . push_b64url_encode($rawSig);
        $authHeader = "vapid t=" . $jwt . ", k=" . $pubB64;
        return ['jwt' => $jwt, 'pubB64' => $pubB64, 'authHeader' => $authHeader];
    }
}

// ===== Kirim push ke endpoint =====
if (!function_exists('push_kirim')) {
    /**
     * @param string $endpoint URL subscription push service
     * @param string $body     body aes128gcm
     * @param string $authHeader nilai header Authorization vapid
     * @param string $topic    kunci topik (opsional, antrian bila offline)
     * @param int    $ttl      detik TTL
     * @return array{status:int, ok:bool, data:string, dead:bool}
     *   dead=true bila 404/410 (subscription kedaluwarsa -> hapus).
     */
    function push_kirim(string $endpoint, string $body, string $authHeader, string $topic = '', int $ttl = 86400): array {
        $headers = [
            "Authorization: $authHeader",
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            "TTL: $ttl",
            'Urgency: normal',
            'Accept: application/json',
        ];
        if ($topic !== '') $headers[] = 'Topic: ' . preg_replace('/[^a-zA-Z0-9_-]/', '', $topic);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_2_0') ? CURL_HTTP_VERSION_2_0 : CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'ok'     => $status >= 200 && $status < 300,
            'data'   => (string)$resp . ($err !== '' ? ' | curl: ' . $err : ''),
            'dead'   => ($status === 404 || $status === 410),
        ];
    }
}

// ===== Helper tingkat aplikasi: kirim push utk satu baris subscription =====
if (!function_exists('push_kirim_subscription')) {
    /**
     * $sub: array {endpoint, p256dh, auth, user_id, id} — baca dari push_subscriptions.
     * Mengambil konfigurasi VAPID & ephemeral dari pengaturan_sistem.
     * @return array{ok:bool,status:int,msg:string,dead:bool}
     */
    function push_kirim_subscription($koneksi, array $sub, string $plaintext, string $topic = ''): array {
        $vapidPriv = pengaturan_get($koneksi, 'vapid_priv', '');
        if ($vapidPriv === '') {
            return ['ok' => false, 'status' => 0, 'msg' => 'VAPID key belum dibuat (jalankan cli_push_gen.php)', 'dead' => false];
        }
        $ephemPriv = pengaturan_get($koneksi, 'push_ephem_priv', '');
        if ($ephemPriv === '') {
            return ['ok' => false, 'status' => 0, 'msg' => 'Ephemeral key belum dibuat (jalankan cli_push_gen.php)', 'dead' => false];
        }

        $enc = push_encrypt_webpush($plaintext, push_b64url_decode($sub['p256dh']), push_b64url_decode($sub['auth']), $ephemPriv);
        if ($enc === false) {
            return ['ok' => false, 'status' => 0, 'msg' => 'Gagal mengenkripsi (cek kunci)', 'dead' => false];
        }

        $origin = parse_url($sub['endpoint']);
        $aud = ($origin['scheme'] ?? 'https') . '://' . ($origin['host'] ?? '');
        if ($aud === 'https://') $aud = 'https://fcm.googleapis.com';

        $vapid = push_vapid_sign($vapidPriv, $aud, 'mailto:' . (pengaturan_get($koneksi, 'email_notif', 'admin@shankara.local')) , 21600);
        if ($vapid === null) {
            return ['ok' => false, 'status' => 0, 'msg' => 'Gagal membuat VAPID JWT', 'dead' => false];
        }

        $res = push_kirim($sub['endpoint'], $enc['body'], $vapid['authHeader'], $topic);
        $msg = 'HTTP ' . $res['status'] . ' ' . $res['data'];
        if ($res['dead']) {
            $id = (int)($sub['id'] ?? 0);
            if ($id > 0 && $koneksi) mysqli_query($koneksi, "DELETE FROM push_subscriptions WHERE id = $id");
            $msg .= ' (subscription dihapus)';
        }
        return ['ok' => $res['ok'], 'status' => $res['status'], 'msg' => $msg, 'dead' => $res['dead']];
    }
}

// ===== Template pesan (placeholder sederhana) =====
if (!function_exists('push_is_pwa_http')) {
    function push_is_pwa_http(): bool {
        return !empty($_SERVER['HTTPS']) || (($_SERVER['SERVER_PORT'] ?? 80) == 443) || strpos($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') !== false;
    }
}