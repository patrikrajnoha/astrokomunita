import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TypingText from '@/components/TypingText.vue'

describe('TypingText', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('types full text and emits done', async () => {
    const wrapper = mount(TypingText, {
      props: {
        text: 'Ahoj',
        speedMs: 20,
        startDelayMs: 0,
      },
    })

    await vi.advanceTimersByTimeAsync(120)

    expect(wrapper.text()).toContain('Ahoj')
    expect(wrapper.emitted('done')).toHaveLength(1)
  })

  it('restarts typing when text prop changes', async () => {
    const wrapper = mount(TypingText, {
      props: {
        text: 'Ahoj',
        speedMs: 20,
        startDelayMs: 0,
      },
    })

    await vi.advanceTimersByTimeAsync(120)
    await wrapper.setProps({ text: 'Ahoj Rajno' })
    await vi.advanceTimersByTimeAsync(240)

    expect(wrapper.text()).toContain('Ahoj Rajno')
    expect(wrapper.emitted('done')).toHaveLength(2)
  })
})
