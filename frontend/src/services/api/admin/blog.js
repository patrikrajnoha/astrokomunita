import api from '@/services/api'

export function getBlogPosts(params = {}) {
  return api.get('/admin/blog-posts', { params })
}

export function getBlogPost(id) {
  return api.get(`/admin/blog-posts/${id}`)
}

export function createBlogPost(data) {
  return api.post('/admin/blog-posts', data)
}

export function updateBlogPost(id, data) {
  return api.put(`/admin/blog-posts/${id}`, data)
}

export function deleteBlogPost(id) {
  return api.delete(`/admin/blog-posts/${id}`)
}

export function publishBlogPost(id, data = {}) {
  return api.patch(`/admin/blog-posts/${id}`, {
    ...data,
    published_at: data.published_at || new Date().toISOString(),
  })
}

export function scheduleBlogPost(id, publishedAt) {
  return api.patch(`/admin/blog-posts/${id}`, { published_at: publishedAt })
}

export function unpublishBlogPost(id) {
  return api.patch(`/admin/blog-posts/${id}`, { published_at: null })
}

export function uploadBlogCover(id, formData) {
  return api.patch(`/admin/blog-posts/${id}`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
}
