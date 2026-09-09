const CACHE_NAME = 'glowsuite-pwa-v5';
const STATIC_ASSETS = [
  '/',
  '/icons/favicon-32x32.png',
  '/manifest.json',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
  '/icons/icon-maskable-192.png',
  '/icons/icon-maskable-512.png',
  '/icons/icon-180x180.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) =>
      Promise.allSettled(STATIC_ASSETS.map((url) => cache.add(url).catch(() => undefined)))
    )
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Only intercept GET requests
  if (event.request.method !== 'GET') return;

  // Exclude API requests from SW caching to prevent stale data
  if (event.request.url.includes('/api/')) return;

  // SPA navigation fallback when offline
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        return caches.match('/').then((cachedResponse) => {
          return cachedResponse || Response.error();
        });
      })
    );
    return;
  }

  // Network-first with cache fallback for static assets
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});
