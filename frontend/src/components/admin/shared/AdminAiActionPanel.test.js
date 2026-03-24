import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminAiActionPanel from './AdminAiActionPanel.vue'

describe('AdminAiActionPanel', () => {
  it('normalizes unknown status to idle and hides idle status pill and empty meta', () => {
    const wrapper = mount(AdminAiActionPanel, {
      props: {
        title: 'AI pomocnik',
        actionLabel: 'Spustit',
        enabled: true,
        status: 'UNKNOWN_STATUS',
      },
    })

    // Idle status is hidden — "Pripravené" is visual noise when nothing ran yet
    expect(wrapper.text()).not.toContain('Pripravené')
    // Meta row is hidden when there's no last-run data
    expect(wrapper.text()).not.toContain('Posledný beh:')
    expect(wrapper.text()).not.toContain('Odozva:')
    // Title and action button are present
    expect(wrapper.text()).toContain('AI pomocnik')
    expect(wrapper.find('button').text()).toContain('Spustit')
  })

  it('shows advanced collapsible with debug data and emits run on button click in error state', async () => {
    const wrapper = mount(AdminAiActionPanel, {
      props: {
        title: 'AI pomocnik',
        actionLabel: 'Spustit',
        enabled: true,
        status: 'error',
        errorMessage: 'Chyba behu',
        retryCount: 2,
        rawStatusCode: 429,
      },
      slots: {
        advanced: '<p data-testid="advanced-slot">detail</p>',
      },
    })

    expect(wrapper.text()).toContain('Rozšírené')
    expect(wrapper.text()).toContain('retry_count: 2')
    expect(wrapper.text()).toContain('status_code: 429')
    expect(wrapper.find('[data-testid="advanced-slot"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Chyba behu')

    // Main button handles retry (no separate "Skúsiť znova" button)
    const runButton = wrapper.find('button')
    await runButton.trigger('click')
    expect(wrapper.emitted('run')).toBeTruthy()
  })

  it('shows loading progress percent in button text and disables the button', () => {
    const wrapper = mount(AdminAiActionPanel, {
      props: {
        title: 'AI pomocnik',
        actionLabel: 'Spustit',
        enabled: true,
        status: 'idle',
        isLoading: true,
        progressPercent: 42,
      },
    })

    expect(wrapper.text()).toContain('Prebieha... 42%')
    const runButton = wrapper.find('button')
    expect(runButton.attributes('disabled')).toBeDefined()
  })
})
