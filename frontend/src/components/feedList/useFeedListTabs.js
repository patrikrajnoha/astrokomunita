import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import {
  consumeHomeFeedPrefetch,
  consumePendingHomeFeedPrefetch,
} from '@/services/feedPrefetch'

const HOME_TABS = [
  { id: 'for_you', label: 'Komunita', tabId: 'feed-tab-for-you', panelId: 'feed-panel-for-you' },
  {
    id: 'astrobot',
    label: 'AstroFeed ✨',
    tabId: 'feed-tab-astrobot',
    panelId: 'feed-panel-astrobot',
    dataTour: 'astrofeed',
  },
]

const BOOKMARK_TABS = [
  {
    id: 'bookmarks',
    label: 'Zalozky',
    tabId: 'feed-tab-bookmarks',
    panelId: 'feed-panel-bookmarks',
  },
]

const HOME_FEED_TAB_STORAGE_KEY = 'astrokomunita.feed.activeTab'

function createFeedState() {
  return {
    items: [],
    nextPageUrl: null,
    loading: false,
    err: '',
    controller: null,
    loaded: false,
    scrollY: 0,
  }
}

export function useFeedListTabs({
  modeRef,
  api,
  bookmarks,
  avatarDebug,
  normalizeFeedError,
  reportTarget,
  closeReport,
}) {
  const isBookmarksMode = computed(() => modeRef.value === 'bookmarks')
  const tabs = computed(() => (isBookmarksMode.value ? BOOKMARK_TABS : HOME_TABS))

  const feedState = reactive({
    for_you: createFeedState(),
    astrobot: createFeedState(),
    bookmarks: createFeedState(),
  })

  function resolveInitialTab() {
    if (isBookmarksMode.value) return 'bookmarks'
    if (typeof window === 'undefined') return 'for_you'

    const stored = window.localStorage.getItem(HOME_FEED_TAB_STORAGE_KEY)
    if (stored && HOME_TABS.some((tab) => tab.id === stored)) {
      return stored
    }

    return 'for_you'
  }

  const activeTab = ref(resolveInitialTab())
  const currentFeed = computed(() => feedState[activeTab.value])
  const items = computed(() => currentFeed.value.items)
  const nextPageUrl = computed(() => currentFeed.value.nextPageUrl)
  const loading = computed(() => currentFeed.value.loading)
  const err = computed(() => currentFeed.value.err)

  function persistActiveTab(tab) {
    if (isBookmarksMode.value || typeof window === 'undefined') return
    if (!HOME_TABS.some((entry) => entry.id === tab)) return

    window.localStorage.setItem(HOME_FEED_TAB_STORAGE_KEY, tab)
  }

  function saveTabScroll(tab) {
    if (typeof window === 'undefined') return
    if (!feedState[tab]) return
    feedState[tab].scrollY = window.scrollY || 0
  }

  function restoreTabScroll(tab) {
    if (typeof window === 'undefined') return
    if (!feedState[tab]) return
    window.scrollTo({
      top: feedState[tab].scrollY || 0,
      behavior: 'auto',
    })
  }

  function resetFeed(tab) {
    const state = feedState[tab]
    if (state?.controller) {
      state.controller.abort()
    }
    state.items = []
    state.nextPageUrl = null
    state.loading = false
    state.err = ''
    state.controller = null
    state.loaded = false
  }

  async function load(reset = true, tab = activeTab.value) {
    const state = feedState[tab]
    if (!state) return
    if (state.loading) return
    state.loading = true
    state.err = ''

    try {
      if (reset && tab === 'for_you') {
        let prefetched = consumeHomeFeedPrefetch()
        if (!prefetched) {
          prefetched = await consumePendingHomeFeedPrefetch()
        }
        if (prefetched) {
          const rows = prefetched.data || []
          avatarDebug('FeedList:load-prefetched', {
            tab,
            count: rows.length,
            sampleUsers: rows.slice(0, 5).map((post) => ({
              postId: post?.id ?? null,
              userId: post?.user?.id ?? null,
              username: post?.user?.username ?? null,
              avatar_mode: post?.user?.avatar_mode ?? null,
              avatar_path: post?.user?.avatar_path ?? null,
              avatar_url: post?.user?.avatar_url ?? null,
            })),
          })

          bookmarks.hydrateFromPosts(rows)
          state.items = rows
          state.nextPageUrl = prefetched.next_page_url || null
          state.loaded = true
          return
        }
      }

      let url

      if (reset) {
        state.nextPageUrl = null

        if (tab === 'astrobot') {
          url = '/astro-feed?with=counts'
        } else if (tab === 'bookmarks') {
          url = '/me/bookmarks?with=counts'
        } else {
          url = '/feed?with=counts'
        }
      } else {
        url = state.nextPageUrl
      }

      if (!url) return

      if (state.controller) {
        state.controller.abort()
      }
      const controller = new AbortController()
      state.controller = controller

      const res = await api.get(url, {
        signal: controller.signal,
        meta: { skipErrorToast: true },
      })
      const payload = res.data || {}
      const rows = payload.data || []
      avatarDebug('FeedList:load-response', {
        tab,
        url,
        count: rows.length,
        sampleUsers: rows.slice(0, 5).map((post) => ({
          postId: post?.id ?? null,
          userId: post?.user?.id ?? null,
          username: post?.user?.username ?? null,
          avatar_mode: post?.user?.avatar_mode ?? null,
          avatar_path: post?.user?.avatar_path ?? null,
          avatar_url: post?.user?.avatar_url ?? null,
        })),
      })
      bookmarks.hydrateFromPosts(rows)

      if (reset) state.items = rows
      else state.items = [...state.items, ...rows]

      state.nextPageUrl = payload.next_page_url || null
      state.loaded = true
    } catch (e) {
      if (e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') return
      state.err = normalizeFeedError(e)
    } finally {
      state.loading = false
    }
  }

  async function switchTab(tab) {
    if (!feedState[tab]) return
    if (activeTab.value === tab) return

    saveTabScroll(activeTab.value)
    activeTab.value = tab
    persistActiveTab(tab)

    if (!feedState[tab].loaded) {
      await load(true, tab)
    }

    await nextTick()
    restoreTabScroll(tab)
  }

  async function retryCurrentTab() {
    const tab = activeTab.value
    resetFeed(tab)
    await load(true, tab)
  }

  function handleGlobalKeydown(event) {
    if (tabs.value.length < 2) {
      if (event.key !== 'Escape') return
      if (reportTarget.value) closeReport()
      return
    }

    if (event.ctrlKey && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
      event.preventDefault()
      const currentIndex = tabs.value.findIndex((tab) => tab.id === activeTab.value)
      if (currentIndex < 0) return

      const direction = event.key === 'ArrowRight' ? 1 : -1
      const nextIndex = (currentIndex + direction + tabs.value.length) % tabs.value.length
      switchTab(tabs.value[nextIndex].id)
      return
    }

    if (event.key !== 'Escape') return
    if (reportTarget.value) closeReport()
  }

  onMounted(() => {
    persistActiveTab(activeTab.value)
    load(true, activeTab.value)
    window.addEventListener('keydown', handleGlobalKeydown)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown)
    Object.values(feedState).forEach((state) => {
      if (state?.controller) {
        state.controller.abort()
      }
    })
  })

  return {
    activeTab,
    currentFeed,
    err,
    feedState,
    isBookmarksMode,
    items,
    load,
    loading,
    nextPageUrl,
    resetFeed,
    retryCurrentTab,
    switchTab,
    tabs,
  }
}
