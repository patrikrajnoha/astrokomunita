<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-1/2"></div>
      <div class="skeleton h-8 w-full"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa načítať</div>
      <div class="stateText">{{ error }}</div>
      <button type="button" class="eventGhostBtn" @click="fetchNextEvent">Skúsiť znova</button>
    </div>

    <div v-else-if="!nextEvent" class="state">
      <div class="stateTitle">{{ emptyTitle }}</div>
      <div class="stateText">{{ emptyText }}</div>
      <div class="panelActions">
        <router-link class="eventGhostBtn" :to="browseTo">{{ browseLabel }}</router-link>
      </div>
    </div>

    <div v-else class="eventCard">
      <div class="eventTitle">{{ nextEvent.title }}</div>
      <div v-if="typeLabel" class="eventType">{{ typeLabel }}</div>
      <time class="eventMeta" :datetime="eventDateTimeValue">{{ formatDateTime(nextEvent) }}</time>
      <div v-if="countdownLabel" class="eventCountdown">{{ countdownLabel }}</div>
      <p v-if="metaLine" class="eventSource">{{ metaLine }}</p>
      <router-link class="eventActionBtn" :to="`/events/${nextEvent.id}`">
        Detail
      </router-link>
    </div>
  </section>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { EVENT_TIMEZONE, formatEventDate, resolveEventTimeContext } from '@/utils/eventTime'

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
    const countdownLabel = computed(() => formatCountdown(eventDateTimeValue.value))
    const metaLine = computed(() => {
      const sourceLabel = formatEventSource(nextEvent.value?.source?.name)
      const updatedLabel = formatTime(nextEvent.value?.updated_at)
      const parts = []

      if (sourceLabel) {
        parts.push(`Zdroj: ${sourceLabel}`)
      }

      if (updatedLabel !== '-') {
        parts.push(`Aktualizované: ${updatedLabel}`)
      }

      return parts.join(' | ')
    })

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

    const formatDateTime = (event) => {
      const raw = event?.start_at || event?.max_at || event?.end_at
      if (!raw) return 'Termín bude upresnený'

      const dateLabel = formatEventDate(raw, EVENT_TIMEZONE, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
      const context = resolveEventTimeContext(event, EVENT_TIMEZONE)

      if (!context.showTimezoneLabel) {
        return `${dateLabel} - ${context.message}`
      }

      return `${dateLabel} - ${context.timeString} (${context.timezoneLabelShort})`
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
      formatDateTime,
      eventDateTimeValue,
      typeLabel,
      countdownLabel,
      metaLine,
    }
  },
}

function mapEventType(value) {
  const type = String(value || '').trim().toLowerCase()

  if (type === 'meteor_shower' || type === 'meteors') return 'Meteory'
  if (type === 'eclipse_solar') return 'Zatmenie Slnka'
  if (type === 'eclipse_lunar') return 'Zatmenie Mesiaca'
  if (type === 'planetary_event' || type === 'conjunction') return 'Planetárny úkaz'
  if (type === 'observation_window') return 'Pozorovacie okno'
  if (type === 'space_event' || type === 'mission') return 'Vesmírna udalosť'
  if (type === 'aurora') return 'Polárna žiara'
  if (type === 'asteroid') return 'Asteroid'
  if (type === 'comet') return 'Kometa'
  return ''
}

function formatEventSource(value) {
  const normalized = String(value || '').trim().toLowerCase()

  if (normalized === 'imo') return 'IMO'
  if (normalized === 'astropixels') return 'Astropixels'
  if (normalized === 'nasa') return 'USNO eclipse feed'
  if (normalized === 'nasa_wts' || normalized === 'nasa_watch_the_skies') return 'USNO moon phases'
  if (normalized === 'manual') return 'Databáza udalostí'

  return normalized || 'Databáza udalostí'
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

function formatTime(value) {
  const raw = String(value || '').trim()
  if (!raw) return '-'

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return '-'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  } catch {
    return '-'
  }
}
</script>

<style scoped>
.card {
  position: relative;
  border: 0;
  background: transparent;
  border-radius: 0;
  padding: 0;
  overflow: visible;
}

.panel {
  display: grid;
  gap: 0.24rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

.panelLoading {
  display: grid;
  gap: var(--sb-gap-xs, 0.3rem);
}

.eventCard {
  display: grid;
  gap: 0.24rem;
  min-width: 0;
}

.eventTitle {
  font-size: 0.86rem;
  font-weight: 800;
  color: #0f73ff;
  line-height: 1.18;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.eventMeta {
  color: var(--color-text-secondary);
  font-size: 0.74rem;
  line-height: 1.24;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.eventType,
.eventCountdown,
.eventSource {
  margin: 0;
}

.eventType {
  color: rgb(var(--color-primary-rgb) / 0.92);
  font-size: 0.68rem;
  font-weight: 700;
  line-height: 1.2;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.eventCountdown {
  color: var(--color-surface);
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.2;
}

.eventSource {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.25;
}

.panelActions {
  display: block;
  width: 100%;
  min-width: 0;
}

.eventActionBtn,
.eventGhostBtn {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  box-sizing: border-box;
  text-align: center;
  text-decoration: none;
  white-space: normal;
  overflow-wrap: anywhere;
  font-size: 0.72rem;
  line-height: 1.12;
  border-radius: 0 !important;
}

.eventActionBtn {
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.16);
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.eventActionBtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.28);
  transform: none;
}

.eventGhostBtn {
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
  box-shadow: inset 0 0 0 1px var(--color-text-secondary);
}

.eventGhostBtn:hover {
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
  box-shadow: inset 0 0 0 1px var(--color-primary);
  transform: none;
}

.stateTitle {
  font-size: 0.82rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.24;
}

.stateText {
  margin-top: 0.2rem;
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  line-height: 1.32;
}

.stateError .stateTitle,
.stateError .stateText {
  color: var(--color-danger);
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.h-4 {
  height: 1rem;
}

.w-2\/3 {
  width: 66.666667%;
}

.w-1\/2 {
  width: 50%;
}

.w-full {
  width: 100%;
}
</style>

