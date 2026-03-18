<template>
  <section class="panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <!-- Loading -->
    <div v-if="loading" class="skeletonCard">
      <div class="skeleton skW55"></div>
      <div class="skeleton skW40"></div>
      <div class="skeleton skW70"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="stateBox">
      <div class="stateName stateNameError">Nepodarilo sa načítať</div>
      <div class="stateSub">{{ error }}</div>
      <button type="button" class="inlineBtn" @click="fetchNextEvent">Skúsiť znova</button>
    </div>

    <!-- Empty -->
    <div v-else-if="!nextEvent" class="stateBox">
      <div class="stateName">{{ emptyTitle }}</div>
      <div class="stateSub">{{ emptyText }}</div>
      <router-link class="inlineBtn" :to="browseTo">{{ browseLabel }} →</router-link>
    </div>

    <!-- Event card — entire card is the link -->
    <router-link v-else class="eventCard" :to="`/events/${nextEvent.id}`">
      <!-- Event name: primary -->
      <p class="eventName">{{ nextEvent.title }}</p>
      <!-- Countdown: dominant emphasis -->
      <div v-if="countdownLabel" class="eventCountdown">{{ countdownLabel }}</div>
      <!-- Footer: icon + date + type -->
      <div class="eventFooter">
        <span class="eventIcon" aria-hidden="true">{{ eventIcon }}</span>
        <time class="eventDate" :datetime="eventDateTimeValue">{{ compactDateTime }}</time>
        <span v-if="typeLabel" class="eventType">{{ typeLabel }}</span>
      </div>
    </router-link>
  </section>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { EVENT_TIMEZONE } from '@/utils/eventTime'

const DATE_SHORT = new Intl.DateTimeFormat('sk-SK', {
  day: 'numeric',
  month: 'short',
  timeZone: EVENT_TIMEZONE,
})

const TIME_HM = new Intl.DateTimeFormat('sk-SK', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
  timeZone: EVENT_TIMEZONE,
})

const TIME_UPDATED = new Intl.DateTimeFormat('sk-SK', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

export default {
  name: 'NextEventWidget',
  props: {
    title: {
      type: String,
      default: 'Najbližšia udalosť',
    },
    endpoint: {
      type: String,
      default: '/events/next',
    },
    emptyTitle: {
      type: String,
      default: 'Zatiaľ žiadna udalosť',
    },
    emptyText: {
      type: String,
      default: 'Pozri kalendár alebo udalosti.',
    },
    browseLabel: {
      type: String,
      default: 'Všetky udalosti',
    },
    browseTo: {
      type: String,
      default: '/events',
    },
    initialPayload: {
      type: Object,
      default: undefined,
    },
    bundlePending: {
      type: Boolean,
      default: false,
    },
  },
  setup(props) {
    const nextEvent = ref(null)
    const loading = ref(true)
    const error = ref(null)
    const hydratedFromBundle = ref(false)

    const eventDateTimeValue = computed(() => (
      String(nextEvent.value?.start_at || nextEvent.value?.max_at || nextEvent.value?.end_at || '').trim()
    ))

    const typeLabel = computed(() => mapEventType(nextEvent.value?.type))
    const eventIcon = computed(() => mapEventIcon(nextEvent.value?.type))
    const countdownLabel = computed(() => formatCountdown(eventDateTimeValue.value))
    const compactDateTime = computed(() => formatCompactDateTime(eventDateTimeValue.value))
    const updatedLabel = computed(() => formatUpdatedTime(nextEvent.value?.updated_at))

    const applyPayload = (payload) => {
      const ev = payload?.data ?? payload?.event ?? payload

      const isEmptyObject =
        ev && typeof ev === 'object' && !Array.isArray(ev) && Object.keys(ev).length === 0

      const isEmptyArray = Array.isArray(ev) && ev.length === 0

      if (!ev || isEmptyObject || isEmptyArray) {
        nextEvent.value = null
      } else if (Array.isArray(ev)) {
        nextEvent.value = ev[0] ?? null
      } else if (!ev.title || !ev.id) {
        nextEvent.value = null
      } else {
        nextEvent.value = ev
      }

      error.value = null
      loading.value = false
      hydratedFromBundle.value = true
    }

    const fetchNextEvent = async () => {
      loading.value = true
      error.value = null
      nextEvent.value = null

      try {
        const res = await api.get(props.endpoint)
        applyPayload(res?.data)
      } catch (err) {
        error.value =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať najbližšiu udalosť.'
      } finally {
        loading.value = false
      }
    }

    watch(
      () => props.initialPayload,
      (payload) => {
        if (payload !== undefined) {
          applyPayload(payload)
        }
      },
      { immediate: true },
    )

    watch(
      () => props.bundlePending,
      (pending, wasPending) => {
        if (pending || !wasPending || hydratedFromBundle.value) return
        fetchNextEvent()
      },
    )

    onMounted(() => {
      if (props.initialPayload !== undefined || props.bundlePending) {
        if (props.bundlePending && props.initialPayload === undefined) {
          loading.value = true
        }
        return
      }

      fetchNextEvent()
    })

    return {
      nextEvent,
      loading,
      error,
      fetchNextEvent,
      eventDateTimeValue,
      typeLabel,
      eventIcon,
      countdownLabel,
      compactDateTime,
      updatedLabel,
    }
  },
}

function mapEventType(value) {
  const type = String(value || '').trim().toLowerCase()

  if (type === 'eclipse_solar') return 'Zatmenie Slnka'
  if (type === 'eclipse_lunar') return 'Zatmenie Mesiaca'
  if (type === 'meteor_shower' || type === 'meteors') return 'Meteorický roj'
  if (type === 'planetary_event' || type === 'conjunction') return 'Planetárny úkaz'
  if (type === 'observation_window') return 'Pozorovacie okno'
  if (type === 'space_event' || type === 'mission') return 'Vesmírna udalosť'
  if (type === 'aurora') return 'Polárna žiara'
  if (type === 'asteroid') return 'Asteroid'
  if (type === 'comet') return 'Kometa'
  return ''
}

function mapEventIcon(value) {
  const type = String(value || '').trim().toLowerCase()

  if (type === 'eclipse_solar') return '🌑'
  if (type === 'eclipse_lunar') return '🌕'
  if (type === 'meteor_shower' || type === 'meteors') return '☄️'
  if (type === 'aurora') return '🌌'
  if (type === 'comet') return '☄️'
  if (type === 'asteroid') return '🪨'
  if (type === 'planetary_event' || type === 'conjunction') return '🪐'
  if (type === 'space_event' || type === 'mission') return '🚀'
  return '✨'
}

function formatCompactDateTime(value) {
  const raw = String(value || '').trim()
  if (!raw) return 'Termín bude upresnený'

  const d = new Date(raw)
  if (Number.isNaN(d.getTime())) return 'Termín bude upresnený'

  try {
    const datePart = DATE_SHORT.format(d)
    const timePart = TIME_HM.format(d)
    return `${datePart} · ${timePart}`
  } catch {
    return 'Termín bude upresnený'
  }
}

function formatCountdown(value) {
  const raw = String(value || '').trim()
  if (!raw) return ''

  const target = new Date(raw)
  if (Number.isNaN(target.getTime())) return ''

  const diffMs = target.getTime() - Date.now()
  if (diffMs <= 0) return 'Prebieha alebo už prebehla'

  const dayMs = 24 * 60 * 60 * 1000
  const hourMs = 60 * 60 * 1000
  const minuteMs = 60 * 1000
  const days = Math.floor(diffMs / dayMs)

  if (days >= 1) {
    return days === 1 ? 'Za 1 deň' : days <= 4 ? `Za ${days} dni` : `Za ${days} dní`
  }

  const hours = Math.floor(diffMs / hourMs)
  if (hours >= 1) {
    return hours === 1 ? 'Za 1 hodinu' : hours <= 4 ? `Za ${hours} hodiny` : `Za ${hours} hodín`
  }

  const minutes = Math.max(1, Math.floor(diffMs / minuteMs))
  return minutes === 1 ? 'Za 1 minútu' : minutes <= 4 ? `Za ${minutes} minúty` : `Za ${minutes} minút`
}

function formatUpdatedTime(value) {
  const raw = String(value || '').trim()
  if (!raw) return ''

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return ''

  try {
    return TIME_UPDATED.format(parsed)
  } catch {
    return ''
  }
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.44rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

/* ── Skeleton ── */
.skeletonCard {
  display: grid;
  gap: 0.3rem;
  padding: 0.52rem 0.56rem;
  border-radius: 0.56rem;
  background: rgb(var(--color-bg-rgb) / 0.16);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.08);
}

.skeleton {
  height: 0.72rem;
  border-radius: 0.25rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

.skW55 { width: 55%; }
.skW40 { width: 40%; }
.skW70 { width: 70%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── State boxes (empty / error) ── */
.stateBox {
  display: grid;
  gap: 0.2rem;
}

.stateName {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--color-surface);
  line-height: 1.22;
}

.stateNameError {
  color: var(--color-danger);
}

.stateSub {
  color: var(--color-text-secondary);
  font-size: 0.74rem;
  line-height: 1.3;
}

.inlineBtn {
  display: inline;
  color: rgb(var(--color-primary-rgb) / 0.85);
  font-size: 0.74rem;
  font-weight: 600;
  line-height: 1.2;
  text-decoration: none;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  margin-top: 0.1rem;
  text-align: left;
}

.inlineBtn:hover {
  color: var(--color-primary);
  text-decoration: underline;
}

/* ── Event card ── */
.eventCard {
  display: grid;
  gap: 0;
  text-decoration: none;
  padding: 0.58rem 0.64rem;
  border-radius: 0.6rem;
  background: rgb(var(--color-bg-rgb) / 0.14);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.07);
  transition: background 0.14s ease, border-color 0.14s ease;
  min-width: 0;
}

.eventCard:hover {
  background: rgb(var(--color-primary-rgb) / 0.06);
  border-color: rgb(var(--color-primary-rgb) / 0.16);
}

/* ── Event name: primary ── */
.eventName {
  margin: 0 0 0.22rem;
  color: var(--color-surface);
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.25;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

/* ── Countdown: dominant ── */
.eventCountdown {
  color: var(--color-surface);
  font-size: 1.38rem;
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.02em;
  margin-bottom: 0.22rem;
}

/* ── Footer: icon + date + type ── */
.eventFooter {
  display: flex;
  align-items: center;
  gap: 0.32rem;
  min-width: 0;
  flex-wrap: nowrap;
  overflow: hidden;
}

.eventIcon {
  font-size: 0.8rem;
  flex-shrink: 0;
  line-height: 1;
  opacity: 0.75;
}

.eventDate {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  font-weight: 400;
  line-height: 1.22;
  white-space: nowrap;
  flex-shrink: 0;
}

.eventType {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  font-weight: 400;
  line-height: 1.22;
  opacity: 0.7;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eventType::before {
  content: '\00B7';
  margin-right: 0.32rem;
  opacity: 0.5;
}
</style>
