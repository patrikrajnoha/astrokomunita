import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useProfileContentTabs } from './useProfileContentTabs'

const listObservationsMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/observations', () => ({
  listObservations: (...args) => listObservationsMock(...args),
}))

describe('useProfileContentTabs', () => {
  let auth
  let http
  let eventFollows
  let confirm

  beforeEach(() => {
    vi.clearAllMocks()

    auth = {
      user: { id: 7, name: 'Tester' },
      initialized: true,
      fetchUser: vi.fn(async () => null),
      csrf: vi.fn(async () => {}),
    }

    http = {
      get: vi.fn(async (url) => {
        if (url === '/posts') {
          return {
            data: {
              data: [],
              total: 0,
              next_page_url: null,
            },
          }
        }

        if (url === '/me/followed-events') {
          return {
            data: {
              data: [],
              total: 0,
            },
          }
        }

        return { data: {} }
      }),
      patch: vi.fn(),
      delete: vi.fn(),
    }

    eventFollows = {
      revision: 0,
      hydrateFromEvents: vi.fn(),
    }

    confirm = vi.fn(async () => true)

    listObservationsMock.mockResolvedValue({
      data: {
        data: [],
        total: 0,
        current_page: 1,
        last_page: 1,
      },
    })
  })

  it('marks scope=me post loads as background auth failures', async () => {
    const tabs = useProfileContentTabs({
      auth,
      http,
      eventFollows,
      confirm,
    })

    await tabs.initializeProfileContent()

    const postRequests = http.get.mock.calls.filter(([url]) => url === '/posts')

    expect(
      postRequests.some(([, config]) => (
        config?.params?.scope === 'me'
        && config?.params?.kind === 'roots'
        && config?.params?.per_page === 1
        && config?.meta?.skipAuthRedirect === true
      )),
    ).toBe(true)

    expect(
      postRequests.some(([, config]) => (
        config?.params?.scope === 'me'
        && config?.params?.kind === 'roots'
        && config?.params?.per_page === 10
        && config?.meta?.skipAuthRedirect === true
      )),
    ).toBe(true)
  })

  it('loads liked posts for likes tab', async () => {
    const tabs = useProfileContentTabs({
      auth,
      http,
      eventFollows,
      confirm,
    })

    await tabs.initializeProfileContent()
    tabs.setActiveTab('likes')
    await tabs.loadTab('likes', true)

    expect(http.get).toHaveBeenCalledWith('/posts', expect.objectContaining({
      params: { kind: 'likes', per_page: 10 },
      meta: { skipAuthRedirect: true },
    }))
  })
})
