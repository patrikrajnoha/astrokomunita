import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import OnboardingView from './OnboardingView.vue'

const mockPreferences = vi.hoisted(() => ({
  supportedInterests: [],
  interests: [],
  locationLabel: '',
  locationPlaceId: '',
  locationLat: null,
  locationLon: null,
  isOnboardingCompleted: false,
  fetchPreferences: vi.fn(async () => {}),
  ensureInterestsLoaded: vi.fn(async () => []),
  saveOnboarding: vi.fn(async () => {}),
  markOnboardingComplete: vi.fn(async () => {}),
}))
const mockOnboardingTour = vi.hoisted(() => ({
  restartTour: vi.fn(),
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => mockPreferences,
}))

vi.mock('@/stores/onboardingTour', () => ({
  useOnboardingTourStore: () => mockOnboardingTour,
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/onboarding', name: 'onboarding', component: OnboardingView },
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
    ],
  })
}

describe('OnboardingView', () => {
  beforeEach(() => {
    mockPreferences.isOnboardingCompleted = false
    mockPreferences.fetchPreferences.mockClear()
    mockPreferences.ensureInterestsLoaded.mockClear()
    mockPreferences.saveOnboarding.mockClear()
    mockPreferences.markOnboardingComplete.mockClear()
    mockOnboardingTour.restartTour.mockClear()
  })

  it('completing onboarding saves preferences once and redirects home', async () => {
    const router = makeRouter()
    await router.push('/onboarding?redirect=/')
    await router.isReady()

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [router],
        stubs: {
          OnboardingModal: {
            template: '<button class="finish" @click="$emit(\'finish\', { interests: [\'meteory\'], location_label: \'Bratislava, Slovensko\' })">finish</button>',
          },
        },
      },
    })

    await wrapper.find('.finish').trigger('click')

    expect(mockPreferences.saveOnboarding).toHaveBeenCalledTimes(1)
    await vi.waitFor(() => {
      expect(router.currentRoute.value.name).toBe('home')
    })
  })

  it('skip marks onboarding complete and redirects home', async () => {
    const router = makeRouter()
    await router.push('/onboarding?redirect=/')
    await router.isReady()

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [router],
        stubs: {
          OnboardingModal: {
            template: '<button class="skip" @click="$emit(\'skip\')">skip</button>',
          },
        },
      },
    })

    await wrapper.find('.skip').trigger('click')

    expect(mockPreferences.markOnboardingComplete).toHaveBeenCalledTimes(1)
    await vi.waitFor(() => {
      expect(router.currentRoute.value.name).toBe('home')
    })
  })

  it('restarts onboarding tour when start_tour query is enabled', async () => {
    const router = makeRouter()
    await router.push('/onboarding?redirect=/&start_tour=1')
    await router.isReady()

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [router],
        stubs: {
          OnboardingModal: {
            template: '<button class="finish" @click="$emit(\'finish\', { interests: [] })">finish</button>',
          },
        },
      },
    })

    await wrapper.find('.finish').trigger('click')

    await vi.waitFor(() => {
      expect(router.currentRoute.value.name).toBe('home')
    })
    expect(mockOnboardingTour.restartTour).toHaveBeenCalledTimes(1)
  })
})
