import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import OnboardingModal from './OnboardingModal.vue'

vi.mock('@/services/events', () => ({
  searchOnboardingLocations: vi.fn(async () => ({ data: { data: [] } })),
}))

function normalizeText(value = '') {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

function findButtonByLabel(wrapper, label) {
  const normalizedLabel = normalizeText(label)
  return wrapper.findAll('button').find((button) => normalizeText(button.text()) === normalizedLabel)
}

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
    expect(normalizeText(wrapper.text())).toContain('zaujmy menia to, co ti aplikacia prioritne ukazuje.')

    const nextButton = findButtonByLabel(wrapper, 'Ďalej')
    expect(nextButton).toBeTruthy()
    if (!nextButton) {
      throw new Error('Next button not found')
    }

    await nextButton.trigger('click')
    await nextTick()

    expect(normalizeText(wrapper.text())).toContain('lokalita spravi widgety skutocne uzitocne.')
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

    const nextButton = findButtonByLabel(wrapper, 'Ďalej')
    expect(nextButton).toBeTruthy()
    if (!nextButton) {
      throw new Error('Next button not found')
    }

    await nextButton.trigger('click')
    await nextTick()

    const finishButton = findButtonByLabel(wrapper, 'Dokončiť')
    expect(finishButton).toBeTruthy()
    if (!finishButton) {
      throw new Error('Finish button not found')
    }

    await finishButton.trigger('click')

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