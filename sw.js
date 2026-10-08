const CACHE_NAME = 'shandev-v3';
const CORE_ASSETS = [
    './',
    './login.php',
    './404.php',
    './assets/images/favicon/favicon.png',
    './assets/images/favicon/icon-192.png',
    './assets/images/favicon/icon-512.png',
    './manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(CORE_ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET') return;
    if (!url.origin.includes('shandev')) return;
    // Jangan cache endpoint API push (harus selalu fresh)
    if (url.pathname.includes('push_api.php')) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                return response;
            })
            .catch(() => caches.match(event.request).then((cached) => cached || caches.match('./404.php')))
    );
});

// ===== PUSH RECEIVED =====
self.addEventListener('push', (event) => {
    let data = { title: 'Shankara Dev', body: 'Ada pengingat baru.', url: './pengingat.php' };
    try {
        const parsed = event.data ? JSON.parse(event.data.text()) : {};
        if (parsed && typeof parsed === 'object') {
            data.title = parsed.title || data.title;
            data.body = parsed.body || data.body;
            data.url = parsed.url || data.url;
            data.tag = parsed.tag || 'shandev-reminder';
        }
    } catch (e) {
        const text = event.data ? event.data.text() : '';
        if (text) data.body = text;
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: './assets/images/favicon/icon-192.png',
            badge: './assets/images/favicon/favicon.png',
            data: { url: data.url },
            tag: data.tag,
            renotify: false
        })
    );
});

// ===== NOTIFICATION CLICK =====
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || './';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes('shandev') && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            return clients.openWindow(targetUrl);
        })
    );
});
