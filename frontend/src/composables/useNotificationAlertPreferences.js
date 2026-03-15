import { computed, ref } from 'vue'
import api from '@/services/api'

function defaultPreferences() {
  return {
    iss_alerts: false,
    good_conditions_alerts: false,
  }
}

export function useNotificationAlertPreferences(options = {}) {
  const isAuthenticated = options.isAuthenticated

  const preferences = ref(defaultPreferences())
  const loading = ref(false)
  const error = ref(false)
  const loaded = ref(false)
  let activeFetchPromise = null

  const canLoadPreferences = computed(() => {
    if (!isAuthenticated) return true
    return Boolean(isAuthenticated.value)
  })

  async function fetchPreferences(fetchOptions = {}) {
    if (!canLoadPreferences.value) {
      preferences.value = defaultPreferences()
      error.value = false
      loading.value = false
      loaded.value = false
      return
    }

    const force = fetchOptions.force === true
    if (loaded.value && !force) {
      return preferences.value
    }

    if (loading.value && activeFetchPromise) {
      return activeFetchPromise
    }

    const requestPromise = (async () => {
      if (!fetchOptions.silent) loading.value = true
      error.value = false

      try {
        const response = await api.get('/me/notifications/preferences', {
          meta: { requiresAuth: true, skipErrorToast: true },
        })

        const payload = response?.data || {}
        preferences.value = defaultPreferences()
        preferences.value = {
          iss_alerts: Boolean(payload.iss_alerts),
          good_conditions_alerts: Boolean(payload.good_conditions_alerts),
        }
        error.value = String(payload.reason || '') === 'unavailable'
        if (error.value) {
          preferences.value = defaultPreferences()
        }
        loaded.value = true
        return preferences.value
      } catch {
        preferences.value = defaultPreferences()
        error.value = true
        loaded.value = false
        return preferences.value
      } finally {
        if (!fetchOptions.silent) loading.value = false
      }
    })()

    activeFetchPromise = requestPromise.finally(() => {
      if (activeFetchPromise === requestPromise) {
        activeFetchPromise = null
      }
    })

    return activeFetchPromise
  }

  async function updatePreferences(nextValues = {}) {
    if (!canLoadPreferences.value) return false

    loading.value = true
    error.value = false

    try {
      const response = await api.post('/me/notifications/preferences', {
        iss_alerts: Boolean(nextValues.iss_alerts),
        good_conditions_alerts: Boolean(nextValues.good_conditions_alerts),
      }, {
        meta: { requiresAuth: true, skipErrorToast: true },
      })

      const payload = response?.data || {}
      preferences.value = {
        iss_alerts: Boolean(payload.iss_alerts),
        good_conditions_alerts: Boolean(payload.good_conditions_alerts),
      }
      error.value = String(payload.reason || '') === 'unavailable'
      if (error.value) {
        preferences.value = defaultPreferences()
      }
      loaded.value = true
      return !error.value
    } catch {
      preferences.value = defaultPreferences()
      error.value = true
      loaded.value = false
      return false
    } finally {
      loading.value = false
    }
  }

  return {
    preferences,
    preferencesLoading: loading,
    preferencesError: error,
    fetchPreferences,
    updatePreferences,
  }
}
