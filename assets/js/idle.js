/* Auto logout otomatis: sesi diakhiri jika user tidak aktif 30 menit.
   Aktivitas (klik, ketik, scroll, sentuh) meng-reset timer. */
(function () {
    var TIMEOUT_MS = 30 * 60 * 1000; // 30 menit
    var timer = null;

    function resetTimer() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
            window.location.href = 'logout.php?pesan=timeout';
        }, TIMEOUT_MS);
    }

    var events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
    events.forEach(function (ev) {
        window.addEventListener(ev, resetTimer, { passive: true });
    });

    resetTimer();
})();
