import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RightObservingSidebar from './RightObservingSidebar.vue'

const pushMock = vi.hoisted(() => vi.fn())
const getMock = vi.hoisted(() => vi.fn())

const authMock = vi.hoisted(() => ({
  isAuthed: true,
  initialized: true,
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ fullPath: '/home' }),
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

function wait(ms = 260) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function observePayload(overrides = {}) {
  return {
    overall: {
      label: 'Pozor',
      reason: 'Vysoká vlhkosť môže znížiť kontrast objektov.',
      alert_level: 'warn',
    },
    observing_index: 58,
    observing_mode: 'deep_sky',
    alerts: [
      { level: 'warn', code: 'high_humidity', message: 'Vysoká vlhkosť môže znížiť kontrast objektov.' },
      { level: 'severe', code: 'high_cloud_cover', message: 'Vysoká oblačnosť výrazne obmedzuje pozorovanie.' },
    ],
    best_time_local: '23:00',
    best_time_index: 52,
    best_time_reason: 'Relatívne najlepšie: nižšia oblačnosť, viac tmy.',
    sun: {
      status: 'ok',
      sunset: '17:10',
      sunrise: '07:11',
      civil_twilight_end: '17:40',
      civil_twilight_begin: '06:40',
    },
    moon: {
      phase_name: 'Waxing crescent',
      illumination_pct: 18,
    },
    atmosphere: {
      humidity: { current_pct: 85, evening_pct: 88, status: 'ok', label: 'Pozor' },
      cloud_cover: { current_pct: 67, evening_pct: 82, status: 'ok', label: 'Zlé' },
      seeing: { score: 44, status: 'ok' },
      air_quality: { pm25: null, pm10: null, status: 'unavailable' },
    },
    weather_now: {
      temperature_c: 2.4,
      apparent_temperature_c: -0.5,
      wind_speed: 12.5,
      weather_code: 2,
      weather_label_sk: 'Polojasno',
    },
    timeline: {
      hourly: [
        { local_time: '18:00', humidity_pct: 82, cloud_cover_pct: 68 },
        { local_time: '19:00', humidity_pct: 84, cloud_cover_pct: 70 },
        { local_time: '20:00', humidity_pct: 88, cloud_cover_pct: 82 },
      ],
      sunset: '17:10',
      sunrise: '07:11',
      civil_twilight_end: '17:40',
      civil_twilight_begin: '06:40',
    },
    ...overrides,
  }
}

describe('RightObservingSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authMock.isAuthed = true
    authMock.initialized = true
  })

  it('fetches only observe summary in deep-sky mode', async () => {
    getMock.mockResolvedValue({ data: observePayload() })

    mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(getMock).toHaveBeenCalledTimes(1)
    expect(getMock).toHaveBeenCalledWith('/observe/summary', expect.objectContaining({
      params: expect.objectContaining({ mode: 'deep_sky' }),
    }))
  })

  it('renders weather now card when weather_now exists', async () => {
    getMock.mockResolvedValue({ data: observePayload() })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Počasie teraz')
    expect(wrapper.text()).toContain('2.4 °C')
    expect(wrapper.text()).toContain('Polojasno')
  })

  it('does not show sky microservice warning and does not use warn/severe labels', async () => {
    getMock.mockResolvedValue({ data: observePayload() })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Pozor')
    expect(wrapper.text()).toContain('Zlé')
    expect(wrapper.text()).not.toContain('warn')
    expect(wrapper.text()).not.toContain('severe')
    expect(wrapper.text()).not.toContain('Nepodarilo sa načítať planéty/meteory')
  })

  it('keeps best-time sentence clean without duplicated prefix', async () => {
    getMock.mockResolvedValue({ data: observePayload() })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Relatívne najlepšie: nižšia oblačnosť, viac tmy.')
    expect(wrapper.text()).not.toContain('Relatívne najlepšie: Relatívne najlepšie:')
  })

  it('hides PM rows when PM data is unavailable', async () => {
    getMock.mockResolvedValue({
      data: observePayload({
        atmosphere: {
          humidity: { current_pct: 85, evening_pct: 88, status: 'ok', label: 'Pozor' },
          cloud_cover: { current_pct: 67, evening_pct: 82, status: 'ok', label: 'Zlé' },
          seeing: { score: 44, status: 'ok' },
          air_quality: { pm25: null, pm10: null, status: 'unavailable' },
        },
      }),
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).not.toContain('PM2.5 / PM10')
  })
})
