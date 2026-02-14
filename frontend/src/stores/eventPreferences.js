import { defineStore } from 'pinia'
import { getMyPreferences, updateMyPreferences } from '@/services/events'

const DEFAULT_REGION = 'global'

export const useEventPreferencesStore = defineStore('eventPreferences', {
  state: () => ({
    loading: false,
    saving: false,
    loaded: false,
    hasPreferences: false,
    eventTypes: [],
    region: DEFAULT_REGION,
    supportedEventTypes: [],
    supportedRegions: ['sk', 'eu', 'global'],
    error: null,
  }),

  getters: {
    hasSelectedTypes: (state) => Array.isArray(state.eventTypes) && state.eventTypes.length > 0,
  },

  actions: {
    reset() {
      this.loading = false
      this.saving = false
      this.loaded = false
      this.hasPreferences = false
      this.eventTypes = []
      this.region = DEFAULT_REGION
      this.supportedEventTypes = []
      this.supportedRegions = ['sk', 'eu', 'global']
      this.error = null
    },

    async fetch(force = false) {
      if (this.loading) return
      if (this.loaded && !force) return

      this.loading = true
      this.error = null

      try {
        const response = await getMyPreferences()
        const data = response?.data?.data || {}
        const meta = response?.data?.meta || {}

        this.eventTypes = Array.isArray(data.event_types) ? data.event_types : []
        this.region = typeof data.region === 'string' ? data.region : DEFAULT_REGION
        this.hasPreferences = Boolean(data.has_preferences)
        this.supportedEventTypes = Array.isArray(meta.supported_event_types) ? meta.supported_event_types : []
        this.supportedRegions = Array.isArray(meta.supported_regions) && meta.supported_regions.length > 0
          ? meta.supported_regions
          : ['sk', 'eu', 'global']

        this.loaded = true
      } catch (error) {
        this.error = error?.response?.data?.message || 'Nepodarilo sa nacitat preferencie.'
        throw error
      } finally {
        this.loading = false
      }
    },

    async save(payload) {
      if (this.saving) return null

      this.saving = true
      this.error = null

      try {
        const response = await updateMyPreferences(payload)
        const data = response?.data?.data || {}
        const meta = response?.data?.meta || {}

        this.eventTypes = Array.isArray(data.event_types) ? data.event_types : []
        this.region = typeof data.region === 'string' ? data.region : DEFAULT_REGION
        this.hasPreferences = Boolean(data.has_preferences)
        this.supportedEventTypes = Array.isArray(meta.supported_event_types) ? meta.supported_event_types : this.supportedEventTypes
        this.supportedRegions = Array.isArray(meta.supported_regions) && meta.supported_regions.length > 0
          ? meta.supported_regions
          : this.supportedRegions
        this.loaded = true

        return response
      } catch (error) {
        this.error = error?.response?.data?.message || 'Nepodarilo sa ulozit preferencie.'
        throw error
      } finally {
        this.saving = false
      }
    },
  },
})
