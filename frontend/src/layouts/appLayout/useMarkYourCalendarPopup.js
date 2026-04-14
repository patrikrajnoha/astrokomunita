import { computed, ref } from 'vue'
import { getMarkYourCalendarPopup, markYourCalendarPopupSeen } from '@/services/popup'

const CALENDAR_POPUP_CHECK_DELAY_MS = 1400

export function useMarkYourCalendarPopup({
  auth,
  isOnboardingFlowActive,
  onboardingTour,
  preferences,
  router,
}) {
  const calendarPopupSessionChecked = ref(false)
  const isCalendarPopupVisible = ref(false)
  const calendarPopupPayload = ref(null)
  const calendarPopupAckInFlight = ref(false)
  let activePopupCheckPromise = null
  let scheduledPopupCheckTimer = null

  const isSettingsRoute = computed(() => {
    const path = String(router?.currentRoute?.value?.path || '')
    return path === '/settings' || path.startsWith('/settings/')
  })

  const canCheckCalendarPopup = computed(() => {
    return (
      auth.bootstrapDone &&
      auth.isAuthed &&
      !auth.isAdmin &&
      Boolean(auth.user?.email_verified_at) &&
      !isSettingsRoute.value &&
      !isOnboardingFlowActive.value &&
      !onboardingTour.isOpen &&
      preferences.isOnboardingCompleted &&
      !calendarPopupSessionChecked.value
    )
  })

  const cancelScheduledCalendarPopupCheck = () => {
    if (scheduledPopupCheckTimer === null || typeof window === 'undefined') {
      scheduledPopupCheckTimer = null
      return
    }

    window.clearTimeout(scheduledPopupCheckTimer)
    scheduledPopupCheckTimer = null
  }

  const runCalendarPopupCheck = async () => {
    if (!canCheckCalendarPopup.value || calendarPopupSessionChecked.value) return
    if (activePopupCheckPromise) return activePopupCheckPromise

    activePopupCheckPromise = (async () => {
      calendarPopupSessionChecked.value = true
      try {
        const response = await getMarkYourCalendarPopup()
        const payload = response?.data || null

        if (payload?.should_show) {
          if (onboardingTour.isOpen) {
            onboardingTour.closeTour()
          }
          calendarPopupPayload.value = payload
          isCalendarPopupVisible.value = true
        }
      } catch {
        // Session check is best effort.
      } finally {
        activePopupCheckPromise = null
      }
    })()

    return activePopupCheckPromise
  }

  const maybeCheckCalendarPopup = async (options = {}) => {
    if (!canCheckCalendarPopup.value) {
      cancelScheduledCalendarPopupCheck()
      return
    }

    if (calendarPopupSessionChecked.value) {
      return activePopupCheckPromise
    }

    if (options.immediate === true || typeof window === 'undefined') {
      await runCalendarPopupCheck()
      return
    }

    if (scheduledPopupCheckTimer !== null) {
      return
    }

    scheduledPopupCheckTimer = window.setTimeout(() => {
      scheduledPopupCheckTimer = null
      void runCalendarPopupCheck()
    }, CALENDAR_POPUP_CHECK_DELAY_MS)
  }

  const closeCalendarPopup = async () => {
    if (!isCalendarPopupVisible.value || calendarPopupAckInFlight.value) {
      return
    }

    calendarPopupAckInFlight.value = true
    try {
      const payload = calendarPopupPayload.value || {}
      await markYourCalendarPopupSeen({
        force_version: Number(payload.force_version || 0),
        month_key: payload.month_key || null,
      })
    } catch {
      // Do not block dismissal when acknowledge fails.
    } finally {
      isCalendarPopupVisible.value = false
      calendarPopupAckInFlight.value = false
    }
  }

  const goToCalendarFromPopup = async () => {
    await closeCalendarPopup()
    await router.push('/calendar')
  }

  const resetCalendarPopupState = () => {
    cancelScheduledCalendarPopupCheck()
    calendarPopupSessionChecked.value = false
    isCalendarPopupVisible.value = false
    calendarPopupPayload.value = null
    calendarPopupAckInFlight.value = false
  }

  return {
    calendarPopupPayload,
    closeCalendarPopup,
    cancelScheduledCalendarPopupCheck,
    goToCalendarFromPopup,
    isCalendarPopupVisible,
    maybeCheckCalendarPopup,
    resetCalendarPopupState,
  }
}
