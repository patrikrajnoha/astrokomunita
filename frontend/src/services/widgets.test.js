import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiGetMock = vi.hoisted(() => vi.fn())

vi.mock('./api', () => ({
  default: {
    get: apiGetMock,
  },
}))

describe('getSidebarWidgetBundle', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
  })

  it('dedupes concurrent bundle requests with the same section set', async () => {
    let resolveRequest = null
    apiGetMock.mockImplementation(() => new Promise((resolve) => {
      resolveRequest = resolve
    }))

    const { getSidebarWidgetBundle } = await import('./widgets')
    const firstPromise = getSidebarWidgetBundle(
      ['space_weather', 'aurora_watch'],
      { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
    )
    const secondPromise = getSidebarWidgetBundle(
      ['aurora_watch', 'space_weather'],
      { lat: '48.1486', lon: '17.1077', tz: ' Europe/Bratislava ' },
    )

    expect(apiGetMock).toHaveBeenCalledTimes(1)

    resolveRequest?.({
      data: {
        requested_sections: ['space_weather', 'aurora_watch'],
        data: {
          space_weather: { kp_index: 2 },
          aurora_watch: { score: 5 },
        },
      },
    })

    await expect(firstPromise).resolves.toEqual({
      requested_sections: ['space_weather', 'aurora_watch'],
      data: {
        space_weather: { kp_index: 2 },
        aurora_watch: { score: 5 },
      },
    })
    await expect(secondPromise).resolves.toEqual({
      requested_sections: ['space_weather', 'aurora_watch'],
      data: {
        space_weather: { kp_index: 2 },
        aurora_watch: { score: 5 },
      },
    })
  })

  it('reuses the cached bundle response for the same normalized request', async () => {
    apiGetMock.mockResolvedValue({
      data: {
        requested_sections: ['observing_conditions', 'neo_watchlist'],
        data: {
          observing_conditions: { score: 82 },
          neo_watchlist: { available: true, items: [] },
        },
      },
    })

    const { getSidebarWidgetBundle } = await import('./widgets')
    const firstPayload = await getSidebarWidgetBundle(
      ['observing_conditions', 'neo_watchlist'],
      { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
    )
    const secondPayload = await getSidebarWidgetBundle(
      ['neo_watchlist', 'observing_conditions'],
      { lat: '48.1486', lon: '17.1077', tz: 'Europe/Bratislava' },
    )

    expect(firstPayload).toEqual({
      requested_sections: ['observing_conditions', 'neo_watchlist'],
      data: {
        observing_conditions: { score: 82 },
        neo_watchlist: { available: true, items: [] },
      },
    })
    expect(secondPayload).toEqual(firstPayload)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
  })
})
