import { describe, expect, it } from 'vitest'
import { classifySkyPhase, SKY_PHASE } from './useSkyWidget'

describe('useSkyWidget sky phase classification', () => {
  it('follows the requested solar altitude boundaries', () => {
    expect(classifySkyPhase({ hasLocationCoords: true, sunAltitudeDeg: 0.1 })).toBe(SKY_PHASE.DAY)
    expect(classifySkyPhase({ hasLocationCoords: true, sunAltitudeDeg: 0 })).toBe(SKY_PHASE.CIVIL_TWILIGHT)
    expect(classifySkyPhase({ hasLocationCoords: true, sunAltitudeDeg: -6 })).toBe(SKY_PHASE.NAUTICAL_TWILIGHT)
    expect(classifySkyPhase({ hasLocationCoords: true, sunAltitudeDeg: -12 })).toBe(SKY_PHASE.ASTRONOMICAL_TWILIGHT)
    expect(classifySkyPhase({ hasLocationCoords: true, sunAltitudeDeg: -18 })).toBe(SKY_PHASE.ASTRONOMICAL_NIGHT)
  })

  it('classifies Bratislava winter midnight as astronomical night when altitude is <= -18', () => {
    const bratislavaWinterMidnight = {
      lat: 48.1486,
      lon: 17.1077,
      localTime: '2026-01-15T00:00:00+01:00',
      sunAltitudeDeg: -32.4,
    }

    expect(classifySkyPhase({
      hasLocationCoords: Number.isFinite(bratislavaWinterMidnight.lat) && Number.isFinite(bratislavaWinterMidnight.lon),
      sunAltitudeDeg: bratislavaWinterMidnight.sunAltitudeDeg,
    })).toBe(SKY_PHASE.ASTRONOMICAL_NIGHT)
  })

  it('classifies Bratislava summer midnight as astronomical twilight when altitude is above -18', () => {
    const bratislavaSummerMidnight = {
      lat: 48.1486,
      lon: 17.1077,
      localTime: '2026-07-01T00:00:00+02:00',
      sunAltitudeDeg: -16.4,
    }

    expect(classifySkyPhase({
      hasLocationCoords: Number.isFinite(bratislavaSummerMidnight.lat) && Number.isFinite(bratislavaSummerMidnight.lon),
      sunAltitudeDeg: bratislavaSummerMidnight.sunAltitudeDeg,
    })).toBe(SKY_PHASE.ASTRONOMICAL_TWILIGHT)
  })

  it('returns location_required when coordinates are missing', () => {
    expect(classifySkyPhase({ hasLocationCoords: false, sunAltitudeDeg: -32.4 })).toBe(SKY_PHASE.LOCATION_REQUIRED)
  })
})
