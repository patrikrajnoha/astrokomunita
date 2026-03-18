import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import MoonPhasesWidget from './MoonPhasesWidget.vue'

const mockGetMoonPhasesWidget = vi.hoisted(() => vi.fn())
const mockGetMoonOverviewWidget = vi.hoisted(() => vi.fn())

vi.mock('@/services/widgets', () => ({
  getMoonPhasesWidget: mockGetMoonPhasesWidget,
  getMoonOverviewWidget: mockGetMoonOverviewWidget,
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

const PHASES_PAYLOAD = {
  reference_at: '2026-03-17T12:00:00+01:00',
  reference_date: '2026-03-17',
  timezone: 'Europe/Bratislava',
  current_phase: 'waning_crescent',
  major_events: [
    { key: 'new_moon', label: 'Nov', at: '2099-01-10T02:23:00+01:00', date: '2099-01-10', time: '02:23', is_current: false },
    { key: 'first_quarter', label: 'Prva stvrt', at: '2099-01-17T20:17:00+01:00', date: '2099-01-17', time: '20:17', is_current: false },
    { key: 'full_moon', label: 'Spln', at: '2099-01-24T04:11:00+01:00', date: '2099-01-24', time: '04:11', is_current: false },
    { key: 'last_quarter', label: 'Posledna stvrt', at: '2099-02-01T10:38:00+01:00', date: '2099-02-01', time: '10:38', is_current: false },
  ],
  phases: [],
  source: {
    provider: 'USNO',
    label: 'USNO Moon Phases API (free, bez API kluca)',
    url: 'https://aa.usno.navy.mil/api/moon/phases/year',
    api_key_required: false,
  },
}

const OVERVIEW_PAYLOAD = {
  reference_at: '2026-03-17T12:00:00+01:00',
  timezone: 'Europe/Bratislava',
  moon_phase: 'waning_crescent',
  moon_illumination_percent: 28,
  moon_altitude_deg: -67.94,
  moon_azimuth_deg: 349.74,
  moon_direction: 'N',
  moon_distance_km: 397906,
  next_new_moon_at: '2099-01-10T02:23:00+01:00',
  next_full_moon_at: '2099-01-24T04:11:00+01:00',
  next_moonrise_at: '2026-03-18T04:16:00+01:00',
  source: {
    phase: { provider: 'USNO', label: 'USNO Oneday API', url: '', api_key_required: false },
    position: { provider: 'JPL', label: 'JPL Horizons API', url: '', api_key_required: false },
    next_phases: { provider: 'USNO', label: 'USNO Moon Phases API', url: '', api_key_required: false },
  },
}

describe('MoonPhasesWidget', () => {
  beforeEach(() => {
    mockGetMoonPhasesWidget.mockReset()
    mockGetMoonOverviewWidget.mockReset()
    mockGetMoonPhasesWidget.mockResolvedValue(PHASES_PAYLOAD)
    mockGetMoonOverviewWidget.mockResolvedValue(OVERVIEW_PAYLOAD)
  })

  it('renders current phase, illumination and upcoming events', async () => {
    const wrapper = mount(MoonPhasesWidget, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava', date: '2026-03-17' },
    })

    await flushPromises()
    await nextTick()

    expect(mockGetMoonPhasesWidget).toHaveBeenCalledOnce()
    expect(mockGetMoonPhasesWidget).toHaveBeenCalledWith({
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      date: '2026-03-17',
    })
    expect(mockGetMoonOverviewWidget).toHaveBeenCalledOnce()
    expect(mockGetMoonOverviewWidget).toHaveBeenCalledWith({
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      date: '2026-03-17',
    })

    // Hero: current phase from overview (most accurate)
    expect(wrapper.text()).toContain('Ubúdajúci kosáčik')
    expect(wrapper.text()).toContain('28 %')

    // Upcoming events with frontend-localized labels (proper diacritics)
    const items = wrapper.findAll('.upcomingItem')
    expect(items.length).toBe(4)
    expect(wrapper.text()).toContain('Nov')
    expect(wrapper.text()).toContain('Prvá štvrt')
    expect(wrapper.text()).toContain('Spln')
    expect(wrapper.text()).toContain('Posledná štvrt')

    // No removed technical data
    expect(wrapper.text()).not.toContain('Smer Mesiaca')
    expect(wrapper.text()).not.toContain('Vzdialenosť')
    expect(wrapper.text()).not.toContain('Výška')
    expect(wrapper.text()).not.toContain('Špeciálne lunárne')
  })

  it('falls back to phases-widget phase when overview API fails', async () => {
    mockGetMoonOverviewWidget.mockRejectedValue(new Error('overview unavailable'))

    const wrapper = mount(MoonPhasesWidget, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await flushPromises()
    await nextTick()

    // Phase name still shown from phasesWidget.current_phase
    expect(wrapper.text()).toContain('Ubúdajúci kosáčik')
    // Illumination hidden since overview failed
    expect(wrapper.text()).not.toContain('28 %')
    // Upcoming events still rendered
    expect(wrapper.findAll('.upcomingItem').length).toBe(4)
  })

  it('shows error state when phases API fails', async () => {
    mockGetMoonPhasesWidget.mockRejectedValue(new Error('phases unavailable'))

    const wrapper = mount(MoonPhasesWidget, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await flushPromises()
    await nextTick()

    expect(wrapper.text()).toContain('Nepodarilo sa načítať')
    expect(wrapper.text()).not.toContain('Ubúdajúci')
  })
})
