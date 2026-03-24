import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import NextMeteorWidget from './NextMeteorWidget.vue'

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

describe('NextMeteorWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-02-16T12:00:00Z'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('loads the dedicated next-meteor endpoint and renders meteor detail link', async () => {
    getMock.mockResolvedValue({
      data: {
        data: {
          id: 77,
          title: 'Perzeidy',
          type: 'meteor_shower',
          start_at: '2026-08-12T21:00:00Z',
          updated_at: '2026-02-16T11:40:00Z',
          source: {
            name: 'imo',
          },
        },
      },
    })

    const wrapper = mount(NextMeteorWidget, {
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

    expect(getMock).toHaveBeenCalledWith('/events/widget/next-meteor-shower')
    expect(wrapper.text()).toContain('Padajúce hviezdy')
    expect(wrapper.text()).toContain('Meteorický roj')
    expect(wrapper.find('a[href="/events/77"]').exists()).toBe(true)
  })
})
