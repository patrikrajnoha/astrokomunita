<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="neo-list">
      <div v-for="i in 3" :key="i" class="skeleton-row">
        <div class="skeleton sk-name"></div>
        <div class="skeleton sk-distance"></div>
        <div class="skeleton sk-orbit"></div>
      </div>
    </div>

    <div v-else-if="error" class="state state--error">
      <div class="state-title">Nepodarilo sa načítať NEO</div>
      <div class="state-text">{{ error }}</div>
      <button type="button" class="retry-btn" @click="fetchPayload">Skúsiť znova</button>
    </div>

    <div v-else-if="!payload?.available" class="state">
      <div class="state-title">NEO dáta nedostupné</div>
      <div class="state-text">NASA JPL SBDB nevracia použiteľný watchlist.</div>
    </div>

    <div v-else-if="items.length === 0" class="state">
      <div class="state-title">Watchlist je prázdny</div>
      <div class="state-text">V prehľade neboli žiadne blízke NEO objekty.</div>
    </div>

    <div v-else class="neo-list">
      <article
        v-for="item in items"
        :key="itemKey(item)"
        class="neo-row"
      >
        <div class="neo-row__body">
          <div class="neo-name">
            <svg
              v-if="item.pha"
              class="warn-icon"
              viewBox="0 0 12 11"
              fill="none"
              stroke="currentColor"
              stroke-width="1.3"
              stroke-linejoin="round"
              stroke-linecap="round"
              title="Potenciálne nebezpečný asteroid"
              aria-label="Potenciálne nebezpečný asteroid"
            >
              <path d="M6 1L11.2 10H.8L6 1Z"/>
              <line x1="6" y1="4.2" x2="6" y2="6.8"/>
              <circle cx="6" cy="8.4" r="0.55" fill="currentColor" stroke="none"/>
            </svg>
            <span class="name-text" :title="item.name">{{ item.name }}</span>
          </div>
          <div class="neo-distance" :class="proximityClass(item.moid_au)">
            <span class="dist-label">{{ proximityLabel(item.moid_au) }}</span>
            <span class="dist-sep">·</span>
            <span class="dist-raw">{{ formatMoid(item.moid_au) }}</span>
          </div>
          <div v-if="orbitLabel(item)" class="neo-orbit">{{ orbitLabel(item) }}</div>
        </div>
        <svg class="row-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </article>

      <p v-if="updatedLabel !== '-'" class="widget-footer">Aktualizované {{ updatedLabel }}</p>
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
      return [...rows]
        .sort((a, b) => {
          const aVal = Number(a?.moid_au)
          const bVal = Number(b?.moid_au)
          const aOk = Number.isFinite(aVal)
          const bOk = Number.isFinite(bVal)
          if (aOk && bOk) return aVal - bVal
          if (aOk) return -1
          if (bOk) return 1
          return 0
        })
        .slice(0, 3)
    })

    const updatedLabel = computed(() => formatTime(payload.value?.updated_at))

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
          || 'Nepodarilo sa načítať NEO watchlist.'
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
      payload,
      updatedLabel,
      itemKey,
      formatMoid,
      orbitLabel,
      proximityLabel,
      proximityClass,
    }
  },
}

function itemKey(item) {
  const designation = String(item?.designation || '').trim()
  const name = String(item?.name || '').trim()
  return designation || name
}

function orbitLabel(item) {
  return String(item?.orbit_class_label || item?.orbit_class_code || '').trim()
}

function proximityLabel(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return 'Vzdialenosť neznáma'
  if (numeric < 0.002) return 'Veľmi blízko'
  if (numeric < 0.05) return 'Blízko'
  return 'Ďaleko'
}

function proximityClass(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return 'dist--unknown'
  if (numeric < 0.002) return 'dist--very-close'
  if (numeric < 0.05) return 'dist--close'
  return 'dist--far'
}

function formatMoid(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return '— AU'

  if (numeric < 0.01) {
    return `${numeric.toFixed(4)} AU`
  }

  return `${numeric.toFixed(3)} AU`
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
  gap: 0.5rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

/* ── NEO list ── */
.neo-list {
  display: grid;
  gap: 0.3rem;
}

/* ── NEO row ── */
.neo-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  background: rgb(var(--color-bg-rgb) / 0.18);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
  min-width: 0;
}

.neo-row:hover {
  background: rgb(var(--color-primary-rgb) / 0.07);
  border-color: rgb(var(--color-primary-rgb) / 0.22);
}

.neo-row__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.18rem;
}

/* ── Name line ── */
.neo-name {
  display: flex;
  align-items: center;
  gap: 0.38rem;
  min-width: 0;
}

.warn-icon {
  flex-shrink: 0;
  width: 0.72rem;
  height: 0.72rem;
  color: var(--color-warning);
  opacity: 0.85;
}

.name-text {
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1.22;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Distance ── */
.neo-distance {
  display: flex;
  align-items: baseline;
  gap: 0.28rem;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

.dist-label {
  font-weight: 600;
}

.dist-sep {
  color: var(--color-text-secondary);
  font-weight: 400;
  opacity: 0.6;
}

.dist-raw {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  font-weight: 400;
}

.dist--very-close .dist-label { color: var(--color-warning); }
.dist--very-close .dist-raw   { color: rgb(var(--color-warning-rgb) / 0.65); }
.dist--close .dist-label      { color: var(--color-surface); }
.dist--far .dist-label        { color: var(--color-text-secondary); }
.dist--unknown .dist-label    { color: var(--color-text-secondary); }

/* ── Orbit group ── */
.neo-orbit {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Chevron ── */
.row-chevron {
  flex-shrink: 0;
  width: 0.7rem;
  height: 0.7rem;
  color: var(--color-text-secondary);
  opacity: 0.45;
  transition: opacity 0.15s ease;
}

.neo-row:hover .row-chevron {
  opacity: 0.75;
}

/* ── Skeleton loading ── */
.skeleton-row {
  display: grid;
  gap: 0.22rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.08);
}

.skeleton {
  border-radius: 0.25rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.sk-name     { height: 0.75rem; width: 78%; }
.sk-distance { height: 0.65rem; width: 35%; }
.sk-orbit    { height: 0.6rem;  width: 28%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── States ── */
.state {
  display: grid;
  gap: 0.22rem;
  padding: 0.1rem 0;
}

.state-title {
  color: var(--color-surface);
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1.24;
}

.state-text {
  color: var(--color-text-secondary);
  font-size: 0.7rem;
  line-height: 1.28;
  margin: 0;
}

.state--error .state-title,
.state--error .state-text {
  color: var(--color-danger);
}

/* ── Retry button ── */
.retry-btn {
  display: inline-block;
  margin-top: 0.2rem;
  padding: 0.22rem 0.56rem;
  border-radius: 0.36rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.retry-btn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  color: var(--color-surface);
}

/* ── Footer ── */
.widget-footer {
  margin: 0.1rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.62rem;
  line-height: 1.2;
  opacity: 0.55;
  text-align: right;
}
</style>
