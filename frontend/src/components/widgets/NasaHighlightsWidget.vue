<template>
  <section v-if="nasaEnabled" class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton nasaThumb"></div>
      <div class="skeleton h-4 w-4/5"></div>
      <div class="skeleton h-4 w-2/3"></div>
    </div>

    <div v-else-if="!nasaItem || !nasaItem.available" class="state">
      <div class="stateText">NASA novinky su momentalne nedostupne.</div>
    </div>

    <div v-else class="nasaCard">
      <a
        class="nasaImageLink"
        :href="nasaItem.link"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="`Otvorit NASA detail: ${nasaItem.title}`"
      >
        <div class="nasaImageWrap">
          <img
            :src="nasaItem.image_url"
            :alt="nasaItem.title"
            loading="lazy"
          />
        </div>
      </a>

      <div class="nasaTitle">{{ nasaItem.title }}</div>
      <div v-if="nasaItem.excerpt" class="nasaExcerpt">{{ nasaItem.excerpt }}</div>
      <p v-if="metaLine" class="metaLine">{{ metaLine }}</p>

      <div class="panelActions">
        <a
          class="nasaActionBtn"
          :href="nasaItem.link"
          target="_blank"
          rel="noopener noreferrer"
          :aria-label="`Otvorit NASA.gov clanok: ${nasaItem.title}`"
          style="border-radius:0;display:block;width:100%;max-width:100%;box-sizing:border-box"
        >
          Zobrazit na NASA.gov
        </a>
      </div>
    </div>
  </section>
</template>

<script>
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/services/api'

export default {
  name: 'NasaHighlightsWidget',
  props: {
    title: {
      type: String,
      default: 'NASA Novinky',
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
    const nasaItem = ref(null)
    const loading = ref(false)
    const error = ref(null)
    const hydratedFromBundle = ref(false)

    const nasaEnabled = computed(() => {
      return (
        import.meta.env.VITE_FEATURE_NASA_IOTD !== 'false' &&
        import.meta.env.VITE_FEATURE_NASA_IOTD !== '0'
      )
    })

    const metaLine = computed(() => {
      const sourceLabel = String(nasaItem.value?.source?.label || 'NASA').trim()
      const updatedLabel = formatTime(nasaItem.value?.updated_at)
      const parts = []

      if (sourceLabel) {
        parts.push(`Zdroj: ${sourceLabel}`)
      }

      if (updatedLabel !== '-') {
        parts.push(`Aktualizovane: ${updatedLabel}`)
      }

      return parts.join(' | ')
    })

    const applyPayload = (payload) => {
      if (payload && payload.available) {
        nasaItem.value = payload
      } else {
        nasaItem.value = null
      }

      error.value = null
      loading.value = false
      hydratedFromBundle.value = true
    }

    const fetchNasaIotd = async () => {
      loading.value = true
      error.value = null
      nasaItem.value = null

      try {
        applyPayload((await api.get('/nasa/iotd'))?.data)
      } catch (err) {
        error.value =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa nacitat NASA novinky.'
        nasaItem.value = null
      } finally {
        loading.value = false
      }
    }

    watch(
      () => props.initialPayload,
      (payload) => {
        if (payload !== undefined) {
          applyPayload(payload)
        }
      },
      { immediate: true },
    )

    watch(
      () => props.bundlePending,
      (pending, wasPending) => {
        if (pending || !wasPending || hydratedFromBundle.value || !nasaEnabled.value) return
        fetchNasaIotd()
      },
    )

    onMounted(() => {
      if (!nasaEnabled.value) {
        return
      }

      if (props.initialPayload !== undefined || props.bundlePending) {
        if (props.bundlePending && props.initialPayload === undefined) {
          loading.value = true
        }
        return
      }

      fetchNasaIotd()
    })

    return {
      nasaItem,
      loading,
      error,
      nasaEnabled,
      metaLine,
      fetchNasaIotd,
    }
  },
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

.panelLoading {
  display: grid;
  gap: var(--sb-gap-xs, 0.3rem);
}

.nasaCard {
  display: grid;
  gap: 0.24rem;
  min-width: 0;
}

.nasaThumb {
  width: 100%;
  aspect-ratio: 16 / 8.8;
  border-radius: 0;
}

.nasaImageWrap {
  width: 100%;
  aspect-ratio: 16 / 9.6;
  max-height: 104px;
  border-radius: 0;
  overflow: hidden;
  border: 1px solid var(--divider-color);
}

.nasaImageLink {
  display: block;
}

.nasaImageWrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.nasaTitle {
  font-size: 0.86rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.18;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.nasaExcerpt {
  color: var(--color-text-secondary);
  font-size: 0.74rem;
  line-height: 1.25;
  display: -webkit-box;
  line-clamp: 1;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.metaLine {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.25;
}

.panelActions {
  display: block;
  width: 100%;
  min-width: 0;
  padding-top: 0;
  margin-bottom: 0.16rem;
}

.nasaActionBtn {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  border-radius: 0 !important;
  border-top-left-radius: 0 !important;
  border-top-right-radius: 0 !important;
  border-bottom-left-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
  border: 0;
  box-shadow: inset 0 0 0 1px var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
  font-size: 0.72rem;
  line-height: 1.2;
  box-sizing: border-box;
  text-align: center;
  white-space: normal;
  text-decoration: none;
  overflow-wrap: anywhere;
}

.nasaCard .ghostbtn {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 1.68rem;
  padding: 0.24rem 0.48rem;
  box-sizing: border-box;
  border: 0 !important;
  box-shadow: inset 0 0 0 1px var(--color-text-secondary) !important;
  border-radius: 0 !important;
  text-align: center;
  text-decoration: none;
  white-space: normal;
  overflow-wrap: anywhere;
}

.nasaActionBtn:hover {
  box-shadow: inset 0 0 0 1px var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
  transform: none;
}

.nasaCard .ghostbtn:hover {
  box-shadow: inset 0 0 0 1px var(--color-primary) !important;
  transform: none !important;
}

.stateText {
  color: var(--color-text-secondary);
  font-size: 0.82rem;
  line-height: 1.32;
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

.nasaCard,
.nasaImageWrap,
.nasaImageWrap img {
  border-radius: 0 !important;
}

.nasaCard * {
  border-radius: 0 !important;
  min-width: 0;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-4 { height: 1rem; }
.w-4\/5 { width: 80%; }
.w-2\/3 { width: 66.666667%; }

@media (max-width: 420px) {
  .nasaImageWrap {
    max-height: 96px;
  }

  .nasaExcerpt {
    -webkit-line-clamp: 2;
    line-clamp: 2;
  }
}
</style>
