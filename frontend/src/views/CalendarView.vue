<template src="./calendar/CalendarView.template.html"></template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { prependStarLabel } from './events/eventsViewCard.utils'
import {
  EVENT_TIMEZONE,
  formatEventDateKey,
  formatEventTime as formatClockTime,
  getEventNowPeriodDefaults,
  resolveEventTimeContext,
} from '@/utils/eventTime'

const route = useRoute()
const router = useRouter()

const initialNow = getEventNowPeriodDefaults(EVENT_TIMEZONE)
const today = buildUtcDate(initialNow.year, initialNow.month, initialNow.day)

const currentMonth = ref(buildUtcDate(initialNow.year, initialNow.month, 1))
const selectedDate = ref(buildUtcDate(initialNow.year, initialNow.month, initialNow.day))
const events = ref([])
const loading = ref(false)
const error = ref('')
const activeYear = ref(initialNow.year)
const activeMonth = ref(initialNow.month)
const activeWeek = ref(initialNow.week)
const activePeriod = ref('month')

const monthLabel = computed(() =>
  currentMonth.value.toLocaleDateString('sk-SK', { month: 'long', timeZone: 'UTC' }),
)
const yearLabel = computed(() => currentMonth.value.getUTCFullYear())

const selectedLabel = computed(() =>
  selectedDate.value.toLocaleDateString('sk-SK', {
    weekday: 'long',
    day: 'numeric',
    timeZone: 'UTC',
  }),
)

const dayCells = computed(() => {
  const year = currentMonth.value.getUTCFullYear()
  const month = currentMonth.value.getUTCMonth()
  const firstDay = new Date(Date.UTC(year, month, 1)).getUTCDay()
  const mondayOffset = (firstDay + 6) % 7
  const daysInMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate()
  const cells = []

  for (let index = 0; index < mondayOffset; index += 1) {
    cells.push({ date: null, day: null, muted: true, blank: true })
  }

  for (let day = 1; day <= daysInMonth; day += 1) {
    const date = buildUtcDate(year, month + 1, day)
    cells.push({
      date,
      day,
      muted: false,
      blank: false,
      isSunday: date.getUTCDay() === 0,
      isSaturday: date.getUTCDay() === 6,
    })
  }

  while (cells.length % 7 !== 0) {
    cells.push({ date: null, day: null, muted: true, blank: true })
  }

  while (cells.length < 42) {
    cells.push({ date: null, day: null, muted: true, blank: true })
  }

  return cells
})

const eventsByDay = computed(() => {
  const map = {}

  events.value.forEach((event) => {
    const key = toYMD(event?.start_at || event?.starts_at || event?.max_at)
    if (!key) return
    if (!map[key]) map[key] = []
    map[key].push(event)
  })

  return map
})

const selectedEvents = computed(() => {
  const key = toYMD(selectedDate.value)
  return key ? eventsByDay.value[key] || [] : []
})

const selectedEventsCountLabel = computed(() => {
  const count = selectedEvents.value.length
  if (count === 0) return 'žiadne udalosti'
  if (count === 1) return '1 udalosť'
  if (count <= 4) return `${count} udalosti`
  return `${count} udalostí`
})

function selectDate(date) {
  selectedDate.value = buildUtcDate(
    date.getUTCFullYear(),
    date.getUTCMonth() + 1,
    date.getUTCDate(),
  )
}

function dayCellClass(cell) {
  if (!cell.date) return 'day-cell--blank'

  const isSelected = isSameDay(cell.date, selectedDate.value)
  const isToday = isSameDay(cell.date, today)
  const hasEvents = Boolean(eventsByDay.value[toYMD(cell.date)]?.length)

  return {
    'day-cell--selected': isSelected,
    'day-cell--today': !isSelected && isToday,
    'day-cell--sun': cell.isSunday,
    'day-cell--sat': cell.isSaturday,
    'day-cell--has-events': hasEvents,
  }
}

function dayEventCount(date) {
  const key = toYMD(date)
  return key ? eventsByDay.value[key]?.length || 0 : 0
}

function cellTooltip(cell) {
  if (!cell.date) return ''

  const items = eventsByDay.value[toYMD(cell.date)] || []
  if (!items.length) return ''

  const shown = items.slice(0, 2).map((event) => prependStarLabel(event.title)).join(' · ')
  if (items.length > 2) return `${shown} · a ${items.length - 2} ďalších`
  return shown
}

function typeDot(type) {
  const map = {
    meteor_shower: 'dot-blue',
    eclipse_lunar: 'dot-amber',
    eclipse_solar: 'dot-amber',
    planetary_event: 'dot-violet',
    aurora: 'dot-violet',
    other: 'dot-blue',
  }

  return map[type] || 'dot-blue'
}

function formatEventTime(event) {
  if (event.all_day) return 'Celý deň'

  const context = resolveEventTimeContext(event, EVENT_TIMEZONE)
  if (!context.showTimezoneLabel) {
    return context.message
  }

  const anchorKey = toYMD(event.start_at || event.starts_at || event.max_at)
  const endRaw = event.end_at || event.ends_at
  const endKey = toYMD(endRaw)

  if (context.timeType !== 'peak' && endRaw && anchorKey && anchorKey === endKey) {
    const endTime = formatClockTime(endRaw, EVENT_TIMEZONE).timeString
    if (endTime) {
      return `${context.timeString} - ${endTime} (${context.timezoneLabelShort})`
    }
  }

  return `${context.message} (${context.timezoneLabelShort})`
}

function openEventDetail(event) {
  const eventId = event?.id
  if (!eventId) return
  router.push(`/events/${eventId}`)
}

function isSameDay(left, right) {
  return toYMD(left) === toYMD(right)
}

function toYMD(value) {
  if (!value) return ''
  if (value instanceof Date) {
    return formatUtcDateKey(value)
  }

  return formatEventDateKey(value, EVENT_TIMEZONE)
}

async function fetchMonthEvents() {
  loading.value = true
  error.value = ''

  const params = { year: activeYear.value }
  if (activePeriod.value === 'month') {
    params.month = activeMonth.value
  } else if (activePeriod.value === 'week') {
    params.week = activeWeek.value
  }

  try {
    const response = await api.get('/events', { params })
    const rows = Array.isArray(response.data?.data) ? response.data.data : response.data
    events.value = Array.isArray(rows) ? rows : []
  } catch (err) {
    error.value = err?.response?.data?.message || 'Nepodarilo sa načítať udalosti.'
    events.value = []
  } finally {
    loading.value = false
  }
}

function prevMonth() {
  if (activePeriod.value === 'month') {
    const previous = new Date(Date.UTC(activeYear.value, activeMonth.value - 2, 1))
    syncQuery({
      year: previous.getUTCFullYear(),
      month: previous.getUTCMonth() + 1,
      period: 'month',
    })
    return
  }

  if (activePeriod.value === 'week') {
    const start = isoWeekStart(activeYear.value, activeWeek.value)
    start.setUTCDate(start.getUTCDate() - 7)
    syncQuery({
      year: start.getUTCFullYear(),
      week: getIsoWeek(start),
      period: 'week',
    })
    return
  }

  syncQuery({ year: activeYear.value - 1, period: 'year' })
}

function nextMonth() {
  if (activePeriod.value === 'month') {
    const next = new Date(Date.UTC(activeYear.value, activeMonth.value, 1))
    syncQuery({
      year: next.getUTCFullYear(),
      month: next.getUTCMonth() + 1,
      period: 'month',
    })
    return
  }

  if (activePeriod.value === 'week') {
    const start = isoWeekStart(activeYear.value, activeWeek.value)
    start.setUTCDate(start.getUTCDate() + 7)
    syncQuery({
      year: start.getUTCFullYear(),
      week: getIsoWeek(start),
      period: 'week',
    })
    return
  }

  syncQuery({ year: activeYear.value + 1, period: 'year' })
}

function prevYear() {
  syncQuery({
    year: activeYear.value - 1,
    month: activeMonth.value,
    week: activeWeek.value,
    period: activePeriod.value,
  })
}

function nextYear() {
  syncQuery({
    year: activeYear.value + 1,
    month: activeMonth.value,
    week: activeWeek.value,
    period: activePeriod.value,
  })
}

function goToToday() {
  const defaults = getEventNowPeriodDefaults(EVENT_TIMEZONE)

  syncQuery({
    year: defaults.year,
    month: defaults.month,
    week: defaults.week,
    period: activePeriod.value,
  })
  selectedDate.value = buildUtcDate(defaults.year, defaults.month, defaults.day)
}

onMounted(() => {
  applyRoutePeriod()
  fetchMonthEvents()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})

watch(
  () => route.query,
  () => {
    applyRoutePeriod()
    fetchMonthEvents()
  },
  { deep: true },
)

function handleKeydown(event) {
  if (event.key === 'ArrowLeft') {
    prevMonth()
  } else if (event.key === 'ArrowRight') {
    nextMonth()
  }
}

function applyRoutePeriod() {
  const defaults = getEventNowPeriodDefaults(EVENT_TIMEZONE)
  const period = typeof route.query.period === 'string' ? route.query.period : 'month'

  activePeriod.value = ['month', 'week', 'year'].includes(period) ? period : 'month'
  activeYear.value = Number(route.query.year) || defaults.year
  activeMonth.value = Number(route.query.month) || defaults.month
  activeWeek.value = Number(route.query.week) || defaults.week

  const routeDate = typeof route.query.date === 'string' ? parseDateKey(route.query.date) : null
  if (routeDate) {
    selectedDate.value = routeDate
  }

  if (activePeriod.value === 'month') {
    currentMonth.value = buildUtcDate(activeYear.value, activeMonth.value, 1)
    return
  }

  if (activePeriod.value === 'week') {
    const start = isoWeekStart(activeYear.value, activeWeek.value)
    currentMonth.value = buildUtcDate(start.getUTCFullYear(), start.getUTCMonth() + 1, 1)
    selectedDate.value = routeDate || start
    return
  }

  currentMonth.value = buildUtcDate(activeYear.value, currentMonth.value.getUTCMonth() + 1, 1)
}

function syncQuery({ year, month, week, period }) {
  const next = {
    ...route.query,
    year: String(year ?? activeYear.value),
    period: period ?? activePeriod.value,
  }

  if ((period ?? activePeriod.value) === 'month') {
    next.month = String(month ?? activeMonth.value)
    delete next.week
  } else if ((period ?? activePeriod.value) === 'week') {
    next.week = String(week ?? activeWeek.value)
    delete next.month
  } else {
    delete next.month
    delete next.week
  }

  router.replace({ query: next })
}

function buildUtcDate(year, month, day) {
  return new Date(Date.UTC(year, month - 1, day))
}

function formatUtcDateKey(date) {
  return [
    String(date.getUTCFullYear()),
    String(date.getUTCMonth() + 1).padStart(2, '0'),
    String(date.getUTCDate()).padStart(2, '0'),
  ].join('-')
}

function parseDateKey(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim())
  if (!match) return null

  const [, year, month, day] = match
  return buildUtcDate(Number(year), Number(month), Number(day))
}

function getIsoWeek(date) {
  const dt = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()))
  const dayNum = dt.getUTCDay() || 7
  dt.setUTCDate(dt.getUTCDate() + 4 - dayNum)
  const yearStart = new Date(Date.UTC(dt.getUTCFullYear(), 0, 1))

  return Math.ceil(((dt - yearStart) / 86400000 + 1) / 7)
}

function isoWeekStart(year, week) {
  const simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7))
  const dayOfWeek = simple.getUTCDay() || 7
  const monday = new Date(simple)

  if (dayOfWeek <= 4) {
    monday.setUTCDate(simple.getUTCDate() - dayOfWeek + 1)
  } else {
    monday.setUTCDate(simple.getUTCDate() + 8 - dayOfWeek)
  }

  monday.setUTCHours(0, 0, 0, 0)
  return monday
}
</script>

<style scoped src="./calendar/CalendarView.css"></style>
