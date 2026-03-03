<script setup>
import { computed, useSlots } from 'vue'
import AdminProgressBar from '@/components/admin/shared/AdminProgressBar.vue'

const STATUS_VALUES = ['idle', 'success', 'fallback', 'error']
const STATUS_LABELS = {
  idle: 'Pripravené',
  success: 'Hotovo',
  fallback: 'Použitý fallback',
  error: 'Chyba',
}

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
  actionLabel: {
    type: String,
    required: true,
  },
  enabled: {
    type: Boolean,
    default: false,
  },
  status: {
    type: String,
    default: 'idle',
    validator: (value) =>
      ['idle', 'success', 'fallback', 'error'].includes(String(value || '').trim().toLowerCase()),
  },
  latencyMs: {
    type: Number,
    default: null,
  },
  lastRunAt: {
    type: String,
    default: null,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  progressPercent: {
    type: Number,
    default: null,
  },
  errorMessage: {
    type: String,
    default: '',
  },
  retryCount: {
    type: Number,
    default: null,
  },
  rawStatusCode: {
    type: [Number, String],
    default: null,
  },
  advancedOpen: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['run'])
const slots = useSlots()

const normalizedStatus = computed(() => {
  const normalized = String(props.status || '').trim().toLowerCase()
  return STATUS_VALUES.includes(normalized) ? normalized : 'idle'
})
const statusText = computed(() => STATUS_LABELS[normalizedStatus.value] || STATUS_LABELS.idle)
const statusClass = computed(() => `statusPill statusPill--${normalizedStatus.value}`)

const formattedLastRun = computed(() => {
  if (!props.lastRunAt) return '—'
  const parsed = new Date(props.lastRunAt)
  if (Number.isNaN(parsed.getTime())) return '—'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
})

const formattedLatency = computed(() => {
  if (!Number.isFinite(props.latencyMs)) return '—'
  return `${Math.max(0, Number(props.latencyMs))} ms`
})

const formattedRetryCount = computed(() => {
  if (!Number.isFinite(props.retryCount)) return '—'
  return String(Math.max(0, Number(props.retryCount)))
})

const formattedRawStatusCode = computed(() => {
  const value = String(props.rawStatusCode ?? '').trim()
  return value !== '' ? value : '—'
})

const hasError = computed(() => String(props.errorMessage || '').trim() !== '')
const hasAdvancedDetails = computed(() => {
  return (
    slots.advanced !== undefined ||
    Number.isFinite(props.retryCount) ||
    String(props.rawStatusCode ?? '').trim() !== ''
  )
})

function triggerRun() {
  if (props.enabled && !props.isLoading) {
    emit('run')
  }
}
</script>

<template>
  <section class="aiPanel">
    <header class="aiPanel__header">
      <h3 class="aiPanel__title">{{ title }}</h3>
      <p v-if="description" class="aiPanel__description">{{ description }}</p>
    </header>

    <div class="aiPanel__actions">
      <button
        type="button"
        class="aiPanel__runBtn"
        :disabled="!enabled || isLoading"
        @click="triggerRun"
      >
        {{ isLoading ? 'Prebieha...' : actionLabel }}
      </button>
      <span :class="statusClass">{{ statusText }}</span>
    </div>

    <div v-if="isLoading || Number.isFinite(progressPercent)" class="aiPanel__progress">
      <div class="aiPanel__progressLabel">
        <span class="aiPanel__spinner" aria-hidden="true"></span>
        <span>{{ isLoading ? 'Pracujem na tom...' : 'Hotovo' }}</span>
      </div>
      <AdminProgressBar :active="isLoading" :progress-percent="progressPercent" />
    </div>

    <div class="aiPanel__meta">
      <span>Posledný beh: {{ formattedLastRun }}</span>
      <span>Odozva: {{ formattedLatency }}</span>
    </div>

    <p v-if="!enabled" class="aiPanel__hint">AI pomocník je momentálne vypnutý.</p>

    <div v-if="hasError" class="aiPanel__errorRow">
      <p class="aiPanel__error">{{ errorMessage }}</p>
      <button
        type="button"
        class="aiPanel__retryBtn"
        :disabled="!enabled || isLoading"
        @click="triggerRun"
      >
        Skúsiť znova
      </button>
    </div>

    <div v-if="$slots.default" class="aiPanel__content">
      <slot />
    </div>

    <details v-if="hasAdvancedDetails" class="aiPanel__advanced" :open="advancedOpen">
      <summary>Rozšírené</summary>
      <div class="aiPanel__advancedBody">
        <p class="aiPanel__advancedMeta">latency_ms: {{ formattedLatency }}</p>
        <p class="aiPanel__advancedMeta">retry_count: {{ formattedRetryCount }}</p>
        <p class="aiPanel__advancedMeta">status_code: {{ formattedRawStatusCode }}</p>
        <slot name="advanced" />
      </div>
    </details>
  </section>
</template>

<style scoped>
.aiPanel {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.68);
  padding: 12px;
  display: grid;
  gap: 10px;
}

.aiPanel__header {
  display: grid;
  gap: 4px;
}

.aiPanel__title {
  margin: 0;
  font-size: 1rem;
}

.aiPanel__description {
  margin: 0;
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.aiPanel__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.aiPanel__runBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  border-radius: 10px;
  padding: 8px 12px;
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: inherit;
  font-weight: 600;
}

.aiPanel__runBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.statusPill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 12px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
}

.statusPill--success {
  border-color: rgb(22 163 74 / 0.45);
  background: rgb(22 163 74 / 0.12);
}

.statusPill--fallback {
  border-color: rgb(245 158 11 / 0.45);
  background: rgb(245 158 11 / 0.14);
}

.statusPill--error {
  border-color: rgb(239 68 68 / 0.45);
  background: rgb(239 68 68 / 0.14);
}

.statusPill--idle {
  border-color: rgb(var(--color-surface-rgb) / 0.25);
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.aiPanel__progress {
  display: grid;
  gap: 6px;
}

.aiPanel__progressLabel {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.aiPanel__spinner {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  border: 2px solid rgb(var(--color-primary-rgb) / 0.25);
  border-top-color: rgb(var(--color-primary-rgb) / 0.9);
  animation: ai-panel-spin 0.8s linear infinite;
}

@keyframes ai-panel-spin {
  to {
    transform: rotate(360deg);
  }
}

.aiPanel__meta {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.aiPanel__hint {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
}

.aiPanel__errorRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.aiPanel__error {
  margin: 0;
  font-size: 13px;
  color: rgb(185 28 28);
}

.aiPanel__retryBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  border-radius: 10px;
  padding: 6px 10px;
  background: transparent;
  color: inherit;
  font-size: 12px;
}

.aiPanel__retryBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.aiPanel__content {
  display: grid;
  gap: 8px;
}

.aiPanel__advanced {
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding-top: 8px;
}

.aiPanel__advanced > summary {
  cursor: pointer;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.aiPanel__advancedBody {
  margin-top: 8px;
  display: grid;
  gap: 6px;
}

.aiPanel__advancedMeta {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}
</style>
