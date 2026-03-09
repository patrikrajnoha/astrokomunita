<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  clearBotSourceCooldown,
  getBotSources,
  resetBotSourceHealth,
  reviveBotSource,
  updateBotSource,
} from '@/services/api/admin/bots'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const loading = ref(false)
const savingId = ref(null)
const error = ref('')
const rows = ref([])

const filters = reactive({
  q: '',
  enabled: '',
  failing_only: false,
})

const editor = reactive({
  id: null,
  name: '',
  url: '',
})

const hasActiveFilters = computed(() => {
  return (
    String(filters.q || '').trim() !== '' ||
    filters.enabled === '1' ||
    filters.enabled === '0' ||
    filters.failing_only
  )
})

const summaryLine = computed(() => {
  const count = rows.value.length
  if (loading.value) return 'Nacitavam zdroje...'
  if (hasActiveFilters.value) return `${count} zdrojov pre aktivne filtre`
  return `${count} zdrojov`
})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatRate(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric < 0) return '-'
  return `${(numeric * 100).toFixed(1)}%`
}

function requestParams() {
  const params = {}
  if (String(filters.q || '').trim() !== '') params.q = String(filters.q).trim()
  if (filters.enabled === '1' || filters.enabled === '0') params.enabled = Number(filters.enabled)
  if (filters.failing_only) params.failing_only = 1
  return params
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await getBotSources(requestParams())
    rows.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nacitanie zdrojov zlyhalo.'
  } finally {
    loading.value = false
  }
}

async function clearFilters() {
  filters.q = ''
  filters.enabled = ''
  filters.failing_only = false
  await load()
}

function startEdit(row) {
  editor.id = row.id
  editor.name = String(row.name || '')
  editor.url = String(row.url || '')
}

function cancelEdit() {
  editor.id = null
  editor.name = ''
  editor.url = ''
}

async function saveEdit() {
  if (!editor.id) return
  savingId.value = editor.id
  try {
    await updateBotSource(editor.id, {
      name: String(editor.name || '').trim() || null,
      url: String(editor.url || '').trim(),
    })
    cancelEdit()
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Ulozenie source konfiguracie zlyhalo.'
  } finally {
    savingId.value = null
  }
}

async function toggleEnabled(row) {
  savingId.value = row.id
  try {
    await updateBotSource(row.id, { is_enabled: !row.is_enabled })
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Aktualizacia source statusu zlyhala.'
  } finally {
    savingId.value = null
  }
}

async function resetHealth(row) {
  savingId.value = row.id
  try {
    await resetBotSourceHealth(row.id)
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reset health zlyhal.'
  } finally {
    savingId.value = null
  }
}

async function clearCooldown(row) {
  savingId.value = row.id
  try {
    await clearBotSourceCooldown(row.id)
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Clear cooldown zlyhal.'
  } finally {
    savingId.value = null
  }
}

async function reviveSource(row) {
  savingId.value = row.id
  try {
    await reviveBotSource(row.id)
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Revive source zlyhal.'
  } finally {
    savingId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <component
    :is="props.embedded ? 'section' : AdminPageShell"
    v-bind="props.embedded ? {} : { title: 'Bot zdroje', subtitle: 'Monitoring zdravia a konfiguracia zdrojov.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Zdroje</h2>
        <p class="embeddedSubtitle">{{ summaryLine }}</p>
      </div>
      <button class="actionBtn" type="button" :disabled="loading" @click="load">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="load">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </template>

    <section class="card filterCard">
      <div class="filterRow">
        <label class="field field--search">
          <input v-model="filters.q" type="text" placeholder="Hladat zdroj podla key, nazvu alebo URL" />
        </label>

        <label class="field field--compact">
          <span>Povolene</span>
          <select v-model="filters.enabled">
            <option value="">Vsetky</option>
            <option value="1">Povolene</option>
            <option value="0">Zakazane</option>
          </select>
        </label>

        <label class="field field--toggle">
          <input v-model="filters.failing_only" type="checkbox" />
          <span>Iba chybove</span>
        </label>

        <div class="filterActions">
          <button class="actionBtn" type="button" :disabled="loading" @click="load">Filtrovat</button>
          <button v-if="hasActiveFilters" class="ghostBtn" type="button" :disabled="loading" @click="clearFilters">
            Vycistit
          </button>
        </div>
      </div>
    </section>

    <section v-if="editor.id" class="card editorCard">
      <h3>Upravit zdroj #{{ editor.id }}</h3>
      <div class="editorGrid">
        <label class="field">
          <span>Nazov</span>
          <input v-model="editor.name" type="text" maxlength="160" />
        </label>
        <label class="field">
          <span>URL adresa</span>
          <input v-model="editor.url" type="url" maxlength="2048" />
        </label>
      </div>
      <div class="editorActions">
        <button class="actionBtn" type="button" :disabled="savingId === editor.id" @click="saveEdit">Ulozit</button>
        <button class="ghostBtn" type="button" :disabled="savingId === editor.id" @click="cancelEdit">Zrusit</button>
      </div>
    </section>

    <section class="card tableCard">
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && rows.length === 0" class="muted">Ziadne zdroje pre vybrane filtre.</p>

      <div v-else class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>Nazov</th>
              <th>Typ</th>
              <th>Stav</th>
              <th>Latencia</th>
              <th>Cooldown</th>
              <th>Posledny uspech</th>
              <th>Posledna chyba</th>
              <th>Zlyhania</th>
              <th>Miera (24h)</th>
              <th>Povolene</th>
              <th>Akcia</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="name">{{ row.name || row.key }}</div>
                <div class="muted muted--small">{{ row.key }}</div>
              </td>
              <td>{{ row.source_type || '-' }}</td>
              <td>
                <div class="statusCell">
                  <span class="status" :class="`status--${String(row.status || '').toLowerCase()}`">
                    {{ String(row.status || 'unknown').toUpperCase() }}
                  </span>
                  <span v-if="row.is_dead" class="status status--dead">DEAD</span>
                </div>
              </td>
              <td>{{ row.avg_latency_ms ? `${row.avg_latency_ms} ms` : '-' }}</td>
              <td>{{ formatDateTime(row.cooldown_until) }}</td>
              <td>{{ formatDateTime(row.last_success_at) }}</td>
              <td>{{ formatDateTime(row.last_error_at) }}</td>
              <td>{{ Number(row.consecutive_failures || 0) }}</td>
              <td>
                <div class="muted muted--small">S {{ formatRate(row?.metrics_24h?.success_rate) }}</div>
                <div class="muted muted--small">F {{ formatRate(row?.metrics_24h?.failure_rate) }}</div>
              </td>
              <td>
                <button
                  class="toggleBtn"
                  type="button"
                  :disabled="savingId === row.id"
                  @click="toggleEnabled(row)"
                >
                  {{ row.is_enabled ? 'ON' : 'OFF' }}
                </button>
              </td>
              <td>
                <div class="rowActions">
                  <button class="ghostBtn" type="button" :disabled="savingId === row.id" @click="startEdit(row)">
                    Upravit
                  </button>
                  <details class="rowMenu">
                    <summary>Viac</summary>
                    <div class="rowMenuBody">
                      <button class="ghostBtn" type="button" :disabled="savingId === row.id" @click="resetHealth(row)">
                        Reset zdravia
                      </button>
                      <button
                        class="ghostBtn"
                        type="button"
                        :disabled="savingId === row.id || !row.cooldown_until"
                        @click="clearCooldown(row)"
                      >
                        Vycistit cooldown
                      </button>
                      <button class="ghostBtn" type="button" :disabled="savingId === row.id" @click="reviveSource(row)">
                        Obnovit
                      </button>
                    </div>
                  </details>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </component>
</template>

<style scoped>
.botSection {
  display: grid;
  gap: 12px;
}

.embeddedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.embeddedTitle {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 800;
}

.embeddedSubtitle {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.8rem;
}

.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.66);
  padding: 12px;
}

.filterCard {
  padding: 10px;
}

.filterRow {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) minmax(120px, auto) minmax(130px, auto) auto;
  align-items: end;
  gap: 8px;
}

.field {
  display: grid;
  gap: 5px;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.field--search {
  min-width: 0;
}

.field input,
.field select {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.38);
  color: var(--color-surface);
  min-height: 34px;
  padding: 7px 9px;
}

.field--compact {
  min-width: 120px;
}

.field--toggle {
  min-height: 34px;
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.field--toggle input {
  width: 15px;
  height: 15px;
}

.filterActions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.actionBtn,
.ghostBtn,
.toggleBtn {
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
}

.actionBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.ghostBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.editorCard {
  display: grid;
  gap: 8px;
}

.editorCard h3 {
  margin: 0;
  font-size: 0.9rem;
}

.editorGrid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.editorActions {
  display: inline-flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
}

.table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
}

.table th,
.table td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  padding: 8px 9px;
  text-align: left;
  vertical-align: top;
  font-size: 0.78rem;
}

.table th {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.statusCell {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.name {
  font-weight: 700;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.muted--small {
  font-size: 0.72rem;
}

.status {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 2px 7px;
  font-size: 0.66rem;
  font-weight: 700;
}

.status--ok {
  border-color: rgb(var(--color-success-rgb) / 0.55);
  color: var(--color-success);
}

.status--warn {
  border-color: rgb(var(--color-warning-rgb) / 0.55);
  color: var(--color-warning);
}

.status--fail,
.status--dead {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  color: var(--color-danger);
}

.toggleBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
}

.rowActions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.rowMenu {
  position: relative;
}

.rowMenu > summary {
  list-style: none;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.rowMenu > summary::-webkit-details-marker {
  display: none;
}

.rowMenuBody {
  position: absolute;
  right: 0;
  margin-top: 6px;
  min-width: 170px;
  z-index: 20;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.95);
  padding: 6px;
  display: grid;
  gap: 6px;
}

.rowMenuBody .ghostBtn {
  text-align: left;
  justify-content: flex-start;
}

.error {
  color: var(--color-danger);
  margin: 0;
}

@media (max-width: 980px) {
  .filterRow {
    grid-template-columns: 1fr;
    align-items: stretch;
  }

  .filterActions {
    justify-content: flex-start;
  }
}
</style>
