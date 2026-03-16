<template>
  <section class="card panel moonPhasesCard">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <AsyncState
      v-if="loading"
      mode="loading"
      title="Načítavam fázy mesiaca"
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
      @action="fetchPhases"
    />

    <AsyncState
      v-else-if="!majorEvents.length"
      mode="empty"
      title="Hlavné fázy mesiaca sú nedostupné"
      message="Skús to neskôr."
      compact
    />

    <section v-else-if="showOverview && moonOverview" class="overviewPanel">
      <div class="overviewVisual">
        <span class="overviewDisc" :class="`overviewDisc--${moonOverview.moon_phase}`" aria-hidden="true"></span>
        <div class="overviewMainLine">Mesiac: {{ moonOverview.illumination_label }}</div>
        <div class="overviewSubLine">{{ moonOverview.phase_label }}</div>
      </div>

      <dl class="overviewStats">
        <div class="overviewRow">
          <dt>Aktuálny čas:</dt>
          <dd>{{ formatDateTime(moonOverview.reference_at) }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Smer Mesiaca:</dt>
          <dd>{{ moonOverview.direction_line }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Výška Mesiaca:</dt>
          <dd>{{ moonOverview.altitude_line }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Vzdialenosť Mesiaca:</dt>
          <dd>{{ moonOverview.distance_line }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Ďalší Nov:</dt>
          <dd>{{ formatDateTime(moonOverview.next_new_moon_at) }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Ďalší Spln:</dt>
          <dd>{{ formatDateTime(moonOverview.next_full_moon_at) }}</dd>
        </div>
        <div class="overviewRow">
          <dt>Ďalší východ Mesiaca:</dt>
          <dd>{{ formatMoonriseLine(moonOverview.next_moonrise_at) }}</dd>
        </div>
      </dl>
    </section>

    <section v-if="showOverview && !loading && !error && moonOverviewError" class="overviewHint">
      {{ moonOverviewError }}
    </section>

    <ul v-if="!loading && !error" class="phaseTimeline" role="list" aria-label="Hlavné fázy mesiaca">
      <li
        v-for="event in majorEvents"
        :key="`${event.key}-${event.at || event.date}`"
        class="phaseEvent"
        :class="{ isCurrent: event.is_current }"
      >
        <div class="phaseLabel">{{ event.label }}</div>
        <span class="phaseDisc" :class="`phaseDisc--${event.key}`" aria-hidden="true"></span>
        <div class="phaseDate">{{ formatDateLabel(event.date, event.at) }}</div>
        <div class="phaseTime">{{ formatTimeLabel(event.time, event.at) }}</div>
      </li>
    </ul>

    <section v-if="showSpecialEvents && !loading && !error" class="specialEvents">
      <div class="specialEventsTitle">Špeciálne lunárne udalosti v {{ specialEventsYearLabel }}</div>

      <ul v-if="specialEvents.length" class="specialEventsList" role="list" aria-label="Špeciálne lunárne udalosti">
        <li
          v-for="event in specialEvents"
          :key="`${event.key}-${event.at || event.label}`"
          class="specialEventRow"
        >
          <span class="specialEventLabel">{{ event.label }}</span>
          <span v-if="formatSpecialEventDateTime(event)" class="specialEventWhen">
            : {{ formatSpecialEventDateTime(event) }}
          </span>
          <span v-if="event.note" class="specialEventNote"> ({{ event.note }})</span>
        </li>
      </ul>

      <p v-else class="specialEventsHint">
        {{ specialEventsError || 'Špeciálne lunárne udalosti sú dočasne nedostupné.' }}
      </p>
    </section>

    <p v-if="metaLine" class="metaLine">{{ metaLine }}</p>
  </section>
</template>

<script>
import { computed, onMounted, ref } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import { getMoonEventsWidget, getMoonOverviewWidget, getMoonPhasesWidget } from '@/services/widgets'

const MAJOR_PHASE_KEYS = ['last_quarter', 'new_moon', 'first_quarter', 'full_moon']
const PHASE_LABEL_MAP = {
  new_moon: 'Nov',
  waxing_crescent: 'Dorastajúci kosáčik',
  first_quarter: 'Prvá štvrt',
  waxing_gibbous: 'Dorastajúci mesiac',
  full_moon: 'Spln',
  waning_gibbous: 'Ubúdajúci mesiac',
  last_quarter: 'Posledná štvrt',
  waning_crescent: 'Ubúdajúci kosáčik',
  unknown: 'Neznáma fáza',
}
const DATE_FORMATTER = new Intl.DateTimeFormat('sk-SK', {
  day: 'numeric',
  month: 'long',
  timeZone: 'UTC',
})
const TIME_FORMATTER = new Intl.DateTimeFormat('sk-SK', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})
const DATE_TIME_FORMATTER = new Intl.DateTimeFormat('sk-SK', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
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
  name: 'MoonPhasesWidget',
  components: {
    AsyncState,
  },
  props: {
    title: {
      type: String,
      default: 'Fázy mesiaca',
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
    showOverview: {
      type: Boolean,
      default: true,
    },
    showSpecialEvents: {
      type: Boolean,
      default: true,
    },
  },
  setup(props) {
    const majorEvents = ref([])
    const moonOverview = ref(null)
    const specialEvents = ref([])
    const moonOverviewError = ref('')
    const phasesSource = ref(null)
    const overviewSource = ref(null)
    const moonEventsSource = ref(null)
    const phasesReferenceAt = ref('')
    const specialEventsYear = ref(new Date().getFullYear())
    const specialEventsError = ref('')
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

    const resolveMajorEventSource = (payload) => {
      const majorEventsFromApi = Array.isArray(payload?.major_events) ? payload.major_events : []
      if (majorEventsFromApi.length > 0) {
        return majorEventsFromApi
      }

      const phases = Array.isArray(payload?.phases) ? payload.phases : []
      return phases
        .filter((item) => MAJOR_PHASE_KEYS.includes(String(item?.key || '').trim()))
        .map((item) => ({
          key: String(item?.key || '').trim(),
          label: String(item?.label || '').trim(),
          at: String(item?.start_at || '').trim(),
          date: String(item?.start_date || '').trim(),
          time: extractIsoTime(String(item?.start_at || '').trim()),
          is_current: Boolean(item?.is_current),
        }))
    }

    const normalizeMajorEvents = (payload) => {
      return resolveMajorEventSource(payload)
        .map((item) => {
          const key = String(item?.key || '').trim()
          const at = String(item?.at || item?.start_at || '').trim()
          const date = sanitizeDate(String(item?.date || item?.start_date || '').trim())
            || sanitizeDate(extractIsoDate(at))
          const time = sanitizeTime(String(item?.time || '').trim())
            || sanitizeTime(extractIsoTime(at))

          return {
            key,
            label: String(item?.label || PHASE_LABEL_MAP[key] || 'Neznáma fáza').trim(),
            at,
            date,
            time,
            is_current: Boolean(item?.is_current),
          }
        })
        .filter((item) => MAJOR_PHASE_KEYS.includes(item.key))
    }

    const normalizeSpecialEvents = (rows) => {
      const source = Array.isArray(rows) ? rows : []

      return source
        .map((item) => {
          const label = String(item?.label || '').trim()
          const at = String(item?.at || '').trim()
          const date = sanitizeDate(String(item?.date || '').trim())
            || sanitizeDate(extractIsoDate(at))
          const time = sanitizeTime(String(item?.time || '').trim())
            || sanitizeTime(extractIsoTime(at))

          return {
            key: String(item?.key || '').trim(),
            label,
            note: String(item?.note || '').trim(),
            at,
            date,
            time,
          }
        })
        .filter((item) => item.label !== '')
    }

    const normalizeMoonOverview = (payload) => {
      const phaseKey = String(payload?.moon_phase || 'unknown').trim().toLowerCase()
      const illumination = toFiniteNumber(payload?.moon_illumination_percent)
      const azimuth = toFiniteNumber(payload?.moon_azimuth_deg)
      const altitude = toFiniteNumber(payload?.moon_altitude_deg)
      const distanceKm = toFiniteNumber(payload?.moon_distance_km)
      const direction = String(payload?.moon_direction || '').trim().toUpperCase()

      const azimuthLabel = azimuth === null ? '-' : `${formatSignedNumber(azimuth, 2)}\u00B0`
      const directionArrow = DIRECTION_ARROW_MAP[direction] || ''
      const directionLabel = direction ? `${direction}${directionArrow ? ` ${directionArrow}` : ''}` : '-'
      const altitudeLabel = altitude === null ? '-' : `${formatSignedNumber(altitude, 2)}\u00B0`
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

    const buildMoonEventsQuery = (baseQuery, year) => {
      const query = {}

      if (Number.isFinite(baseQuery?.lat)) query.lat = baseQuery.lat
      if (Number.isFinite(baseQuery?.lon)) query.lon = baseQuery.lon
      if (String(baseQuery?.tz || '').trim() !== '') query.tz = String(baseQuery.tz).trim()
      if (Number.isInteger(year)) query.year = year

      return query
    }

    const fetchPhases = async () => {
      loading.value = true
      error.value = ''
      moonOverviewError.value = ''
      specialEventsError.value = ''

      try {
        const query = buildQuery()
        const [phasesResult, overviewResult] = await Promise.allSettled([
          getMoonPhasesWidget(query),
          props.showOverview ? getMoonOverviewWidget(query) : Promise.resolve(null),
        ])

        if (phasesResult.status === 'rejected') {
          throw phasesResult.reason
        }

        const payload = phasesResult.value
        majorEvents.value = normalizeMajorEvents(payload)
        phasesSource.value = payload?.source || null
        phasesReferenceAt.value = String(payload?.reference_at || '').trim()

        if (!props.showOverview) {
          moonOverview.value = null
          overviewSource.value = null
        } else if (overviewResult.status === 'fulfilled') {
          moonOverview.value = normalizeMoonOverview(overviewResult.value)
          overviewSource.value = overviewResult.value?.source || null
        } else {
          moonOverview.value = null
          overviewSource.value = null
          moonOverviewError.value =
            overviewResult.reason?.response?.data?.message
            || overviewResult.reason?.message
            || 'Aktuálny prehľad Mesiaca je dočasne nedostupný.'
        }

        const fallbackYear = new Date().getFullYear()
        const referenceYear = resolveYearFromDate(payload?.reference_date, fallbackYear)
        specialEventsYear.value = referenceYear

        if (!props.showSpecialEvents) {
          specialEvents.value = []
        } else {
          try {
            const moonEventsPayload = await getMoonEventsWidget(buildMoonEventsQuery(query, referenceYear))
            specialEvents.value = normalizeSpecialEvents(moonEventsPayload?.events)
            moonEventsSource.value = moonEventsPayload?.source || null
            specialEventsYear.value = Number.isFinite(Number(moonEventsPayload?.year))
              ? Number(moonEventsPayload.year)
              : referenceYear
          } catch (eventsErr) {
            specialEvents.value = []
            moonEventsSource.value = null
            specialEventsError.value =
              eventsErr?.response?.data?.message
              || eventsErr?.message
              || 'Špeciálne lunárne udalosti sú dočasne nedostupné.'
          }
        }
      } catch (err) {
        majorEvents.value = []
        moonOverview.value = null
        specialEvents.value = []
        phasesSource.value = null
        overviewSource.value = null
        moonEventsSource.value = null
        phasesReferenceAt.value = ''
        error.value =
          err?.response?.data?.message
          || err?.message
          || 'Skús obnoviť widget neskôr.'
      } finally {
        loading.value = false
      }
    }

    const formatDateLabel = (date, at) => {
      return formatDate(date) || formatDate(extractIsoDate(at)) || '-'
    }

    const formatTimeLabel = (time, at) => {
      return formatTime(time, at)
    }

    const formatSpecialEventDateTime = (event) => {
      const dateLabel = formatDate(event?.date || extractIsoDate(event?.at || ''))
      const timeLabel = sanitizeTime(event?.time || extractIsoTime(event?.at || ''))

      if (dateLabel && timeLabel) return `${dateLabel}, ${timeLabel}`
      return dateLabel || timeLabel || ''
    }

    const formatDateTime = (value) => {
      return formatDateTimeLabel(value)
    }

    const formatMoonriseLine = (value) => {
      return formatRelativeDateTimeLabel(value)
    }

    const specialEventsYearLabel = computed(() => {
      return Number.isFinite(Number(specialEventsYear.value))
        ? Number(specialEventsYear.value)
        : new Date().getFullYear()
    })

    const metaLine = computed(() => {
      const labels = new Set()

      const phaseLabel = String(phasesSource.value?.label || phasesSource.value?.provider || '').trim()
      const overviewPhaseLabel = String(overviewSource.value?.phase?.label || overviewSource.value?.phase?.provider || '').trim()
      const overviewPositionLabel = String(overviewSource.value?.position?.label || overviewSource.value?.position?.provider || '').trim()
      const eventsDistanceLabel = String(
        moonEventsSource.value?.distance?.label || moonEventsSource.value?.distance?.provider || '',
      ).trim()

      ;[phaseLabel, overviewPhaseLabel, overviewPositionLabel, eventsDistanceLabel]
        .filter(Boolean)
        .forEach((label) => labels.add(label))

      const updatedAt = String(moonOverview.value?.reference_at || phasesReferenceAt.value || '').trim()
      const updatedLabel = formatDateTimeLabel(updatedAt)
      const parts = []

      if (labels.size > 0) {
        parts.push(`Zdroj: ${Array.from(labels).join(', ')}`)
      }

      if (updatedLabel !== '-') {
        parts.push(`Aktualizované: ${updatedLabel}`)
      }

      return parts.join(' | ')
    })

    onMounted(() => {
      fetchPhases()
    })

    return {
      majorEvents,
      specialEvents,
      moonOverview,
      moonOverviewError,
      specialEventsYearLabel,
      specialEventsError,
      loading,
      error,
      metaLine,
      fetchPhases,
      formatDateLabel,
      formatTimeLabel,
      formatSpecialEventDateTime,
      formatDateTime,
      formatMoonriseLine,
    }
  },
}

function formatDate(value) {
  const text = String(value || '').trim()
  if (!text) return ''

  const match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!match) return ''

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return ''

  try {
    return DATE_FORMATTER.format(new Date(Date.UTC(year, month - 1, day)))
  } catch {
    return ''
  }
}

function formatTime(value, isoAt) {
  const text = sanitizeTime(value) || sanitizeTime(extractIsoTime(isoAt))
  if (text) {
    return text
  }

  const at = String(isoAt || '').trim()
  if (!at) return '-'

  const parsed = new Date(at)
  if (Number.isNaN(parsed.getTime())) return '-'

  try {
    return TIME_FORMATTER.format(parsed)
  } catch {
    return '-'
  }
}

function sanitizeDate(value) {
  const text = String(value || '').trim()
  return /^\d{4}-\d{2}-\d{2}$/.test(text) ? text : ''
}

function sanitizeTime(value) {
  const text = String(value || '').trim()
  return /^\d{2}:\d{2}$/.test(text) ? text : ''
}

function extractIsoDate(value) {
  const text = String(value || '').trim()
  const match = text.match(/^(\d{4}-\d{2}-\d{2})T/)
  return match?.[1] || ''
}

function extractIsoTime(value) {
  const text = String(value || '').trim()
  const match = text.match(/T(\d{2}:\d{2})/)
  return match?.[1] || ''
}

function resolveYearFromDate(value, fallbackYear) {
  const text = String(value || '').trim()
  const match = text.match(/^(\d{4})-\d{2}-\d{2}$/)
  if (!match) return fallbackYear

  const year = Number(match[1])
  if (!Number.isInteger(year) || year < 1700 || year > 2100) {
    return fallbackYear
  }

  return year
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function formatSignedNumber(value, digits = 2) {
  const number = toFiniteNumber(value)
  if (number === null) return '-'
  const fixed = number.toFixed(digits)
  return fixed.startsWith('-') ? fixed : fixed
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

  const timeLabel = new Intl.DateTimeFormat('sk-SK', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)

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

.moonPhasesCard {
  container-type: inline-size;
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
  grid-template-columns: 1fr;
  gap: 0.48rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.18), rgb(var(--color-bg-rgb) / 0.1));
  padding: 0.42rem;
}

@container (min-width: 380px) {
  .overviewPanel {
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  }
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

.overviewHint {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.69rem;
  line-height: 1.2;
}

.phaseTimeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.15);
}

.phaseEvent {
  display: grid;
  justify-items: center;
  gap: 0.2rem;
  text-align: center;
  min-width: 0;
  padding: 0.44rem 0.26rem 0.5rem;
  border-right: 0;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
}

.phaseEvent:nth-child(odd) {
  border-right: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
}

.phaseEvent:nth-last-child(-n+2) {
  border-bottom: 0;
}

.phaseEvent:last-child {
  border-right: 0;
}

@container (min-width: 460px) {
  .phaseTimeline {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .phaseEvent {
    border-right: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
    border-bottom: 0;
  }

  .phaseEvent:nth-child(odd) {
    border-right: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  }

  .phaseEvent:last-child {
    border-right: 0;
  }

  .phaseEvent:nth-last-child(-n+2) {
    border-bottom: 0;
  }
}

.phaseEvent.isCurrent {
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.phaseLabel {
  color: var(--color-surface);
  font-size: 0.79rem;
  line-height: 1.16;
  font-weight: 800;
  min-height: 1.8em;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.phaseDisc {
  inline-size: 2.52rem;
  block-size: 2.52rem;
  border-radius: 999px;
  border: 0.2rem solid rgb(var(--color-surface-rgb, 255 255 255) / 0.88);
  background: #050810;
  box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.06);
}

.phaseDisc--new_moon {
  background: #050810;
}

.phaseDisc--full_moon {
  background: radial-gradient(circle at 38% 34%, #fff 0%, #eef2fa 74%, #d8dfee 100%);
}

.phaseDisc--first_quarter {
  background: linear-gradient(90deg, #050810 50%, #eef2fa 50%);
}

.phaseDisc--last_quarter {
  background: linear-gradient(90deg, #eef2fa 50%, #050810 50%);
}

.phaseDate {
  color: var(--color-surface);
  font-size: 0.71rem;
  line-height: 1.2;
  font-weight: 700;
}

.phaseTime {
  color: var(--color-text-secondary);
  font-size: 0.7rem;
  line-height: 1.15;
  font-weight: 700;
}

.specialEvents {
  border-top: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.32);
  padding-top: 0.44rem;
  display: grid;
  gap: 0.24rem;
}

.specialEventsTitle {
  margin: 0;
  color: var(--color-surface);
  font-weight: 800;
  font-size: 0.76rem;
  line-height: 1.2;
}

.specialEventsList {
  margin: 0;
  padding: 0 0 0 1rem;
  display: grid;
  gap: 0.2rem;
}

.specialEventRow {
  color: var(--color-text-secondary);
  font-size: 0.71rem;
  line-height: 1.25;
}

.specialEventLabel {
  color: var(--color-surface);
  font-weight: 700;
}

.specialEventWhen {
  color: var(--color-text-secondary);
}

.specialEventNote {
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}

.specialEventsHint {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.71rem;
  line-height: 1.25;
}

.metaLine {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.25;
}

</style>
