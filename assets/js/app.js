(function () {
    'use strict';

    function syncIcon() {
        var btn = document.getElementById('sidebarToggleBtn');
        if (!btn) return;
        var icon = btn.querySelector('i');
        if (!icon) return;
        icon.className = document.body.classList.contains('sidebar-open') ? 'bi bi-x-lg' : 'bi bi-list';
    }

    // Paksa sidebar mobile agar tingginya mengikuti viewport
    // (area scroll diatur lewat CSS .sidebar-content flex + overflow)
    function fixMobileSidebar() {
        var sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        var isMobile = window.innerWidth <= 767.98;
        if (isMobile) {
            sidebar.style.height = window.innerHeight + 'px';
            sidebar.style.maxHeight = window.innerHeight + 'px';
            sidebar.style.overflow = 'hidden';
        } else {
            sidebar.style.height = '';
            sidebar.style.maxHeight = '';
            sidebar.style.overflow = '';
        }

        var content = sidebar.querySelector('.sidebar-content');
        if (content) {
            content.style.maxHeight = '';
        }
    }

    function initSidebar() {
        var body = document.body;
        var btn = document.getElementById('sidebarToggleBtn');
        var overlay = document.querySelector('.app-overlay');

        if (btn && document.body.classList.contains('sidebar-open')) {
            document.body.classList.remove('sidebar-open');
        }

        if (overlay) {
            overlay.addEventListener('click', function () {
                body.classList.remove('sidebar-open');
                syncIcon();
            });
        }

        document.querySelectorAll('.sidebar a').forEach(function (a) {
            a.addEventListener('click', function () {
                body.classList.remove('sidebar-open');
                syncIcon();
            });
        });

        var observer = new MutationObserver(function () {
            syncIcon();
        });
        observer.observe(body, { attributes: true, attributeFilter: ['class'] });

        syncIcon();
        fixMobileSidebar();
        window.addEventListener('resize', function () {
            fixMobileSidebar();
            if (window.innerWidth > 767.98) {
                body.classList.remove('sidebar-open');
                syncIcon();
            }
        });
        window.addEventListener('orientationchange', function () {
            setTimeout(fixMobileSidebar, 200);
        });
    }

    // ===== TABEL RESPONSIF MODE KARTU (HP) =====
    // pada layar <768px, tabel diubah jadi kartu agar tidak
    // menggulir horizontal. Label kolom diambil otomatis dari <thead>.
    function applyTableCardMode() {
        var isMobile = window.innerWidth <= 767.98;
        var wrappers = document.querySelectorAll('.table-responsive');

        wrappers.forEach(function (wrap) {
            var table = wrap.querySelector('table');
            if (!table) return;

            var headRow = table.querySelector('thead tr');
            if (!headRow) return;
            var headers = Array.prototype.slice.call(headRow.querySelectorAll('th'));
            if (!headers.length) return;

            // isi data-label tiap sel dari header kolom sesuai indeks
            Array.prototype.forEach.call(table.querySelectorAll('tbody > tr'), function (tr) {
                if (tr.querySelector('input[type="checkbox"].form-check-input') && tr.querySelectorAll('td').length === 1) return;
                var cells = Array.prototype.slice.call(tr.children);
                cells.forEach(function (td, i) {
                    var label = headers[i] ? headers[i].textContent.trim() : '';
                    if (label === '') {
                        td.removeAttribute('data-label');
                        if (td.querySelector('.form-check-input')) {
                            td.classList.add('col-check');
                        }
                    } else {
                        td.setAttribute('data-label', label);
                        td.classList.remove('col-check');
                    }
                });
            });

            wrap.classList.toggle('table-card-mode', isMobile);
        });
    }

    document.addEventListener('DOMContentLoaded', initSidebar);
    document.addEventListener('DOMContentLoaded', applyTableCardMode);
    window.addEventListener('resize', applyTableCardMode);
    window.addEventListener('orientationchange', function () {
        setTimeout(applyTableCardMode, 200);
    });

    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function () {});
        });
    }
})();
