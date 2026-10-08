/* push_client.js - Subscribe/unsubscribe Web Push + aktifkan notifikasi */
(function () {
    'use strict';

    // SW scope harus di root agar mengontrol seluruh halaman.
    const SW_PATH = 'sw.js';
    const API_PATH = 'push_api.php';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const buf = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) buf[i] = raw.charCodeAt(i);
        return buf;
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function api(action, payload) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken());
        if (payload) Object.keys(payload).forEach((k) => fd.append(k, payload[k]));
        return fetch(API_PATH + '?a=' + action, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then((r) => r.json().catch(() => ({ success: false, message: 'Respon tidak valid' })));
    }

    function getVapidPub() {
        return fetch(API_PATH + '?a=key', { credentials: 'same-origin' })
            .then((r) => r.json())
            .then((d) => (d && d.success && d.vapid_pub) ? d.vapid_pub : null);
    }

    async function doSubscribe() {
        const reg = await navigator.serviceWorker.register(SW_PATH);
        await navigator.serviceWorker.ready;
        const vapid = await getVapidPub();
        if (!vapid) throw new Error('VAPID key tidak tersedia.');
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid)
        });
        const data = sub.toJSON();
        const res = await api('subscribe', {
            endpoint: data.endpoint,
            p256dh: data.keys.p256dh,
            auth: data.keys.auth
        });
        if (!res.success) throw new Error(res.message || 'Gagal menyimpan subscription.');
        return res;
    }

    async function doUnsubscribe() {
        const reg = await navigator.serviceWorker.getRegistration();
        if (!reg) return { success: true, message: 'Belum ada subscription.' };
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            const endpoint = sub.endpoint;
            await sub.unsubscribe();
            await api('unsubscribe', { endpoint });
        }
        return { success: true, message: 'Notifikasi dimatikan.' };
    }

    async function sendTest() {
        return api('test', {});
    }

    function isSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    function setupButtons(scope) {
        if (!scope) return;
        const btnOn = scope.querySelectorAll('[data-push-subscribe]');
        const btnOff = scope.querySelectorAll('[data-push-unsubscribe]');
        const btnTest = scope.querySelectorAll('[data-push-test]');
        const logs = scope.querySelectorAll('[data-push-status]');

        function show(msg, isError) {
            logs.forEach((el) => {
                el.textContent = msg;
                el.classList.toggle('text-danger', !!isError);
                el.classList.toggle('text-success', !isError);
            });
        }

        btnOn.forEach((el) => {
            el.addEventListener('click', () => {
                if (!isSupported()) { show('Browser tidak mendukung Web Push.', true); return; }
                if (!('Notification' in window)) { show('Browser tidak mendukung notifikasi.', true); return; }
                Notification.requestPermission().then((perm) => {
                    if (perm !== 'granted') { show('Izin notifikasi ditolak.', true); return; }
                    doSubscribe().then((res) => {
                        show(res.message || 'Notifikasi berhasil diaktifkan.');
                        setBtnState(scope, true);
                    }).catch((err) => show((err && err.message) || 'Gagal mengaktifkan notifikasi.', true));
                });
            });
        });

        btnOff.forEach((el) => {
            el.addEventListener('click', () => {
                doUnsubscribe().then((res) => {
                    show(res.message || 'Notifikasi dimatikan.');
                    setBtnState(scope, false);
                }).catch((err) => show((err && err.message) || 'Gagal mematikan notifikasi.', true));
            });
        });

        btnTest.forEach((el) => {
            el.addEventListener('click', () => {
                sendTest().then((res) => {
                    show(res.message || 'Selesai.');
                }).catch(() => show('Gagal mengirim tes.', true));
            });
        });
    }

    function setBtnState(scope, subscribed) {
        const btnOn = scope.querySelectorAll('[data-push-subscribe]');
        const btnOff = scope.querySelectorAll('[data-push-unsubscribe]');
        btnOn.forEach((el) => { el.classList.toggle('d-none', !!subscribed); el.classList.toggle('d-inline-flex', !subscribed); });
        btnOff.forEach((el) => { el.classList.toggle('d-none', !subscribed); el.classList.toggle('d-inline-flex', !!subscribed); });
    }

    // Inisialisasi global: pertahankan SW tetap hidup & tampilkan status tombol.
    function init() {
        if (!isSupported()) return;
        if (location.protocol === 'http:' && !['localhost', '127.0.0.1'].includes(location.hostname)) return;

        navigator.serviceWorker.register(SW_PATH).catch(function () {});

        document.querySelectorAll('[data-push-area]').forEach((scope) => {
            setupButtons(scope);
            navigator.serviceWorker.ready.then((reg) => reg.pushManager.getSubscription()).then((sub) => {
                setBtnState(scope, !!sub);
            }).catch(() => {});
        });

        // Jika user sudah pernah ijinkan dulu, pastikan tersimpan (perbaiki bila endpoint berubah).
        if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
            navigator.serviceWorker.ready.then((reg) => reg.pushManager.getSubscription()).then((sub) => {
                if (!sub) return;
                const data = sub.toJSON();
                api('subscribe', { endpoint: data.endpoint, p256dh: data.keys.p256dh, auth: data.keys.auth }).catch(() => {});
            }).catch(() => {});
        }
    }

    // Ekspor API global untuk pemakaian halaman.
    window.ShankaraPush = { init, doSubscribe, doUnsubscribe, sendTest, isSupported };
    document.addEventListener('DOMContentLoaded', init);
})();