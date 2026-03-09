import { describe, expect, it } from 'vitest'
import {
  isValidTimezone,
  resolveUserCoordinates,
  resolveUserLocationLabel,
  resolveUserPreferredTimezone,
} from './userTimezone'

describe('userTimezone', () => {
  it('uses Bratislava defaults when user location is missing', () => {
    expect(resolveUserPreferredTimezone(null)).toBe('Europe/Bratislava')
    expect(resolveUserLocationLabel(null)).toBe('Bratislava, Slovakia')
    expect(resolveUserCoordinates(null)).toBeNull()
  })

  it('prefers canonical location_data values', () => {
    const user = {
      timezone: 'Europe/Prague',
      location_label: 'Kosice',
      location_data: {
        timezone: 'America/New_York',
        label: 'New York, USA',
        latitude: 40.7128,
        longitude: -74.006,
      },
    }

    expect(resolveUserPreferredTimezone(user)).toBe('America/New_York')
    expect(resolveUserLocationLabel(user)).toBe('New York, USA')
    expect(resolveUserCoordinates(user)).toEqual({
      lat: 40.7128,
      lon: -74.006,
    })
  })

  it('falls back from invalid timezone to a valid stored timezone', () => {
    const user = {
      timezone: 'Europe/Prague',
      location_data: {
        timezone: 'Invalid/Timezone',
      },
    }

    expect(resolveUserPreferredTimezone(user)).toBe('Europe/Prague')
  })

  it('rejects out-of-range coordinates', () => {
    const user = {
      location_data: {
        latitude: 148.0,
        longitude: 17.0,
      },
      latitude: 49.0,
      longitude: 18.0,
    }

    expect(resolveUserCoordinates(user)).toBeNull()
  })

  it('validates IANA timezone values', () => {
    expect(isValidTimezone('Europe/Bratislava')).toBe(true)
    expect(isValidTimezone('Invalid/Timezone')).toBe(false)
  })
})
