import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AsyncState from './AsyncState.vue'

describe('AsyncState', () => {
  it('renders loading mode with spinner', () => {
    const wrapper = mount(AsyncState, {
      props: {
        mode: 'loading',
        title: 'Nacitavam...',
      },
    })

    expect(wrapper.text()).toContain('Nacitavam...')
    expect(wrapper.find('[data-testid="async-state-spinner"]').exists()).toBe(true)
  })

  it('renders error state and emits action', async () => {
    const wrapper = mount(AsyncState, {
      props: {
        mode: 'error',
        title: 'Nastala chyba',
        message: 'Nepodarilo sa nacitat data.',
        actionLabel: 'Skusit znova',
      },
    })

    expect(wrapper.attributes('role')).toBe('alert')
    expect(wrapper.text()).toContain('Nepodarilo sa nacitat data.')

    await wrapper.get('[data-testid="async-state-action"]').trigger('click')
    expect(wrapper.emitted('action')).toBeTruthy()
    expect(wrapper.emitted('action').length).toBe(1)
  })
})
