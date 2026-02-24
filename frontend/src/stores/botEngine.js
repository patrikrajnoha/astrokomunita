import { defineStore } from 'pinia'
import {
  deleteBotItemPost,
  getBotItems,
  getBotRuns,
  getBotSources,
  publishBotItem,
  publishBotRun,
  backfillBotTranslation,
  retryBotTranslation,
  runBotSource,
  getBotTranslationHealth,
  setBotTranslationSimulateOutage,
  testBotTranslation,
} from '@/services/api/admin/bots'

const DEFAULT_FILTERS = Object.freeze({
  per_page: 20,
  page: 1,
  sourceKey: '',
  bot_identity: '',
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
  const normalizedBotIdentity = String(source.bot_identity || '').trim().toLowerCase()

  return {
    per_page: toPositiveInt(source.per_page, DEFAULT_FILTERS.per_page),
    page: toPositiveInt(source.page, DEFAULT_FILTERS.page),
    sourceKey: String(source.sourceKey || '').trim(),
    bot_identity: ['kozmo', 'stela'].includes(normalizedBotIdentity) ? normalizedBotIdentity : '',
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
    publishingItemIds: new Set(),
    deletingItemIds: new Set(),
    publishingRunIds: new Set(),
    retryingTranslationSourceKeys: new Set(),
    backfillingTranslationSourceKeys: new Set(),
    testingTranslation: false,
    translationHealth: null,
    loadingTranslationHealth: false,
    savingTranslationOutage: false,
  }),

  getters: {
    runs: (state) => state.runsPage.data,
    runItems: (state) => state.runItemsPage.data,
  },

  actions: {
    isSourceRunning(sourceKey) {
      return this.runningSourceKeys.has(String(sourceKey || '').toLowerCase())
    },

    isItemPublishing(itemId) {
      const normalized = Number(itemId)
      if (!Number.isInteger(normalized) || normalized <= 0) return false
      return this.publishingItemIds.has(normalized)
    },

    isRunPublishing(runId) {
      const normalized = Number(runId)
      if (!Number.isInteger(normalized) || normalized <= 0) return false
      return this.publishingRunIds.has(normalized)
    },

    isItemDeleting(itemId) {
      const normalized = Number(itemId)
      if (!Number.isInteger(normalized) || normalized <= 0) return false
      return this.deletingItemIds.has(normalized)
    },

    isTranslationRetrying(sourceKey) {
      const normalized = String(sourceKey || '').trim().toLowerCase()
      if (!normalized) return false
      return this.retryingTranslationSourceKeys.has(normalized)
    },

    isTranslationBackfilling(sourceKey) {
      const normalized = String(sourceKey || '').trim().toLowerCase()
      if (!normalized) return false
      return this.backfillingTranslationSourceKeys.has(normalized)
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

    async runSource(sourceKey, options = {}) {
      const normalizedSourceKey = String(sourceKey || '').trim().toLowerCase()
      if (!normalizedSourceKey || this.runningSourceKeys.has(normalizedSourceKey)) {
        return null
      }

      this.runningSourceKeys.add(normalizedSourceKey)

      try {
        const response = await runBotSource(normalizedSourceKey, options)
        return response?.data || null
      } finally {
        this.runningSourceKeys.delete(normalizedSourceKey)
      }
    },

    async publishItem(botItemId, options = {}) {
      const normalizedItemId = Number(botItemId)
      if (!Number.isInteger(normalizedItemId) || normalizedItemId <= 0) {
        return null
      }

      if (this.publishingItemIds.has(normalizedItemId)) {
        return null
      }

      this.publishingItemIds.add(normalizedItemId)

      try {
        const response = await publishBotItem(normalizedItemId, options)
        return response?.data || null
      } finally {
        this.publishingItemIds.delete(normalizedItemId)
      }
    },

    async publishRun(runId, options = {}) {
      const normalizedRunId = Number(runId)
      if (!Number.isInteger(normalizedRunId) || normalizedRunId <= 0) {
        return null
      }

      if (this.publishingRunIds.has(normalizedRunId)) {
        return null
      }

      this.publishingRunIds.add(normalizedRunId)

      try {
        const response = await publishBotRun(normalizedRunId, options)
        return response?.data || null
      } finally {
        this.publishingRunIds.delete(normalizedRunId)
      }
    },

    async deleteItemPost(botItemId) {
      const normalizedItemId = Number(botItemId)
      if (!Number.isInteger(normalizedItemId) || normalizedItemId <= 0) {
        return null
      }

      if (this.deletingItemIds.has(normalizedItemId)) {
        return null
      }

      this.deletingItemIds.add(normalizedItemId)

      try {
        const response = await deleteBotItemPost(normalizedItemId)
        return response?.data || null
      } finally {
        this.deletingItemIds.delete(normalizedItemId)
      }
    },

    async testTranslation(payload = {}) {
      if (this.testingTranslation) {
        return null
      }

      this.testingTranslation = true

      try {
        const response = await testBotTranslation(payload)
        return response?.data || null
      } finally {
        this.testingTranslation = false
      }
    },

    async fetchTranslationHealth() {
      this.loadingTranslationHealth = true

      try {
        const response = await getBotTranslationHealth()
        this.translationHealth = response?.data || null
        return this.translationHealth
      } finally {
        this.loadingTranslationHealth = false
      }
    },

    async setTranslationOutageProvider(provider = 'none') {
      if (this.savingTranslationOutage) {
        return null
      }

      this.savingTranslationOutage = true

      try {
        const response = await setBotTranslationSimulateOutage(provider)
        return response?.data || null
      } finally {
        this.savingTranslationOutage = false
      }
    },

    async retryTranslation(sourceKey, payload = {}) {
      const normalizedSourceKey = String(sourceKey || '').trim().toLowerCase()
      if (!normalizedSourceKey) {
        return null
      }

      if (this.retryingTranslationSourceKeys.has(normalizedSourceKey)) {
        return null
      }

      this.retryingTranslationSourceKeys.add(normalizedSourceKey)

      try {
        const response = await retryBotTranslation(normalizedSourceKey, payload)
        return response?.data || null
      } finally {
        this.retryingTranslationSourceKeys.delete(normalizedSourceKey)
      }
    },

    async backfillTranslation(sourceKey, payload = {}) {
      const normalizedSourceKey = String(sourceKey || '').trim().toLowerCase()
      if (!normalizedSourceKey) {
        return null
      }

      if (this.backfillingTranslationSourceKeys.has(normalizedSourceKey)) {
        return null
      }

      this.backfillingTranslationSourceKeys.add(normalizedSourceKey)

      try {
        const response = await backfillBotTranslation(normalizedSourceKey, payload)
        return response?.data || null
      } finally {
        this.backfillingTranslationSourceKeys.delete(normalizedSourceKey)
      }
    },
  },
})
