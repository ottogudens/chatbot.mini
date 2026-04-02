const CACHE_NAME = 'skale-chat-v2';
const SHELL_ASSETS = [
    '/',
    '/index.php',
    '/style.css',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png'
];

// Install: cache the app shell
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) { return cache.addAll(SHELL_ASSETS); })
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(keys.filter(function(k) { return k !== CACHE_NAME; }).map(function(k) { return caches.delete(k); }));
        })
    );
    self.clients.claim();
});

// Fetch: network-first for API calls, cache-first for static assets
self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);

    // Always bypass cache for API/PHP calls
    if (url.pathname.indexOf('.php') !== -1 || url.pathname.indexOf('api') !== -1) {
        return; // Let the browser handle normally
    }

    // Cache-first for static assets
    event.respondWith(
        caches.match(event.request).then(function(cached) {
            if (cached) return cached;
            return fetch(event.request).then(function(response) {
                if (response && response.status === 200) {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) { cache.put(event.request, clone); });
                }
                return response;
            });
        }).catch(function() { return caches.match('/index.php'); })
    );
});

