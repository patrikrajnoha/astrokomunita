<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useAdminTable } from '@/composables/useAdminTable'
import { useConfirm } from '@/composables/useConfirm'
import BaseModal from '@/components/ui/BaseModal.vue'
import http from '@/services/api'

const mode = ref('list')
const editingEvent = ref(null)
const formLoading = ref(false)
const formError = ref('')
const formSuccess = ref('')
const formSubmitAttempted = ref(false)
const { confirm } = useConfirm()

const eventTypes = [
  { value: 'meteor_shower', label: 'Meteorick\u00fd roj' },
  { value: 'eclipse_lunar', label: 'Zatmenie Mesiaca' },
  { value: 'eclipse_solar', label: 'Zatmenie Slnka' },
  { value: 'planetary_event', label: 'Planet\u00e1rny \u00fakaz' },
  { value: 'aurora', label: 'Pol\u00e1rna \u017eiara' },
  { value: 'other', label: 'In\u00e1 udalos\u0165' },
]

const eventIconOptions = [
  { value: '\u{1F319}', label: '\u{1F319} Mesiac' },
  { value: '\u2604\uFE0F', label: '\u2604\uFE0F Kometa' },
  { value: '\u{1F320}', label: '\u{1F320} Meteory' },
  { value: '\u{1F52D}', label: '\u{1F52D} Teleskop' },
  { value: '\u{1FA90}', label: '\u{1FA90} Planeta' },
  { value: '\u{1F6F0}\uFE0F', label: '\u{1F6F0}\uFE0F Satelit' },
  { value: '\u{1F680}', label: '\u{1F680} Misia' },
  { value: '\u2728', label: '\u2728 V\u0161eobecn\u00e1' },
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
const filterYear = ref('')
const filterMonth = ref('')
const filterDay = ref('')
const filterSourceKind = ref('')
const filterSourceName = ref('')
let searchDebounceTimeoutId = null
const monthOptions = [
  { value: 1, label: 'Janu\u00e1r' },
  { value: 2, label: 'Febru\u00e1r' },
  { value: 3, label: 'Marec' },
  { value: 4, label: 'April' },
  { value: 5, label: 'Maj' },
  { value: 6, label: 'J\u00fan' },
  { value: 7, label: 'J\u00fal' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'Okt\u00f3ber' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

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
  setFilters,
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
const formModalTitle = computed(() => (isEdit.value ? 'Upravi\u0165 udalos\u0165' : 'Nov\u00e1 udalos\u0165'))
const selectedIconPreview = computed(() => {
  const explicit = normalizeIconValue(form.value.icon_emoji)
  if (explicit) return explicit
  return defaultEventTypeIcons[String(form.value.type || '').trim()] || '\u2728'
})
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  const values = []
  for (let year = currentYear + 2; year >= currentYear - 10; year -= 1) {
    values.push(year)
  }

  const selectedYear = Number(filterYear.value || 0)
  if (Number.isInteger(selectedYear) && selectedYear > 0 && !values.includes(selectedYear)) {
    values.push(selectedYear)
    values.sort((left, right) => right - left)
  }

  return values
})
const canSelectMonth = computed(() => Number(filterYear.value) > 0)
const canSelectDay = computed(() => canSelectMonth.value && Number(filterMonth.value) > 0)
const daysInSelectedMonth = computed(() => {
  const year = Number(filterYear.value || 0)
  const month = Number(filterMonth.value || 0)
  if (year <= 0 || month <= 0) return 31
  return new Date(Date.UTC(year, month, 0)).getUTCDate()
})
const dayOptions = computed(() => {
  const values = []
  for (let day = 1; day <= daysInSelectedMonth.value; day += 1) {
    values.push(day)
  }
  return values
})
const hasActiveListFilters = computed(() => Boolean(
  String(searchQ.value || '').trim() !== ''
  || String(filterType.value || '').trim() !== ''
  || String(filterVisibility.value || '').trim() !== ''
  || String(filterYear.value || '').trim() !== ''
  || String(filterMonth.value || '').trim() !== ''
  || String(filterDay.value || '').trim() !== ''
  || String(filterSourceKind.value || '').trim() !== ''
  || String(filterSourceName.value || '').trim() !== ''
))
const hasAdvancedFiltersActive = computed(() => Boolean(
  String(filterYear.value || '').trim() !== ''
  || String(filterMonth.value || '').trim() !== ''
  || String(filterDay.value || '').trim() !== ''
  || String(filterSourceKind.value || '').trim() !== ''
  || String(filterSourceName.value || '').trim() !== ''
))
const selectedDateLabel = computed(() => {
  const year = Number(filterYear.value || 0)
  const month = Number(filterMonth.value || 0)
  const day = Number(filterDay.value || 0)
  if (year <= 0) return ''

  const labelParts = [String(year)]
  if (month > 0) {
    const monthLabel = monthOptions.find((item) => Number(item.value) === month)?.label || String(month)
    labelParts.push(monthLabel)
  }
  if (day > 0) {
    labelParts.push(String(day))
  }

  return labelParts.join(' / ')
})
const activeFilterChips = computed(() => {
  const chips = []
  const query = String(searchQ.value || '').trim()
  if (query !== '') {
    chips.push({
      key: 'search',
      label: `N\u00e1zov: ${query}`,
    })
  }

  if (String(filterType.value || '').trim() !== '') {
    chips.push({
      key: 'type',
      label: `Typ: ${eventTypeLabel(filterType.value)}`,
    })
  }

  if (String(filterVisibility.value || '').trim() !== '') {
    chips.push({
      key: 'visibility',
      label: `Stav: ${Number(filterVisibility.value) === 1 ? 'Verejn\u00e9' : 'Skryt\u00e9'}`,
    })
  }

  if (selectedDateLabel.value !== '') {
    chips.push({
      key: 'date',
      label: `D\u00e1tum: ${selectedDateLabel.value}`,
    })
  }

  if (String(filterSourceKind.value || '').trim() !== '') {
    chips.push({
      key: 'source_kind',
      label: `P\u00f4vod: ${filterSourceKind.value === 'manual' ? 'Manu\u00e1lne' : 'Crawlovan\u00e9'}`,
    })
  }

  const sourceName = String(filterSourceName.value || '').trim()
  if (sourceName !== '') {
    chips.push({
      key: 'source_name',
      label: `Zdroj: ${sourceName}`,
    })
  }

  return chips
})

const formErrors = computed(() => {
  const errors = []
  if (!String(form.value.title || '').trim()) {
    errors.push('N\u00e1zov je povinn\u00fd.')
  }
  if (!form.value.start_at) {
    errors.push('\u010cas za\u010diatku je povinn\u00fd.')
  }

  if (form.value.start_at && form.value.end_at) {
    const start = new Date(form.value.start_at)
    const end = new Date(form.value.end_at)
    if (!Number.isNaN(start.getTime()) && !Number.isNaN(end.getTime()) && end < start) {
      errors.push('Koniec nem\u00f4\u017ee by\u0165 sk\u00f4r ako za\u010diatok.')
    }
  }

  return errors
})

function eventTypeLabel(type) {
  const found = eventTypes.find((t) => t.value === String(type || '').trim())
  return found ? found.label : String(type || '-')
}

function applySearch() {
  setSearch(String(searchQ.value || '').trim())
}

function clearSearchDebounce() {
  if (searchDebounceTimeoutId !== null) {
    clearTimeout(searchDebounceTimeoutId)
    searchDebounceTimeoutId = null
  }
}

function queueSearch() {
  clearSearchDebounce()
  searchDebounceTimeoutId = setTimeout(() => {
    searchDebounceTimeoutId = null
    applySearch()
  }, 260)
}

function toOptionalFilterInt(value, { min, max }) {
  const normalized = String(value ?? '').trim()
  if (normalized === '') return undefined
  const parsed = Number(normalized)
  if (!Number.isInteger(parsed) || parsed < min || parsed > max) return undefined
  return parsed
}

function applyDateFilters() {
  const year = toOptionalFilterInt(filterYear.value, { min: 1900, max: 2200 })
  const month = year
    ? toOptionalFilterInt(filterMonth.value, { min: 1, max: 12 })
    : undefined
  const day = month
    ? toOptionalFilterInt(filterDay.value, { min: 1, max: daysInSelectedMonth.value })
    : undefined

  setFilters({
    year,
    month,
    day,
  })
}

function applyYearFilter(value) {
  filterYear.value = value
  if (!filterYear.value) {
    filterMonth.value = ''
    filterDay.value = ''
  }
  if (filterDay.value && Number(filterDay.value) > daysInSelectedMonth.value) {
    filterDay.value = ''
  }
  applyDateFilters()
}

function applyMonthFilter(value) {
  filterMonth.value = value
  if (!filterMonth.value) {
    filterDay.value = ''
  }
  if (filterDay.value && Number(filterDay.value) > daysInSelectedMonth.value) {
    filterDay.value = ''
  }
  applyDateFilters()
}

function applyDayFilter(value) {
  filterDay.value = value
  applyDateFilters()
}

function applyDatePreset(preset) {
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth() + 1
  const day = now.getDate()
  const normalizedPreset = String(preset || '').trim().toLowerCase()

  if (normalizedPreset === 'today') {
    filterYear.value = String(year)
    filterMonth.value = String(month)
    filterDay.value = String(day)
    applyDateFilters()
    return
  }

  if (normalizedPreset === 'this_month') {
    filterYear.value = String(year)
    filterMonth.value = String(month)
    filterDay.value = ''
    applyDateFilters()
    return
  }

  if (normalizedPreset === 'this_year') {
    filterYear.value = String(year)
    filterMonth.value = ''
    filterDay.value = ''
    applyDateFilters()
  }
}

function clearActiveFilterChip(chipKey) {
  const normalizedKey = String(chipKey || '').trim().toLowerCase()
  if (normalizedKey === 'search') {
    searchQ.value = ''
    applySearch()
    return
  }

  if (normalizedKey === 'type') {
    applyTypeFilter('')
    return
  }

  if (normalizedKey === 'visibility') {
    applyVisibilityFilter('')
    return
  }

  if (normalizedKey === 'date') {
    filterYear.value = ''
    filterMonth.value = ''
    filterDay.value = ''
    applyDateFilters()
    return
  }

  if (normalizedKey === 'source_kind') {
    applySourceKindFilter('')
    return
  }

  if (normalizedKey === 'source_name') {
    filterSourceName.value = ''
    applySourceNameFilter()
  }
}

function clearListFilters() {
  clearSearchDebounce()
  searchQ.value = ''
  filterType.value = ''
  filterVisibility.value = ''
  filterYear.value = ''
  filterMonth.value = ''
  filterDay.value = ''
  filterSourceKind.value = ''
  filterSourceName.value = ''

  setSearch('')
  setFilters({
    type: undefined,
    visibility: undefined,
    year: undefined,
    month: undefined,
    day: undefined,
    source_kind: undefined,
    source_name: undefined,
  })
}

function applyTypeFilter(value) {
  filterType.value = value
  setFilter('type', value || undefined)
}

function applyVisibilityFilter(value) {
  filterVisibility.value = value
  setFilter('visibility', value !== '' ? Number(value) : undefined)
}

function applySourceKindFilter(value) {
  filterSourceKind.value = value
  setFilter('source_kind', value || undefined)
}

function applySourceNameFilter() {
  const normalized = String(filterSourceName.value || '').trim()
  filterSourceName.value = normalized
  setFilter('source_name', normalized !== '' ? normalized : undefined)
}

async function toggleVisibility(event) {
  const newVisibility = event.visibility === 1 ? 0 : 1
  const basePayload = { ...event, visibility: newVisibility }

  try {
    await http.put(`/admin/events/${event.id}`, basePayload)
    event.visibility = newVisibility
  } catch (err) {
    const errorCode = String(err?.response?.data?.error_code || '').trim().toUpperCase()
    if (newVisibility === 1 && errorCode === 'AI_DESCRIPTION_REVIEW_REQUIRED') {
      const gateMessage = String(
        err?.response?.data?.message
          || err?.userMessage
          || 'Udalos\u0165 vy\u017eaduje kontrolu opisu pred zverejnen\u00edm.'
      ).trim()

      const approved = await confirm({
        title: 'Publikova\u0165 po kontrole',
        message: `${gateMessage}\n\nChce\u0161 zverejni\u0165 udalos\u0165 aj tak?`,
        confirmText: 'Zverejni\u0165 aj tak',
        cancelText: 'Zru\u0161i\u0165',
      })

      if (!approved) {
        return
      }

      try {
        await http.put(`/admin/events/${event.id}`, {
          ...basePayload,
          force_publish: true,
        })
        event.visibility = newVisibility
        return
      } catch {
        await refresh()
        return
      }
    }

    await refresh()
  }
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
  mode.value = 'create'
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
  mode.value = 'edit'
}

function closeForm() {
  mode.value = 'list'
  formError.value = ''
  formSuccess.value = ''
  formSubmitAttempted.value = false
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
      formSuccess.value = 'Udalos\u0165 bola upraven\u00e1.'
    } else {
      await http.post('/admin/events', payload)
      formSuccess.value = 'Udalos\u0165 bola vytvoren\u00e1.'
    }

    await refresh()
    mode.value = 'list'
  } catch (err) {
    formError.value = err?.response?.data?.message || 'Ulo\u017eenie zlyhalo.'
  } finally {
    formLoading.value = false
  }
}

onBeforeUnmount(() => {
  clearSearchDebounce()
})
</script>

<template src="./eventsUnified/EventsUnifiedView.template.html"></template>

<style scoped src="./eventsUnified/EventsUnifiedView.css"></style>


