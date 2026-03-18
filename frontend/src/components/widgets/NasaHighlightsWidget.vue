<template>
  <section v-if="nasaEnabled" class="panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <!-- Loading -->
    <div v-if="loading" class="skeletonCard">
      <div class="skeleton skImg"></div>
      <div class="skeleton skW70"></div>
      <div class="skeleton skW50"></div>
    </div>

    <!-- Unavailable -->
    <div v-else-if="!nasaItem || !nasaItem.available" class="stateText">
      NASA novinky sú momentálne nedostupné.
    </div>

    <!-- Card -->
    <a
      v-else
      class="nasaCard"
      :href="nasaItem.link"
      target="_blank"
      rel="noopener noreferrer"
      :aria-label="`Otvoriť: ${nasaItem.title}`"
    >
      <!-- Hero image -->
      <div class="nasaImg">
        <img :src="nasaItem.image_url" :alt="nasaItem.title" loading="lazy" />
      </div>

      <!-- Title -->
      <div class="nasaTitle">{{ nasaItem.title }}</div>

      <!-- Description (2 lines max) -->
      <div v-if="nasaItem.excerpt" class="nasaDesc">{{ nasaItem.excerpt }}</div>

      <!-- CTA + updated -->
      <div class="nasaFooter">
        <span class="nasaCta">Čítať →</span>
        <span v-if="updatedLabel" class="nasaUpdated">Aktualizované {{ updatedLabel }}</span>
      </div>
    </a>
  </section>
</template>

<script>
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/services/api'

const TIME_FMT = new Intl.DateTimeFormat('sk-SK', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

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
    const hydratedFromBundle = ref(false)

    const nasaEnabled = computed(() => (
      import.meta.env.VITE_FEATURE_NASA_IOTD !== 'false' &&
      import.meta.env.VITE_FEATURE_NASA_IOTD !== '0'
    ))

    const updatedLabel = computed(() => {
      const raw = String(nasaItem.value?.updated_at || '').trim()
      if (!raw) return ''
      const d = new Date(raw)
      if (Number.isNaN(d.getTime())) return ''
      try { return TIME_FMT.format(d) } catch { return '' }
    })

    const applyPayload = (payload) => {
      nasaItem.value = payload?.available ? payload : null
      loading.value = false
      hydratedFromBundle.value = true
    }

    const fetchNasaIotd = async () => {
      loading.value = true
      nasaItem.value = null
      try {
        applyPayload((await api.get('/nasa/iotd'))?.data)
      } catch {
        nasaItem.value = null
      } finally {
        loading.value = false
      }
    }

    watch(
      () => props.initialPayload,
      (payload) => { if (payload !== undefined) applyPayload(payload) },
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
      if (!nasaEnabled.value) return
      if (props.initialPayload !== undefined || props.bundlePending) {
        if (props.bundlePending && props.initialPayload === undefined) loading.value = true
        return
      }
      fetchNasaIotd()
    })

    return { nasaItem, loading, nasaEnabled, updatedLabel, fetchNasaIotd }
  },
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.28rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

/* ── Skeleton ── */
.skeletonCard {
  display: grid;
  gap: 0.28rem;
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
  animation: shimmer 1.2s infinite;
}

.skImg  { width: 100%; aspect-ratio: 16 / 9; border-radius: 0.4rem; }
.skW70  { height: 0.72rem; width: 70%; }
.skW50  { height: 0.68rem; width: 50%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Unavailable ── */
.stateText {
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  line-height: 1.3;
}

/* ── Card (entire card is a link) ── */
.nasaCard {
  display: grid;
  gap: 0.22rem;
  text-decoration: none;
  min-width: 0;
}

.nasaImg {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: 0.4rem;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.1);
}

.nasaImg img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: opacity 0.14s ease;
}

.nasaCard:hover .nasaImg img {
  opacity: 0.88;
}

.nasaTitle {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--color-surface);
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  transition: color 0.12s ease;
}

.nasaCard:hover .nasaTitle {
  color: var(--color-primary);
}

.nasaDesc {
  color: var(--color-text-secondary);
  font-size: 0.70rem;
  line-height: 1.28;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ── Footer: CTA + updated ── */
.nasaFooter {
  display: flex;
  align-items: baseline;
  gap: 0.44rem;
  margin-top: 0.06rem;
}

.nasaCta {
  color: rgb(var(--color-primary-rgb) / 0.85);
  font-size: 0.72rem;
  font-weight: 600;
  flex-shrink: 0;
  transition: color 0.12s ease;
}

.nasaCard:hover .nasaCta {
  color: var(--color-primary);
}

.nasaUpdated {
  color: var(--color-text-secondary);
  font-size: 0.66rem;
  opacity: 0.65;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
