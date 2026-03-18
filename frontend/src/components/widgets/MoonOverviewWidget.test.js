import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import MoonOverviewWidget from './MoonOverviewWidget.vue'

const getMoonOverviewWidgetMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/widgets', () => ({
  getMoonOverviewWidget: getMoonOverviewWidgetMock,
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('MoonOverviewWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-17T21:00:00Z'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders phase emoji, name, illumination, visibility and next event', async () => {
    getMoonOverviewWidgetMock.mockResolvedValue({
      moon_phase: 'waning_crescent',
      moon_illumination_percent: 3,
      moon_altitude_deg: 12.5,
      next_new_moon_at: '2026-03-29T08:58:00Z',
      next_full_moon_at: '2026-04-13T00:22:00Z',
    })

    const wrapper = mount(MoonOverviewWidget)
    await flushPromises()
    await nextTick()

    // Phase emoji (waning crescent)
    expect(wrapper.text()).toContain('🌘')

    // Phase name — proper Slovak with diacritics
    expect(wrapper.text()).toContain('Ubúdajúci kosáčik')

    // Illumination
    expect(wrapper.text()).toContain('3%')

    // Visibility — altitude > 0 → above horizon
    expect(wrapper.text()).toContain('Nad obzorom')

    // Next event — new moon (29. mar) is closer than full moon (13. apr)
    expect(wrapper.text()).toContain('Nov')
    expect(wrapper.text()).toContain('29.')

    // Removed: technical data rows
    expect(wrapper.text()).not.toContain('Smer Mesiaca')
    expect(wrapper.text()).not.toContain('Výška Mesiaca')
    expect(wrapper.text()).not.toContain('Vzdialenosť')
    expect(wrapper.text()).not.toContain('Aktuálny čas')
  })

  it('shows "Pod horizontom" when altitude is negative', async () => {
    getMoonOverviewWidgetMock.mockResolvedValue({
      moon_phase: 'full_moon',
      moon_illumination_percent: 100,
      moon_altitude_deg: -18.3,
      next_new_moon_at: '2026-03-29T08:58:00Z',
      next_full_moon_at: '2026-04-13T00:22:00Z',
    })

    const wrapper = mount(MoonOverviewWidget)
    await flushPromises()
    await nextTick()

    expect(wrapper.text()).toContain('🌕')
    expect(wrapper.text()).toContain('Spln')
    expect(wrapper.text()).toContain('100%')
    expect(wrapper.text()).toContain('Pod horizontom')
  })

  it('shows "Nízko nad obzorom" for altitude between 0 and 5', async () => {
    getMoonOverviewWidgetMock.mockResolvedValue({
      moon_phase: 'first_quarter',
      moon_illumination_percent: 52,
      moon_altitude_deg: 2.4,
      next_new_moon_at: '2026-03-29T08:58:00Z',
      next_full_moon_at: '2026-04-13T00:22:00Z',
    })

    const wrapper = mount(MoonOverviewWidget)
    await flushPromises()
    await nextTick()

    expect(wrapper.text()).toContain('Nízko nad obzorom')
  })

  it('shows full moon as next event when it comes before new moon', async () => {
    getMoonOverviewWidgetMock.mockResolvedValue({
      moon_phase: 'waxing_gibbous',
      moon_illumination_percent: 75,
      moon_altitude_deg: 45,
      next_new_moon_at: '2026-04-27T19:31:00Z',
      next_full_moon_at: '2026-04-13T00:22:00Z',
    })

    const wrapper = mount(MoonOverviewWidget)
    await flushPromises()
    await nextTick()

    expect(wrapper.text()).toContain('Spln')
    expect(wrapper.text()).toContain('13.')
  })

  it('shows error state and retry button on fetch failure', async () => {
    getMoonOverviewWidgetMock.mockRejectedValue(new Error('Network error'))

    const wrapper = mount(MoonOverviewWidget)
    await flushPromises()
    await nextTick()

    expect(wrapper.text()).toContain('Network error')
    expect(wrapper.find('button').text()).toContain('Skúsiť znova')
  })
})
