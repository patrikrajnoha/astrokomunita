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

function normalizeText(value = '') {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
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

  it('renders type label, compact date, countdown and link for the next event', async () => {
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

    // Type label is the primary identifier (no verbose DB title)
    expect(normalizeText(wrapper.text())).toContain('meteoricky roj')

    // Countdown
    expect(wrapper.text()).toContain('Za 2 dni')

    // No source attribution
    expect(wrapper.text()).not.toContain('Zdroj:')

    // Entire card links to event detail
    expect(wrapper.find('a[href="/events/42"]').exists()).toBe(true)
  })

  it('uses bundled payload and skips standalone API fetch', async () => {
    const wrapper = mount(NextEventWidget, {
      props: {
        initialPayload: {
          data: {
            id: 7,
            title: 'Zatmenie',
            type: 'eclipse_solar',
            start_at: '2026-03-01T10:00:00Z',
            updated_at: '2026-02-16T11:40:00Z',
            source: {
              name: 'nasa',
            },
          },
        },
      },
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

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Zatmenie Slnka')
    expect(wrapper.find('a[href="/events/7"]').exists()).toBe(true)
  })
})
