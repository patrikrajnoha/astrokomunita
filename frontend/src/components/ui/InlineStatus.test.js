import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import InlineStatus from './InlineStatus.vue'

describe('InlineStatus', () => {
  it('renders success message with status role', () => {
    const wrapper = mount(InlineStatus, {
      props: {
        variant: 'success',
        message: 'Ulozenie prebehlo uspesne.',
      },
    })

    expect(wrapper.attributes('role')).toBe('status')
    expect(wrapper.text()).toContain('Ulozenie prebehlo uspesne.')
  })

  it('renders error variant and emits action', async () => {
    const wrapper = mount(InlineStatus, {
      props: {
        variant: 'error',
        message: 'Nastala chyba.',
        actionLabel: 'Skusit znova',
      },
    })

    expect(wrapper.attributes('role')).toBe('alert')
    await wrapper.get('[data-testid="inline-status-action"]').trigger('click')
    expect(wrapper.emitted('action')).toBeTruthy()
  })
})
