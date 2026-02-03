/**
 * API service pre events management
 * Centralizované API volania pre events
 */

import api from '@/services/api.js';

/**
 * Získa zoznam events
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getEvents(params = {}) {
  return api.get('/admin/events', { params });
}

/**
 * Získa detail eventu
 * @param {number} id - Event ID
 * @returns {Promise} API response
 */
export function getEvent(id) {
  return api.get(`/admin/events/${id}`);
}

/**
 * Vytvorí nový event
 * @param {Object} data - Event data
 * @returns {Promise} API response
 */
export function createEvent(data) {
  return api.post('/admin/events', data);
}

/**
 * Aktualizuje event
 * @param {number} id - Event ID
 * @param {Object} data - Event data
 * @returns {Promise} API response
 */
export function updateEvent(id, data) {
  return api.put(`/admin/events/${id}`, data);
}

/**
 * Vymaže event
 * @param {number} id - Event ID
 * @returns {Promise} API response
 */
export function deleteEvent(id) {
  return api.delete(`/admin/events/${id}`);
}

/**
 * Publikuje event
 * @param {number} id - Event ID
 * @param {Object} data - Publikačné dáta
 * @returns {Promise} API response
 */
export function publishEvent(id, data = {}) {
  return api.post(`/admin/events/${id}/publish`, data);
}

/**
 * Skryje/zobrazí event
 * @param {number} id - Event ID
 * @param {boolean} hidden - Hidden status
 * @returns {Promise} API response
 */
export function toggleEventVisibility(id, hidden) {
  return api.patch(`/admin/events/${id}/visibility`, { hidden });
}

/**
 * Získa event statistics
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getEventStats(params = {}) {
  return api.get('/admin/events/stats', { params });
}

/**
 * Bulk operácie pre events
 * @param {Object} data - Bulk dáta
 * @returns {Promise} API response
 */
export function bulkEventAction(data) {
  return api.post('/admin/events/bulk', data);
}

/**
 * Exportuje events
 * @param {Object} params - Export parametre
 * @returns {Promise} API response
 */
export function exportEvents(params = {}) {
  return api.get('/admin/events/export', { 
    params,
    responseType: 'blob'
  });
}
