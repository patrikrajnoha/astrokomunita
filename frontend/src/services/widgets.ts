import api from './api'

const MOON_WIDGET_CACHE_TTL_MS = 2 * 60 * 1000
const moonWidgetResponseCache = new Map<string, { expiresAt: number, payload: unknown }>()
const moonWidgetPendingRequests = new Map<string, Promise<unknown>>()
const SIDEBAR_WIDGET_BUNDLE_CACHE_TTL_MS = 60 * 1000
const sidebarWidgetBundleResponseCache = new Map<string, { expiresAt: number, payload: SidebarWidgetBundlePayload }>()
const sidebarWidgetBundlePendingRequests = new Map<string, Promise<SidebarWidgetBundlePayload>>()

function normalizeMoonQuery(query: MoonPhasesWidgetQuery = {}): Record<string, string | number> {
  const normalized: Record<string, string | number> = {}

  const lat = toFiniteCoordinate(query.lat)
  const lon = toFiniteCoordinate(query.lon)
  if (lat !== null) normalized.lat = lat
  if (lon !== null) normalized.lon = lon

  const tz = String(query.tz || '').trim()
  if (tz) normalized.tz = tz

  const date = String(query.date || '').trim()
  if (/^\d{4}-\d{2}-\d{2}$/.test(date)) normalized.date = date

  if (Number.isFinite(Number(query.year))) normalized.year = Number(query.year)

  return normalized
}

function toFiniteCoordinate(value: unknown): number | null {
  if (value === null || value === undefined) return null
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  if (typeof value !== 'string') return null

  const normalized = value.trim()
  if (!normalized) return null

  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : null
}

function buildMoonWidgetCacheKey(endpoint: string, query: MoonPhasesWidgetQuery = {}): string {
  const normalized = normalizeMoonQuery(query)
  const entries = Object.entries(normalized).sort(([a], [b]) => a.localeCompare(b))
  const search = new URLSearchParams(entries.map(([key, value]) => [key, String(value)]))
  return `${endpoint}?${search.toString()}`
}

async function fetchMoonWidget<T>(endpoint: string, query: MoonPhasesWidgetQuery = {}): Promise<T> {
  const normalizedQuery = normalizeMoonQuery(query)
  const cacheKey = buildMoonWidgetCacheKey(endpoint, normalizedQuery)
  const now = Date.now()
  const cached = moonWidgetResponseCache.get(cacheKey)

  if (cached && cached.expiresAt > now) {
    return cached.payload as T
  }

  const pending = moonWidgetPendingRequests.get(cacheKey)
  if (pending) {
    return pending as Promise<T>
  }

  const requestPromise = api.get<T>(endpoint, {
    params: normalizedQuery,
    meta: {
      skipErrorToast: true,
    },
  })
    .then((response) => {
      moonWidgetResponseCache.set(cacheKey, {
        payload: response.data,
        expiresAt: Date.now() + MOON_WIDGET_CACHE_TTL_MS,
      })
      return response.data
    })
    .finally(() => {
      if (moonWidgetPendingRequests.get(cacheKey) === requestPromise) {
        moonWidgetPendingRequests.delete(cacheKey)
      }
    })

  moonWidgetPendingRequests.set(cacheKey, requestPromise)
  return requestPromise
}

export type UpcomingEventWidgetItem = {
  id: number
  title: string
  type: string | null
  slug: string | null
  start_at: string | null
}

export type UpcomingEventsWidgetPayload = {
  items: UpcomingEventWidgetItem[]
  source?: {
    provider?: string
    label?: string
    url?: string
  }
  generated_at: string
}

export type SidebarWidgetBundlePayload = {
  requested_sections: string[]
  data: Record<string, unknown>
}

export type SidebarWidgetBundleQuery = {
  lat?: number | string | null
  lon?: number | string | null
  tz?: string | null
  date?: string | null
}

function normalizeSidebarWidgetBundleSections(sections: string[] = []): string[] {
  return Array.from(new Set(
    (Array.isArray(sections) ? sections : [])
      .map((entry) => String(entry || '').trim())
      .filter(Boolean),
  ))
}

function normalizeSidebarWidgetBundleQuery(
  query: SidebarWidgetBundleQuery = {},
): Record<string, string | number> {
  const moonQuery: MoonPhasesWidgetQuery = {}
  const lat = toFiniteCoordinate(query.lat)
  const lon = toFiniteCoordinate(query.lon)
  if (lat !== null) moonQuery.lat = lat
  if (lon !== null) moonQuery.lon = lon
  if (query.tz) moonQuery.tz = query.tz
  if (query.date) moonQuery.date = query.date
  return normalizeMoonQuery(moonQuery)
}

function buildSidebarWidgetBundleCacheKey(
  sections: string[],
  query: SidebarWidgetBundleQuery = {},
): string {
  const normalizedSections = [...normalizeSidebarWidgetBundleSections(sections)].sort((left, right) => (
    left.localeCompare(right)
  ))
  const normalizedQuery = normalizeSidebarWidgetBundleQuery(query)
  const queryEntries = Object.entries(normalizedQuery).sort(([left], [right]) => left.localeCompare(right))
  const search = new URLSearchParams(queryEntries.map(([key, value]) => [key, String(value)]))

  return `${normalizedSections.join(',')}?${search.toString()}`
}

export async function getUpcomingEventsWidget(): Promise<UpcomingEventsWidgetPayload> {
  const response = await api.get<UpcomingEventsWidgetPayload>('/events/widget/upcoming')
  return response.data
}

export async function getSidebarWidgetBundle(
  sections: string[],
  query: SidebarWidgetBundleQuery = {},
): Promise<SidebarWidgetBundlePayload> {
  const normalizedSections = normalizeSidebarWidgetBundleSections(sections)

  if (normalizedSections.length === 0) {
    return {
      requested_sections: [],
      data: {},
    }
  }

  const normalizedQuery = normalizeSidebarWidgetBundleQuery(query)
  const cacheKey = buildSidebarWidgetBundleCacheKey(normalizedSections, normalizedQuery)
  const now = Date.now()
  const cached = sidebarWidgetBundleResponseCache.get(cacheKey)
  if (cached && cached.expiresAt > now) {
    return cached.payload
  }

  const pending = sidebarWidgetBundlePendingRequests.get(cacheKey)
  if (pending) {
    return pending
  }

  const requestPromise = api.get<SidebarWidgetBundlePayload>('/sidebar-data', {
    params: { sections: normalizedSections, ...normalizedQuery },
    meta: {
      skipErrorToast: true,
    },
  })
    .then((response) => {
      const payload = response.data
      sidebarWidgetBundleResponseCache.set(cacheKey, {
        payload,
        expiresAt: Date.now() + SIDEBAR_WIDGET_BUNDLE_CACHE_TTL_MS,
      })
      return payload
    })
    .finally(() => {
      if (sidebarWidgetBundlePendingRequests.get(cacheKey) === requestPromise) {
        sidebarWidgetBundlePendingRequests.delete(cacheKey)
      }
    })

  sidebarWidgetBundlePendingRequests.set(cacheKey, requestPromise)
  return requestPromise
}

export type MoonPhaseWidgetItem = {
  key: string
  label: string
  start_at: string
  end_at: string
  start_date: string
  end_date: string
  is_current: boolean
}

export type MoonPhaseMajorEventItem = {
  key: string
  label: string
  at: string
  date: string
  time: string
  is_current: boolean
}

export type MoonSpecialEventWidgetItem = {
  key: string
  label: string
  at: string | null
  date: string | null
  time: string | null
  note: string | null
}

export type MoonPhasesWidgetPayload = {
  reference_at: string
  reference_date: string
  timezone: string
  current_phase: string
  phases: MoonPhaseWidgetItem[]
  major_events?: MoonPhaseMajorEventItem[]
  source: {
    provider: string
    label: string
    url: string
    api_key_required: boolean
  }
}

export type MoonEventsWidgetPayload = {
  year: number
  timezone: string
  events: MoonSpecialEventWidgetItem[]
  source: {
    moon_phases: {
      provider: string
      label: string
      url: string
      api_key_required: boolean
    }
    distance: {
      provider: string
      label: string
      url: string
      api_key_required: boolean
    }
  }
}

export type MoonOverviewWidgetPayload = {
  reference_at: string
  timezone: string
  moon_phase: string
  moon_illumination_percent: number | null
  moon_altitude_deg: number | null
  moon_azimuth_deg: number | null
  moon_direction: string | null
  moon_distance_km: number | null
  next_new_moon_at: string | null
  next_full_moon_at: string | null
  next_moonrise_at: string | null
  source: {
    phase: {
      provider: string
      label: string
      url: string
      api_key_required: boolean
    }
    position: {
      provider: string
      label: string
      url: string
      api_key_required: boolean
    }
    next_phases: {
      provider: string
      label: string
      url: string
      api_key_required: boolean
    }
  }
}

export type MoonPhasesWidgetQuery = {
  lat?: number
  lon?: number
  tz?: string
  date?: string
  year?: number
}

export async function getMoonPhasesWidget(query: MoonPhasesWidgetQuery = {}): Promise<MoonPhasesWidgetPayload> {
  return fetchMoonWidget<MoonPhasesWidgetPayload>('/sky/moon-phases', query)
}

export async function getMoonEventsWidget(query: MoonPhasesWidgetQuery = {}): Promise<MoonEventsWidgetPayload> {
  return fetchMoonWidget<MoonEventsWidgetPayload>('/sky/moon-events', query)
}

export async function getMoonOverviewWidget(query: MoonPhasesWidgetQuery = {}): Promise<MoonOverviewWidgetPayload> {
  return fetchMoonWidget<MoonOverviewWidgetPayload>('/sky/moon-overview', query)
}
