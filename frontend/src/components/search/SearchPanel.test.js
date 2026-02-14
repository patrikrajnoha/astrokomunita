import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import SearchPanel from '@/components/search/SearchPanel.vue'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('SearchPanel', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    api.get.mockReset()
  })

  afterEach(() => {
    vi.runOnlyPendingTimers()
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('shows suggestions on typing and selects first suggestion with Enter', async () => {
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          { id: 1, name: 'Marek Nova', username: 'marek' },
          { id: 2, name: 'Luna Sky', username: 'luna' },
        ],
      },
    })

    const wrapper = mount(SearchPanel, {
      props: {
        modelValue: '',
        mode: 'users',
      },
      attachTo: document.body,
    })

    const input = wrapper.get('input')
    await input.trigger('focus')
    await input.setValue('ma')

    vi.advanceTimersByTime(320)
    await flushPromises()
    await nextTick()

    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
    expect(wrapper.findAll('[role="option"]').length).toBe(2)

    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })

    const submits = wrapper.emitted('submit') || []
    expect(submits.length).toBeGreaterThan(0)
    expect(submits[submits.length - 1]).toEqual(['marek'])
  })
})
