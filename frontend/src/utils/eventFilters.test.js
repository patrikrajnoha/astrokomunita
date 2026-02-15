import { describe, expect, it } from 'vitest'
import { buildPeriodQuery, resolveDefaultYear } from './eventFilters'

describe('event filter helpers', () => {
  it('resolves default year with bounds', () => {
    const year = resolveDefaultYear({ minYear: 2021, maxYear: 2030, defaultYear: 2034 }, new Date('2026-02-15'))
    expect(year).toBe(2030)
  })

  it('builds query for week period and drops month', () => {
    const query = buildPeriodQuery({ period: 'week', year: 2026, month: 2, week: 7 })
    expect(query).toEqual({
      period: 'week',
      year: '2026',
      week: '7',
    })
  })
})
