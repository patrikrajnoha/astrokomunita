/**
 * API service pre admin dashboard
 * Centralizované API volania pre dashboard data
 */

import api from '@/services/api.js';

/**
 * Získa dashboard statistics
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getDashboardStats(params = {}) {
  return api.get('/admin/dashboard/stats', { params });
}

/**
 * Získa recent activities
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getRecentActivities(params = {}) {
  return api.get('/admin/dashboard/activities', { params });
}

/**
 * Získa quick actions data
 * @returns {Promise} API response
 */
export function getQuickActions() {
  return api.get('/admin/dashboard/quick-actions');
}

/**
 * Získa system health status
 * @returns {Promise} API response
 */
export function getSystemHealth() {
  return api.get('/admin/dashboard/health');
}

/**
 * Získa charts data pre dashboard
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getChartsData(params = {}) {
  return api.get('/admin/dashboard/charts', { params });
}
