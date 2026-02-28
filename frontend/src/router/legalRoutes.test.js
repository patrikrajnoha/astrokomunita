import { describe, expect, it, vi } from 'vitest'

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    bootstrapDone: true,
    status: 'guest',
    loading: false,
    isAuthed: false,
    isAdmin: false,
    user: null,
    bootstrapAuth: vi.fn(),
  }),
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => ({
    loaded: true,
    loading: false,
    isOnboardingCompleted: true,
    fetchPreferences: vi.fn(),
  }),
}))

import router from './index'

describe('legal routes', () => {
  it('registers public legal pages on the real router', () => {
    const paths = router.getRoutes().map((route) => route.path)

    expect(paths).toContain('/privacy')
    expect(paths).toContain('/terms')
    expect(paths).toContain('/cookies')
  })
})
