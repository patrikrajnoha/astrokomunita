import { defineStore } from 'pinia'
import { getMyPreferences, updateMyPreferences, getOnboardingInterests } from '@/services/events'

const DEFAULT_REGION = 'global'
const DEFAULT_INTERESTS = []
const DEFAULT_BORTLE_CLASS = 6

export const useEventPreferencesStore = defineStore('eventPreferences', {
  state: () => ({
    loading: false,
    saving: false,
    loaded: false,
    hasPreferences: false,
    eventTypes: [],
    interests: [],
    region: DEFAULT_REGION,
    locationLabel: '',
    locationPlaceId: '',
    locationLat: null,
    locationLon: null,
    bortleClass: DEFAULT_BORTLE_CLASS,
    onboardingCompletedAt: null,
    supportedEventTypes: [],
    supportedRegions: ['sk', 'eu', 'global'],
    supportedInterests: [],
    error: null,
  }),

  getters: {
    hasSelectedTypes: (state) => Array.isArray(state.eventTypes) && state.eventTypes.length > 0,
    isOnboardingCompleted: (state) => Boolean(state.onboardingCompletedAt),
  },

  actions: {
    reset() {
      this.loading = false
      this.saving = false
      this.loaded = false
      this.hasPreferences = false
      this.eventTypes = []
      this.interests = [...DEFAULT_INTERESTS]
      this.region = DEFAULT_REGION
      this.locationLabel = ''
      this.locationPlaceId = ''
      this.locationLat = null
      this.locationLon = null
      this.bortleClass = DEFAULT_BORTLE_CLASS
      this.onboardingCompletedAt = null
      this.supportedEventTypes = []
      this.supportedRegions = ['sk', 'eu', 'global']
      this.supportedInterests = []
      this.error = null
    },

    async fetchPreferences(force = false) {
      if (this.loading) return
      if (this.loaded && !force) return

      this.loading = true
      this.error = null

      try {
        const response = await getMyPreferences()
        const data = response?.data?.data || {}
        const meta = response?.data?.meta || {}

        this.eventTypes = Array.isArray(data.event_types) ? data.event_types : []
        this.interests = Array.isArray(data.interests) ? data.interests : [...DEFAULT_INTERESTS]
        this.region = typeof data.region === 'string' ? data.region : DEFAULT_REGION
        this.locationLabel = typeof data.location_label === 'string' ? data.location_label : ''
        this.locationPlaceId = typeof data.location_place_id === 'string' ? data.location_place_id : ''
        this.locationLat = Number.isFinite(Number(data.location_lat)) ? Number(data.location_lat) : null
        this.locationLon = Number.isFinite(Number(data.location_lon)) ? Number(data.location_lon) : null
        this.bortleClass = Number.isInteger(Number(data.bortle_class))
          ? Math.min(9, Math.max(1, Number(data.bortle_class)))
          : DEFAULT_BORTLE_CLASS
        this.onboardingCompletedAt = typeof data.onboarding_completed_at === 'string' && data.onboarding_completed_at
          ? data.onboarding_completed_at
          : null
        this.hasPreferences = Boolean(data.has_preferences)
        this.supportedEventTypes = Array.isArray(meta.supported_event_types) ? meta.supported_event_types : []
        this.supportedRegions = Array.isArray(meta.supported_regions) && meta.supported_regions.length > 0
          ? meta.supported_regions
          : ['sk', 'eu', 'global']
        this.supportedInterests = Array.isArray(meta.supported_interests) ? meta.supported_interests : []

        this.loaded = true
      } catch (error) {
        this.error = error?.response?.data?.message || 'Nepodarilo sa nacitat preferencie.'
        throw error
      } finally {
        this.loading = false
      }
    },

    async savePreferences(payload) {
      if (this.saving) return null

      this.saving = true
      this.error = null

      try {
        const response = await updateMyPreferences(payload)
        const data = response?.data?.data || {}
        const meta = response?.data?.meta || {}

        this.eventTypes = Array.isArray(data.event_types) ? data.event_types : []
        this.interests = Array.isArray(data.interests) ? data.interests : this.interests
        this.region = typeof data.region === 'string' ? data.region : DEFAULT_REGION
        this.locationLabel = typeof data.location_label === 'string' ? data.location_label : this.locationLabel
        this.locationPlaceId = typeof data.location_place_id === 'string' ? data.location_place_id : this.locationPlaceId
        this.locationLat = Number.isFinite(Number(data.location_lat)) ? Number(data.location_lat) : this.locationLat
        this.locationLon = Number.isFinite(Number(data.location_lon)) ? Number(data.location_lon) : this.locationLon
        this.bortleClass = Number.isInteger(Number(data.bortle_class))
          ? Math.min(9, Math.max(1, Number(data.bortle_class)))
          : this.bortleClass
        this.onboardingCompletedAt = typeof data.onboarding_completed_at === 'string' && data.onboarding_completed_at
          ? data.onboarding_completed_at
          : this.onboardingCompletedAt
        this.hasPreferences = Boolean(data.has_preferences)
        this.supportedEventTypes = Array.isArray(meta.supported_event_types) ? meta.supported_event_types : this.supportedEventTypes
        this.supportedRegions = Array.isArray(meta.supported_regions) && meta.supported_regions.length > 0
          ? meta.supported_regions
          : this.supportedRegions
        this.supportedInterests = Array.isArray(meta.supported_interests) ? meta.supported_interests : this.supportedInterests
        this.loaded = true

        return response
      } catch (error) {
        this.error = error?.response?.data?.message || 'Nepodarilo sa ulozit preferencie.'
        throw error
      } finally {
        this.saving = false
      }
    },

    async ensureInterestsLoaded() {
      if (this.supportedInterests.length > 0) return this.supportedInterests

      const response = await getOnboardingInterests()
      const rows = Array.isArray(response?.data?.data) ? response.data.data : []
      this.supportedInterests = rows
      return rows
    },

    async saveOnboarding(payload) {
      const completedAt = payload?.onboarding_completed_at || new Date().toISOString()

      return this.savePreferences({
        interests: Array.isArray(payload?.interests) ? payload.interests : [],
        location_label: payload?.location_label ?? '',
        location_place_id: payload?.location_place_id ?? null,
        location_lat: payload?.location_lat ?? null,
        location_lon: payload?.location_lon ?? null,
        onboarding_completed_at: completedAt,
      })
    },

    async markOnboardingComplete() {
      return this.saveOnboarding({
        interests: [],
        location_label: '',
        location_place_id: null,
        location_lat: null,
        location_lon: null,
      })
    },

    async fetch(force = false) {
      return this.fetchPreferences(force)
    },

    async save(payload) {
      return this.savePreferences(payload)
    },
  },
})
