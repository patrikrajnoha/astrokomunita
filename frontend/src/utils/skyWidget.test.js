import { describe, expect, it } from 'vitest'
import { getPlanetVisibilityTag, getVisiblePlanets } from './skyWidget'

describe('skyWidget planet visibility helpers', () => {
  it('marks a planet visible only when it is night, high enough, and far enough from the Sun', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -12.1,
      altitudeDeg: 10,
      elongationDeg: 20,
    })).toBe('visible')
  })

  it('marks planets close to the Sun when elongation is below 15 degrees', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 12,
      elongationDeg: 14.9,
    })).toBe('close_to_sun')
  })

  it('does not mark elongation 15-19.9 as visible', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 12,
      elongationDeg: 15,
    })).toBe('hidden')

    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 12,
      elongationDeg: 19.9,
    })).toBe('hidden')
  })

  it('marks low and hidden altitude edge cases correctly', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 9.9,
      elongationDeg: 40,
    })).toBe('low')

    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 4.9,
      elongationDeg: 40,
    })).toBe('hidden')
  })

  it('hides the list before full night based on root sun_altitude_deg', () => {
    expect(getVisiblePlanets({
      sample_at: '2026-02-27T18:00:00+01:00',
      sun_altitude_deg: -12,
      planets: [
        { name: 'Jupiter', altitude_deg: 52, elongation_deg: 132, direction: 'SE' },
      ],
    })).toEqual([])
  })
})
