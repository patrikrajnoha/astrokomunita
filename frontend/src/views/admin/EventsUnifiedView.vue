<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAdminTable } from '@/composables/useAdminTable'
import { useConfirm } from '@/composables/useConfirm'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
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
const showTranslationTools = ref(false)
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
const showAdvancedAiInForm = ref(false)
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
const isFormModalOpen = computed({
  get: () => mode.value !== 'list',
  set: (open) => {
    if (!open) closeForm()
  },
})
const formModalTitle = computed(() => (isEdit.value ? 'Upravit udalost' : 'Nova udalost'))
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
  showAdvancedAiInForm.value = false
  mode.value = 'create'
  showTranslationTools.value = false
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
  showAdvancedAiInForm.value = false
  mode.value = 'edit'
  showTranslationTools.value = false
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
  showAdvancedAiInForm.value = false
  showTranslationTools.value = false
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

function toggleTranslationTools() {
  showTranslationTools.value = !showTranslationTools.value
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

<template src="./eventsUnified/EventsUnifiedView.template.html"></template>

<style scoped src="./eventsUnified/EventsUnifiedView.css"></style>






