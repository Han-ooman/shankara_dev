<?php session_start(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <?php include "pwa_head.php"; ?>
    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>404 - Halaman Tidak Ditemukan | Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --accent-blue: #2563eb;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f2557 0%, #1e40af 40%, #2563eb 100%);
            background-size: 300% 300%;
            animation: gradShift 16s ease-in-out infinite;
            color: #ffffff;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }
        @keyframes gradShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Ikon mengambang di latar */
        .float-icon {
            position: fixed;
            color: rgba(255, 255, 255, 0.22);
            z-index: 0;
            user-select: none;
            pointer-events: none;
        }
        @keyframes floatUp {
            0%   { transform: translateY(0) rotate(0deg); }
            50%  { transform: translateY(-30px) rotate(8deg); }
            100% { transform: translateY(0) rotate(0deg); }
        }
        @keyframes twinkle {
            0%, 100% { opacity: 0.15; }
            50%      { opacity: 0.4; }
        }
        .bokeh {
            position: fixed;
            z-index: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.35), transparent 70%);
            pointer-events: none;
        }

        .err-card {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
            text-align: center;
        }

        .big-404 {
            font-size: 6rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 4px;
            background: linear-gradient(135deg, #ffffff 20%, #a7f3d0 60%, #34d399 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: pulse 2.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.05); }
        }

        .house-anim {
            display: inline-block;
            font-size: 3.2rem;
            color: #34d399;
            animation: houseBounce 1.8s ease-in-out infinite;
            filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.35));
        }
        @keyframes houseBounce {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50%      { transform: translateY(-14px) rotate(-6deg); }
        }

        .gear-spin {
            display: inline-block;
            font-size: 1.6rem;
            color: rgba(255, 255, 255, 0.5);
            animation: spin 6s linear infinite;
        }
        .gear-spin-rev {
            display: inline-block;
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.35);
            animation: spinRev 5s linear infinite;
        }
        @keyframes spin    { to { transform: rotate(360deg); } }
        @keyframes spinRev { to { transform: rotate(-360deg); } }

        .btn-home {
            background-color: var(--primary-blue);
            color: #ffffff;
            border-radius: 30px;
            padding: 12px 28px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-home:hover {
            background-color: var(--accent-blue);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .btn-back {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 30px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-back:hover {
            background-color: rgba(255, 255, 255, 0.22);
            color: #ffffff;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<script>
// Bokeh & ikon latar dibuat via JS agar posisinya acak
document.addEventListener('DOMContentLoaded', function () {
    const icons = ['bi-house-door-fill', 'bi-buildings', 'bi-tree-fill', 'bi-key-fill', 'bi-geo-alt-fill', 'bi-compass'];
    const body = document.body;

    for (let i = 0; i < 16; i++) {
        const d = document.createElement('div');
        d.className = 'bokeh';
        const size = 8 + Math.random() * 28;
        d.style.width = size + 'px';
        d.style.height = size + 'px';
        d.style.top = Math.random() * 100 + '%';
        d.style.left = Math.random() * 100 + '%';
        d.style.animation = `twinkle ${3 + Math.random() * 4}s ease-in-out ${Math.random() * 4}s infinite`;
        body.appendChild(d);
    }

    for (let i = 0; i < 10; i++) {
        const el = document.createElement('i');
        el.className = 'bi ' + icons[i % icons.length] + ' float-icon';
        el.style.top = (5 + Math.random() * 85) + '%';
        el.style.left = (3 + Math.random() * 90) + '%';
        el.style.fontSize = (24 + Math.random() * 30) + 'px';
        el.style.animation = `floatUp ${5 + (i % 4)}s ease-in-out ${i * 0.4}s infinite`;
        body.appendChild(el);
    }
});
</script>

<div class="err-card">
    <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
        <i class="bi bi-house-door-fill house-anim"></i>
        <span class="gear-spin"><i class="bi bi-gear-fill"></i></span>
        <span class="gear-spin-rev"><i class="bi bi-gear"></i></span>
    </div>

    <h1 class="big-404 mb-1">404</h1>
    <h4 class="fw-bold mb-2">Ooops! Halaman Tidak Ditemukan</h4>
    <p class="text-white-50 small mb-4">
        Sepertinya alamat yang Anda tuju tidak tersedia, telah dipindahkan,
        atau belum dibangun seperti rumah yang masih dalam proses. <i class="bi bi-tools"></i>
    </p>

    <div class="d-flex justify-content-center gap-2">
        <?php if (isset($_SESSION['status_login']) && $_SESSION['status_login'] === true): ?>
            <a href="./" class="btn btn-home"><i class="bi bi-grid-1x2-fill me-1"></i> Kembali ke Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-home"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sistem</a>
        <?php endif; ?>
        <button type="button" class="btn btn-back" onclick="history.back()"><i class="bi bi-arrow-left me-1"></i> Kembali</button>
    </div>

    <div class="mt-4">
        <span class="text-white-50" style="font-size: 0.72rem;">Shankara Dev &bull; Sistem Terintegrasi Supabase &bull; Shankara Residence</span>
    </div>
</div>

    <script src="assets/js/app.js"></script>
</body>
</html>
