import { beforeEach, describe, expect, it, vi } from 'vitest'
import { clearPreloadRecoveryState, installPreloadRecovery } from '@/bootstrap/preloadRecovery'

function createStorage() {
  const store = new Map()

  return {
    getItem: vi.fn((key) => (store.has(key) ? store.get(key) : null)),
    setItem: vi.fn((key, value) => {
      store.set(key, String(value))
    }),
    removeItem: vi.fn((key) => {
      store.delete(key)
    }),
  }
}

function createFakeWindow() {
  const listeners = new Map()

  return {
    sessionStorage: createStorage(),
    location: {
      reload: vi.fn(),
    },
    addEventListener: vi.fn((type, listener) => {
      listeners.set(type, listener)
    }),
    removeEventListener: vi.fn((type, listener) => {
      if (listeners.get(type) === listener) {
        listeners.delete(type)
      }
    }),
    dispatchPreloadError(payload = new Error('Failed to fetch dynamically imported module')) {
      const listener = listeners.get('vite:preloadError')
      const event = {
        payload,
        preventDefault: vi.fn(),
      }

      listener?.(event)

      return event
    },
  }
}

describe('preload recovery', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(console, 'warn').mockImplementation(() => {})
  })

  it('reloads once when Vite reports a preload error', () => {
    const fakeWindow = createFakeWindow()
    const cleanup = installPreloadRecovery(fakeWindow)

    const event = fakeWindow.dispatchPreloadError()

    expect(event.preventDefault).toHaveBeenCalledTimes(1)
    expect(fakeWindow.location.reload).toHaveBeenCalledTimes(1)
    expect(fakeWindow.sessionStorage.setItem).toHaveBeenCalledTimes(1)

    cleanup()
  })

  it('does not reload repeatedly once recovery was already attempted', () => {
    const fakeWindow = createFakeWindow()
    installPreloadRecovery(fakeWindow)

    fakeWindow.dispatchPreloadError()
    fakeWindow.dispatchPreloadError()

    expect(fakeWindow.location.reload).toHaveBeenCalledTimes(1)
  })

  it('allows a future recovery attempt after bootstrap clears the marker', () => {
    const fakeWindow = createFakeWindow()
    installPreloadRecovery(fakeWindow)

    fakeWindow.dispatchPreloadError()
    clearPreloadRecoveryState(fakeWindow)
    fakeWindow.dispatchPreloadError(new Error('Importing a module script failed.'))

    expect(fakeWindow.location.reload).toHaveBeenCalledTimes(2)
    expect(fakeWindow.sessionStorage.removeItem).toHaveBeenCalledTimes(1)
  })
})
