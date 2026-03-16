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
} from '@/services/api/admin/ai'

const mode = ref('list')
const editingEvent = ref(null)
const formLoading = ref(false)
const formError = ref('')
const formSuccess = ref('')
const formSubmitAttempted = ref(false)
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
const { confirm } = useConfirm()

const eventTypes = [
  { value: 'meteor_shower', label: 'Meteorický roj' },
  { value: 'eclipse_lunar', label: 'Zatmenie Mesiaca' },
  { value: 'eclipse_solar', label: 'Zatmenie Slnka' },
  { value: 'planetary_event', label: 'Planetárny úkaz' },
  { value: 'aurora', label: 'Polarna ziara' },
  { value: 'other', label: 'Iná udalosť' },
]

const eventIconOptions = [
  { value: '\u{1F319}', label: '\u{1F319} Mesiac' },
  { value: '\u2604\uFE0F', label: '\u2604\uFE0F Kometa' },
  { value: '\u{1F320}', label: '\u{1F320} Meteory' },
  { value: '\u{1F52D}', label: '\u{1F52D} Teleskop' },
  { value: '\u{1FA90}', label: '\u{1FA90} Planeta' },
  { value: '\u{1F6F0}\uFE0F', label: '\u{1F6F0}\uFE0F Satelit' },
  { value: '\u{1F680}', label: '\u{1F680} Misia' },
  { value: '\u2728', label: '\u2728 Vseobecna' },
]

const defaultEventTypeIcons = {
  meteors: '\u2604\uFE0F',
  meteor_shower: '\u2604\uFE0F',
  eclipse: '\u{1F318}',
  eclipse_lunar: '\u{1F318}',
  eclipse_solar: '\u{1F30E}',
  conjunction: '\u{1FA90}',
  planetary_event: '\u{1FA90}',
  aurora: '\u{1F30C}',
  comet: '\u2604\uFE0F',
  asteroid: '\u{1F6F0}\uFE0F',
  mission: '\u{1F680}',
  other: '\u2728',
}

const form = ref({
  title: '',
  description: '',
  type: 'meteor_shower',
  icon_emoji: '',
  start_at: '',
  end_at: '',
  visibility: 1,
})

const searchQ = ref('')
const filterType = ref('')
const filterVisibility = ref('')

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
  setSearch,
  setFilter,
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
const formModalTitle = computed(() => (isEdit.value ? 'Upraviť udalosť' : 'Nová udalosť'))
const editingEventId = computed(() => Number(editingEvent.value?.id || 0))
const aiEnabled = computed(() => Boolean(aiConfig.value?.events_ai_humanized_enabled))
const aiPanelEnabled = computed(() => aiEnabled.value && editingEventId.value > 0)
const aiPanelReady = computed(() => !aiConfigLoading.value && aiConfig.value !== null)
const selectedIconPreview = computed(() => {
  const explicit = normalizeIconValue(form.value.icon_emoji)
  if (explicit) return explicit
  return defaultEventTypeIcons[String(form.value.type || '').trim()] || '\u2728'
})
const aiEventLastRun = computed(() => {
  const eventId = editingEventId.value
  if (eventId > 0 && aiLastRunByEvent.value[eventId]) {
    return aiLastRunByEvent.value[eventId]
  }

  return aiConfig.value?.features?.event_description_generate?.last_run || null
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

function eventTypeLabel(type) {
  const found = eventTypes.find((t) => t.value === String(type || '').trim())
  return found ? found.label : String(type || '-')
}

function applySearch() {
  setSearch(searchQ.value)
}

function applyTypeFilter(value) {
  filterType.value = value
  setFilter('type', value || undefined)
}

function applyVisibilityFilter(value) {
  filterVisibility.value = value
  setFilter('visibility', value !== '' ? Number(value) : undefined)
}

async function toggleVisibility(event) {
  const newVisibility = event.visibility === 1 ? 0 : 1
  try {
    await http.put(`/admin/events/${event.id}`, { ...event, visibility: newVisibility })
    event.visibility = newVisibility
  } catch (err) {
    // silently ignore — refresh will fix state
    await refresh()
  }
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

function normalizeIconValue(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

function eventDisplayIcon(event) {
  const explicit = normalizeIconValue(event?.icon_emoji)
  if (explicit) return explicit
  return defaultEventTypeIcons[String(event?.type || '').trim()] || '\u2728'
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
    if (normalizedEventId > 0 && run) {
      aiLastRunByEvent.value = {
        ...aiLastRunByEvent.value,
        [normalizedEventId]: run,
      }
    }
    if (run?.status) {
      aiActionStatus.value = normalizeAiStatus(run.status)
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
    icon_emoji: '',
    start_at: '',
    end_at: '',
    visibility: 1,
  }
  formError.value = ''
  formSuccess.value = ''
  formSubmitAttempted.value = false
  aiActionError.value = ''
  aiActionStatus.value = 'idle'
  aiActionResult.value = null
  aiActionNotice.value = ''
  aiActionRawStatus.value = null
  aiUndoSnapshot.value = null
  aiShortDraft.value = ''
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
    icon_emoji: normalizeIconValue(event.icon_emoji),
    start_at: toLocalInput(event.start_at || event.starts_at || event.max_at),
    end_at: toLocalInput(event.end_at || event.ends_at),
    visibility: typeof event.visibility === 'number' ? event.visibility : 1,
  }
  formError.value = ''
  formSuccess.value = ''
  formSubmitAttempted.value = false
  aiActionError.value = ''
  aiActionStatus.value = 'idle'
  aiActionResult.value = null
  aiActionNotice.value = ''
  aiActionRawStatus.value = null
  aiUndoSnapshot.value = null
  aiShortDraft.value = String(event.short || '')
  showAdvancedAiInForm.value = false
  mode.value = 'edit'
  showTranslationTools.value = false
  loadAiConfig(event?.id)
}

function closeForm() {
  mode.value = 'list'
  formError.value = ''
  formSuccess.value = ''
  formSubmitAttempted.value = false
  aiActionError.value = ''
  aiActionStatus.value = 'idle'
  aiActionResult.value = null
  aiActionNotice.value = ''
  aiActionRawStatus.value = null
  aiUndoSnapshot.value = null
  aiShortDraft.value = ''
  showAdvancedAiInForm.value = false
  showTranslationTools.value = false
}

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('sk-SK', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
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
  formSubmitAttempted.value = true
  if (formErrors.value.length > 0) {
    return
  }

  formLoading.value = true
  formError.value = ''
  formSuccess.value = ''

  const payload = {
    title: String(form.value.title || '').trim(),
    description: String(form.value.description || '').trim() || null,
    type: form.value.type,
    icon_emoji: normalizeIconValue(form.value.icon_emoji) || null,
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






