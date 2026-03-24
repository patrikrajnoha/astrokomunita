<template>
  <section class="panel">
    <div class="panelTitle sidebarSection__header">{{ normalizedTitle }}</div>

    <p v-if="cloudNotice && !loading && !error && items.length > 0" class="cloudNotice" :class="`cloudNotice--${cloudNotice.tone}`">
      <span class="cloudNoticeIcon" aria-hidden="true">{{ cloudNotice.emoji }}</span>
      <span>{{ cloudNotice.text }}</span>
    </p>

    <div v-if="loading" class="skeletonList" aria-hidden="true">
      <div v-for="idx in 3" :key="`sk-${idx}`" class="skeletonRow">
        <div class="skeleton skName"></div>
        <div class="skeleton skMeta"></div>
        <div class="skeleton skVisibility"></div>
      </div>
    </div>

    <div v-else-if="error" class="stateBox stateBox--error">
      <div class="stateTitle">Nepodarilo sa načítať</div>
      <div class="stateText">{{ error }}</div>
      <button type="button" class="retryBtn" @click="fetchPayload">Skúsiť znova</button>
    </div>

    <div v-else-if="items.length === 0" class="stateBox">
      <div class="stateTitle">Dnes večer bez odporúčaní</div>
      <div class="stateText">Skús widget obnoviť neskôr.</div>
    </div>

    <ul v-else class="constellationList" aria-label="Odporúčané súhvezdia">
      <li v-for="item in items" :key="itemKey(item)" class="constellationRow">
        <div class="rowTop">
          <p class="rowName">{{ displayName(item) }}</p>
          <span class="rowVisibility" :class="visibilityClass(item)">
            {{ visibilityLabel(item) }}
          </span>
        </div>

        <p class="rowMeta">
          <span>{{ item.direction }}</span>
          <span aria-hidden="true">•</span>
          <span>{{ item.best_time }}</span>
        </p>

        <p v-if="item.short_hint" class="rowHint">{{ item.short_hint }}</p>
      </li>
    </ul>

    <p v-if="metaLabel" class="widgetFooter">{{ metaLabel }}</p>
  </section>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue'
import { getSidebarWidgetBundle } from '@/services/widgets'

const EXACT_TEXT_REPLACEMENTS = {
  'Suhvezdia teraz': 'Súhvezdia teraz',
  'Suhvezdia vecer': 'Viditeľné súhvezdia',
  'Suhvezdia dnes': 'Viditeľné súhvezdia',
  'Súhvezdia večer': 'Viditeľné súhvezdia',
  'Lahko viditeľné': 'Ľahko viditeľné',
  'Stredne viditeľné': 'Stredne viditeľné',
  'Velmi dobré viditeľné': 'Veľmi dobré viditeľné',
  'cely vecer': 'celý večer',
  juhovychod: 'juhovýchod',
  vychod: 'východ',
  severovychod: 'severovýchod',
  sever: 'sever',
  Blizenci: 'Blíženci',
  Byk: 'Býk',
  'Velky pes': 'Veľký pes',
}

const WORD_REPLACEMENTS = [
  [/\baz\b/gi, 'až'],
  [/\bsu\b/gi, 'sú'],
  [/\bvecer\b/gi, 'večer'],
  [/\bcely\b/gi, 'celý'],
  [/\bvelmi\b/gi, 'veľmi'],
  [/\blahko\b/gi, 'ľahko'],
  [/\bviditeľné\b/gi, 'viditeľné'],
  [/\bviditeľná\b/gi, 'viditeľná'],
  [/\bviditelny\b/gi, 'viditeľný'],
  [/\bvychod\b/gi, 'východ'],
  [/\bjuhovychod\b/gi, 'juhovýchod'],
  [/\bseverovychod\b/gi, 'severovýchod'],
  [/\bseverozapad\b/gi, 'severozápad'],
]

const MONTH_REPLACEMENTS = {
  januar: 'január',
  februar: 'február',
  marec: 'marec',
  april: 'apríl',
  maj: 'máj',
  jun: 'jún',
  jul: 'júl',
  august: 'august',
  september: 'september',
  oktober: 'október',
  november: 'november',
  december: 'december',
}

export default {
  name: 'ConstellationsNowWidget',
  props: {
    title: {
      type: String,
      default: 'Viditeľné súhvezdia',
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
    const loading = ref(true)
    const error = ref('')
    const hydratedFromBundle = ref(false)

    const normalizedTitle = computed(() => applyDiacritics(props.title))

    const items = computed(() => {
      const rows = Array.isArray(payload.value?.items) ? payload.value.items : []
      return rows.map((entry) => normalizeItem(entry)).filter(Boolean).slice(0, 4)
    })

    const cloudNotice = computed(() => createCloudNotice(payload.value?.meta?.evening_cloud_percent))

    const metaLabel = computed(() => {
      const monthLabel = applyDiacritics(String(payload.value?.meta?.reference_month_label || '').trim())
      const referenceDate = String(payload.value?.meta?.reference_date || '').trim()
      const yearMatch = referenceDate.match(/^(\d{4})-/)
      const yearLabel = yearMatch?.[1] || ''

      if (!monthLabel && !yearLabel) return ''
      if (monthLabel && yearLabel) return `${monthLabel} ${yearLabel}`
      return monthLabel || yearLabel
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
        const response = await getSidebarWidgetBundle(['constellations_now'])
        applyPayload(response?.data?.constellations_now)
      } catch (requestError) {
        error.value = applyDiacritics(
          requestError?.response?.data?.message || requestError?.message || 'Widget sa nepodarilo načítať.',
        )
      } finally {
        loading.value = false
      }
    }

    watch(
      () => props.initialPayload,
      (nextPayload) => {
        if (nextPayload !== undefined) applyPayload(nextPayload)
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
        if (props.bundlePending && props.initialPayload === undefined) loading.value = true
        return
      }
      fetchPayload()
    })

    return {
      normalizedTitle,
      items,
      cloudNotice,
      loading,
      error,
      metaLabel,
      fetchPayload,
      itemKey,
      displayName,
      visibilityClass,
      visibilityLabel,
    }
  },
}

function normalizeItem(entry) {
  if (!entry || typeof entry !== 'object') return null

  const name = String(entry.name || '').trim()
  if (!name) return null

  const level = normalizeVisibilityLevel(entry?.visibility?.level || entry.visibility_level || entry.visibility)
  const visibilityText = String(
    entry?.visibility?.label || entry.visibility_text || visibilityLabelFromLevel(level),
  ).trim()

  return {
    ...entry,
    name,
    direction: applyDiacritics(String(entry.direction || 'sever').trim() || 'sever'),
    best_time: applyDiacritics(String(entry.best_time || 'cely vecer').trim() || 'cely vecer'),
    visibility_level: level,
    visibility_text: applyDiacritics(visibilityText || visibilityLabelFromLevel(level)),
    short_hint: applyDiacritics(String(entry.short_hint || '').trim()),
    display_name: applyDiacritics(String(entry.display_name || '').trim()),
    localized_name: applyDiacritics(String(entry.localized_name || '').trim()),
  }
}

function normalizeVisibilityLevel(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (normalized === 'high' || normalized === 'medium') return normalized
  return 'medium'
}

function visibilityLabelFromLevel(level) {
  return level === 'high' ? 'Ľahko viditeľné' : 'Stredne viditeľné'
}

function applyDiacritics(value) {
  const source = String(value || '').trim()
  if (!source) return ''

  const direct = EXACT_TEXT_REPLACEMENTS[source]
  if (direct) return direct

  const month = MONTH_REPLACEMENTS[source.toLowerCase()]
  if (month) return month

  let normalized = source
  for (const [pattern, replacement] of WORD_REPLACEMENTS) {
    normalized = normalized.replace(pattern, replacement)
  }

  return normalized
}

function itemKey(item) {
  return `${String(item?.name || '').trim()}::${String(item?.best_time || '').trim()}`
}

function displayName(item) {
  return applyDiacritics(String(item?.display_name || item?.localized_name || item?.name || '').trim())
}

function visibilityClass(item) {
  return normalizeVisibilityLevel(item?.visibility_level) === 'high' ? 'is-high' : 'is-medium'
}

function visibilityLabel(item) {
  const label = applyDiacritics(String(item?.visibility_text || '').trim())
  if (label) return label
  return visibilityLabelFromLevel(normalizeVisibilityLevel(item?.visibility_level))
}

function createCloudNotice(cloudPercentRaw) {
  if (!Number.isFinite(Number(cloudPercentRaw))) return null

  const cloudPercent = clampPercent(Number(cloudPercentRaw))
  if (cloudPercent < 60) return null

  if (cloudPercent >= 80) {
    return {
      emoji: '☁️',
      tone: 'poor',
      text: `Dnes večer bude obloha výrazne zamračená (${cloudPercent} %), súhvezdia pravdepodobne nebudú viditeľné.`,
    }
  }

  return {
    emoji: '☁️',
    tone: 'warn',
    text: `Dnes večer môže oblačnosť (${cloudPercent} %) zhoršiť viditeľnosť súhvezdí.`,
  }
}

function clampPercent(value) {
  return Math.max(0, Math.min(100, Math.round(value)))
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.36rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

.cloudNotice {
  margin: 0;
  display: flex;
  align-items: flex-start;
  gap: 0.34rem;
  padding: 0.42rem 0.52rem;
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-warning-rgb) / 0.32);
  background: rgb(var(--color-warning-rgb) / 0.1);
  color: rgb(var(--color-warning-rgb) / 0.96);
  font-size: 0.69rem;
  line-height: 1.32;
}

.cloudNotice--poor {
  border-color: rgb(var(--color-danger-rgb) / 0.34);
  background: rgb(var(--color-danger-rgb) / 0.1);
  color: rgb(var(--color-danger-rgb) / 0.96);
}

.cloudNoticeIcon {
  line-height: 1;
  margin-top: 0.02rem;
}

.constellationList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.24rem;
}

.constellationRow {
  display: grid;
  gap: 0.2rem;
  padding: 0.54rem 0.58rem;
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  background: rgb(var(--color-bg-rgb) / 0.13);
}

.rowTop {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.38rem;
}

.rowName {
  margin: 0;
  min-width: 0;
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1.24;
}

.rowVisibility {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1px solid transparent;
  padding: 0.1rem 0.42rem;
  font-size: 0.64rem;
  font-weight: 700;
  line-height: 1.08;
  white-space: nowrap;
  flex-shrink: 0;
}

.rowVisibility.is-high {
  border-color: rgb(var(--color-success-rgb) / 0.36);
  background: rgb(var(--color-success-rgb) / 0.12);
  color: rgb(var(--color-success-rgb) / 0.96);
}

.rowVisibility.is-medium {
  border-color: rgb(var(--color-warning-rgb) / 0.4);
  background: rgb(var(--color-warning-rgb) / 0.12);
  color: rgb(var(--color-warning-rgb) / 0.96);
}

.rowMeta {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.3rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.71rem;
  line-height: 1.24;
}

.rowHint {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
  font-size: 0.69rem;
  line-height: 1.3;
}

.skeletonList {
  display: grid;
  gap: 0.24rem;
}

.skeletonRow {
  display: grid;
  gap: 0.14rem;
  padding: 0.56rem;
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.1);
}

.skeleton {
  border-radius: 0.24rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

.skName {
  height: 0.72rem;
  width: 58%;
}

.skMeta {
  height: 0.58rem;
  width: 72%;
}

.skVisibility {
  height: 0.58rem;
  width: 42%;
}

.stateBox {
  display: grid;
  gap: 0.2rem;
  padding: 0.56rem;
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  background: rgb(var(--color-bg-rgb) / 0.12);
}

.stateBox--error {
  border-color: rgb(var(--color-danger-rgb) / 0.34);
  background: rgb(var(--color-danger-rgb) / 0.08);
}

.stateTitle {
  margin: 0;
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--color-surface);
}

.stateText {
  margin: 0;
  font-size: 0.7rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.retryBtn {
  justify-self: start;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
  background: transparent;
  color: var(--color-surface);
  border-radius: 999px;
  font-size: 0.68rem;
  padding: 0.24rem 0.54rem;
  cursor: pointer;
}

.retryBtn:hover {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.46);
}

.widgetFooter {
  margin: 0;
  font-size: 0.68rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.72);
  text-align: right;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
