import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import ObservationCard from './ObservationCard.vue'
import api from '@/services/api'

function baseObservation(overrides = {}) {
  return {
    id: 1,
    title: 'Pozorovanie',
    observed_at: '2026-03-05T12:00:00Z',
    description: '',
    media: [],
    user: { username: 'astro' },
    ...overrides,
  }
}

async function mountCard(observation) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/events/:id', name: 'event-detail', component: { template: '<div>event</div>' } },
    ],
  })
  await router.push('/events/1')
  await router.isReady()

  return mount(ObservationCard, {
    props: {
      observation,
    },
    global: {
      plugins: [router],
    },
  })
}

describe('ObservationCard event chip', () => {
  it('renders event title chip when full event object is present', async () => {
    const wrapper = await mountCard(baseObservation({
      event_id: 42,
      event: {
        id: 42,
        title: 'Totalne zatmenie Mesiaca',
      },
    }))

    const link = wrapper.get('.observation-event-link')
    expect(link.text()).toContain('Udalosť: Totalne zatmenie Mesiaca')
    expect(link.attributes('href')).toBe('/events/42')
  })

  it('renders generic event chip when only event_id is present', async () => {
    const wrapper = await mountCard(baseObservation({
      event_id: 77,
      event: null,
    }))

    const link = wrapper.get('.observation-event-link')
    expect(link.text()).toContain('Otvoriť udalosť')
    expect(link.attributes('href')).toBe('/events/77')
  })

  it('hides event chip when event data is missing', async () => {
    const wrapper = await mountCard(baseObservation({
      event_id: null,
      event: null,
    }))

    expect(wrapper.find('.observation-event-link').exists()).toBe(false)
  })

  it('renders observation image from production-like relative media payload', async () => {
    const originalBaseUrl = api.defaults.baseURL
    api.defaults.baseURL = 'https://api.astrokomunita.test/api'

    try {
      const wrapper = await mountCard(baseObservation({
        media: [{
          id: 91,
          path: 'observations/123/images/galaxy-shot.webp',
          url: '/api/media/file/observations/123/images/galaxy-shot.webp',
          mime_type: 'image/webp',
        }],
      }))

      const image = wrapper.get('.observation-image')
      expect(image.attributes('src')).toBe('https://api.astrokomunita.test/api/media/file/observations/123/images/galaxy-shot.webp')
      expect(image.attributes('alt')).toBe('Observation image')
    } finally {
      api.defaults.baseURL = originalBaseUrl
    }
  })

  it('does not render image grid when observation has no media source', async () => {
    const wrapper = await mountCard(baseObservation({
      media: [{
        id: 17,
        path: '',
        url: '',
        mime_type: 'image/jpeg',
      }],
    }))

    expect(wrapper.find('.observation-media').exists()).toBe(false)
    expect(wrapper.find('.observation-image').exists()).toBe(false)
  })
})
