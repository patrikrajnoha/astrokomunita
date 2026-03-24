import { describe, expect, it } from 'vitest'
import {
  EXCLUSIVE_SIDEBAR_SECTION_KEYS,
  GUEST_OBSERVING_PROMPT_SECTION_KEY,
  MAX_ENABLED_SIDEBAR_WIDGETS,
  getEnabledSidebarSections,
} from '@/sidebar/engine'

describe('sidebar engine constraints', () => {
  it('normalizes builtin section titles to clean Slovak labels', () => {
    const enabled = getEnabledSidebarSections([
      {
        kind: 'builtin',
        section_key: 'next_event',
        title: 'Najbližšia udalosť',
        order: 0,
        is_enabled: true,
      },
    ])

    expect(enabled).toHaveLength(1)
    expect(enabled[0].title).toBe('Najbližšia udalosť')
  })

  it('limits enabled widgets to configured maximum', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'search', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'nasa_apod', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'next_event', order: 2, is_enabled: true },
    ])

    expect(enabled).toHaveLength(MAX_ENABLED_SIDEBAR_WIDGETS)
    expect(enabled.map((item) => item.section_key)).toEqual(['search', 'nasa_apod', 'next_event'])
  })

  it('keeps observing widgets together when they are enabled', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'observing_conditions', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'observing_weather', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'night_sky', order: 2, is_enabled: true },
    ])

    expect(EXCLUSIVE_SIDEBAR_SECTION_KEYS).toEqual([])
    expect(enabled).toHaveLength(3)
    expect(enabled.map((item) => item.section_key)).toEqual([
      'observing_conditions',
      'observing_weather',
      'night_sky',
    ])
  })

  it('collapses observing widgets into one guest prompt and keeps room for next widgets', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'observing_conditions', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'observing_weather', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'night_sky', order: 2, is_enabled: true },
      { kind: 'builtin', section_key: 'search', order: 3, is_enabled: true },
      { kind: 'builtin', section_key: 'nasa_apod', order: 4, is_enabled: true },
    ], { isGuest: true })

    expect(enabled).toHaveLength(3)
    expect(enabled.map((item) => item.section_key)).toEqual([
      GUEST_OBSERVING_PROMPT_SECTION_KEY,
      'search',
      'nasa_apod',
    ])
  })

  it('shows only one observing widget when location is missing for signed-in user', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'observing_conditions', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'observing_weather', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'night_sky', order: 2, is_enabled: true },
    ], { isGuest: false, collapseObservingForMissingLocation: true })

    expect(enabled).toHaveLength(1)
    expect(enabled.map((item) => item.section_key)).toEqual([GUEST_OBSERVING_PROMPT_SECTION_KEY])
  })

  it('treats aurora_watch as an observing widget for guest collapse', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'aurora_watch', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'space_weather', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'search', order: 2, is_enabled: true },
    ], { isGuest: true })

    expect(enabled).toHaveLength(2)
    expect(enabled.map((item) => item.section_key)).toEqual([
      GUEST_OBSERVING_PROMPT_SECTION_KEY,
      'search',
    ])
  })

  it('does not collapse moon widgets into guest observing prompt', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'moon_phases', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'next_event', order: 1, is_enabled: true },
    ], { isGuest: true })

    expect(enabled).toHaveLength(2)
    expect(enabled.map((item) => item.section_key)).toEqual(['moon_phases', 'next_event'])
  })

  it('filters enabled widgets by preferred section keys', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'search', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'nasa_apod', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'next_event', order: 2, is_enabled: true },
      { kind: 'builtin', section_key: 'latest_articles', order: 3, is_enabled: true },
    ], { preferredSectionKeys: ['latest_articles', 'search'] })

    expect(enabled).toHaveLength(2)
    expect(enabled.map((item) => item.section_key)).toEqual(['latest_articles', 'search'])
  })

  it('returns no widgets when preferred keys are explicitly empty', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'search', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'nasa_apod', order: 1, is_enabled: true },
    ], { preferredSectionKeys: [] })

    expect(enabled).toHaveLength(0)
  })

  it('uses preferred keys even when those sections are globally disabled', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'search', order: 0, is_enabled: false },
      { kind: 'builtin', section_key: 'nasa_apod', order: 1, is_enabled: false },
      { kind: 'builtin', section_key: 'moon_phases', order: 2, is_enabled: true },
    ], { preferredSectionKeys: ['search', 'nasa_apod'], allowUserPreferenceOverride: true })

    expect(enabled).toHaveLength(2)
    expect(enabled.map((item) => item.section_key)).toEqual(['search', 'nasa_apod'])
  })
})
