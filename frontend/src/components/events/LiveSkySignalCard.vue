<template>
  <article class="liveSignalCard" :class="toneClass">
    <div class="liveSignalTop">
      <span class="liveSignalBadge">{{ badgeLabel }}</span>
      <span v-if="scoreLabel" class="liveSignalScore">{{ scoreLabel }}</span>
    </div>

    <h2 class="liveSignalTitle">{{ titleLabel }}</h2>
    <p class="liveSignalStatus">{{ statusLabel }}</p>
    <p v-if="summaryLabel" class="liveSignalSummary">{{ summaryLabel }}</p>
    <p v-if="detailLabel" class="liveSignalDetail">{{ detailLabel }}</p>

    <div class="liveSignalMeta">
      <span v-if="forecastLabel" class="liveSignalMetaItem">
        Predikcia:
        <time v-if="forecastDateTime" :datetime="forecastDateTime">{{ forecastLabel }}</time>
        <span v-else>{{ forecastLabel }}</span>
      </span>

      <span class="liveSignalMetaItem">
        Zdroj:
        <a
          v-if="sourceUrl"
          class="liveSignalLink"
          :href="sourceUrl"
          target="_blank"
          rel="noreferrer"
        >
          {{ sourceLabel }}
        </a>
        <span v-else>{{ sourceLabel }}</span>
      </span>

      <span class="liveSignalMetaItem">
        Aktualizované:
        <time v-if="updatedDateTime" :datetime="updatedDateTime">{{ updatedLabel }}</time>
        <span v-else>{{ updatedLabel }}</span>
      </span>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  signal: {
    type: Object,
    required: true,
  },
  timeZone: {
    type: String,
    default: 'Europe/Bratislava',
  },
})

const toneClass = computed(() => `is-${String(props.signal?.tone || 'neutral').trim() || 'neutral'}`)
const badgeLabel = computed(() => String(props.signal?.badge || 'Živé teraz').trim() || 'Živé teraz')
const titleLabel = computed(() => String(props.signal?.title || 'Live signal').trim() || 'Live signal')
const statusLabel = computed(() => String(props.signal?.status_label || 'Bez dát').trim() || 'Bez dát')
const summaryLabel = computed(() => String(props.signal?.summary || '').trim())
const detailLabel = computed(() => String(props.signal?.detail || '').trim())
const sourceLabel = computed(() => String(props.signal?.source?.label || 'Neznámy zdroj').trim() || 'Neznámy zdroj')
const sourceUrl = computed(() => String(props.signal?.source?.url || '').trim())
const scoreLabel = computed(() => {
  const value = Number(props.signal?.status_score)
  return Number.isFinite(value) ? `${Math.round(value)}/100` : ''
})
const forecastDateTime = computed(() => normalizeTimestamp(props.signal?.forecast_for))
const updatedDateTime = computed(() => normalizeTimestamp(props.signal?.updated_at || props.signal?.observed_at))
const forecastLabel = computed(() => formatDateTime(forecastDateTime.value, props.timeZone))
const updatedLabel = computed(() => formatTime(updatedDateTime.value, props.timeZone))

function normalizeTimestamp(value) {
  const raw = String(value || '').trim()
  if (!raw) return ''

  const parsed = new Date(raw)
  return Number.isNaN(parsed.getTime()) ? '' : parsed.toISOString()
}

function formatDateTime(value, timeZone) {
  if (!value) return ''

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  }
}

function formatTime(value, timeZone) {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  }
}
</script>

<style scoped>
.liveSignalCard {
  display: grid;
  gap: 0.58rem;
  border: 0;
  border-radius: 1rem;
  background: #1c2736;
  padding: 0.98rem 1rem;
}

.liveSignalCard.is-high {
  background: #1c2736;
}

.liveSignalCard.is-medium {
  background: #1c2736;
}

.liveSignalCard.is-low {
  background: #1c2736;
}

.liveSignalTop {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.liveSignalBadge,
.liveSignalScore {
  display: inline-flex;
  align-items: center;
  min-height: 1.86rem;
  border-radius: 999px;
  border: 0;
  box-shadow: none;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.28rem 0.64rem;
}

.liveSignalBadge {
  background: #0f73ff;
  color: #ffffff;
}

.liveSignalScore {
  background: #222e3f;
  color: #abb8c9;
}

.liveSignalTitle,
.liveSignalStatus,
.liveSignalSummary,
.liveSignalDetail {
  margin: 0;
}

.liveSignalTitle {
  font-size: 1.02rem;
  line-height: 1.2;
  color: #ffffff;
}

.liveSignalStatus {
  font-size: 1.08rem;
  font-weight: 700;
  color: #ffffff;
}

.liveSignalCard.is-high .liveSignalStatus {
  color: #0f73ff;
}

.liveSignalCard.is-low .liveSignalStatus {
  color: #abb8c9;
}

.liveSignalSummary {
  font-size: 0.88rem;
  line-height: 1.45;
  color: #abb8c9;
}

.liveSignalDetail,
.liveSignalMeta {
  font-size: 0.8rem;
  line-height: 1.5;
  color: #abb8c9;
}

.liveSignalMeta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.7rem;
}

.liveSignalMetaItem {
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
  flex-wrap: wrap;
}

.liveSignalLink {
  color: #0f73ff;
  text-decoration: none;
}

.liveSignalLink:hover,
.liveSignalLink:focus-visible {
  color: #ffffff;
  text-decoration: none;
}
</style>
