<template>
  <div class="events-page">
    <section class="hero">
      <div class="hero-inner">
        <h1 class="hero-title">Astronomicke udalosti</h1>
        <p class="hero-subtitle">Najblizsie ukazy a archiv na jednom mieste.</p>
      </div>
    </section>

    <main class="content-wrap">
      <section class="filter-panel">
        <div class="toolbar-row">
          <div class="toolbar-segments">
            <div class="segmented-control" role="tablist" aria-label="Zobrazenie udalosti">
              <button
                class="segment-btn"
                :class="{ active: !isCalendarView }"
                type="button"
                @click="setView('list')"
              >
                Zoznam
              </button>
              <button
                class="segment-btn"
                :class="{ active: isCalendarView }"
                type="button"
                @click="setView('calendar')"
              >
                Kalendar
              </button>
            </div>

            <div
              v-if="!isCalendarView"
              class="segmented-control"
              role="tablist"
              aria-label="Casovy rozsah udalosti"
            >
              <button
                v-for="option in scopeOptions"
                :key="option.value"
                class="segment-btn"
                :class="{ active: selectedScope === option.value }"
                type="button"
                @click="setScope(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>

          <div v-if="!isCalendarView" class="toolbar-actions">
            <button
              class="filter-toggle-btn"
              type="button"
              :aria-expanded="filtersOpen"
              @click="filtersOpen = !filtersOpen"
            >
              <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M3 5h14" />
                <path d="M6 10h8" />
                <path d="M8 15h4" />
              </svg>
              <span>{{ filtersOpen ? 'Skryt filtre' : 'Zobrazit filtre' }}</span>
            </button>
          </div>
        </div>

        <template v-if="!isCalendarView">
          <div v-if="filtersOpen" class="filters-content">
            <div class="filter-row" role="tablist" aria-label="Filtre typu udalosti">
              <button
                class="filter-btn"
                :class="{ active: selectedType === 'all' }"
                type="button"
                @click="selectedType = 'all'"
              >
                Vsetky
              </button>
              <button
                class="filter-btn"
                :class="{ active: selectedType === 'meteors' }"
                type="button"
                @click="selectedType = 'meteors'"
              >
                Meteoricke roje
              </button>
              <button
                class="filter-btn"
                :class="{ active: selectedType === 'eclipses' }"
                type="button"
                @click="selectedType = 'eclipses'"
              >
                Zatmenia
              </button>
              <button
                class="filter-btn"
                :class="{ active: selectedType === 'conjunctions' }"
                type="button"
                @click="selectedType = 'conjunctions'"
              >
                Konjunkcie
              </button>
              <button
                class="filter-btn"
                :class="{ active: selectedType === 'comets' }"
                type="button"
                @click="selectedType = 'comets'"
              >
                Komety
              </button>
            </div>

            <div class="advanced-filters">
              <label class="filter-field">
                <span>Rok</span>
                <select v-model.number="selectedYear" @change="onPeriodSelectionChanged">
                  <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                </select>
              </label>

              <label class="filter-field">
                <span>Obdobie</span>
                <select v-model="selectedPeriod" @change="onPeriodSelectionChanged">
                  <option value="month">Mesiac</option>
                  <option value="week">Tyzden</option>
                  <option value="year">Vsetko v roku</option>
                </select>
              </label>

              <label v-if="selectedPeriod === 'month'" class="filter-field">
                <span>Mesiac</span>
                <select v-model.number="selectedMonth" @change="onPeriodSelectionChanged">
                  <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                    {{ month.label }}
                  </option>
                </select>
              </label>

              <label v-if="selectedPeriod === 'week'" class="filter-field">
                <span>ISO tyzden</span>
                <select v-model.number="selectedWeek" @change="onPeriodSelectionChanged">
                  <option v-for="week in weekOptions" :key="week" :value="week">{{ week }}</option>
                </select>
              </label>

              <label class="filter-field search-field">
                <span>Hladaj</span>
                <input
                  v-model.trim="searchQuery"
                  type="search"
                  placeholder="Nazov alebo popis udalosti"
                  autocomplete="off"
                />
              </label>

              <label class="filter-field">
                <span>Region</span>
                <select v-model="selectedRegion">
                  <option value="all">Vsetky regiony</option>
                  <option value="sk">Slovensko</option>
                  <option value="eu">Europa</option>
                  <option value="global">Globalne</option>
                </select>
              </label>

              <button class="secondary-btn" type="button" @click="resetFilters">
                Reset filtrov
              </button>
            </div>
          </div>

          <p class="filter-meta">
            Vysledkov: <strong>{{ totalEvents }}</strong>
            <span v-if="hasPagination"> &middot; Strana {{ currentPage }} z {{ lastPage }}</span>
          </p>
        </template>
      </section>

      <section v-if="isCalendarView" class="calendar-panel" data-tour="calendar">
        <CalendarView />
      </section>

      <AsyncState
        v-else-if="loading"
        mode="loading"
        title="Nacitavam udalosti"
        loading-style="skeleton"
        :skeleton-rows="5"
        compact
      />

      <section v-else-if="error" class="state-card state-error">
        <InlineStatus
          variant="error"
          :message="error"
          action-label="Skusit znova"
          @action="fetchEvents"
        />
      </section>

      <section v-else-if="events.length > 0">
        <div
          v-if="shouldShowRealtimeBanner"
          class="realtime-banner"
          role="status"
          aria-live="polite"
        >
          <button
            class="realtime-banner-main"
            type="button"
            :disabled="loadingRealtimePending"
            @click="loadPendingRealtimeEvents"
          >
            {{ realtimeBannerLabel }}
          </button>
          <button
            class="realtime-banner-dismiss"
            type="button"
            aria-label="Skryt banner novych udalosti"
            @click="dismissRealtimeBanner"
          >
            x
          </button>
        </div>

        <div class="events-grid">
          <RouterLink
            v-for="eventItem in events"
            :key="eventItem.id"
            :to="`/events/${eventItem.id}`"
            class="event-card"
            :class="{ 'event-card-new': isEventFresh(eventItem.id) }"
          >
            <div class="card-content">
              <div class="card-header">
                <div class="card-heading">
                  <h3 class="card-title">{{ eventDisplayTitle(eventItem) }}</h3>
                </div>

                <button
                  class="favorite-switch"
                  :class="{ active: favorites.isFavorite(eventItem.id) }"
                  type="button"
                  :disabled="favorites.loading || !auth.isAuthed"
                  :aria-pressed="favorites.isFavorite(eventItem.id)"
                  :aria-label="favoriteToggleLabel(eventItem.id)"
                  @click.prevent.stop="toggleFavorite(eventItem.id)"
                >
                  <span class="favorite-switch-track" aria-hidden="true">
                    <span class="favorite-switch-thumb"></span>
                  </span>
                  <span class="sr-only">{{ favoriteToggleLabel(eventItem.id) }}</span>
                </button>
              </div>

              <div class="meta-row">
                <span class="type-badge">{{ typeLabel(eventItem.type) }}</span>
                <span
                  v-if="publicConfidenceBadgeLabel(eventItem)"
                  class="confidence-badge"
                  :class="`confidence-${eventItem?.public_confidence?.level || 'unknown'}`"
                  :title="publicConfidenceTooltip(eventItem)"
                >
                  {{ publicConfidenceBadgeLabel(eventItem) }}
                </span>
                <span class="card-meta-text">{{ formatCardDate(eventItem.max_at || eventItem.start_at) }}</span>
                <span class="card-meta-separator" aria-hidden="true">&middot;</span>
                <span
                  class="card-meta-text"
                  :title="eventCardTimeAriaLabel(eventItem)"
                  :aria-label="eventCardTimeAriaLabel(eventItem)"
                >
                  {{ eventCardTimeMessage(eventItem) }}
                  <span v-if="eventCardTimeTimezoneLabel(eventItem)" class="card-timezone-label">
                    ({{ eventCardTimeTimezoneLabel(eventItem) }})
                  </span>
                </span>
                <span
                  v-if="shouldShowRegion(eventItem.region_scope)"
                  class="card-meta-separator"
                  aria-hidden="true"
                  >&middot;</span
                >
                <span v-if="shouldShowRegion(eventItem.region_scope)" class="card-meta-text">{{
                  regionLabel(eventItem.region_scope)
                }}</span>
              </div>

              <p v-if="eventCardSummary(eventItem)" class="card-description">
                {{ eventCardSummary(eventItem) }}
              </p>

              <div class="card-footer">
                <span class="open-label">Detail &rarr;</span>
              </div>
            </div>
          </RouterLink>
        </div>

        <div v-if="hasPagination" class="pager">
          <p class="pager-meta">
            Strana {{ currentPage }} z {{ lastPage }} | {{ totalEvents }} spolu
          </p>
          <div class="pager-actions">
            <button
              class="pager-btn"
              type="button"
              :disabled="loading || currentPage <= 1"
              @click="prevPage"
            >
              Predosla
            </button>
            <button
              class="pager-btn"
              type="button"
              :disabled="loading || currentPage >= lastPage"
              @click="nextPage"
            >
              Dalsia
            </button>
          </div>
        </div>
      </section>

      <FutureEventsEmptyState
        v-else-if="shouldUseFutureEmptyState"
        @show-all="showAllEvents"
        @reset-filters="resetFilters"
      />

      <div v-else-if="!isCalendarView && !loading && !error" class="state-card state-empty">
        <h3>{{ selectedScope === 'past' ? 'Ziadne minule udalosti' : 'Ziadne udalosti' }}</h3>
        <p>Skus upravit filtre, zmenit scope alebo rozsirit vyhladavanie.</p>
        <button class="secondary-btn" type="button" @click="resetFilters">Vymazat filtre</button>
      </div>
    </main>
  </div>
</template>

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
  formatEventDate,
  getEventNowPeriodDefaults,
  resolveEventTimeContext,
} from '@/utils/eventTime'
import { eventDisplayShort, eventDisplayTitle } from '@/utils/translatedFields'
import { getEcho, initEcho } from '@/realtime/echo'

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

function startRealtimeFeed() {
  if (isCalendarView.value || activeRealtimeChannel === 'events.feed') return

  const echo = initEcho()
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

function regionLabel(region) {
  const map = { sk: 'Slovensko', eu: 'Europa', global: 'Globalne' }
  return map[region] || region || '-'
}

function typeLabel(type) {
  const map = {
    meteors: 'Meteory',
    meteor_shower: 'Meteoricky roj',
    eclipse: 'Zatmenie',
    eclipse_lunar: 'Zatmenie (L)',
    eclipse_solar: 'Zatmenie (S)',
    conjunction: 'Konjunkcia',
    planetary_event: 'Planetarny ukaz',
    comet: 'Kometa',
    asteroid: 'Asteroid',
    mission: 'Misia',
    other: 'Ine',
  }

  return map[type] || type
}

function publicConfidenceBadgeLabel(eventItem) {
  const level = eventItem?.public_confidence?.level
  if (!level || level === 'unknown') return ''
  if (level === 'verified') return 'Overene'
  if (level === 'partial') return 'Ciastocne'
  if (level === 'low') return 'Nizka dovera'
  return ''
}

function publicConfidenceTooltip(eventItem) {
  const confidence = eventItem?.public_confidence
  if (!confidence) return ''
  if (confidence.level === 'unknown') return 'Nie su dostupne udaje o doveryhodnosti.'

  if (typeof confidence.score === 'number' && typeof confidence.sources_count === 'number') {
    return `${confidence.reason} Skore: ${confidence.score}/100 | Zdrojov: ${confidence.sources_count}`
  }

  return confidence.reason || 'Nie su dostupne udaje o doveryhodnosti.'
}

function normalizeText(value) {
  if (typeof value !== 'string') return ''
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim()
}

function isRedundantEventSummary(summary) {
  const normalized = normalizeText(summary)
  if (!normalized) return true

  return /^(priblizne|orientacne|okolo|cca)?\s*\d{1,2}\.\s*\d{1,2}\.\s*\d{2,4}(\s+\d{1,2}:\d{2})?$/.test(
    normalized,
  )
}

function eventCardSummary(eventItem) {
  const summary = eventDisplayShort(eventItem)
  if (!summary || summary === '-') return ''

  const normalizedSummary = normalizeText(summary)
  const normalizedTitle = normalizeText(eventDisplayTitle(eventItem))

  if (!normalizedSummary || normalizedSummary === normalizedTitle) return ''
  if (isRedundantEventSummary(summary)) return ''

  return summary
}

function formatCardDate(value) {
  return formatEventDate(value, EVENT_TIMEZONE, {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
  })
}

function eventCardTimeContext(eventItem) {
  return resolveEventTimeContext(eventItem, EVENT_TIMEZONE)
}

function eventCardTimeMessage(eventItem) {
  return eventCardTimeContext(eventItem).message
}

function eventCardTimeTimezoneLabel(eventItem) {
  const context = eventCardTimeContext(eventItem)
  return context.showTimezoneLabel ? context.timezoneLabelShort : ''
}

function eventCardTimeAriaLabel(eventItem) {
  const context = eventCardTimeContext(eventItem)
  if (!context.showTimezoneLabel) {
    return context.message
  }

  return `${context.message} (${context.timezoneLabelShort}), cas v ${context.timezoneLabelLong}`
}

function shouldShowRegion(region) {
  return Boolean(region && region !== 'global')
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

  startRealtimeFeed()
})

watch(isCalendarView, (calendarView) => {
  if (calendarView) {
    stopRealtimeFeed()
    return
  }
  startRealtimeFeed()
})

onBeforeUnmount(() => {
  stopRealtimeFeed()
  freshTimeouts.forEach((timeoutId) => window.clearTimeout(timeoutId))
  freshTimeouts.clear()
})
</script>

<style scoped>
.events-page {
  --events-bg: var(--color-bg-main);
  --events-surface: var(--color-bg-main);
  --events-text: var(--color-text-primary);
  --events-muted: var(--color-text-muted);
  --events-border: var(--color-border);
  --events-border-strong: var(--color-border-strong);
  --events-divider: var(--color-divider);
  --events-pill: rgb(255 255 255 / 0.04);
  --events-pill-hover: rgb(255 255 255 / 0.07);
  --events-pill-active: rgb(255 255 255 / 0.1);
  min-height: 100vh;
  width: 100%;
  background: var(--events-bg);
  color: var(--events-text);
}

.hero {
  border-bottom: 1px solid var(--events-divider);
}

.hero-inner {
  max-width: 36rem;
  padding: 0.4rem 0 1rem;
}

.hero-title {
  margin: 0;
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  line-height: 1.08;
  color: var(--events-text);
  text-wrap: balance;
}

.hero-subtitle {
  margin: 0.35rem 0 0;
  max-width: 30rem;
  font-size: 0.88rem;
  line-height: 1.45;
  color: var(--events-muted);
}

.content-wrap {
  width: 100%;
  padding-top: 1rem;
}

.filter-panel {
  display: grid;
  gap: 0.85rem;
}

.toolbar-row,
.toolbar-segments,
.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.toolbar-row {
  justify-content: space-between;
}

.segmented-control {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  padding: 0.22rem;
  border: 1px solid var(--events-border);
  border-radius: 999px;
  background: var(--events-pill);
}

.segment-btn,
.filter-btn,
.filter-toggle-btn,
.secondary-btn,
.pager-btn,
.filter-field input,
.filter-field select {
  border: 1px solid var(--events-border);
  border-radius: 0.8rem;
  background: var(--events-surface);
  color: var(--events-text);
}

.segment-btn {
  border-color: transparent;
  border-radius: 999px;
  background: transparent;
  padding: 0.42rem 0.78rem;
  font-size: 0.79rem;
  font-weight: 600;
  color: var(--events-muted);
  transition:
    background-color 140ms ease,
    color 140ms ease,
    border-color 140ms ease;
}

.segment-btn:hover,
.segment-btn:focus-visible {
  background: var(--events-pill-hover);
  color: var(--events-text);
}

.segment-btn.active {
  background: var(--events-pill-active);
  color: var(--events-text);
}

.filter-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.45rem 0.72rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--events-text);
}

.filter-toggle-btn svg {
  width: 0.9rem;
  height: 0.9rem;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}

.filters-content {
  display: grid;
  gap: 0.7rem;
  border: 1px solid var(--events-border);
  border-radius: 1rem;
  background: var(--events-surface);
  padding: 0.9rem;
  animation: fade-slide 150ms ease;
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.filter-btn {
  white-space: nowrap;
  padding: 0.4rem 0.7rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--events-muted);
}

.filter-btn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.22);
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: var(--events-text);
}

.advanced-filters {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.65rem;
  align-items: end;
}

.filter-field {
  display: grid;
  gap: 0.35rem;
}

.filter-field span {
  font-size: 0.72rem;
  color: var(--events-muted);
}

.filter-field input,
.filter-field select,
.secondary-btn {
  min-height: 2.5rem;
  padding: 0.55rem 0.72rem;
  font-size: 0.82rem;
}

.search-field {
  grid-column: span 2;
}

.secondary-btn,
.pager-btn {
  font-weight: 600;
}

.filter-meta,
.pager-meta {
  margin: 0;
  font-size: 0.74rem;
  color: var(--events-muted);
}

.state-card,
.pager {
  margin-top: 0.9rem;
  border-radius: 1rem;
  border: 1px solid var(--events-border);
  padding: 0.95rem 1rem;
  background: var(--events-surface);
}

.state-card {
  text-align: center;
}

.state-error {
  text-align: left;
}

.state-error :deep(.inlineStatus) {
  margin-top: 0;
}

.calendar-panel {
  margin-top: 0.95rem;
  width: 100%;
}

.events-grid {
  margin-top: 0.55rem;
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.85rem;
}

.event-card {
  text-decoration: none;
  border-radius: 1rem;
  border: 1px solid var(--events-border);
  background: var(--events-surface);
  transition:
    border-color 140ms ease,
    transform 140ms ease;
}

.event-card:hover {
  border-color: var(--events-border-strong);
  transform: translateY(-1px);
}

.event-card-new {
  animation: realtime-pulse 2.2s ease;
}

.card-content {
  display: grid;
  gap: 0.55rem;
  padding: 0.95rem 1rem;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.9rem;
}

.card-heading {
  min-width: 0;
  flex: 1;
}

.card-title {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.98rem;
  line-height: 1.25;
  color: var(--events-text);
}

.favorite-switch {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.7rem;
  min-width: 2.7rem;
  height: 1.65rem;
  padding: 0.12rem;
  border: 1px solid var(--events-border);
  border-radius: 999px;
  background: var(--events-surface);
  cursor: pointer;
  transition:
    border-color 140ms ease,
    background-color 140ms ease;
}

.favorite-switch.active {
  border-color: rgb(var(--color-primary-rgb) / 0.25);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.favorite-switch:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.favorite-switch-track {
  position: relative;
  display: inline-flex;
  width: 100%;
  height: 100%;
  padding: 0.1rem;
  border-radius: 999px;
  background: rgb(255 255 255 / 0.06);
}

.favorite-switch.active .favorite-switch-track {
  background: rgb(var(--color-primary-rgb) / 0.24);
}

.favorite-switch-thumb {
  width: 1.12rem;
  height: 1.12rem;
  border-radius: 999px;
  background: rgb(226 232 240);
  transition:
    transform 140ms ease,
    background-color 140ms ease;
}

.favorite-switch.active .favorite-switch-thumb {
  transform: translateX(1rem);
  background: var(--color-white);
}

.meta-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.type-badge,
.confidence-badge {
  display: inline-flex;
  align-items: center;
  min-height: 1.35rem;
  padding: 0 0.45rem;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 0.64rem;
  font-weight: 700;
  line-height: 1;
}

.type-badge {
  border-color: rgb(var(--color-primary-rgb) / 0.2);
  background: rgb(var(--color-primary-rgb) / 0.1);
  color: rgb(191 219 254 / 0.96);
}

.confidence-badge {
  color: var(--events-text);
}

.confidence-verified {
  border-color: rgb(74 222 128 / 0.2);
  background: rgb(74 222 128 / 0.12);
  color: rgb(220 252 231 / 0.96);
}

.confidence-partial {
  border-color: rgb(250 204 21 / 0.2);
  background: rgb(250 204 21 / 0.12);
  color: rgb(254 249 195 / 0.96);
}

.confidence-low {
  border-color: rgb(248 113 113 / 0.2);
  background: rgb(248 113 113 / 0.12);
  color: rgb(254 226 226 / 0.96);
}

.card-meta-text,
.card-meta-separator,
.card-description {
  font-size: 0.76rem;
  color: var(--events-muted);
}

.card-timezone-label {
  color: rgb(148 163 184 / 0.82);
}

.card-description {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: 1.4;
}

.card-footer {
  display: flex;
  justify-content: flex-end;
}

.open-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgb(var(--color-primary-rgb) / 0.86);
}

.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
}

.pager-actions {
  display: flex;
  gap: 0.5rem;
}

.pager-btn {
  min-height: 2.4rem;
  padding: 0.5rem 0.82rem;
  font-size: 0.78rem;
}

.pager-btn:disabled {
  opacity: 0.55;
}

.realtime-banner {
  display: flex;
  align-items: center;
  width: 100%;
  margin-top: 0.55rem;
  margin-bottom: 0.25rem;
  border-radius: 0.95rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.18);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

.realtime-banner-main,
.realtime-banner-dismiss {
  border: 0;
  background: transparent;
  color: var(--events-text);
}

.realtime-banner-main {
  flex: 1;
  padding: 0.65rem 0.8rem;
  text-align: left;
  font-size: 0.78rem;
  font-weight: 600;
}

.realtime-banner-dismiss {
  padding: 0.65rem 0.8rem;
  color: var(--events-muted);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@keyframes fade-slide {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes realtime-pulse {
  0% {
    border-color: rgb(var(--color-primary-rgb) / 0.42);
  }
  100% {
    border-color: var(--events-border);
  }
}

@media (min-width: 900px) {
  .events-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 960px) {
  .advanced-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .search-field {
    grid-column: 1 / -1;
  }
}

@media (max-width: 640px) {
  .content-wrap {
    padding-top: 0.8rem;
  }

  .hero-inner {
    padding: 0.15rem 0 0.85rem;
  }

  .toolbar-row {
    align-items: flex-start;
  }

  .toolbar-actions {
    width: 100%;
  }

  .advanced-filters {
    grid-template-columns: 1fr;
  }

  .search-field {
    grid-column: auto;
  }

  .pager {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
