import { defineStore } from 'pinia'
import api from '@/services/api'
import { getEnabledSidebarSections } from '@/sidebar/engine'
import {
  hasExplicitSidebarScope,
  normalizeSidebarScope,
} from '@/generated/sidebarScopes'

const DEFAULT_ITEMS = [
  { kind: 'builtin', section_key: 'search', title: 'Search', order: 0, is_enabled: true },
  { kind: 'builtin', section_key: 'observing_conditions', title: 'Observing Conditions', order: 1, is_enabled: true },
  { kind: 'builtin', section_key: 'nasa_apod', title: 'NASA APOD', order: 2, is_enabled: true },
  { kind: 'builtin', section_key: 'next_event', title: 'Next Event', order: 3, is_enabled: true },
  { kind: 'builtin', section_key: 'latest_articles', title: 'Latest Articles', order: 4, is_enabled: true },
  { kind: 'builtin', section_key: 'upcoming_events', title: 'Co sa deje', order: 5, is_enabled: true },
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
      if (!hasExplicitSidebarScope(scope)) {
        return this.getDefaultForScope()
      }
      const normalizedScope = normalizeSidebarScope(scope)

      if (!force && this.byScope[normalizedScope]) {
        return cloneAndSort(this.byScope[normalizedScope])
      }

      if (!force && this.pendingByScope[normalizedScope]) {
        return this.pendingByScope[normalizedScope]
      }

      const requestPromise = api
        .get('/sidebar-config', {
          params: { scope: normalizedScope },
          meta: { skipErrorToast: true },
        })
        .then((response) => {
          const items = cloneAndSort(response?.data?.data)
          this.byScope[normalizedScope] = items.length > 0 ? items : this.getDefaultForScope()
          return cloneAndSort(this.byScope[normalizedScope])
        })
        .catch(() => {
          const fallback = this.getDefaultForScope()
          this.byScope[normalizedScope] = fallback
          return cloneAndSort(fallback)
        })
        .finally(() => {
          delete this.pendingByScope[normalizedScope]
        })

      this.pendingByScope[normalizedScope] = requestPromise
      return requestPromise
    },

    async getEnabledSectionsForScope(scope, { force = false } = {}) {
      const items = await this.fetchScope(scope, { force })
      return getEnabledSidebarSections(items)
    },
  },
})
