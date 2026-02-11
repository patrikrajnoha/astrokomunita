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
