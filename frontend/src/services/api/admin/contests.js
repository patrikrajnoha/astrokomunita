import api from '@/services/api'

export function previewContestHashtags(params = {}) {
  return api.get('/admin/contests/hashtags-preview', { params })
}
