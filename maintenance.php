<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include "pwa_head.php"; ?>
    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <title>Shankara Dev - Sedang Maintenance</title>
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --accent-blue: #2563eb;
            --bg-main: #f4f7f5;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f2557 0%, #1e40af 40%, #2563eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        .bg-overlay {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 220" preserveAspectRatio="none"><path fill="%23062e21" opacity="0.4" d="M0,220 L0,140 L60,110 L120,150 L120,220 Z M120,220 L120,130 L180,95 L240,140 L240,220 Z M240,220 L240,120 L320,70 L400,130 L400,220 Z M400,220 L400,150 L470,120 L540,160 L540,220 Z M540,220 L540,110 L620,80 L700,140 L700,220 Z M700,220 L700,140 L780,100 L860,150 L860,220 Z M860,220 L860,125 L940,85 L1020,140 L1020,220 Z M1020,220 L1020,150 L1080,115 L1140,150 L1140,220 L1200,190 L1200,220 Z"/></svg>');
            background-position: bottom;
            background-size: 100% 40vh;
            background-repeat: no-repeat;
        }

        .bokeh {
            position: fixed;
            z-index: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.3), transparent 70%);
            pointer-events: none;
        }

        .container {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 40px 24px;
            max-width: 520px;
        }

        .logo-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
        }

        .brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 24px;
        }

        .card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 40px 36px;
        }

        .icon-wrench {
            font-size: 3rem;
            margin-bottom: 16px;
            display: block;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.08); opacity: 1; }
        }

        .card h2 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .card p {
            font-size: 0.95rem;
            line-height: 1.6;
            opacity: 0.85;
            margin-bottom: 20px;
        }

        .detail-msg {
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 0.85rem;
            line-height: 1.5;
            opacity: 0.75;
            margin-bottom: 24px;
            border-left: 3px solid rgba(255,255,255,0.4);
        }

        .timer {
            font-size: 0.85rem;
            opacity: 0.6;
        }

        .timer strong {
            color: #93c5fd;
        }

        .bar-wrap {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.15);
            border-radius: 4px;
            margin-top: 16px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            background: linear-gradient(90deg, #60a5fa, #3b82f6);
            border-radius: 4px;
            animation: progress 30s linear infinite;
        }

        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        .footer-note {
            margin-top: 32px;
            font-size: 0.75rem;
            opacity: 0.5;
        }

        @media (max-width: 480px) {
            .card { padding: 32px 20px; }
            .brand-name { font-size: 1.3rem; }
            .card h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<div class="bg-overlay"></div>

<div class="container">
    <img src="favicon/favicon.png" alt="Shankara Dev" class="logo-img">
    <div class="brand-name">Shankara Dev</div>

    <div class="card">
        <span class="icon-wrench">ðŸ› </span>
        <h2>Website Sedang Dalam Perbaikan</h2>
        <p>Sistem sedang dalam proses pembaruan atau pemeliharaan. Silakan coba kembali beberapa saat lagi.</p>

        <?php
        $msg_file = __DIR__ . '/.maintenance';
        if (file_exists($msg_file)) {
            $msg = trim(file_get_contents($msg_file));
            if (!empty($msg)) {
                echo '<div class="detail-msg">' . nl2br(htmlspecialchars($msg)) . '</div>';
            }
        }
        ?>

        <div class="timer">
            Halaman ini akan memuat ulang otomatis dalam <strong id="countdown">30</strong> detik.
        </div>
        <div class="bar-wrap"><div class="bar"></div></div>
    </div>

    <div class="footer-note">Shankara Dev &copy; <?= date('Y') ?> &bull; PT. Golden Shankara Indonesia</div>
</div>

<script>
(function () {
    var sec = 30;
    var el = document.getElementById('countdown');
    var iv = setInterval(function () {
        sec--;
        if (el) el.textContent = sec;
        if (sec <= 0) { clearInterval(iv); location.reload(); }
    }, 1000);
})();

document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    for (var i = 0; i < 10; i++) {
        var d = document.createElement('div');
        d.className = 'bokeh';
        var sz = 10 + Math.random() * 30;
        d.style.width = sz + 'px';
        d.style.height = sz + 'px';
        d.style.top = Math.random() * 100 + '%';
        d.style.left = Math.random() * 100 + '%';
        d.style.animation = 'pulse ' + (3 + Math.random() * 4) + 's ease-in-out ' + (Math.random() * 3) + 's infinite';
        body.appendChild(d);
    }
});
</script>

</body>
</html>
