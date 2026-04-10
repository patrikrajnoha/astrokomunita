import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import OnboardingView from './OnboardingView.vue'

const mockPreferences = vi.hoisted(() => ({
  supportedSidebarWidgets: [],
  sidebarWidgetOverrides: {},
  sidebarWidgetKeys: [],
  locationLabel: '',
  locationPlaceId: '',
  locationLat: null,
  locationLon: null,
  isOnboardingCompleted: false,
  fetchPreferences: vi.fn(async () => {}),
  saveOnboarding: vi.fn(async () => {}),
  markOnboardingComplete: vi.fn(async () => {}),
}))
const mockSidebarConfigStore = vi.hoisted(() => ({
  fetchScope: vi.fn(async () => []),
}))
const mockOnboardingTour = vi.hoisted(() => ({
  restartTour: vi.fn(),
}))
const warnMock = vi.hoisted(() => vi.fn())

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => mockPreferences,
}))

vi.mock('@/stores/sidebarConfig', () => ({
  useSidebarConfigStore: () => mockSidebarConfigStore,
}))

vi.mock('@/stores/onboardingTour', () => ({
  useOnboardingTourStore: () => mockOnboardingTour,
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    warn: warnMock,
  }),
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
    mockPreferences.supportedSidebarWidgets = []
    mockPreferences.sidebarWidgetOverrides = {}
    mockPreferences.sidebarWidgetKeys = []
    mockPreferences.fetchPreferences.mockClear()
    mockPreferences.saveOnboarding.mockClear()
    mockPreferences.markOnboardingComplete.mockClear()
    mockSidebarConfigStore.fetchScope.mockClear()
    mockOnboardingTour.restartTour.mockClear()
    warnMock.mockClear()
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
            template: '<button class="finish" @click="$emit(\'finish\', { sidebar_widget_keys: [\'search\', \'nasa_apod\', \'next_event\'], sidebar_widget_overrides: { home: [\'search\', \'nasa_apod\', \'next_event\'] }, location_label: \'Bratislava\' })">finish</button>',
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
            template: '<button class="finish" @click="$emit(\'finish\', { sidebar_widget_keys: [\'search\', \'nasa_apod\', \'next_event\'] })">finish</button>',
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

  it('keeps the user on onboarding and warns when skip save fails', async () => {
    const router = makeRouter()
    await router.push('/onboarding?redirect=/')
    await router.isReady()

    mockPreferences.markOnboardingComplete.mockRejectedValueOnce({
      userMessage: 'Relacia nie je pripravena.',
    })

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
    await vi.waitFor(() => {
      expect(warnMock).toHaveBeenCalledWith('Relacia nie je pripravena.')
    })
    expect(router.currentRoute.value.name).toBe('onboarding')
  })
})
