<template>
  <section class="panel">
    <!-- Header: icon + animated title -->
    <div class="panelHeader sidebarSection__header" aria-live="polite">
      <span class="panelIcon" aria-hidden="true">📰</span>
      <span class="panelTitle" :class="{ 'panelTitle--fading': titleFading }">{{ typedTitle || '\u00A0' }}</span>
    </div>

    <!-- Loading: 2 skeleton cards -->
    <div v-if="loading" class="skeletonStack">
      <div class="skeletonCard">
        <div class="skeleton skW68"></div>
        <div class="skeleton skW42"></div>
      </div>
      <div class="skeletonCard">
        <div class="skeleton skW55"></div>
        <div class="skeleton skW34"></div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="stateBox">
      <div class="stateTitle stateError">{{ loadErrorTitle }}</div>
      <div class="stateText">{{ error }}</div>
    </div>

    <!-- Empty -->
    <div v-else-if="activeArticles.length === 0" class="stateBox">
      <div class="stateTitle">{{ emptyStateTitle }}</div>
    </div>

    <!-- Articles: max 2, entire card is the link -->
    <transition-group v-else tag="ul" name="articleSwap" class="articleList">
      <li
        v-for="post in activeArticles.slice(0, 2)"
        :key="`${mode}-${post.id}`"
        class="articleItem"
      >
        <router-link class="articleCard" :to="`/articles/${post.slug}`">
          <span class="articleTitle">{{ post.title }}</span>
          <span v-if="contextLine(post)" class="articleMeta">{{ contextLine(post) }}</span>
        </router-link>
      </li>
    </transition-group>
  </section>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { blogPosts } from '@/services/blogPosts'

const VIEW_FORMAT  = new Intl.NumberFormat('sk-SK')
const SHORT_DATE   = new Intl.DateTimeFormat('sk-SK', { day: 'numeric', month: 'short' })

export default {
  name: 'LatestArticlesWidget',
  props: {
    mostReadTitle: {
      type: String,
      default: 'Najčítanejšie',
    },
    latestTitle: {
      type: String,
      default: 'Najnovšie',
    },
    switchIntervalMs: {
      type: Number,
      default: 60000,
    },
    refetchIntervalMs: {
      type: Number,
      default: 180000,
    },
    emptyStateTitle: {
      type: String,
      default: 'Zatiaľ žiadne články',
    },
    loadErrorTitle: {
      type: String,
      default: 'Nepodarilo sa načítať',
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
    const mostReadArticles = ref([])
    const latestArticles   = ref([])
    const mode             = ref('most_read')
    const loading          = ref(true)
    const error            = ref(null)
    const typedTitle       = ref('')
    const titleFading      = ref(false)
    const hydratedFromBundle = ref(false)

    let modeSwitchIntervalId = null
    let refetchIntervalId    = null
    let titleFadeTimer       = null

    const activeArticles = computed(() =>
      mode.value === 'most_read' ? mostReadArticles.value : latestArticles.value,
    )

    const hasLoadedAnyData = computed(
      () => mostReadArticles.value.length > 0 || latestArticles.value.length > 0,
    )

    const modeTitle = computed(() =>
      mode.value === 'most_read' ? props.mostReadTitle : props.latestTitle,
    )

    // Context line: view count for most-read, compact date for latest
    const contextLine = (post) => {
      if (mode.value === 'most_read') {
        const v = Number(post.views)
        if (!Number.isFinite(v) || v <= 0) return ''
        return `${VIEW_FORMAT.format(v)} prečítaní`
      }
      const raw = String(post.created_at || '').trim()
      if (!raw) return ''
      const d = new Date(raw)
      if (Number.isNaN(d.getTime())) return ''
      try { return SHORT_DATE.format(d) } catch { return '' }
    }

    const runHeaderTyping = (nextTitle) => {
      if (!typedTitle.value) {
        typedTitle.value = nextTitle
        return
      }
      titleFading.value = true
      clearTimeout(titleFadeTimer)
      titleFadeTimer = setTimeout(() => {
        typedTitle.value = nextTitle
        titleFading.value = false
      }, 180)
    }

    const applyPayload = (payload) => {
      mostReadArticles.value = Array.isArray(payload?.most_read) ? payload.most_read.slice(0, 3) : []
      latestArticles.value   = Array.isArray(payload?.latest)    ? payload.latest.slice(0, 3)    : []
      error.value            = null
      loading.value          = false
      hydratedFromBundle.value = true
    }

    const fetchWidgetData = async ({ showLoader = false } = {}) => {
      if (showLoader) loading.value = true
      try {
        applyPayload(await blogPosts.widget())
      } catch (err) {
        if (!hasLoadedAnyData.value) {
          error.value = err?.response?.data?.message || err?.message || 'Nepodarilo sa načítať články.'
        }
      } finally {
        loading.value = false
      }
    }

    watch(modeTitle, (nextTitle) => runHeaderTyping(nextTitle))

    watch(
      () => props.initialPayload,
      (payload) => { if (payload !== undefined) applyPayload(payload) },
      { immediate: true },
    )

    watch(
      () => props.bundlePending,
      (pending, wasPending) => {
        if (pending || !wasPending || hydratedFromBundle.value) return
        fetchWidgetData({ showLoader: true })
      },
    )

    onMounted(() => {
      if (props.initialPayload === undefined && props.bundlePending) {
        loading.value = true
      } else if (props.initialPayload === undefined) {
        fetchWidgetData({ showLoader: true })
      }
      runHeaderTyping(modeTitle.value)

      modeSwitchIntervalId = setInterval(() => {
        mode.value = mode.value === 'most_read' ? 'latest' : 'most_read'
      }, props.switchIntervalMs)

      refetchIntervalId = setInterval(() => {
        fetchWidgetData({ showLoader: false })
      }, props.refetchIntervalMs)
    })

    onBeforeUnmount(() => {
      if (modeSwitchIntervalId) clearInterval(modeSwitchIntervalId)
      if (refetchIntervalId)    clearInterval(refetchIntervalId)
      clearTimeout(titleFadeTimer)
    })

    return {
      activeArticles,
      mode,
      loading,
      error,
      typedTitle,
      titleFading,
      contextLine,
    }
  },
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.36rem;
  min-width: 0;
}

/* ── Header ── */
.panelHeader {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  min-height: 1.05rem;
}

.panelIcon {
  font-size: 0.8rem;
  line-height: 1;
  flex-shrink: 0;
  opacity: 0.75;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.2;
  transition: opacity 180ms ease;
}

.panelTitle--fading {
  opacity: 0;
}

/* ── Skeleton ── */
.skeletonStack {
  display: grid;
  gap: 0.22rem;
}

.skeletonCard {
  display: grid;
  gap: 0.24rem;
  padding: 0.44rem 0.52rem;
  border-radius: 0.52rem;
  background: rgb(var(--color-bg-rgb) / 0.12);
}

.skeleton {
  height: 0.68rem;
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

.skW68 { width: 68%; }
.skW42 { width: 42%; height: 0.54rem; }
.skW55 { width: 55%; }
.skW34 { width: 34%; height: 0.54rem; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── State boxes ── */
.stateBox {
  display: grid;
  gap: 0.14rem;
}

.stateTitle {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--color-surface);
  line-height: 1.22;
}

.stateText {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  line-height: 1.3;
}

.stateError {
  color: var(--color-danger, #f87171);
}

/* ── Article list ── */
.articleList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.18rem;
  min-height: 5.4rem;
}

.articleItem {
  display: block;
}

/* Each row is a card — entire surface is clickable */
.articleCard {
  display: grid;
  gap: 0.1rem;
  text-decoration: none;
  padding: 0.44rem 0.52rem;
  border-radius: 0.52rem;
  border: 1px solid transparent;
  transition: background 0.12s ease, border-color 0.12s ease;
  min-width: 0;
}

.articleCard:hover {
  background: rgb(var(--color-bg-rgb) / 0.22);
  border-color: rgb(var(--color-text-secondary-rgb) / 0.1);
}

.articleTitle {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.22;
  word-break: break-word;
  overflow-wrap: anywhere;
  transition: color 0.12s ease;
}

.articleCard:hover .articleTitle {
  color: var(--color-primary);
}

.articleMeta {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  font-weight: 400;
  line-height: 1.2;
  opacity: 0.75;
}

/* ── Transition: swap animation ── */
.articleSwap-enter-active,
.articleSwap-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.articleSwap-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.articleSwap-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.articleSwap-move {
  transition: transform 0.3s ease;
}
</style>
