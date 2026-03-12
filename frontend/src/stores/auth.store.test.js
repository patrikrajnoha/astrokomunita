import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'
import axios from 'axios'

const csrfGetMock = vi.hoisted(() => vi.fn(async () => ({ data: {} })))
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
}))

vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => ({
      get: csrfGetMock,
    })),
  },
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
    clearHomeFeedPrefetchMock.mockClear()
    csrfGetMock.mockClear()
    http.post.mockReset()
    http.get.mockReset()
    http.defaults.headers.common = {}
    axios.create.mockClear()
  })

  it('keeps authenticated user when login background refresh fails with 401', async () => {
    const store = useAuthStore()

    http.post.mockResolvedValueOnce({
      data: { id: 7, name: 'Admin', role: 'admin' },
    })

    http.get.mockRejectedValueOnce({
      response: {
        status: 401,
        data: { message: 'Unauthenticated.' },
      },
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
    })
    expect(http.get).toHaveBeenCalledWith('/auth/me', expect.any(Object))
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
})
