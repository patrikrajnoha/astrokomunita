<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header" :class="{ 'panelTitle--fading': titleFading }" aria-live="polite">{{ typedTitle || '\u00A0' }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-4/5"></div>
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-3/4"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">{{ loadErrorTitle }}</div>
      <div class="stateText">{{ error }}</div>
    </div>

    <div v-else-if="activeArticles.length === 0" class="state">
      <div class="stateTitle">{{ emptyStateTitle }}</div>
    </div>

    <transition-group v-else tag="ul" name="articleSwap" class="articleList articleViewport">
      <li v-for="post in activeArticles" :key="`${mode}-${post.id}`" class="articleItem">
        <router-link class="articleLink" :to="`/articles/${post.slug}`">
          {{ post.title }}
        </router-link>
      </li>
    </transition-group>
  </section>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { blogPosts } from '@/services/blogPosts'

export default {
  name: 'LatestArticlesWidget',
  props: {
    mostReadTitle: {
      type: String,
      default: 'Najčítanejšie články',
    },
    latestTitle: {
      type: String,
      default: 'Najnovšie články',
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
    const latestArticles = ref([])
    const mode = ref('most_read')
    const loading = ref(true)
    const error = ref(null)
    const typedTitle = ref('')
    const titleFading = ref(false)
    const hydratedFromBundle = ref(false)

    let modeSwitchIntervalId = null
    let refetchIntervalId = null
    let titleFadeTimer = null

    const activeArticles = computed(() =>
      mode.value === 'most_read' ? mostReadArticles.value : latestArticles.value,
    )

    const hasLoadedAnyData = computed(
      () => mostReadArticles.value.length > 0 || latestArticles.value.length > 0,
    )

    const modeTitle = computed(() =>
      mode.value === 'most_read' ? props.mostReadTitle : props.latestTitle,
    )

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

    const setMode = (nextMode) => {
      mode.value = nextMode
    }

    const applyPayload = (payload) => {
      mostReadArticles.value = Array.isArray(payload?.most_read) ? payload.most_read.slice(0, 3) : []
      latestArticles.value = Array.isArray(payload?.latest) ? payload.latest.slice(0, 3) : []
      error.value = null
      loading.value = false
      hydratedFromBundle.value = true
    }

    const fetchWidgetData = async ({ showLoader = false } = {}) => {
      if (showLoader) {
        loading.value = true
      }

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

    const startModeSwitching = () => {
      modeSwitchIntervalId = setInterval(() => {
        setMode(mode.value === 'most_read' ? 'latest' : 'most_read')
      }, props.switchIntervalMs)
    }

    const startRefetching = () => {
      refetchIntervalId = setInterval(() => {
        fetchWidgetData({ showLoader: false })
      }, props.refetchIntervalMs)
    }

    watch(modeTitle, (nextTitle) => {
      runHeaderTyping(nextTitle)
    })

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
      startModeSwitching()
      startRefetching()
    })

    onBeforeUnmount(() => {
      if (modeSwitchIntervalId) clearInterval(modeSwitchIntervalId)
      if (refetchIntervalId) clearInterval(refetchIntervalId)
      clearTimeout(titleFadeTimer)
    })

    return {
      activeArticles,
      mode,
      loading,
      error,
      typedTitle,
      titleFading,
    }
  },
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
  min-height: 1.05rem;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
  letter-spacing: 0.01em;
  transition: opacity 180ms ease;
}

.panelTitle--fading {
  opacity: 0;
}

.panelLoading {
  display: grid;
  gap: var(--sb-gap-xs, 0.3rem);
}

.articleList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0;
}

.articleViewport {
  min-height: 3.4rem;
}

.articleItem {
  display: block;
  border-bottom: 1px solid var(--divider-color);
  padding: 0.3rem 0;
}

.articleItem:last-child {
  border-bottom: none;
}

.articleLink {
  color: var(--color-surface);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.76rem;
  line-height: 1.2;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.articleLink:hover {
  color: var(--color-primary);
}

.stateTitle {
  font-size: 0.82rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.24;
}

.stateText {
  margin-top: 0.2rem;
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  line-height: 1.32;
}

.stateError .stateTitle,
.stateError .stateText {
  color: var(--color-danger);
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
.w-4\/5 { width: 80%; }
.w-2\/3 { width: 66.666667%; }
.w-3\/4 { width: 75%; }

.articleSwap-enter-active,
.articleSwap-leave-active {
  transition: opacity 0.35s ease, transform 0.35s ease;
}

.articleSwap-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.articleSwap-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.articleSwap-move {
  transition: transform 0.35s ease;
}
</style>
