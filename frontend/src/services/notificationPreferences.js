import api from './api'

export function getNotificationPreferences() {
  return api.get('/notification-preferences', {
    meta: { requiresAuth: true },
  })
}

export function updateNotificationPreferences(payload) {
  return api.put('/notification-preferences', payload, {
    meta: { requiresAuth: true },
  })
}
