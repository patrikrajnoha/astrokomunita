/**
 * API service pre blog management
 * Centralizované API volania pre blog posts
 */

import api from '@/services/api.js';

/**
 * Získa zoznam blog posts
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getBlogPosts(params = {}) {
  return api.get('/admin/blog-posts', { params });
}

/**
 * Získa detail blog postu
 * @param {number} id - Blog post ID
 * @returns {Promise} API response
 */
export function getBlogPost(id) {
  return api.get(`/admin/blog-posts/${id}`);
}

/**
 * Vytvorí nový blog post
 * @param {Object} data - Blog post data
 * @returns {Promise} API response
 */
export function createBlogPost(data) {
  return api.post('/admin/blog-posts', data);
}

/**
 * Aktualizuje blog post
 * @param {number} id - Blog post ID
 * @param {Object} data - Blog post data
 * @returns {Promise} API response
 */
export function updateBlogPost(id, data) {
  return api.put(`/admin/blog-posts/${id}`, data);
}

/**
 * Vymaže blog post
 * @param {number} id - Blog post ID
 * @returns {Promise} API response
 */
export function deleteBlogPost(id) {
  return api.delete(`/admin/blog-posts/${id}`);
}

/**
 * Publikuje blog post
 * @param {number} id - Blog post ID
 * @param {Object} data - Publikačné dáta
 * @returns {Promise} API response
 */
export function publishBlogPost(id, data = {}) {
  return api.patch(`/admin/blog-posts/${id}`, {
    ...data,
    published_at: data.published_at || new Date().toISOString(),
  });
}

/**
 * Naplánuje blog post
 * @param {number} id - Blog post ID
 * @param {string} publishedAt - Dátum publikácie
 * @returns {Promise} API response
 */
export function scheduleBlogPost(id, publishedAt) {
  return api.patch(`/admin/blog-posts/${id}`, { published_at: publishedAt });
}

/**
 * Skryje blog post
 * @param {number} id - Blog post ID
 * @returns {Promise} API response
 */
export function unpublishBlogPost(id) {
  return api.patch(`/admin/blog-posts/${id}`, { published_at: null });
}

/**
 * Nahraje cover image pre blog post
 * @param {number} id - Blog post ID
 * @param {FormData} formData - Image data
 * @returns {Promise} API response
 */
export function uploadBlogCover(id, formData) {
  return api.patch(`/admin/blog-posts/${id}`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  });
}

/**
 * Získa blog statistics
 * @param {Object} params - Query parametre
 * @returns {Promise} API response
 */
export function getBlogStats(params = {}) {
  return api.get('/admin/blog/stats', { params });
}

/**
 * Bulk operácie pre blog posts
 * @param {Object} data - Bulk dáta
 * @returns {Promise} API response
 */
export function bulkBlogAction(data) {
  return api.post('/admin/blog/bulk', data);
}

/**
 * Exportuje blog posts
 * @param {Object} params - Export parametre
 * @returns {Promise} API response
 */
export function exportBlogPosts(params = {}) {
  return api.get('/admin/blog/export', {
    params,
    responseType: 'blob'
  });
}
