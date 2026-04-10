import { beforeEach, describe, expect, it, vi } from 'vitest'
import api, { setBootstrapPromise, shouldRedirectToLogin } from '@/services/api'

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    warn: vi.fn(),
    error: vi.fn(),
    success: vi.fn(),
    info: vi.fn(),
  }),
}))

function makeError(config = {}) {
  return {
    config,
    response: {
      status: 401,
      data: { message: 'Unauthenticated.' },
    },
  }
}

beforeEach(() => {
  setBootstrapPromise(null)
})

describe('request bootstrap gating', () => {
  it('waits for auth bootstrap before sending non-auth requests', async () => {
    let resolveBootstrap
    let interceptorReleased = false
    const bootstrapPromise = new Promise((resolve) => {
      resolveBootstrap = resolve
    })
    setBootstrapPromise(bootstrapPromise)

    const pendingConfig = api.interceptors.request.handlers[0].fulfilled({
      url: '/notifications/unread-count',
    })
      .then((config) => {
        interceptorReleased = true
        return config
      })

    await Promise.resolve()

    expect(interceptorReleased).toBe(false)

    resolveBootstrap()

    const config = await pendingConfig

    expect(config).toEqual(expect.objectContaining({
      url: '/notifications/unread-count',
    }))
  })

  it('does not wait for the bootstrap auth/me request itself', async () => {
    let resolveBootstrap
    const bootstrapPromise = new Promise((resolve) => {
      resolveBootstrap = resolve
    })
    setBootstrapPromise(bootstrapPromise)

    const config = await api.interceptors.request.handlers[0].fulfilled({
      url: '/auth/me',
    })

    expect(config).toEqual(expect.objectContaining({
      url: '/auth/me',
    }))

    resolveBootstrap()
    await bootstrapPromise
  })

  it('does not wait for csrf cookie requests', async () => {
    let resolveBootstrap
    const bootstrapPromise = new Promise((resolve) => {
      resolveBootstrap = resolve
    })
    setBootstrapPromise(bootstrapPromise)

    const config = await api.interceptors.request.handlers[0].fulfilled({
      url: '/sanctum/csrf-cookie',
    })

    expect(config).toEqual(expect.objectContaining({
      url: '/sanctum/csrf-cookie',
    }))

    resolveBootstrap()
    await bootstrapPromise
  })
})

describe('shouldRedirectToLogin', () => {
  it('does not redirect for background requests marked with skipAuthRedirect', () => {
    expect(shouldRedirectToLogin(makeError({
      url: '/notifications/unread-count',
      meta: { skipAuthRedirect: true },
    }))).toBe(false)

    expect(shouldRedirectToLogin(makeError({
      url: '/me/preferences',
      meta: { requiresAuth: true, skipAuthRedirect: true },
    }))).toBe(false)

    expect(shouldRedirectToLogin(makeError({
      url: '/posts',
      meta: { skipAuthRedirect: true },
      params: { scope: 'me', kind: 'roots', per_page: 1 },
    }))).toBe(false)
  })

  it('redirects for auth bootstrap requests when redirect suppression is not set', () => {
    expect(shouldRedirectToLogin(makeError({
      url: '/auth/me',
      meta: { skipErrorToast: true },
    }))).toBe(true)
  })

  it('redirects for explicit requiresAuth requests', () => {
    expect(shouldRedirectToLogin(makeError({
      url: '/notification-preferences',
      meta: { requiresAuth: true },
    }))).toBe(true)
  })
})
