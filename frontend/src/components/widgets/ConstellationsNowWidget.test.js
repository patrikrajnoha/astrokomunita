import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ConstellationsNowWidget from './ConstellationsNowWidget.vue'

const getSidebarWidgetBundleMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/widgets', () => ({
  getSidebarWidgetBundle: getSidebarWidgetBundleMock,
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('ConstellationsNowWidget', () => {
  beforeEach(() => {
    getSidebarWidgetBundleMock.mockReset()
    getSidebarWidgetBundleMock.mockResolvedValue({
      requested_sections: ['constellations_now'],
      data: {
        constellations_now: {
          available: true,
          items: [
            { name: 'Orion', display_name: 'Orion', direction: 'juhovychod', best_time: '20:00-22:00', visibility_level: 'high', visibility_text: 'Lahko viditeľné' },
            { name: 'Gemini', display_name: 'Blizenci', direction: 'vychod', best_time: '20:30-23:00', visibility_level: 'high', visibility_text: 'Lahko viditeľné' },
            { name: 'Taurus', display_name: 'Byk', direction: 'juhovychod', best_time: '20:00-22:00', visibility_level: 'medium', visibility_text: 'Stredne viditeľné' },
            { name: 'Auriga', display_name: 'Vozka', direction: 'severovychod', best_time: 'cely vecer', visibility_level: 'high', visibility_text: 'Lahko viditeľné' },
            { name: 'Canis Major', display_name: 'Velky pes', direction: 'juh', best_time: '21:00-23:00', visibility_level: 'medium', visibility_text: 'Stredne viditeľné' },
            { name: 'Cassiopeia', display_name: 'Kasiopeja', direction: 'sever', best_time: 'cely vecer', visibility_level: 'high', visibility_text: 'Velmi dobré viditeľné' },
          ],
          meta: {
            location_label: 'Slovensko (default)',
            reference_month_label: 'januar',
            reference_date: '2026-01-15',
            evening_cloud_percent: 84,
          },
        },
      },
    })
  })

  it('fetches data through bundled sidebar endpoint and renders at most four rows', async () => {
    const wrapper = mount(ConstellationsNowWidget)

    await flushPromises()
    await nextTick()

    expect(getSidebarWidgetBundleMock).toHaveBeenCalledTimes(1)
    expect(getSidebarWidgetBundleMock).toHaveBeenCalledWith(['constellations_now'])
    expect(wrapper.findAll('.constellationRow')).toHaveLength(4)
    expect(wrapper.find('.cloudNotice').exists()).toBe(true)
    expect(wrapper.find('.cloudNotice').classes()).toContain('cloudNotice--poor')
    expect(wrapper.text()).toContain('84 %')
    expect(wrapper.text()).toContain('Orion')
    expect(wrapper.text()).toContain('2026')
  })

  it('uses bundled initial payload and skips fetch on mount', async () => {
    const wrapper = mount(ConstellationsNowWidget, {
      props: {
        initialPayload: {
          available: true,
          items: [
            {
              name: 'Leo',
              display_name: 'Lev',
              direction: 'vychod',
              best_time: 'po 21:30',
              visibility_level: 'medium',
              visibility_text: 'Stredne viditeľné',
            },
          ],
          meta: {
            location_label: 'Slovensko (default)',
            reference_month_label: 'marec',
            reference_date: '2026-03-21',
            evening_cloud_percent: 40,
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(getSidebarWidgetBundleMock).not.toHaveBeenCalled()
    expect(wrapper.findAll('.constellationRow')).toHaveLength(1)
    expect(wrapper.find('.cloudNotice').exists()).toBe(false)
    expect(wrapper.text()).toContain('Lev')
  })
})
