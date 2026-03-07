import { describe, expect, it } from 'vitest'
import {
  EXCLUSIVE_SIDEBAR_SECTION_KEYS,
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
    expect(enabled.map((item) => item.section_key)).toEqual(['search', 'nasa_apod'])
  })

  it('returns only observing_conditions when exclusive widget is enabled', () => {
    const enabled = getEnabledSidebarSections([
      { kind: 'builtin', section_key: 'search', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'observing_conditions', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'nasa_apod', order: 2, is_enabled: true },
    ])

    expect(EXCLUSIVE_SIDEBAR_SECTION_KEYS).toContain('observing_conditions')
    expect(enabled).toHaveLength(1)
    expect(enabled[0].section_key).toBe('observing_conditions')
  })
})
