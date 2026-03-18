import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import NeoWatchlistWidget from './NeoWatchlistWidget.vue'

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

describe('NeoWatchlistWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders bundled NEO payload without an extra API fetch', async () => {
    const wrapper = mount(NeoWatchlistWidget, {
      props: {
        initialPayload: {
          available: true,
          updated_at: '2026-03-15T20:30:00Z',
          source: {
            label: 'NASA JPL SBDB',
          },
          items: [
            {
              name: '99942 Apophis',
              designation: '99942',
              orbit_class_label: 'Apollo',
              pha: true,
              moid_au: 0.00026,
              diameter_km: 0.37,
            },
          ],
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('99942 Apophis')
    expect(wrapper.text()).toContain('Veľmi blízko')
    expect(wrapper.text()).toContain('0.0003 AU')
    expect(wrapper.text()).toContain('Apollo')
  })

  it('fetches the watchlist when no bundle payload is provided', async () => {
    getMock.mockResolvedValue({
      data: {
        available: true,
        updated_at: '2026-03-15T20:30:00Z',
        source: {
          label: 'NASA JPL SBDB',
        },
        items: [
          {
            name: '2001 FO32',
            designation: '2001 FO32',
            orbit_class_label: 'Apollo',
            pha: false,
            moid_au: 0.0035,
            diameter_km: 0.97,
          },
        ],
      },
    })

    const wrapper = mount(NeoWatchlistWidget)

    await flushPromises()
    await nextTick()

    expect(getMock).toHaveBeenCalledWith('/sky/neo-watchlist', {
      meta: { skipErrorToast: true },
    })
    expect(wrapper.text()).toContain('2001 FO32')
    expect(wrapper.text()).toContain('Blízko')
    expect(wrapper.text()).toContain('0.0035 AU')
    expect(wrapper.text()).toContain('Apollo')
  })
})
