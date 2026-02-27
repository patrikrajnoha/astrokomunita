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

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => mockPreferences,
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
})
