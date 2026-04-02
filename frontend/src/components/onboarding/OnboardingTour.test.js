import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { nextTick } from 'vue'
import OnboardingTour from './OnboardingTour.vue'
import { ONBOARDING_TOUR_STORAGE_KEY, useOnboardingTourStore } from '@/stores/onboardingTour'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
      { path: '/events', name: 'events', component: { template: '<div>events</div>' } },
    ],
  })
}

function appendTourTarget(id) {
  const element = document.createElement('div')
  element.setAttribute('data-tour', id)
  element.style.display = 'block'
  element.style.visibility = 'visible'
  element.getBoundingClientRect = () => ({
    top: 48,
    left: 64,
    width: 300,
    height: 180,
    right: 364,
    bottom: 228,
  })
  element.scrollIntoView = vi.fn()
  document.body.appendChild(element)
  return element
}

function normalizeText(value = '') {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

async function mountTour(startStep = 0) {
  const pinia = createPinia()
  setActivePinia(pinia)

  const store = useOnboardingTourStore()
  store.restartTour()
  store.openTour({ force: true, startStep })

  const router = makeRouter()
  await router.push('/')
  await router.isReady()

  const wrapper = mount(OnboardingTour, {
    global: {
      plugins: [pinia, router],
    },
    attachTo: document.body,
  })

  await nextTick()
  await vi.waitFor(() => {
    expect(wrapper.find('.tourTitle').exists()).toBe(true)
  })

  return wrapper
}

describe('OnboardingTour', () => {
  beforeEach(() => {
    window.localStorage.removeItem(ONBOARDING_TOUR_STORAGE_KEY)
    appendTourTarget('feed')
    appendTourTarget('conditions')
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders rotating widget preview on the conditions step', async () => {
    const wrapper = await mountTour(2)

    expect(normalizeText(wrapper.get('.tourTitle').text())).toBe('pozorovacie podmienky')
    expect(wrapper.find('.widgetPreview').exists()).toBe(true)
    expect(wrapper.findAll('.widgetSlide').length).toBeGreaterThanOrEqual(3)

    const text = normalizeText(wrapper.text())
    expect(text).toContain('pocasie')
    expect(text).toContain('widgety')
  })

  it('does not render widget preview on non-widget steps', async () => {
    const wrapper = await mountTour(0)

    expect(normalizeText(wrapper.get('.tourTitle').text())).toBe('komunitny feed')
    expect(wrapper.find('.widgetPreview').exists()).toBe(false)
  })
})