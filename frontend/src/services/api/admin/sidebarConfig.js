import api from '@/services/api'

export const sidebarConfigAdminApi = {
  async get(scope) {
    const response = await api.get('/admin/sidebar-config', {
      params: { scope },
    })
    return response?.data
  },

  async update(scope, items) {
    const response = await api.put(
      '/admin/sidebar-config',
      { items },
      {
        params: { scope },
      },
    )

    return response?.data
  },
}

export const sidebarCustomComponentsAdminApi = {
  async list({ activeOnly = false } = {}) {
    const response = await api.get('/admin/sidebar/custom-components', {
      params: activeOnly ? { active_only: 1 } : {},
    })
    return response?.data
  },

  async get(id) {
    const response = await api.get(`/admin/sidebar/custom-components/${id}`)
    return response?.data
  },

  async create(payload) {
    const response = await api.post('/admin/sidebar/custom-components', payload)
    return response?.data
  },

  async update(id, payload) {
    const response = await api.put(`/admin/sidebar/custom-components/${id}`, payload)
    return response?.data
  },

  async remove(id) {
    const response = await api.delete(`/admin/sidebar/custom-components/${id}`)
    return response?.data
  },
}
