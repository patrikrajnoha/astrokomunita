import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import NextEclipseWidget from './NextEclipseWidget.vue'

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

describe('NextEclipseWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-02-16T12:00:00Z'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('loads the dedicated next-eclipse endpoint and renders eclipse detail link', async () => {
    getMock.mockResolvedValue({
      data: {
        data: {
          id: 108,
          title: 'Ciastocne zatmenie Slnka',
          type: 'eclipse_solar',
          start_at: '2026-03-29T09:15:00Z',
          updated_at: '2026-02-16T11:40:00Z',
          source: {
            name: 'manual',
          },
        },
      },
    })

    const wrapper = mount(NextEclipseWidget, {
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="String(to)"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).toHaveBeenCalledWith('/events/widget/next-eclipse')
    expect(wrapper.text()).toContain('Najblizsie zatmenie')
    expect(wrapper.text()).toContain('Zatmenie Slnka')
    expect(wrapper.find('a[href="/events/108"]').exists()).toBe(true)
  })
})
