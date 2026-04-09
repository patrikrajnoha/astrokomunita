<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="launch-list">
      <div v-for="i in 3" :key="i" class="skeleton-row">
        <div class="skeleton sk-name"></div>
        <div class="skeleton sk-countdown"></div>
        <div class="skeleton sk-meta"></div>
      </div>
    </div>

    <div v-else-if="error" class="state state--error">
      <div class="state-title">Nepodarilo sa načítať štarty</div>
      <div class="state-text">{{ error }}</div>
      <button type="button" class="retry-btn" @click="fetchPayload">Skúsiť znova</button>
    </div>

    <div v-else-if="!payload?.available" class="state">
      <div class="state-title">Dáta nedostupné</div>
      <div class="state-text">Launch Library 2 nevracia použiteľný prehľad štartov.</div>
    </div>

    <div v-else-if="items.length === 0" class="state">
      <div class="state-title">Žiadny blízky štart</div>
      <div class="state-text">V prehľade nie je žiadny potvrdený štart.</div>
    </div>

    <div v-else class="launch-list">
      <p v-if="payload?.stale" class="stale-notice">Posledný potvrdený prehľad</p>

      <article
        v-for="item in items"
        :key="itemKey(item)"
        class="launch-row"
      >
        <div class="launch-row__body">
          <div class="launch-name">
            <span
              class="status-dot"
              :class="statusToneClass(item)"
              :title="item?.status?.description || statusLabel(item)"
            ></span>
            <span class="name-text" :title="item.name">{{ displayLaunchTitle(item) }}</span>
          </div>
          <div v-if="formatCountdown(item)" class="launch-countdown">{{ formatCountdown(item) }}</div>
          <div class="launch-meta">{{ formatCompactMeta(item) }}</div>
        </div>
        <svg class="row-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </article>

      <p v-if="updatedLabel !== '-'" class="widget-footer">Aktualizované {{ updatedLabel }}</p>
    </div>
  </section>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'

export default {
  name: 'UpcomingLaunchesWidget',
  props: {
    title: {
      type: String,
      default: 'Štarty do vesmíru',
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
    const payload = ref(null)
    const loading = ref(false)
    const error = ref('')
    const hydratedFromBundle = ref(false)

    const items = computed(() => {
      const rows = Array.isArray(payload.value?.items) ? payload.value.items : []
      return rows.slice(0, 3)
    })

    const updatedLabel = computed(() => formatTime(payload.value?.updated_at))

    const applyPayload = (nextPayload) => {
      payload.value = nextPayload && typeof nextPayload === 'object' ? nextPayload : null
      error.value = ''
      loading.value = false
      hydratedFromBundle.value = true
    }

    const fetchPayload = async () => {
      loading.value = true
      error.value = ''
      payload.value = null

      try {
        applyPayload((await api.get('/sky/upcoming-launches', {
          meta: { skipErrorToast: true },
        }))?.data)
      } catch (requestError) {
        payload.value = null
        error.value = (
          requestError?.response?.data?.message
          || requestError?.message
          || 'Nepodarilo sa načítať prehľad štartov.'
        )
      } finally {
        loading.value = false
      }
    }

    watch(
      () => props.initialPayload,
      (nextPayload) => {
        if (nextPayload !== undefined) {
          applyPayload(nextPayload)
        }
      },
      { immediate: true },
    )

    watch(
      () => props.bundlePending,
      (pending, wasPending) => {
        if (pending || !wasPending || hydratedFromBundle.value) return
        fetchPayload()
      },
    )

    onMounted(() => {
      if (props.initialPayload !== undefined || props.bundlePending) {
        if (props.bundlePending && props.initialPayload === undefined) {
          loading.value = true
        }
        return
      }

      fetchPayload()
    })

    return {
      error,
      fetchPayload,
      items,
      loading,
      payload,
      updatedLabel,
      itemKey,
      displayLaunchTitle,
      formatCountdown,
      formatCompactMeta,
      statusLabel,
      statusToneClass,
    }
  },
}

function itemKey(item) {
  const id = String(item?.id || '').trim()
  const slug = String(item?.slug || '').trim()
  const name = String(item?.name || '').trim()
  return id || slug || name
}

function parseLaunchHeadline(item) {
  const rawName = String(item?.name || '').trim()
  if (!rawName) return { title: '', mission: null }

  const segments = rawName
    .split('|')
    .map((segment) => segment.trim())
    .filter(Boolean)

  if (segments.length <= 1) return { title: rawName, mission: null }

  const title = segments[0] || rawName
  const mission = segments.slice(1).join(' | ')

  if (mission.toLowerCase() === 'unknown payload') return { title, mission: null }

  return { title, mission }
}

function statusLabel(item) {
  return String(item?.status?.abbrev || item?.status?.label || '').trim()
}

function statusToneClass(item) {
  const normalized = statusLabel(item).toLowerCase()
  if (normalized === 'go') return 'dot--go'
  if (normalized === 'tbd' || normalized === 'tbc') return 'dot--tbd'
  if (normalized.includes('hold')) return 'dot--hold'
  if (normalized.includes('scrub')) return 'dot--scrub'
  return 'dot--tbd'
}

function formatCountdown(item) {
  const raw = String(item?.net || item?.window_start || '').trim()
  if (!raw) return ''

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return ''

  const diffMs = parsed.getTime() - Date.now()
  if (diffMs <= -30 * 60 * 1000) return ''
  if (diffMs < 0) return 'Práve prebieha'

  const diffMinutes = Math.round(diffMs / (60 * 1000))
  if (diffMinutes < 60) return `O ${diffMinutes} min`

  const diffHours = Math.round(diffMinutes / 60)
  if (diffHours < 48) return `O ${diffHours} h`

  const diffDays = Math.round(diffHours / 24)
  return `O ${diffDays} dni`
}

function formatCompactDate(item) {
  const raw = String(item?.net || item?.window_start || '').trim()
  if (!raw) return 'Čas sa upresňuje'

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return 'Čas sa upresňuje'

  try {
    const date = new Intl.DateTimeFormat('sk-SK', { day: 'numeric', month: 'numeric' }).format(parsed)
    const time = new Intl.DateTimeFormat('sk-SK', { hour: '2-digit', minute: '2-digit', hour12: false }).format(parsed)
    return `${date} o ${time}`
  } catch {
    return 'Čas sa upresňuje'
  }
}

function formatCompactMeta(item) {
  const datePart = formatCompactDate(item)
  const provider = String(item?.provider || '').trim()
  const location = String(item?.location || '').trim()
  const secondary = provider || location
  if (!secondary) return datePart

  const truncated = secondary.length > 22 ? `${secondary.slice(0, 20)}\u2026` : secondary
  return `${datePart} · ${truncated}`
}

function displayLaunchTitle(item) {
  return parseLaunchHeadline(item).title || String(item?.name || '').trim()
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
  gap: 0.5rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

/* ── Launch list ── */
.launch-list {
  display: grid;
  gap: 0.3rem;
}

/* ── Launch row ── */
.launch-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  background: rgb(var(--color-bg-rgb) / 0.18);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
  min-width: 0;
}

.launch-row:hover {
  background: rgb(var(--color-primary-rgb) / 0.07);
  border-color: rgb(var(--color-primary-rgb) / 0.22);
}

.launch-row__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.18rem;
}

/* ── Launch name line ── */
.launch-name {
  display: flex;
  align-items: center;
  gap: 0.38rem;
  min-width: 0;
}

.status-dot {
  flex-shrink: 0;
  width: 0.44rem;
  height: 0.44rem;
  border-radius: 50%;
  background: rgb(var(--color-text-secondary-rgb) / 0.45);
}

.status-dot.dot--go {
  background: rgb(var(--color-success-rgb) / 0.85);
}

.status-dot.dot--hold {
  background: rgb(var(--color-warning-rgb) / 0.85);
}

.status-dot.dot--scrub {
  background: rgb(var(--color-danger-rgb) / 0.85);
}

.status-dot.dot--tbd {
  background: rgb(var(--color-text-secondary-rgb) / 0.4);
}

.name-text {
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1.22;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Countdown ── */
.launch-countdown {
  color: var(--color-surface);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
  opacity: 0.9;
}

/* ── Meta line ── */
.launch-meta {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Chevron ── */
.row-chevron {
  flex-shrink: 0;
  width: 0.7rem;
  height: 0.7rem;
  color: var(--color-text-secondary);
  opacity: 0.45;
  transition: opacity 0.15s ease;
}

.launch-row:hover .row-chevron {
  opacity: 0.75;
}

/* ── Skeleton loading ── */
.skeleton-row {
  display: grid;
  gap: 0.22rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.08);
}

.skeleton {
  border-radius: 0.25rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.sk-name    { height: 0.75rem; width: 72%; }
.sk-countdown { height: 0.65rem; width: 30%; }
.sk-meta    { height: 0.6rem;  width: 88%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── States ── */
.state {
  display: grid;
  gap: 0.22rem;
  padding: 0.1rem 0;
}

.state-title {
  color: var(--color-surface);
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1.24;
}

.state-text {
  color: var(--color-text-secondary);
  font-size: 0.7rem;
  line-height: 1.28;
  margin: 0;
}

.state--error .state-title,
.state--error .state-text {
  color: var(--color-danger);
}

/* ── Retry button ── */
.retry-btn {
  display: inline-block;
  margin-top: 0.2rem;
  padding: 0.22rem 0.56rem;
  border-radius: 0.36rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.retry-btn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  color: var(--color-surface);
}

/* ── Stale notice ── */
.stale-notice {
  color: var(--color-warning);
  font-size: 0.66rem;
  line-height: 1.2;
  margin: 0;
  opacity: 0.8;
}

/* ── Footer ── */
.widget-footer {
  margin: 0.1rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.62rem;
  line-height: 1.2;
  opacity: 0.55;
  text-align: right;
}
</style>
