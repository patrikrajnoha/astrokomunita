import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import MoonPhasesWidget from './MoonPhasesWidget.vue'

const mockGetMoonPhasesWidget = vi.hoisted(() => vi.fn())

vi.mock('@/services/widgets', () => ({
  getMoonPhasesWidget: mockGetMoonPhasesWidget,
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('MoonPhasesWidget', () => {
  beforeEach(() => {
    mockGetMoonPhasesWidget.mockReset()
    mockGetMoonPhasesWidget.mockResolvedValue({
      reference_at: '2026-03-08T00:00:00+01:00',
      reference_date: '2026-03-08',
      timezone: 'Europe/Bratislava',
      current_phase: 'full_moon',
      phases: [
        { key: 'new_moon', label: 'Nov', start_date: '2026-02-17', end_date: '2026-02-20', is_current: false },
        { key: 'waxing_crescent', label: 'Dorastajuci kosacik', start_date: '2026-02-20', end_date: '2026-02-24', is_current: false },
        { key: 'first_quarter', label: 'Prva stvrt', start_date: '2026-02-24', end_date: '2026-02-27', is_current: false },
        { key: 'waxing_gibbous', label: 'Dorastajuci mesiac', start_date: '2026-02-27', end_date: '2026-03-03', is_current: false },
        { key: 'full_moon', label: 'Spln', start_date: '2026-03-03', end_date: '2026-03-06', is_current: true },
        { key: 'waning_gibbous', label: 'Ubudajuci mesiac', start_date: '2026-03-06', end_date: '2026-03-10', is_current: false },
        { key: 'last_quarter', label: 'Posledna stvrt', start_date: '2026-03-10', end_date: '2026-03-13', is_current: false },
        { key: 'waning_crescent', label: 'Ubudajuci kosacik', start_date: '2026-03-13', end_date: '2026-03-17', is_current: false },
      ],
      source: {
        provider: 'USNO',
        label: 'USNO Moon Phases API (free, bez API kluca)',
        url: 'https://aa.usno.navy.mil/api/moon/phases/year',
        api_key_required: false,
      },
    })
  })

  it('fetches phases and renders all rows with highlighted current phase', async () => {
    const wrapper = mount(MoonPhasesWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        date: '2026-03-08',
      },
    })

    await flushPromises()
    await nextTick()

    expect(mockGetMoonPhasesWidget).toHaveBeenCalledTimes(1)
    expect(mockGetMoonPhasesWidget).toHaveBeenCalledWith({
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      date: '2026-03-08',
    })

    expect(wrapper.findAll('.phaseRow')).toHaveLength(8)
    expect(wrapper.findAll('.phaseRow.isCurrent')).toHaveLength(1)
    expect(wrapper.find('.phaseRow.isCurrent').text()).toContain('Spln')
  })
})
