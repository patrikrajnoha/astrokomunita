import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import NextEventWidget from './NextEventWidget.vue'

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

describe('NextEventWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-02-16T12:00:00Z'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders type, countdown, and source transparency for the next event', async () => {
    getMock.mockResolvedValue({
      data: {
        data: {
          id: 42,
          title: 'Perzeidy',
          type: 'meteor_shower',
          start_at: '2026-02-18T21:00:00Z',
          updated_at: '2026-02-16T11:40:00Z',
          source: {
            name: 'imo',
          },
        },
      },
    })

    const wrapper = mount(NextEventWidget, {
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

    expect(getMock).toHaveBeenCalledWith('/events/next')
    expect(wrapper.text()).toContain('Perzeidy')
    expect(wrapper.text()).toContain('Meteory')
    expect(wrapper.text()).toContain('Za 2 dni')
    expect(wrapper.text()).toContain('Zdroj: IMO')
    expect(wrapper.find('a[href="/events/42"]').exists()).toBe(true)
  })
})
