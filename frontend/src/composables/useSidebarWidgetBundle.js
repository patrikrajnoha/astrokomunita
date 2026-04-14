import { computed, ref, watch } from 'vue'
import { getSidebarWidgetBundle } from '@/services/widgets'

export const SIDEBAR_WIDGET_BUNDLE_SECTION_KEYS = new Set([
  'observing_conditions',
  'observing_weather',
  'night_sky',
  'constellations_now',
  'iss_pass',
  'nasa_apod',
  'next_event',
  'next_eclipse',
  'next_meteor_shower',
  'space_weather',
  'aurora_watch',
  'neo_watchlist',
  'upcoming_launches',
  'latest_articles',
  'upcoming_events',
])

function normalizeSidebarWidgetBundleSectionKeys(sectionKeys = []) {
  return Array.from(new Set(
    (Array.isArray(sectionKeys) ? sectionKeys : [])
      .map((entry) => String(entry || '').trim())
      .filter((entry) => entry !== ''),
  ))
}

function normalizeSidebarWidgetBundleQuery(query = {}) {
  const normalized = {}

  const lat = toFiniteCoordinate(query?.lat)
  const lon = toFiniteCoordinate(query?.lon)
  if (lat !== null) normalized.lat = lat
  if (lon !== null) normalized.lon = lon

  const tz = String(query?.tz || '').trim()
  if (tz) normalized.tz = tz

  const date = String(query?.date || '').trim()
  if (/^\d{4}-\d{2}-\d{2}$/.test(date)) normalized.date = date

  return normalized
}

function toFiniteCoordinate(value) {
  if (value === null || value === undefined) return null
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  if (typeof value !== 'string') return null

  const normalized = value.trim()
  if (!normalized) return null

  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : null
}

export function useSidebarWidgetBundle({
  enabled,
  query,
  sectionKeys,
}) {
  const bundledSectionPayloads = ref({})
  const bundlePending = ref(false)
  const normalizedSectionKeys = computed(() => normalizeSidebarWidgetBundleSectionKeys(sectionKeys?.value))
  const normalizedQuery = computed(() => normalizeSidebarWidgetBundleQuery(query?.value))
  const sectionSignature = computed(() => normalizedSectionKeys.value.join('|'))
  const querySignature = computed(() => JSON.stringify(normalizedQuery.value))
  let bundleRequestId = 0
  let loadedBundleSignature = ''
  let pendingBundleSignature = ''

  const resetBundle = () => {
    bundleRequestId += 1
    bundledSectionPayloads.value = {}
    bundlePending.value = false
    loadedBundleSignature = ''
    pendingBundleSignature = ''
  }

  const syncBundle = async (
    nextSectionKeys = normalizedSectionKeys.value,
    options = {},
  ) => {
    const nextNormalizedSectionKeys = normalizeSidebarWidgetBundleSectionKeys(nextSectionKeys)
    const force = options?.force === true

    if (!enabled?.value || nextNormalizedSectionKeys.length === 0) {
      resetBundle()
      return
    }

    const signature = `${nextNormalizedSectionKeys.join('|')}::${querySignature.value}`
    if (!force && (signature === loadedBundleSignature || signature === pendingBundleSignature)) {
      return
    }

    bundleRequestId += 1
    const requestId = bundleRequestId
    pendingBundleSignature = signature
    bundlePending.value = true
    bundledSectionPayloads.value = {}

    try {
      const payload = await getSidebarWidgetBundle(nextNormalizedSectionKeys, normalizedQuery.value)
      if (requestId !== bundleRequestId) return

      bundledSectionPayloads.value =
        payload?.data && typeof payload.data === 'object'
          ? payload.data
          : {}
      loadedBundleSignature = signature
    } catch {
      if (requestId !== bundleRequestId) return
      bundledSectionPayloads.value = {}
      loadedBundleSignature = ''
    } finally {
      if (requestId === bundleRequestId) {
        pendingBundleSignature = ''
        bundlePending.value = false
      }
    }
  }

  watch(
    () => [
      Boolean(enabled?.value),
      sectionSignature.value,
      querySignature.value,
    ],
    async ([isEnabled, nextSectionSignature, nextQuerySignature], previous = []) => {
      const previousSectionSignature = Array.isArray(previous) ? previous[1] : undefined
      const previousQuerySignature = Array.isArray(previous) ? previous[2] : undefined
      const signatureChanged = (
        nextSectionSignature !== previousSectionSignature
        || nextQuerySignature !== previousQuerySignature
      )

      if (!isEnabled || signatureChanged) {
        resetBundle()
      }

      if (!isEnabled) return
      await syncBundle(normalizedSectionKeys.value)
    },
    { immediate: true },
  )

  return {
    bundlePending,
    bundledSectionPayloads,
    normalizedQuery,
    normalizedSectionKeys,
    resetBundle,
    syncBundle,
  }
}
