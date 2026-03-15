import { describe, expect, it } from 'vitest'
import { buildWidgetProps } from './appLayout.utils'

describe('buildWidgetProps', () => {
  const observingContext = {
    lat: 48.1486,
    lon: 17.1077,
    date: '2026-03-13',
    tz: 'Europe/Bratislava',
    locationName: 'Bratislava',
  }

  it('hides duplicated moon blocks inside moon_phases when dedicated moon widgets are enabled', () => {
    const props = buildWidgetProps('moon_phases', 'Fazy mesiaca', observingContext, {
      enabledSectionKeys: ['moon_phases', 'moon_overview', 'moon_events'],
    })

    expect(props.showOverview).toBe(false)
    expect(props.showSpecialEvents).toBe(false)
  })

  it('keeps full moon_phases content when dedicated moon widgets are not enabled', () => {
    const props = buildWidgetProps('moon_phases', 'Fazy mesiaca', observingContext, {
      enabledSectionKeys: ['moon_phases'],
    })

    expect(props.showOverview).toBe(true)
    expect(props.showSpecialEvents).toBe(true)
  })

  it('passes the configured title to title-based sidebar widgets', () => {
    expect(buildWidgetProps('next_meteor_shower', 'Najblizsi meteoricky roj', observingContext)).toEqual({
      title: 'Najblizsi meteoricky roj',
      initialPayload: undefined,
      bundlePending: false,
    })
    expect(buildWidgetProps('upcoming_events', 'Co sa deje dnes', observingContext)).toEqual({
      title: 'Co sa deje dnes',
      initialPayload: undefined,
      bundlePending: false,
    })
    expect(buildWidgetProps('neo_watchlist', 'NEO watchlist', observingContext)).toEqual({
      title: 'NEO watchlist',
      initialPayload: undefined,
      bundlePending: false,
    })
    expect(buildWidgetProps('upcoming_launches', 'Bliziace sa starty', observingContext)).toEqual({
      title: 'Bliziace sa starty',
      initialPayload: undefined,
      bundlePending: false,
    })
  })

  it('passes observing context to aurora_watch', () => {
    expect(buildWidgetProps('aurora_watch', 'Aurora watch', observingContext)).toEqual({
      lat: 48.1486,
      lon: 17.1077,
      date: '2026-03-13',
      tz: 'Europe/Bratislava',
      locationName: 'Bratislava',
      initialPayload: undefined,
      bundlePending: false,
    })
  })

  it('passes mobile bundle payloads to bundle-aware widgets', () => {
    const props = buildWidgetProps('neo_watchlist', 'NEO watchlist', observingContext, {
      initialPayloads: {
        neo_watchlist: {
          available: true,
          items: [{ name: 'Apophis' }],
        },
      },
      bundlePending: true,
    })

    expect(props).toEqual({
      title: 'NEO watchlist',
      initialPayload: {
        available: true,
        items: [{ name: 'Apophis' }],
      },
      bundlePending: true,
    })
  })
})
