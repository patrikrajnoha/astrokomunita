const CACHE_NAME = 'astrokomunita-shell-v1'
const APP_SHELL = ['/', '/index.html', '/manifest.webmanifest', '/favicon.ico']

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).catch(() => {})
  )
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(keys.map((key) => (key === CACHE_NAME ? Promise.resolve() : caches.delete(key))))
      )
  )
  self.clients.claim()
})

self.addEventListener('fetch', (event) => {
  const { request } = event

  if (request.method !== 'GET') return

  const url = new URL(request.url)

  // Keep API always network-first and uncached to avoid stale data.
  if (url.pathname.startsWith('/api/')) return

  // Only handle same-origin requests and static assets.
  if (url.origin !== self.location.origin) return

  const isStaticAsset =
    request.destination === 'script' ||
    request.destination === 'style' ||
    request.destination === 'image' ||
    request.destination === 'font' ||
    url.pathname.startsWith('/assets/')

  const isShellNavigation = request.mode === 'navigate'

  if (!isStaticAsset && !isShellNavigation) return

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached

      return fetch(request)
        .then((response) => {
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response
          }

          const cloned = response.clone()
          caches.open(CACHE_NAME).then((cache) => cache.put(request, cloned)).catch(() => {})
          return response
        })
        .catch(() => {
          if (isShellNavigation) {
            return caches.match('/index.html')
          }
          return undefined
        })
    })
  )
})
