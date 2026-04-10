import { beforeEach, describe, expect, it, vi } from 'vitest'
import api, { shouldRedirectToLogin } from '@/services/api'

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
  vi.restoreAllMocks()
})

describe('request interceptor', () => {
  it('extends timeout for long-running admin jobs', async () => {
    const config = await api.interceptors.request.handlers[0].fulfilled({
      url: '/admin/event-sources/run',
      timeout: 15000,
    })

    expect(config).toEqual(expect.objectContaining({
      url: '/admin/event-sources/run',
      timeout: 300000,
    }))
  })

  it('extends timeout for slow sky widgets', async () => {
    const config = await api.interceptors.request.handlers[0].fulfilled({
      url: '/sky/moon-overview',
      timeout: 15000,
    })

    expect(config).toEqual(expect.objectContaining({
      url: '/sky/moon-overview',
      timeout: 45000,
    }))
  })

  it('leaves default timeout for regular requests', async () => {
    const config = await api.interceptors.request.handlers[0].fulfilled({
      url: '/notifications/unread-count',
      timeout: 15000,
    })

    expect(config).toEqual(expect.objectContaining({
      url: '/notifications/unread-count',
      timeout: 15000,
    }))
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
