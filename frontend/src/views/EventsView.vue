<template src="./events/EventsView.template.html"></template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import CalendarView from './CalendarView.vue'
import FutureEventsEmptyState from '@/components/events/FutureEventsEmptyState.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useFavoritesStore } from '@/stores/favorites'
import { useAuthStore } from '@/stores/auth'
import { getEvents, getEventYears, lookupEventsByIds } from '@/services/events'
import {
  buildPeriodQuery,
  normalizeScope,
  parsePositiveInt,
  resolveDefaultYear,
  resolvePeriodSelectionFromQuery,
} from '@/utils/eventFilters'
import {
  EVENT_TIMEZONE,
  getEventNowPeriodDefaults,
} from '@/utils/eventTime'
import { getEcho, initEcho } from '@/realtime/echo'
import { eventDisplayTitle } from '@/utils/translatedFields'
import {
  eventCardSummary,
  eventCardTimeAriaLabel,
  eventCardTimeContext,
  eventCardTimeMessage,
  eventCardTimeTimezoneLabel,
  formatCardDate,
  publicConfidenceBadgeLabel,
  publicConfidenceTooltip,
  regionLabel,
  shouldShowRegion,
  typeLabel,
} from './events/eventsViewCard.utils'

const route = useRoute()
const router = useRouter()

const favorites = useFavoritesStore()
const auth = useAuthStore()
const initialPeriod = getEventNowPeriodDefaults(EVENT_TIMEZONE)

const selectedType = ref('all')
const selectedRegion = ref('all')
const searchQuery = ref('')
const selectedScope = ref('future')
const selectedYear = ref(initialPeriod.year)
const selectedPeriod = ref('month')
const selectedMonth = ref(initialPeriod.month)
const selectedWeek = ref(initialPeriod.week)
const page = ref(1)
const yearOptions = ref([])
const filtersOpen = ref(false)
const isApplyingRoute = ref(false)
const isReady = ref(false)

const eventResponse = ref({ data: [] })
const loading = ref(false)
const error = ref('')
const pendingRealtimeIds = ref([])
const loadingRealtimePending = ref(false)
const freshEventIds = ref(new Set())
const realtimeBannerDismissed = ref(false)

const MAX_PENDING_REALTIME_IDS = 20
let activeRealtimeChannel = ''
let suppressLocalFilterWatch = false
const freshTimeouts = new Map()

const scopeOptions = [
  { value: 'future', label: 'Buduce' },
  { value: 'past', label: 'Minule' },
  { value: 'all', label: 'Vsetky' },
]

const monthOptions = [
  { value: 1, label: 'Januar' },
  { value: 2, label: 'Februar' },
  { value: 3, label: 'Marec' },
  { value: 4, label: 'April' },
  { value: 5, label: 'Maj' },
  { value: 6, label: 'Jun' },
  { value: 7, label: 'Jul' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'Oktober' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const allFeedTypeGroups = {
  meteors: ['meteors', 'meteor_shower'],
  eclipses: ['eclipse', 'eclipse_lunar', 'eclipse_solar'],
  conjunctions: ['conjunction', 'planetary_event'],
  comets: ['comet', 'asteroid', 'other'],
}

const isCalendarView = computed(() => route.query?.view === 'calendar')
const events = computed(() =>
  Array.isArray(eventResponse.value?.data) ? eventResponse.value.data : [],
)
const pagination = computed(() => eventResponse.value?.meta || null)
const totalEvents = computed(() => pagination.value?.total ?? events.value.length)
const currentPage = computed(() => pagination.value?.current_page ?? page.value)
const lastPage = computed(() => pagination.value?.last_page ?? 1)
const hasPagination = computed(() => !isCalendarView.value && lastPage.value > 1)
const shouldShowRealtimeBanner = computed(
  () => pendingRealtimeIds.value.length > 0 && !realtimeBannerDismissed.value,
)
const shouldUseFutureEmptyState = computed(
  () =>
    !isCalendarView.value &&
    !loading.value &&
    !error.value &&
    events.value.length === 0 &&
    selectedScope.value === 'future',
)
const realtimeBannerLabel = computed(() => {
  const count = pendingRealtimeIds.value.length
  const noun = count === 1 ? 'Nova udalost' : 'Nove udalosti'
  return `${noun} (${count})`
})
const weekOptions = computed(() => Array.from({ length: 53 }, (_, idx) => idx + 1))

function currentPeriodDefaults() {
  return getEventNowPeriodDefaults(EVENT_TIMEZONE)
}

function normalizePeriod(value) {
  return ['month', 'week', 'year'].includes(value) ? value : 'month'
}

function currentEventItems() {
  return Array.isArray(eventResponse.value?.data) ? eventResponse.value.data : []
}

function buildParams() {
  const params = { feed: 'all', scope: selectedScope.value, page: page.value }

  if (selectedType.value !== 'all') params.types = allFeedTypeGroups[selectedType.value] || []
  if (selectedRegion.value !== 'all') params.region = selectedRegion.value
  if (searchQuery.value) params.q = searchQuery.value

  params.year = selectedYear.value
  if (selectedPeriod.value === 'month') params.month = selectedMonth.value
  if (selectedPeriod.value === 'week') params.week = selectedWeek.value

  return params
}

function buildManagedQuery() {
  const nextQuery = { ...route.query, scope: selectedScope.value }

  delete nextQuery.month
  delete nextQuery.week

  Object.assign(
    nextQuery,
    buildPeriodQuery({
      period: selectedPeriod.value,
      year: selectedYear.value,
      month: selectedMonth.value,
      week: selectedWeek.value,
    }),
  )

  if (page.value > 1) nextQuery.page = String(page.value)
  else delete nextQuery.page

  return nextQuery
}

function buildRouteSnapshot(query = route.query) {
  const defaults = currentPeriodDefaults()
  const state = resolvePeriodSelectionFromQuery(query, {
    now: new Date(Date.UTC(defaults.year, defaults.month - 1, 1)),
    year: selectedYear.value || defaults.year,
    month: selectedMonth.value || defaults.month,
    week: selectedWeek.value || defaults.week,
  })

  return {
    scope: normalizeScope(query.scope),
    page: String(parsePositiveInt(query.page, 1)),
    period: normalizePeriod(state.period),
    year: String(state.year),
    month: normalizePeriod(state.period) === 'month' ? String(state.month) : '',
    week: normalizePeriod(state.period) === 'week' ? String(state.week) : '',
  }
}

function buildLocalSnapshot() {
  return {
    scope: normalizeScope(selectedScope.value),
    page: String(page.value),
    period: normalizePeriod(selectedPeriod.value),
    year: String(selectedYear.value),
    month: selectedPeriod.value === 'month' ? String(selectedMonth.value) : '',
    week: selectedPeriod.value === 'week' ? String(selectedWeek.value) : '',
  }
}

async function syncManagedRouteQuery() {
  if (isApplyingRoute.value) return false

  const current = buildRouteSnapshot(route.query)
  const next = buildLocalSnapshot()
  const unchanged =
    current.scope === next.scope &&
    current.page === next.page &&
    current.period === next.period &&
    current.year === next.year &&
    current.month === next.month &&
    current.week === next.week

  if (unchanged) return false

  await router.replace({ name: 'events', query: buildManagedQuery() })
  return true
}

function applyRouteState() {
  isApplyingRoute.value = true

  const defaults = currentPeriodDefaults()
  const fallbackYear = yearOptions.value.includes(selectedYear.value)
    ? selectedYear.value
    : yearOptions.value[0] || defaults.year
  const state = resolvePeriodSelectionFromQuery(route.query, {
    now: new Date(Date.UTC(defaults.year, defaults.month - 1, 1)),
    year: fallbackYear,
    month: defaults.month,
    week: defaults.week,
  })

  selectedYear.value = yearOptions.value.includes(state.year) ? state.year : fallbackYear
  selectedPeriod.value = normalizePeriod(state.period)
  selectedMonth.value = state.month
  selectedWeek.value = state.week
  selectedScope.value = normalizeScope(route.query.scope)
  page.value = parsePositiveInt(route.query.page, 1)

  isApplyingRoute.value = false
}

async function fetchEvents() {
  if (isCalendarView.value) return

  loading.value = true
  error.value = ''

  try {
    const response = await getEvents(buildParams())
    eventResponse.value = response?.data || { data: [] }
  } catch (err) {
    error.value =
      err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa nacitat udalosti.'
    eventResponse.value = { data: [] }
  } finally {
    loading.value = false
  }
}

function isNearTopOfPage() {
  if (typeof window === 'undefined') return false
  return window.scrollY <= 50
}

function isEventFresh(eventId) {
  return freshEventIds.value.has(Number(eventId))
}

function markEventFresh(eventId) {
  const normalized = Number(eventId)
  if (!Number.isInteger(normalized) || normalized <= 0) return

  const next = new Set(freshEventIds.value)
  next.add(normalized)
  freshEventIds.value = next

  const existingTimeout = freshTimeouts.get(normalized)
  if (existingTimeout) window.clearTimeout(existingTimeout)

  const timeoutId = window.setTimeout(() => {
    const snapshot = new Set(freshEventIds.value)
    snapshot.delete(normalized)
    freshEventIds.value = snapshot
    freshTimeouts.delete(normalized)
  }, 2600)

  freshTimeouts.set(normalized, timeoutId)
}

function isEventAlreadyPresent(eventId) {
  const normalized = Number(eventId)
  return currentEventItems().some((eventItem) => Number(eventItem?.id) === normalized)
}

function prependEventIfMissing(eventItem) {
  const eventId = Number(eventItem?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return false
  if (isEventAlreadyPresent(eventId)) return false

  eventResponse.value = {
    ...(eventResponse.value || {}),
    data: [eventItem, ...currentEventItems()],
  }
  markEventFresh(eventId)
  return true
}

function enqueuePendingRealtimeEvent(eventId) {
  const normalized = Number(eventId)
  if (!Number.isInteger(normalized) || normalized <= 0) return
  if (isEventAlreadyPresent(normalized) || pendingRealtimeIds.value.includes(normalized)) return

  pendingRealtimeIds.value = [...pendingRealtimeIds.value, normalized].slice(
    -MAX_PENDING_REALTIME_IDS,
  )
  realtimeBannerDismissed.value = false
}

async function loadPendingRealtimeEvents(options = {}) {
  if (loadingRealtimePending.value || pendingRealtimeIds.value.length === 0) return

  loadingRealtimePending.value = true

  try {
    const idsToLoad = [...pendingRealtimeIds.value]
    const response = await lookupEventsByIds(idsToLoad)
    const fetched = Array.isArray(response?.data?.data) ? response.data.data : []

    pendingRealtimeIds.value = pendingRealtimeIds.value.filter((id) => !idsToLoad.includes(id))

    for (let index = fetched.length - 1; index >= 0; index -= 1) {
      prependEventIfMissing(fetched[index])
    }

    if (options.refetchAfter !== false) await fetchEvents()
  } catch (err) {
    console.warn('Realtime event fetch failed:', err?.message || err)
  } finally {
    loadingRealtimePending.value = false
  }
}

async function startRealtimeFeed() {
  if (isCalendarView.value || activeRealtimeChannel === 'events.feed') return

  const echo = await initEcho()
  if (!echo) return

  activeRealtimeChannel = 'events.feed'
  echo.channel(activeRealtimeChannel).listen('.event.published', async (payload) => {
    const eventId = Number(payload?.event_id || payload?.id || 0)
    if (!Number.isInteger(eventId) || eventId <= 0 || isEventAlreadyPresent(eventId)) return

    if (isNearTopOfPage()) {
      try {
        const response = await lookupEventsByIds([eventId])
        const fetched = Array.isArray(response?.data?.data) ? response.data.data : []
        prependEventIfMissing(fetched[0])
      } catch (err) {
        console.warn('Realtime single event fetch failed:', err?.message || err)
      }
      return
    }

    enqueuePendingRealtimeEvent(eventId)
  })
}

function dismissRealtimeBanner() {
  realtimeBannerDismissed.value = true
}

function stopRealtimeFeed() {
  const echo = getEcho()
  if (echo && activeRealtimeChannel) echo.leaveChannel(activeRealtimeChannel)
  activeRealtimeChannel = ''
}

function setView(view) {
  const nextQuery = { ...route.query }
  if (view === 'calendar') nextQuery.view = 'calendar'
  else delete nextQuery.view
  router.replace({ name: 'events', query: nextQuery })
}

async function setScope(scope) {
  const normalized = normalizeScope(scope)
  if (selectedScope.value === normalized && page.value === 1) return

  selectedScope.value = normalized
  page.value = 1

  const routeChanged = await syncManagedRouteQuery()
  if (!routeChanged && !isCalendarView.value) await fetchEvents()
}

async function onPeriodSelectionChanged() {
  if (isApplyingRoute.value) return

  page.value = 1

  const routeChanged = await syncManagedRouteQuery()
  if (!routeChanged && !isCalendarView.value) await fetchEvents()
}

async function goToPage(nextPageValue) {
  const nextPage = Math.max(1, Number(nextPageValue) || 1)
  if (page.value === nextPage) return

  page.value = nextPage

  const routeChanged = await syncManagedRouteQuery()
  if (!routeChanged && !isCalendarView.value) await fetchEvents()
}

function prevPage() {
  if (!loading.value && currentPage.value > 1) goToPage(currentPage.value - 1)
}

function nextPage() {
  if (!loading.value && currentPage.value < lastPage.value) goToPage(currentPage.value + 1)
}

async function resetFilters() {
  const defaults = currentPeriodDefaults()
  suppressLocalFilterWatch = true
  selectedType.value = 'all'
  selectedRegion.value = 'all'
  searchQuery.value = ''
  selectedScope.value = 'future'
  selectedPeriod.value = 'month'
  selectedYear.value = defaults.year
  selectedMonth.value = defaults.month
  selectedWeek.value = defaults.week
  page.value = 1
  await nextTick()
  suppressLocalFilterWatch = false

  const routeChanged = await syncManagedRouteQuery()
  if (!routeChanged && !isCalendarView.value) await fetchEvents()
}

async function showAllEvents() {
  await setScope('all')
}

async function toggleFavorite(eventId) {
  await favorites.toggle(eventId)
}

function favoriteToggleLabel(eventId) {
  if (!auth.isAuthed) return 'Prihlaste sa pre sledovanie udalosti'
  return favorites.isFavorite(eventId) ? 'Odobrat zo sledovanych' : 'Pridat do sledovanych'
}

watch([selectedType, selectedRegion, searchQuery], async () => {
  if (!isReady.value || isApplyingRoute.value || suppressLocalFilterWatch || isCalendarView.value)
    return

  if (page.value !== 1) {
    page.value = 1
    const routeChanged = await syncManagedRouteQuery()
    if (!routeChanged) await fetchEvents()
    return
  }

  await fetchEvents()
})

watch(
  () => route.query,
  async () => {
    if (!isReady.value) return
    applyRouteState()
    if (!isCalendarView.value) await fetchEvents()
  },
  { deep: true },
)

onMounted(async () => {
  try {
    const yearsRes = await getEventYears()
    const meta = yearsRes?.data || {}
    yearOptions.value = Array.isArray(meta.years) ? meta.years : []
    const defaults = currentPeriodDefaults()
    const defaultYear = resolveDefaultYear(meta, new Date(Date.UTC(defaults.year, defaults.month - 1, 1)))
    if (!yearOptions.value.includes(defaultYear)) yearOptions.value = [defaultYear]
    selectedYear.value = defaultYear
  } catch {
    const minYear = 2021
    const maxYear = 2100
    const defaults = currentPeriodDefaults()
    const defaultYear = resolveDefaultYear(
      { minYear, maxYear },
      new Date(Date.UTC(defaults.year, defaults.month - 1, 1)),
    )
    yearOptions.value = Array.from({ length: maxYear - minYear + 1 }, (_, idx) => minYear + idx)
    selectedYear.value = defaultYear
  }

  applyRouteState()
  isReady.value = true

  const routeChanged = await syncManagedRouteQuery()
  if (!routeChanged && !isCalendarView.value) await fetchEvents()

  if (!isCalendarView.value && auth.isAuthed && favorites.ids.size === 0 && !favorites.loading) {
    await favorites.fetch()
  }

  void startRealtimeFeed()
})

watch(isCalendarView, (calendarView) => {
  if (calendarView) {
    stopRealtimeFeed()
    return
  }
  void startRealtimeFeed()
})

onBeforeUnmount(() => {
  stopRealtimeFeed()
  freshTimeouts.forEach((timeoutId) => window.clearTimeout(timeoutId))
  freshTimeouts.clear()
})
</script>

<style scoped src="./events/EventsView.css"></style>
