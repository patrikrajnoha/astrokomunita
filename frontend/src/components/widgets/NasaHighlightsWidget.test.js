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
          excerpt: 'Bundled excerpt',
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
    expect(wrapper.text()).toContain('Zdroj: NASA IOTD RSS')
  })
})
