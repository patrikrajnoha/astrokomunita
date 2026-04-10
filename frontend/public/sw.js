const CACHE_NAME = 'astrokomunita-shell-v3'
const APP_SHELL = ['/', '/index.html', '/manifest.webmanifest', '/favicon.ico']
const NETWORK_ONLY_HOSTS = new Set(['api.astrokomunita.sk'])
const NETWORK_ONLY_PATH_PREFIXES = ['/api/', '/sanctum/', '/broadcasting/']

function shouldBypassRequest(url) {
  if (NETWORK_ONLY_HOSTS.has(url.hostname)) return true
  return NETWORK_ONLY_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))
}

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

  // Keep API and auth traffic always network-only and uncached.
  if (shouldBypassRequest(url)) return

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

  // Navigation should be network-first so users see freshly deployed bundles.
  if (isShellNavigation) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200 && response.type === 'basic') {
            const cloned = response.clone()
            caches.open(CACHE_NAME).then((cache) => cache.put('/index.html', cloned)).catch(() => {})
          }
          return response
        })
        .catch(() => caches.match('/index.html'))
    )
    return
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached

      return fetch(request).then((response) => {
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response
        }

        const cloned = response.clone()
        caches.open(CACHE_NAME).then((cache) => cache.put(request, cloned)).catch(() => {})
        return response
      })
    })
  )
})
