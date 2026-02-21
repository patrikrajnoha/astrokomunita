import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ONBOARDING_TOUR_STORAGE_KEY, useOnboardingTourStore } from '@/stores/onboardingTour'

describe('onboardingTour store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    window.localStorage.removeItem(ONBOARDING_TOUR_STORAGE_KEY)
  })

  it('reads done flag from localStorage by default', () => {
    window.localStorage.setItem(ONBOARDING_TOUR_STORAGE_KEY, 'done')

    const store = useOnboardingTourStore()
    expect(store.isDone).toBe(true)
    expect(store.shouldAutoOpen).toBe(false)
  })

  it('markDone persists done flag and closes tour', () => {
    const store = useOnboardingTourStore()
    store.openTour()
    expect(store.isOpen).toBe(true)

    store.markDone()

    expect(store.isDone).toBe(true)
    expect(store.isOpen).toBe(false)
    expect(window.localStorage.getItem(ONBOARDING_TOUR_STORAGE_KEY)).toBe('done')
  })

  it('openTour and closeTour toggle visibility', () => {
    const store = useOnboardingTourStore()
    expect(store.isOpen).toBe(false)

    store.openTour()
    expect(store.isOpen).toBe(true)

    store.closeTour()
    expect(store.isOpen).toBe(false)
  })
})
