<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import MarkYourCalendarModal from '@/components/MarkYourCalendarModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { getEvents } from '@/services/api/admin/events'
import {
  applyFallbackAsFeatured,
  createFeaturedEvent,
  deleteFeaturedEvent,
  forceFeaturedEventsPopup,
  getFeaturedEvents,
  updateFeaturedEvent,
  updateFeaturedPopupSettings,
} from '@/services/api/admin/featuredEvents'

const { confirm } = useConfirm()
const loading = ref(false)
const saving = ref(false)
const forcing = ref(false)
const applyingFallback = ref(false)
const refreshingFallback = ref(false)
const error = ref('')
const success = ref('')
let successTimer = null
function showSuccess(msg) {
  success.value = msg
  clearTimeout(successTimer)
  successTimer = setTimeout(() => { success.value = '' }, 3000)
}
const featured = ref([])
const fallbackPreview = ref([])
const resolvedEvents = ref([])
const selectionMode = ref('fallback')
const selectedMonth = ref(currentMonthKey())
const settings = ref({ enabled: true, force_version: 0, force_at: null })
const maxItems = ref(10)
const candidateEvents = ref([])
const selectedEventId = ref('')
const calendarBundleUrl = ref('')
const previewOpen = ref(false)

const activeCount = computed(() => featured.value.filter((item) => item.is_active).length)
const canAdd = computed(() => Number(selectedEventId.value) > 0 && !saving.value)
const counterClass = computed(() => {
  const ratio = activeCount.value / maxItems.value
  if (ratio >= 1) return 'counter--full'
  if (ratio >= 0.7) return 'counter--warn'
  return 'counter--ok'
})
const modeBadgeText = computed(() => {
  return selectionMode.value === 'admin' ? 'Používa sa: Admin výber' : 'Používa sa: Auto fallback'
})

const monthOptions = computed(() => {
  const current = currentMonthKey()
  const next = addMonths(current, 1)

  return [
    { value: current, label: `${formatMonthLabel(current)} (aktuálny)` },
    { value: next, label: `${formatMonthLabel(next)} (ďalší)` },
  ]
})

async function load({ refreshFallback = false } = {}) {
  loading.value = true
  error.value = ''

  try {
    const [featuredRes, eventsRes] = await Promise.all([
      getFeaturedEvents({
        month: selectedMonth.value,
        refresh_fallback: refreshFallback ? 1 : 0,
      }),
      getEvents({ per_page: 50 }),
    ])

    const payload = featuredRes?.data || {}

    featured.value = Array.isArray(payload?.data) ? payload.data : []
    fallbackPreview.value = Array.isArray(payload?.fallback_preview) ? payload.fallback_preview : []
    resolvedEvents.value = Array.isArray(payload?.resolved_events) ? payload.resolved_events : []
    selectionMode.value = payload?.selection_mode || 'fallback'
    settings.value = payload?.settings || settings.value
    maxItems.value = Number(payload?.meta?.max_items || 10)
    calendarBundleUrl.value = payload?.calendar?.bundle_ics_url || ''

    const eventsPayload = eventsRes?.data?.data || []
    candidateEvents.value = Array.isArray(eventsPayload)
      ? eventsPayload.map((row) => ({ id: row.id, title: row.title }))
      : []
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa načítať vybrané udalosti.'
  } finally {
    loading.value = false
  }
}

function eventLabel(eventId) {
  const row = candidateEvents.value.find((item) => Number(item.id) === Number(eventId))
  return row ? `${row.id} - ${row.title}` : String(eventId)
}

async function addFeaturedEvent() {
  if (!canAdd.value) return

  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await createFeaturedEvent({
      event_id: Number(selectedEventId.value),
      month: selectedMonth.value,
    })

    selectedEventId.value = ''
    showSuccess('Udalosť bola pridaná do popup zoznamu.')
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa pridať udalosť do výberu.'
  } finally {
    saving.value = false
  }
}

async function moveItem(item, direction) {
  const next = Number(item.position) + direction
  if (next < 0) return

  saving.value = true
  error.value = ''
  try {
    await updateFeaturedEvent(item.id, { position: next })
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa zmeniť poradie udalosti.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(item, checked) {
  saving.value = true
  error.value = ''
  try {
    await updateFeaturedEvent(item.id, { is_active: checked })
    item.is_active = checked
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa aktualizovať aktívny stav.'
  } finally {
    saving.value = false
  }
}

async function removeItem(item) {
  const approved = await confirm({
    title: 'Odstrániť vybranú udalosť?',
    message: 'Udalosť bude odstránená z popup zoznamu pre zvolený mesiac.',
    confirmText: 'Odstrániť udalosť',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!approved) return

  saving.value = true
  error.value = ''
  try {
    await deleteFeaturedEvent(item.id)
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa odstrániť udalosť.'
  } finally {
    saving.value = false
  }
}

async function forceNow() {
  forcing.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await forceFeaturedEventsPopup()
    settings.value = {
      ...settings.value,
      force_version: Number(response?.data?.force_version || settings.value.force_version),
      force_at: response?.data?.force_at || settings.value.force_at,
    }
    showSuccess('Popup bol naplánovaný pre všetkých používateľov.')
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa vynútiť popup.'
  } finally {
    forcing.value = false
  }
}

async function refreshFallbackPreview() {
  refreshingFallback.value = true
  error.value = ''
  try {
    await load({ refreshFallback: true })
  } finally {
    refreshingFallback.value = false
  }
}

async function useFallbackAsFeatured() {
  applyingFallback.value = true
  error.value = ''
  success.value = ''

  try {
    await applyFallbackAsFeatured({ month: selectedMonth.value })
    showSuccess('Fallback výber bol uložený ako admin výber pre zvolený mesiac.')
    await load({ refreshFallback: true })
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa použiť fallback ako admin výber.'
  } finally {
    applyingFallback.value = false
  }
}

async function toggleEnabled(checked) {
  saving.value = true
  error.value = ''
  try {
    const response = await updateFeaturedPopupSettings({ enabled: checked })
    settings.value = response?.data?.data || settings.value
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa uložiť nastavenie popupu.'
  } finally {
    saving.value = false
  }
}

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function onMonthChange() {
  load({ refreshFallback: true })
}

function currentMonthKey() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

function addMonths(monthKey, monthsToAdd) {
  const [yearRaw, monthRaw] = String(monthKey).split('-')
  const year = Number(yearRaw)
  const month = Number(monthRaw)
  const date = new Date(Date.UTC(year, month - 1 + monthsToAdd, 1))

  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`
}

function formatMonthLabel(monthKey) {
  const parsed = new Date(`${monthKey}-01T00:00:00Z`)
  if (Number.isNaN(parsed.getTime())) return monthKey
  return parsed.toLocaleDateString('sk-SK', { month: 'long', year: 'numeric' })
}

onMounted(load)
</script>

<template src="./featuredEvents/AdminFeaturedEventsView.template.html"></template>

<style scoped src="./featuredEvents/AdminFeaturedEventsView.css"></style>
