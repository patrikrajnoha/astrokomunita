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
  useRoute: () => ({
    fullPath: '/home',
  }),
  useRouter: () => ({
    push: pushMock,
  }),
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
      label: 'OK',
      reason: 'Stable weather.',
      alert_level: 'none',
    },
    observing_index: 72,
    observing_mode: 'deep_sky',
    factors: {
      humidity: 60,
      cloud: 55,
      air_quality: 72,
      moon: 40,
      darkness: 80,
      seeing: 58,
    },
    weights: {
      humidity: 0.25,
      cloud: 0.3,
      air_quality: 0.2,
      moon: 0.15,
      darkness: 0.05,
      seeing: 0.05,
    },
    alerts: [],
    best_time_local: '22:00',
    best_time_index: 78,
    best_time_reason: 'Low cloud cover.',
    sun: {
      status: 'ok',
      sunset: '17:10',
      sunrise: '07:11',
      civil_twilight_end: '17:40',
      civil_twilight_begin: '06:40',
    },
    moon: {
      phase_name: 'Full moon',
      illumination_pct: 91,
    },
    atmosphere: {
      humidity: { current_pct: 65, evening_pct: 62, status: 'ok' },
      cloud_cover: { current_pct: 45, evening_pct: 34, status: 'ok' },
      seeing: { score: 56, status: 'ok' },
      air_quality: { pm25: 12.2, pm10: 28.4, status: 'ok' },
    },
    timeline: {
      hourly: [
        { local_time: '18:00', humidity_pct: 70, cloud_cover_pct: 45 },
        { local_time: '19:00', humidity_pct: 66, cloud_cover_pct: 38 },
        { local_time: '20:00', humidity_pct: 62, cloud_cover_pct: 34 },
      ],
      sunset: '17:10',
      sunrise: '07:11',
      civil_twilight_end: '17:40',
      civil_twilight_begin: '06:40',
    },
    ...overrides,
  }
}

function skyPayload(overrides = {}) {
  return {
    planets: [],
    meteors: [],
    moon: {
      phase_name: 'Full moon',
      illumination: 91,
      rise_local: '18:00',
      set_local: '06:00',
      altitude_hourly: [
        { local_time: '18:00', altitude_deg: 10.0 },
        { local_time: '19:00', altitude_deg: 18.5 },
        { local_time: '20:00', altitude_deg: 26.1 },
      ],
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

  it('toggles observing mode and sends mode query param', async () => {
    getMock.mockImplementation((url, { params }) => {
      if (url === '/observe/summary') {
        return Promise.resolve({ data: observePayload({ observing_mode: params.mode }) })
      }

      return Promise.resolve({ data: skyPayload() })
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.10, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()
    expect(getMock).toHaveBeenCalledWith('/observe/summary', expect.objectContaining({
      params: expect.objectContaining({ mode: 'deep_sky' }),
    }))

    await wrapper.findAll('.modeBtn')[1].trigger('click')
    await wait()

    expect(getMock).toHaveBeenCalledWith('/observe/summary', expect.objectContaining({
      params: expect.objectContaining({ mode: 'planets' }),
    }))
  })

  it('renders progress bar from observing index and robustly maps accented bad labels', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/observe/summary') {
        return Promise.resolve({ data: observePayload({ observing_index: 78, overall: { label: 'Zlé', reason: 'Bad cloud.', alert_level: 'warn' } }) })
      }
      return Promise.resolve({ data: skyPayload() })
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.10, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.get('.progressTrack').attributes('aria-valuenow')).toBe('78')
    expect(wrapper.get('.progressFill').attributes('style')).toContain('78%')

    const atmosphereChip = wrapper.findAll('.chip').find((chip) => chip.text().includes('Atmosfera'))
    expect(atmosphereChip?.classes()).toContain('isBad')
  })

  it('renders alerts list', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/observe/summary') {
        return Promise.resolve({
          data: observePayload({
            alerts: [
              { level: 'warn', code: 'high_humidity', message: 'Humidity too high.' },
              { level: 'severe', code: 'high_cloud_cover', message: 'Cloud cover too high.' },
            ],
          }),
        })
      }
      return Promise.resolve({ data: skyPayload() })
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.10, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.findAll('.alertItem')).toHaveLength(2)
    expect(wrapper.text()).toContain('Humidity too high.')
    expect(wrapper.text()).toContain('Cloud cover too high.')
  })

  it('survives partial failure when observe summary fails but sky summary succeeds', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/observe/summary') {
        return Promise.reject(new Error('observe failed'))
      }

      return Promise.resolve({
        data: skyPayload({
          planets: [
            {
              key: 'jupiter',
              name: 'Jupiter',
              best_from: '19:00',
              best_to: '23:00',
              direction: 'SE',
              alt_max_deg: 38.4,
              is_low: false,
            },
          ],
        }),
      })
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.10, date: '2026-02-20', tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Observing index je docasne nedostupny')
    expect(wrapper.text()).toContain('Planety')
    expect(wrapper.text()).toContain('Jupiter')
  })
})
