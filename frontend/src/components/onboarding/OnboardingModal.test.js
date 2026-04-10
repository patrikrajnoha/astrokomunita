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
      widgetCatalog: [
        { key: 'search', label: 'Hľadaj', description: 'Rýchle vyhľadávanie obsahu.' },
        { key: 'nasa_apod', label: 'Astrofoto dňa', description: 'Denná dávka astrofotografie.' },
        { key: 'next_event', label: 'Najbližšia udalosť', description: 'Najbližšie udalosti na oblohe.' },
        { key: 'latest_articles', label: 'Astro články', description: 'Nové články a tipy na pozorovanie.' },
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

  it('shows widget showcase and updates copy after moving to location step', async () => {
    const wrapper = mountModal({
      initialWidgetKeys: ['search', 'nasa_apod', 'next_event'],
    })

    expect(wrapper.find('.onbShowcasePreview').exists()).toBe(true)
    expect(normalizeText(wrapper.text())).toContain('zacni s widgetmi, ktore naozaj pouzivas.')

    const nextButton = findButtonByLabel(wrapper, 'Ďalej')
    expect(nextButton).toBeTruthy()
    if (!nextButton) {
      throw new Error('Next button not found')
    }

    await nextButton.trigger('click')
    await nextTick()

    expect(normalizeText(wrapper.text())).toContain('lokalita zlepsi presnost widgetov.')
    expect(wrapper.find('.onbShowcasePreview').exists()).toBe(true)
  })

  it('emits finish payload with selected widgets and location', async () => {
    const wrapper = mountModal({
      initialWidgetKeys: ['search', 'nasa_apod', 'next_event'],
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
      sidebar_widget_keys: ['search', 'nasa_apod', 'next_event'],
      sidebar_widget_overrides: {
        home: ['search', 'nasa_apod', 'next_event'],
      },
      location_label: 'Bratislava',
      location_place_id: 'bratislava',
      location_lat: 48.1486,
      location_lon: 17.1077,
    })
  })
})
