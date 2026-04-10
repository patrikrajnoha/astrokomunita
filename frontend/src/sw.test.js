import { beforeEach, describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import vm from 'node:vm'

function loadServiceWorker() {
  const listeners = new Map()
  const context = {
    URL,
    caches: {
      open: vi.fn(async () => ({ addAll: vi.fn(), put: vi.fn() })),
      keys: vi.fn(async () => []),
      delete: vi.fn(async () => true),
      match: vi.fn(async () => undefined),
    },
    fetch: vi.fn(),
    Promise,
    self: {
      location: { origin: 'https://astrokomunita.sk' },
      addEventListener: vi.fn((type, listener) => {
        listeners.set(type, listener)
      }),
      skipWaiting: vi.fn(),
      clients: {
        claim: vi.fn(),
      },
    },
  }

  context.globalThis = context

  const swPath = resolve(process.cwd(), 'public/sw.js')
  const source = readFileSync(swPath, 'utf8')
  vm.runInNewContext(source, context, { filename: swPath })

  return {
    listeners,
    context,
  }
}

function createFetchEvent(url, options = {}) {
  return {
    request: {
      method: options.method || 'GET',
      mode: options.mode || 'cors',
      destination: options.destination || '',
      url,
    },
    respondWith: vi.fn(),
  }
}

describe('custom service worker fetch handling', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('bypasses api.astrokomunita.sk requests entirely', () => {
    const { listeners } = loadServiceWorker()
    const fetchHandler = listeners.get('fetch')
    const event = createFetchEvent('https://api.astrokomunita.sk/api/auth/me')

    fetchHandler(event)

    expect(event.respondWith).not.toHaveBeenCalled()
  })

  it('bypasses same-origin auth and api paths', () => {
    const { listeners } = loadServiceWorker()
    const fetchHandler = listeners.get('fetch')

    const apiEvent = createFetchEvent('https://astrokomunita.sk/api/auth/me')
    const sanctumEvent = createFetchEvent('https://astrokomunita.sk/sanctum/csrf-cookie')
    const broadcastingEvent = createFetchEvent('https://astrokomunita.sk/broadcasting/auth')

    fetchHandler(apiEvent)
    fetchHandler(sanctumEvent)
    fetchHandler(broadcastingEvent)

    expect(apiEvent.respondWith).not.toHaveBeenCalled()
    expect(sanctumEvent.respondWith).not.toHaveBeenCalled()
    expect(broadcastingEvent.respondWith).not.toHaveBeenCalled()
  })
})
