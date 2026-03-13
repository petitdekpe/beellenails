const CACHE_NAME = 'beelle-mobile-v1';

// Ressources à mettre en cache au premier chargement
const PRECACHE_URLS = [
    '/mobile',
    '/mobile-manifest.json',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Network-first : on essaie le réseau, on tombe sur le cache si hors ligne
self.addEventListener('fetch', event => {
    // Ne pas intercepter les requêtes non-GET ni les requêtes vers d'autres origines
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith(self.location.origin)) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Mettre en cache les pages /mobile/*
                if (event.request.url.includes('/mobile')) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
