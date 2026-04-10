import { defineStore } from 'pinia'
import { getMyPreferences, updateMyPreferences, getOnboardingInterests } from '@/services/events'
import { useAuthStore } from '@/stores/auth'

const DEFAULT_REGION = 'global'
const DEFAULT_INTERESTS = []
const DEFAULT_BORTLE_CLASS = 6
const MAX_SIDEBAR_WIDGETS = 3
let activePreferencesFetchPromise = null

const normalizeSidebarWidgetKeys = (value) => {
  if (!Array.isArray(value)) return []

  return Array.from(
    new Set(
      value
        .map((entry) => String(entry || '').trim())
        .filter(Boolean),
    ),
  ).slice(0, MAX_SIDEBAR_WIDGETS)
}

const normalizeSupportedSidebarWidgets = (value) => {
  if (!Array.isArray(value)) return []

  return value
    .map((entry) => ({
      section_key: String(entry?.section_key || '').trim(),
      title: String(entry?.title || '').trim(),
    }))
    .filter((entry) => entry.section_key !== '')
}

const normalizeSidebarWidgetOverrides = (value) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {}

  const normalized = {}
  Object.entries(value).forEach(([scope, keys]) => {
    const normalizedScope = String(scope || '').trim()
    if (!normalizedScope) return

    const normalizedKeys = normalizeSidebarWidgetKeys(keys)
    normalized[normalizedScope] = normalizedKeys
  })

  return normalized
}

const normalizeSupportedSidebarScopes = (value) => {
  if (!Array.isArray(value)) return []

  return Array.from(new Set(
    value
      .map((entry) => String(entry || '').trim())
      .filter(Boolean),
  ))
}

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
    sidebarWidgetKeys: [],
    sidebarWidgetOverrides: {},
    onboardingCompletedAt: null,
    supportedEventTypes: [],
    supportedRegions: ['sk', 'eu', 'global'],
    supportedInterests: [],
    supportedSidebarWidgets: [],
    supportedSidebarScopes: [],
    error: null,
  }),

  getters: {
    hasSelectedTypes: (state) => Array.isArray(state.eventTypes) && state.eventTypes.length > 0,
    isOnboardingCompleted: (state) => Boolean(state.onboardingCompletedAt),
    sidebarWidgetKeysForScope: (state) => (scope) => {
      const normalizedScope = typeof scope === 'string' ? scope.trim() : ''
      const hasExplicitScopedOverride = normalizedScope !== ''
        && Object.prototype.hasOwnProperty.call(state.sidebarWidgetOverrides || {}, normalizedScope)

      if (hasExplicitScopedOverride) {
        const scoped = state.sidebarWidgetOverrides?.[normalizedScope]
        if (Array.isArray(scoped)) return scoped
      }

      const globalHomeKeys = state.sidebarWidgetOverrides?.home
      if (Array.isArray(globalHomeKeys) && globalHomeKeys.length > 0) {
        return globalHomeKeys
      }

      return Array.isArray(state.sidebarWidgetKeys) ? state.sidebarWidgetKeys : []
    },
    hasSidebarWidgetOverrideForScope: (state) => (scope) => {
      const normalizedScope = typeof scope === 'string' ? scope.trim() : ''
      if (!normalizedScope) return false
      return Object.prototype.hasOwnProperty.call(state.sidebarWidgetOverrides || {}, normalizedScope)
    },
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
      this.sidebarWidgetKeys = []
      this.sidebarWidgetOverrides = {}
      this.onboardingCompletedAt = null
      this.supportedEventTypes = []
      this.supportedRegions = ['sk', 'eu', 'global']
      this.supportedInterests = []
      this.supportedSidebarWidgets = []
      this.supportedSidebarScopes = []
      this.error = null
    },

    async fetchPreferences(force = false) {
      if (this.loaded && !force) return this
      if (this.loading && activePreferencesFetchPromise) return activePreferencesFetchPromise

      const requestPromise = (async () => {
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
          const overrides = normalizeSidebarWidgetOverrides(data.sidebar_widget_overrides)
          const legacyHomeKeys = normalizeSidebarWidgetKeys(data.sidebar_widget_keys)
          this.sidebarWidgetOverrides = Object.keys(overrides).length > 0
            ? overrides
            : (legacyHomeKeys.length > 0 ? { home: legacyHomeKeys } : {})
          this.sidebarWidgetKeys = normalizeSidebarWidgetKeys(
            this.sidebarWidgetOverrides.home ?? legacyHomeKeys,
          )
          this.onboardingCompletedAt = typeof data.onboarding_completed_at === 'string' && data.onboarding_completed_at
            ? data.onboarding_completed_at
            : null
          this.hasPreferences = Boolean(data.has_preferences)
          this.supportedEventTypes = Array.isArray(meta.supported_event_types) ? meta.supported_event_types : []
          this.supportedRegions = Array.isArray(meta.supported_regions) && meta.supported_regions.length > 0
            ? meta.supported_regions
            : ['sk', 'eu', 'global']
          this.supportedInterests = Array.isArray(meta.supported_interests) ? meta.supported_interests : []
          this.supportedSidebarWidgets = normalizeSupportedSidebarWidgets(meta.supported_sidebar_widgets)
          this.supportedSidebarScopes = normalizeSupportedSidebarScopes(meta.supported_sidebar_scopes)

          this.loaded = true
          return this
        } catch (error) {
          this.error = error?.response?.data?.message || 'Nepodarilo sa nacitat preferencie.'
          throw error
        } finally {
          this.loading = false
        }
      })()

      const trackedPromise = requestPromise.finally(() => {
        if (activePreferencesFetchPromise === trackedPromise) {
          activePreferencesFetchPromise = null
        }
      })
      activePreferencesFetchPromise = trackedPromise

      return trackedPromise
    },

    async savePreferences(payload, options = {}) {
      if (this.saving) return null

      this.saving = true
      this.error = null

      try {
        const auth = useAuthStore()
        await auth.csrf()

        const response = await updateMyPreferences(payload, {
          meta: {
            skipAuthRedirect: options.skipAuthRedirect === true,
            skipErrorToast: options.skipErrorToast === true,
          },
        })
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
        const nextOverrides = normalizeSidebarWidgetOverrides(data.sidebar_widget_overrides)
        const hasOverridesPayload = Object.prototype.hasOwnProperty.call(data, 'sidebar_widget_overrides')
        this.sidebarWidgetOverrides = hasOverridesPayload
          ? nextOverrides
          : this.sidebarWidgetOverrides
        this.sidebarWidgetKeys = normalizeSidebarWidgetKeys(
          this.sidebarWidgetOverrides.home
            ?? (Array.isArray(data.sidebar_widget_keys) ? data.sidebar_widget_keys : this.sidebarWidgetKeys),
        )
        this.onboardingCompletedAt = typeof data.onboarding_completed_at === 'string' && data.onboarding_completed_at
          ? data.onboarding_completed_at
          : this.onboardingCompletedAt
        this.hasPreferences = Boolean(data.has_preferences)
        this.supportedEventTypes = Array.isArray(meta.supported_event_types) ? meta.supported_event_types : this.supportedEventTypes
        this.supportedRegions = Array.isArray(meta.supported_regions) && meta.supported_regions.length > 0
          ? meta.supported_regions
          : this.supportedRegions
        this.supportedInterests = Array.isArray(meta.supported_interests) ? meta.supported_interests : this.supportedInterests
        this.supportedSidebarWidgets = Array.isArray(meta.supported_sidebar_widgets)
          ? normalizeSupportedSidebarWidgets(meta.supported_sidebar_widgets)
          : this.supportedSidebarWidgets
        this.supportedSidebarScopes = Array.isArray(meta.supported_sidebar_scopes)
          ? normalizeSupportedSidebarScopes(meta.supported_sidebar_scopes)
          : this.supportedSidebarScopes
        this.loaded = true

        if (Object.prototype.hasOwnProperty.call(payload || {}, 'location_label')) {
          try {
            await auth.fetchUser({
              source: 'preferences-save',
              retry: false,
              markBootstrap: false,
              preserveStateOnError: true,
            })
          } catch {
            // Preference save should stay successful even if auth refresh fails.
          }
        }

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
      }, {
        skipAuthRedirect: true,
        skipErrorToast: true,
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
