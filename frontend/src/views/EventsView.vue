<template>
  <div class="events-page">
    <section class="hero">
      <div class="hero-noise" aria-hidden="true"></div>
      <div class="hero-orb hero-orb-a" aria-hidden="true"></div>
      <div class="hero-orb hero-orb-b" aria-hidden="true"></div>

      <div class="hero-inner">
        <p class="hero-kicker">Astronomy Feed</p>
        <h1 class="hero-title">Astronomicke udalosti</h1>
        <p class="hero-subtitle">Filtruj udalosti podla typu, regionu, textu a datumu.</p>
      </div>
    </section>

    <main class="content-wrap">
      <section class="filter-panel">
        <div class="view-toggle" role="tablist" aria-label="Zobrazenie udalosti">
          <button class="view-btn" :class="{ active: !isCalendarView }" type="button" @click="setView('list')">
            Zoznam
          </button>
          <button class="view-btn" :class="{ active: isCalendarView }" type="button" @click="setView('calendar')">
            Kalendar
          </button>
        </div>

        <template v-if="!isCalendarView">
          <div class="filters-toolbar">
            <button
              class="filter-toggle-btn"
              type="button"
              :aria-expanded="filtersOpen"
              @click="filtersOpen = !filtersOpen"
            >
              {{ filtersOpen ? 'Skryt filtre' : 'Zobrazit filtre' }}
            </button>
          </div>

          <div v-if="filtersOpen" class="filters-content">
            <div class="filter-row" role="tablist" aria-label="Event type filters">
              <button class="filter-btn" :class="{ active: selectedType === 'all' }" @click="selectedType = 'all'">Vsetky</button>
              <button class="filter-btn" :class="{ active: selectedType === 'meteors' }" @click="selectedType = 'meteors'">Meteoricke roje</button>
              <button class="filter-btn" :class="{ active: selectedType === 'eclipses' }" @click="selectedType = 'eclipses'">Zatmenia</button>
              <button class="filter-btn" :class="{ active: selectedType === 'conjunctions' }" @click="selectedType = 'conjunctions'">Konjunkcie</button>
              <button class="filter-btn" :class="{ active: selectedType === 'comets' }" @click="selectedType = 'comets'">Komety</button>
            </div>

            <div class="advanced-filters">
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

              <button class="secondary-btn" type="button" @click="resetFilters">Reset filtrov</button>
            </div>

            <div class="date-panel">
              <div class="date-presets">
                <button
                  v-for="preset in datePresets"
                  :key="preset.value"
                  class="date-preset-btn"
                  :class="{ active: datePreset === preset.value }"
                  type="button"
                  @click="setDatePreset(preset.value)"
                >
                  {{ preset.label }}
                </button>
              </div>

              <div class="date-custom">
                <label class="filter-field">
                  <span>Od</span>
                  <input v-model="dateFrom" type="date" @change="setDatePreset('custom')" />
                </label>

                <label class="filter-field">
                  <span>Do</span>
                  <input v-model="dateTo" type="date" @change="setDatePreset('custom')" />
                </label>
              </div>
            </div>
          </div>

          <p class="filter-meta">Zobrazenych udalosti: <strong>{{ events.length }}</strong></p>
        </template>
      </section>

      <section v-if="isCalendarView" class="calendar-panel">
        <CalendarView />
      </section>

      <div v-else-if="loading" class="state-card">
        <div class="spinner" aria-hidden="true"></div>
        <h3>Nacitavam udalosti</h3>
      </div>

      <div v-else-if="error" class="state-card state-error">
        <h3>Nastala chyba</h3>
        <p>{{ error }}</p>
      </div>

      <section v-else class="events-grid">
        <RouterLink v-for="e in events" :key="e.id" :to="`/events/${e.id}`" class="event-card">
          <div class="card-content">
            <div class="card-header">
              <div>
                <h3 class="card-title">{{ e.title }}</h3>
                <div class="meta-row">
                  <span class="type-badge">{{ typeLabel(e.type) }}</span>
                  <span class="card-date">{{ formatDateTime(e.max_at) }}</span>
                </div>
              </div>

              <button
                class="favorite-btn"
                type="button"
                :disabled="favorites.loading || !auth.isAuthed"
                :aria-pressed="favorites.isFavorite(e.id)"
                @click.prevent.stop="toggleFavorite(e.id)"
              >
                {{ favorites.isFavorite(e.id) ? 'ON' : 'OFF' }}
              </button>
            </div>

            <p class="card-description">{{ e.short || '-' }}</p>

            <div class="card-footer">
              <span>Region: {{ regionLabel(e.region_scope) }}</span>
              <span class="open-label">Zobrazit detail</span>
            </div>
          </div>
        </RouterLink>
      </section>

      <div v-if="!isCalendarView && !loading && !error && events.length === 0" class="state-card state-empty">
        <h3>Ziadne udalosti</h3>
        <p>Skus upravit filtre a rozsirit vyhladavanie.</p>
        <button class="secondary-btn" type="button" @click="resetFilters">Vymazat filtre</button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import CalendarView from './CalendarView.vue'
import { useFavoritesStore } from '@/stores/favorites'
import { useAuthStore } from '@/stores/auth'
import { getEvents } from '@/services/events'

const route = useRoute()
const router = useRouter()

const favorites = useFavoritesStore()
const auth = useAuthStore()

const selectedType = ref('all')
const selectedRegion = ref('all')
const searchQuery = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const datePreset = ref('next_30_days')
const filtersOpen = ref(false)

const events = ref([])
const loading = ref(false)
const error = ref('')

const isCalendarView = computed(() => route.query?.view === 'calendar')

const allFeedTypeGroups = {
  meteors: ['meteors', 'meteor_shower'],
  eclipses: ['eclipse', 'eclipse_lunar', 'eclipse_solar'],
  conjunctions: ['conjunction', 'planetary_event'],
  comets: ['comet', 'asteroid', 'other'],
}

const datePresets = [
  { value: 'any', label: 'Kedykolvek' },
  { value: 'today', label: 'Dnes' },
  { value: 'next_7_days', label: 'Najblizsich 7 dni' },
  { value: 'next_30_days', label: 'Najblizsich 30 dni' },
  { value: 'this_month', label: 'Tento mesiac' },
  { value: 'custom', label: 'Vlastny rozsah' },
]

function toIsoDate(dateLike) {
  const date = new Date(dateLike)
  if (Number.isNaN(date.getTime())) return ''
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function todayIso() {
  return toIsoDate(new Date())
}

function setDatePreset(preset) {
  datePreset.value = preset

  if (preset === 'custom') {
    return
  }

  if (preset === 'any') {
    dateFrom.value = ''
    dateTo.value = ''
    return
  }

  const now = new Date()
  const start = new Date(now)
  const end = new Date(now)

  if (preset === 'today') {
    dateFrom.value = toIsoDate(start)
    dateTo.value = toIsoDate(end)
    return
  }

  if (preset === 'next_7_days') {
    end.setDate(end.getDate() + 7)
    dateFrom.value = toIsoDate(start)
    dateTo.value = toIsoDate(end)
    return
  }

  if (preset === 'next_30_days') {
    end.setDate(end.getDate() + 30)
    dateFrom.value = toIsoDate(start)
    dateTo.value = toIsoDate(end)
    return
  }

  if (preset === 'this_month') {
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1)
    const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    dateFrom.value = toIsoDate(monthStart)
    dateTo.value = toIsoDate(monthEnd)
  }
}

function ensureDateRangeOrder() {
  if (!dateFrom.value || !dateTo.value) return
  if (dateFrom.value <= dateTo.value) return

  const from = dateFrom.value
  dateFrom.value = dateTo.value
  dateTo.value = from
}

function buildParams() {
  const params = { feed: 'all' }

  if (selectedType.value !== 'all') {
    params.types = allFeedTypeGroups[selectedType.value] || []
  }

  if (selectedRegion.value !== 'all') {
    params.region = selectedRegion.value
  }

  if (searchQuery.value) {
    params.q = searchQuery.value
  }

  ensureDateRangeOrder()

  if (dateFrom.value && dateTo.value) {
    params.from = dateFrom.value
    params.to = dateTo.value
  }

  return params
}

async function fetchEvents() {
  if (isCalendarView.value) return

  loading.value = true
  error.value = ''

  try {
    const response = await getEvents(buildParams())
    events.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (err) {
    error.value = err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa nacitat udalosti.'
  } finally {
    loading.value = false
  }
}

function setView(view) {
  const nextQuery = { ...route.query }

  if (view === 'calendar') {
    nextQuery.view = 'calendar'
  } else {
    delete nextQuery.view
  }

  router.replace({ name: 'events', query: nextQuery })
}

function resetFilters() {
  selectedType.value = 'all'
  selectedRegion.value = 'all'
  searchQuery.value = ''
  setDatePreset('next_30_days')
}

async function toggleFavorite(eventId) {
  await favorites.toggle(eventId)
}

function regionLabel(region) {
  const map = {
    sk: 'Slovensko',
    eu: 'Europa',
    global: 'Globalne',
  }

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

function formatDateTime(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

watch(
  [isCalendarView, selectedType, selectedRegion, searchQuery, dateFrom, dateTo],
  async () => {
    if (!isCalendarView.value) {
      await fetchEvents()
    }
  },
  { immediate: true },
)

onMounted(async () => {
  if (!dateFrom.value && !dateTo.value) {
    setDatePreset('next_30_days')
  } else if (dateFrom.value && dateTo.value && dateFrom.value === todayIso()) {
    datePreset.value = 'today'
  }

  if (!isCalendarView.value && auth.isAuthed && favorites.ids.size === 0 && !favorites.loading) {
    await favorites.fetch()
  }
})
</script>

<style scoped>
.events-page {
  min-height: 100vh;
  width: 100%;
}

.hero {
  position: relative;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
  border-radius: 0.95rem;
  background:
    radial-gradient(circle at 12% 8%, rgb(56 189 248 / 0.12), transparent 32%),
    radial-gradient(circle at 85% 20%, rgb(244 63 94 / 0.12), transparent 30%),
    linear-gradient(155deg, rgb(var(--color-bg-rgb) / 0.9), rgb(var(--color-bg-rgb) / 0.62));
}

.hero-noise {
  position: absolute;
  inset: 0;
  opacity: 0.2;
  background-image: radial-gradient(rgb(255 255 255 / 0.24) 1px, transparent 1px);
  background-size: 22px 22px;
}

.hero-orb {
  position: absolute;
  border-radius: 999px;
  filter: blur(34px);
}

.hero-orb-a {
  width: 180px;
  height: 180px;
  top: -30px;
  right: -24px;
  background: rgb(59 130 246 / 0.26);
}

.hero-orb-b {
  width: 140px;
  height: 140px;
  left: 18%;
  bottom: -40px;
  background: rgb(236 72 153 / 0.22);
}

.hero-inner {
  position: relative;
  max-width: 820px;
  margin: 0 auto;
  padding: 1.6rem 1rem 1.3rem;
  text-align: center;
}

.hero-kicker {
  margin: 0;
  font-size: 0.73rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-weight: 700;
}

.hero-title {
  margin: 0.45rem 0 0;
  font-size: clamp(1.5rem, 4vw, 2.4rem);
  line-height: 1.05;
  color: var(--color-surface);
  text-wrap: balance;
}

.hero-subtitle {
  margin: 0.55rem auto 0;
  max-width: 620px;
  font-size: 0.86rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.content-wrap {
  width: 100%;
  padding: 0.85rem 0.1rem 0;
}

.filter-panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  border-radius: 1rem;
  padding: 0.72rem;
  background: linear-gradient(155deg, rgb(var(--color-bg-rgb) / 0.8), rgb(var(--color-bg-rgb) / 0.6));
  box-shadow: 0 14px 36px rgb(2 6 23 / 0.18);
}

.view-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.5rem;
  padding: 0.22rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
}

.view-btn {
  border: 1px solid transparent;
  border-radius: 999px;
  padding: 0.3rem 0.64rem;
  font-size: 0.74rem;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  background: transparent;
}

.view-btn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.55rem;
}

.filters-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0.45rem;
}

.filter-toggle-btn {
  border-radius: 0.65rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background: rgb(var(--color-bg-rgb) / 0.72);
  color: var(--color-surface);
  padding: 0.34rem 0.56rem;
  font-size: 0.76rem;
  font-weight: 600;
}

.filters-content {
  animation: fade-slide 150ms ease;
}

.filter-btn {
  white-space: nowrap;
  padding: 0.36rem 0.58rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.66);
  color: var(--color-surface);
  font-size: 0.76rem;
  font-weight: 600;
}

.filter-btn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  background: linear-gradient(145deg, rgb(var(--color-primary-rgb) / 0.28), rgb(var(--color-bg-rgb) / 0.72));
}

.advanced-filters {
  display: grid;
  grid-template-columns: minmax(220px, 1.6fr) minmax(130px, 1fr) auto;
  gap: 0.45rem;
  align-items: end;
}

.date-panel {
  margin-top: 0.5rem;
  padding: 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  border-radius: 0.75rem;
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.date-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.date-preset-btn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.66);
  color: var(--color-surface);
  border-radius: 999px;
  font-size: 0.72rem;
  padding: 0.28rem 0.52rem;
}

.date-preset-btn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  background: linear-gradient(145deg, rgb(var(--color-primary-rgb) / 0.28), rgb(var(--color-bg-rgb) / 0.72));
}

.date-custom {
  margin-top: 0.45rem;
  display: grid;
  grid-template-columns: repeat(2, minmax(130px, 1fr));
  gap: 0.45rem;
}

.filter-field {
  display: grid;
  gap: 0.3rem;
}

.filter-field span {
  font-size: 0.66rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.filter-field input,
.filter-field select,
.secondary-btn {
  border-radius: 0.65rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background: rgb(var(--color-bg-rgb) / 0.72);
  color: var(--color-surface);
  padding: 0.38rem 0.52rem;
  font-size: 0.78rem;
}

.filter-meta {
  margin: 0.48rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 0.78rem;
}

.state-card {
  margin-top: 0.7rem;
  border-radius: 0.8rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  padding: 0.85rem;
  background: rgb(var(--color-bg-rgb) / 0.66);
  text-align: center;
}

.state-error {
  border-color: rgb(251 113 133 / 0.45);
  background: rgb(190 24 93 / 0.12);
}

.spinner {
  width: 1.5rem;
  height: 1.5rem;
  margin: 0 auto 0.4rem;
  border-radius: 999px;
  border: 2px solid rgb(var(--color-primary-rgb) / 0.25);
  border-top-color: rgb(var(--color-primary-rgb) / 0.95);
  animation: spin 1s linear infinite;
}

.events-grid {
  margin-top: 0.65rem;
  display: grid;
  gap: 0.55rem;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
}

.calendar-panel {
  margin-top: 0.7rem;
  width: 100%;
}

.event-card {
  border-radius: 0.8rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: linear-gradient(170deg, rgb(var(--color-bg-rgb) / 0.82), rgb(var(--color-bg-rgb) / 0.62));
  text-decoration: none;
}

.card-content {
  padding: 0.72rem;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}

.card-title {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.2;
  color: var(--color-surface);
}

.meta-row {
  margin-top: 0.3rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
  flex-wrap: wrap;
}

.type-badge {
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.38);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: rgb(191 219 254);
  font-size: 0.62rem;
  font-weight: 700;
  padding: 0.12rem 0.36rem;
}

.card-date,
.card-description,
.card-footer {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.72rem;
}

.favorite-btn {
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.84);
  color: var(--color-surface);
  font-size: 0.58rem;
  padding: 0.28rem 0.38rem;
}

.card-description {
  margin: 0.48rem 0 0;
  line-height: 1.35;
}

.card-footer {
  margin-top: 0.6rem;
  padding-top: 0.42rem;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.open-label {
  color: rgb(var(--color-primary-rgb) / 0.9);
  font-weight: 700;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
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

@media (max-width: 900px) {
  .advanced-filters {
    grid-template-columns: repeat(2, minmax(140px, 1fr));
  }

  .search-field {
    grid-column: 1 / -1;
  }

  .date-custom {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .events-grid {
    grid-template-columns: 1fr;
  }

  .advanced-filters {
    grid-template-columns: 1fr;
  }

  .hero-inner {
    padding: 1.2rem 0.8rem 1rem;
  }

  .content-wrap {
    padding-top: 0.65rem;
  }
}
</style>
