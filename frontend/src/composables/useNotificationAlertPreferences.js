import { ref } from 'vue'
import api from '@/services/api'

function normalizePreferences(payload) {
  const source = payload && typeof payload === 'object' ? payload : {}
  return {
    good_conditions_alerts: Boolean(source.good_conditions_alerts),
    iss_alerts: Boolean(source.iss_alerts),
  }
}

export function useNotificationAlertPreferences(options = {}) {
  const isAuthenticated = options.isAuthenticated
  const preferences = ref({
    good_conditions_alerts: false,
    iss_alerts: false,
  })
  const preferencesLoading = ref(false)
  const preferencesError = ref(false)

  async function fetchPreferences() {
    if (isAuthenticated && !Boolean(isAuthenticated.value)) {
      preferences.value = {
        good_conditions_alerts: false,
        iss_alerts: false,
      }
      preferencesError.value = false
      return
    }

    preferencesLoading.value = true
    preferencesError.value = false
    try {
      const response = await api.get('/me/notifications/preferences')
      preferences.value = normalizePreferences(response?.data)
    } catch {
      preferencesError.value = true
    } finally {
      preferencesLoading.value = false
    }
  }

  async function updatePreferences(payload) {
    preferencesLoading.value = true
    preferencesError.value = false
    try {
      const response = await api.post('/me/notifications/preferences', payload)
      preferences.value = normalizePreferences(response?.data)
    } catch {
      preferencesError.value = true
      throw new Error('Unable to update notification preferences')
    } finally {
      preferencesLoading.value = false
    }
  }

  return {
    preferences,
    preferencesLoading,
    preferencesError,
    fetchPreferences,
    updatePreferences,
  }
}
