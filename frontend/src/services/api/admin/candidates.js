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
  return api.get('/admin/candidates', { params });
}

/**
 * Získa detail candidate
 * @param {number} id - Candidate ID
 * @returns {Promise} API response
 */
export function getCandidate(id) {
  return api.get(`/admin/candidates/${id}`);
}

/**
 * Vytvorí nový manual candidate
 * @param {Object} data - Candidate data
 * @returns {Promise} API response
 */
export function createCandidate(data) {
  return api.post('/admin/candidates', data);
}

/**
 * Aktualizuje candidate
 * @param {number} id - Candidate ID
 * @param {Object} data - Candidate data
 * @returns {Promise} API response
 */
export function updateCandidate(id, data) {
  return api.put(`/admin/candidates/${id}`, data);
}

/**
 * Vymaže candidate
 * @param {number} id - Candidate ID
 * @returns {Promise} API response
 */
export function deleteCandidate(id) {
  return api.delete(`/admin/candidates/${id}`);
}

/**
 * Schváli candidate na publikáciu
 * @param {number} id - Candidate ID
 * @param {Object} data - Publikačné dáta
 * @returns {Promise} API response
 */
export function approveCandidate(id, data = {}) {
  return api.post(`/admin/candidates/${id}/approve`, data);
}

/**
 * Zamietne candidate
 * @param {number} id - Candidate ID
 * @param {Object} data - Reason data
 * @returns {Promise} API response
 */
export function rejectCandidate(id, data = {}) {
  return api.post(`/admin/candidates/${id}/reject`, data);
}

/**
 * Publikuje candidate ako event
 * @param {number} id - Candidate ID
 * @param {Object} data - Event data
 * @returns {Promise} API response
 */
export function publishCandidate(id, data = {}) {
  return api.post(`/admin/candidates/${id}/publish`, data);
}

/**
 * Importuje candidates z externého zdroja
 * @param {Object} data - Import dáta
 * @returns {Promise} API response
 */
export function importCandidates(data) {
  return api.post('/admin/candidates/import', data);
}

/**
 * Získa meta informácie pre candidates
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getCandidatesMeta(params = {}) {
  return api.get('/admin/candidates/meta', { params });
}

/**
 * Bulk operácie pre candidates
 * @param {Object} data - Bulk dáta
 * @returns {Promise} API response
 */
export function bulkCandidateAction(data) {
  return api.post('/admin/candidates/bulk', data);
}
