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
            <button type="button" class="nav-btn" @click="prevMonth">‹</button>
            <button type="button" class="nav-btn" @click="goToToday">Dnes</button>
            <button type="button" class="nav-btn" @click="nextMonth">›</button>
            <button type="button" class="nav-btn" @click="prevYear">«</button>
            <button type="button" class="nav-btn" @click="nextYear">»</button>
          </div>
        </header>

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
            v-for="(cell, i) in dayCells"
            :key="i"
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
          <li v-for="ev in selectedEvents" :key="ev.id" class="event-item">
            <span :class="['dot', typeDot(ev.type)]" aria-hidden="true"></span>
            <div>
              <div class="event-title">{{ ev.title }}</div>
              <div class="event-time">{{ formatEventTime(ev) }}</div>
            </div>
          </li>
          <li v-if="selectedEvents.length === 0" class="event-empty">
            Žiadne udalosti v tento deň.
          </li>
        </ul>
      </aside>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

const route = useRoute()
const today = new Date()
const currentMonth = ref(new Date(today.getFullYear(), today.getMonth(), 1))
const selectedDate = ref(new Date(today.getFullYear(), today.getMonth(), today.getDate()))
const events = ref([])

const monthLabel = computed(() =>
  currentMonth.value.toLocaleDateString('sk-SK', { month: 'long' })
)
const yearLabel = computed(() => currentMonth.value.getFullYear())

const selectedLabel = computed(() =>
  selectedDate.value.toLocaleDateString('sk-SK', {
    weekday: 'long',
    day: 'numeric',
  })
)

const dayCells = computed(() => {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const firstDay = new Date(year, month, 1).getDay() // 0 = Sunday
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const cells = []

  for (let i = 0; i < firstDay; i += 1) {
    cells.push({ date: null, day: null, muted: true, blank: true })
  }

  for (let d = 1; d <= daysInMonth; d += 1) {
    const date = new Date(year, month, d)
    cells.push({
      date,
      day: d,
      muted: false,
      blank: false,
      isSunday: date.getDay() === 0,
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
  events.value.forEach((ev) => {
    const key = toYMD(ev.starts_at)
    if (!map[key]) map[key] = []
    map[key].push(ev)
  })
  return map
})

const selectedEvents = computed(() => {
  const key = toYMD(selectedDate.value)
  return eventsByDay.value[key] || []
})

function selectDate(date) {
  selectedDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

function dayCellClass(cell) {
  if (!cell.date) return 'day-cell--blank'
  const isSelected = isSameDay(cell.date, selectedDate.value)
  const isToday = isSameDay(cell.date, today)
  const hasEvents = !!eventsByDay.value[toYMD(cell.date)]?.length
  return {
    'day-cell--selected': isSelected,
    'day-cell--today': !isSelected && isToday,
    'day-cell--sun': cell.isSunday,
    'day-cell--has-events': hasEvents,
  }
}

function dayEventCount(date) {
  return eventsByDay.value[toYMD(date)]?.length || 0
}

function cellTooltip(cell) {
  if (!cell.date) return ''
  const items = eventsByDay.value[toYMD(cell.date)] || []
  if (!items.length) return ''
  return items
    .slice(0, 2)
    .map((e) => e.title)
    .join(' • ')
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

function formatEventTime(ev) {
  if (ev.all_day) return 'Celý deň'
  const start = ev.starts_at ? new Date(ev.starts_at) : null
  const end = ev.ends_at ? new Date(ev.ends_at) : null
  if (!start) return '—'
  const startStr = start.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
  if (!end) return startStr
  const endStr = end.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
  return `${startStr} – ${endStr}`
}

function isSameDay(a, b) {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  )
}

function toYMD(date) {
  const d = new Date(date)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

async function fetchMonthEvents() {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const from = toYMD(new Date(year, month, 1))
  const to = toYMD(new Date(year, month + 1, 0))
  const res = await api.get('/events', { params: { from, to } })
  const rows = Array.isArray(res.data?.data) ? res.data.data : res.data
  events.value = Array.isArray(rows) ? rows : []
}

function prevMonth() {
  currentMonth.value = new Date(
    currentMonth.value.getFullYear(),
    currentMonth.value.getMonth() - 1,
    1
  )
  fetchMonthEvents()
}

function nextMonth() {
  currentMonth.value = new Date(
    currentMonth.value.getFullYear(),
    currentMonth.value.getMonth() + 1,
    1
  )
  fetchMonthEvents()
}

function prevYear() {
  currentMonth.value = new Date(
    currentMonth.value.getFullYear() - 1,
    currentMonth.value.getMonth(),
    1
  )
  fetchMonthEvents()
}

function nextYear() {
  currentMonth.value = new Date(
    currentMonth.value.getFullYear() + 1,
    currentMonth.value.getMonth(),
    1
  )
  fetchMonthEvents()
}

function goToToday() {
  currentMonth.value = new Date(today.getFullYear(), today.getMonth(), 1)
  selectedDate.value = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  fetchMonthEvents()
}

onMounted(() => {
  applyRouteDate()
  fetchMonthEvents()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})

watch(
  () => route.query.date,
  () => {
    applyRouteDate()
    fetchMonthEvents()
  }
)

function handleKeydown(event) {
  if (event.key === 'ArrowLeft') {
    prevMonth()
  } else if (event.key === 'ArrowRight') {
    nextMonth()
  }
}

function applyRouteDate() {
  const q = route.query.date
  if (!q || typeof q !== 'string') return
  const d = new Date(q)
  if (isNaN(d.getTime())) return
  currentMonth.value = new Date(d.getFullYear(), d.getMonth(), 1)
  selectedDate.value = new Date(d.getFullYear(), d.getMonth(), d.getDate())
}
</script>

<style>
.calendar-card {
  --bg: var(--color-bg);
  --bg-soft: var(--color-bg);
  --text: var(--color-surface);
  --text-dim: var(--color-text-secondary);
  --text-muted: var(--color-text-secondary);
  --sun: var(--color-primary);
  --divider: rgb(var(--color-surface-rgb) / 0.18);
  --shadow: 0 20px 50px rgb(var(--color-bg-rgb) / 0.45);
  background: linear-gradient(145deg, var(--bg), var(--bg-soft));
  border-radius: 22px;
  box-shadow: var(--shadow);
  color: var(--text);
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  padding: clamp(14px, 2.2vw, 24px);
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
  padding-right: 22px;
  min-width: 0;
}

.month-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
  gap: 12px;
  flex-wrap: wrap;
}

.month-title {
  font-size: clamp(1.35rem, 2.5vw, 1.75rem);
  font-weight: 700;
  letter-spacing: 0.2px;
  line-height: 1.1;
  text-transform: capitalize;
}

.month-meta {
  font-size: 13px;
  color: var(--text-dim);
  font-weight: 500;
}

.month-actions {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.nav-btn {
  appearance: none;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  background: rgb(var(--color-surface-rgb) / 0.04);
  color: var(--text);
  border-radius: 999px;
  min-height: 36px;
  padding: 7px 13px;
  font-size: 12px;
  letter-spacing: 0.2px;
  cursor: pointer;
}

.nav-btn:hover {
  background: rgb(var(--color-surface-rgb) / 0.08);
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
  row-gap: 10px;
  column-gap: 4px;
  font-size: 16px;
  line-height: 1;
}

.day-cell {
  appearance: none;
  border: none;
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
  background: var(--color-surface);
  color: var(--color-bg);
  font-weight: 700;
}

.day-cell--today {
  outline: 1px solid rgb(var(--color-surface-rgb) / 0.35);
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
  background: rgb(var(--color-bg-rgb) / 0.95);
  color: var(--color-surface);
  font-size: 11px;
  padding: 6px 8px;
  border-radius: 8px;
  white-space: nowrap;
  z-index: 10;
  box-shadow: 0 10px 25px rgb(var(--color-bg-rgb) / 0.35);
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
  padding-left: 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
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
  gap: 16px;
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
    border-radius: 16px;
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
