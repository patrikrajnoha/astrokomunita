import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'

const refreshCsrfCookieMock = vi.hoisted(() => vi.fn(async () => ({ data: {} })))
const clearHomeFeedPrefetchMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    defaults: {
      baseURL: 'http://127.0.0.1:8000/api',
      headers: {
        common: {},
      },
    },
  },
  refreshCsrfCookie: (...args) => refreshCsrfCookieMock(...args),
}))

vi.mock('@/services/feedPrefetch', () => ({
  clearHomeFeedPrefetch: clearHomeFeedPrefetchMock,
}))

async function flushPromises() {
  await Promise.resolve()
  await Promise.resolve()
}

describe('auth store login resilience', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const store = useAuthStore()
    store.reset()
    store.bootstrapDone = false
    store.initialized = false
    store.status = 'idle'
    store.loading = false
    store.user = null
    store.error = null
    clearHomeFeedPrefetchMock.mockClear()
    refreshCsrfCookieMock.mockClear()
    http.post.mockReset()
    http.get.mockReset()
    http.defaults.headers.common = {}
  })

  it('keeps authenticated user after login without triggering auth refresh', async () => {
    const store = useAuthStore()

    http.post.mockResolvedValueOnce({
      data: { id: 7, name: 'Admin', role: 'admin' },
    })

    await store.login({
      email: 'admin@example.com',
      password: 'secret',
    })

    await flushPromises()

    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 7, role: 'admin' }))
    expect(store.status).toBe('authenticated')
    expect(store.error).toBeNull()
    expect(http.post).toHaveBeenCalledWith('/auth/login', {
      email: 'admin@example.com',
      password: 'secret',
      remember: true,
    }, { meta: { skipErrorToast: true } })
    expect(refreshCsrfCookieMock).toHaveBeenCalledTimes(1)
    expect(http.get).not.toHaveBeenCalled()
  })

  it('keeps authenticated user after register without triggering auth refresh', async () => {
    const store = useAuthStore()

    http.post.mockResolvedValueOnce({
      data: { id: 17, name: 'New User', role: 'member' },
    })

    await store.register({
      email: 'new@example.com',
      password: 'secret',
    })

    await flushPromises()

    expect(refreshCsrfCookieMock).toHaveBeenCalledTimes(1)
    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 17 }))
    expect(store.status).toBe('authenticated')
    expect(store.error).toBeNull()
    expect(http.get).not.toHaveBeenCalled()
  })

  it('keeps local logout cleanup when backend logout returns 401', async () => {
    const store = useAuthStore()
    store.user = { id: 5, name: 'Tester' }
    store.status = 'authenticated'
    store.bootstrapDone = true
    store.initialized = true

    http.post.mockRejectedValueOnce({
      response: {
        status: 401,
        data: { message: 'Unauthenticated.' },
      },
    })

    await store.logout()

    expect(refreshCsrfCookieMock).toHaveBeenCalledTimes(1)
    expect(http.post).toHaveBeenCalledWith('/auth/logout')
    expect(clearHomeFeedPrefetchMock).toHaveBeenCalled()
    expect(store.isAuthed).toBe(false)
    expect(store.user).toBeNull()
    expect(store.status).toBe('guest')
    expect(store.error).toBeNull()
  })

  it('still clears auth state for regular fetchUser unauthorized failures', async () => {
    const store = useAuthStore()
    store.user = { id: 5, name: 'Tester' }
    store.status = 'authenticated'

    http.get.mockRejectedValueOnce({
      response: {
        status: 401,
        data: { message: 'Unauthenticated.' },
      },
    })

    const data = await store.fetchUser({ source: 'manual', retry: false, markBootstrap: true })

    expect(data).toBeNull()
    expect(store.isAuthed).toBe(false)
    expect(store.user).toBeNull()
    expect(store.status).toBe('guest')
    expect(store.error?.type).toBe('unauthorized')
  })

  it('keeps authenticated state for profile-save unauthorized failures', async () => {
    const store = useAuthStore()
    store.user = { id: 5, name: 'Tester' }
    store.status = 'authenticated'

    http.get.mockRejectedValueOnce({
      response: {
        status: 401,
        data: { message: 'Unauthenticated.' },
      },
    })

    const data = await store.fetchUser({
      source: 'profile-save',
      retry: false,
      markBootstrap: true,
      preserveStateOnError: true,
    })

    expect(data).toEqual(expect.objectContaining({ id: 5 }))
    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 5 }))
    expect(store.status).toBe('authenticated')
    expect(store.error).toBeNull()
  })

  it('keeps authenticated state for preferences-save unauthorized failures', async () => {
    const store = useAuthStore()
    store.user = { id: 9, name: 'Observer' }
    store.status = 'authenticated'

    http.get.mockRejectedValueOnce({
      response: {
        status: 401,
        data: { message: 'Unauthenticated.' },
      },
    })

    const data = await store.fetchUser({
      source: 'preferences-save',
      retry: false,
      markBootstrap: true,
      preserveStateOnError: true,
    })

    expect(data).toEqual(expect.objectContaining({ id: 9 }))
    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 9 }))
    expect(store.status).toBe('authenticated')
    expect(store.error).toBeNull()
  })

  it('uses auth-aware fetchUser request flags for /me', async () => {
    const store = useAuthStore()

    http.get.mockResolvedValueOnce({
      data: { id: 8, name: 'Scoped User' },
    })

    await store.fetchUser({ source: 'manual', retry: false, markBootstrap: true })

    expect(http.get).toHaveBeenCalledWith(
      '/me',
      expect.objectContaining({
        withCredentials: true,
        meta: expect.objectContaining({
          skipErrorToast: true,
          skipAuthRedirect: true,
        }),
      }),
    )
  })

  it('treats empty /me payload as guest', async () => {
    const store = useAuthStore()

    http.get.mockResolvedValueOnce({
      data: null,
    })

    const data = await store.fetchUser({ source: 'manual', retry: false, markBootstrap: true })

    expect(data).toBeNull()
    expect(store.isAuthed).toBe(false)
    expect(store.user).toBeNull()
    expect(store.status).toBe('guest')
    expect(store.error).toBeNull()
  })

  it('keeps authenticated state for preferences-save empty /me payloads', async () => {
    const store = useAuthStore()
    store.user = { id: 12, name: 'Sky User' }
    store.status = 'authenticated'

    http.get.mockResolvedValueOnce({
      data: null,
    })

    const data = await store.fetchUser({
      source: 'preferences-save',
      retry: false,
      markBootstrap: true,
      preserveStateOnError: true,
    })

    expect(data).toEqual(expect.objectContaining({ id: 12 }))
    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 12 }))
    expect(store.status).toBe('authenticated')
    expect(store.error).toBeNull()
  })

  it('keeps authenticated state for transient fetchUser server failures', async () => {
    const store = useAuthStore()
    store.user = { id: 5, name: 'Tester', role: 'admin' }
    store.status = 'authenticated'

    http.get.mockRejectedValueOnce({
      response: {
        status: 500,
        data: { message: 'Server error' },
      },
    })

    const data = await store.fetchUser({ source: 'manual', retry: false, markBootstrap: true })

    expect(data).toEqual(expect.objectContaining({ id: 5 }))
    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 5 }))
    expect(store.status).toBe('authenticated')
    expect(store.error).toBeNull()
  })

  it('dedupes concurrent bootstrapAuth requests onto a single /me call', async () => {
    const store = useAuthStore()
    let resolveRequest

    http.get.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveRequest = resolve
        }),
    )

    const first = store.bootstrapAuth()
    const second = store.bootstrapAuth()

    await vi.waitFor(() => {
      expect(http.get).toHaveBeenCalledTimes(1)
    })

    resolveRequest({
      data: { id: 42, name: 'Sky User' },
    })

    await expect(first).resolves.toEqual(expect.objectContaining({ id: 42 }))
    await expect(second).resolves.toEqual(expect.objectContaining({ id: 42 }))
    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 42 }))
  })

  it('exposes the in-flight bootstrap promise for other callers to await', async () => {
    const store = useAuthStore()
    let resolveRequest

    http.get.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveRequest = resolve
        }),
    )

    const bootstrapPromise = store.bootstrapAuth()

    expect(store.bootstrapPromise).toBeTruthy()
    const waitedBootstrap = store.waitForBootstrap()

    await vi.waitFor(() => {
      expect(http.get).toHaveBeenCalledTimes(1)
    })

    resolveRequest({
      data: { id: 77, name: 'Deferred User' },
    })

    await expect(bootstrapPromise).resolves.toEqual(expect.objectContaining({ id: 77 }))
    await expect(waitedBootstrap).resolves.toEqual(expect.objectContaining({ id: 77 }))
  })

  it('refreshes csrf before bootstrap /me fetch', async () => {
    const store = useAuthStore()
    const callOrder = []

    refreshCsrfCookieMock.mockImplementationOnce(async () => {
      callOrder.push('csrf')
      return { data: {} }
    })

    http.get.mockImplementationOnce(async () => {
      callOrder.push('auth-me')
      return { data: { id: 21, name: 'Bootstrap User' } }
    })

    await store.bootstrapAuth()

    expect(callOrder).toEqual(['csrf', 'auth-me'])
  })

  it('continues bootstrap auth even when csrf refresh fails', async () => {
    const store = useAuthStore()

    refreshCsrfCookieMock.mockRejectedValueOnce(new Error('csrf failed'))
    http.get.mockResolvedValueOnce({
      data: { id: 22, name: 'Fallback User' },
    })

    await expect(store.bootstrapAuth()).resolves.toEqual(expect.objectContaining({ id: 22 }))
    expect(http.get).toHaveBeenCalledTimes(1)
    expect(store.isAuthed).toBe(true)
  })

  it('ignores stale bootstrap failure after a successful login', async () => {
    const store = useAuthStore()
    let rejectBootstrapRequest

    http.get.mockImplementationOnce(
      () =>
        new Promise((_, reject) => {
          rejectBootstrapRequest = reject
        }),
    )

    http.post.mockResolvedValueOnce({
      data: { id: 11, name: 'Sky User' },
    })

    const bootstrapPromise = store.bootstrapAuth()
    await vi.waitFor(() => {
      expect(http.get).toHaveBeenCalledTimes(1)
    })

    await store.login({
      email: 'sky@example.com',
      password: 'secret',
    })

    rejectBootstrapRequest({
      response: {
        status: 401,
        data: { message: 'Unauthenticated.' },
      },
    })

    await expect(bootstrapPromise).resolves.toEqual(expect.objectContaining({ id: 11 }))
    await flushPromises()

    expect(store.isAuthed).toBe(true)
    expect(store.user).toEqual(expect.objectContaining({ id: 11 }))
    expect(store.status).toBe('authenticated')
    expect(store.error).toBeNull()
  })
})
