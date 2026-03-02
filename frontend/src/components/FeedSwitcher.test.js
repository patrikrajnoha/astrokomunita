import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FeedSwitcher from '@/components/FeedSwitcher.vue'

class ResizeObserverMock {
  observe() {}
  disconnect() {}
}

const tabs = [
  { id: 'for_you', label: 'Komunita', tabId: 'tab-for-you', panelId: 'panel-for-you' },
  { id: 'astrobot', label: 'AstroFeed', tabId: 'tab-astrobot', panelId: 'panel-astrobot' },
]

describe('FeedSwitcher', () => {
  beforeEach(() => {
    vi.stubGlobal('ResizeObserver', ResizeObserverMock)
    Object.defineProperty(window, 'scrollY', {
      value: 0,
      writable: true,
      configurable: true,
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('updates aria-selected and emits tab change', async () => {
    const wrapper = mount(FeedSwitcher, {
      props: {
        modelValue: 'for_you',
        tabs,
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

  it('moves single ink bar when active tab changes', async () => {
    const wrapper = mount(FeedSwitcher, {
      props: {
        modelValue: 'for_you',
        tabs,
      },
      attachTo: document.body,
    })

    const firstButton = wrapper.get('#tab-for-you').element
    const secondButton = wrapper.get('#tab-astrobot').element
    const labels = wrapper.findAll('button[role="tab"] > span')

    Object.defineProperty(firstButton, 'offsetWidth', { value: 180, configurable: true })
    Object.defineProperty(firstButton, 'offsetLeft', { value: 0, configurable: true })
    Object.defineProperty(secondButton, 'offsetWidth', { value: 180, configurable: true })
    Object.defineProperty(secondButton, 'offsetLeft', { value: 180, configurable: true })
    Object.defineProperty(labels[0].element, 'offsetWidth', { value: 62, configurable: true })
    Object.defineProperty(labels[1].element, 'offsetWidth', { value: 74, configurable: true })

    window.dispatchEvent(new Event('resize'))
    await nextTick()
    await nextTick()

    const inkBar = wrapper.get('[data-testid="feed-tabs-ink-bar"]')
    const initialStyle = inkBar.attributes('style')
    expect(initialStyle).toContain('translateX(')
    expect(initialStyle).toContain('width:')

    await wrapper.setProps({ modelValue: 'astrobot' })
    Object.defineProperty(secondButton, 'offsetWidth', { value: 240, configurable: true })
    Object.defineProperty(secondButton, 'offsetLeft', { value: 520, configurable: true })
    Object.defineProperty(labels[1].element, 'offsetWidth', { value: 82, configurable: true })
    window.dispatchEvent(new Event('resize'))
    await nextTick()
    await nextTick()

    const movedStyle = wrapper.get('[data-testid="feed-tabs-ink-bar"]').attributes('style')
    expect(movedStyle).toContain('translateX(')
    expect(movedStyle).not.toBe(initialStyle)
  })

  it('reveals the sticky divider state when the page is scrolled', async () => {
    const wrapper = mount(FeedSwitcher, {
      props: {
        modelValue: 'for_you',
        tabs,
      },
      attachTo: document.body,
    })

    const sticky = wrapper.get('[data-testid="feed-tabs-sticky"]')

    expect(sticky.classes()).toContain('shadow-none')

    window.scrollY = 28
    Object.defineProperty(document.documentElement, 'scrollTop', {
      value: 28,
      writable: true,
      configurable: true,
    })
    window.dispatchEvent(new Event('scroll'))
    await nextTick()
    await nextTick()

    expect(sticky.classes()).toContain('bg-[rgb(var(--color-bg-rgb)/0.96)]')
  })
})
