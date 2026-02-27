import api from '@/services/api'

export function getMarkYourCalendarPopup() {
  return api.get('/popup/mark-your-calendar', {
    meta: { requiresAuth: true },
  })
}

export function markYourCalendarPopupSeen(payload) {
  return api.post('/popup/mark-your-calendar/seen', payload, {
    meta: { requiresAuth: true },
  })
}

