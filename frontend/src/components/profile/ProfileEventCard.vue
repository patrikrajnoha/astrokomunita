<template>
  <article class="eventCard">
    <div class="eventCard__cover" aria-hidden="true">
      <div class="eventCard__glow"></div>
      <div class="eventCard__status">
        <span class="eventCard__pill">{{ statusLabel }}</span>
        <span class="eventCard__pill eventCard__pill--muted">Sledujes</span>
      </div>
    </div>

    <div class="eventCard__body">
      <div class="eventCard__meta">
        <p class="eventCard__date">{{ formattedDate }}</p>
        <p class="eventCard__type">{{ typeLabel }}</p>
      </div>

      <h3 class="eventCard__title">{{ title }}</h3>
      <p class="eventCard__summary">{{ summary }}</p>

      <div class="eventCard__footer">
        <p class="eventCard__visibility">{{ visibilityLabel }}</p>
        <button type="button" class="eventCard__button" @click="$emit('open', event)">
          Otvorit detail
        </button>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import {
  EVENT_TIMEZONE,
  formatEventDate,
  formatEventDateKey,
  formatEventTime,
  getEventNowPeriodDefaults,
  resolveEventTimeContext,
} from '@/utils/eventTime'
import { eventDisplayShort, eventDisplayTitle } from '@/utils/translatedFields'

const props = defineProps({
  event: {
    type: Object,
    default: null,
  },
})

defineEmits(['open'])

const title = computed(() => {
  const value = eventDisplayTitle(props.event)
  return value === '-' ? 'Bez nazvu udalosti' : value
})

const summary = computed(() => {
  const value = eventDisplayShort(props.event)
  return value === '-' ? 'Popis doplnime neskor.' : value
})

const formattedDate = computed(() => formatDateRange(props.event))
const visibilityLabel = computed(() => mapVisibility(props.event?.visibility))
const statusLabel = computed(() => mapStatus(props.event))
const typeLabel = computed(() => mapType(props.event?.type))

function mapType(type) {
  const types = {
    meteor_shower: 'Meteoricky roj',
    eclipse_lunar: 'Zatmenie Mesiaca',
    eclipse_solar: 'Zatmenie Slnka',
    planetary_event: 'Planetarny ukaz',
    conjunction: 'Konjunkcia',
    comet: 'Kometa',
    other: 'Udalost',
  }

  return types[type] || 'Udalost'
}

function mapStatus(event) {
  const startRaw = event?.start_at || event?.starts_at || event?.max_at
  if (!startRaw) return 'Termin caka'

  const eventDateKey = formatEventDateKey(startRaw, EVENT_TIMEZONE)
  if (!eventDateKey) return 'Termin caka'

  const today = getEventNowPeriodDefaults(EVENT_TIMEZONE)
  const todayKey = `${today.year}-${String(today.month).padStart(2, '0')}-${String(today.day).padStart(2, '0')}`

  if (eventDateKey < todayKey) return 'Prebehlo'
  if (eventDateKey === todayKey) return 'Dnes'
  return 'Planovane'
}

function formatDateRange(event) {
  const startRaw = event?.start_at || event?.starts_at || event?.max_at
  const endRaw = event?.end_at || event?.ends_at

  const startLabel = formatLongDate(startRaw)
  if (!startLabel) {
    return 'Datum doplnime'
  }

  const context = resolveEventTimeContext(event, EVENT_TIMEZONE)
  if (!endRaw) {
    if (!context.showTimezoneLabel) {
      return `${startLabel} | ${context.message}`
    }

    return `${startLabel} | ${context.timeString} (${context.timezoneLabelShort})`
  }

  const sameDay = formatEventDateKey(startRaw, EVENT_TIMEZONE) === formatEventDateKey(endRaw, EVENT_TIMEZONE)
  if (sameDay) {
    if (!context.showTimezoneLabel) {
      return `${startLabel} | ${context.message}`
    }

    const endTime = formatEventTime(endRaw, EVENT_TIMEZONE).timeString
    if (!endTime) {
      return `${startLabel} | ${context.timeString} (${context.timezoneLabelShort})`
    }

    return `${startLabel} | ${context.timeString} - ${endTime} (${context.timezoneLabelShort})`
  }

  const endLabel = formatLongDate(endRaw)
  if (!endLabel) {
    return `${startLabel} | ${context.message}`
  }

  return `${startLabel} - ${endLabel}`
}

function formatLongDate(value) {
  if (!value) return ''

  const label = formatEventDate(value, EVENT_TIMEZONE, {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })

  return label === '-' ? '' : label
}

function mapVisibility(value) {
  if (value === 1 || value === '1') return 'Viditelne zo Slovenska'
  if (value === 0 || value === '0') return 'Neviditelne zo Slovenska'
  return 'Viditelnost upresnime'
}
</script>

<style scoped>
.eventCard {
  overflow: hidden;
  border-radius: 1.2rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background:
    linear-gradient(180deg, rgb(255 255 255 / 0.03), transparent 30%),
    linear-gradient(160deg, rgb(18 27 39 / 0.92), rgb(12 18 28 / 0.98));
  box-shadow: 0 24px 48px rgb(0 0 0 / 0.18);
}

.eventCard__cover {
  position: relative;
  min-height: 5.2rem;
  padding: 0.8rem 0.9rem 0;
  background:
    radial-gradient(circle at top left, rgb(var(--color-primary-rgb) / 0.28), transparent 52%),
    radial-gradient(circle at top right, rgb(116 143 189 / 0.16), transparent 46%);
}

.eventCard__glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(2px 2px at 18% 30%, rgb(255 255 255 / 0.5), transparent 60%),
    radial-gradient(2px 2px at 68% 22%, rgb(255 255 255 / 0.35), transparent 60%),
    radial-gradient(2px 2px at 84% 54%, rgb(255 255 255 / 0.22), transparent 60%);
  opacity: 0.9;
}

.eventCard__status {
  position: relative;
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.eventCard__pill {
  display: inline-flex;
  align-items: center;
  min-height: 1.8rem;
  padding: 0 0.72rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.28);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: rgb(236 243 252 / 0.96);
  font-size: 0.73rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.eventCard__pill--muted {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(255 255 255 / 0.06);
  color: rgb(255 255 255 / 0.72);
}

.eventCard__body {
  display: grid;
  gap: 0.85rem;
  padding: 1rem 0.95rem 1rem;
}

.eventCard__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 0.8rem;
  color: rgb(255 255 255 / 0.5);
  font-size: 0.78rem;
}

.eventCard__title {
  margin: 0;
  color: rgb(255 255 255 / 0.94);
  font-size: 1.05rem;
  line-height: 1.2;
  font-weight: 600;
}

.eventCard__summary {
  color: rgb(255 255 255 / 0.72);
  line-height: 1.6;
  font-size: 0.92rem;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.eventCard__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.eventCard__visibility {
  color: rgb(255 255 255 / 0.56);
  font-size: 0.8rem;
}

.eventCard__button {
  min-height: 2.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  background: rgb(255 255 255 / 0.04);
  color: rgb(255 255 255 / 0.92);
  padding: 0 0.95rem;
  font-size: 0.84rem;
  font-weight: 600;
  transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.eventCard__button:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.4);
  background: rgb(var(--color-primary-rgb) / 0.12);
  transform: translateY(-1px);
}
</style>
