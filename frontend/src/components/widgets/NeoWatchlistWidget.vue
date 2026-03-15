<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-4/5"></div>
      <div class="skeleton h-10 w-full"></div>
      <div class="skeleton h-10 w-full"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa nacitat NEO</div>
      <div class="stateText">{{ error }}</div>
      <button type="button" class="ghostBtn" @click="fetchPayload">Skusit znova</button>
    </div>

    <div v-else-if="!payload?.available" class="state">
      <div class="stateTitle">NEO data su nedostupne</div>
      <div class="stateText">NASA JPL SBDB teraz nevracia pouzitelny watchlist.</div>
    </div>

    <div v-else-if="items.length === 0" class="state">
      <div class="stateTitle">Watchlist je prazdny</div>
      <div class="stateText">V aktualnom prehlade neboli ziadne blizke NEO objekty.</div>
    </div>

    <div v-else class="content">
      <article
        v-for="item in items"
        :key="itemKey(item)"
        class="neoRow"
      >
        <div class="rowHeader">
          <div class="rowTitle">{{ item.name }}</div>
          <span class="flagBadge" :class="{ danger: item.pha }">{{ item.pha ? 'PHA' : 'NEO' }}</span>
        </div>

        <p class="rowMeta">{{ formatMeta(item) }}</p>
        <p v-if="formatDetail(item)" class="rowDetail">{{ formatDetail(item) }}</p>
      </article>

      <p v-if="metaLine" class="metaLine">{{ metaLine }}</p>
    </div>
  </section>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'

export default {
  name: 'NeoWatchlistWidget',
  props: {
    title: {
      type: String,
      default: 'NEO watchlist',
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
      const sourceLabel = String(payload.value?.source?.label || 'NASA JPL SBDB').trim()
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
        applyPayload((await api.get('/sky/neo-watchlist', {
          meta: { skipErrorToast: true },
        }))?.data)
      } catch (requestError) {
        payload.value = null
        error.value = (
          requestError?.response?.data?.message
          || requestError?.message
          || 'Nepodarilo sa nacitat NEO watchlist.'
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
      formatMeta,
      formatDetail,
    }
  },
}

function itemKey(item) {
  const designation = String(item?.designation || '').trim()
  const name = String(item?.name || '').trim()
  return designation || name
}

function formatMeta(item) {
  const parts = []
  const orbitLabel = String(item?.orbit_class_label || item?.orbit_class_code || '').trim()
  const moid = formatMoid(item?.moid_au)

  if (orbitLabel) {
    parts.push(orbitLabel)
  }

  if (moid) {
    parts.push(`MOID ${moid}`)
  }

  return parts.join(' | ') || 'Bez orbitalnych detailov'
}

function formatDetail(item) {
  const parts = []

  if (item?.pha) {
    parts.push('Potencialne nebezpecny objekt')
  }

  const diameter = formatDiameter(item?.diameter_km)
  if (diameter) {
    parts.push(`Priemer ~${diameter}`)
  }

  return parts.join(' | ')
}

function formatMoid(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return ''

  if (numeric < 0.01) {
    return `${numeric.toFixed(4)} AU`
  }

  return `${numeric.toFixed(3)} AU`
}

function formatDiameter(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric <= 0) return ''

  if (numeric < 1) {
    return `${Math.round(numeric * 1000)} m`
  }

  return `${numeric.toFixed(1)} km`
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

.neoRow {
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
  min-width: 2.15rem;
  padding: 0.16rem 0.34rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.36);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: var(--color-surface);
  font-size: 0.66rem;
  font-weight: 800;
  line-height: 1.1;
}

.flagBadge.danger {
  border-color: rgb(var(--color-danger-rgb) / 0.42);
  background: rgb(var(--color-danger-rgb) / 0.14);
}

.rowMeta,
.rowDetail,
.metaLine {
  margin: 0;
}

.rowMeta,
.rowDetail,
.metaLine,
.stateText {
  color: var(--color-text-secondary);
  font-size: 0.7rem;
  line-height: 1.28;
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
.h-10 { height: 2.5rem; }
.w-4\/5 { width: 80%; }
.w-full { width: 100%; }
</style>
