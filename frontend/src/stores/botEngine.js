import { defineStore } from 'pinia'
import { getBotItems, getBotRuns, getBotSources, runBotSource } from '@/services/api/admin/bots'

const DEFAULT_FILTERS = Object.freeze({
  per_page: 20,
  page: 1,
  sourceKey: '',
  status: '',
  date_from: '',
  date_to: '',
})

function toPositiveInt(value, fallback) {
  const parsed = Number(value)
  if (Number.isInteger(parsed) && parsed > 0) {
    return parsed
  }

  return fallback
}

function normalizeFilters(input = {}) {
  const source = { ...DEFAULT_FILTERS, ...(input || {}) }
  return {
    per_page: toPositiveInt(source.per_page, DEFAULT_FILTERS.per_page),
    page: toPositiveInt(source.page, DEFAULT_FILTERS.page),
    sourceKey: String(source.sourceKey || '').trim(),
    status: String(source.status || '').trim(),
    date_from: String(source.date_from || '').trim(),
    date_to: String(source.date_to || '').trim(),
  }
}

function normalizeRunsMeta(payload, fallbackPerPage = DEFAULT_FILTERS.per_page) {
  const currentPage = toPositiveInt(payload?.current_page, 1)
  const lastPage = toPositiveInt(payload?.last_page, 1)
  const perPage = toPositiveInt(payload?.per_page, fallbackPerPage)

  return {
    current_page: currentPage,
    last_page: Math.max(lastPage, currentPage),
    per_page: perPage,
    total: Number(payload?.total) || 0,
    from: Number(payload?.from) || 0,
    to: Number(payload?.to) || 0,
  }
}

function normalizeItemsMeta(payload, fallbackPerPage = DEFAULT_FILTERS.per_page) {
  return normalizeRunsMeta(payload, fallbackPerPage)
}

export const useBotEngineStore = defineStore('botEngine', {
  state: () => ({
    sources: [],
    runsPage: {
      data: [],
      meta: normalizeRunsMeta(null),
    },
    runItemsPage: {
      data: [],
      meta: normalizeItemsMeta(null),
    },
    filters: normalizeFilters(),
    loadingSources: false,
    loadingRuns: false,
    loadingRunItems: false,
    runningSourceKeys: new Set(),
  }),

  getters: {
    runs: (state) => state.runsPage.data,
    runItems: (state) => state.runItemsPage.data,
  },

  actions: {
    isSourceRunning(sourceKey) {
      return this.runningSourceKeys.has(String(sourceKey || '').toLowerCase())
    },

    updateFilters(nextFilters = {}, { resetPage = false } = {}) {
      const merged = normalizeFilters({
        ...this.filters,
        ...(nextFilters || {}),
      })

      if (resetPage) {
        merged.page = 1
      }

      this.filters = merged
      return merged
    },

    resetFilters() {
      this.filters = normalizeFilters()
      return this.filters
    },

    async fetchSources() {
      this.loadingSources = true

      try {
        const response = await getBotSources()
        this.sources = Array.isArray(response?.data?.data) ? response.data.data : []
        return this.sources
      } finally {
        this.loadingSources = false
      }
    },

    async fetchRuns(params = {}) {
      this.loadingRuns = true

      try {
        const requestFilters = this.updateFilters(params)
        const response = await getBotRuns(requestFilters)
        const payload = response?.data || {}

        this.runsPage = {
          data: Array.isArray(payload?.data) ? payload.data : [],
          meta: normalizeRunsMeta(payload, requestFilters.per_page),
        }

        return this.runsPage
      } finally {
        this.loadingRuns = false
      }
    },

    clearRunItems() {
      this.runItemsPage = {
        data: [],
        meta: normalizeItemsMeta(null),
      }
    },

    async fetchItemsForRun(runId, params = {}) {
      const normalizedRunId = Number(runId)
      if (!Number.isInteger(normalizedRunId) || normalizedRunId <= 0) {
        this.clearRunItems()
        return this.runItemsPage
      }

      this.loadingRunItems = true

      try {
        const requestParams = {
          run_id: normalizedRunId,
          per_page: toPositiveInt(params?.per_page, 20),
          page: toPositiveInt(params?.page, 1),
        }

        const response = await getBotItems(requestParams)
        const payload = response?.data || {}

        this.runItemsPage = {
          data: Array.isArray(payload?.data) ? payload.data : [],
          meta: normalizeItemsMeta(payload, requestParams.per_page),
        }

        return this.runItemsPage
      } finally {
        this.loadingRunItems = false
      }
    },

    async runSource(sourceKey) {
      const normalizedSourceKey = String(sourceKey || '').trim().toLowerCase()
      if (!normalizedSourceKey || this.runningSourceKeys.has(normalizedSourceKey)) {
        return null
      }

      this.runningSourceKeys.add(normalizedSourceKey)

      try {
        const response = await runBotSource(normalizedSourceKey)
        return response?.data || null
      } finally {
        this.runningSourceKeys.delete(normalizedSourceKey)
      }
    },
  },
})
