import { useToast } from '@/composables/useToast'

function normalizeMessage(value, fallback = 'Nastala chyba') {
  const msg = String(value || '').trim()
  return msg || fallback
}

function resolveApiErrorMessage(err, fallback) {
  if (err?.response?.status === 422 && err?.response?.data?.errors) {
    const fieldMessages = Object.values(err.response.data.errors)
      .flat()
      .map((item) => String(item || '').trim())
      .filter(Boolean)

    if (fieldMessages.length > 0) {
      return fieldMessages.join(', ')
    }
  }

  if (err?.response?.data?.message) {
    return String(err.response.data.message)
  }

  if (err?.message) {
    return String(err.message)
  }

  return fallback
}

function buildApi() {
  const toast = useToast()

  const success = (message, options = {}) => {
    return toast.success(normalizeMessage(message, 'Hotovo'), options)
  }

  const error = (message, options = {}) => {
    return toast.error(normalizeMessage(message), options)
  }

  const warning = (message, options = {}) => {
    return toast.warn(normalizeMessage(message, 'Upozornenie'), options)
  }

  const info = (message, options = {}) => {
    return toast.info(normalizeMessage(message, 'Informacia'), options)
  }

  const handleApiError = (err, defaultMessage = 'Nastala chyba') => {
    return error(resolveApiErrorMessage(err, defaultMessage))
  }

  const handleApiSuccess = (message, response = null) => {
    const finalMessage = response?.data?.message || message
    return success(finalMessage)
  }

  return {
    notifications: toast.toasts,
    addNotification: toast.show,
    removeNotification: toast.dismiss,
    clearAll: toast.clearAll,
    success,
    error,
    warning,
    info,
    handleApiError,
    handleApiSuccess,
  }
}

export function useNotifications() {
  return buildApi()
}

export const notificationsApi = buildApi()
