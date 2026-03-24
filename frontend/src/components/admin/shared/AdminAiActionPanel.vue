<script setup>
import { computed, useSlots } from 'vue'
import AdminProgressBar from '@/components/admin/shared/AdminProgressBar.vue'

const STATUS_VALUES = ['idle', 'success', 'fallback', 'error']
const STATUS_LABELS = {
  idle: 'Pripravené',
  success: 'Hotovo',
  fallback: 'Použitá šablóna (routing)',
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
const displayProgressPercent = computed(() => {
  if (!Number.isFinite(props.progressPercent)) return null
  return Math.max(0, Math.min(100, Math.round(Number(props.progressPercent))))
})
const runButtonText = computed(() => {
  if (!props.isLoading) return props.actionLabel
  if (displayProgressPercent.value === null) return 'Prebieha...'
  return `Prebieha... ${displayProgressPercent.value}%`
})

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
    <div class="aiPanel__header">
      <div class="aiPanel__titleRow">
        <h3 class="aiPanel__title">{{ title }}</h3>
      </div>
      <p v-if="description" class="aiPanel__desc">{{ description }}</p>
    </div>

    <div class="aiPanel__actionRow">
      <button
        type="button"
        class="aiPanel__runBtn"
        :disabled="!enabled || isLoading"
        @click="triggerRun"
      >
        <span v-if="isLoading" class="aiPanel__spinner" aria-hidden="true"></span>
        {{ runButtonText }}
      </button>
      <span v-if="normalizedStatus !== 'idle'" class="aiPanel__statusText" :data-s="normalizedStatus">
        {{ statusText }}
      </span>
    </div>

    <AdminProgressBar
      v-if="isLoading"
      :active="isLoading"
      :progress-percent="progressPercent"
    />

    <p v-if="hasError" class="aiPanel__error">{{ errorMessage }}</p>

    <p v-if="!enabled" class="aiPanel__hint">AI je momentálne vypnuté.</p>

    <div v-if="$slots.default" class="aiPanel__body">
      <slot />
    </div>

    <div v-if="formattedLastRun !== '—'" class="aiPanel__meta">
      <span>{{ formattedLastRun }}</span>
      <span v-if="formattedLatency !== '—'">· {{ formattedLatency }}</span>
    </div>

    <details v-if="hasAdvancedDetails" class="aiPanel__adv" :open="advancedOpen">
      <summary>Rozšírené</summary>
      <div class="aiPanel__advBody">
        <span>latency_ms: {{ formattedLatency }}</span>
        <span>retry_count: {{ formattedRetryCount }}</span>
        <span>status_code: {{ formattedRawStatusCode }}</span>
        <slot name="advanced" />
      </div>
    </details>
  </section>
</template>

<style scoped>
.aiPanel {
  border-radius: 14px;
  background: rgb(var(--color-surface-rgb) / 0.04);
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.aiPanel__header {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.aiPanel__titleRow {
  display: flex;
  align-items: center;
}

.aiPanel__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
}

.aiPanel__desc {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.72);
  line-height: 1.4;
}

.aiPanel__actionRow {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.aiPanel__runBtn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.32);
  border-radius: 999px;
  padding: 6px 12px;
  background: rgb(var(--color-primary-rgb) / 0.10);
  color: inherit;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.12s, border-color 0.12s;
}

.aiPanel__runBtn:not(:disabled):hover {
  background: rgb(var(--color-primary-rgb) / 0.16);
  border-color: rgb(var(--color-primary-rgb) / 0.45);
}

.aiPanel__runBtn:disabled {
  opacity: 0.4;
  cursor: default;
}

.aiPanel__spinner {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  border: 1.5px solid rgb(var(--color-primary-rgb) / 0.25);
  border-top-color: rgb(var(--color-primary-rgb));
  animation: ai-spin 0.75s linear infinite;
  flex-shrink: 0;
}

@keyframes ai-spin {
  to { transform: rotate(360deg); }
}

.aiPanel__statusText {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  padding: 2px 8px;
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.7);
}

.aiPanel__statusText[data-s="success"] { color: rgb(22 163 74 / 0.9); }
.aiPanel__statusText[data-s="error"]   { color: rgb(220 38 38 / 0.9); }
.aiPanel__statusText[data-s="fallback"] { color: rgb(245 158 11 / 0.9); }

.aiPanel__error {
  margin: 0;
  font-size: 12px;
  color: rgb(220 38 38 / 0.85);
}

.aiPanel__hint {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.55);
}

.aiPanel__body {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.aiPanel__meta {
  display: flex;
  gap: 4px;
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.45);
}

.aiPanel__adv {
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding-top: 8px;
}

.aiPanel__adv > summary {
  cursor: pointer;
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.6);
  user-select: none;
}

.aiPanel__advBody {
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.aiPanel__advBody span {
  font-size: 11px;
  font-family: ui-monospace, monospace;
  color: rgb(var(--color-text-secondary-rgb) / 0.7);
}
</style>



