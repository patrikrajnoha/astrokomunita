import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { nextTick } from 'vue'
import ObservationDetailView from './ObservationDetailView.vue'
import api from '@/services/api'

const getObservationMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())

const authMock = vi.hoisted(() => ({
  user: { id: 1 },
  csrf: vi.fn(async () => {}),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/observations', () => ({
  getObservation: getObservationMock,
  updateObservation: vi.fn(async () => ({ data: {} })),
  deleteObservation: vi.fn(async () => {}),
}))

vi.mock('@/services/events', () => ({
  getEvents: getEventsMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/events/:id', name: 'event-detail', component: { template: '<div>event</div>' } },
      { path: '/observations', name: 'observations', component: { template: '<div>list</div>' } },
      { path: '/observations/:id', name: 'observations.detail', component: ObservationDetailView },
    ],
  })

  await router.push('/observations/123')
  await router.isReady()

  const wrapper = mount(ObservationDetailView, {
    global: {
      plugins: [router],
    },
  })

  await flush()
  await nextTick()

  return wrapper
}

function baseObservation(overrides = {}) {
  return {
    id: 123,
    user_id: 1,
    title: 'Pozorovanie',
    description: null,
    observed_at: '2026-03-05T12:00:00Z',
    media: [],
    event_id: null,
    event: null,
    is_public: true,
    ...overrides,
  }
}

describe('ObservationDetailView event chip', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getEventsMock.mockResolvedValue({ data: { data: [] } })
  })

  it('renders event title chip when full event object is present', async () => {
    getObservationMock.mockResolvedValue({
      data: baseObservation({
        event_id: 42,
        event: {
          id: 42,
          title: 'Planetarna konjunkcia',
        },
      }),
    })

    const wrapper = await mountView()
    const chip = wrapper.get('.event-chip')
    expect(chip.text()).toContain('Udalosť: Planetarna konjunkcia')
    expect(chip.attributes('href')).toBe('/events/42')
  })

  it('renders generic event chip when only event_id is present', async () => {
    getObservationMock.mockResolvedValue({
      data: baseObservation({
        event_id: 99,
        event: null,
      }),
    })

    const wrapper = await mountView()
    const chip = wrapper.get('.event-chip')
    expect(chip.text()).toContain('Otvoriť udalosť')
    expect(chip.attributes('href')).toBe('/events/99')
  })

  it('hides event chip when event payload is missing', async () => {
    getObservationMock.mockResolvedValue({
      data: baseObservation({
        event_id: null,
        event: null,
      }),
    })

    const wrapper = await mountView()
    expect(wrapper.find('.event-chip').exists()).toBe(false)
  })

  it('normalizes existing observation media preview src against API origin', async () => {
    const originalBaseUrl = api.defaults.baseURL
    api.defaults.baseURL = 'https://api.astrokomunita.test/api'

    getObservationMock.mockResolvedValue({
      data: baseObservation({
        media: [{
          id: 4,
          path: 'observations/123/images/stacked-m31.jpg',
          url: '/api/media/file/observations/123/images/stacked-m31.jpg',
          mime_type: 'image/jpeg',
        }],
      }),
    })

    try {
      const wrapper = await mountView()
      await wrapper.findAll('.detail-actions button')[1].trigger('click')
      const preview = wrapper.get('.existing-media img')
      expect(preview.attributes('src')).toBe('https://api.astrokomunita.test/api/media/file/observations/123/images/stacked-m31.jpg')
    } finally {
      api.defaults.baseURL = originalBaseUrl
    }
  })
})
