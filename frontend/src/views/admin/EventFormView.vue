<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import api from '@/services/api'
import {
  generateAdminEventDescription,
  getAdminAiConfig,
  postEditAdminEventTitle,
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
const aiTitleStatus = ref('idle')
const aiTitleLoading = ref(false)
const aiTitleError = ref('')
const aiTitleLastRun = ref(null)
const aiTitleRawStatusCode = ref(null)
const aiTitleSuggestion = ref('')
const aiTitleSource = ref('')
const aiTitleNotice = ref('')
const aiTitleFallbackUsed = ref(false)
const aiTitleUndoSnapshot = ref(null)
const descriptionPreviewRef = ref(null)

const isEdit = computed(() => typeof route.params.id !== 'undefined')
const eventId = computed(() => Number(route.params.id))
const aiEnabled = computed(() => Boolean(aiConfig.value?.events_ai_humanized_enabled) && eventId.value > 0)
const aiTitleEnabled = computed(
  () => Boolean(aiConfig.value?.events_ai_title_postedit_enabled) && eventId.value > 0,
)
const aiPanelReady = computed(() => !aiConfigLoading.value && aiConfig.value !== null)

const types = [
  { value: 'meteor_shower', label: 'Meteory' },
  { value: 'eclipse_lunar', label: 'Zatmenie (L)' },
  { value: 'eclipse_solar', label: 'Zatmenie (S)' },
  { value: 'planetary_event', label: 'Konjunkcia' },
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
    aiTitleLastRun.value = aiConfig.value?.features?.event_title_postedit?.last_run || aiTitleLastRun.value
    if (aiLastRun.value?.status) {
      aiStatus.value = normalizeAiStatus(aiLastRun.value.status)
    }
    if (aiTitleLastRun.value?.status) {
      aiTitleStatus.value = normalizeAiStatus(aiTitleLastRun.value.status)
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
    aiTitleSource.value = String(form.title || '')
    aiTitleSuggestion.value = ''
    aiTitleNotice.value = ''
    aiTitleFallbackUsed.value = false
    aiTitleUndoSnapshot.value = null
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

async function runAiSuggestTitle() {
  if (aiTitleLoading.value || !aiTitleEnabled.value) return

  const sourceTitle = String(form.title || '').trim()
  if (!sourceTitle) return

  aiTitleLoading.value = true
  aiTitleError.value = ''
  aiTitleNotice.value = ''
  aiTitleRawStatusCode.value = null
  aiTitleStatus.value = 'idle'
  aiTitleFallbackUsed.value = false

  try {
    const response = await postEditAdminEventTitle(eventId.value, { mode: 'preview' })
    const payload = response?.data || {}
    const status = String(payload.status || '').trim().toLowerCase()
    const suggestedTitle = String(payload.suggested_title_sk || '').trim()

    aiTitleLastRun.value = payload.last_run || aiTitleLastRun.value
    aiTitleStatus.value = normalizeAiStatus(status, payload.fallback_used ? 'fallback' : 'success')
    aiTitleSource.value = sourceTitle
    aiTitleSuggestion.value = suggestedTitle
    aiTitleFallbackUsed.value = Boolean(payload.fallback_used)
  } catch (e) {
    aiTitleStatus.value = 'error'
    aiTitleError.value = 'Nepodarilo sa navrhnúť názov.'
    aiTitleRawStatusCode.value = Number(e?.response?.status || 0) || null
  } finally {
    aiTitleLoading.value = false
    await loadAiConfig()
  }
}

function applyAiTitleSuggestion() {
  const suggestion = String(aiTitleSuggestion.value || '').trim()
  if (!suggestion) return

  if (aiTitleUndoSnapshot.value === null) {
    aiTitleUndoSnapshot.value = String(form.title || '')
  }

  form.title = suggestion
  aiTitleNotice.value = 'Názov aktualizovaný.'
}

function undoAiTitleSuggestion() {
  if (aiTitleUndoSnapshot.value === null) return

  form.title = aiTitleUndoSnapshot.value
  aiTitleUndoSnapshot.value = null
  aiTitleNotice.value = ''
}

async function runAiGenerateDescription() {
  if (aiLoading.value || !aiEnabled.value) return

  const previousDescription = String(form.description || '')
  const previousShort = String(aiShortDraft.value || '')

  aiLoading.value = true
  aiError.value = ''
  aiNotice.value = ''
  aiRawStatusCode.value = null
  aiStatus.value = 'idle'
  aiResult.value = null

  try {
    const response = await generateAdminEventDescription(eventId.value, {
      sync: true,
      mode: 'ollama',
      fallback: 'base',
      force: true,
    })

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

    if (aiResult.value.description) {
      form.description = aiResult.value.description
    }
    if (aiResult.value.short) {
      aiShortDraft.value = aiResult.value.short
    }

    aiUndoSnapshot.value = {
      description: previousDescription,
      short: previousShort,
    }
    aiNotice.value = 'Opis aktualizovaný.'

    await focusDescriptionPreview()
  } catch (e) {
    aiStatus.value = 'error'
    aiError.value = 'Nepodarilo sa vylepšiť opis.'
    aiRawStatusCode.value = Number(e?.response?.status || 0) || null
  } finally {
    aiLoading.value = false
    await loadAiConfig()
  }
}

function undoAiDescription() {
  if (!aiUndoSnapshot.value) return

  form.description = aiUndoSnapshot.value.description
  aiShortDraft.value = aiUndoSnapshot.value.short
  aiResult.value = {
    description: aiUndoSnapshot.value.description,
    short: aiUndoSnapshot.value.short,
    fallbackUsed: false,
  }
  aiNotice.value = ''
  aiUndoSnapshot.value = null
}

onMounted(async () => {
  await Promise.all([loadEvent(), loadAiConfig()])
})
</script>

<template>
  <div style="max-width: 880px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
      <div>
        <h1 style="margin:0 0 6px;">{{ isEdit ? 'Upraviť udalosť' : 'Vytvoriť udalosť' }}</h1>
        <div style="opacity:.8; font-size: 14px;">
          {{ isEdit ? 'Uprav existujúce údaje udalosti.' : 'Pridaj manuálnu udalosť.' }}
        </div>
      </div>
    </div>

    <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
      {{ error }}
    </div>
    <div v-if="success" style="margin-top: 12px; color: var(--color-success);">
      {{ success }}
    </div>

    <div style="margin-top: 16px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px; padding: 16px;">
      <template v-if="aiPanelReady">
        <AdminAiActionPanel
          title="AI: Zlepšiť názov (SK)"
          description="Navrhne prirodzenejší slovenský názov bez pridávania nových faktov."
          action-label="Navrhnúť"
          :enabled="aiTitleEnabled"
          :status="aiTitleStatus"
          :latency-ms="aiTitleLastRun?.latency_ms ?? null"
          :last-run-at="aiTitleLastRun?.updated_at ?? null"
          :retry-count="aiTitleLastRun?.retry_count ?? null"
          :raw-status-code="aiTitleRawStatusCode"
          :is-loading="aiTitleLoading"
          :error-message="aiTitleError"
          @run="runAiSuggestTitle"
        >
          <p v-if="!isEdit" style="margin:0; font-size:12px; opacity:.85;">
            Panel sa aktivuje po uložení eventu.
          </p>
          <template v-else>
            <div
              v-if="aiTitleNotice"
              style="display:inline-flex; align-items:center; gap:8px; flex-wrap:wrap;"
            >
              <span style="font-size:12px; color:rgb(22 101 52);">{{ aiTitleNotice }}</span>
              <button
                v-if="aiTitleUndoSnapshot !== null"
                type="button"
                @click="undoAiTitleSuggestion"
                style="border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:999px; padding:3px 9px; background:transparent; color:inherit; font-size:11px;"
              >
                Undo
              </button>
            </div>
            <span
              v-if="aiTitleStatus === 'fallback' || aiTitleFallbackUsed"
              style="display:inline-flex; border-radius:999px; border:1px solid rgb(245 158 11 / .45); background:rgb(245 158 11 / .12); padding:2px 8px; font-size:11px;"
            >
              Použitý fallback
            </span>
            <div
              v-if="aiTitleSuggestion"
              style="display:grid; gap:6px; padding:8px; border:1px solid rgb(var(--color-surface-rgb) / .16); border-radius:10px;"
            >
              <p style="margin:0; font-size:12px; opacity:.9;"><strong>Pôvodný:</strong> {{ aiTitleSource || '-' }}</p>
              <p style="margin:0; font-size:12px; opacity:.9;"><strong>Návrh:</strong> {{ aiTitleSuggestion }}</p>
              <div>
                <button
                  type="button"
                  data-testid="event-title-apply-btn"
                  @click="applyAiTitleSuggestion"
                  style="border:1px solid rgb(var(--color-primary-rgb) / .35); border-radius:10px; padding:6px 10px; background:rgb(var(--color-primary-rgb) / .12); color:inherit; font-size:12px;"
                >
                  Použiť
                </button>
              </div>
            </div>
          </template>
        </AdminAiActionPanel>

        <AdminAiActionPanel
          style="margin-top:12px;"
          title="AI pomocník"
          description="Vylepší opis aktuálnej udalosti."
          action-label="Vylepšiť opis"
          :enabled="aiEnabled"
          :status="aiStatus"
          :latency-ms="aiLastRun?.latency_ms ?? null"
          :last-run-at="aiLastRun?.updated_at ?? null"
          :retry-count="aiLastRun?.retry_count ?? null"
          :raw-status-code="aiRawStatusCode"
          :is-loading="aiLoading"
          :error-message="aiError"
          @run="runAiGenerateDescription"
        >
          <p v-if="!isEdit" style="margin:0; font-size:12px; opacity:.85;">
            Panel sa aktivuje po uložení eventu.
          </p>
          <template v-else>
            <div
              v-if="aiNotice"
              style="display:inline-flex; align-items:center; gap:8px; flex-wrap:wrap;"
            >
              <span style="font-size:12px; color:rgb(22 101 52);">Opis aktualizovaný.</span>
              <button
                v-if="aiUndoSnapshot"
                type="button"
                @click="undoAiDescription"
                style="border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:999px; padding:3px 9px; background:transparent; color:inherit; font-size:11px;"
              >
                Undo
              </button>
            </div>
            <span
              v-if="aiStatus === 'fallback'"
              style="display:inline-flex; border-radius:999px; border:1px solid rgb(245 158 11 / .45); background:rgb(245 158 11 / .12); padding:2px 8px; font-size:11px;"
            >
              Použitý fallback
            </span>
            <p style="margin:0; font-size:12px; opacity:.9;"><strong>Krátky opis:</strong> {{ aiShortDraft || '-' }}</p>
          </template>
        </AdminAiActionPanel>
      </template>
      <p v-else style="margin:0; font-size:12px; opacity:.8;">
        Načítavam AI konfiguráciu...
      </p>

      <div style="display:grid; gap:12px;">
        <label style="display:block;">
          <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Názov</div>
          <input v-model="form.title" type="text" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;" />
        </label>

        <label ref="descriptionPreviewRef" style="display:block;">
          <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Popis</div>
          <textarea v-model="form.description" rows="4" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"></textarea>
        </label>

        <div style="display:grid; grid-template-columns: repeat(12, 1fr); gap:12px;">
          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Typ udalosti</div>
            <select v-model="form.type" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;">
              <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </label>

          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Viditeľnosť</div>
            <select v-model.number="form.visibility" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;">
              <option :value="1">Verejné</option>
              <option :value="0">Skryté</option>
            </select>
          </label>
        </div>

        <div style="display:grid; grid-template-columns: repeat(12, 1fr); gap:12px;">
          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Začína o</div>
            <input v-model="form.start_at" type="datetime-local" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;" />
          </label>
          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Končí o (voliteľné)</div>
            <input v-model="form.end_at" type="datetime-local" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;" />
          </label>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
          <button
            @click="submit"
            :disabled="loading"
            style="padding:10px 14px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            {{ loading ? 'Ukladám...' : 'Uložiť' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
