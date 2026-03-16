import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsChart from '@/components/admin/dashboard/StatsChart.vue'

describe('StatsChart', () => {
  it('renders an svg trend line and points for chart data', () => {
    const wrapper = mount(StatsChart, {
      props: {
        metricKey: 'new_posts',
        points: [
          { date: '2026-02-14', new_posts: 2 },
          { date: '2026-02-23', new_posts: 6 },
          { date: '2026-03-04', new_posts: 4 },
          { date: '2026-03-15', new_posts: 9 },
        ],
      },
    })

    expect(wrapper.find('svg.chartSvg').exists()).toBe(true)
    expect(wrapper.find('path.chartLine').exists()).toBe(true)
    expect(wrapper.findAll('circle.chartPoint')).toHaveLength(4)
    expect(wrapper.text()).toContain('14.02.')
    expect(wrapper.text()).toContain('15.03.')
  })

  it('renders an empty state when no points are available', () => {
    const wrapper = mount(StatsChart, {
      props: {
        metricKey: 'new_posts',
        points: [],
      },
    })

    expect(wrapper.text()).toContain('Trend nie je dostupny.')
    expect(wrapper.find('svg.chartSvg').exists()).toBe(false)
  })
})
