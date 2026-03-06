<script setup>
import { onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  clearBotSourceCooldown,
  getBotSources,
  resetBotSourceHealth,
  reviveBotSource,
  updateBotSource,
} from '@/services/api/admin/bots'

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
  <AdminPageShell title="Bot Sources" subtitle="Health monitoring a konfiguracia zdrojov.">
    <template #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="load">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </template>

    <section class="card filters">
      <label class="field">
        <span>Search</span>
        <input v-model="filters.q" type="text" placeholder="source key / name / url" />
      </label>
      <label class="field">
        <span>Enabled</span>
        <select v-model="filters.enabled">
          <option value="">Vsetky</option>
          <option value="1">Enabled</option>
          <option value="0">Disabled</option>
        </select>
      </label>
      <label class="field field--inline">
        <input v-model="filters.failing_only" type="checkbox" />
        <span>Failing only</span>
      </label>
      <div class="actions">
        <button class="actionBtn" type="button" :disabled="loading" @click="load">Filtrovat</button>
      </div>
    </section>

    <section v-if="editor.id" class="card editor">
      <h3>Edit source #{{ editor.id }}</h3>
      <div class="editorGrid">
        <label class="field">
          <span>Name</span>
          <input v-model="editor.name" type="text" maxlength="160" />
        </label>
        <label class="field">
          <span>URL</span>
          <input v-model="editor.url" type="url" maxlength="2048" />
        </label>
      </div>
      <div class="actions">
        <button class="actionBtn" type="button" :disabled="savingId === editor.id" @click="saveEdit">Ulozit</button>
        <button class="actionBtn ghost" type="button" :disabled="savingId === editor.id" @click="cancelEdit">Zrusit</button>
      </div>
    </section>

    <section class="card">
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && rows.length === 0" class="muted">Ziadne zdroje pre vybrane filtre.</p>

      <div v-else class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Status</th>
              <th>Latency</th>
              <th>Cooldown</th>
              <th>Last success</th>
              <th>Last error</th>
              <th>Failures</th>
              <th>Rates (24h)</th>
              <th>Enabled</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="name">{{ row.name || row.key }}</div>
                <div class="muted">{{ row.key }}</div>
              </td>
              <td>{{ row.source_type || '-' }}</td>
              <td>
                <span class="status" :class="`status--${String(row.status || '').toLowerCase()}`">
                  {{ String(row.status || 'unknown').toUpperCase() }}
                </span>
              </td>
              <td>{{ row.avg_latency_ms ? `${row.avg_latency_ms} ms` : '-' }}</td>
              <td>{{ formatDateTime(row.cooldown_until) }}</td>
              <td>{{ formatDateTime(row.last_success_at) }}</td>
              <td>{{ formatDateTime(row.last_error_at) }}</td>
              <td>{{ Number(row.consecutive_failures || 0) }}</td>
              <td>
                <div class="muted">S {{ formatRate(row?.metrics_24h?.success_rate) }}</div>
                <div class="muted">F {{ formatRate(row?.metrics_24h?.failure_rate) }}</div>
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
                <div class="actions">
                  <button class="actionBtn ghost" type="button" :disabled="savingId === row.id" @click="startEdit(row)">
                    Edit
                  </button>
                  <button class="actionBtn ghost" type="button" :disabled="savingId === row.id" @click="resetHealth(row)">
                    Reset health
                  </button>
                  <button
                    class="actionBtn ghost"
                    type="button"
                    :disabled="savingId === row.id || !row.cooldown_until"
                    @click="clearCooldown(row)"
                  >
                    Clear cooldown
                  </button>
                  <button
                    class="actionBtn ghost"
                    type="button"
                    :disabled="savingId === row.id"
                    @click="reviveSource(row)"
                  >
                    Revive
                  </button>
                </div>
                <span v-if="row.is_dead" class="status status--dead">DEAD</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.72);
  padding: 14px;
}

.filters {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
}

.editor {
  display: grid;
  gap: 10px;
}

.editorGrid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.field {
  display: grid;
  gap: 6px;
  font-size: 0.8rem;
}

.field input,
.field select {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  padding: 8px 10px;
}

.field--inline {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.actionBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  border-radius: 10px;
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
  font-weight: 700;
  padding: 7px 11px;
  cursor: pointer;
}

.actionBtn.ghost {
  border-color: rgb(var(--color-surface-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.35);
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
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 8px 10px;
  text-align: left;
  vertical-align: top;
  font-size: 0.8rem;
}

.table th {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.name {
  font-weight: 700;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.status {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.7rem;
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
  border: 1px solid rgb(var(--color-surface-rgb) / 0.26);
  border-radius: 999px;
  padding: 4px 10px;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.error {
  color: var(--color-danger);
  margin: 0;
}
</style>
