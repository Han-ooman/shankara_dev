<!-- PAGE LOADER -->
<style>
.page-loader {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: #0f2557;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    overflow: hidden;
    transition: opacity 0.6s cubic-bezier(.4,0,.2,1), visibility 0.6s;
}
.page-loader.hide { opacity: 0; visibility: hidden; pointer-events: none; }

/* Animated mesh gradient background */
.pl-bg {
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse 600px 600px at 20% 50%, rgba(37, 99, 235,0.15) 0%, transparent 70%),
        radial-gradient(ellipse 500px 500px at 80% 30%, rgba(96, 165, 250,0.10) 0%, transparent 70%),
        radial-gradient(ellipse 400px 400px at 60% 80%, rgba(59, 130, 246,0.08) 0%, transparent 70%);
    animation: pl-mesh 6s ease-in-out infinite alternate;
}
@keyframes pl-mesh {
    0% { transform: scale(1) rotate(0deg); filter: hue-rotate(0deg); }
    100% { transform: scale(1.15) rotate(8deg); filter: hue-rotate(15deg); }
}

/* Grid lines background */
.pl-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(96, 165, 250,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(96, 165, 250,0.03) 1px, transparent 1px);
    background-size: 60px 60px;
    animation: pl-grid-move 20s linear infinite;
}
@keyframes pl-grid-move {
    0% { transform: translate(0, 0); }
    100% { transform: translate(60px, 60px); }
}

/* Orbit rings */
.pl-orbit {
    position: absolute;
    border: 1px solid rgba(96, 165, 250,0.08);
    border-radius: 50%;
    animation: pl-orbit-spin linear infinite;
}
.pl-orbit:nth-child(1) { width: 280px; height: 280px; animation-duration: 12s; }
.pl-orbit:nth-child(2) { width: 360px; height: 360px; animation-duration: 18s; animation-direction: reverse; border-style: dashed; }
.pl-orbit:nth-child(3) { width: 440px; height: 440px; animation-duration: 25s; }
@keyframes pl-orbit-spin { to { transform: rotate(360deg); } }

/* Orbit dot */
.pl-orbit-dot {
    position: absolute;
    width: 6px; height: 6px;
    background: #60a5fa;
    border-radius: 50%;
    box-shadow: 0 0 12px #60a5fa, 0 0 24px rgba(96, 165, 250,0.4);
    top: -3px; left: 50%;
}

/* Center logo container */
.pl-center {
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

/* Glow ring behind logo */
.pl-glow-ring {
    position: absolute;
    width: 140px; height: 140px;
    top: 50%; left: 50%;
    transform: translate(-50%, -65%);
    border-radius: 50%;
    background: conic-gradient(from 0deg, transparent, #60a5fa, transparent, #3b82f6, transparent);
    animation: pl-glow-spin 3s linear infinite;
    filter: blur(8px);
    opacity: 0.5;
}
@keyframes pl-glow-spin { to { transform: translate(-50%, -65%) rotate(360deg); } }

/* Logo */
.pl-logo-wrap {
    position: relative;
    width: 90px; height: 90px;
    display: flex; align-items: center; justify-content: center;
}
.pl-logo {
    width: 64px; height: 64px;
    position: relative; z-index: 2;
    filter: drop-shadow(0 0 20px rgba(96, 165, 250,0.4));
    animation: pl-logo-breathe 3s ease-in-out infinite;
}
@keyframes pl-logo-breathe {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 0 20px rgba(96, 165, 250,0.4)); }
    50% { transform: scale(1.08); filter: drop-shadow(0 0 30px rgba(96, 165, 250,0.6)); }
}

/* Spinning arcs around logo */
.pl-arc {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 2.5px solid transparent;
}
.pl-arc:nth-child(1) {
    border-top-color: #60a5fa;
    animation: pl-arc-spin 1.2s linear infinite;
}
.pl-arc:nth-child(2) {
    inset: 6px;
    border-right-color: #93c5fd;
    animation: pl-arc-spin 1.8s linear infinite reverse;
}
@keyframes pl-arc-spin { to { transform: rotate(360deg); } }

/* Brand name */
.pl-brand {
    color: #fff;
    font-family: 'Segoe UI', system-ui, sans-serif;
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: 3px;
    text-transform: uppercase;
    background: linear-gradient(135deg, #fff 0%, #60a5fa 50%, #93c5fd 100%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: pl-shimmer 3s ease-in-out infinite;
}
@keyframes pl-shimmer {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Tagline */
.pl-tagline {
    color: rgba(255,255,255,0.35);
    font-size: 0.7rem;
    letter-spacing: 4px;
    text-transform: uppercase;
    font-weight: 500;
}

/* Animated bar loader */
.pl-bar-container {
    display: flex;
    gap: 3px;
    margin-top: 4px;
}
.pl-bar-seg {
    width: 28px; height: 3px;
    border-radius: 3px;
    background: rgba(255,255,255,0.08);
    overflow: hidden;
    position: relative;
}
.pl-bar-seg::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, #60a5fa, transparent);
    animation: pl-bar-fill 1.6s ease-in-out infinite;
}
.pl-bar-seg:nth-child(1)::after { animation-delay: 0s; }
.pl-bar-seg:nth-child(2)::after { animation-delay: 0.15s; }
.pl-bar-seg:nth-child(3)::after { animation-delay: 0.3s; }
.pl-bar-seg:nth-child(4)::after { animation-delay: 0.45s; }
.pl-bar-seg:nth-child(5)::after { animation-delay: 0.6s; }
.pl-bar-seg:nth-child(6)::after { animation-delay: 0.75s; }
.pl-bar-seg:nth-child(7)::after { animation-delay: 0.9s; }
.pl-bar-seg:nth-child(8)::after { animation-delay: 1.05s; }
@keyframes pl-bar-fill {
    0% { transform: translateX(-100%); }
    50% { transform: translateX(0%); }
    100% { transform: translateX(100%); }
}

/* Status text */
.pl-status {
    color: rgba(255,255,255,0.3);
    font-size: 0.68rem;
    letter-spacing: 1px;
    margin-top: 8px;
    animation: pl-status-pulse 2s ease-in-out infinite;
}
@keyframes pl-status-pulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.7; }
}

/* Floating particles */
.pl-particle {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    animation: pl-particle-float linear infinite;
}
@keyframes pl-particle-float {
    0% { transform: translateY(0) translateX(0) scale(0); opacity: 0; }
    10% { opacity: 1; transform: scale(1); }
    90% { opacity: 1; }
    100% { transform: translateY(-100vh) translateX(var(--drift)) scale(0.5); opacity: 0; }
}
</style>

<div class="page-loader" id="pageLoader">
    <div class="pl-bg"></div>
    <div class="pl-grid"></div>

    <!-- Orbit rings -->
    <div class="pl-orbit"><div class="pl-orbit-dot"></div></div>
    <div class="pl-orbit"><div class="pl-orbit-dot"></div></div>
    <div class="pl-orbit"></div>

    <!-- Center -->
    <div class="pl-center">
        <!-- Glow ring -->
        <div class="pl-glow-ring"></div>

        <!-- Logo with spinning arcs -->
        <div class="pl-logo-wrap">
            <div class="pl-arc"></div>
            <div class="pl-arc"></div>
            <img src="../assets/images/favicon/favicon.png" alt="Shankara Dev" class="pl-logo">
        </div>

        <div class="pl-brand">Shankara Dev</div>
        <div class="pl-tagline">Real Estate Management</div>

        <!-- Animated bar segments -->
        <div class="pl-bar-container">
            <div class="pl-bar-seg"></div>
            <div class="pl-bar-seg"></div>
            <div class="pl-bar-seg"></div>
            <div class="pl-bar-seg"></div>
            <div class="pl-bar-seg"></div>
            <div class="pl-bar-seg"></div>
            <div class="pl-bar-seg"></div>
            <div class="pl-bar-seg"></div>
        </div>

        <div class="pl-status">Memuat data sistem...</div>
    </div>
</div>

<script>
(function () {
    var loader = document.getElementById('pageLoader');
    if (!loader) return;

    /* Particles */
    var pWrap = loader;
    var colors = ['rgba(96, 165, 250,0.25)', 'rgba(147, 197, 253,0.20)', 'rgba(255,255,255,0.08)', 'rgba(59, 130, 246,0.15)'];
    for (var i = 0; i < 30; i++) {
        var p = document.createElement('div');
        p.className = 'pl-particle';
        var size = 2 + Math.random() * 6;
        p.style.width = size + 'px';
        p.style.height = size + 'px';
        p.style.left = Math.random() * 100 + '%';
        p.style.bottom = '-10px';
        p.style.background = colors[Math.floor(Math.random() * colors.length)];
        p.style.setProperty('--drift', (Math.random() * 80 - 40) + 'px');
        p.style.animationDuration = (5 + Math.random() * 10) + 's';
        p.style.animationDelay = (Math.random() * 6) + 's';
        pWrap.appendChild(p);
    }

    /* Hide on load */
    function hideLoader() {
        setTimeout(function () {
            loader.classList.add('hide');
            setTimeout(function () { loader.remove(); }, 700);
        }, 250);
    }

    if (document.readyState === 'complete') hideLoader();
    else window.addEventListener('load', hideLoader);

    /* Safety: max 4s */
    setTimeout(function () {
        if (!loader.classList.contains('hide')) hideLoader();
    }, 4000);
})();
</script>
<!-- END PAGE LOADER -->
