import { describe, expect, it } from 'vitest'
import { getPlanetVisibilityTag, getVisiblePlanets } from './skyWidget'

describe('skyWidget planet visibility helpers', () => {
  it('marks altitude 10 and elongation 20 as visible', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -12.1,
      altitudeDeg: 10,
      elongationDeg: 20,
    })).toBe('visible')
  })

  it('marks elongation 14.9 as close to the Sun when altitude is otherwise visible', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 12,
      elongationDeg: 14.9,
    })).toBe('close_to_sun')
  })

  it('marks altitude 5 as low', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 5,
      elongationDeg: 40,
    })).toBe('low')
  })

  it('marks altitude 9.9 as low', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 9.9,
      elongationDeg: 40,
    })).toBe('low')
  })

  it('keeps altitude 5-9.9 in the low bucket even when elongation is below 15', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 9.9,
      elongationDeg: 14.9,
    })).toBe('low')
  })

  it('marks altitude 4.9 as hidden', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 4.9,
      elongationDeg: 40,
    })).toBe('hidden')
  })

  it('does not mark elongation 15 as visible', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 12,
      elongationDeg: 15,
    })).toBe('hidden')
  })

  it('does not mark elongation 19.9 as visible', () => {
    expect(getPlanetVisibilityTag({
      sunAltitudeDeg: -18,
      altitudeDeg: 12,
      elongationDeg: 19.9,
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
