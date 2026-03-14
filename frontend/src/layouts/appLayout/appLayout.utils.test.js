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
    })
    expect(buildWidgetProps('upcoming_events', 'Co sa deje dnes', observingContext)).toEqual({
      title: 'Co sa deje dnes',
    })
  })
})
