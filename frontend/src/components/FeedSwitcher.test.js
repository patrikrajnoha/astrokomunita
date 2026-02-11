import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FeedSwitcher from '@/components/FeedSwitcher.vue'

class ResizeObserverMock {
  observe() {}
  disconnect() {}
}

describe('FeedSwitcher', () => {
  beforeEach(() => {
    vi.stubGlobal('ResizeObserver', ResizeObserverMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('updates aria-selected and emits tab change', async () => {
    const wrapper = mount(FeedSwitcher, {
      props: {
        modelValue: 'for_you',
        tabs: [
          { id: 'for_you', label: 'Pre vas', tabId: 'tab-for-you', panelId: 'panel-for-you' },
          { id: 'astrobot', label: 'AstroBot', tabId: 'tab-astrobot', panelId: 'panel-astrobot' },
        ],
      },
      attachTo: document.body,
    })

    const first = wrapper.get('#tab-for-you')
    const second = wrapper.get('#tab-astrobot')

    expect(first.attributes('aria-selected')).toBe('true')
    expect(second.attributes('aria-selected')).toBe('false')

    await second.trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0][0]).toBe('astrobot')
  })

  it('shows arrow buttons only when strip overflows', async () => {
    const wrapper = mount(FeedSwitcher, {
      props: {
        modelValue: 'for_you',
        tabs: [
          { id: 'for_you', label: 'Pre vas', tabId: 'tab-for-you', panelId: 'panel-for-you' },
          { id: 'astrobot', label: 'AstroBot', tabId: 'tab-astrobot', panelId: 'panel-astrobot' },
        ],
      },
      attachTo: document.body,
    })

    const strip = wrapper.get('[data-testid="tab-strip"]').element

    Object.defineProperty(strip, 'clientWidth', { value: 200, configurable: true })
    Object.defineProperty(strip, 'scrollWidth', { value: 500, configurable: true })
    Object.defineProperty(strip, 'scrollLeft', { value: 0, writable: true, configurable: true })

    strip.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.find('[data-testid="strip-arrow-left"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="strip-arrow-right"]').exists()).toBe(true)

    strip.scrollLeft = 120
    strip.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.find('[data-testid="strip-arrow-left"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="strip-arrow-right"]').exists()).toBe(true)

    Object.defineProperty(strip, 'clientWidth', { value: 600, configurable: true })
    strip.scrollLeft = 0
    strip.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.find('[data-testid="strip-arrow-left"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="strip-arrow-right"]').exists()).toBe(false)
  })
})
