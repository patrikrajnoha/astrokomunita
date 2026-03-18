import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import UpcomingLaunchesWidget from './UpcomingLaunchesWidget.vue'

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

describe('UpcomingLaunchesWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders bundled launch payload without an extra API fetch', async () => {
    const wrapper = mount(UpcomingLaunchesWidget, {
      props: {
        initialPayload: {
          available: true,
          updated_at: '2026-03-15T09:55:00Z',
          source: {
            label: 'The Space Devs Launch Library 2',
          },
          items: [
            {
              id: 'launch-1',
              name: 'Falcon 9 Block 5 | Starlink Group 17-24',
              net: '2026-03-16T19:00:00Z',
              provider: 'SpaceX',
              pad: 'SLC-40',
              location: 'Cape Canaveral, FL, USA',
              mission_name: 'Starlink Group 17-24',
              status: {
                abbrev: 'Go',
              },
            },
          ],
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Falcon 9 Block 5')
    expect(wrapper.text()).not.toContain('Starlink Group 17-24')
    expect(wrapper.text()).toContain('SpaceX')
  })

  it('fetches launches when no bundle payload is provided', async () => {
    getMock.mockResolvedValue({
      data: {
        available: true,
        updated_at: '2026-03-15T09:55:00Z',
        source: {
          label: 'The Space Devs Launch Library 2',
        },
        items: [
          {
            id: 'launch-2',
            name: 'Long March 6A | Unknown Payload',
            net: '2026-03-15T13:20:00Z',
            provider: 'CASC',
            pad: 'Launch Complex 9A',
            status: {
              abbrev: 'Go',
            },
          },
        ],
      },
    })

    const wrapper = mount(UpcomingLaunchesWidget)

    await flushPromises()
    await nextTick()

    expect(getMock).toHaveBeenCalledWith('/sky/upcoming-launches', {
      meta: { skipErrorToast: true },
    })
    expect(wrapper.text()).toContain('Long March 6A')
    expect(wrapper.text()).not.toContain('Unknown Payload')
    expect(wrapper.text()).toContain('CASC')
  })
})
