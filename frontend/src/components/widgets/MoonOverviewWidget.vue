<template>
  <section class="card panel moonOverviewCard">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <AsyncState
      v-if="loading"
      mode="loading"
      title="Nacitavam prehlad Mesiaca"
      loading-style="skeleton"
      :skeleton-rows="2"
      compact
    />

    <AsyncState
      v-else-if="error"
      mode="error"
      title="Nepodarilo sa nacitat"
      :message="error"
      action-label="Skusit znova"
      compact
      @action="fetchOverview"
    />

    <AsyncState
      v-else-if="!overview"
      mode="empty"
      title="Prehlad Mesiaca je nedostupny"
      message="Skus to neskor."
      compact
    />

    <section v-else class="overviewPanel">
      <div class="overviewVisual">
        <span class="overviewDisc" :class="`overviewDisc--${overview.moon_phase}`" aria-hidden="true"></span>
        <div class="overviewMainLine">Mesiac: {{ overview.illumination_label }}</div>
        <div class="overviewSubLine">{{ overview.phase_label }}</div>
      </div>

      <dl class="overviewStats">
        <div class="overviewRow">
          <dt>Aktualny cas:</dt>
          <dd>{{ formatDateTime(overview.reference_at) }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Smer Mesiaca:</dt>
          <dd>{{ overview.direction_line }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Vyska Mesiaca:</dt>
          <dd>{{ overview.altitude_line }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Vzdialenost Mesiaca:</dt>
          <dd>{{ overview.distance_line }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Dalsi Nov:</dt>
          <dd>{{ formatDateTime(overview.next_new_moon_at) }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Dalsi Spln:</dt>
          <dd>{{ formatDateTime(overview.next_full_moon_at) }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Dalsi vychod Mesiaca:</dt>
          <dd>{{ formatMoonriseLine(overview.next_moonrise_at) }}</dd>
        </div>
      </dl>
    </section>
  </section>
</template>

<script>
import { onMounted, ref } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import { getMoonOverviewWidget } from '@/services/widgets'

const PHASE_LABEL_MAP = {
  new_moon: 'Nov',
  waxing_crescent: 'Dorastajuci kosacik',
  first_quarter: 'Prva stvrt',
  waxing_gibbous: 'Dorastajuci mesiac',
  full_moon: 'Spln',
  waning_gibbous: 'Ubudajuci mesiac',
  last_quarter: 'Posledna stvrt',
  waning_crescent: 'Ubudajuci kosacik',
  unknown: 'Neznama faza',
}

const DATE_TIME_FORMATTER = new Intl.DateTimeFormat('sk-SK', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

const TIME_FORMATTER = new Intl.DateTimeFormat('sk-SK', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

const DEGREE_FORMATTER = new Intl.NumberFormat('sk-SK', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const NUMBER_FORMATTER = new Intl.NumberFormat('sk-SK')

const DIRECTION_ARROW_MAP = {
  N: '\u2191',
  NE: '\u2197',
  E: '\u2192',
  SE: '\u2198',
  S: '\u2193',
  SW: '\u2199',
  W: '\u2190',
  NW: '\u2196',
}

export default {
  name: 'MoonOverviewWidget',
  components: {
    AsyncState,
  },
  props: {
    title: {
      type: String,
      default: 'Mesiac teraz',
    },
    lat: {
      type: [Number, String],
      default: null,
    },
    lon: {
      type: [Number, String],
      default: null,
    },
    tz: {
      type: String,
      default: '',
    },
    date: {
      type: String,
      default: '',
    },
  },
  setup(props) {
    const overview = ref(null)
    const loading = ref(true)
    const error = ref('')

    const buildQuery = () => {
      const query = {}
      const lat = Number(props.lat)
      const lon = Number(props.lon)
      const tz = String(props.tz || '').trim()
      const date = String(props.date || '').trim()

      if (Number.isFinite(lat)) {
        query.lat = lat
      }

      if (Number.isFinite(lon)) {
        query.lon = lon
      }

      if (tz) {
        query.tz = tz
      }

      if (/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        query.date = date
      }

      return query
    }

    const fetchOverview = async () => {
      loading.value = true
      error.value = ''

      try {
        const payload = await getMoonOverviewWidget(buildQuery())
        overview.value = normalizeMoonOverview(payload)
      } catch (err) {
        overview.value = null
        error.value =
          err?.response?.data?.message
          || err?.message
          || 'Skus obnovit widget neskor.'
      } finally {
        loading.value = false
      }
    }

    const formatDateTime = (value) => formatDateTimeLabel(value)
    const formatMoonriseLine = (value) => formatRelativeDateTimeLabel(value)

    onMounted(() => {
      fetchOverview()
    })

    return {
      overview,
      loading,
      error,
      fetchOverview,
      formatDateTime,
      formatMoonriseLine,
    }
  },
}

function normalizeMoonOverview(payload) {
  const phaseKey = String(payload?.moon_phase || 'unknown').trim().toLowerCase()
  const illumination = toFiniteNumber(payload?.moon_illumination_percent)
  const azimuth = toFiniteNumber(payload?.moon_azimuth_deg)
  const altitude = toFiniteNumber(payload?.moon_altitude_deg)
  const distanceKm = toFiniteNumber(payload?.moon_distance_km)
  const direction = String(payload?.moon_direction || '').trim().toUpperCase()

  const azimuthLabel = azimuth === null ? '-' : `${DEGREE_FORMATTER.format(azimuth)}\u00B0`
  const directionArrow = DIRECTION_ARROW_MAP[direction] || ''
  const directionLabel = direction ? `${direction}${directionArrow ? ` ${directionArrow}` : ''}` : '-'
  const altitudeLabel = altitude === null ? '-' : `${DEGREE_FORMATTER.format(altitude)}\u00B0`
  const distanceLabel = distanceKm === null ? '-' : `${NUMBER_FORMATTER.format(Math.round(distanceKm))} km`
  const illuminationLabel = illumination === null ? '-' : `${Math.round(illumination)}%`

  return {
    reference_at: String(payload?.reference_at || '').trim(),
    moon_phase: phaseKey || 'unknown',
    phase_label: PHASE_LABEL_MAP[phaseKey] || PHASE_LABEL_MAP.unknown,
    illumination_label: illuminationLabel,
    direction_line: `${azimuthLabel} ${directionLabel}`.trim(),
    altitude_line: altitudeLabel,
    distance_line: distanceLabel,
    next_new_moon_at: String(payload?.next_new_moon_at || '').trim(),
    next_full_moon_at: String(payload?.next_full_moon_at || '').trim(),
    next_moonrise_at: String(payload?.next_moonrise_at || '').trim(),
  }
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function formatDateTimeLabel(value) {
  const text = String(value || '').trim()
  if (!text) return '-'

  const date = new Date(text)
  if (Number.isNaN(date.getTime())) return '-'

  try {
    return DATE_TIME_FORMATTER.format(date)
  } catch {
    return '-'
  }
}

function formatRelativeDateTimeLabel(value) {
  const text = String(value || '').trim()
  if (!text) return '-'

  const date = new Date(text)
  if (Number.isNaN(date.getTime())) return '-'

  const now = new Date()
  const localToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const targetDay = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const diffDays = Math.round((targetDay.getTime() - localToday.getTime()) / 86400000)
  const timeLabel = TIME_FORMATTER.format(date)

  if (diffDays === 0) {
    return `Dnes, ${timeLabel}`
  }

  if (diffDays === 1) {
    return `Zajtra, ${timeLabel}`
  }

  return formatDateTimeLabel(text)
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
  margin: 0;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
}

.overviewPanel {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  gap: 0.48rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.18), rgb(var(--color-bg-rgb) / 0.1));
  padding: 0.42rem;
}

.overviewVisual {
  background: rgb(var(--color-surface-rgb, 255 255 255) / 0.06);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  display: grid;
  justify-items: center;
  align-content: center;
  gap: 0.2rem;
  padding: 0.4rem 0.35rem;
}

.overviewDisc {
  inline-size: 3rem;
  block-size: 3rem;
  border-radius: 999px;
  border: 0.18rem solid rgb(var(--color-surface-rgb, 255 255 255) / 0.85);
  background: #050810;
  box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.08);
}

.overviewDisc--new_moon {
  background: #050810;
}

.overviewDisc--waxing_crescent {
  background: radial-gradient(circle at 70% 50%, #eef2fa 34%, #050810 36%);
}

.overviewDisc--first_quarter {
  background: linear-gradient(90deg, #050810 50%, #eef2fa 50%);
}

.overviewDisc--waxing_gibbous {
  background: radial-gradient(circle at 35% 50%, #050810 34%, #eef2fa 36%);
}

.overviewDisc--full_moon {
  background: radial-gradient(circle at 38% 34%, #fff 0%, #eef2fa 74%, #d8dfee 100%);
}

.overviewDisc--waning_gibbous {
  background: radial-gradient(circle at 65% 50%, #050810 34%, #eef2fa 36%);
}

.overviewDisc--last_quarter {
  background: linear-gradient(90deg, #eef2fa 50%, #050810 50%);
}

.overviewDisc--waning_crescent {
  background: radial-gradient(circle at 30% 50%, #eef2fa 34%, #050810 36%);
}

.overviewMainLine {
  color: var(--color-surface);
  font-size: 1.04rem;
  font-weight: 800;
  line-height: 1.1;
}

.overviewSubLine {
  color: rgb(var(--color-primary-rgb) / 0.9);
  font-size: 0.73rem;
  font-weight: 700;
  line-height: 1.2;
}

.overviewStats {
  margin: 0;
  padding: 0;
  display: grid;
  align-content: start;
}

.overviewRow {
  display: grid;
  grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
  gap: 0.45rem;
  align-items: baseline;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  padding: 0.2rem 0;
}

.overviewRow:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.overviewRow dt {
  margin: 0;
  color: var(--color-surface);
  font-size: 0.72rem;
  font-weight: 800;
}

.overviewRow dd {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.71rem;
  line-height: 1.2;
  text-align: left;
}

@media (max-width: 860px) {
  .overviewPanel {
    grid-template-columns: 1fr;
  }
}
</style>
