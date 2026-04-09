import { describe, expect, it, vi } from 'vitest'
import { shouldRedirectToLogin } from '@/services/api'

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
