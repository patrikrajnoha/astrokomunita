import { defineStore } from 'pinia'

export const ONBOARDING_TOUR_STORAGE_KEY = 'ns_onboarding_v1'
const DONE_VALUE = 'done'

function readDoneFlag() {
  if (typeof window === 'undefined') return false

  try {
    return window.localStorage.getItem(ONBOARDING_TOUR_STORAGE_KEY) === DONE_VALUE
  } catch {
    return false
  }
}

function writeDoneFlag(done: boolean) {
  if (typeof window === 'undefined') return

  try {
    if (done) {
      window.localStorage.setItem(ONBOARDING_TOUR_STORAGE_KEY, DONE_VALUE)
      return
    }

    window.localStorage.removeItem(ONBOARDING_TOUR_STORAGE_KEY)
  } catch {
    // localStorage write failures are non-fatal for tour UX.
  }
}

export const useOnboardingTourStore = defineStore('onboardingTour', {
  state: () => ({
    isOpen: false,
    isDone: readDoneFlag(),
    startStep: 0,
  }),
  getters: {
    shouldAutoOpen: (state) => !state.isDone && !state.isOpen,
  },
  actions: {
    hydrate() {
      this.isDone = readDoneFlag()
    },
    openTour(options: { force?: boolean; startStep?: number } = {}) {
      const { force = false, startStep = 0 } = options
      if (!force && this.isDone) return

      this.startStep = Number.isFinite(startStep) && startStep >= 0 ? Math.floor(startStep) : 0
      this.isOpen = true
    },
    closeTour() {
      this.isOpen = false
      this.startStep = 0
    },
    markDone() {
      this.isDone = true
      this.isOpen = false
      this.startStep = 0
      writeDoneFlag(true)
    },
    restartTour() {
      this.isDone = false
      writeDoneFlag(false)
      this.openTour({ force: true, startStep: 0 })
    },
  },
})
