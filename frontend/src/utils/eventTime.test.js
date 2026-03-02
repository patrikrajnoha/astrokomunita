import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  EVENT_TIMEZONE,
  EVENT_TIMEZONE_SHORT_LABEL,
  formatEventTime,
  getEventNowPeriodDefaults,
  resolveEventTimeContext,
} from './eventTime'

describe('eventTime', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('formats UTC timestamps into Europe/Bratislava across DST boundaries', () => {
    expect(formatEventTime('2026-01-14T19:30:00Z', EVENT_TIMEZONE)).toEqual({
      timeString: '20:30',
      timezoneLabel: 'Europe/Bratislava',
      timezoneLabelShort: EVENT_TIMEZONE_SHORT_LABEL,
      timezoneLabelLong: 'Europe/Bratislava',
    })

    expect(formatEventTime('2026-07-14T19:30:00Z', EVENT_TIMEZONE)).toEqual({
      timeString: '21:30',
      timezoneLabel: 'Europe/Bratislava',
      timezoneLabelShort: EVENT_TIMEZONE_SHORT_LABEL,
      timezoneLabelLong: 'Europe/Bratislava',
    })
  })

  it('builds peak, start and unknown messages from API metadata', () => {
    expect(
      resolveEventTimeContext(
        {
          start_at: '2026-03-14T19:30:00Z',
          max_at: '2026-03-14T19:30:00Z',
          time_type: 'peak',
          time_precision: 'exact',
        },
        EVENT_TIMEZONE,
      ),
    ).toMatchObject({
      message: 'Maximum o 20:30',
      showTimezoneLabel: true,
      timezoneLabelShort: EVENT_TIMEZONE_SHORT_LABEL,
    })

    expect(
      resolveEventTimeContext(
        {
          start_at: '2026-03-14T19:30:00Z',
          max_at: '2026-03-14T19:30:00Z',
          time_type: 'start',
          time_precision: 'exact',
        },
        EVENT_TIMEZONE,
      ),
    ).toMatchObject({
      message: 'Zaciatok o 20:30',
      showTimezoneLabel: true,
    })

    expect(
      resolveEventTimeContext(
        {
          start_at: '2026-05-06T00:00:00Z',
          max_at: '2026-05-06T00:00:00Z',
          time_type: 'peak',
          source: { name: 'imo' },
        },
        EVENT_TIMEZONE,
      ),
    ).toMatchObject({
      message: 'Cas bude upresneny',
      timePrecision: 'unknown',
      showTimezoneLabel: false,
    })
  })

  it('preserves explicit midnight times when precision is exact', () => {
    expect(
      resolveEventTimeContext(
        {
          start_at: '2026-01-05T23:00:00Z',
          max_at: '2026-01-05T23:00:00Z',
          time_type: 'peak',
          time_precision: 'exact',
          source: { name: 'imo' },
        },
        EVENT_TIMEZONE,
      ),
    ).toMatchObject({
      message: 'Maximum o 00:00',
      timePrecision: 'exact',
      showTimezoneLabel: true,
      timezoneLabelShort: EVENT_TIMEZONE_SHORT_LABEL,
    })
  })

  it('treats legacy events without time_precision as exact when source is not a fallback midnight source', () => {
    expect(
      resolveEventTimeContext(
        {
          start_at: '2026-04-01T18:30:00Z',
          max_at: '2026-04-01T18:30:00Z',
          source: { name: 'manual' },
        },
        EVENT_TIMEZONE,
      ),
    ).toMatchObject({
      timePrecision: 'exact',
      showTimezoneLabel: true,
      timezoneLabelShort: EVENT_TIMEZONE_SHORT_LABEL,
    })
  })

  it('can return a short timezone label for inline UI usage', () => {
    expect(
      formatEventTime('2026-01-14T19:30:00Z', EVENT_TIMEZONE, { timezoneLabelStyle: 'short' }),
    ).toMatchObject({
      timeString: '20:30',
      timezoneLabel: EVENT_TIMEZONE_SHORT_LABEL,
      timezoneLabelShort: EVENT_TIMEZONE_SHORT_LABEL,
      timezoneLabelLong: 'Europe/Bratislava',
    })
  })

  it('derives current period defaults in the event timezone instead of browser-local dates', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-12-31T23:30:00Z'))

    expect(getEventNowPeriodDefaults(EVENT_TIMEZONE)).toMatchObject({
      year: 2027,
      month: 1,
      day: 1,
    })
  })
})
