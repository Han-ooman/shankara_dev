<?php
require_once __DIR__ . '/includes/security.php';   // memuat harden_session + csrf + rate-limit
harden_session();                          // atur cookie sesi aman SEBELUM session_start
session_start();
include "includes/koneksi.php";
require_once __DIR__ . '/includes/helper_log.php';

$pesan_error = "";

// Rate-limit login: kunci setelah 5 gagal (per username+IP)
$lock_key = login_fail_key($_POST['username'] ?? '');

// 1. CEK JIKA SUDAH LOGIN (Mencegah paksa akses halaman login jika sudah masuk)
if (isset($_SESSION['status_login']) && $_SESSION['status_login'] === true) {
    header("Location: ./");
    exit();
}

// 2. PROSES OTENTIKASI LOGIN
if (isset($_POST['login'])) {
    // Proteksi CSRF pada form login
    if (!csrf_verify()) {
        $pesan_error = "Sesi form tidak valid. Muat ulang halaman lalu coba lagi.";
    }
    // Proteksi brute-force: blokir jika sudah terlalu banyak percobaan gagal
    elseif (login_locked($lock_key, 5)) {
        $pesan_error = "Terlalu banyak percobaan masuk. Coba lagi beberapa saat.";
    } else {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validasi input kosong
    if (empty($username) || empty($password)) {
        $pesan_error = "Username dan Password wajib diisi!";
    } else {
        // Menggunakan variabel $koneksi sesuai file koneksi.php Anda
        $stmt = $koneksi->prepare("SELECT id, nama_lengkap, username, password, role, status FROM users WHERE username = ? LIMIT 1");
        
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Cek apakah akun aktif
                if ($user['status'] !== 'Aktif') {
                    $pesan_error = "Akun Anda telah dinonaktifkan. Hubungi Administrator.";
                    record_login_fail($lock_key);
                    log_aktivitas($koneksi, 'LOGIN_GAGAL', 'Login', 'Akun dinonaktifkan: ' . $username);
                } 
                // Verifikasi Password Hash yang Aman
                else if (password_verify($password, $user['password'])) {

                    // Batas perangkat login: default 1 device, admin (id 1) & Trainee (id 5) boleh 2
                    $limit_device = in_array(intval($user['id']), [1, 5], true) ? 2 : 1;

                    // IP klien utk penanda device (Vercel melewatkan IP asli di X-Forwarded-For)
                    $ip_client = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))[0]);

                    // Bersihkan sesi yang sudah basi (terakhir aktif lebih dari 30 menit lalu)
                    sdb_query($koneksi, "DELETE FROM user_sesi WHERE user_id = " . intval($user['id']) . " AND last_active < (NOW() - INTERVAL 30 MINUTE)");
                    // Buang row tidak sah (session_id kosong — tak mungkin dicocokkan cek_sesi)
                    sdb_query($koneksi, "DELETE FROM user_sesi WHERE user_id = " . intval($user['id']) . " AND (session_id IS NULL OR session_id = '')");

                    // Hitung jumlah sesi aktif untuk akun ini
                    $count_result = sdb_query($koneksi, "SELECT COUNT(*) AS jml FROM user_sesi WHERE user_id = " . intval($user['id']));
                    $jml_aktif = (int) sdb_fetch_assoc($count_result)['jml'];

                    if ($jml_aktif >= $limit_device) {
                        if ($limit_device === 2) {
                            $pesan_error = "Akun ini hanya bisa dipakai 2 device. Jika ingin menggunakan device lain, maka harus logout atau keluar dahulu.";
                        } else {
                            $pesan_error = "Akun ini hanya bisa dipakai 1 device. Jika ingin menggunakan device lain, maka harus logout atau keluar dahulu.";
                        }
                        log_aktivitas($koneksi, 'LOGIN_PENUH', 'Login', 'Login ditolak (kuota device penuh): ' . $username);
                    } else {
                        // Regenerasi ID Sesi untuk Mencegah Session Fixation Attack
                        session_regenerate_id(true);

                        // Set Variabel Sesi Aman
                        $_SESSION['status_login'] = true;
                        $_SESSION['user_id']      = $user['id'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                        $_SESSION['username']     = $user['username'];
                        $_SESSION['role']         = $user['role'];

                        // Reset counter gagal setelah login sukses
                        reset_login_fails($lock_key);

                        // Runtime Vercel dapat mengeksekusi 1 request 2x → hapus jejak login
                        // kembar milik device/IP yang sama supaya kuota tidak terisi palsu
                        $sid_baru = session_id();
                        $ip_safe  = sdb_real_escape_string($koneksi, $ip_client);
                        sdb_query($koneksi, "DELETE FROM user_sesi WHERE user_id = " . intval($user['id'])
                            . " AND session_id <> '" . sdb_real_escape_string($koneksi, $sid_baru) . "'"
                            . " AND ip_address = '" . $ip_safe . "'"
                            . " AND last_active > (NOW() - INTERVAL 2 MINUTE)");

                        // Simpan sesi aktif di tabel user_sesi (dengan IP device)
                        $stmt_sesi = $koneksi->prepare("INSERT INTO user_sesi (user_id, session_id, login_at, last_active, ip_address) VALUES (?, ?, NOW(), NOW(), ?)");
                        $stmt_sesi->bind_param("iss", $user['id'], $sid_baru, $ip_client);
                        $stmt_sesi->execute();
                        $stmt_sesi->close();

                        log_aktivitas($koneksi, 'LOGIN', 'Login', 'Login berhasil: ' . $user['username']);

                        header("Location: ./");
                        exit();
                    }
                } else {
                    $pesan_error = "Username atau Password salah!";
                    record_login_fail($lock_key);
                    log_aktivitas($koneksi, 'LOGIN_GAGAL', 'Login', 'Password salah: ' . $username);
                }
            } else {
                $pesan_error = "Username atau Password salah!";
                record_login_fail($lock_key);
                log_aktivitas($koneksi, 'LOGIN_GAGAL', 'Login', 'Username tidak ditemukan: ' . $username);
            }
            $stmt->close();
        } else {
            $pesan_error = "Terjadi kesalahan pada sistem database.";
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">    <title>Login - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --accent-blue: #2563eb;
            --card-radius: 20px;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        /* ===== BACKGROUND INTERAKTIF TEMA PERUMAHAN ===== */
        .bg-animated {
            position: fixed;
            inset: 0;
            z-index: -2;
            background: linear-gradient(135deg, #0f2557 0%, #1e40af 40%, #2563eb 100%);
            background-size: 300% 300%;
            animation: gradShift 16s ease-in-out infinite;
        }
        @keyframes gradShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Lapisan siluet rumah di bawah (langit kota) */
        .bg-skyline {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 34vh;
            z-index: -1;
            opacity: 0.5;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 220" preserveAspectRatio="none"><path fill="%23062e21" d="M0,220 L0,140 L60,110 L120,150 L120,220 Z M120,220 L120,130 L180,95 L240,140 L240,220 Z M240,220 L240,120 L320,70 L400,130 L400,220 Z M400,220 L400,150 L470,120 L540,160 L540,220 Z M540,220 L540,110 L620,80 L700,140 L700,220 Z M700,220 L700,140 L780,100 L860,150 L860,220 Z M860,220 L860,125 L940,85 L1020,140 L1020,220 Z M1020,220 L1020,150 L1080,115 L1140,150 L1140,220 L1200,190 L1200,220 Z"/></svg>');
            background-size: 100% 100%;
            background-repeat: no-repeat;
            animation: skylineBreath 6s ease-in-out infinite;
        }
        @keyframes skylineBreath {
            0%, 100% { transform: translateY(0); opacity: 0.45; }
            50%      { transform: translateY(-6px); opacity: 0.6; }
        }

        /* Ikon rumah / elemen perumahan yang mengambang */
        .float-icon {
            position: fixed;
            color: rgba(255, 255, 255, 0.28);
            z-index: -1;
            user-select: none;
            pointer-events: none;
            will-change: transform;
        }
        @keyframes floatUp {
            0%   { transform: translateY(0); }
            50%  { transform: translateY(-28px); }
            100% { transform: translateY(0); }
        }
        @keyframes twinkle {
            0%, 100% { opacity: 0.18; }
            50%      { opacity: 0.4; }
        }

        /* Bola cahaya kecil (bokeh) */
        .bokeh {
            position: fixed;
            z-index: -1;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.35), transparent 70%);
            pointer-events: none;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: var(--card-radius);
            padding: 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            color: #ffffff;
        }

        .brand-logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .form-control-custom {
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.95rem;
            background-color: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .form-control-custom::placeholder {
            color: rgba(255, 255, 255, 0.65);
        }

        .form-control-custom:focus {
            background-color: rgba(255, 255, 255, 0.25);
            border-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }

        .input-icon {
            background-color: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.8);
        }

        .login-card .form-label,
        .login-card .subtitle-muted {
            color: rgba(255, 255, 255, 0.9);
        }

        .btn-login {
            background-color: var(--primary-blue);
            color: #ffffff;
            border-radius: 30px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-login:hover {
            background-color: var(--accent-blue);
            color: #ffffff;
        }

        /* ===== RESPONSIF HP: proporsi pas & tetap di tengah ===== */
        @media (max-width: 576px) {
            body {
                overflow: auto;
                padding: 16px;
            }

            .login-card {
                margin: auto;
                max-width: 100%;
                padding: 24px 18px;
                border-radius: 16px;
            }

            .login-clock {
                padding: 12px 14px;
                margin-bottom: 16px;
            }

            .lc-time {
                font-size: 1.6rem;
            }

            .brand-logo {
                font-size: 1.15rem;
                margin-bottom: 16px;
            }

            .brand-logo img {
                width: 46px;
                height: 46px;
            }

            .subtitle-muted {
                margin-bottom: 16px !important;
            }
        }

        /* Layar pendek (semua lebar): padatkan isi kartu agar tetap muat & di tengah */
        @media (max-height: 700px) {
            body {
                overflow: auto;
            }

            .login-card {
                padding: 18px 16px;
            }

            .login-clock {
                padding: 8px 12px;
                margin-bottom: 10px;
            }

            .lc-time {
                font-size: 1.3rem;
            }

            .lc-date {
                margin-top: 2px;
            }

            .lc-sec-bar {
                display: none;
            }

            .brand-logo {
                margin-bottom: 10px;
            }
        }

        /* Layar pendek & sempit: sembunyikan jam agar form proporsional */
        @media (max-height: 650px) {
            .login-clock {
                display: none;
            }
        }

        /* Layar sangat pendek (mis. landscape HP): padatkan maksimal */
        @media (max-height: 520px) {
            .login-card {
                padding: 14px;
            }

            .brand-logo img {
                width: 36px;
                height: 36px;
            }

            .brand-logo {
                font-size: 1.05rem;
                gap: 4px;
                margin-bottom: 4px;
            }

            .subtitle-muted {
                margin-bottom: 8px !important;
            }

            .form-control-custom {
                padding: 7px 10px;
            }

            .login-card .form-label {
                margin-bottom: 2px;
            }

            .login-card form .mb-3 {
                margin-bottom: 10px !important;
            }

            .login-card form .mb-4 {
                margin-bottom: 12px !important;
            }

            .login-card .mt-4 {
                margin-top: 10px !important;
            }
        }
    </style>
</head>
<body class="login-bg">
    <?php include "includes/page_loader.php"; ?>

<!-- LAPISAN BACKGROUND INTERAKTIF -->
<div class="bg-animated"></div>
<div class="bg-skyline"></div>

<script>
// Bokeh & ikon mengambang dibuat via JS agar posisinya acak & interaktif
document.addEventListener('DOMContentLoaded', function () {
    const icons = ['bi-house-door-fill', 'bi-buildings', 'bi-tree-fill', 'bi-key-fill', 'bi-geo-alt-fill'];
    const sizes = [26, 34, 22, 30, 28];
    const positions = [
        { top: '12%', left: '8%' },   { top: '18%', left: '30%' },
        { top: '8%',  left: '55%' },  { top: '22%', left: '78%' },
        { top: '60%', left: '5%' },   { top: '70%', left: '22%' },
        { top: '62%', left: '45%' },  { top: '75%', left: '68%' },
        { top: '55%', left: '88%' },  { top: '30%', left: '92%' }
    ];
    const body = document.body;

    // Bokeh
    for (let i = 0; i < 14; i++) {
        const d = document.createElement('div');
        d.className = 'bokeh';
        const size = 8 + Math.random() * 26;
        d.style.width = size + 'px';
        d.style.height = size + 'px';
        d.style.top = Math.random() * 100 + '%';
        d.style.left = Math.random() * 100 + '%';
        d.style.animation = `twinkle ${3 + Math.random() * 4}s ease-in-out ${Math.random() * 4}s infinite`;
        body.appendChild(d);
    }

    // Ikon rumah
    positions.forEach((pos, i) => {
        const el = document.createElement('i');
        el.className = 'bi ' + icons[i % icons.length] + ' float-icon';
        el.style.top = pos.top;
        el.style.left = pos.left;
        el.style.fontSize = sizes[i] + 'px';
        el.style.animation = `floatUp ${5 + (i % 4)}s ease-in-out ${i * 0.4}s infinite`;
        body.appendChild(el);
    });

    // Efek parallax mengikuti mouse (interaktif & catchy)
    const parallaxEls = document.querySelectorAll('.float-icon, .bokeh, .bg-skyline');
    let raf = null;
    window.addEventListener('mousemove', (e) => {
        if (raf) return;
        raf = requestAnimationFrame(() => {
            const cx = (e.clientX / window.innerWidth - 0.5);
            const cy = (e.clientY / window.innerHeight - 0.5);
            parallaxEls.forEach((el, idx) => {
                const depth = (idx % 5) * 4 + 6;
                const tx = cx * depth;
                const ty = cy * depth;
                if (el.classList.contains('bg-skyline')) {
                    el.style.transform = `translate(${tx * 2}px, ${ty * 2}px)`;
                } else {
                    el.style.transform = `translate(${tx}px, ${ty}px)`;
                }
            });
            raf = null;
        });
    });
});
</script>

<div class="login-card">
    <!-- REAL-TIME CLOCK -->
    <div class="login-clock" id="loginClock">
        <div class="lc-time">
            <span class="lc-h" id="lcH">00</span><span class="lc-colon">:</span><span class="lc-m" id="lcM">00</span><span class="lc-sep">.</span><span class="lc-s" id="lcS">00</span>
        </div>
        <div class="lc-date"><span id="lcDay">--</span>, <span id="lcDate">--</span></div>
        <div class="lc-sec-bar"><div class="lc-sec-fill" id="lcSecBar"></div></div>
    </div>
    <style>
        .login-clock {
            text-align: center;
            margin-bottom: 20px;
            padding: 16px 20px;
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
        }
        .lc-time {
            font-family: 'Courier New', monospace;
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 2px;
            line-height: 1.2;
            text-shadow: 0 0 20px rgba(96, 165, 250,0.5), 0 0 40px rgba(96, 165, 250,0.2);
        }
        .lc-colon {
            animation: lcBlink 1s step-end infinite;
            color: #60a5fa;
        }
        .lc-sep {
            font-size: 1.4rem;
            color: rgba(255,255,255,0.4);
            margin: 0 1px;
        }
        .lc-s {
            font-size: 1.2rem;
            color: #60a5fa;
            min-width: 2ch;
            display: inline-block;
        }
        .lc-date {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.55);
            margin-top: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 500;
        }
        .lc-sec-bar {
            margin-top: 10px;
            height: 3px;
            background: rgba(255,255,255,0.08);
            border-radius: 3px;
            overflow: hidden;
        }
        .lc-sec-fill {
            height: 100%;
            background: linear-gradient(90deg, #60a5fa, #3b82f6);
            border-radius: 3px;
            width: 0%;
            transition: width 0.3s linear;
            box-shadow: 0 0 8px rgba(96, 165, 250,0.6);
        }
        @keyframes lcBlink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }
        @media (max-width: 480px) {
            .lc-time { font-size: 1.7rem; }
        }
    </style>

    <div class="brand-logo">
        <img src="assets/images/favicon/favicon.png" alt="Shankara Dev" style="width: 56px; height: 56px; object-fit: contain;">
        <span>Shankara Dev</span>
    </div>
    
    <p class="text-center subtitle-muted small mb-4">Silakan masuk menggunakan akun administratif Anda.</p>

    <?php if (!empty($pesan_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 small mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($pesan_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] === 'timeout'): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 small mb-3" role="alert">
            <i class="bi bi-hourglass-split me-1"></i> Sesi Anda berakhir karena tidak ada aktivitas selama 30 menit. Silakan masuk kembali.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold">Username</label>
            <div class="input-group">
                <span class="input-group-text input-icon border-end-0 rounded-start-4 ps-3"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control form-control-custom border-start-0 rounded-end-4 shadow-none" placeholder="Masukkan username..." required autocomplete="off">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text input-icon border-end-0 rounded-start-4 ps-3"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control form-control-custom border-start-0 rounded-end-4 shadow-none" placeholder="Masukkan password..." required>
            </div>
        </div>

        <button type="submit" name="login" class="btn btn-login shadow-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sistem
        </button>
    </form>

    <div class="text-center mt-4">
        <span class="text-muted" style="font-size: 0.75rem; color: rgba(255,255,255,0.7) !important;">Sistem Terintegrasi MySQL &bull; Shankara Residence</span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
(function(){
    var days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabit'];
    var months=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    function pad(n){return n<10?'0'+n:n;}
    function tick(){
        var now=new Date();
        var h=now.getHours(),m=now.getMinutes(),s=now.getSeconds();
        document.getElementById('lcH').textContent=pad(h);
        document.getElementById('lcM').textContent=pad(m);
        document.getElementById('lcS').textContent=pad(s);
        document.getElementById('lcDay').textContent=days[now.getDay()];
        document.getElementById('lcDate').textContent=now.getDate()+' '+months[now.getMonth()]+' '+now.getFullYear();
        document.getElementById('lcSecBar').style.width=(s/59*100)+'%';
    }
    tick();
    setInterval(tick,1000);
})();
</script>
</body>
</html>