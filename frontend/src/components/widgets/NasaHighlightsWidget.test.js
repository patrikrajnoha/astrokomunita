import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import NasaHighlightsWidget from './NasaHighlightsWidget.vue'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('NasaHighlightsWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('uses bundled payload and skips initial API fetch', async () => {
    const wrapper = mount(NasaHighlightsWidget, {
      props: {
        initialPayload: {
          available: true,
          title: 'NASA bundled',
          excerpt: 'Bundled excerpt text.',
          image_url: 'https://example.com/image.jpg',
          link: 'https://www.nasa.gov/example',
          updated_at: '2026-02-16T12:00:00Z',
          source: {
            label: 'NASA IOTD RSS',
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('NASA bundled')
    expect(wrapper.text()).toContain('Bundled excerpt text.')
    expect(wrapper.text()).toContain('Čítať →')
    expect(wrapper.text()).toContain('Aktualizované')
    // No source label in footer
    expect(wrapper.text()).not.toContain('Zdroj:')
    // No expand toggle
    expect(wrapper.find('button').exists()).toBe(false)
    // Entire card is a link
    expect(wrapper.find('a.nasaCard').exists()).toBe(true)
  })

  it('shows unavailable state when payload has available: false', async () => {
    const wrapper = mount(NasaHighlightsWidget, {
      props: {
        initialPayload: {
          available: false,
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(wrapper.text()).toContain('nedostupné')
    expect(wrapper.find('a.nasaCard').exists()).toBe(false)
  })
})
