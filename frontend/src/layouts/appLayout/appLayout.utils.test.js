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

  it('hides special events inside moon_phases when moon_events widget is enabled', () => {
    const props = buildWidgetProps('moon_phases', 'Fázy Mesiaca', observingContext, {
      enabledSectionKeys: ['moon_phases', 'moon_events'],
    })

    expect(props.showSpecialEvents).toBe(false)
  })

  it('keeps full moon_phases content when moon_events widget is not enabled', () => {
    const props = buildWidgetProps('moon_phases', 'Fázy Mesiaca', observingContext, {
      enabledSectionKeys: ['moon_phases'],
    })

    expect(props.showSpecialEvents).toBe(true)
  })

  it('passes the configured title to title-based sidebar widgets', () => {
    expect(buildWidgetProps('next_meteor_shower', 'Padajúce hviezdy', observingContext)).toEqual({
      title: 'Padajúce hviezdy',
      initialPayload: undefined,
      bundlePending: false,
    })
    expect(buildWidgetProps('upcoming_events', 'Udalosti v kalendári', observingContext)).toEqual({
      title: 'Udalosti v kalendári',
      initialPayload: undefined,
      bundlePending: false,
    })
    expect(buildWidgetProps('neo_watchlist', 'Asteroidy nablízku', observingContext)).toEqual({
      title: 'Asteroidy nablízku',
      initialPayload: undefined,
      bundlePending: false,
    })
    expect(buildWidgetProps('upcoming_launches', 'Štarty do vesmíru', observingContext)).toEqual({
      title: 'Štarty do vesmíru',
      initialPayload: undefined,
      bundlePending: false,
    })
    expect(buildWidgetProps('constellations_now', 'Viditeľné súhvezdia', observingContext)).toEqual({
      title: 'Viditeľné súhvezdia',
      initialPayload: undefined,
      bundlePending: false,
    })
  })

  it('passes observing context to aurora_watch', () => {
    expect(buildWidgetProps('aurora_watch', 'Polárna žiara', observingContext)).toEqual({
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
    const props = buildWidgetProps('neo_watchlist', 'Asteroidy nablízku', observingContext, {
      initialPayloads: {
        neo_watchlist: {
          available: true,
          items: [{ name: 'Apophis' }],
        },
      },
      bundlePending: true,
    })

    expect(props).toEqual({
      title: 'Asteroidy nablízku',
      initialPayload: {
        available: true,
        items: [{ name: 'Apophis' }],
      },
      bundlePending: true,
    })
  })
})
