import { describe, expect, it } from 'vitest'
import {
  EXCLUSIVE_SIDEBAR_SECTION_KEYS,
  GUEST_OBSERVING_PROMPT_SECTION_KEY,
  MAX_ENABLED_SIDEBAR_WIDGETS,
  getEnabledSidebarSections,
} from '@/sidebar/engine'

describe('sidebar engine constraints', () => {
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
})
