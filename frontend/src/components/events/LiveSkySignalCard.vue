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
        Aktualizovane:
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
const badgeLabel = computed(() => String(props.signal?.badge || 'Zive teraz').trim() || 'Zive teraz')
const titleLabel = computed(() => String(props.signal?.title || 'Live signal').trim() || 'Live signal')
const statusLabel = computed(() => String(props.signal?.status_label || 'Bez dat').trim() || 'Bez dat')
const summaryLabel = computed(() => String(props.signal?.summary || '').trim())
const detailLabel = computed(() => String(props.signal?.detail || '').trim())
const sourceLabel = computed(() => String(props.signal?.source?.label || 'Neznamy zdroj').trim() || 'Neznamy zdroj')
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
  gap: 0.48rem;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.68);
  border-radius: 1rem;
  background: rgb(var(--color-bg-main-rgb) / 0.58);
  padding: 0.9rem 0.95rem;
}

.liveSignalCard.is-high {
  border-color: rgb(16 185 129 / 0.35);
  box-shadow: inset 0 0 0 1px rgb(16 185 129 / 0.1);
}

.liveSignalCard.is-medium {
  border-color: rgb(56 189 248 / 0.35);
  box-shadow: inset 0 0 0 1px rgb(56 189 248 / 0.1);
}

.liveSignalCard.is-low {
  border-color: rgb(var(--color-primary-rgb) / 0.26);
  box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.08);
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
  min-height: 1.8rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.24);
  background: rgb(var(--color-primary-rgb) / 0.1);
  color: var(--color-text-primary);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.24rem 0.62rem;
}

.liveSignalTitle,
.liveSignalStatus,
.liveSignalSummary,
.liveSignalDetail {
  margin: 0;
}

.liveSignalTitle {
  font-size: 1rem;
  line-height: 1.15;
}

.liveSignalStatus {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text-primary);
}

.liveSignalSummary {
  font-size: 0.84rem;
  line-height: 1.45;
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
}

.liveSignalDetail,
.liveSignalMeta {
  font-size: 0.74rem;
  line-height: 1.5;
  color: var(--color-text-muted);
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
  color: var(--color-primary);
  text-decoration: none;
}

.liveSignalLink:hover,
.liveSignalLink:focus-visible {
  text-decoration: underline;
}
</style>
