/**
 * API service pre event candidates
 * Centralizované API volania pre candidates management
 */

import api from '@/services/api.js';

/**
 * Získa zoznam event candidates
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getCandidates(params = {}) {
  const normalized = { ...params }
  if (normalized.source_name == null && normalized.source != null) {
    normalized.source_name = normalized.source
  }
  delete normalized.source

  return api.get('/admin/event-candidates', { params: normalized });
}

/**
 * Získa detail candidate
 * @param {number} id - Candidate ID
 * @returns {Promise} API response
 */
export function getCandidate(id) {
  return api.get(`/admin/event-candidates/${id}`);
}

/**
 * Vytvorí nový manual candidate
 * @param {Object} data - Candidate data
 * @returns {Promise} API response
 */
export function createCandidate(data) {
  return api.post('/admin/manual-events', data);
}

/**
 * Aktualizuje candidate
 * @param {number} id - Candidate ID
 * @param {Object} data - Candidate data
 * @returns {Promise} API response
 */
export function updateCandidate(id, data) {
  return api.put(`/admin/manual-events/${id}`, data);
}

/**
 * Vymaže candidate
 * @param {number} id - Candidate ID
 * @returns {Promise} API response
 */
export function deleteCandidate(id) {
  return api.delete(`/admin/manual-events/${id}`);
}

/**
 * Schváli candidate na publikáciu
 * @param {number} id - Candidate ID
 * @param {Object} data - Publikačné dáta
 * @returns {Promise} API response
 */
export function approveCandidate(id, data = {}) {
  return api.post(`/admin/event-candidates/${id}/approve`, data);
}

/**
 * Zamietne candidate
 * @param {number} id - Candidate ID
 * @param {Object} data - Reason data
 * @returns {Promise} API response
 */
export function rejectCandidate(id, data = {}) {
  return api.post(`/admin/event-candidates/${id}/reject`, data);
}

/**
 * Publikuje candidate ako event
 * @param {number} id - Candidate ID
 * @param {Object} data - Event data
 * @returns {Promise} API response
 */
export function publishCandidate(id, data = {}) {
  return api.post(`/admin/event-candidates/${id}/approve`, data);
}

/**
 * Importuje candidates z externého zdroja
 * @param {Object} data - Import dáta
 * @returns {Promise} API response
 */
export function importCandidates(data) {
  return api.post('/admin/manual-events', data);
}

/**
 * Získa meta informácie pre candidates
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getCandidatesMeta(params = {}) {
  return api.get('/admin/event-candidates-meta', { params });
}

/**
 * Bulk operácie pre candidates
 * @param {Object} data - Bulk dáta
 * @returns {Promise} API response
 */
export function bulkCandidateAction(data) {
  return api.post('/admin/event-candidates', data);
}
