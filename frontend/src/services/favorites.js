import api from './api'

export const getFavorites = () => api.get('/favorites')
export const addFavorite = (eventId) => api.post('/favorites', { event_id: eventId })
export const removeFavorite = (eventId) => api.delete(`/favorites/${eventId}`)
