<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import api from '@/services/api'
import {
  generateAdminEventDescription,
  getAdminAiConfig,
} from '@/services/api/admin/ai'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const error = ref('')
const success = ref('')
const aiConfig = ref(null)
const aiConfigLoading = ref(false)
const aiStatus = ref('idle')
const aiLoading = ref(false)
const aiError = ref('')
const aiLastRun = ref(null)
const aiResult = ref(null)
const aiNotice = ref('')
const aiRawStatusCode = ref(null)
const aiUndoSnapshot = ref(null)
const aiShortDraft = ref('')
const aiProgressPercent = ref(null)
const descriptionPreviewRef = ref(null)
let aiProgressIntervalId = null
let aiProgressResetTimeoutId = null
let aiProgressStartedAt = 0

const isEdit = computed(() => typeof route.params.id !== 'undefined')
const eventId = computed(() => Number(route.params.id))
const aiEnabled = computed(() => Boolean(aiConfig.value?.events_ai_humanized_enabled) && eventId.value > 0)
const aiPanelReady = computed(() => !aiConfigLoading.value && aiConfig.value !== null)

const types = [
  { value: 'meteor_shower', label: 'Meteory' },
  { value: 'eclipse_lunar', label: 'Zatmenie (L)' },
  { value: 'eclipse_solar', label: 'Zatmenie (S)' },
  { value: 'planetary_event', label: 'Konjunkcia' },
  { value: 'aurora', label: 'Polárna žiara' },
  { value: 'other', label: 'Iné' },
]

const form = reactive({
  title: '',
  description: '',
  type: 'meteor_shower',
  start_at: '',
  end_at: '',
  visibility: 1,
})

function toLocalInput(value) {
  if (!value) return ''
  const d = new Date(value)
  if (isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

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

function clearAiProgressTimers() {
  if (aiProgressIntervalId !== null) {
    clearInterval(aiProgressIntervalId)
    aiProgressIntervalId = null
  }

  if (aiProgressResetTimeoutId !== null) {
    clearTimeout(aiProgressResetTimeoutId)
    aiProgressResetTimeoutId = null
  }
}

function startAiProgressBar() {
  clearAiProgressTimers()
  aiProgressStartedAt = Date.now()
  aiProgressPercent.value = 6

  aiProgressIntervalId = setInterval(() => {
    const elapsedMs = Math.max(0, Date.now() - aiProgressStartedAt)
    const easedValue = 6 + (1 - Math.exp(-elapsedMs / 22_000)) * 84
    const nextValue = Math.round(easedValue)
    const currentValue = Number(aiProgressPercent.value || 0)

    aiProgressPercent.value = Math.min(90, Math.max(currentValue, nextValue))
  }, 320)
}

function stopAiProgressBar(success) {
  clearAiProgressTimers()

  if (success) {
    aiProgressPercent.value = 100
  }

  aiProgressResetTimeoutId = setTimeout(() => {
    aiProgressPercent.value = null
    aiProgressResetTimeoutId = null
  }, success ? 550 : 250)
}

async function focusDescriptionPreview() {
  await nextTick()
  if (descriptionPreviewRef.value?.scrollIntoView) {
    descriptionPreviewRef.value.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
  }
}

async function loadAiConfig() {
  aiConfigLoading.value = true

  try {
    const params = {}
    if (eventId.value > 0) {
      params.event_id = eventId.value
    }

    const response = await getAdminAiConfig(params)
    aiConfig.value = response?.data?.data || null
    aiLastRun.value = aiConfig.value?.features?.event_description_generate?.last_run || aiLastRun.value
    if (aiLastRun.value?.status) {
      aiStatus.value = normalizeAiStatus(aiLastRun.value.status)
    }
  } catch {
    aiConfig.value = null
  } finally {
    aiConfigLoading.value = false
  }
}

async function loadEvent() {
  if (!isEdit.value) return
  loading.value = true
  error.value = ''

  try {
    const res = await api.get(`/admin/events/${eventId.value}`)
    const ev = res.data?.data ?? res.data

    form.title = ev?.title || ''
    form.description = ev?.description || ''
    aiShortDraft.value = String(ev?.short || '')
    form.type = ev?.type || 'meteor_shower'
    form.start_at = toLocalInput(ev?.start_at || ev?.starts_at || ev?.max_at)
    form.end_at = toLocalInput(ev?.end_at || ev?.ends_at)
    form.visibility = typeof ev?.visibility === 'number' ? ev.visibility : 1
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať udalosť.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  loading.value = true
  error.value = ''
  success.value = ''

  const payload = {
    title: form.title,
    description: form.description || null,
    type: form.type,
    start_at: form.start_at,
    end_at: form.end_at || null,
    visibility: form.visibility,
  }

  try {
    if (isEdit.value) {
      await api.put(`/admin/events/${eventId.value}`, payload)
      success.value = 'Udalosť bola upravená.'
    } else {
      await api.post('/admin/events', payload)
      success.value = 'Udalosť bola vytvorená.'
    }

    window.setTimeout(() => {
      router.push('/admin/events')
    }, 600)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Uloženie zlyhalo.'
  } finally {
    loading.value = false
  }
}

async function runAiGenerateDescription() {
  await runAiDescription({ dryRun: false, mode: 'ollama' })
}

async function runAiPreviewDescription() {
  await runAiDescription({ dryRun: true, mode: 'ollama' })
}

async function runTemplateDescription() {
  await runAiDescription({ dryRun: false, mode: 'template' })
}

async function runAiDescription({ dryRun = false, mode = 'ollama' } = {}) {
  if (aiLoading.value || !aiEnabled.value) return
  const selectedMode = String(mode || '').trim().toLowerCase() === 'template' ? 'template' : 'ollama'
  const modeLabel = selectedMode === 'template' ? 'Šablóna' : 'AI popis'

  const previousDescription = String(form.description || '')
  const previousShort = String(aiShortDraft.value || '')

  aiLoading.value = true
  aiError.value = ''
  aiNotice.value = ''
  aiRawStatusCode.value = null
  aiStatus.value = 'idle'
  aiResult.value = null
  startAiProgressBar()

  try {
    const payload = {
      sync: true,
      mode: selectedMode,
      fallback: dryRun ? 'skip' : 'base',
      force: !dryRun,
    }
    if (dryRun) {
      payload.dry_run = true
    }

    const response = await generateAdminEventDescription(eventId.value, payload)

    const data = response?.data?.data || {}
    aiLastRun.value = response?.data?.last_run || aiLastRun.value
    aiStatus.value = normalizeAiStatus(
      aiLastRun.value?.status,
      data.fallback_used ? 'fallback' : 'success',
    )
    aiResult.value = {
      description: String(data.description || '').trim(),
      short: String(data.short || '').trim(),
      fallbackUsed: Boolean(data.fallback_used),
    }

    if (!dryRun && aiResult.value.description) {
      form.description = aiResult.value.description
    }
    if (!dryRun && aiResult.value.short) {
      aiShortDraft.value = aiResult.value.short
    }

    if (!dryRun) {
      aiUndoSnapshot.value = {
        description: previousDescription,
        short: previousShort,
      }
      aiNotice.value = `${modeLabel} bol aplikovaný.`
    } else {
      aiUndoSnapshot.value = null
      aiNotice.value = 'Návrh pripravený (bez uloženia).'
    }

    await focusDescriptionPreview()
  } catch (e) {
    const backendMessage = String(e?.response?.data?.message || '').trim()
    const normalizedUserMessage = String(e?.userMessage || '').trim()
    const normalizedTransportMessage = String(e?.message || '').toLowerCase()
    const isTimeout = e?.code === 'ECONNABORTED' || normalizedTransportMessage.includes('timeout')

    aiStatus.value = 'error'
    if (backendMessage !== '') {
      aiError.value = backendMessage
    } else if (isTimeout) {
      aiError.value = 'AI generovanie trvá dlhšie ako limit klienta. Skús to znova o chvíľu.'
    } else if (normalizedUserMessage !== '') {
      aiError.value = normalizedUserMessage
    } else {
      aiError.value = 'Nepodarilo sa vylepšiť opis.'
    }
    aiRawStatusCode.value = Number(e?.response?.status || 0) || null
  } finally {
    aiLoading.value = false
    stopAiProgressBar(aiStatus.value !== 'error')
    await loadAiConfig()
  }
}

async function undoAiDescription() {
  if (!aiUndoSnapshot.value || aiLoading.value || eventId.value <= 0) return

  const snapshot = {
    description: String(aiUndoSnapshot.value.description || ''),
    short: String(aiUndoSnapshot.value.short || ''),
  }

  aiLoading.value = true
  aiError.value = ''
  aiRawStatusCode.value = null

  try {
    await api.put(`/admin/events/${eventId.value}`, {
      title: form.title,
      description: snapshot.description !== '' ? snapshot.description : null,
      short: snapshot.short !== '' ? snapshot.short : null,
      type: form.type,
      start_at: form.start_at,
      end_at: form.end_at || null,
      visibility: form.visibility,
    })

    form.description = snapshot.description
    aiShortDraft.value = snapshot.short
    aiStatus.value = 'success'
    aiNotice.value = 'Opis bol vrátený.'
  } catch (e) {
    aiStatus.value = 'error'
    aiError.value = String(e?.response?.data?.message || 'Vrátenie pôvodného opisu zlyhalo.')
    aiRawStatusCode.value = Number(e?.response?.status || 0) || null
    return
  } finally {
    aiLoading.value = false
    await loadAiConfig()
  }

  aiResult.value = {
    description: snapshot.description,
    short: snapshot.short,
    fallbackUsed: false,
  }
  aiUndoSnapshot.value = null
}

onMounted(async () => {
  await Promise.all([loadEvent(), loadAiConfig()])
})

onBeforeUnmount(() => {
  clearAiProgressTimers()
})
</script>
<template>
  <div class="eventFormPage">
    <div class="eventFormHeader">
      <div>
        <h1 class="eventFormTitle">{{ isEdit ? 'Upraviť udalosť' : 'Vytvoriť udalosť' }}</h1>
        <div class="eventFormSubtitle">
          {{ isEdit ? 'Uprav existujúce údaje udalosti.' : 'Pridaj manuálnu udalosť.' }}
        </div>
      </div>
    </div>

    <div v-if="error" class="eventFormMessage eventFormMessage--error">
      {{ error }}
    </div>
    <div v-if="success" class="eventFormMessage eventFormMessage--success">
      {{ success }}
    </div>

    <div class="eventFormCard">
      <template v-if="aiPanelReady">
        <AdminAiActionPanel
          class="aiPanel aiPanelSecondary"
          title="AI asistent"
          description="Minimálny flow: otestuj návrh, potom použi návrh."
          action-label="Použiť návrh"
          :enabled="aiEnabled"
          :status="aiStatus"
          :latency-ms="aiLastRun?.latency_ms ?? null"
          :last-run-at="aiLastRun?.updated_at ?? null"
          :retry-count="aiLastRun?.retry_count ?? null"
          :raw-status-code="aiRawStatusCode"
          :is-loading="aiLoading"
          :progress-percent="aiProgressPercent"
          :error-message="aiError"
          @run="runAiGenerateDescription"
        >
          <p v-if="!isEdit" class="aiPanelHint">
            Panel sa aktivuje po uložení eventu.
          </p>
          <template v-else>
            <div class="aiNoticeRow">
              <button
                type="button"
                @click="runAiPreviewDescription"
                class="aiInlineBtn"
                :disabled="aiLoading || !aiEnabled"
              >
                Otestovať návrh
              </button>
              <button
                type="button"
                @click="runTemplateDescription"
                class="aiInlineBtn"
                :disabled="aiLoading || !aiEnabled"
              >
                Použiť šablónu
              </button>
            </div>
            <p class="aiPanelHint">Krok 1: otestuj návrh. Krok 2: použi návrh.</p>
            <div
              v-if="aiNotice"
              class="aiNoticeRow"
            >
              <span class="aiNoticeText">{{ aiNotice }}</span>
              <button
                v-if="aiUndoSnapshot"
                type="button"
                @click="undoAiDescription"
                class="aiInlineBtn"
                :disabled="aiLoading || !aiUndoSnapshot"
              >
                Vrátiť
              </button>
            </div>
            <span
              v-if="aiStatus === 'fallback'"
              class="aiFallbackBadge"
            >
              Použitý bezpečný fallback
            </span>
            <div class="aiProposalCard">
              <p class="aiProposalLine"><strong>Krátky opis:</strong> {{ aiResult?.short || aiShortDraft || '-' }}</p>
              <p class="aiProposalLine"><strong>AI návrh:</strong> {{ aiResult?.description || '-' }}</p>
            </div>
          </template>
        </AdminAiActionPanel>
      </template>
      <p v-else class="aiPanelHint">
        Načítavam AI konfiguráciu...
      </p>

      <div class="eventFormFields">
        <label class="fieldBlock">
          <div class="fieldLabel">Názov</div>
          <input v-model="form.title" type="text" :disabled="loading" class="fieldControl" />
        </label>

        <label ref="descriptionPreviewRef" class="fieldBlock">
          <div class="fieldLabel">Popis</div>
          <textarea v-model="form.description" rows="4" :disabled="loading" class="fieldControl fieldControlTextarea"></textarea>
        </label>

        <div class="twoColGrid">
          <label class="fieldBlock">
            <div class="fieldLabel">Typ udalosti</div>
            <select v-model="form.type" :disabled="loading" class="fieldControl">
              <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </label>

          <label class="fieldBlock">
            <div class="fieldLabel">Viditeľnosť</div>
            <select v-model.number="form.visibility" :disabled="loading" class="fieldControl">
              <option :value="1">Verejné</option>
              <option :value="0">Skryté</option>
            </select>
          </label>
        </div>

        <div class="twoColGrid">
          <label class="fieldBlock">
            <div class="fieldLabel">Začína o</div>
            <input v-model="form.start_at" type="datetime-local" :disabled="loading" class="fieldControl" />
          </label>
          <label class="fieldBlock">
            <div class="fieldLabel">Končí o (voliteľné)</div>
            <input v-model="form.end_at" type="datetime-local" :disabled="loading" class="fieldControl" />
          </label>
        </div>

        <div class="formActions">
          <button
            @click="submit"
            :disabled="loading || aiLoading"
            class="saveBtn"
          >
            {{ aiLoading ? 'AI generuje opis...' : (loading ? 'Ukladám...' : 'Uložiť') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.eventFormPage {
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
  padding: 0;
  display: grid;
  gap: 10px;
  min-width: 0;
}

.eventFormHeader {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 11px;
  background: rgb(var(--color-bg-rgb) / 0.84);
  padding: 10px;
}

.eventFormTitle {
  margin: 0;
  font-size: 1.15rem;
  line-height: 1.15;
}

.eventFormSubtitle {
  margin-top: 3px;
  opacity: 0.82;
  font-size: 12px;
}

.eventFormMessage {
  margin-top: 0.2rem;
  font-size: 0.85rem;
}

.eventFormMessage--error {
  color: var(--color-danger);
}

.eventFormMessage--success {
  color: var(--color-success);
}

.eventFormCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 11px;
  background: rgb(var(--color-bg-rgb) / 0.84);
  padding: 10px;
}

.aiPanelSecondary {
  margin-top: 0.65rem;
}

.aiPanelHint {
  margin: 0;
  font-size: 0.74rem;
  opacity: 0.85;
}

.aiNoticeRow {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.aiNoticeText {
  font-size: 0.74rem;
  color: rgb(22 101 52);
}

.aiInlineBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  padding: 0.18rem 0.5rem;
  background: transparent;
  color: inherit;
  font-size: 0.68rem;
}

.aiFallbackBadge {
  display: inline-flex;
  border-radius: 999px;
  border: 1px solid rgb(245 158 11 / 0.45);
  background: rgb(245 158 11 / 0.12);
  padding: 0.15rem 0.5rem;
  font-size: 0.68rem;
}

.aiProposalCard {
  display: grid;
  gap: 0.35rem;
  padding: 0.5rem;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 10px;
}

.aiProposalLine {
  margin: 0;
  font-size: 0.74rem;
  opacity: 0.9;
}

.aiApplyBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  border-radius: 10px;
  padding: 0.35rem 0.65rem;
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: inherit;
  font-size: 0.74rem;
}

.eventFormFields {
  margin-top: 0.7rem;
  display: grid;
  gap: 0.65rem;
}

.fieldBlock {
  display: block;
}

.fieldLabel {
  font-size: 0.74rem;
  opacity: 0.8;
  margin-bottom: 0.35rem;
}

.fieldControl {
  width: 100%;
  min-height: 36px;
  padding: 0.5rem 0.6rem;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: transparent;
  color: inherit;
}

.fieldControlTextarea {
  min-height: 88px;
  resize: vertical;
}

.twoColGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.formActions {
  display: flex;
  gap: 0.6rem;
  justify-content: flex-end;
  margin-top: 0.15rem;
}

.saveBtn {
  min-height: 36px;
  padding: 0.45rem 0.75rem;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: rgb(var(--color-surface-rgb) / 0.08);
  color: inherit;
  font-size: 0.83rem;
}

.saveBtn:disabled,
.aiApplyBtn:disabled,
.aiInlineBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 760px) {
  .eventFormPage {
    padding: 0;
  }

  .twoColGrid {
    grid-template-columns: 1fr;
  }

  .formActions {
    justify-content: stretch;
  }

  .saveBtn {
    width: 100%;
  }
}
</style>



