<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getEvents } from '@/services/api/admin/events'
import {
  getNewsletterPreview,
  getNewsletterRuns,
  sendNewsletterPreview,
  sendNewsletter,
  updateNewsletterFeaturedEvents,
} from '@/services/api/admin/newsletter'

const COPY_DRAFT_STORAGE_KEY = 'admin.newsletter.copy_draft.v1'

const loading = ref(false)
const savingSelection = ref(false)
const sending = ref(false)
const previewSending = ref(false)
const error = ref('')
const success = ref('')
const previewEmail = ref('')
const newsletterSubject = ref('')
const newsletterIntro = ref('')
const newsletterTipText = ref('')
const localCopyEdited = ref(false)
const draftHydrated = ref(false)
const draftSavedAt = ref('')

const preview = ref(null)
const runs = ref([])
const selectedEventIds = ref([])
const candidateEvents = ref([])
const maxFeaturedEvents = ref(10)
const eventSelectionRef = ref(null)

const sendOptions = reactive({
  force: false,
  dry_run: false,
})

const selectedCount = computed(() => selectedEventIds.value.length)
const canSaveSelection = computed(
  () =>
    !loading.value &&
    !savingSelection.value &&
    selectedCount.value <= maxFeaturedEvents.value,
)
const weekStart = computed(() => String(preview.value?.week?.start || '-'))
const weekEnd = computed(() => String(preview.value?.week?.end || '-'))
const pageTitle = computed(() => `Newsletter - Tyzden ${weekStart.value} az ${weekEnd.value}`)
const previewTopEvents = computed(() => (Array.isArray(preview.value?.top_events) ? preview.value.top_events : []))
const topArticles = computed(() => (Array.isArray(preview.value?.top_articles) ? preview.value.top_articles : []))
const selectedEventsForSummary = computed(() => previewTopEvents.value.slice(0, 5))
const selectionMode = computed(() => String(preview.value?.selection?.mode || 'manual'))
const isAutoSelectionMode = computed(() => selectionMode.value === 'automatic_fallback')
const selectionModeLabel = computed(() => (isAutoSelectionMode.value ? 'Automaticky vyber' : 'Rucny vyber'))
const draftStatusLabel = computed(() => {
  if (!draftSavedAt.value) return ''
  return `Draft sa ulozil ${formatDateTime(draftSavedAt.value)}`
})
const visibleCandidateEvents = computed(() => {
  if (!Array.isArray(candidateEvents.value) || candidateEvents.value.length === 0) {
    return []
  }

  const startRaw = preview.value?.week?.start
  const endRaw = preview.value?.week?.end
  if (!startRaw || !endRaw) {
    return candidateEvents.value
  }

  const start = new Date(`${startRaw}T00:00:00`)
  const end = new Date(`${endRaw}T23:59:59`)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return candidateEvents.value
  }

  const filtered = candidateEvents.value.filter((event) => {
    const parsed = new Date(event.start_at)
    if (Number.isNaN(parsed.getTime())) return false
    return parsed >= start && parsed <= end
  })

  return filtered.length > 0 ? filtered : candidateEvents.value
})

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

function supportsLocalStorage() {
  try {
    return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
  } catch {
    return false
  }
}

function saveCopyDraft() {
  if (!supportsLocalStorage()) return

  const payload = {
    subject: String(newsletterSubject.value || '').trim(),
    intro: String(newsletterIntro.value || '').trim(),
    tip: String(newsletterTipText.value || '').trim(),
    saved_at: new Date().toISOString(),
  }

  window.localStorage.setItem(COPY_DRAFT_STORAGE_KEY, JSON.stringify(payload))
  draftSavedAt.value = payload.saved_at
}

function hydrateCopyDraftOnce() {
  if (draftHydrated.value || !supportsLocalStorage()) {
    draftHydrated.value = true
    return
  }

  const raw = window.localStorage.getItem(COPY_DRAFT_STORAGE_KEY)
  draftHydrated.value = true
  if (!raw) return

  try {
    const decoded = JSON.parse(raw)
    const subject = String(decoded?.subject || '').trim()
    const intro = String(decoded?.intro || '').trim()
    const tip = String(decoded?.tip || '').trim()

    if (!subject && !intro && !tip) {
      return
    }

    newsletterSubject.value = subject || defaultNewsletterSubject()
    newsletterIntro.value = intro || defaultNewsletterIntro()
    newsletterTipText.value = tip || newsletterTipText.value
    draftSavedAt.value = String(decoded?.saved_at || '')
    localCopyEdited.value = true
  } catch {
    window.localStorage.removeItem(COPY_DRAFT_STORAGE_KEY)
  }
}

function clearCopyDraft() {
  if (!supportsLocalStorage()) return
  window.localStorage.removeItem(COPY_DRAFT_STORAGE_KEY)
  draftSavedAt.value = ''
}

function markLocalCopyEdited() {
  localCopyEdited.value = true
  saveCopyDraft()
}

function resetCopyToSuggested() {
  localCopyEdited.value = false
  syncLocalCopyFieldsFromPreview()
  clearCopyDraft()
}

async function focusEventSelection() {
  await nextTick()

  const root = eventSelectionRef.value
  if (!root || typeof root.scrollIntoView !== 'function') {
    return
  }

  root.scrollIntoView({ behavior: 'smooth', block: 'center' })

  const firstCheckbox = root.querySelector('input[type="checkbox"]')
  if (firstCheckbox && typeof firstCheckbox.focus === 'function') {
    firstCheckbox.focus()
  }
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [previewRes, runsRes, eventsRes] = await Promise.all([
      getNewsletterPreview(),
      getNewsletterRuns({ per_page: 20 }),
      getEvents({ per_page: 100 }),
    ])

    preview.value = previewRes?.data?.data || null
    maxFeaturedEvents.value = Number(previewRes?.data?.meta?.max_featured_events || 10)

    const explicitSelectionIds = Array.isArray(preview.value?.selection?.admin_selected_event_ids)
      ? preview.value.selection.admin_selected_event_ids
          .map((row) => Number(row || 0))
          .filter((id) => id > 0)
      : null
    const fallbackSelectionIds = Array.isArray(preview.value?.top_events)
      ? preview.value.top_events
          .map((row) => Number(row?.id || 0))
          .filter((id) => id > 0)
      : []

    selectedEventIds.value = explicitSelectionIds ?? fallbackSelectionIds

    runs.value = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []

    const eventsPayload = eventsRes?.data?.data || []
    candidateEvents.value = Array.isArray(eventsPayload)
      ? eventsPayload.map((item) => ({
          id: Number(item.id),
          title: String(item.title || ''),
          start_at: item.start_at,
        }))
      : []

    syncLocalCopyFieldsFromPreview()
    hydrateCopyDraftOnce()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa nacitat data newslettera.'
  } finally {
    loading.value = false
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

    success.value = 'Vyber udalosti bol ulozeny.'
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa ulozit vybrane udalosti.'
  } finally {
    savingSelection.value = false
  }
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
      ? `Newsletter run ${runId} bol prijaty (${reason}).`
      : `Newsletter akcia skoncila (${reason}).`

    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa odoslat newsletter.'
  } finally {
    sending.value = false
  }
}

async function triggerPreviewSend() {
  if (previewSending.value) return

  const normalizedEmail = String(previewEmail.value || '').trim()
  if (!normalizedEmail) {
    error.value = 'Email pre test je povinny.'
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

    success.value = `Test bol odoslany na ${email} (${eventsCount} udalosti, ${articlesCount} clanky).`
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa odoslat test.'
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
