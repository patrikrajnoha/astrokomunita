<template>
  <section class="calendar-card">
    <div class="calendar-layout">
      <div class="left">
        <header class="month-header">
          <div>
            <div class="month-title">{{ monthLabel }}</div>
            <div class="month-meta">{{ yearLabel }}</div>
          </div>
          <div class="month-actions">
            <button type="button" class="nav-btn" @click="prevMonth">&lsaquo;</button>
            <button type="button" class="nav-btn" @click="goToToday">Dnes</button>
            <button type="button" class="nav-btn" @click="nextMonth">&rsaquo;</button>
            <button type="button" class="nav-btn" @click="prevYear">&laquo;</button>
            <button type="button" class="nav-btn" @click="nextYear">&raquo;</button>
          </div>
        </header>

        <p v-if="loading" class="month-meta">Nacitavam udalosti...</p>
        <p v-else-if="error" class="month-meta" style="color: var(--color-danger)">{{ error }}</p>

        <div class="dow-grid">
          <span class="dow sun">S</span>
          <span class="dow">M</span>
          <span class="dow">T</span>
          <span class="dow">W</span>
          <span class="dow">T</span>
          <span class="dow">F</span>
          <span class="dow sun">S</span>
        </div>

        <div class="days-grid">
          <button
            v-for="(cell, index) in dayCells"
            :key="index"
            type="button"
            class="day-cell"
            :class="dayCellClass(cell)"
            :data-tip="cellTooltip(cell)"
            :disabled="!cell.date"
            @click="cell.date && selectDate(cell.date)"
          >
            <span v-if="cell.date">{{ cell.day }}</span>
            <span v-if="cell.date && dayEventCount(cell.date)" class="count-badge">
              {{ dayEventCount(cell.date) }}
            </span>
          </button>
        </div>
      </div>

      <div class="divider" aria-hidden="true"></div>

      <aside class="right">
        <header class="list-header">
          <div class="list-title">{{ selectedLabel }}</div>
          <div class="list-subtitle">{{ selectedEvents.length }} udalosti</div>
        </header>

        <ul class="event-list">
          <li
            v-for="event in selectedEvents"
            :key="event.id"
            class="event-item"
            role="button"
            tabindex="0"
            @click="openEventDetail(event)"
            @keydown.enter.prevent="openEventDetail(event)"
            @keydown.space.prevent="openEventDetail(event)"
          >
            <span :class="['dot', typeDot(event.type)]" aria-hidden="true"></span>
            <div>
              <div class="event-title">{{ event.title }}</div>
              <div class="event-time">{{ formatEventTime(event) }}</div>
            </div>
          </li>
          <li v-if="selectedEvents.length === 0" class="event-empty">
            Ziadne udalosti v tento den.
          </li>
        </ul>
      </aside>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
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
  const daysInMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate()
  const cells = []

  for (let index = 0; index < firstDay; index += 1) {
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

  return items
    .slice(0, 2)
    .map((event) => event.title)
    .join(' · ')
}

function typeDot(type) {
  const map = {
    meteor_shower: 'dot-blue',
    eclipse_lunar: 'dot-amber',
    eclipse_solar: 'dot-amber',
    planetary_event: 'dot-violet',
    other: 'dot-blue',
  }

  return map[type] || 'dot-blue'
}

function formatEventTime(event) {
  if (event.all_day) return 'Cely den'

  const context = resolveEventTimeContext(event, EVENT_TIMEZONE)
  if (!context.showTimezoneLabel) {
    return context.message
  }

  const anchorKey = toYMD(event.start_at || event.starts_at || event.max_at)
  const endRaw = event.end_at || event.ends_at
  const endKey = toYMD(endRaw)

  if (endRaw && anchorKey && anchorKey === endKey) {
    const endTime = formatClockTime(endRaw, EVENT_TIMEZONE).timeString
    if (endTime) {
      return `${context.timeString} - ${endTime} (${context.timezoneLabelShort})`
    }
  }

  return `${context.timeString} (${context.timezoneLabelShort})`
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
    error.value = err?.response?.data?.message || 'Nepodarilo sa nacitat udalosti.'
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

<style>
.calendar-card {
  --bg: #151d28;
  --bg-soft: #151d28;
  --text: var(--color-surface);
  --text-dim: var(--color-text-secondary);
  --text-muted: var(--color-text-secondary);
  --sun: var(--color-primary);
  --divider: rgb(var(--color-text-secondary-rgb) / 0.16);
  background: var(--bg);
  border: 1px solid var(--divider);
  border-radius: 1rem;
  color: var(--text);
  font-family: inherit;
  padding: clamp(14px, 2vw, 20px);
  width: 100%;
  overflow: hidden;
}

.calendar-card,
.calendar-card * {
  box-sizing: border-box;
}

.calendar-layout {
  display: grid;
  grid-template-columns: 1.15fr 1px 0.85fr;
  gap: 0;
  min-width: 0;
}

.left {
  padding-right: 20px;
  min-width: 0;
}

.month-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  gap: 12px;
  flex-wrap: wrap;
}

.month-title {
  font-size: clamp(1.2rem, 2.3vw, 1.55rem);
  font-weight: 600;
  letter-spacing: 0.2px;
  line-height: 1.1;
  text-transform: capitalize;
}

.month-meta {
  font-size: 12px;
  color: var(--text-dim);
  font-weight: 500;
}

.month-actions {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}

.nav-btn {
  appearance: none;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background: rgb(255 255 255 / 0.04);
  color: var(--text);
  border-radius: 999px;
  min-height: 34px;
  padding: 7px 12px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.1px;
  cursor: pointer;
}

.nav-btn:hover {
  background: rgb(255 255 255 / 0.07);
}

.dow-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  grid-auto-rows: 1fr;
  font-size: 12px;
  letter-spacing: 1px;
  color: var(--text-dim);
  margin-bottom: 10px;
  text-transform: uppercase;
}

.dow {
  text-align: center;
}

.days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  grid-auto-rows: minmax(36px, auto);
  row-gap: 8px;
  column-gap: 4px;
  font-size: 16px;
  line-height: 1;
}

.day-cell {
  appearance: none;
  border: 1px solid transparent;
  background: transparent;
  color: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  min-height: 36px;
  padding: 8px 0;
  border-radius: 999px;
  position: relative;
}

.day-cell--selected {
  background: rgb(var(--color-surface-rgb) / 0.92);
  color: var(--bg);
  font-weight: 700;
}

.day-cell--today {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.22);
}

.day-cell--muted {
  color: var(--text-muted);
}

.day-cell--blank {
  color: transparent;
  cursor: default;
}

.day-cell--sun,
.sun {
  color: var(--sun);
}

.day-cell--has-events::after {
  content: '';
  width: 5px;
  height: 5px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.55);
  position: absolute;
  transform: translateY(14px);
}

.day-cell--selected.day-cell--has-events::after {
  background: rgb(var(--color-bg-rgb) / 0.6);
}

.count-badge {
  position: absolute;
  top: -4px;
  right: -2px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.14);
  color: var(--text);
  font-size: 10px;
  line-height: 16px;
  text-align: center;
  pointer-events: none;
}

.day-cell--selected .count-badge {
  background: rgb(var(--color-bg-rgb) / 0.65);
  color: var(--color-surface);
}

.day-cell[data-tip]:hover::before {
  content: attr(data-tip);
  position: absolute;
  bottom: 125%;
  left: 50%;
  transform: translateX(-50%);
  background: rgb(var(--color-bg-rgb) / 0.96);
  color: var(--color-surface);
  font-size: 11px;
  padding: 6px 8px;
  border-radius: 8px;
  white-space: nowrap;
  z-index: 10;
  max-width: min(240px, 92vw);
  overflow: hidden;
  text-overflow: ellipsis;
}

.day-cell[data-tip]:hover::after {
  content: '';
  position: absolute;
  bottom: 112%;
  left: 50%;
  transform: translateX(-50%);
  border: 6px solid transparent;
  border-top-color: rgb(var(--color-bg-rgb) / 0.95);
}

.divider {
  width: 1px;
  background: var(--divider);
}

.right {
  padding-left: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}

.list-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.list-title {
  font-size: 16px;
  font-weight: 600;
}

.list-subtitle {
  font-size: 12px;
  color: var(--text-dim);
}

.event-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 420px;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding-right: 4px;
}

.event-item {
  display: grid;
  grid-template-columns: 10px 1fr;
  column-gap: 10px;
  align-items: start;
  cursor: pointer;
  border-radius: 0.8rem;
  border: 1px solid transparent;
  padding: 0.55rem 0.65rem;
}

.event-item:hover {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.16);
  background: rgb(255 255 255 / 0.03);
}

.event-item:focus-visible {
  outline: 2px solid rgb(var(--color-surface-rgb) / 0.35);
  outline-offset: 2px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 6px;
  background: rgb(var(--color-primary-rgb) / 0.35);
}

.dot-blue {
  background: rgb(var(--color-primary-rgb) / 0.35);
}

.dot-amber {
  background: rgb(var(--color-primary-rgb) / 0.35);
}

.dot-violet {
  background: rgb(var(--color-primary-rgb) / 0.35);
}

.event-title {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.2px;
  line-height: 1.35;
}

.event-time {
  font-size: 12px;
  color: var(--text-dim);
  margin-top: 4px;
}

.event-empty {
  color: var(--text-dim);
  font-size: 13px;
  padding: 6px 0;
}

@media (max-width: 900px) {
  .calendar-layout {
    grid-template-columns: 1fr;
    gap: 14px;
  }

  .divider {
    width: 100%;
    height: 1px;
  }

  .left {
    padding-right: 0;
  }

  .right {
    padding-left: 0;
  }

  .event-list {
    max-height: 280px;
  }
}

@media (max-width: 640px) {
  .calendar-card {
    border-radius: 1rem;
  }

  .month-header {
    margin-bottom: 14px;
    gap: 10px;
  }

  .month-meta {
    font-size: 12px;
  }

  .month-actions {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px;
  }

  .nav-btn {
    width: 100%;
    min-height: 34px;
    padding: 6px 8px;
    font-size: 11px;
  }

  .dow-grid {
    font-size: 11px;
    margin-bottom: 8px;
  }

  .days-grid {
    grid-auto-rows: minmax(32px, auto);
    row-gap: 8px;
    font-size: 14px;
  }

  .day-cell {
    min-height: 32px;
    padding: 6px 0;
  }

  .day-cell--has-events::after {
    transform: translateY(12px);
  }

  .count-badge {
    top: -3px;
    right: -1px;
    min-width: 14px;
    height: 14px;
    line-height: 14px;
    font-size: 9px;
  }

  .list-title {
    font-size: 14px;
  }

  .list-subtitle {
    font-size: 11px;
  }

  .event-title {
    font-size: 13px;
  }

  .event-list {
    max-height: 240px;
    gap: 12px;
  }
}

@media (hover: none) {
  .day-cell[data-tip]:hover::before,
  .day-cell[data-tip]:hover::after {
    content: none;
  }
}
</style>
