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
const summaryLine = computed(() => {
  if (loading.value) return 'Načítavam schedule záznamy...'
  return `${pagination.total} planov | strana ${pagination.current_page}/${pagination.last_page}`
})

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
    title: 'Vymazať schedule',
    message: `Naozaj vymazať schedule #${row.id}?`,
    confirmText: 'Vymazať',
    cancelText: 'Zrušiť',
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
    v-bind="props.embedded ? {} : { title: 'Plány botov', subtitle: 'Správa plánov bez potreby nasadenia.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Plány</h2>
        <p class="embeddedSubtitle">{{ summaryLine }}</p>
      </div>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Načítavam...' : 'Obnoviť' }}
      </button>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Načítavam...' : 'Obnoviť' }}
      </button>
    </template>

    <details class="card createCard" :open="rows.length === 0">
      <summary>Novy schedule</summary>
      <div class="createBody">
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
              <option value="">Všetky povolené zdroje</option>
              <option v-for="source in sourceOptions" :key="source.id" :value="String(source.id)">
                {{ source.key }}
              </option>
            </select>
          </label>

          <label class="field field--compact">
            <span>Interval (min)</span>
            <input v-model.number="form.interval_minutes" type="number" min="1" max="10080" />
          </label>

          <label class="field field--compact">
            <span>Jitter (sek)</span>
            <input v-model.number="form.jitter_seconds" type="number" min="0" max="86400" />
          </label>

          <label class="field field--toggle">
            <input v-model="form.enabled" type="checkbox" />
            <span>Povolene</span>
          </label>
        </div>

        <div class="createActions">
          <button class="actionBtn" type="button" :disabled="savingId === 'create'" @click="createSchedule">Vytvoriť</button>
        </div>
      </div>
    </details>

    <section class="card tableCard">
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && rows.length === 0" class="muted">Zatiaľ nie sú vytvorené schedules.</p>

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
                  <button class="actionBtn" type="button" :disabled="savingId === row.id" @click="saveRow(row)">Uložiť</button>
                  <details class="rowMenu">
                    <summary>Viac</summary>
                    <div class="rowMenuBody">
                      <RouterLink
                        class="activityLink"
                        :to="{ name: 'admin.bots.activity', query: { bot_identity: row?.source?.bot_identity || undefined } }"
                      >
                        Aktivita
                      </RouterLink>
                      <button class="dangerBtn" type="button" :disabled="savingId === row.id" @click="removeRow(row)">Zmazať</button>
                    </div>
                  </details>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <button class="ghostBtn" type="button" :disabled="loading || !canPrev" @click="prevPage">Predosla</button>
        <span class="muted">Strana {{ pagination.current_page }} / {{ pagination.last_page }} - {{ pagination.total }}</span>
        <button class="ghostBtn" type="button" :disabled="loading || !canNext" @click="nextPage">Ďalšia</button>
      </div>
    </section>
  </component>
</template>

<style scoped>
.botSection {
  display: grid;
  gap: 12px;
  min-width: 0;
  container-type: inline-size;
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

.createCard {
  padding: 10px 12px;
}

.createCard > summary {
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.createBody {
  margin-top: 10px;
  display: grid;
  gap: 8px;
  min-width: 0;
}

.formGrid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 8px;
}

.field {
  display: grid;
  gap: 5px;
  font-size: 0.74rem;
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
  max-width: 170px;
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

.createActions {
  display: inline-flex;
  gap: 8px;
}

.actionBtn,
.ghostBtn,
.dangerBtn {
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

.dangerBtn {
  border: 1px solid rgb(var(--color-danger-rgb) / 0.55);
  background: rgb(var(--color-danger-rgb) / 0.14);
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
  max-width: 100%;
}

.table {
  width: 100%;
  min-width: 920px;
  border-collapse: collapse;
}

.table th,
.table td {
  border-bottom: 1px solid var(--divider-color);
  padding: 8px 9px;
  text-align: left;
  font-size: 0.78rem;
}

.table th {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.inlineInput {
  width: 74px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.38);
  color: var(--color-surface);
  padding: 5px 7px;
}

.rowActions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
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
}

.rowMenu > summary::-webkit-details-marker {
  display: none;
}

.rowMenuBody {
  position: absolute;
  margin-top: 6px;
  right: 0;
  min-width: 130px;
  z-index: 20;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.95);
  padding: 6px;
  display: grid;
  gap: 6px;
}

.result {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 2px 7px;
  font-size: 0.68rem;
  font-weight: 700;
}

.activityLink {
  color: var(--color-primary);
  font-size: 0.74rem;
  font-weight: 700;
  text-decoration: none;
}

.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
  flex-wrap: wrap;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.8rem;
}

.error {
  color: var(--color-danger);
  margin: 0;
}

@container (max-width: 920px) {
  .embeddedHeader {
    align-items: stretch;
    flex-direction: column;
  }

  .embeddedHeader .actionBtn {
    width: 100%;
    text-align: center;
  }

  .formGrid {
    grid-template-columns: 1fr;
  }

  .field--compact {
    max-width: 100%;
  }

  .createActions {
    width: 100%;
  }

  .createActions .actionBtn {
    width: 100%;
    text-align: center;
  }
}

@container (max-width: 760px) {
  .table {
    min-width: 760px;
  }

  .rowActions {
    width: 100%;
    justify-content: flex-end;
  }

  .pager {
    justify-content: stretch;
  }

  .pager .ghostBtn {
    flex: 1 1 auto;
    text-align: center;
  }
}
</style>
