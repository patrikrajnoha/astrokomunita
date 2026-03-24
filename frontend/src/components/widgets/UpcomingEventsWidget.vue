<template>
  <section class="panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <!-- Loading: 4 skeleton rows -->
    <div v-if="loading" class="skeletonStack">
      <div class="skeleton skW52"></div>
      <div class="skeleton skW66"></div>
      <div class="skeleton skW44"></div>
      <div class="skeleton skW58"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="stateBox">
      <div class="stateError">{{ loadErrorTitle }}</div>
      <button type="button" class="retryBtn" @click="fetchItems">Skúsiť znova</button>
    </div>

    <!-- Empty -->
    <p v-else-if="!items.length" class="emptyText">Žiadne blízke udalosti.</p>

    <!-- List: date · icon title — max 4 items -->
    <ul v-else class="eventsList">
      <li v-for="event in items.slice(0, 4)" :key="event.id" class="eventItem">
        <router-link :to="`/events/${event.id}`" class="eventRow">
          <time class="eventRowDate" :datetime="event.start_at || undefined">{{ shortDate(event.start_at) }}</time>
          <span class="eventRowSep" aria-hidden="true">·</span>
          <span class="eventRowIcon" aria-hidden="true">{{ eventIcon(event.type) }}</span>
          <span class="eventRowTitle">{{ event.title }}</span>
        </router-link>
      </li>
    </ul>

    <!-- Footer link -->
    <router-link class="showAllLink" :to="showMoreTo">{{ showMoreLabel }}</router-link>
  </section>
</template>

<script>
import { onMounted, ref, watch } from 'vue'
import { getUpcomingEventsWidget } from '@/services/widgets'
import { EVENT_TIMEZONE } from '@/utils/eventTime'

const SHORT_DATE = new Intl.DateTimeFormat('sk-SK', {
  day: 'numeric',
  month: 'short',
  timeZone: EVENT_TIMEZONE,
})

export default {
  name: 'UpcomingEventsWidget',
  props: {
    title: {
      type: String,
      default: 'Udalosti v kalendári',
    },
    showMoreLabel: {
      type: String,
      default: 'Zobraziť všetko →',
    },
    showMoreTo: {
      type: String,
      default: '/events',
    },
    loadErrorTitle: {
      type: String,
      default: 'Nepodarilo sa načítať',
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
    const items = ref([])
    const loading = ref(true)
    const error = ref('')
    const hydratedFromBundle = ref(false)

    const applyPayload = (payload) => {
      items.value = Array.isArray(payload?.items) ? payload.items.slice(0, 4) : []
      error.value = ''
      loading.value = false
      hydratedFromBundle.value = true
    }

    const fetchItems = async () => {
      loading.value = true
      error.value = ''
      try {
        applyPayload(await getUpcomingEventsWidget())
      } catch (err) {
        error.value = err?.response?.data?.message || err?.message || 'Skús to neskôr.'
      } finally {
        loading.value = false
      }
    }

    const shortDate = (value) => {
      const raw = String(value || '').trim()
      if (!raw) return '-'
      const d = new Date(raw)
      if (Number.isNaN(d.getTime())) return '-'
      try { return SHORT_DATE.format(d) } catch { return '-' }
    }

    watch(
      () => props.initialPayload,
      (payload) => { if (payload !== undefined) applyPayload(payload) },
      { immediate: true },
    )

    watch(
      () => props.bundlePending,
      (pending, wasPending) => {
        if (pending || !wasPending || hydratedFromBundle.value) return
        fetchItems()
      },
    )

    onMounted(() => {
      if (props.initialPayload !== undefined || props.bundlePending) {
        if (props.bundlePending && props.initialPayload === undefined) loading.value = true
        return
      }
      fetchItems()
    })

    return { items, loading, error, fetchItems, shortDate, eventIcon }
  },
}

function eventIcon(type) {
  const t = String(type || '').trim().toLowerCase()
  if (t === 'eclipse_solar')                    return '🌑'
  if (t === 'eclipse_lunar')                    return '🌕'
  if (t === 'meteor_shower' || t === 'meteors') return '☄️'
  if (t === 'aurora')                           return '🌌'
  if (t === 'comet')                            return '☄️'
  if (t === 'asteroid')                         return '🪨'
  if (t === 'planetary_event' || t === 'conjunction') return '🪐'
  if (t === 'space_event' || t === 'mission')   return '🚀'
  if (t === 'observation_window')               return '🔭'
  return '✨'
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.36rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
  margin: 0;
}

/* ── Skeleton ── */
.skeletonStack {
  display: grid;
  gap: 0.32rem;
}

.skeleton {
  height: 0.68rem;
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

.skW52 { width: 52%; }
.skW66 { width: 66%; }
.skW44 { width: 44%; }
.skW58 { width: 58%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── State boxes ── */
.stateBox {
  display: grid;
  gap: 0.2rem;
}

.stateError {
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--color-danger, #f87171);
  line-height: 1.3;
}

.retryBtn {
  display: inline;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  color: rgb(var(--color-primary-rgb) / 0.85);
  font-size: 0.72rem;
  font-weight: 600;
  text-align: left;
}

.retryBtn:hover {
  color: var(--color-primary);
  text-decoration: underline;
}

.emptyText {
  margin: 0;
  font-size: 0.76rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
}

/* ── Event list ── */
.eventsList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0;
}

.eventItem {
  display: block;
}

/* Single-line row: date · icon title */
.eventRow {
  display: flex;
  align-items: baseline;
  gap: 0.26rem;
  text-decoration: none;
  padding: 0.24rem 0;
  min-width: 0;
}

.eventRowDate {
  color: var(--color-text-secondary);
  font-size: 0.70rem;
  font-weight: 400;
  white-space: nowrap;
  flex-shrink: 0;
  line-height: 1.2;
}

.eventRowSep {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  opacity: 0.35;
  flex-shrink: 0;
  line-height: 1;
}

.eventRowIcon {
  font-size: 0.76rem;
  flex-shrink: 0;
  line-height: 1;
}

.eventRowTitle {
  color: var(--color-surface);
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1.18;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: color 0.12s ease;
}

.eventRow:hover .eventRowTitle {
  color: var(--color-primary);
}

/* ── Footer link ── */
.showAllLink {
  color: rgb(var(--color-primary-rgb) / 0.75);
  font-size: 0.70rem;
  font-weight: 500;
  text-decoration: none;
  line-height: 1.2;
  transition: color 0.12s ease;
}

.showAllLink:hover {
  color: var(--color-primary);
  text-decoration: underline;
}
</style>
