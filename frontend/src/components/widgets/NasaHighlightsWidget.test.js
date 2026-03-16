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

  it('toggles the full excerpt instead of rendering the external CTA button', async () => {
    const excerpt = 'Skore ranne slnecne svetlo osvetluje zapadnu stenu tohto nemenovaneho kratera a zanechava hlboke tiene na zemi i vo vnutri. Obraz bol urobeny 30. augusta 2023 LROC.'

    const wrapper = mount(NasaHighlightsWidget, {
      props: {
        initialPayload: {
          available: true,
          title: 'Dobre rano, Mesiac',
          excerpt,
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

    const toggle = wrapper.get('button.nasaExcerptToggle')
    const excerptNode = wrapper.get('.nasaExcerpt')

    expect(wrapper.text()).not.toContain('Zobrazit na NASA.gov')
    expect(toggle.text()).toBe('Zobrazit cely text')
    expect(excerptNode.classes()).not.toContain('nasaExcerpt--expanded')

    await toggle.trigger('click')

    expect(wrapper.get('button.nasaExcerptToggle').text()).toBe('Skryt text')
    expect(wrapper.get('.nasaExcerpt').classes()).toContain('nasaExcerpt--expanded')
  })
})
