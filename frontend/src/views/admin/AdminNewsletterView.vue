<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import { getEvents } from '@/services/api/admin/events'
import { draftNewsletterCopy, getAdminAiConfig, primeNewsletterInsights } from '@/services/api/admin/ai'
import {
  getNewsletterPreview,
  getNewsletterRuns,
  sendNewsletterPreview,
  sendNewsletter,
  updateNewsletterFeaturedEvents,
} from '@/services/api/admin/newsletter'

const loading = ref(false)
const savingSelection = ref(false)
const sending = ref(false)
const previewSending = ref(false)
const aiConfigLoading = ref(false)
const aiPrimeLoading = ref(false)
const aiPrimeError = ref('')
const aiPrimeStatus = ref('idle')
const aiPrimeLastRun = ref(null)
const aiPrimeResult = ref(null)
const aiPrimeLimit = ref(5)
const aiPrimeNotice = ref('')
const aiPrimeRawStatus = ref(null)
const aiDraftLoading = ref(false)
const aiDraftError = ref('')
const aiDraftStatus = ref('idle')
const aiDraftLastRun = ref(null)
const aiDraftResult = ref(null)
const aiDraftSelectedIndex = ref(0)
const aiDraftNotice = ref('')
const error = ref('')
const success = ref('')
const previewEmail = ref('')
const newsletterSubject = ref('')
const newsletterIntro = ref('')
const newsletterTipText = ref('')
const localCopyEdited = ref(false)

const preview = ref(null)
const runs = ref([])
const aiConfig = ref(null)
const selectedEventIds = ref([])
const candidateEvents = ref([])
const maxFeaturedEvents = ref(10)

const sendOptions = reactive({
  force: false,
  dry_run: false,
})

const selectedCount = computed(() => selectedEventIds.value.length)
const aiEnabled = computed(() => Boolean(aiConfig.value?.events_ai_humanized_enabled))
const aiInsightsTtlSeconds = computed(() => Number(aiConfig.value?.insights_cache_ttl_seconds || 0))
const aiInsightsTtlDays = computed(() => {
  const seconds = aiInsightsTtlSeconds.value
  if (!Number.isFinite(seconds) || seconds <= 0) return null
  return Math.max(1, Math.round(seconds / 86400))
})
const aiPrimeMaxLimit = computed(() => {
  const value = Number(aiConfig.value?.prime_insights_max_limit || 10)
  if (!Number.isFinite(value)) return 10
  return Math.max(1, Math.min(Math.round(value), 10))
})
const aiNewsletterFeature = computed(() => aiConfig.value?.features?.newsletter_prime_insights || null)
const aiCopyFeature = computed(() => aiConfig.value?.features?.newsletter_copy_draft || null)
const aiCopyEnabled = computed(() => Boolean(aiCopyFeature.value?.enabled))
const aiPanelLastRun = computed(
  () => aiPrimeLastRun.value || aiNewsletterFeature.value?.last_run || null,
)
const canSaveSelection = computed(
  () =>
    !loading.value &&
    !savingSelection.value &&
    selectedCount.value <= maxFeaturedEvents.value,
)

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

function createFallbackAiConfig() {
  return {
    events_ai_humanized_enabled: false,
    insights_cache_ttl_seconds: 0,
    prime_insights_max_limit: 10,
    features: {
      newsletter_prime_insights: {
        enabled: false,
        last_run: null,
      },
      newsletter_copy_draft: {
        enabled: false,
        last_run: null,
      },
    },
  }
}

function defaultNewsletterSubject() {
  return 'Nebesky sprievodca: Tyzdenny newsletter'
}

function defaultNewsletterIntro() {
  const start = preview.value?.week?.start || '-'
  const end = preview.value?.week?.end || '-'
  return `Prehlad na tyzden ${start} az ${end}.`
}

function syncLocalCopyFieldsFromPreview() {
  if (localCopyEdited.value) return

  newsletterSubject.value = defaultNewsletterSubject()
  newsletterIntro.value = defaultNewsletterIntro()
  newsletterTipText.value = String(preview.value?.astronomical_tip || '').trim()
  if (!newsletterTipText.value) {
    newsletterTipText.value = 'Tip pripraveny z udalosti.'
  }
}

function markLocalCopyEdited() {
  localCopyEdited.value = true
}

async function load() {
  loading.value = true
  aiConfigLoading.value = true
  error.value = ''

  try {
    const [previewResult, runsResult, eventsResult, aiConfigResult] = await Promise.allSettled([
      getNewsletterPreview(),
      getNewsletterRuns({ per_page: 20 }),
      getEvents({ per_page: 100 }),
      getAdminAiConfig(),
    ])

    if (previewResult.status !== 'fulfilled') {
      throw previewResult.reason
    }
    if (runsResult.status !== 'fulfilled') {
      throw runsResult.reason
    }
    if (eventsResult.status !== 'fulfilled') {
      throw eventsResult.reason
    }

    const previewRes = previewResult.value
    const runsRes = runsResult.value
    const eventsRes = eventsResult.value

    preview.value = previewRes?.data?.data || null
    maxFeaturedEvents.value = Number(previewRes?.data?.meta?.max_featured_events || 10)
    selectedEventIds.value = Array.isArray(preview.value?.top_events)
      ? preview.value.top_events
          .map((row) => Number(row?.id || 0))
          .filter((id) => id > 0)
      : []

    runs.value = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    const eventsPayload = eventsRes?.data?.data || []
    candidateEvents.value = Array.isArray(eventsPayload)
      ? eventsPayload.map((item) => ({
          id: Number(item.id),
          title: item.title,
          start_at: item.start_at,
        }))
      : []

    aiConfig.value = aiConfigResult.status === 'fulfilled'
      ? (aiConfigResult.value?.data?.data || createFallbackAiConfig())
      : createFallbackAiConfig()

    aiPrimeLimit.value = Math.max(1, Math.min(Number(aiPrimeLimit.value || 5), aiPrimeMaxLimit.value))
    aiPrimeLastRun.value = aiConfig.value?.features?.newsletter_prime_insights?.last_run || null
    aiPrimeStatus.value = normalizeAiStatus(aiPrimeLastRun.value?.status, 'idle')
    aiDraftLastRun.value = aiConfig.value?.features?.newsletter_copy_draft?.last_run || null
    aiDraftStatus.value = normalizeAiStatus(aiDraftLastRun.value?.status, 'idle')
    syncLocalCopyFieldsFromPreview()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa načítať dáta newslettera.'
  } finally {
    loading.value = false
    aiConfigLoading.value = false
  }
}

function isSelected(eventId) {
  return selectedEventIds.value.includes(Number(eventId))
}

function toggleSelected(eventId, checked) {
  const id = Number(eventId)
  if (id <= 0) return

  if (checked) {
    if (selectedEventIds.value.includes(id)) return
    if (selectedEventIds.value.length >= maxFeaturedEvents.value) return
    selectedEventIds.value = [...selectedEventIds.value, id]
    return
  }

  selectedEventIds.value = selectedEventIds.value.filter((value) => value !== id)
}

async function saveFeaturedEvents() {
  if (!canSaveSelection.value) return

  savingSelection.value = true
  error.value = ''
  success.value = ''
  try {
    await updateNewsletterFeaturedEvents({
      event_ids: selectedEventIds.value,
    })
    success.value = 'Vybrané udalosti boli uložené.'
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa uložiť vybrané udalosti.'
  } finally {
    savingSelection.value = false
  }
}

async function triggerPrimeInsights() {
  if (aiPrimeLoading.value) return
  if (!aiEnabled.value) {
    aiPrimeStatus.value = 'idle'
    aiPrimeError.value = 'AI pomocnik je momentalne vypnuty.'
    return
  }

  aiPrimeLoading.value = true
  aiPrimeError.value = ''
  aiPrimeNotice.value = ''
  aiPrimeRawStatus.value = null
  aiPrimeStatus.value = 'idle'
  aiPrimeResult.value = null

  const limit = Math.max(1, Math.min(Number(aiPrimeLimit.value || 5), aiPrimeMaxLimit.value))

  try {
    const response = await primeNewsletterInsights({ limit })
    aiPrimeResult.value = response?.data?.data || null
    aiPrimeLastRun.value = response?.data?.last_run || null
    aiPrimeStatus.value = normalizeAiStatus(aiPrimeLastRun.value?.status, 'success')
    if (
      Number(aiPrimeResult.value?.primed || 0) === 0
      && Number(aiPrimeResult.value?.failed || 0) === 0
    ) {
      aiPrimeStatus.value = 'idle'
    }
    await load()
    aiPrimeNotice.value = 'Tip pripraveny.'
  } catch (e) {
    aiPrimeStatus.value = 'error'
    const responseStatus = Number(e?.response?.status || 0)
    const retryAfterSeconds = Number(e?.response?.data?.retry_after_seconds || 0)
    aiPrimeRawStatus.value = responseStatus > 0 ? responseStatus : null

    if (responseStatus === 409 && retryAfterSeconds > 0) {
      aiPrimeError.value = `Skus znova o ${Math.ceil(retryAfterSeconds)} s.`
    } else {
      aiPrimeError.value = 'Tip sa nepodarilo pripravit.'
    }
  } finally {
    aiPrimeLoading.value = false
  }
}

async function triggerDraftCopy() {
  if (aiDraftLoading.value) return
  if (!aiCopyEnabled.value) {
    aiDraftStatus.value = 'idle'
    aiDraftError.value = 'AI navrh copy je momentalne vypnuty.'
    return
  }

  aiDraftLoading.value = true
  aiDraftError.value = ''
  aiDraftNotice.value = ''
  aiDraftStatus.value = 'idle'
  aiDraftResult.value = null
  aiDraftSelectedIndex.value = 0

  try {
    const response = await draftNewsletterCopy()
    const payload = response?.data || {}
    aiDraftResult.value = {
      subjects: Array.isArray(payload?.subjects) ? payload.subjects : [],
      intro: String(payload?.intro || ''),
      tip_text: String(payload?.tip_text || ''),
      fallback_used: Boolean(payload?.fallback_used),
    }
    aiDraftLastRun.value = payload?.last_run || null
    aiDraftStatus.value = normalizeAiStatus(payload?.status, 'success')
    aiDraftNotice.value = 'Navrh pripraveny.'
  } catch (e) {
    aiDraftStatus.value = 'error'
    aiDraftError.value = e?.response?.data?.message || e?.userMessage || 'Navrh sa nepodarilo pripravit.'
  } finally {
    aiDraftLoading.value = false
  }
}

function applyDraftCopy() {
  const draft = aiDraftResult.value
  if (!draft || !Array.isArray(draft.subjects) || draft.subjects.length === 0) return

  const selectedIndex = Math.max(
    0,
    Math.min(Number(aiDraftSelectedIndex.value || 0), draft.subjects.length - 1),
  )
  const selectedSubject = String(draft.subjects[selectedIndex] || draft.subjects[0] || '').trim()
  const intro = String(draft.intro || '').trim()
  const tipText = String(draft.tip_text || '').trim()

  if (selectedSubject) {
    newsletterSubject.value = selectedSubject
  }
  if (intro) {
    newsletterIntro.value = intro
  }
  if (tipText) {
    newsletterTipText.value = tipText
  }

  markLocalCopyEdited()
  aiDraftNotice.value = 'Navrh aplikovany do local preview.'
}

async function triggerSend() {
  if (sending.value) return

  sending.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await sendNewsletter({
      force: Boolean(sendOptions.force),
      dry_run: Boolean(sendOptions.dry_run),
    })

    const reason = response?.data?.reason || 'created'
    const runId = response?.data?.data?.id
    success.value = runId
      ? `Newsletter run ${runId} accepted (${reason}).`
      : `Newsletter action completed (${reason}).`

    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa spustiť odoslanie newslettera.'
  } finally {
    sending.value = false
  }
}

async function triggerPreviewSend() {
  if (previewSending.value) return

  const normalizedEmail = String(previewEmail.value || '').trim()
  if (!normalizedEmail) {
    error.value = 'Email pre náhľad je povinný.'
    success.value = ''
    return
  }

  previewSending.value = true
  error.value = ''
  success.value = ''
  try {
    const payload = {
      email: normalizedEmail,
    }
    const subjectOverride = String(newsletterSubject.value || '').trim()
    const introOverride = String(newsletterIntro.value || '').trim()
    const tipOverride = String(newsletterTipText.value || '').trim()

    if (subjectOverride) {
      payload.subject_override = subjectOverride
    }
    if (introOverride) {
      payload.intro_override = introOverride
    }
    if (tipOverride) {
      payload.tip_override = tipOverride
    }

    const response = await sendNewsletterPreview(payload)

    const data = response?.data?.data || {}
    const email = data?.email || normalizedEmail
    const eventsCount = Number(data?.events_count || 0)
    const articlesCount = Number(data?.articles_count || 0)

    success.value = `Náhľad odoslaný na ${email} (${eventsCount} udalosti, ${articlesCount} články).`
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa odoslať preview email.'
  } finally {
    previewSending.value = false
  }
}

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(load)
</script>

<template src="./newsletter/AdminNewsletterView.template.html"></template>

<style scoped src="./newsletter/AdminNewsletterView.css"></style>




