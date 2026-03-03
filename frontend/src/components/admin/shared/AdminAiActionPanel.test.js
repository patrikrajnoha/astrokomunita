import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminAiActionPanel from './AdminAiActionPanel.vue'

describe('AdminAiActionPanel', () => {
  it('normalizes unknown status to idle and always renders last run + latency meta', () => {
    const wrapper = mount(AdminAiActionPanel, {
      props: {
        title: 'AI pomocnik',
        actionLabel: 'Spustit',
        enabled: true,
        status: 'UNKNOWN_STATUS',
      },
    })

    expect(wrapper.text()).toContain('Pripravené')
    expect(wrapper.text()).toContain('Posledný beh:')
    expect(wrapper.text()).toContain('Odozva:')
  })

  it('shows advanced collapsible and allows retry from error state', async () => {
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

    const retryButton = wrapper.findAll('button').find((button) => button.text().includes('Skúsiť znova'))
    expect(retryButton).toBeTruthy()
    await retryButton.trigger('click')

    expect(wrapper.emitted('run')).toBeTruthy()
  })
})
