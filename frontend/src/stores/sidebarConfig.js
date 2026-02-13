import { defineStore } from 'pinia'
import api from '@/services/api'
import { getEnabledSidebarSections } from '@/sidebar/engine'

const DEFAULT_ITEMS = [
  { kind: 'builtin', section_key: 'search', title: 'Search', order: 0, is_enabled: true },
  { kind: 'builtin', section_key: 'observing_conditions', title: 'Observing Conditions', order: 1, is_enabled: true },
  { kind: 'builtin', section_key: 'nasa_apod', title: 'NASA APOD', order: 2, is_enabled: true },
  { kind: 'builtin', section_key: 'next_event', title: 'Next Event', order: 3, is_enabled: true },
  { kind: 'builtin', section_key: 'latest_articles', title: 'Latest Articles', order: 4, is_enabled: true },
]

const cloneAndSort = (items) => {
  const source = Array.isArray(items) ? items : []
  return [...source]
    .map((item) => ({
      kind: item?.kind === 'custom_component' ? 'custom_component' : 'builtin',
      section_key: String(item.section_key || ''),
      title: String(item.title || ''),
      custom_component_id: Number.isFinite(Number(item.custom_component_id))
        ? Number(item.custom_component_id)
        : null,
      custom_component: item?.custom_component ?? null,
      order: Number.isFinite(item.order) ? Number(item.order) : 0,
      is_enabled: Boolean(item.is_enabled),
    }))
    .sort((a, b) => a.order - b.order)
}

export const useSidebarConfigStore = defineStore('sidebarConfig', {
  state: () => ({
    byScope: {},
    pendingByScope: {},
  }),

  actions: {
    getDefaultForScope() {
      return cloneAndSort(DEFAULT_ITEMS)
    },

    async fetchScope(scope, { force = false } = {}) {
      if (!scope) {
        return this.getDefaultForScope()
      }

      if (!force && this.byScope[scope]) {
        return cloneAndSort(this.byScope[scope])
      }

      if (!force && this.pendingByScope[scope]) {
        return this.pendingByScope[scope]
      }

      const requestPromise = api
        .get('/sidebar-config', {
          params: { scope },
          meta: { skipErrorToast: true },
        })
        .then((response) => {
          const items = cloneAndSort(response?.data?.data)
          this.byScope[scope] = items.length > 0 ? items : this.getDefaultForScope()
          return cloneAndSort(this.byScope[scope])
        })
        .catch(() => {
          const fallback = this.getDefaultForScope()
          this.byScope[scope] = fallback
          return cloneAndSort(fallback)
        })
        .finally(() => {
          delete this.pendingByScope[scope]
        })

      this.pendingByScope[scope] = requestPromise
      return requestPromise
    },

    async getEnabledSectionsForScope(scope, { force = false } = {}) {
      const items = await this.fetchScope(scope, { force })
      return getEnabledSidebarSections(items)
    },
  },
})
