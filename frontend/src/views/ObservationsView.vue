<template>
  <section class="observations-page">
    <header class="observations-header">
      <div>
        <h1>Pozorovania</h1>
        <p>Zaznamenane pozorovania s fotografiami a metadatami.</p>
      </div>
      <RouterLink class="create-link" to="/observations/new">
        Pridat pozorovanie
      </RouterLink>
    </header>

    <div v-if="auth.isAuthed" class="filters-card">
      <button
        type="button"
        class="toggle-filter"
        :class="{ active: filters.mine }"
        @click="toggleMine"
      >
        {{ filters.mine ? 'Moje' : 'Vsetky' }}
      </button>

      <label class="filter-field">
        <span>Udalost</span>
        <select v-model="filters.eventId" :disabled="eventsLoading" @change="applyFilters">
          <option value="">Vsetky udalosti</option>
          <option v-for="eventItem in events" :key="eventItem.id" :value="String(eventItem.id)">
            {{ eventItem.title }}
          </option>
        </select>
      </label>

      <label class="filter-field">
        <span>Zoradenie</span>
        <select v-model="filters.sort" @change="applyFilters">
          <option value="newest">Najnovsie</option>
          <option value="oldest">Najstarsie</option>
        </select>
      </label>
    </div>

    <div v-if="!auth.isAuthed" class="state-card">
      Prihlas sa pre zobrazenie pozorovani.
    </div>

    <div v-else-if="isInitialLoading" class="state-card" data-testid="observations-loading">
      Nacitavam pozorovania...
    </div>

    <div v-else-if="error" class="state-card state-error" data-testid="observations-error">
      <p>{{ error }}</p>
      <button type="button" class="retry-btn" @click="retryLoad">
        Skusit znova
      </button>
    </div>

    <div v-else-if="items.length === 0" class="state-card" data-testid="observations-empty">
      {{ emptyMessage }}
    </div>

    <div v-else class="observations-list">
      <ObservationCard
        v-for="item in items"
        :key="item.id"
        :observation="item"
        :clickable="true"
        @open="openDetail"
      />
    </div>

    <div v-if="auth.isAuthed && nextPage" class="load-more">
      <button type="button" class="load-more-btn" :disabled="loading" @click="load(false)">
        {{ isPaginating ? 'Nacitavam...' : 'Nacitat viac' }}
      </button>
    </div>

    <p v-if="isPaginating" class="paging-status">
      Nacitavam dalsiu stranu...
    </p>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getEvents } from '@/services/events'
import { listObservations } from '@/services/observations'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import { extractObservationError } from '@/utils/observationErrors'

const router = useRouter()
const auth = useAuthStore()

const items = ref([])
const loading = ref(false)
const error = ref('')
const nextPage = ref(null)
const refreshQueued = ref(false)
const events = ref([])
const eventsLoading = ref(false)
const filters = reactive({
  mine: true,
  eventId: '',
  sort: 'newest',
})

const isInitialLoading = computed(() => loading.value && items.value.length === 0)
const isPaginating = computed(() => loading.value && items.value.length > 0)
const emptyMessage = computed(() => {
  if (!filters.mine) return 'Zatial nie su dostupne ziadne pozorovania.'
  if (filters.eventId) return 'Pre tuto udalost zatial nemas ziadne pozorovania.'
  return 'Zatial nemas ziadne pozorovania.'
})

function mergeUniqueById(existingItems, incomingItems) {
  const seen = new Set()
  const merged = []

  const append = (item) => {
    const id = Number(item?.id || 0)
    if (!Number.isInteger(id) || id <= 0) {
      merged.push(item)
      return
    }

    if (seen.has(id)) return
    seen.add(id)
    merged.push(item)
  }

  ;(Array.isArray(existingItems) ? existingItems : []).forEach(append)
  ;(Array.isArray(incomingItems) ? incomingItems : []).forEach(append)

  return merged
}

async function loadEvents() {
  eventsLoading.value = true
  try {
    const response = await getEvents({ per_page: 50 })
    const rows = Array.isArray(response?.data?.data) ? response.data.data : []
    events.value = rows
  } catch {
    events.value = []
  } finally {
    eventsLoading.value = false
  }
}

async function load(reset = true) {
  if (!auth.isAuthed || loading.value) return

  loading.value = true
  error.value = ''

  try {
    const page = reset ? 1 : nextPage.value
    if (!page) return

    const response = await listObservations({
      mine: filters.mine ? 1 : 0,
      event_id: filters.eventId,
      sort: filters.sort,
      page,
      per_page: 12,
    })

    const payload = response?.data || {}
    const rows = Array.isArray(payload?.data) ? payload.data : []

    items.value = reset
      ? mergeUniqueById([], rows)
      : mergeUniqueById(items.value, rows)

    const currentPage = Number(payload?.current_page || page)
    const lastPage = Number(payload?.last_page || currentPage)
    nextPage.value = currentPage < lastPage ? currentPage + 1 : null
  } catch (requestError) {
    error.value = extractObservationError(requestError, 'Nacitavanie zlyhalo.')
  } finally {
    loading.value = false

    if (refreshQueued.value) {
      refreshQueued.value = false
      nextPage.value = null
      void load(true)
    }
  }
}

function applyFilters() {
  if (loading.value) {
    refreshQueued.value = true
    return
  }

  nextPage.value = null
  void load(true)
}

function toggleMine() {
  filters.mine = !filters.mine
  applyFilters()
}

function retryLoad() {
  void load(true)
}

function openDetail(observation) {
  const id = Number(observation?.id || 0)
  if (!Number.isInteger(id) || id <= 0) return
  router.push(`/observations/${id}`)
}

onMounted(() => {
  void loadEvents()
  void load(true)
})
</script>

<style scoped>
.observations-page {
  max-width: 860px;
  margin: 0 auto;
  padding: 1rem;
  display: grid;
  gap: 0.9rem;
}

.observations-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.8rem;
}

.observations-header h1 {
  margin: 0;
  color: var(--color-surface);
  font-size: 1.45rem;
}

.observations-header p {
  margin: 0.3rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.92rem;
}

.create-link {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  border-radius: 999px;
  padding: 0.45rem 0.82rem;
  color: var(--color-primary);
  text-decoration: none;
  background: rgb(var(--color-primary-rgb) / 0.14);
  white-space: nowrap;
}

.create-link:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.85);
}

.filters-card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  border-radius: 0.9rem;
  padding: 0.8rem;
  display: grid;
  gap: 0.6rem;
  background: rgb(var(--color-bg-rgb) / 0.35);
  grid-template-columns: auto minmax(0, 1fr) minmax(0, 1fr);
}

.toggle-filter {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 999px;
  background: transparent;
  color: var(--color-surface);
  padding: 0.45rem 0.85rem;
  font-weight: 700;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.toggle-filter.active {
  border-color: rgb(var(--color-primary-rgb) / 0.75);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-primary);
}

.filter-field {
  display: grid;
  gap: 0.24rem;
  font-size: 0.78rem;
  color: var(--color-text-secondary);
}

.filter-field select {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.34);
  border-radius: 0.65rem;
  background: rgb(var(--color-bg-rgb) / 0.48);
  color: var(--color-surface);
  padding: 0.46rem 0.54rem;
}

.state-card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  border-radius: 0.9rem;
  padding: 0.9rem;
  color: var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.35);
}

.state-error {
  color: rgb(var(--color-danger-rgb) / 0.95);
}

.retry-btn {
  margin-top: 0.6rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 999px;
  padding: 0.3rem 0.72rem;
  background: transparent;
  color: var(--color-surface);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.observations-list {
  display: grid;
  gap: 0.7rem;
}

.load-more {
  display: flex;
  justify-content: center;
}

.load-more-btn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 999px;
  padding: 0.38rem 0.86rem;
  background: transparent;
  color: var(--color-surface);
}

.paging-status {
  text-align: center;
  margin: 0;
  font-size: 0.8rem;
  color: var(--color-text-secondary);
}

@media (max-width: 720px) {
  .observations-header {
    flex-direction: column;
    align-items: stretch;
  }

  .create-link {
    width: fit-content;
  }

  .filters-card {
    grid-template-columns: 1fr;
  }
}
</style>
