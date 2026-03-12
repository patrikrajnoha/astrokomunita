<template>
  <section class="card panel moonPhasesCard">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <AsyncState
      v-if="loading"
      mode="loading"
      title="Nacitavam fazy mesiaca"
      loading-style="skeleton"
      :skeleton-rows="4"
      compact
    />

    <AsyncState
      v-else-if="error"
      mode="error"
      title="Nepodarilo sa nacitat"
      :message="error"
      action-label="Skusit znova"
      compact
      @action="fetchPhases"
    />

    <AsyncState
      v-else-if="!phaseRows.length"
      mode="empty"
      title="Fazy mesiaca su nedostupne"
      message="Skus to neskor."
      compact
    />

    <ul v-else class="phaseList" role="list" aria-label="Fazy mesiaca">
      <li
        v-for="phase in phaseRows"
        :key="phase.key"
        class="phaseRow"
        :class="{ isCurrent: phase.is_current }"
      >
        <span class="phaseIcon" aria-hidden="true">{{ phase.icon }}</span>

        <div class="phaseCopy">
          <div class="phaseLabel">{{ phase.label }}</div>
          <div class="phaseRange">{{ formatRange(phase.start_date, phase.end_date) }}</div>
        </div>
      </li>
    </ul>

  </section>
</template>

<script>
import { onMounted, ref } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import { getMoonPhasesWidget } from '@/services/widgets'

const PHASE_ICON_MAP = {
  new_moon: '\u{1F311}',
  waxing_crescent: '\u{1F312}',
  first_quarter: '\u{1F313}',
  waxing_gibbous: '\u{1F314}',
  full_moon: '\u{1F315}',
  waning_gibbous: '\u{1F316}',
  last_quarter: '\u{1F317}',
  waning_crescent: '\u{1F318}',
}

export default {
  name: 'MoonPhasesWidget',
  components: {
    AsyncState,
  },
  props: {
    title: {
      type: String,
      default: 'Fazy mesiaca',
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
    const phaseRows = ref([])
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

    const normalizeRows = (rows) => {
      const source = Array.isArray(rows) ? rows : []

      return source.map((item) => {
        const key = String(item?.key || '').trim()
        return {
          key,
          label: String(item?.label || key || 'Neznama faza'),
          start_date: String(item?.start_date || '').trim(),
          end_date: String(item?.end_date || '').trim(),
          is_current: Boolean(item?.is_current),
          icon: PHASE_ICON_MAP[key] || '\u{1F319}',
        }
      })
    }

    const fetchPhases = async () => {
      loading.value = true
      error.value = ''

      try {
        const payload = await getMoonPhasesWidget(buildQuery())
        phaseRows.value = normalizeRows(payload?.phases)
      } catch (err) {
        phaseRows.value = []
        error.value =
          err?.response?.data?.message
          || err?.message
          || 'Skus obnovit widget neskor.'
      } finally {
        loading.value = false
      }
    }

    const formatRange = (startDate, endDate) => {
      const start = formatDate(startDate)
      const end = formatDate(endDate)

      if (!start && !end) return '-'
      if (start && end && start === end) return start
      if (start && end) return `${start} - ${end}`
      return start || end || '-'
    }

    onMounted(() => {
      fetchPhases()
    })

    return {
      phaseRows,
      loading,
      error,
      fetchPhases,
      formatRange,
    }
  },
}

function formatDate(value) {
  const text = String(value || '').trim()
  if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return ''

  const [year, month, day] = text.split('-')
  if (!year || !month || !day) return ''

  return `${day}.${month}.`
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

.phaseList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.16rem;
}

.phaseRow {
  display: grid;
  grid-template-columns: 1.1rem minmax(0, 1fr);
  align-items: center;
  column-gap: 0.34rem;
  padding: 0.2rem 0.24rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.15);
}

.phaseRow.isCurrent {
  border-color: rgb(var(--color-primary-rgb) / 0.9);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.phaseIcon {
  font-size: 0.88rem;
  line-height: 1;
  text-align: center;
}

.phaseCopy {
  min-width: 0;
  display: grid;
  gap: 0.04rem;
}

.phaseLabel {
  color: var(--color-surface);
  font-size: 0.74rem;
  line-height: 1.12;
  font-weight: 700;
}

.phaseRange {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.15;
}

</style>
