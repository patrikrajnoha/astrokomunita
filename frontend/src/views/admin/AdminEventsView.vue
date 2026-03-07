<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import api from '@/services/api'

const router = useRouter()

const loading = ref(false)
const error = ref('')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const res = await api.get('/admin/events', {
      params: { page: page.value, per_page: perPage.value },
    })
    data.value = res.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa nacitat udalosti.'
  } finally {
    loading.value = false
  }
}

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (isNaN(d.getTime())) return String(value)
  return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function openCreate() {
  router.push('/admin/events/create')
}

function openEdit(id) {
  router.push(`/admin/events/${id}/edit`)
}

function prevPage() {
  if (!data.value || page.value <= 1) return
  page.value -= 1
  load()
}

function nextPage() {
  if (!data.value || page.value >= data.value.last_page) return
  page.value += 1
  load()
}

onMounted(load)
</script>

<template>
  <section class="adminEvents">
    <header class="adminEvents__header">
      <div>
        <h1 class="adminEvents__title">Udalosti</h1>
        <p class="adminEvents__subtitle">Prehlad udalosti a rychly pristup k uprave.</p>
      </div>
      <button
        type="button"
        class="ui-btn ui-btn--secondary"
        :disabled="loading"
        @click="openCreate"
      >
        Vytvorit udalost
      </button>
    </header>

    <InlineStatus
      v-if="error"
      variant="error"
      :message="error"
      action-label="Skusit znova"
      @action="load"
    />

    <AsyncState
      v-else-if="loading"
      mode="loading"
      title="Nacitavam udalosti"
      loading-style="skeleton"
      :skeleton-rows="5"
      compact
    />

    <div v-else class="adminEvents__tableWrap">
      <table class="adminEvents__table">
        <thead>
          <tr>
            <th>Nazov</th>
            <th>Typ</th>
            <th>Zaciatok</th>
            <th>Viditelnost</th>
            <th class="is-right">Akcia</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ev in data?.data || []" :key="ev.id" class="adminEvents__row">
            <td>{{ ev.title }}</td>
            <td>{{ ev.type || '-' }}</td>
            <td>{{ formatDate(ev.start_at || ev.starts_at || ev.max_at) }}</td>
            <td>{{ ev.visibility === 1 ? 'public' : 'hidden' }}</td>
            <td class="is-right">
              <button type="button" class="ui-btn ui-btn--secondary" @click="openEdit(ev.id)">
                Upravit
              </button>
            </td>
          </tr>
          <tr v-if="!(data?.data?.length)">
            <td colspan="5" class="adminEvents__emptyCell">
              <AsyncState
                mode="empty"
                title="Ziadne udalosti"
                message="Skus upravit filtre alebo vytvor novu udalost."
                compact
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <footer v-if="data" class="adminEvents__footer">
      <p>
        Strana <strong>{{ data.current_page }}</strong> z <strong>{{ data.last_page }}</strong>
        (spolu {{ data.total }})
      </p>

      <div class="adminEvents__pagination">
        <button
          type="button"
          class="ui-btn ui-btn--secondary"
          :disabled="loading || page <= 1"
          @click="prevPage"
        >
          Predchadzajuca
        </button>
        <button
          type="button"
          class="ui-btn ui-btn--secondary"
          :disabled="loading || page >= data.last_page"
          @click="nextPage"
        >
          Dalsia
        </button>
      </div>
    </footer>
  </section>
</template>

<style scoped>
.adminEvents {
  width: min(1100px, 100%);
  margin: 0 auto;
  padding: var(--space-6) var(--space-4);
  display: grid;
  gap: var(--space-4);
}

.adminEvents__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.adminEvents__title {
  margin: 0;
  font-size: clamp(1.15rem, 1.6vw, 1.45rem);
}

.adminEvents__subtitle {
  margin: var(--space-1) 0 0;
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
}

.adminEvents__tableWrap {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: auto;
  background: rgb(var(--bg-surface-rgb) / 0.7);
}

.adminEvents__table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
}

.adminEvents__table th {
  text-align: left;
  font-size: var(--font-size-xs);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-secondary);
  padding: var(--space-3);
  border-bottom: 1px solid var(--color-divider);
}

.adminEvents__table td {
  padding: var(--space-3);
  border-bottom: 1px solid var(--color-divider);
  vertical-align: middle;
}

.adminEvents__row {
  transition: background-color var(--motion-fast), transform 120ms ease;
}

.adminEvents__row:hover {
  background: var(--interactive-hover);
}

.adminEvents__row:last-child td {
  border-bottom: 0;
}

.adminEvents__emptyCell {
  padding: var(--space-3);
}

.is-right {
  text-align: right;
}

.adminEvents__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
}

.adminEvents__footer p {
  margin: 0;
}

.adminEvents__pagination {
  display: inline-flex;
  gap: var(--space-2);
}
</style>
