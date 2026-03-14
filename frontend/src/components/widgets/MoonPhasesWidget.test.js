import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import MoonPhasesWidget from './MoonPhasesWidget.vue'

const mockGetMoonPhasesWidget = vi.hoisted(() => vi.fn())
const mockGetMoonEventsWidget = vi.hoisted(() => vi.fn())
const mockGetMoonOverviewWidget = vi.hoisted(() => vi.fn())

vi.mock('@/services/widgets', () => ({
  getMoonPhasesWidget: mockGetMoonPhasesWidget,
  getMoonEventsWidget: mockGetMoonEventsWidget,
  getMoonOverviewWidget: mockGetMoonOverviewWidget,
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('MoonPhasesWidget', () => {
  beforeEach(() => {
    mockGetMoonPhasesWidget.mockReset()
    mockGetMoonEventsWidget.mockReset()
    mockGetMoonOverviewWidget.mockReset()
    mockGetMoonPhasesWidget.mockResolvedValue({
      reference_at: '2026-03-08T00:00:00+01:00',
      reference_date: '2026-03-08',
      timezone: 'Europe/Bratislava',
      current_phase: 'full_moon',
      major_events: [
        { key: 'last_quarter', label: 'Posledna stvrt', at: '2026-03-11T10:38:00+01:00', date: '2026-03-11', time: '10:38', is_current: false },
        { key: 'new_moon', label: 'Nov', at: '2026-03-19T02:23:00+01:00', date: '2026-03-19', time: '02:23', is_current: false },
        { key: 'first_quarter', label: 'Prva stvrt', at: '2026-03-25T20:17:00+01:00', date: '2026-03-25', time: '20:17', is_current: false },
        { key: 'full_moon', label: 'Spln', at: '2026-04-02T04:11:00+02:00', date: '2026-04-02', time: '04:11', is_current: false },
      ],
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
    mockGetMoonEventsWidget.mockResolvedValue({
      year: 2026,
      timezone: 'Europe/Bratislava',
      events: [
        { key: 'super_new_moon', label: 'Super New Moon', at: '2026-05-16T07:01:00+02:00', date: '2026-05-16', time: '07:01', note: 'Nov blizko perigea.' },
        { key: 'blue_moon', label: 'Blue Moon', at: '2026-05-31T10:45:00+02:00', date: '2026-05-31', time: '10:45', note: 'Druhy spln v jednom kalendarnom mesiaci.' },
        { key: 'micro_full_moon', label: 'Micro Full Moon', at: '2026-05-31T10:45:00+02:00', date: '2026-05-31', time: '10:45', note: 'Spln blizko apogea.' },
      ],
      source: {
        moon_phases: {
          provider: 'USNO',
          label: 'USNO Moon Phases API (free, bez API kluca)',
          url: 'https://aa.usno.navy.mil/api/moon/phases/year',
          api_key_required: false,
        },
        distance: {
          provider: 'JPL',
          label: 'JPL Horizons API',
          url: 'https://ssd.jpl.nasa.gov/api/horizons.api',
          api_key_required: false,
        },
      },
    })
    mockGetMoonOverviewWidget.mockResolvedValue({
      reference_at: '2026-03-13T19:35:02+01:00',
      timezone: 'Europe/Bratislava',
      moon_phase: 'waning_crescent',
      moon_illumination_percent: 28,
      moon_altitude_deg: -67.94,
      moon_azimuth_deg: 349.74,
      moon_direction: 'N',
      moon_distance_km: 397906,
      next_new_moon_at: '2026-03-19T03:23:00+01:00',
      next_full_moon_at: '2026-04-02T06:11:00+02:00',
      next_moonrise_at: '2026-03-14T04:16:00+01:00',
      source: {
        phase: {
          provider: 'USNO',
          label: 'USNO Oneday API (free, bez API kluca)',
          url: 'https://aa.usno.navy.mil/api/rstt/oneday',
          api_key_required: false,
        },
        position: {
          provider: 'JPL',
          label: 'JPL Horizons API',
          url: 'https://ssd.jpl.nasa.gov/api/horizons.api',
          api_key_required: false,
        },
        next_phases: {
          provider: 'USNO',
          label: 'USNO Moon Phases API (free, bez API kluca)',
          url: 'https://aa.usno.navy.mil/api/moon/phases/year',
          api_key_required: false,
        },
      },
    })
  })

  it('fetches moon phases and renders the major phase timeline with date and time', async () => {
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
    expect(mockGetMoonEventsWidget).toHaveBeenCalledTimes(1)
    expect(mockGetMoonEventsWidget).toHaveBeenCalledWith({
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      year: 2026,
    })
    expect(mockGetMoonOverviewWidget).toHaveBeenCalledTimes(1)
    expect(mockGetMoonOverviewWidget).toHaveBeenCalledWith({
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      date: '2026-03-08',
    })

    expect(wrapper.findAll('.phaseEvent')).toHaveLength(4)
    expect(wrapper.text()).toContain('Posledna stvrt')
    expect(wrapper.text()).toContain('Nov')
    expect(wrapper.text()).toContain('10:38')
    expect(wrapper.text()).toContain('04:11')
    expect(wrapper.text()).toContain('Mesiac: 28%')
    expect(wrapper.text()).toContain('Smer Mesiaca:')
    expect(wrapper.text()).toContain('Vzdialenost Mesiaca:')
    expect(wrapper.text()).toContain('Dalsi vychod Mesiaca:')
    expect(wrapper.findAll('.specialEventRow')).toHaveLength(3)
    expect(wrapper.text()).toContain('Specialne lunarne udalosti v 2026')
    expect(wrapper.text()).toContain('Super New Moon')
    expect(wrapper.text()).toContain('Blue Moon')
  })

  it('can hide overview and special events when dedicated widgets are present', async () => {
    const wrapper = mount(MoonPhasesWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        date: '2026-03-08',
        showOverview: false,
        showSpecialEvents: false,
      },
    })

    await flushPromises()
    await nextTick()

    expect(mockGetMoonPhasesWidget).toHaveBeenCalledTimes(1)
    expect(mockGetMoonOverviewWidget).not.toHaveBeenCalled()
    expect(mockGetMoonEventsWidget).not.toHaveBeenCalled()

    expect(wrapper.findAll('.phaseEvent')).toHaveLength(4)
    expect(wrapper.text()).not.toContain('Aktualny cas:')
    expect(wrapper.text()).not.toContain('Specialne lunarne udalosti v')
  })
})
