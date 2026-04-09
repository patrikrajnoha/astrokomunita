import { beforeEach, describe, expect, it, vi } from 'vitest'
import api from '@/services/api'
import { getMyPreferences } from '@/services/events'
import { listObservations } from '@/services/observations'
import { clearStatsCache, getStats } from '@/services/api/admin/stats'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    put: vi.fn(),
  },
}))

describe('background auth request metadata', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    clearStatsCache()
    api.get.mockResolvedValue({ data: {} })
  })

  it('marks preferences fetch as a non-redirect auth failure', async () => {
    await getMyPreferences()

    expect(api.get).toHaveBeenCalledWith('/me/preferences', {
      meta: { requiresAuth: true, skipAuthRedirect: true },
    })
  })

  it('marks mine observations fetch as a non-redirect auth failure', async () => {
    await listObservations({ mine: 1, page: 1, per_page: 10 })

    expect(api.get).toHaveBeenCalledWith('/observations', {
      params: { mine: 1, page: 1, per_page: 10 },
      meta: { requiresAuth: true, skipAuthRedirect: true },
    })
  })

  it('marks admin stats fetch as a non-redirect auth failure', async () => {
    await getStats({ force: true })

    expect(api.get).toHaveBeenCalledWith('/admin/stats', {
      meta: { skipErrorToast: true, skipAuthRedirect: true },
    })
  })
})
