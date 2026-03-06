<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header" aria-live="polite">{{ typedTitle || '\u00A0' }}</div>

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
        <router-link class="articleLink" :to="`/clanky/${post.slug}`">
          {{ post.title }}
        </router-link>
      </li>
    </transition-group>

    <div v-if="hasLoadedAnyData" class="modeIndicator" :class="{ spin: modeChangeTick > 0 }" aria-hidden="true">o</div>
    <div v-if="hasLoadedAnyData" class="switchProgress" aria-hidden="true">
      <span class="switchProgressBar" :style="{ transform: `scaleX(${switchProgress})` }"></span>
    </div>
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
      default: 'Zatial ziadne clanky',
    },
    loadErrorTitle: {
      type: String,
      default: 'Nepodarilo sa nacitat',
    },
  },
  setup(props) {
    const mostReadArticles = ref([])
    const latestArticles = ref([])
    const mode = ref('most_read')
    const loading = ref(true)
    const error = ref(null)
    const typedTitle = ref('')
    const switchProgress = ref(0)
    const modeChangeTick = ref(0)

    let modeSwitchIntervalId = null
    let refetchIntervalId = null
    let progressIntervalId = null
    let cycleStartTs = Date.now()
    let titleAnimationToken = 0
    const timeoutIds = new Set()

    const activeArticles = computed(() =>
      mode.value === 'most_read' ? mostReadArticles.value : latestArticles.value,
    )

    const hasLoadedAnyData = computed(
      () => mostReadArticles.value.length > 0 || latestArticles.value.length > 0,
    )

    const modeTitle = computed(() =>
      mode.value === 'most_read' ? props.mostReadTitle : props.latestTitle,
    )

    const clearTrackedTimeouts = () => {
      for (const id of timeoutIds) {
        clearTimeout(id)
      }
      timeoutIds.clear()
    }

    const wait = (ms) =>
      new Promise((resolve) => {
        const id = setTimeout(() => {
          timeoutIds.delete(id)
          resolve()
        }, ms)
        timeoutIds.add(id)
      })

    const runHeaderTyping = async (nextTitle) => {
      const token = ++titleAnimationToken

      while (typedTitle.value.length > 0) {
        if (token !== titleAnimationToken) return
        typedTitle.value = typedTitle.value.slice(0, -1)
        await wait(14)
      }

      await wait(80)

      for (let i = 1; i <= nextTitle.length; i += 1) {
        if (token !== titleAnimationToken) return
        typedTitle.value = nextTitle.slice(0, i)
        await wait(28)
      }
    }

    const setMode = (nextMode) => {
      mode.value = nextMode
      cycleStartTs = Date.now()
      switchProgress.value = 0
      modeChangeTick.value += 1
      const id = setTimeout(() => {
        timeoutIds.delete(id)
        modeChangeTick.value = 0
      }, 420)
      timeoutIds.add(id)
    }

    const fetchWidgetData = async ({ showLoader = false } = {}) => {
      if (showLoader) {
        loading.value = true
      }

      try {
        const payload = await blogPosts.widget()
        mostReadArticles.value = Array.isArray(payload?.most_read) ? payload.most_read.slice(0, 3) : []
        latestArticles.value = Array.isArray(payload?.latest) ? payload.latest.slice(0, 3) : []
        error.value = null
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

    const startProgressUpdates = () => {
      progressIntervalId = setInterval(() => {
        const elapsed = Date.now() - cycleStartTs
        switchProgress.value = Math.min(Math.max(elapsed / props.switchIntervalMs, 0), 1)
      }, 100)
    }

    watch(modeTitle, (nextTitle) => {
      runHeaderTyping(nextTitle)
    })

    onMounted(() => {
      fetchWidgetData({ showLoader: true })
      runHeaderTyping(modeTitle.value)
      startModeSwitching()
      startRefetching()
      startProgressUpdates()
    })

    onBeforeUnmount(() => {
      if (modeSwitchIntervalId) clearInterval(modeSwitchIntervalId)
      if (refetchIntervalId) clearInterval(refetchIntervalId)
      if (progressIntervalId) clearInterval(progressIntervalId)
      titleAnimationToken += 1
      clearTrackedTimeouts()
    })

    return {
      activeArticles,
      mode,
      loading,
      error,
      hasLoadedAnyData,
      typedTitle,
      switchProgress,
      modeChangeTick,
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
  overflow: hidden;
}

.panel {
  display: grid;
  gap: var(--sb-gap-sm, 0.5rem);
  min-width: 0;
}

.panelTitle {
  min-height: 1.05rem;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
  letter-spacing: 0.01em;
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
  min-height: 4.2rem;
}

.articleItem {
  display: block;
  border-bottom: 1px solid var(--divider-color);
  padding: 0.38rem 0;
}

.articleItem:last-child {
  border-bottom: none;
}

.articleLink {
  color: var(--color-surface);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.8rem;
  line-height: 1.24;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
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
  border-radius: 0.75rem;
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

.modeIndicator {
  position: absolute;
  top: 0.62rem;
  right: 0.62rem;
  font-size: 0.64rem;
  opacity: 0.26;
  color: var(--color-surface);
  transform-origin: 50% 50%;
}

.modeIndicator.spin {
  animation: modeSpin 0.42s ease;
}

.switchProgress {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 1px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--divider-color);
}

.switchProgressBar {
  display: block;
  width: 100%;
  height: 100%;
  transform-origin: left center;
  background: rgb(var(--color-primary-rgb) / 0.55);
  transition: transform 0.12s linear;
}

@keyframes modeSpin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(180deg); }
}
</style>
