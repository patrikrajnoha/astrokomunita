import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import OnboardingModal from './OnboardingModal.vue'

vi.mock('@/services/events', () => ({
  searchOnboardingLocations: vi.fn(async () => ({ data: { data: [] } })),
}))

function mountModal(props = {}) {
  return mount(OnboardingModal, {
    props: {
      interestsCatalog: [
        { key: 'meteory', label: 'Meteory' },
        { key: 'mesiac', label: 'Mesiac' },
      ],
      ...props,
    },
    attachTo: document.body,
  })
}

describe('OnboardingModal', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('shows widget showcase and updates the copy when moving to location step', async () => {
    const wrapper = mountModal({
      initialInterests: ['meteory'],
    })

    expect(wrapper.find('.widgetPreview').exists()).toBe(true)
    expect(wrapper.text()).toContain('Zaujmy menia to, co ti aplikacia prioritne ukazuje.')

    await wrapper.get('.btnPrimary').trigger('click')
    await nextTick()

    expect(wrapper.text()).toContain('Lokalita spravi widgety skutocne uzitocne.')
    expect(wrapper.find('.widgetPreview').exists()).toBe(true)
  })

  it('emits finish payload after completing both steps', async () => {
    const wrapper = mountModal({
      initialInterests: ['meteory'],
      initialLocation: {
        location_label: 'Bratislava',
        location_place_id: 'bratislava',
        location_lat: 48.1486,
        location_lon: 17.1077,
      },
    })

    await wrapper.get('.btnPrimary').trigger('click')
    await nextTick()
    await wrapper.get('.btnPrimary').trigger('click')

    expect(wrapper.emitted('finish')).toHaveLength(1)
    expect(wrapper.emitted('finish')[0][0]).toEqual({
      interests: ['meteory'],
      location_label: 'Bratislava',
      location_place_id: 'bratislava',
      location_lat: 48.1486,
      location_lon: 17.1077,
    })
  })
})
