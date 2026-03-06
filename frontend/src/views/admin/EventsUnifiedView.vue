<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAdminTable } from '@/composables/useAdminTable'
import { useConfirm } from '@/composables/useConfirm'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import http from '@/services/api'
import {
  generateAdminEventDescription,
  getAdminAiConfig,
  postEditAdminEventTitle,
} from '@/services/api/admin/ai'

const mode = ref('list')
const editingEvent = ref(null)
const formLoading = ref(false)
const formError = ref('')
const formSuccess = ref('')
const translationActionLoading = ref(false)
const translationError = ref('')
const translationSummary = ref(null)
const aiConfig = ref(null)
const aiConfigLoading = ref(false)
const aiActionLoading = ref(false)
const aiActionError = ref('')
const aiActionStatus = ref('idle')
const aiActionResult = ref(null)
const aiLastRunByEvent = ref({})
const aiActionNotice = ref('')
const aiActionRawStatus = ref(null)
const aiUndoSnapshot = ref(null)
const aiShortDraft = ref('')
const aiTitleActionLoading = ref(false)
const aiTitleActionError = ref('')
const aiTitleActionStatus = ref('idle')
const aiTitleActionRawStatus = ref(null)
const aiTitleActionNotice = ref('')
const aiTitleFallbackUsed = ref(false)
const aiTitleSuggestion = ref('')
const aiTitleSource = ref('')
const aiTitleUndoSnapshot = ref(null)
const aiTitleLastRunByEvent = ref({})
const { confirm } = useConfirm()

const eventTypes = [
  { value: 'meteor_shower', label: 'Meteorický roj' },
  { value: 'eclipse_lunar', label: 'Zatmenie Mesiaca' },
  { value: 'eclipse_solar', label: 'Zatmenie Slnka' },
  { value: 'planetary_event', label: 'Planetárny úkaz' },
  { value: 'other', label: 'Iná udalosť' },
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
const editingEventId = computed(() => Number(editingEvent.value?.id || 0))
const aiEnabled = computed(() => Boolean(aiConfig.value?.events_ai_humanized_enabled))
const aiPanelEnabled = computed(() => aiEnabled.value && editingEventId.value > 0)
const aiTitleEnabled = computed(
  () => Boolean(aiConfig.value?.events_ai_title_postedit_enabled) && editingEventId.value > 0,
)
const aiPanelReady = computed(() => !aiConfigLoading.value && aiConfig.value !== null)
const aiEventLastRun = computed(() => {
  const eventId = editingEventId.value
  if (eventId > 0 && aiLastRunByEvent.value[eventId]) {
    return aiLastRunByEvent.value[eventId]
  }

  return aiConfig.value?.features?.event_description_generate?.last_run || null
})
const aiTitleEventLastRun = computed(() => {
  const eventId = editingEventId.value
  if (eventId > 0 && aiTitleLastRunByEvent.value[eventId]) {
    return aiTitleLastRunByEvent.value[eventId]
  }

  return aiConfig.value?.features?.event_title_postedit?.last_run || null
})

const formErrors = computed(() => {
  const errors = []
  if (!String(form.value.title || '').trim()) {
    errors.push('Názov je povinný.')
  }
  if (!form.value.start_at) {
    errors.push('Čas začiatku je povinný.')
  }

  if (form.value.start_at && form.value.end_at) {
    const start = new Date(form.value.start_at)
    const end = new Date(form.value.end_at)
    if (!Number.isNaN(start.getTime()) && !Number.isNaN(end.getTime()) && end < start) {
      errors.push('Koniec nemôže byť skôr ako začiatok.')
    }
  }

  return errors
})

function normalizeAiStatus(value, fallback = 'idle') {
  const normalized = String(value || '').trim().toLowerCase()
  if (['idle', 'success', 'fallback', 'error'].includes(normalized)) {
    return normalized
  }

  const fallbackNormalized = String(fallback || '').trim().toLowerCase()
  return ['idle', 'success', 'fallback', 'error'].includes(fallbackNormalized)
    ? fallbackNormalized
    : 'idle'
}

async function loadAiConfig(eventId = null) {
  aiConfigLoading.value = true

  try {
    const params = {}
    const normalizedEventId = Number(eventId || 0)
    if (normalizedEventId > 0) {
      params.event_id = normalizedEventId
    }

    const response = await getAdminAiConfig(params)
    aiConfig.value = response?.data?.data || null

    const run = aiConfig.value?.features?.event_description_generate?.last_run
    const titleRun = aiConfig.value?.features?.event_title_postedit?.last_run
    if (normalizedEventId > 0 && run) {
      aiLastRunByEvent.value = {
        ...aiLastRunByEvent.value,
        [normalizedEventId]: run,
      }
    }
    if (normalizedEventId > 0 && titleRun) {
      aiTitleLastRunByEvent.value = {
        ...aiTitleLastRunByEvent.value,
        [normalizedEventId]: titleRun,
      }
    }
    if (run?.status) {
      aiActionStatus.value = normalizeAiStatus(run.status)
    }
    if (titleRun?.status) {
      aiTitleActionStatus.value = normalizeAiStatus(titleRun.status)
    }
  } catch {
    aiConfig.value = null
  } finally {
    aiConfigLoading.value = false
  }
}

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
  aiActionError.value = ''
  aiActionStatus.value = 'idle'
  aiActionResult.value = null
  aiActionNotice.value = ''
  aiActionRawStatus.value = null
  aiUndoSnapshot.value = null
  aiShortDraft.value = ''
  aiTitleActionLoading.value = false
  aiTitleActionError.value = ''
  aiTitleActionStatus.value = 'idle'
  aiTitleActionRawStatus.value = null
  aiTitleActionNotice.value = ''
  aiTitleFallbackUsed.value = false
  aiTitleSuggestion.value = ''
  aiTitleSource.value = ''
  aiTitleUndoSnapshot.value = null
  mode.value = 'create'
  loadAiConfig()
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
  aiActionError.value = ''
  aiActionStatus.value = 'idle'
  aiActionResult.value = null
  aiActionNotice.value = ''
  aiActionRawStatus.value = null
  aiUndoSnapshot.value = null
  aiShortDraft.value = String(event.short || '')
  aiTitleActionLoading.value = false
  aiTitleActionError.value = ''
  aiTitleActionStatus.value = 'idle'
  aiTitleActionRawStatus.value = null
  aiTitleActionNotice.value = ''
  aiTitleFallbackUsed.value = false
  aiTitleSuggestion.value = ''
  aiTitleSource.value = String(event.title || '')
  aiTitleUndoSnapshot.value = null
  mode.value = 'edit'
  loadAiConfig(event?.id)
}

function closeForm() {
  mode.value = 'list'
  formError.value = ''
  formSuccess.value = ''
  aiActionError.value = ''
  aiActionStatus.value = 'idle'
  aiActionResult.value = null
  aiActionNotice.value = ''
  aiActionRawStatus.value = null
  aiUndoSnapshot.value = null
  aiShortDraft.value = ''
  aiTitleActionLoading.value = false
  aiTitleActionError.value = ''
  aiTitleActionStatus.value = 'idle'
  aiTitleActionRawStatus.value = null
  aiTitleActionNotice.value = ''
  aiTitleFallbackUsed.value = false
  aiTitleSuggestion.value = ''
  aiTitleSource.value = ''
  aiTitleUndoSnapshot.value = null
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
      formSuccess.value = 'Udalosť bola upravená.'
    } else {
      await http.post('/admin/events', payload)
      formSuccess.value = 'Udalosť bola vytvorená.'
    }

    await refresh()
    mode.value = 'list'
  } catch (err) {
    formError.value = err?.response?.data?.message || 'Uloženie zlyhalo.'
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
  const approved = await confirm({
    title: 'Spustit retranslate',
    message: 'Naozaj spustit retranslate schvalenych udalosti?',
    confirmText: 'Spustit',
    cancelText: 'Zrusit',
  })
  if (!approved) return
  await requestTranslationBackfill(false)
}

async function runAiSuggestTitle() {
  const eventId = editingEventId.value
  if (aiTitleActionLoading.value || eventId <= 0 || !aiTitleEnabled.value) return

  const sourceTitle = String(form.value.title || '').trim()
  if (!sourceTitle) return

  aiTitleActionLoading.value = true
  aiTitleActionError.value = ''
  aiTitleActionNotice.value = ''
  aiTitleActionRawStatus.value = null
  aiTitleActionStatus.value = 'idle'
  aiTitleFallbackUsed.value = false

  try {
    const response = await postEditAdminEventTitle(eventId, { mode: 'preview' })
    const payload = response?.data || {}
    const status = String(payload.status || '').trim().toLowerCase()
    const suggestion = String(payload.suggested_title_sk || '').trim()
    const lastRun = payload.last_run || null

    if (lastRun) {
      aiTitleLastRunByEvent.value = {
        ...aiTitleLastRunByEvent.value,
        [eventId]: lastRun,
      }
    }

    aiTitleSource.value = sourceTitle
    aiTitleSuggestion.value = suggestion
    aiTitleFallbackUsed.value = Boolean(payload.fallback_used)
    aiTitleActionStatus.value = normalizeAiStatus(
      status,
      payload.fallback_used ? 'fallback' : 'success',
    )
  } catch (err) {
    aiTitleActionStatus.value = 'error'
    aiTitleActionError.value = 'Nepodarilo sa navrhnut nazov.'
    aiTitleActionRawStatus.value = Number(err?.response?.status || 0) || null
  } finally {
    aiTitleActionLoading.value = false
    await loadAiConfig(eventId)
  }
}

function applyAiTitleSuggestion() {
  const suggestion = String(aiTitleSuggestion.value || '').trim()
  if (!suggestion) return

  if (aiTitleUndoSnapshot.value === null) {
    aiTitleUndoSnapshot.value = String(form.value.title || '')
  }

  form.value.title = suggestion
  aiTitleActionNotice.value = 'Nazov aktualizovany.'
}

function undoAiTitleSuggestion() {
  if (aiTitleUndoSnapshot.value === null) return

  form.value.title = aiTitleUndoSnapshot.value
  aiTitleUndoSnapshot.value = null
  aiTitleActionNotice.value = ''
}

async function runAiGenerateDescription() {
  const eventId = editingEventId.value
  if (aiActionLoading.value || eventId <= 0) return

  const previousDescription = String(form.value.description || '')
  const previousShort = String(aiShortDraft.value || '')

  aiActionLoading.value = true
  aiActionError.value = ''
  aiActionNotice.value = ''
  aiActionRawStatus.value = null
  aiActionStatus.value = 'idle'
  aiActionResult.value = null

  try {
    const response = await generateAdminEventDescription(eventId, {
      sync: true,
      mode: 'ollama',
      fallback: 'base',
      force: true,
    })

    const data = response?.data?.data || {}
    const lastRun = response?.data?.last_run || null
    if (lastRun) {
      aiLastRunByEvent.value = {
        ...aiLastRunByEvent.value,
        [eventId]: lastRun,
      }
    }

    aiActionResult.value = {
      description: String(data.description || '').trim(),
      short: String(data.short || '').trim(),
      fallbackUsed: Boolean(data.fallback_used),
    }
    aiActionStatus.value = normalizeAiStatus(
      lastRun?.status,
      data.fallback_used ? 'fallback' : 'success',
    )

    if (aiActionResult.value.description) {
      form.value.description = aiActionResult.value.description
    }
    if (aiActionResult.value.short) {
      aiShortDraft.value = aiActionResult.value.short
    }

    aiUndoSnapshot.value = {
      description: previousDescription,
      short: previousShort,
    }
    aiActionNotice.value = 'Opis aktualizovany.'

    await refresh()
  } catch (err) {
    aiActionStatus.value = 'error'
    aiActionError.value = 'Nepodarilo sa vylepsit opis.'
    aiActionRawStatus.value = Number(err?.response?.status || 0) || null
  } finally {
    aiActionLoading.value = false
    await loadAiConfig(eventId)
  }
}

function undoAiDescription() {
  if (!aiUndoSnapshot.value) return

  form.value.description = aiUndoSnapshot.value.description
  aiShortDraft.value = aiUndoSnapshot.value.short
  aiActionResult.value = {
    description: aiUndoSnapshot.value.description,
    short: aiUndoSnapshot.value.short,
    fallbackUsed: false,
  }
  aiActionNotice.value = ''
  aiUndoSnapshot.value = null
}

onMounted(() => {
  loadAiConfig()
})
</script>

<template>
  <div class="eventsView">
    <section class="panel headerPanel">
      <div>
        <h1>Udalosti</h1>
        <p>Kompaktný prehľad publikovaných a manuálnych udalostí.</p>
      </div>
      <div class="toolbar">
        <button class="btn ghost" :disabled="translationActionLoading" @click="previewTranslationBackfill">Náhľad retranslate</button>
        <button class="btn ghost" :disabled="translationActionLoading" @click="runTranslationBackfill">Spustiť retranslate</button>
        <button class="btn primary" @click="openCreate">Nová udalosť</button>
      </div>
    </section>

    <section v-if="translationError" class="notice noticeError">{{ translationError }}</section>
    <section v-else-if="translationSummary" class="notice noticeOk">
      Kandidáti: {{ translationSummary.summary?.total_candidates ?? 0 }} |
      Preložené: {{ translationSummary.summary?.translated ?? 0 }} |
      Zlyhalo: {{ translationSummary.summary?.failed ?? 0 }} |
      Aktualizované eventy: {{ translationSummary.summary?.events_updated ?? 0 }} |
      Dry run: {{ translationSummary.summary?.dry_run ? 'áno' : 'nie' }}
    </section>

    <section v-if="mode !== 'list'" class="panel formPanel">
      <div class="formHead">
        <div>
          <h2>{{ isEdit ? 'Upraviť udalosť' : 'Vytvoriť udalosť' }}</h2>
          <p>{{ isEdit ? 'Upravíš existujúci záznam.' : 'Vytvoríš novú manuálnu udalosť.' }}</p>
        </div>
        <div class="quickBtns">
          <button class="btn tiny" type="button" @click="setStartNow">Začiatok teraz</button>
          <button class="btn tiny" type="button" @click="setEndAfter(1)">Koniec +1h</button>
          <button class="btn tiny" type="button" @click="setEndAfter(2)">Koniec +2h</button>
        </div>
      </div>

      <AdminAiActionPanel
        v-if="aiPanelReady"
        title="AI: Zlepšiť názov (SK)"
        description="Navrhne prirodzenejsi nazov bez pridavania novych faktov."
        action-label="Navrhnúť"
        :enabled="aiTitleEnabled"
        :status="aiTitleActionStatus"
        :latency-ms="aiTitleEventLastRun?.latency_ms ?? null"
        :last-run-at="aiTitleEventLastRun?.updated_at ?? null"
        :retry-count="aiTitleEventLastRun?.retry_count ?? null"
        :raw-status-code="aiTitleActionRawStatus"
        :is-loading="aiTitleActionLoading"
        :error-message="aiTitleActionError"
        @run="runAiSuggestTitle"
      >
        <p v-if="editingEventId <= 0" class="aiHint">
          Najprv uloz udalost, potom mozes navrhnut nazov.
        </p>
        <template v-else>
          <div v-if="aiTitleActionNotice" class="aiNoticeRow">
            <span class="aiNotice">{{ aiTitleActionNotice }}</span>
            <button
              v-if="aiTitleUndoSnapshot !== null"
              type="button"
              class="aiUndoBtn"
              @click="undoAiTitleSuggestion"
            >
              Undo
            </button>
          </div>
          <span
            v-if="aiTitleActionStatus === 'fallback' || aiTitleFallbackUsed"
            class="aiBadge aiBadge--fallback"
          >
            Použitý fallback
          </span>
          <div v-if="aiTitleSuggestion" class="aiCompareBlock">
            <p class="aiHint"><strong>Pôvodný:</strong> {{ aiTitleSource || '-' }}</p>
            <p class="aiHint"><strong>Návrh:</strong> {{ aiTitleSuggestion }}</p>
            <button
              type="button"
              data-testid="events-unified-title-apply-btn"
              class="btn tiny"
              @click="applyAiTitleSuggestion"
            >
              Použiť
            </button>
          </div>
        </template>
      </AdminAiActionPanel>
      <AdminAiActionPanel
        v-if="aiPanelReady"
        style="margin-top:10px;"
        title="AI pomocnik"
        description="Vylepsi opis udalosti bez zobrazenia internych detailov."
        action-label="Vylepšiť opis"
        :enabled="aiPanelEnabled"
        :status="aiActionStatus"
        :latency-ms="aiEventLastRun?.latency_ms ?? null"
        :last-run-at="aiEventLastRun?.updated_at ?? null"
        :retry-count="aiEventLastRun?.retry_count ?? null"
        :raw-status-code="aiActionRawStatus"
        :is-loading="aiActionLoading"
        :error-message="aiActionError"
        @run="runAiGenerateDescription"
      >
        <p v-if="editingEventId <= 0" class="aiHint">
          Najprv uloz udalost, potom mozes vylepsit opis.
        </p>
        <template v-else>
          <div v-if="aiActionNotice" class="aiNoticeRow">
            <span class="aiNotice">Opis aktualizovany.</span>
            <button
              v-if="aiUndoSnapshot"
              type="button"
              class="aiUndoBtn"
              @click="undoAiDescription"
            >
              Undo
            </button>
          </div>
          <span v-if="aiActionStatus === 'fallback'" class="aiBadge aiBadge--fallback">Použitý fallback</span>
          <p class="aiHint"><strong>Kratky opis:</strong> {{ aiShortDraft || '-' }}</p>
          <p class="aiHint"><strong>Opis:</strong> {{ form.description || aiActionResult?.description || '-' }}</p>
        </template>
      </AdminAiActionPanel>
      <p v-else class="aiHint">
        Načítavam AI konfiguráciu...
      </p>

      <div v-if="formError" class="notice noticeError">{{ formError }}</div>
      <div v-if="formSuccess" class="notice noticeOk">{{ formSuccess }}</div>

      <form class="formGrid" @submit.prevent="submitForm">
        <label class="field fieldWide">
          <span>Názov *</span>
          <input v-model="form.title" type="text" :disabled="formLoading" />
        </label>

        <label class="field">
          <span>Typ *</span>
          <select v-model="form.type" :disabled="formLoading">
            <option v-for="item in eventTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
        </label>

        <label class="field">
          <span>Viditeľnosť</span>
          <select v-model.number="form.visibility" :disabled="formLoading">
            <option :value="1">Verejné</option>
            <option :value="0">Skryté</option>
          </select>
        </label>

        <label class="field">
          <span>Začiatok *</span>
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
          <button type="button" class="btn ghost" :disabled="formLoading" @click="closeForm">Zrušiť</button>
          <button type="submit" class="btn primary" :disabled="formLoading || formErrors.length > 0">
            {{ formLoading ? 'Ukladá sa...' : (isEdit ? 'Uložiť zmeny' : 'Vytvoriť') }}
          </button>
        </div>
      </form>
    </section>

    <section class="panel listPanel">
      <div class="listTop">
        <div class="meta">
          <strong>Prehľad</strong>
          <span v-if="pagination">Strana {{ pagination.currentPage }} / {{ pagination.lastPage }} (spolu {{ pagination.total }})</span>
        </div>
        <label class="perPage">
          <span>Na stránku</span>
          <select :value="perPage" @change="setPerPage(Number($event.target.value))">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </label>
      </div>

      <div v-if="error" class="notice noticeError">
        {{ error }}
        <button class="btn tiny" @click="refresh">Skúsiť znova</button>
      </div>

      <div v-else-if="loading" class="loading">Načítavam udalosti...</div>

      <div v-else-if="data" class="tableWrap">
        <table class="compactTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Názov</th>
              <th>Typ</th>
              <th>Začiatok</th>
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
                  {{ event.visibility === 1 ? 'verejné' : 'skryté' }}
                </span>
              </td>
              <td class="right">
                <button class="btn tiny" @click="openEdit(event)">Upraviť</button>
              </td>
            </tr>
            <tr v-if="data.data.length === 0">
              <td colspan="6" class="empty">Žiadne udalosti.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination" class="pager">
        <button class="btn ghost" :disabled="!hasPrevPage" @click="prevPage">Pred</button>
        <button class="btn ghost" :disabled="!hasNextPage" @click="nextPage">Ďalšia</button>
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

.aiHint {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.aiCompareBlock {
  display: grid;
  gap: 6px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 8px;
}

.aiNoticeRow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.aiNotice {
  font-size: 12px;
  color: rgb(22 101 52);
}

.aiUndoBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  background: transparent;
  color: inherit;
  padding: 3px 9px;
  font-size: 11px;
}

.aiBadge {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  padding: 2px 8px;
  font-size: 11px;
}

.aiBadge--fallback {
  border-color: rgb(245 158 11 / 0.42);
  background: rgb(245 158 11 / 0.12);
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


