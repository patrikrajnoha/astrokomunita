<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { useConfirm } from '@/composables/useConfirm'
import {
  createBotSchedule,
  deleteBotSchedule,
  getBotOverview,
  getBotSchedules,
  getBotSources,
  updateBotSchedule,
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
const botOptions = ref([])
const sourceOptions = ref([])
const { confirm } = useConfirm()

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 30,
  total: 0,
})

const form = reactive({
  bot_user_id: '',
  source_id: '',
  interval_minutes: 60,
  jitter_seconds: 0,
  enabled: true,
})

const canPrev = computed(() => Number(pagination.current_page || 1) > 1)
const canNext = computed(() => Number(pagination.current_page || 1) < Number(pagination.last_page || 1))

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

async function bootstrapLookups() {
  const [overviewRes, sourcesRes] = await Promise.all([getBotOverview(), getBotSources()])
  botOptions.value = Array.isArray(overviewRes?.data?.bots) ? overviewRes.data.bots : []
  sourceOptions.value = Array.isArray(sourcesRes?.data?.data) ? sourcesRes.data.data : []
}

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const response = await getBotSchedules({
      page,
      per_page: pagination.per_page,
    })
    const payload = response?.data || {}
    rows.value = Array.isArray(payload?.data) ? payload.data : []
    pagination.current_page = Number(payload?.current_page || page)
    pagination.last_page = Number(payload?.last_page || 1)
    pagination.per_page = Number(payload?.per_page || pagination.per_page)
    pagination.total = Number(payload?.total || rows.value.length)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nacitanie schedule zaznamov zlyhalo.'
  } finally {
    loading.value = false
  }
}

async function createSchedule() {
  const botUserId = Number(form.bot_user_id || 0)
  if (!Number.isInteger(botUserId) || botUserId <= 0) {
    error.value = 'Vyber bota pre schedule.'
    return
  }

  savingId.value = 'create'
  error.value = ''
  try {
    await createBotSchedule({
      bot_user_id: botUserId,
      source_id: form.source_id ? Number(form.source_id) : null,
      interval_minutes: Number(form.interval_minutes || 60),
      jitter_seconds: Number(form.jitter_seconds || 0),
      enabled: Boolean(form.enabled),
    })

    form.source_id = ''
    form.interval_minutes = 60
    form.jitter_seconds = 0
    form.enabled = true
    await load(1)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Vytvorenie schedule zlyhalo.'
  } finally {
    savingId.value = null
  }
}

async function saveRow(row) {
  savingId.value = row.id
  try {
    await updateBotSchedule(row.id, {
      enabled: Boolean(row.enabled),
      interval_minutes: Number(row.interval_minutes || 1),
      jitter_seconds: Number(row.jitter_seconds || 0),
    })
    await load(pagination.current_page || 1)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Uprava schedule zlyhala.'
  } finally {
    savingId.value = null
  }
}

async function removeRow(row) {
  const approved = await confirm({
    title: 'Vymazat schedule',
    message: `Naozaj vymazat schedule #${row.id}?`,
    confirmText: 'Vymazat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!approved) return

  savingId.value = row.id
  try {
    await deleteBotSchedule(row.id)
    await load(pagination.current_page || 1)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Mazanie schedule zlyhalo.'
  } finally {
    savingId.value = null
  }
}

function prevPage() {
  if (!canPrev.value || loading.value) return
  void load(Number(pagination.current_page || 1) - 1)
}

function nextPage() {
  if (!canNext.value || loading.value) return
  void load(Number(pagination.current_page || 1) + 1)
}

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    await bootstrapLookups()
    await load(1)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <component
    :is="props.embedded ? 'section' : AdminPageShell"
    v-bind="props.embedded ? {} : { title: 'Plany botov', subtitle: 'Sprava planov bez potreby nasadenia.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Plany</h2>
        <p class="embeddedSubtitle">Sprava planov bez potreby deployu.</p>
      </div>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </template>

    <section class="card formCard">
      <h3>Novy schedule</h3>
      <div class="formGrid">
        <label class="field">
          <span>Bot</span>
          <select v-model="form.bot_user_id">
            <option value="">Vyber bota</option>
            <option v-for="bot in botOptions" :key="bot.id" :value="String(bot.id)">
              {{ bot.username }} ({{ bot.bot_identity || '-' }})
            </option>
          </select>
        </label>
        <label class="field">
          <span>Zdroj (volitelne)</span>
          <select v-model="form.source_id">
            <option value="">Vsetky povolene zdroje</option>
            <option v-for="source in sourceOptions" :key="source.id" :value="String(source.id)">
              {{ source.key }}
            </option>
          </select>
        </label>
        <label class="field">
          <span>Interval (min)</span>
          <input v-model.number="form.interval_minutes" type="number" min="1" max="10080" />
        </label>
        <label class="field">
          <span>Jitter (sek)</span>
          <input v-model.number="form.jitter_seconds" type="number" min="0" max="86400" />
        </label>
        <label class="field field--inline">
          <input v-model="form.enabled" type="checkbox" />
          <span>Povolene</span>
        </label>
      </div>
      <div class="actions">
        <button class="actionBtn" type="button" :disabled="savingId === 'create'" @click="createSchedule">Vytvorit</button>
      </div>
    </section>

    <section class="card">
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && rows.length === 0" class="muted">Zatial nie su vytvorene schedules.</p>

      <div v-else class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Bot</th>
              <th>Zdroj</th>
              <th>Interval</th>
              <th>Jitter</th>
              <th>Povolene</th>
              <th>Posledny beh</th>
              <th>Dalsi beh</th>
              <th>Vysledok</th>
              <th>Akcia</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.id }}</td>
              <td>{{ row?.bot_user?.username || row.bot_user_id }}</td>
              <td>{{ row?.source?.key || 'all' }}</td>
              <td>
                <input v-model.number="row.interval_minutes" class="inlineInput" type="number" min="1" max="10080" />
              </td>
              <td>
                <input v-model.number="row.jitter_seconds" class="inlineInput" type="number" min="0" max="86400" />
              </td>
              <td>
                <input v-model="row.enabled" type="checkbox" />
              </td>
              <td>{{ formatDateTime(row.last_run_at) }}</td>
              <td>{{ formatDateTime(row.next_run_at) }}</td>
              <td>
                <span class="result">{{ row.last_result || '-' }}</span>
              </td>
              <td>
                <div class="rowActions">
                  <button class="actionBtn ghost" type="button" :disabled="savingId === row.id" @click="saveRow(row)">Ulozit</button>
                  <button class="actionBtn danger" type="button" :disabled="savingId === row.id" @click="removeRow(row)">Zmazat</button>
                  <RouterLink
                    class="activityLink"
                    :to="{ name: 'admin.bots.activity', query: { bot_identity: row?.source?.bot_identity || undefined } }"
                  >
                    Aktivita
                  </RouterLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <button class="actionBtn ghost" type="button" :disabled="loading || !canPrev" @click="prevPage">Predosla</button>
        <span class="muted">Strana {{ pagination.current_page }} / {{ pagination.last_page }} - {{ pagination.total }}</span>
        <button class="actionBtn ghost" type="button" :disabled="loading || !canNext" @click="nextPage">Dalsia</button>
      </div>
    </section>
  </component>
</template>

<style scoped>
.botSection {
  display: grid;
  gap: 14px;
}

.embeddedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.embeddedTitle {
  margin: 0 0 6px;
  font-size: 1.06rem;
  font-weight: 800;
}

.embeddedSubtitle {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.85rem;
}

.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.72);
  padding: 14px;
}

.formCard {
  display: grid;
  gap: 10px;
}

.formCard h3 {
  margin: 0;
}

.formGrid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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

.actionBtn.danger {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  background: rgb(var(--color-danger-rgb) / 0.16);
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
}

.table {
  width: 100%;
  min-width: 1040px;
  border-collapse: collapse;
}

.table th,
.table td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 8px 10px;
  text-align: left;
  font-size: 0.8rem;
}

.table th {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.inlineInput {
  width: 78px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  padding: 6px 8px;
}

.rowActions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.result {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.72rem;
  font-weight: 700;
}

.activityLink {
  color: var(--color-primary);
  font-size: 0.78rem;
  font-weight: 700;
}

.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.error {
  color: var(--color-danger);
  margin: 0;
}
</style>
