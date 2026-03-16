<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getEvents } from '@/services/api/admin/events'
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
const error = ref('')
const success = ref('')
const previewEmail = ref('')
const newsletterSubject = ref('')
const newsletterIntro = ref('')
const newsletterTipText = ref('')
const localCopyEdited = ref(false)

const preview = ref(null)
const runs = ref([])
const selectedEventIds = ref([])
const candidateEvents = ref([])
const maxFeaturedEvents = ref(10)

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

function defaultNewsletterSubject() {
  return 'Nebeský sprievodca: Týždenný newsletter'
}

function defaultNewsletterIntro() {
  const start = preview.value?.week?.start || '-'
  const end = preview.value?.week?.end || '-'
  return `Prehľad na týždeň ${start} až ${end}.`
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
  error.value = ''

  try {
    const [previewRes, runsRes, eventsRes] = await Promise.all([
      getNewsletterPreview(),
      getNewsletterRuns({ per_page: 20 }),
      getEvents({ per_page: 100 }),
    ])

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

    syncLocalCopyFieldsFromPreview()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa načítať dáta newslettera.'
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

    success.value = 'Vybrané udalosti boli uložené.'
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa uložiť vybrané udalosti.'
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

    success.value = `Náhľad odoslaný na ${email} (${eventsCount} udalostí, ${articlesCount} články).`
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
