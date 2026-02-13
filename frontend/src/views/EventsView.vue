<template>
  <div class="events-page">
    <section class="hero">
      <div class="hero-noise" aria-hidden="true"></div>
      <div class="hero-orb hero-orb-a" aria-hidden="true"></div>
      <div class="hero-orb hero-orb-b" aria-hidden="true"></div>

      <div class="hero-inner">
        <p class="hero-kicker">Astronomy Feed</p>
        <h1 class="hero-title">Astronomicke udalosti</h1>
        <p class="hero-subtitle">Prepni medzi globalnym feedom a personalizovanym feedom "Pre mna".</p>
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
          <div class="feed-toggle" role="tablist" aria-label="Typ feedu">
            <button class="feed-btn" :class="{ active: feedMode === 'all' }" type="button" @click="setFeed('all')">
              Vsetko
            </button>
            <button
              class="feed-btn"
              :class="{ active: feedMode === 'mine' }"
              :disabled="!auth.isAuthed"
              type="button"
              @click="setFeed('mine')"
            >
              Pre mna
            </button>
          </div>

          <div v-if="!auth.isAuthed" class="mine-cta-banner">
            <p>Prihlas sa pre personalizovany feed udalosti.</p>
            <RouterLink class="cta-link" :to="loginLink">Prihlasit sa</RouterLink>
          </div>

          <div v-if="auth.isAuthed" class="preferences-header">
            <button class="pref-toggle-btn" type="button" @click="preferencesOpen = !preferencesOpen">
              {{ preferencesOpen ? 'Skryt moje preferencie' : 'Moje preferencie' }}
            </button>
          </div>

          <div v-if="auth.isAuthed && preferencesOpen" class="preferences-panel">
            <p class="panel-title">Typy udalosti</p>
            <div class="chip-grid">
              <button
                v-for="type in availablePreferenceTypes"
                :key="type"
                type="button"
                class="chip"
                :class="{ active: draft.event_types.includes(type) }"
                @click="toggleDraftType(type)"
              >
                {{ typeLabel(type) }}
              </button>
            </div>

            <label class="region-row">
              <span>Region</span>
              <select v-model="draft.region">
                <option v-for="region in availableRegions" :key="region" :value="region">{{ regionLabel(region) }}</option>
              </select>
            </label>

            <div class="pref-actions">
              <button class="save-btn" type="button" :disabled="preferences.saving" @click="savePreferences">
                {{ preferences.saving ? 'Ukladam...' : 'Ulozit' }}
              </button>
              <button class="secondary-btn" type="button" :disabled="preferences.loading" @click="applyAllTypesPreset">
                Vybrat vsetky typy
              </button>
            </div>
          </div>

          <div v-if="feedMode === 'all'" class="filter-row" role="tablist" aria-label="Event type filters">
            <button class="filter-btn" :class="{ active: selectedType === 'all' }" @click="selectedType = 'all'">Vsetky</button>
            <button class="filter-btn" :class="{ active: selectedType === 'meteors' }" @click="selectedType = 'meteors'">Meteoricke roje</button>
            <button class="filter-btn" :class="{ active: selectedType === 'eclipses' }" @click="selectedType = 'eclipses'">Zatmenia</button>
            <button class="filter-btn" :class="{ active: selectedType === 'conjunctions' }" @click="selectedType = 'conjunctions'">Konjunkcie</button>
            <button class="filter-btn" :class="{ active: selectedType === 'comets' }" @click="selectedType = 'comets'">Komety</button>
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

      <div v-else-if="mineWithoutPreferences" class="state-card state-empty">
        <h3>Nastav si preferencie</h3>
        <p>Aby feed "Pre mna" fungoval, vyber typy udalosti a region.</p>
        <button class="save-btn" type="button" @click="openPreferences">Otvorit preferencie</button>
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

      <div v-if="!isCalendarView && !loading && !error && !mineWithoutPreferences && events.length === 0" class="state-card state-empty">
        <h3>{{ feedMode === 'mine' ? 'Ziadne udalosti pre tvoje filtre' : 'Ziadne udalosti' }}</h3>
        <p v-if="feedMode === 'mine'">Skus zmenit region alebo pridat dalsie typy udalosti.</p>
        <p v-else>V tejto kategorii sa nenasli ziadne udalosti.</p>
        <button v-if="feedMode === 'mine'" class="secondary-btn" type="button" @click="openPreferences">Zmenit preferencie</button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import CalendarView from './CalendarView.vue'
import { useFavoritesStore } from '@/stores/favorites'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import { useToast } from '@/composables/useToast'
import { getEvents } from '@/services/events'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const favorites = useFavoritesStore()
const auth = useAuthStore()
const preferences = useEventPreferencesStore()

const selectedType = ref('all')
const events = ref([])
const loading = ref(false)
const error = ref('')
const preferencesOpen = ref(false)

const draft = reactive({
  event_types: [],
  region: 'global',
})

const isCalendarView = computed(() => route.query?.view === 'calendar')
const feedMode = computed(() => (route.query?.feed === 'mine' ? 'mine' : 'all'))
const loginLink = computed(() => `/login?redirect=${encodeURIComponent('/events?feed=mine')}`)

const mineWithoutPreferences = computed(() => {
  return feedMode.value === 'mine' && auth.isAuthed && preferences.loaded && !preferences.hasPreferences
})

const availablePreferenceTypes = computed(() => {
  if (preferences.supportedEventTypes.length > 0) {
    return preferences.supportedEventTypes
  }

  return ['meteors', 'meteor_shower', 'eclipse', 'eclipse_lunar', 'eclipse_solar', 'conjunction', 'comet', 'other']
})

const availableRegions = computed(() => {
  return preferences.supportedRegions.length > 0 ? preferences.supportedRegions : ['sk', 'eu', 'global']
})

const allFeedTypeGroups = {
  meteors: ['meteors', 'meteor_shower'],
  eclipses: ['eclipse', 'eclipse_lunar', 'eclipse_solar'],
  conjunctions: ['conjunction', 'planetary_event'],
  comets: ['comet', 'other'],
}

function syncDraftFromStore() {
  draft.event_types = [...preferences.eventTypes]
  draft.region = preferences.region || 'global'
}

async function fetchEvents() {
  if (isCalendarView.value) return

  loading.value = true
  error.value = ''

  try {
    const params = {
      feed: feedMode.value,
    }

    if (feedMode.value === 'all' && selectedType.value !== 'all') {
      params.types = allFeedTypeGroups[selectedType.value] || []
    }

    const response = await getEvents(params)
    events.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (err) {
    if (err?.response?.status === 401 && feedMode.value === 'mine') {
      error.value = 'Prihlas sa, aby si videl personalizovany feed.'
      return
    }

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

function setFeed(mode) {
  if (mode === 'mine' && !auth.isAuthed) return

  const nextQuery = { ...route.query }
  nextQuery.feed = mode

  router.replace({ name: 'events', query: nextQuery })
}

function toggleDraftType(type) {
  if (draft.event_types.includes(type)) {
    draft.event_types = draft.event_types.filter((item) => item !== type)
    return
  }

  draft.event_types = [...draft.event_types, type]
}

function openPreferences() {
  preferencesOpen.value = true
}

function applyAllTypesPreset() {
  draft.event_types = [...availablePreferenceTypes.value]
}

async function savePreferences() {
  try {
    await preferences.save({
      event_types: draft.event_types,
      region: draft.region,
    })

    syncDraftFromStore()
    toast.success('Preferencie boli ulozene.')

    if (feedMode.value === 'mine') {
      await fetchEvents()
    }
  } catch {
    toast.error(preferences.error || 'Nepodarilo sa ulozit preferencie.')
  }
}

async function ensureMineContext() {
  if (!auth.isAuthed) {
    if (feedMode.value === 'mine') {
      setFeed('all')
    }
    events.value = []
    return
  }

  try {
    await preferences.fetch()
    syncDraftFromStore()

    if (!preferences.hasPreferences) {
      events.value = []
      return
    }

    await fetchEvents()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Nepodarilo sa nacitat personalizovany feed.'
  }
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
  [isCalendarView, feedMode, selectedType, () => auth.isAuthed],
  async () => {
    if (isCalendarView.value) return

    if (feedMode.value === 'mine') {
      await ensureMineContext()
      return
    }

    await fetchEvents()
  },
  { immediate: true },
)

onMounted(async () => {
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
  border-radius: 1.2rem;
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
  padding: 2.6rem 1.2rem 2.2rem;
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
  margin: 0.75rem 0 0;
  font-size: clamp(1.9rem, 5vw, 3.4rem);
  line-height: 1.05;
  color: var(--color-surface);
  text-wrap: balance;
}

.hero-subtitle {
  margin: 0.9rem auto 0;
  max-width: 620px;
  font-size: 0.98rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.content-wrap {
  width: 100%;
  padding: 1.35rem 0.2rem 0;
}

.filter-panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  border-radius: 1rem;
  padding: 0.95rem;
  background: linear-gradient(155deg, rgb(var(--color-bg-rgb) / 0.8), rgb(var(--color-bg-rgb) / 0.6));
  box-shadow: 0 14px 36px rgb(2 6 23 / 0.18);
}

.view-toggle,
.feed-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  margin-bottom: 0.7rem;
  padding: 0.28rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
}

.view-btn,
.feed-btn {
  border: 1px solid transparent;
  border-radius: 999px;
  padding: 0.36rem 0.78rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  background: transparent;
}

.view-btn.active,
.feed-btn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.feed-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.mine-cta-banner {
  margin-bottom: 0.8rem;
  border: 1px dashed rgb(var(--color-primary-rgb) / 0.6);
  border-radius: 0.8rem;
  padding: 0.7rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
}

.mine-cta-banner p {
  margin: 0;
  font-size: 0.85rem;
}

.cta-link {
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  color: var(--color-surface);
  text-decoration: none;
}

.preferences-header {
  margin-bottom: 0.7rem;
}

.pref-toggle-btn,
.save-btn,
.secondary-btn {
  border-radius: 0.75rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: var(--color-surface);
  padding: 0.45rem 0.7rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.preferences-panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  border-radius: 0.9rem;
  padding: 0.8rem;
  margin-bottom: 0.8rem;
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.panel-title {
  margin: 0 0 0.45rem;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.chip-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.chip {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.75);
  color: var(--color-surface);
  padding: 0.25rem 0.55rem;
  font-size: 0.74rem;
}

.chip.active {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  background: rgb(var(--color-primary-rgb) / 0.22);
}

.region-row {
  margin-top: 0.7rem;
  display: flex;
  gap: 0.6rem;
  align-items: center;
  font-size: 0.84rem;
}

.region-row select {
  border-radius: 0.6rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background: rgb(var(--color-bg-rgb) / 0.8);
  color: var(--color-surface);
  padding: 0.36rem 0.5rem;
}

.pref-actions {
  margin-top: 0.75rem;
  display: flex;
  gap: 0.5rem;
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.filter-btn {
  white-space: nowrap;
  padding: 0.48rem 0.72rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.66);
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 600;
}

.filter-btn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  background: linear-gradient(145deg, rgb(var(--color-primary-rgb) / 0.28), rgb(var(--color-bg-rgb) / 0.72));
}

.filter-meta {
  margin: 0.72rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 0.84rem;
}

.state-card {
  margin-top: 1rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  padding: 1.1rem;
  background: rgb(var(--color-bg-rgb) / 0.66);
  text-align: center;
}

.state-error {
  border-color: rgb(251 113 133 / 0.45);
  background: rgb(190 24 93 / 0.12);
}

.spinner {
  width: 2rem;
  height: 2rem;
  margin: 0 auto 0.6rem;
  border-radius: 999px;
  border: 3px solid rgb(var(--color-primary-rgb) / 0.25);
  border-top-color: rgb(var(--color-primary-rgb) / 0.95);
  animation: spin 1s linear infinite;
}

.events-grid {
  margin-top: 1rem;
  display: grid;
  gap: 0.85rem;
  grid-template-columns: repeat(auto-fill, minmax(255px, 1fr));
}

.calendar-panel {
  margin-top: 1rem;
  width: 100%;
}

.event-card {
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: linear-gradient(170deg, rgb(var(--color-bg-rgb) / 0.82), rgb(var(--color-bg-rgb) / 0.62));
  text-decoration: none;
}

.card-content {
  padding: 0.95rem;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.7rem;
}

.card-title {
  margin: 0;
  font-size: 1.02rem;
  line-height: 1.24;
  color: var(--color-surface);
}

.meta-row {
  margin-top: 0.45rem;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.type-badge {
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.38);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: rgb(191 219 254);
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
}

.card-date,
.card-description,
.card-footer {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.78rem;
}

.favorite-btn {
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.84);
  color: var(--color-surface);
  font-size: 0.64rem;
  padding: 0.4rem 0.5rem;
}

.card-description {
  margin: 0.72rem 0 0;
  line-height: 1.45;
}

.card-footer {
  margin-top: 0.9rem;
  padding-top: 0.6rem;
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

@media (max-width: 640px) {
  .events-grid {
    grid-template-columns: 1fr;
  }

  .mine-cta-banner {
    flex-direction: column;
    align-items: flex-start;
  }

  .pref-actions {
    flex-direction: column;
  }
}
</style>
