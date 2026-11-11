/**
 * Rabindra Maitree University (RMU) Service Worker
 * Security Rule: Strictly caches public static assets and the offline shell ONLY.
 * Authenticated portals, admin routes, admission/payment endpoints, and POST forms
 * always bypass CacheStorage directly to the live MySQL-backed PHP server.
 */
'use strict';

const CACHE_NAME = 'rmu-pwa-v1';
const SCOPE_URL = new URL('./', self.registration.scope);
const OFFLINE_URL = new URL('offline.php', SCOPE_URL).href;

const PRECACHE_PATHS = [
  'assets/css/style.css',
  'assets/css/responsive.css',
  'assets/js/main.js',
  'assets/js/validation.js',
  'assets/js/pwa.js',
  'assets/images/logo.jpg',
  'assets/images/logo.svg',
  'assets/pwa/icon-192.png',
  'assets/pwa/icon-512.png',
  'offline.php'
];

function isSensitiveOrDynamicPath(relPath) {
  const clean = relPath.replace(/^\/+/, '').toLowerCase();
  if (
    clean.startsWith('student/') ||
    clean === 'student' ||
    clean.startsWith('admin/') ||
    clean === 'admin' ||
    clean.startsWith('actions/') ||
    clean.startsWith('tests/') ||
    clean.startsWith('storage/') ||
    clean.startsWith('admission-status.php') ||
    clean.startsWith('admission-document.php') ||
    clean.startsWith('admission-payment.php') ||
    clean.startsWith('sandbox-gateway.php') ||
    clean.startsWith('verify-application.php') ||
    clean.startsWith('manifest.php') ||
    clean === 'sw.js'
  ) {
    return true;
  }
  return false;
}

function isStaticAsset(relPath) {
  const clean = relPath.replace(/^\/+/, '').toLowerCase();
  return (
    clean.startsWith('assets/css/') ||
    clean.startsWith('assets/js/') ||
    clean.startsWith('assets/images/') ||
    clean.startsWith('assets/pwa/')
  );
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      const requests = PRECACHE_PATHS.map((p) => new URL(p, SCOPE_URL).href);
      await Promise.all(
        requests.map((url) =>
          fetch(url, { cache: 'reload' })
            .then((res) => {
              if (res && res.ok) {
                return cache.put(url, res);
              }
            })
            .catch(() => null)
        )
      );
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((k) => k.startsWith('rmu-pwa-') && k !== CACHE_NAME)
            .map((k) => caches.delete(k))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  let url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return;
  }

  if (url.origin !== self.location.origin) {
    return;
  }

  const scopePath = SCOPE_URL.pathname;
  const relPath = url.pathname.startsWith(scopePath)
    ? url.pathname.slice(scopePath.length)
    : url.pathname.replace(/^\/+/, '');

  if (isSensitiveOrDynamicPath(relPath)) {
    return;
  }

  if (isStaticAsset(relPath)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) {
          return cached;
        }
        return fetch(request).then((networkRes) => {
          if (networkRes && networkRes.ok && networkRes.type === 'basic') {
            const copy = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => null);
          }
          return networkRes;
        });
      })
    );
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match(OFFLINE_URL).then((cachedOffline) => {
          if (cachedOffline) {
            return cachedOffline;
          }
          return new Response('You are currently offline.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=UTF-8' }
          });
        })
      )
    );
  }
});
