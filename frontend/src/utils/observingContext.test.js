import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { resolveObservingContext } from './observingContext'

describe('resolveObservingContext', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-14T21:20:00Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('falls back to preference coordinates when canonical user location is missing', () => {
    const context = resolveObservingContext({
      user: {
        timezone: 'Europe/Bratislava',
      },
      preferences: {
        locationLat: 48.1486,
        locationLon: 17.1077,
        locationLabel: 'Bratislava',
      },
    })

    expect(context).toMatchObject({
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      locationName: 'Bratislava',
      date: '2026-03-14',
    })
  })

  it('prefers canonical location_data over preference coordinates and respects date query', () => {
    const context = resolveObservingContext({
      user: {
        location_data: {
          latitude: 49.223,
          longitude: 18.739,
          timezone: 'Europe/Prague',
          label: 'Zilina',
        },
        location_meta: {
          lat: 48.9,
          lon: 17.8,
          tz: 'Europe/Bratislava',
          label: 'Meta city',
        },
        timezone: 'UTC',
      },
      preferences: {
        locationLat: 48.1486,
        locationLon: 17.1077,
        locationLabel: 'Bratislava',
      },
      dateQuery: '2026-04-01',
    })

    expect(context).toMatchObject({
      lat: 49.223,
      lon: 18.739,
      tz: 'Europe/Prague',
      locationName: 'Zilina',
      date: '2026-04-01',
    })
  })
})
