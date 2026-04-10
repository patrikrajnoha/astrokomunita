import { beforeEach, describe, expect, it, vi } from 'vitest'

const authStoreMock = vi.hoisted(() => ({
  bootstrapDone: true,
  waitForBootstrap: vi.fn(async () => null),
}))
const useAuthStoreMock = vi.hoisted(() => vi.fn(() => authStoreMock))
const getActivePiniaMock = vi.hoisted(() => vi.fn(() => ({ __pinia: true })))

vi.mock('pinia', () => ({
  getActivePinia: (...args) => getActivePiniaMock(...args),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: (...args) => useAuthStoreMock(...args),
}))

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
  authStoreMock.bootstrapDone = true
  authStoreMock.waitForBootstrap.mockReset()
  authStoreMock.waitForBootstrap.mockResolvedValue(null)
  useAuthStoreMock.mockClear()
  getActivePiniaMock.mockReset()
  getActivePiniaMock.mockReturnValue({ __pinia: true })
})

describe('request bootstrap gating', () => {
  it('waits for auth bootstrap before sending non-auth requests', async () => {
    authStoreMock.bootstrapDone = false

    const config = await api.interceptors.request.handlers[0].fulfilled({
      url: '/notifications/unread-count',
    })

    expect(useAuthStoreMock).toHaveBeenCalledTimes(1)
    expect(authStoreMock.waitForBootstrap).toHaveBeenCalledTimes(1)
    expect(config).toEqual(expect.objectContaining({
      url: '/notifications/unread-count',
    }))
  })

  it('does not wait for the bootstrap auth/me request itself', async () => {
    authStoreMock.bootstrapDone = false

    await api.interceptors.request.handlers[0].fulfilled({
      url: '/auth/me',
    })

    expect(useAuthStoreMock).not.toHaveBeenCalled()
    expect(authStoreMock.waitForBootstrap).not.toHaveBeenCalled()
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
