import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RightObservingSidebar from './RightObservingSidebar.vue'
import { resolveSidebarScopeFromPath } from '@/utils/sidebarScope'

const pushMock = vi.hoisted(() => vi.fn())
const getMock = vi.hoisted(() => vi.fn())

const authMock = vi.hoisted(() => ({
  isAuthed: true,
  initialized: true,
  isAdmin: false,
  user: { id: 10, is_admin: false, role: 'user' },
}))

const preferencesMock = vi.hoisted(() => ({
  bortleClass: 6,
  savePreferences: vi.fn(async () => {}),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => preferencesMock,
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

function buildSummary(overrides = {}) {
  const base = {
    observing_index: 84,
    overall: { label: 'ok', reason: 'Jasna noc' },
    best_time_local: '22:30',
    best_time_index: 78,
    best_time_reason: 'nizsia oblacnost',
    atmosphere: {
      humidity: { evening_pct: 54 },
      cloud_cover: { evening_pct: 21 },
      seeing: { status: 'ok', score: 77, wind_speed_kmh: 5.6, humidity_pct: 54 },
      air_quality: { pm25: 8.5, pm10: 12.3 },
    },
    moon: {
      phase_name: 'waxing crescent',
      illumination_pct: 18,
      phase_schedule: [],
    },
    sky_quality: {
      bortle_class: 6,
      impact_note: 'Mestsky okraj',
    },
    timeline: {
      hourly: [
        { local_time: '20:00', humidity_pct: 60, cloud_cover_pct: 20 },
        { local_time: '21:00', humidity_pct: 55, cloud_cover_pct: 18 },
      ],
      sunset: '17:22',
      civil_twilight_end: '18:05',
      sunrise: '06:47',
    },
    weather_now: {
      weather_code: 2,
      weather_label_sk: 'Polojasno',
      temperature_c: 2.4,
      apparent_temperature_c: 1.5,
      wind_speed: 6.5,
    },
    sun: { sunrise: '06:47', sunset: '17:22' },
    alerts: [],
  }

  const merged = { ...base, ...overrides }
  if (overrides.overall) merged.overall = { ...base.overall, ...overrides.overall }
  if (overrides.atmosphere) merged.atmosphere = { ...base.atmosphere, ...overrides.atmosphere }
  if (overrides.moon) merged.moon = { ...base.moon, ...overrides.moon }
  if (overrides.sky_quality) merged.sky_quality = { ...base.sky_quality, ...overrides.sky_quality }
  if (overrides.timeline) merged.timeline = { ...base.timeline, ...overrides.timeline }
  if (overrides.weather_now) merged.weather_now = { ...base.weather_now, ...overrides.weather_now }
  if (overrides.sun) merged.sun = { ...base.sun, ...overrides.sun }

  return merged
}

async function flushFetch(ms = 320) {
  await vi.advanceTimersByTimeAsync(ms)
  await Promise.resolve()
  await Promise.resolve()
}

function mountSidebar(props = {}) {
  return mount(RightObservingSidebar, {
    props: {
      lat: 48.14,
      lon: 17.1,
      tz: 'Europe/Bratislava',
      locationName: 'Ivanka pri Nitre',
      ...props,
    },
  })
}

describe('RightObservingSidebar', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-02-27T19:30:00+01:00'))
    vi.clearAllMocks()

    authMock.isAuthed = true
    authMock.initialized = true
    authMock.isAdmin = false
    authMock.user = { id: 10, is_admin: false, role: 'user' }
    preferencesMock.bortleClass = 6
    preferencesMock.savePreferences.mockResolvedValue(undefined)

    getMock.mockImplementation(async (url) => {
      if (url !== '/observe/summary') {
        throw new Error(`Unexpected URL ${url}`)
      }
      return { data: buildSummary() }
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('maps known routes to sidebar scopes', () => {
    expect(resolveSidebarScopeFromPath('/')).toBe('home')
    expect(resolveSidebarScopeFromPath('/events')).toBe('events')
    expect(resolveSidebarScopeFromPath('/search')).toBe('search')
    expect(resolveSidebarScopeFromPath('/settings')).toBeNull()
    expect(resolveSidebarScopeFromPath('/observations')).toBeNull()
  })

  it('renders observing summary from /observe/summary', async () => {
    const wrapper = mountSidebar()
    await flushFetch()

    expect(getMock).toHaveBeenCalledWith('/observe/summary', expect.objectContaining({
      params: expect.objectContaining({
        lat: 48.14,
        lon: 17.1,
        mode: 'deep_sky',
      }),
    }))
    expect(wrapper.text()).toContain('Astronom')
    expect(wrapper.text()).toContain('Index')
    expect(wrapper.text()).toContain('84')
    expect(wrapper.text()).toContain('Jasna noc')
  })

  it('does not fetch summary without coordinates and shows setup CTA', async () => {
    const wrapper = mountSidebar({ lat: null, lon: null, locationName: '' })
    await flushFetch()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Nastavi')
  })

  it('navigates to profile location editor from setup CTA', async () => {
    const wrapper = mountSidebar({ lat: null, lon: null, locationName: '' })
    await flushFetch()

    const setupButton = wrapper.find('button.btn')
    expect(setupButton.exists()).toBe(true)
    await setupButton.trigger('click')

    expect(pushMock).toHaveBeenCalledWith({
      name: 'profile',
      query: { edit: '1', section: 'location' },
    })
  })

  it('shows fetch error and retries', async () => {
    let attempts = 0
    getMock.mockImplementation(async () => {
      attempts += 1
      if (attempts === 1) {
        throw new Error('temporary failure')
      }
      return { data: buildSummary() }
    })

    const wrapper = mountSidebar()
    await flushFetch()

    expect(wrapper.text()).toContain('Nepodarilo')
    const retryButton = wrapper.find('button.btnGhost')
    expect(retryButton.exists()).toBe(true)

    await retryButton.trigger('click')
    await flushFetch()

    expect(attempts).toBeGreaterThanOrEqual(2)
  })

  it('saves bortle preference and refetches summary', async () => {
    const wrapper = mountSidebar()
    await flushFetch()

    const initialCalls = getMock.mock.calls.length
    const slider = wrapper.find('input[type="range"]')
    expect(slider.exists()).toBe(true)

    await slider.setValue('8')
    await flushFetch(620)

    expect(preferencesMock.savePreferences).toHaveBeenCalledWith({ bortle_class: 8 })
    expect(getMock.mock.calls.length).toBeGreaterThan(initialCalls)
  })

  it('hides bortle slider for guests', async () => {
    authMock.isAuthed = false
    const wrapper = mountSidebar()
    await flushFetch()

    expect(wrapper.find('input[type="range"]').exists()).toBe(false)
  })
})
