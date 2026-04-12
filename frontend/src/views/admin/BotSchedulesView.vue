<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
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
  refreshToken: {
    type: Number,
    default: 0,
  },
})

const loading = ref(false)
const savingId = ref(null)
const error = ref('')
const rows = ref([])
const botOptions = ref([])
const sourceOptions = ref([])
const editModalOpen = ref(false)

const editDraft = reactive({
  id: null,
  interval_minutes: 60,
  jitter_seconds: 0,
  enabled: true,
})

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
  if (loading.value) return 'Načítavam plánované behy…'
  return `${pagination.total} plánov · strana ${pagination.current_page}/${pagination.last_page}`
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
    error.value = e?.response?.data?.message || 'Načítanie plánov zlyhalo.'
  } finally {
    loading.value = false
  }
}

async function createSchedule() {
  const botUserId = Number(form.bot_user_id || 0)
  if (!Number.isInteger(botUserId) || botUserId <= 0) {
    error.value = 'Vyber bota pre plán.'
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
    error.value = e?.response?.data?.message || 'Vytvorenie plánu zlyhalo.'
  } finally {
    savingId.value = null
  }
}

function openEdit(row) {
  editDraft.id = Number(row?.id || 0)
  editDraft.interval_minutes = Number(row?.interval_minutes || 60)
  editDraft.jitter_seconds = Number(row?.jitter_seconds || 0)
  editDraft.enabled = Boolean(row?.enabled)
  editModalOpen.value = true
}

function handleEditModalToggle(isOpen) {
  editModalOpen.value = Boolean(isOpen)
  if (!isOpen) {
    editDraft.id = null
  }
}

async function saveEdit() {
  if (!editDraft.id) return

  savingId.value = editDraft.id
  try {
    await updateBotSchedule(editDraft.id, {
      enabled: Boolean(editDraft.enabled),
      interval_minutes: Number(editDraft.interval_minutes || 1),
      jitter_seconds: Number(editDraft.jitter_seconds || 0),
    })
    editModalOpen.value = false
    editDraft.id = null
    await load(pagination.current_page || 1)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Úprava plánu zlyhala.'
  } finally {
    savingId.value = null
  }
}

async function removeRow(row) {
  const approved = await confirm({
    title: 'Vymazať plán',
    message: `Naozaj vymazať plán #${row.id}?`,
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
    error.value = e?.response?.data?.message || 'Mazanie plánu zlyhalo.'
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

watch(
  () => props.refreshToken,
  () => {
    void load(pagination.current_page || 1)
  },
)

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
    v-bind="props.embedded ? {} : { title: 'Plány botov', subtitle: 'Bezpečná správa plánovaných behov.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Plány</h2>
        <p class="embeddedSubtitle">{{ summaryLine }}</p>
      </div>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Načítavam…' : 'Obnoviť' }}
      </button>
    </template>

    <details class="card createCard">
      <summary>Nový plán</summary>
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
            <span>Zdroj (voliteľne)</span>
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
            <span>Povolené</span>
          </label>
        </div>

        <div class="createActions">
          <button class="actionBtn" type="button" :disabled="savingId === 'create'" @click="createSchedule">Vytvoriť</button>
        </div>
      </div>
    </details>

    <section class="card tableCard">
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && rows.length === 0" class="muted">Zatiaľ nie sú vytvorené plány.</p>

      <div v-else class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>Bot</th>
              <th>Zdroj</th>
              <th>Interval</th>
              <th>Jitter</th>
              <th>Povolené</th>
              <th>Posledný beh</th>
              <th>Ďalší beh</th>
              <th>Akcia</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row?.bot_user?.username || row.bot_user_id }}</td>
              <td>{{ row?.source?.key || 'all' }}</td>
              <td>{{ Number(row.interval_minutes || 0) }} min</td>
              <td>{{ Number(row.jitter_seconds || 0) }} s</td>
              <td>
                <span class="result" :class="{ 'result--on': row.enabled }">
                  {{ row.enabled ? 'Áno' : 'Nie' }}
                </span>
              </td>
              <td>{{ formatDateTime(row.last_run_at) }}</td>
              <td>{{ formatDateTime(row.next_run_at) }}</td>
              <td>
                <div class="rowActions">
                  <button class="actionBtn" type="button" :disabled="savingId === row.id" @click="openEdit(row)">
                    Upraviť
                  </button>
                  <details class="rowMenu">
                    <summary>Viac</summary>
                    <div class="rowMenuBody">
                      <RouterLink
                        class="activityLink"
                        :to="{ name: 'admin.bots.activity', query: { bot_identity: row?.source?.bot_identity || undefined } }"
                      >
                        Logy
                      </RouterLink>
                      <button class="dangerBtn" type="button" :disabled="savingId === row.id" @click="removeRow(row)">Vymazať</button>
                    </div>
                  </details>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <button class="ghostBtn" type="button" :disabled="loading || !canPrev" @click="prevPage">Predošlá</button>
        <span class="muted">Strana {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }}</span>
        <button class="ghostBtn" type="button" :disabled="loading || !canNext" @click="nextPage">Ďalšia</button>
      </div>
    </section>

    <BaseModal
      :open="editModalOpen"
      title="Upraviť plán"
      test-id="bot-schedule-edit-modal"
      close-test-id="bot-schedule-edit-modal-close"
      @update:open="handleEditModalToggle"
    >
      <div class="editBody">
        <label class="field field--compact">
          <span>Interval (min)</span>
          <input v-model.number="editDraft.interval_minutes" type="number" min="1" max="10080" />
        </label>

        <label class="field field--compact">
          <span>Jitter (sek)</span>
          <input v-model.number="editDraft.jitter_seconds" type="number" min="0" max="86400" />
        </label>

        <label class="field field--toggle">
          <input v-model="editDraft.enabled" type="checkbox" />
          <span>Povolené</span>
        </label>

        <div class="editActions">
          <button class="actionBtn" type="button" :disabled="savingId === editDraft.id" @click="saveEdit">
            {{ savingId === editDraft.id ? 'Ukladám…' : 'Uložiť' }}
          </button>
          <button class="ghostBtn" type="button" :disabled="savingId === editDraft.id" @click="handleEditModalToggle(false)">
            Zrušiť
          </button>
        </div>
      </div>
    </BaseModal>
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
  font-size: 0.82rem;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
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

.createActions,
.editActions {
  display: inline-flex;
  gap: 8px;
  flex-wrap: wrap;
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
  min-width: 860px;
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

.result--on {
  border-color: rgb(var(--color-success-rgb) / 0.55);
  color: var(--color-success);
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

.editBody {
  display: grid;
  gap: 10px;
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
    min-width: 720px;
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

  .editActions {
    display: grid;
  }

  .editActions .actionBtn,
  .editActions .ghostBtn {
    width: 100%;
    text-align: center;
  }
}
</style>
