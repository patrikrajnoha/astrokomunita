<template>
  <section class="moonWidget">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <AsyncState
      v-if="loading"
      mode="loading"
      loading-style="skeleton"
      :skeleton-rows="2"
      compact
    />

    <AsyncState
      v-else-if="error"
      mode="error"
      title="Nepodarilo sa načítať"
      :message="error"
      action-label="Skúsiť znova"
      compact
      @action="fetchData"
    />

    <AsyncState
      v-else-if="!currentPhaseKey || currentPhaseKey === 'unknown'"
      mode="empty"
      title="Fáza mesiaca je nedostupná"
      message="Skús to neskôr."
      compact
    />

    <template v-else>
      <div class="hero">
        <span class="heroIcon" aria-hidden="true">{{ phaseIcon(currentPhaseKey) }}</span>
        <div class="heroBody">
          <div class="heroPhase">{{ phaseLabel(currentPhaseKey) }}</div>
          <div v-if="illuminationLabel" class="heroIllumination">{{ illuminationLabel }}</div>
          <div v-if="moonriseLine" class="heroMoonrise">{{ moonriseLine }}</div>
        </div>
      </div>

      <ul v-if="upcomingEvents.length" class="upcomingList" role="list" aria-label="Ďalšie fázy mesiaca">
        <li
          v-for="event in upcomingEvents"
          :key="`${event.key}-${event.date}`"
          class="upcomingItem"
        >
          <span class="upcomingIcon" aria-hidden="true">{{ phaseIcon(event.key) }}</span>
          <span class="upcomingLabel">{{ event.label }}</span>
          <span class="upcomingSep" aria-hidden="true">·</span>
          <span class="upcomingDate">{{ shortDate(event.date) }}</span>
        </li>
      </ul>
    </template>
  </section>
</template>

<script>
import { computed, onMounted, ref } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import { getMoonOverviewWidget, getMoonPhasesWidget } from '@/services/widgets'

const PHASE_ICONS = {
  new_moon: '🌑',
  waxing_crescent: '🌒',
  first_quarter: '🌓',
  waxing_gibbous: '🌔',
  full_moon: '🌕',
  waning_gibbous: '🌖',
  last_quarter: '🌗',
  waning_crescent: '🌘',
}

const PHASE_LABELS = {
  new_moon: 'Nov',
  waxing_crescent: 'Dorastajúci kosáčik',
  first_quarter: 'Prvá štvrt',
  waxing_gibbous: 'Dorastajúci mesiac',
  full_moon: 'Spln',
  waning_gibbous: 'Ubúdajúci mesiac',
  last_quarter: 'Posledná štvrt',
  waning_crescent: 'Ubúdajúci kosáčik',
}

const SHORT_DATE = new Intl.DateTimeFormat('sk-SK', { day: 'numeric', month: 'short', timeZone: 'UTC' })
const TIME_FORMAT = new Intl.DateTimeFormat('sk-SK', { hour: '2-digit', minute: '2-digit', hour12: false })

export default {
  name: 'MoonPhasesWidget',
  components: { AsyncState },
  props: {
    title: { type: String, default: 'Fázy mesiaca' },
    lat: { type: [Number, String], default: null },
    lon: { type: [Number, String], default: null },
    tz: { type: String, default: '' },
    date: { type: String, default: '' },
  },
  setup(props) {
    const loading = ref(true)
    const error = ref('')
    const phasesPhaseKey = ref('')
    const majorEvents = ref([])
    const overviewPhaseKey = ref('')
    const illumination = ref(null)
    const nextMoonriseAt = ref('')

    const currentPhaseKey = computed(() => overviewPhaseKey.value || phasesPhaseKey.value || 'unknown')

    const upcomingEvents = computed(() => {
      const todayStr = new Date().toISOString().slice(0, 10)
      return majorEvents.value
        .filter((e) => e.date && e.date >= todayStr)
        .slice(0, 4)
    })

    const illuminationLabel = computed(() => {
      if (illumination.value === null) return ''
      return `${Math.round(illumination.value)} %`
    })

    const moonriseLine = computed(() => {
      const value = nextMoonriseAt.value
      if (!value) return ''

      const date = new Date(value)
      if (Number.isNaN(date.getTime())) return ''

      const now = new Date()
      const localToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      const targetDay = new Date(date.getFullYear(), date.getMonth(), date.getDate())
      const diffDays = Math.round((targetDay.getTime() - localToday.getTime()) / 86400000)

      if (diffDays < 0 || diffDays > 1) return ''

      const timeStr = TIME_FORMAT.format(date)
      return diffDays === 0 ? `Dnes vychádza ${timeStr}` : `Zajtra vychádza ${timeStr}`
    })

    const buildQuery = () => {
      const q = {}
      const lat = Number(props.lat)
      const lon = Number(props.lon)
      const tz = String(props.tz || '').trim()
      const date = String(props.date || '').trim()

      if (Number.isFinite(lat)) q.lat = lat
      if (Number.isFinite(lon)) q.lon = lon
      if (tz) q.tz = tz
      if (/^\d{4}-\d{2}-\d{2}$/.test(date)) q.date = date

      return q
    }

    const fetchData = async () => {
      loading.value = true
      error.value = ''
      overviewPhaseKey.value = ''
      illumination.value = null
      nextMoonriseAt.value = ''

      try {
        const query = buildQuery()
        const [phasesResult, overviewResult] = await Promise.allSettled([
          getMoonPhasesWidget(query),
          getMoonOverviewWidget(query),
        ])

        if (phasesResult.status === 'rejected') throw phasesResult.reason

        const payload = phasesResult.value
        phasesPhaseKey.value = String(payload?.current_phase || '').trim()
        majorEvents.value = normalizeMajorEvents(payload?.major_events)

        if (overviewResult.status === 'fulfilled') {
          const ov = overviewResult.value
          overviewPhaseKey.value = String(ov?.moon_phase || '').trim()
          const ill = Number(ov?.moon_illumination_percent)
          illumination.value = Number.isFinite(ill) ? ill : null
          nextMoonriseAt.value = String(ov?.next_moonrise_at || '').trim()
        }
      } catch (err) {
        error.value = err?.response?.data?.message || err?.message || 'Skús obnoviť widget neskôr.'
      } finally {
        loading.value = false
      }
    }

    onMounted(fetchData)

    return {
      loading,
      error,
      currentPhaseKey,
      illuminationLabel,
      moonriseLine,
      upcomingEvents,
      fetchData,
      phaseIcon,
      phaseLabel,
      shortDate,
    }
  },
}

function normalizeMajorEvents(rows) {
  return (Array.isArray(rows) ? rows : [])
    .map((item) => {
      const key = String(item?.key || '').trim()
      const at = String(item?.at || '').trim()
      const date = sanitizeDate(String(item?.date || '').trim()) || sanitizeDate(extractIsoDate(at))

      return {
        key,
        label: PHASE_LABELS[key] || String(item?.label || key).trim(),
        date,
      }
    })
    .filter((item) => item.key && item.date)
}

function sanitizeDate(v) {
  const t = String(v || '').trim()
  return /^\d{4}-\d{2}-\d{2}$/.test(t) ? t : ''
}

function extractIsoDate(v) {
  return String(v || '').trim().match(/^(\d{4}-\d{2}-\d{2})T/)?.[1] || ''
}

function phaseIcon(key) {
  return PHASE_ICONS[String(key || '').toLowerCase()] || '🌙'
}

function phaseLabel(key) {
  return PHASE_LABELS[String(key || '').toLowerCase()] || String(key || '') || 'Neznáma fáza'
}

function shortDate(value) {
  const m = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!m) return ''
  try {
    return SHORT_DATE.format(new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]))))
  } catch {
    return ''
  }
}
</script>

<style scoped>
.moonWidget {
  display: grid;
  gap: 0.5rem;
  min-width: 0;
}

.panelTitle {
  margin: 0;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

/* ── Hero ── */
.hero {
  display: flex;
  align-items: flex-start;
  gap: 0.64rem;
  padding: 0.2rem 0 0.1rem;
}

.heroIcon {
  font-size: 2rem;
  line-height: 1;
  flex-shrink: 0;
  margin-top: 0.05rem;
}

.heroBody {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.14rem;
}

.heroPhase {
  color: var(--color-surface);
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.15;
}

.heroIllumination {
  color: var(--color-text-secondary);
  font-size: 0.84rem;
  font-weight: 600;
  line-height: 1.2;
}

.heroMoonrise {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  line-height: 1.2;
  opacity: 0.8;
}

/* ── Upcoming list ── */
.upcomingList {
  list-style: none;
  margin: 0;
  padding: 0.44rem 0 0;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.15);
  display: grid;
  gap: 0.3rem;
}

.upcomingItem {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  min-width: 0;
}

.upcomingIcon {
  font-size: 0.76rem;
  flex-shrink: 0;
  line-height: 1;
}

.upcomingLabel {
  color: var(--color-surface);
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1.2;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upcomingSep {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  flex-shrink: 0;
  opacity: 0.5;
  line-height: 1;
}

.upcomingDate {
  color: var(--color-text-secondary);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.2;
  flex-shrink: 0;
  white-space: nowrap;
}
</style>
