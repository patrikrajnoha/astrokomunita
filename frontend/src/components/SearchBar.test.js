import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { reactive, nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import SearchBar from '@/components/SearchBar.vue'
import api from '@/services/api'

const pushMock = vi.fn(() => Promise.resolve())
const routeState = reactive({
  name: 'home',
  query: {},
})

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => routeState,
}))

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

const mountSearchBar = () => {
  return mount(SearchBar, {
    attachTo: document.body,
  })
}

const focusAndType = async (wrapper, value, suggestions = []) => {
  api.get.mockResolvedValueOnce({ data: { data: suggestions } })

  const input = wrapper.get('input')
  await input.trigger('focus')
  await input.setValue(value)

  vi.advanceTimersByTime(210)
  await flushPromises()
  await nextTick()
}

describe('SearchBar', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    pushMock.mockClear()
    api.get.mockReset()
    routeState.name = 'home'
    routeState.query = {}
  })

  afterEach(() => {
    vi.runOnlyPendingTimers()
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('opens dropdown for input with at least 2 characters', async () => {
    const wrapper = mountSearchBar()

    await focusAndType(wrapper, 'ma', [
      { id: '1', type: 'user', label: 'Marek (@marek)', value: 'marek' },
      { id: '2', type: 'tag', label: '#mars', value: '#mars' },
    ])

    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
    expect(wrapper.findAll('[role="option"]').length).toBe(2)
  })

  it('supports ArrowDown and Enter to select active suggestion', async () => {
    const wrapper = mountSearchBar()

    await focusAndType(wrapper, 'ma', [
      { id: '1', type: 'user', label: 'Marek (@marek)', value: 'marek' },
      { id: '2', type: 'tag', label: '#mars', value: '#mars' },
    ])

    const input = wrapper.get('input')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({
      name: 'search',
      query: { q: 'marek' },
    })
  })

  it('closes dropdown on Escape', async () => {
    const wrapper = mountSearchBar()

    await focusAndType(wrapper, 'ma', [
      { id: '1', type: 'user', label: 'Marek (@marek)', value: 'marek' },
    ])

    const input = wrapper.get('input')
    await input.trigger('keydown', { key: 'Escape' })
    await nextTick()

    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })

  it('closes dropdown on outside click', async () => {
    const wrapper = mountSearchBar()

    await focusAndType(wrapper, 'ma', [
      { id: '1', type: 'user', label: 'Marek (@marek)', value: 'marek' },
    ])

    document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    await nextTick()

    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })
})
