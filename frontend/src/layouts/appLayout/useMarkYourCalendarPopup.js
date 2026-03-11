import { computed, ref } from 'vue'
import { getMarkYourCalendarPopup, markYourCalendarPopupSeen } from '@/services/popup'

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

  const canCheckCalendarPopup = computed(() => {
    return (
      auth.bootstrapDone &&
      auth.isAuthed &&
      !auth.isAdmin &&
      Boolean(auth.user?.email_verified_at) &&
      !isOnboardingFlowActive.value &&
      !onboardingTour.isOpen &&
      preferences.isOnboardingCompleted &&
      !calendarPopupSessionChecked.value
    )
  })

  const maybeCheckCalendarPopup = async () => {
    if (!canCheckCalendarPopup.value) return

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
    }
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
    calendarPopupSessionChecked.value = false
    isCalendarPopupVisible.value = false
    calendarPopupPayload.value = null
    calendarPopupAckInFlight.value = false
  }

  return {
    calendarPopupPayload,
    closeCalendarPopup,
    goToCalendarFromPopup,
    isCalendarPopupVisible,
    maybeCheckCalendarPopup,
    resetCalendarPopupState,
  }
}
