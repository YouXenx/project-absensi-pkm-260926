/*
 * Service worker of the PWA. Deliberately small:
 *
 *  - Pages are always fetched from the network and never stored: they contain attendance and student data,
 *    and a copy left on a shared phone would be readable after logout. Without a connection the user gets
 *    the offline page instead.
 *  - Built assets (/build/assets/*) have a content hash in their name, so they are served from the cache
 *    and only downloaded once.
 *  - Everything else (form posts, DataTables requests, other origins) is left alone.
 *
 * Bump VERSION to throw away the caches of an earlier release.
 */
const VERSION = 'v2';
const CACHE = `absensi-${VERSION}`;
const OFFLINE_URL = '/offline';
const PRECACHE = [OFFLINE_URL, '/images/pwa/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));

        return;
    }

    // What the offline page needs (its logo) comes from the cache, so the page is complete without a connection.
    if (PRECACHE.includes(url.pathname)) {
        event.respondWith(caches.match(request).then((cached) => cached ?? fetch(request)));

        return;
    }

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();

                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );
    }
});
