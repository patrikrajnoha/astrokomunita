import { describe, expect, it } from 'vitest'
import { calculateAstronomyScore } from './astronomyScore'

describe('calculateAstronomyScore', () => {
  it('returns a high score for clear astronomical night with low humidity and weak wind', () => {
    const result = calculateAstronomyScore({
      sunAltitudeDeg: -24,
      cloudPercent: 5,
      humidityPercent: 48,
      windKmh: 6,
      moonIlluminationPercent: 80,
      moonAltitudeDeg: -8,
      bortleClass: 2,
    })

    expect(result.phase).toBe('astronomical_night')
    expect(result.score).toBeGreaterThanOrEqual(80)
    expect(result.reasons[0]).toContain('Oblacnost')
  })

  it('returns a low score for overcast night conditions', () => {
    const result = calculateAstronomyScore({
      sunAltitudeDeg: -25,
      cloudPercent: 100,
      humidityPercent: 96,
      windKmh: 32,
      moonIlluminationPercent: 99,
      moonAltitudeDeg: 50,
      bortleClass: 8,
    })

    expect(result.phase).toBe('astronomical_night')
    expect(result.score).toBeLessThanOrEqual(20)
  })

  it('returns N/A score during daylight', () => {
    const result = calculateAstronomyScore({
      sunAltitudeDeg: 3,
      cloudPercent: 10,
      humidityPercent: 50,
      windKmh: 8,
      moonIlluminationPercent: 10,
      moonAltitudeDeg: -20,
      bortleClass: 4,
    })

    expect(result.phase).toBe('daylight')
    expect(result.score).toBeNull()
    expect(result.reasons).toContain('Denné svetlo')
  })

  it('caps twilight score to 40 points', () => {
    const result = calculateAstronomyScore({
      sunAltitudeDeg: -10,
      cloudPercent: 0,
      humidityPercent: 40,
      windKmh: 3,
      moonIlluminationPercent: 5,
      moonAltitudeDeg: -15,
      bortleClass: 1,
    })

    expect(result.phase).toBe('twilight')
    expect(result.twilightCap).toBe(40)
    expect(result.score).toBeLessThanOrEqual(40)
  })

  it('reduces score when bright moon is high above horizon', () => {
    const darkMoon = calculateAstronomyScore({
      sunAltitudeDeg: -24,
      cloudPercent: 10,
      humidityPercent: 55,
      windKmh: 8,
      moonIlluminationPercent: 96,
      moonAltitudeDeg: -6,
      bortleClass: 3,
    })
    const brightMoon = calculateAstronomyScore({
      sunAltitudeDeg: -24,
      cloudPercent: 10,
      humidityPercent: 55,
      windKmh: 8,
      moonIlluminationPercent: 96,
      moonAltitudeDeg: 55,
      bortleClass: 3,
    })

    expect(darkMoon.score).toBeTypeOf('number')
    expect(brightMoon.score).toBeTypeOf('number')
    expect(darkMoon.score).toBeGreaterThan(brightMoon.score)
  })
})
