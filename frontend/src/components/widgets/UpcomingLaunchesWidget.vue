<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-4/5"></div>
      <div class="skeleton h-12 w-full"></div>
      <div class="skeleton h-12 w-full"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa nacitat starty</div>
      <div class="stateText">{{ error }}</div>
      <button type="button" class="ghostBtn" @click="fetchPayload">Skusit znova</button>
    </div>

    <div v-else-if="!payload?.available" class="state">
      <div class="stateTitle">Launch data su nedostupne</div>
      <div class="stateText">Launch Library 2 teraz nevracia pouzitelny prehlad startov.</div>
    </div>

    <div v-else-if="items.length === 0" class="state">
      <div class="stateTitle">Ziadny blizky start</div>
      <div class="stateText">V aktualnom prehlade nie je ziaden potvrdeny nadchadzajuci start.</div>
    </div>

    <div v-else class="content">
      <p v-if="payload?.stale" class="staleNotice">Zobrazeny je posledny potvrdeny prehlad startov.</p>

      <article
        v-for="item in items"
        :key="itemKey(item)"
        class="launchRow"
      >
        <div class="rowHeader">
          <div class="rowTitle">{{ item.name }}</div>
          <span
            v-if="statusLabel(item)"
            class="flagBadge"
            :class="statusToneClass(item)"
            :title="item?.status?.description || statusLabel(item)"
          >
            {{ statusLabel(item) }}
          </span>
        </div>

        <p class="rowMeta">
          <time :datetime="item.net || item.window_start || ''">{{ formatNet(item) }}</time>
          <span v-if="formatCountdown(item)"> | {{ formatCountdown(item) }}</span>
        </p>
        <p v-if="formatProviderMeta(item)" class="rowDetail">{{ formatProviderMeta(item) }}</p>
        <p v-if="item.location" class="rowDetail">{{ item.location }}</p>
        <p v-if="item.mission_name" class="rowDetail">{{ item.mission_name }}</p>
      </article>

      <p v-if="metaLine" class="metaLine">{{ metaLine }}</p>
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
      default: 'Bliziace sa starty',
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

    const metaLine = computed(() => {
      const sourceLabel = String(payload.value?.source?.label || 'The Space Devs Launch Library 2').trim()
      const updatedLabel = formatTime(payload.value?.updated_at)
      const parts = []

      if (sourceLabel) {
        parts.push(`Zdroj: ${sourceLabel}`)
      }

      if (updatedLabel !== '-') {
        parts.push(`Aktualizovane: ${updatedLabel}`)
      }

      return parts.join(' | ')
    })

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
          || 'Nepodarilo sa nacitat prehlad startov.'
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
      metaLine,
      payload,
      itemKey,
      formatCountdown,
      formatNet,
      formatProviderMeta,
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

function statusLabel(item) {
  return String(item?.status?.abbrev || item?.status?.label || '').trim()
}

function statusToneClass(item) {
  const normalized = statusLabel(item).toLowerCase()
  if (normalized === 'go') return 'good'
  if (normalized === 'tbd' || normalized === 'tbc') return 'muted'
  if (normalized.includes('hold')) return 'warn'
  if (normalized.includes('scrub')) return 'danger'
  return 'muted'
}

function formatNet(item) {
  const raw = String(item?.net || item?.window_start || '').trim()
  if (!raw) return 'Cas startu sa upresnuje'

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return 'Cas startu sa upresnuje'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      day: 'numeric',
      month: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  } catch {
    return 'Cas startu sa upresnuje'
  }
}

function formatCountdown(item) {
  const raw = String(item?.net || item?.window_start || '').trim()
  if (!raw) return ''

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return ''

  const diffMs = parsed.getTime() - Date.now()
  if (diffMs <= -30 * 60 * 1000) return ''
  if (diffMs < 0) return 'Prave prebieha'

  const diffMinutes = Math.round(diffMs / (60 * 1000))
  if (diffMinutes < 60) {
    return `Za ${diffMinutes} min`
  }

  const diffHours = Math.round(diffMinutes / 60)
  if (diffHours < 48) {
    return `Za ${diffHours} h`
  }

  const diffDays = Math.round(diffHours / 24)
  return `Za ${diffDays} dni`
}

function formatProviderMeta(item) {
  const parts = []
  const provider = String(item?.provider || '').trim()
  const pad = String(item?.pad || '').trim()

  if (provider) {
    parts.push(provider)
  }

  if (pad) {
    parts.push(pad)
  }

  return parts.join(' | ')
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
  gap: 0.24rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

.panelLoading,
.content {
  display: grid;
  gap: 0.24rem;
}

.launchRow {
  display: grid;
  gap: 0.14rem;
  padding: 0.38rem 0.44rem;
  border: 1px solid var(--divider-color);
  background: rgb(var(--color-bg-rgb) / 0.2);
  border-radius: 0.56rem;
}

.rowHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}

.rowTitle {
  color: var(--color-surface);
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1.2;
  overflow-wrap: anywhere;
}

.flagBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.3rem;
  padding: 0.16rem 0.34rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.36);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: var(--color-surface);
  font-size: 0.66rem;
  font-weight: 800;
  line-height: 1.1;
}

.flagBadge.good {
  border-color: rgb(var(--color-success-rgb) / 0.4);
  background: rgb(var(--color-success-rgb) / 0.14);
}

.flagBadge.warn {
  border-color: rgb(var(--color-warning-rgb) / 0.42);
  background: rgb(var(--color-warning-rgb) / 0.14);
}

.flagBadge.danger {
  border-color: rgb(var(--color-danger-rgb) / 0.42);
  background: rgb(var(--color-danger-rgb) / 0.14);
}

.rowMeta,
.rowDetail,
.metaLine,
.stateText,
.staleNotice {
  color: var(--color-text-secondary);
  font-size: 0.7rem;
  line-height: 1.28;
  margin: 0;
}

.staleNotice {
  color: var(--color-warning);
}

.stateTitle {
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 800;
  line-height: 1.24;
}

.stateError .stateTitle,
.stateError .stateText {
  color: var(--color-danger);
}

.ghostBtn {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  border: 0;
  box-sizing: border-box;
  text-align: center;
  white-space: normal;
  overflow-wrap: anywhere;
  font-size: 0.72rem;
  line-height: 1.12;
  border-radius: 0 !important;
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
  box-shadow: inset 0 0 0 1px var(--color-text-secondary);
}

.ghostBtn:hover {
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
  box-shadow: inset 0 0 0 1px var(--color-primary);
  transform: none;
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-4 { height: 1rem; }
.h-12 { height: 3rem; }
.w-4\/5 { width: 80%; }
.w-full { width: 100%; }
</style>
