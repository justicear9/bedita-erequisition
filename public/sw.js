/* eRequisition service worker — app shell cache + web push */
const CACHE = 'ereq-shell-v3';
const PRECACHE = [
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
        ).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate' || request.url.includes('/products/search')) {
        event.respondWith(
            fetch(request)
                .then((response) => response)
                .catch(() => caches.match(request)),
        );
        return;
    }

    if (request.url.includes('/build/') || request.url.includes('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                        return response;
                    }),
            ),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {
        title: 'BEDITA eREQUISITION',
        body: 'You have a new update.',
        url: '/dashboard',
        tag: 'erequisition',
    };

    try {
        if (event.data) {
            data = { ...data, ...event.data.json() };
        }
    } catch {
        // keep defaults
    }

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(data.title, {
                body: data.body,
                icon: '/icons/icon-192.png',
                badge: '/icons/icon-192.png',
                tag: data.tag,
                data: { url: data.url },
                renotify: true,
            }),
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
                clients.forEach((client) => {
                    client.postMessage({ type: 'PUSH_RECEIVED', payload: data });
                });
            }),
        ]),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client) {
                    client.navigate?.(target);
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }
            return undefined;
        }),
    );
});
