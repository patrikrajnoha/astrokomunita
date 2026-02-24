<script setup>
import { computed, ref } from 'vue'
import { useAdminTable } from '@/composables/useAdminTable'
import http from '@/services/api'

const mode = ref('list')
const editingEvent = ref(null)
const formLoading = ref(false)
const formError = ref('')
const formSuccess = ref('')
const translationActionLoading = ref(false)
const translationError = ref('')
const translationSummary = ref(null)

const eventTypes = [
  { value: 'meteor_shower', label: 'Meteoricky roj' },
  { value: 'eclipse_lunar', label: 'Zatmenie mesiaca' },
  { value: 'eclipse_solar', label: 'Zatmenie slnka' },
  { value: 'planetary_event', label: 'Planetarny ukaz' },
  { value: 'other', label: 'Ina udalost' },
]

const form = ref({
  title: '',
  description: '',
  type: 'meteor_shower',
  start_at: '',
  end_at: '',
  visibility: 1,
})

const {
  loading,
  error,
  data,
  pagination,
  hasNextPage,
  hasPrevPage,
  nextPage,
  prevPage,
  perPage,
  setPerPage,
  refresh,
} = useAdminTable(
  async (params) => {
    const response = await http.get('/admin/events', { params })
    return response
  },
  { defaultPerPage: 20 }
)

const isEdit = computed(() => mode.value === 'edit' && Boolean(editingEvent.value))

const formErrors = computed(() => {
  const errors = []
  if (!String(form.value.title || '').trim()) {
    errors.push('Nazov je povinny.')
  }
  if (!form.value.start_at) {
    errors.push('Cas zaciatku je povinny.')
  }

  if (form.value.start_at && form.value.end_at) {
    const start = new Date(form.value.start_at)
    const end = new Date(form.value.end_at)
    if (!Number.isNaN(start.getTime()) && !Number.isNaN(end.getTime()) && end < start) {
      errors.push('Koniec nemoze byt skor ako zaciatok.')
    }
  }

  return errors
})

function openCreate() {
  editingEvent.value = null
  form.value = {
    title: '',
    description: '',
    type: 'meteor_shower',
    start_at: '',
    end_at: '',
    visibility: 1,
  }
  formError.value = ''
  formSuccess.value = ''
  mode.value = 'create'
}

function openEdit(event) {
  editingEvent.value = event
  form.value = {
    title: event.title || '',
    description: event.description || '',
    type: event.type || 'meteor_shower',
    start_at: toLocalInput(event.start_at || event.starts_at || event.max_at),
    end_at: toLocalInput(event.end_at || event.ends_at),
    visibility: typeof event.visibility === 'number' ? event.visibility : 1,
  }
  formError.value = ''
  formSuccess.value = ''
  mode.value = 'edit'
}

function closeForm() {
  mode.value = 'list'
  formError.value = ''
  formSuccess.value = ''
}

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function toLocalInput(value) {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function setStartNow() {
  form.value.start_at = toLocalInput(new Date().toISOString())
}

function setEndAfter(hours) {
  const base = form.value.start_at ? new Date(form.value.start_at) : new Date()
  if (Number.isNaN(base.getTime())) return
  base.setHours(base.getHours() + hours)
  form.value.end_at = toLocalInput(base.toISOString())
}

async function submitForm() {
  if (formErrors.value.length > 0) {
    formError.value = formErrors.value[0]
    return
  }

  formLoading.value = true
  formError.value = ''
  formSuccess.value = ''

  const payload = {
    title: String(form.value.title || '').trim(),
    description: String(form.value.description || '').trim() || null,
    type: form.value.type,
    start_at: form.value.start_at,
    end_at: form.value.end_at || null,
    visibility: form.value.visibility,
  }

  try {
    if (isEdit.value) {
      await http.put(`/admin/events/${editingEvent.value.id}`, payload)
      formSuccess.value = 'Udalost bola upravena.'
    } else {
      await http.post('/admin/events', payload)
      formSuccess.value = 'Udalost bola vytvorena.'
    }

    await refresh()
    mode.value = 'list'
  } catch (err) {
    formError.value = err?.response?.data?.message || 'Ulozenie zlyhalo.'
  } finally {
    formLoading.value = false
  }
}

async function requestTranslationBackfill(dryRun) {
  translationActionLoading.value = true
  translationError.value = ''
  translationSummary.value = null

  try {
    const response = await http.post('/admin/events/retranslate', {
      dry_run: dryRun,
      force: false,
      limit: 0,
    })
    translationSummary.value = response.data
    if (!dryRun) {
      await refresh()
    }
  } catch (err) {
    translationError.value = err?.response?.data?.message || 'Retranslate zlyhal.'
  } finally {
    translationActionLoading.value = false
  }
}

async function previewTranslationBackfill() {
  await requestTranslationBackfill(true)
}

async function runTranslationBackfill() {
  if (!window.confirm('Spustit retranslate schvalenych udalosti?')) return
  await requestTranslationBackfill(false)
}
</script>

<template>
  <div class="eventsView">
    <section class="panel headerPanel">
      <div>
        <h1>Udalosti</h1>
        <p>Kompaktny prehlad publikovanych a manualnych udalosti.</p>
      </div>
      <div class="toolbar">
        <button class="btn ghost" :disabled="translationActionLoading" @click="previewTranslationBackfill">Nahlad retranslate</button>
        <button class="btn ghost" :disabled="translationActionLoading" @click="runTranslationBackfill">Spustit retranslate</button>
        <button class="btn primary" @click="openCreate">Nova udalost</button>
      </div>
    </section>

    <section v-if="translationError" class="notice noticeError">{{ translationError }}</section>
    <section v-else-if="translationSummary" class="notice noticeOk">
      Kandidati: {{ translationSummary.summary?.total_candidates ?? 0 }} |
      Prelozene: {{ translationSummary.summary?.translated ?? 0 }} |
      Zlyhalo: {{ translationSummary.summary?.failed ?? 0 }} |
      Updated events: {{ translationSummary.summary?.events_updated ?? 0 }} |
      Dry run: {{ translationSummary.summary?.dry_run ? 'ano' : 'nie' }}
    </section>

    <section v-if="mode !== 'list'" class="panel formPanel">
      <div class="formHead">
        <div>
          <h2>{{ isEdit ? 'Upravit udalost' : 'Vytvorit udalost' }}</h2>
          <p>{{ isEdit ? 'Upravis existujuci zaznam.' : 'Vytvoris novu manualnu udalost.' }}</p>
        </div>
        <div class="quickBtns">
          <button class="btn tiny" type="button" @click="setStartNow">Zaciatok teraz</button>
          <button class="btn tiny" type="button" @click="setEndAfter(1)">Koniec +1h</button>
          <button class="btn tiny" type="button" @click="setEndAfter(2)">Koniec +2h</button>
        </div>
      </div>

      <div v-if="formError" class="notice noticeError">{{ formError }}</div>
      <div v-if="formSuccess" class="notice noticeOk">{{ formSuccess }}</div>

      <form class="formGrid" @submit.prevent="submitForm">
        <label class="field fieldWide">
          <span>Nazov *</span>
          <input v-model="form.title" type="text" :disabled="formLoading" />
        </label>

        <label class="field">
          <span>Typ *</span>
          <select v-model="form.type" :disabled="formLoading">
            <option v-for="item in eventTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
        </label>

        <label class="field">
          <span>Viditelnost</span>
          <select v-model.number="form.visibility" :disabled="formLoading">
            <option :value="1">Public</option>
            <option :value="0">Hidden</option>
          </select>
        </label>

        <label class="field">
          <span>Zaciatok *</span>
          <input v-model="form.start_at" type="datetime-local" :disabled="formLoading" />
        </label>

        <label class="field">
          <span>Koniec</span>
          <input v-model="form.end_at" type="datetime-local" :disabled="formLoading" />
        </label>

        <label class="field fieldWide">
          <span>Popis</span>
          <textarea v-model="form.description" rows="3" :disabled="formLoading"></textarea>
        </label>

        <div v-if="formErrors.length > 0" class="fieldWide notice noticeError">{{ formErrors[0] }}</div>

        <div class="formActions fieldWide">
          <button type="button" class="btn ghost" :disabled="formLoading" @click="closeForm">Zrusit</button>
          <button type="submit" class="btn primary" :disabled="formLoading || formErrors.length > 0">
            {{ formLoading ? 'Uklada sa...' : (isEdit ? 'Ulozit zmeny' : 'Vytvorit') }}
          </button>
        </div>
      </form>
    </section>

    <section class="panel listPanel">
      <div class="listTop">
        <div class="meta">
          <strong>Prehlad</strong>
          <span v-if="pagination">Strana {{ pagination.currentPage }} / {{ pagination.lastPage }} (spolu {{ pagination.total }})</span>
        </div>
        <label class="perPage">
          <span>Na stranku</span>
          <select :value="perPage" @change="setPerPage(Number($event.target.value))">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </label>
      </div>

      <div v-if="error" class="notice noticeError">
        {{ error }}
        <button class="btn tiny" @click="refresh">Skusit znova</button>
      </div>

      <div v-else-if="loading" class="loading">Nacitavam udalosti...</div>

      <div v-else-if="data" class="tableWrap">
        <table class="compactTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nazov</th>
              <th>Typ</th>
              <th>Zaciatok</th>
              <th>Stav</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in data.data" :key="event.id">
              <td class="mono">{{ event.id }}</td>
              <td>
                <div class="title">{{ event.title }}</div>
                <div v-if="event.description" class="sub">{{ event.description }}</div>
              </td>
              <td><span class="pill">{{ event.type }}</span></td>
              <td>{{ formatDate(event.start_at || event.starts_at || event.max_at) }}</td>
              <td>
                <span class="pill" :class="event.visibility === 1 ? 'ok' : 'muted'">
                  {{ event.visibility === 1 ? 'public' : 'hidden' }}
                </span>
              </td>
              <td class="right">
                <button class="btn tiny" @click="openEdit(event)">Upravit</button>
              </td>
            </tr>
            <tr v-if="data.data.length === 0">
              <td colspan="6" class="empty">Ziadne udalosti.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination" class="pager">
        <button class="btn ghost" :disabled="!hasPrevPage" @click="prevPage">Pred</button>
        <button class="btn ghost" :disabled="!hasNextPage" @click="nextPage">Dalsia</button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.eventsView {
  display: grid;
  gap: 12px;
}

.panel {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.84);
  padding: 12px;
}

.headerPanel {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.headerPanel h1 {
  margin: 0;
  font-size: 1.2rem;
}

.headerPanel p {
  margin: 3px 0 0;
  font-size: 12px;
  opacity: 0.82;
}

.toolbar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 10px;
  padding: 7px 11px;
  background: transparent;
  color: inherit;
  font-size: 13px;
}

.btn:hover:not(:disabled) {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn.primary {
  border-color: rgb(var(--color-primary-rgb) / 0.36);
  background: rgb(var(--color-primary-rgb) / 0.14);
}

.btn.tiny {
  padding: 5px 9px;
  font-size: 12px;
  border-radius: 999px;
}

.notice {
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 12px;
}

.noticeOk {
  border: 1px solid rgb(22 163 74 / 0.35);
  background: rgb(22 163 74 / 0.1);
}

.noticeError {
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
}

.formPanel {
  display: grid;
  gap: 10px;
}

.formHead {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.formHead h2 {
  margin: 0;
  font-size: 1rem;
}

.formHead p {
  margin: 3px 0 0;
  font-size: 12px;
  opacity: 0.82;
}

.quickBtns {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.formGrid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.field {
  display: grid;
  gap: 4px;
}

.field span {
  font-size: 12px;
  opacity: 0.82;
}

.fieldWide {
  grid-column: span 4;
}

.field input,
.field textarea,
.field select {
  width: 100%;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 10px;
  background: transparent;
  color: inherit;
  padding: 8px 10px;
}

.formActions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.listTop {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.meta {
  display: grid;
  gap: 2px;
}

.meta span {
  font-size: 12px;
  opacity: 0.82;
}

.perPage {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.perPage select {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  padding: 6px 8px;
}

.loading {
  font-size: 13px;
  opacity: 0.85;
}

.tableWrap {
  overflow-x: auto;
}

.compactTable {
  width: 100%;
  border-collapse: collapse;
}

.compactTable th,
.compactTable td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 8px;
  text-align: left;
  vertical-align: top;
}

.compactTable th {
  font-size: 12px;
  opacity: 0.82;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
}

.title {
  font-weight: 600;
}

.sub {
  margin-top: 3px;
  font-size: 12px;
  opacity: 0.75;
  max-width: 420px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  background: rgb(var(--color-surface-rgb) / 0.08);
  padding: 2px 8px;
  font-size: 12px;
}

.pill.ok {
  border-color: rgb(22 163 74 / 0.35);
  background: rgb(22 163 74 / 0.12);
}

.pill.muted {
  opacity: 0.75;
}

.right {
  text-align: right;
}

.empty {
  text-align: center;
  opacity: 0.78;
  padding: 16px;
}

.pager {
  margin-top: 10px;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 900px) {
  .formGrid {
    grid-template-columns: 1fr;
  }

  .fieldWide {
    grid-column: span 1;
  }
}
</style>
