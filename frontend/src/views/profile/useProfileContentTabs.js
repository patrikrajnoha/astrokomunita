import { computed, reactive, ref, watch } from 'vue'
import { listObservations } from '@/services/observations'
import { hasEventPlanData, mergeUniqueById } from '../profileView.utils'

export function useProfileContentTabs({
  auth,
  http,
  eventFollows,
  confirm,
}) {
  const tabs = [
    { key: 'posts', label: 'Prispevky', kind: 'roots' },
    { key: 'observations', label: 'Pozorovania', kind: 'observations' },
    { key: 'events', label: 'Udalosti', kind: 'events' },
    { key: 'bookmarks', label: 'Zalozky', kind: 'bookmarks' },
    { key: 'media', label: 'Media', kind: 'media' },
    { key: 'likes', label: 'Paci sa', kind: 'likes' },
  ]
  const eventSegments = [
    { key: 'planned', label: 'Planovane' },
    { key: 'following', label: 'Sledujes' },
  ]

  const stats = reactive({ posts: '--', replies: '--', media: '--' })
  const activeTab = ref('posts')
  const activeEventSegment = ref('planned')
  const actionMsg = ref('')
  const actionErr = ref('')
  const deleteLoadingId = ref(null)
  const pinLoadingId = ref(null)
  const pinnedPost = ref(null)

  const tabState = reactive({
    posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
    observations: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
    events: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
    bookmarks: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
    media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
    likes: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  })

  const plannedEventItems = computed(() => (
    tabState.events.items.filter((item) => hasEventPlanData(item))
  ))
  const followingEventItems = computed(() => (
    tabState.events.items.filter((item) => !hasEventPlanData(item))
  ))
  const activeEventItems = computed(() => (
    activeEventSegment.value === 'planned' ? plannedEventItems.value : followingEventItems.value
  ))
  const eventSegmentCounts = computed(() => ({
    planned: plannedEventItems.value.length,
    following: followingEventItems.value.length,
  }))
  const activeTabItems = computed(() => {
    if (activeTab.value === 'events') {
      return tabState.events.items
    }

    const state = tabState[activeTab.value]
    return Array.isArray(state?.items) ? state.items : []
  })
  const shouldShowLoadingState = computed(() => (
    Boolean(tabState[activeTab.value]?.loading) && activeTabItems.value.length === 0
  ))
  const shouldShowEmptyState = computed(() => (
    !tabState[activeTab.value]?.loading && activeTabItems.value.length === 0
  ))
  const globalEmptyTitle = computed(() => (
    activeTab.value === 'events'
      ? 'Zatiaľ nesleduješ žiadne udalosti'
      : activeTab.value === 'observations'
        ? 'Zatiaľ žiadne pozorovania'
        : 'Zatiaľ žiadny obsah'
  ))
  const globalEmptyMessage = computed(() => (
    activeTab.value === 'events'
      ? 'Sleduj udalost a zobrazime ju tu.'
      : activeTab.value === 'observations'
        ? 'Pridaj prve pozorovanie a zobrazime ho tu.'
        : 'Tento feed je momentalne prazdny.'
  ))
  const globalEmptyActionLabel = computed(() => (
    activeTab.value === 'observations' ? 'Pridať pozorovanie' : ''
  ))
  const eventSegmentEmptyTitle = computed(() => (
    activeEventSegment.value === 'planned'
      ? 'Zatiaľ nemáš plánované udalosti'
      : 'Zatiaľ nemáš udalosti v sledovaní'
  ))
  const eventSegmentEmptyMessage = computed(() => (
    activeEventSegment.value === 'planned'
      ? 'Pridaj k udalosti poznamku, pripomienku alebo cas a zobrazime ju medzi planovanymi.'
      : 'Sleduj udalost a zobrazime ju v segmente Sledujes.'
  ))

  function setActiveTab(key) {
    activeTab.value = key
  }

  function resetObservationTabState() {
    tabState.observations.items = []
    tabState.observations.next = null
    tabState.observations.err = ''
    tabState.observations.loaded = false
    tabState.observations.loading = false
    tabState.observations.total = null
  }

  function isPinnedOnProfile(post) {
    if (!post?.id) return false
    if (post?.profile_pinned_at) return true
    return Number(pinnedPost.value?.id) === Number(post.id)
  }

  function canPinProfilePost(post) {
    if (!auth.user?.id || !post?.id) return false
    if (Number(post?.parent_id || 0) > 0) return false

    const ownerId = Number(post?.user_id ?? post?.user?.id ?? 0)
    return ownerId === Number(auth.user.id)
  }

  function syncPinnedPostFromRows(rows, clearIfMissing = false) {
    const nextPinned = Array.isArray(rows)
      ? rows.find((item) => Boolean(item?.profile_pinned_at)) || null
      : null

    if (nextPinned) {
      pinnedPost.value = nextPinned
      return
    }

    if (clearIfMissing) {
      pinnedPost.value = null
    }
  }

  async function togglePin(post) {
    if (!post?.id || pinLoadingId.value) return

    if (!canPinProfilePost(post)) {
      actionErr.value = 'Môžeš pripnúť iba svoj hlavný príspevok.'
      return
    }

    actionMsg.value = ''
    actionErr.value = ''
    const wasPinned = isPinnedOnProfile(post)
    pinLoadingId.value = post.id

    try {
      await auth.csrf()

      if (wasPinned) {
        await http.patch(`/profile/posts/${post.id}/unpin`)
      } else {
        await http.patch(`/profile/posts/${post.id}/pin`)
      }

      await loadTab('posts', true)

      if (
        activeTab.value !== 'posts'
        && ['bookmarks', 'media'].includes(activeTab.value)
        && tabState[activeTab.value]?.loaded
      ) {
        await loadTab(activeTab.value, true)
      }

      actionMsg.value = wasPinned
        ? 'Príspevok bol odopnutý z profilu.'
        : 'Príspevok bol pripnutý na profile.'
    } catch (e) {
      const status = e?.response?.status
      if (status === 401) actionErr.value = 'Prihlás sa.'
      else if (status === 403) actionErr.value = 'Nemáš oprávnenie.'
      else if (status === 422) actionErr.value = e?.response?.data?.message || 'Pripnúť sa dá iba hlavný príspevok.'
      else actionErr.value = e?.response?.data?.message || 'Zmena pripnutia zlyhala.'
    } finally {
      pinLoadingId.value = null
    }
  }

  async function deletePost(post) {
    if (!post?.id || deleteLoadingId.value) return
    const ok = await confirm({
      title: 'Vymazať príspevok',
      message: 'Naozaj chceš vymazať tento príspevok?',
      confirmText: 'Vymazať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    })
    if (!ok) return

    actionMsg.value = ''
    actionErr.value = ''
    deleteLoadingId.value = post.id

    try {
      await auth.csrf()
      await http.delete(`/posts/${post.id}`)

      for (const key of Object.keys(tabState)) {
        tabState[key].items = tabState[key].items.filter((x) => x.id !== post.id)
        if (typeof tabState[key].total === 'string' && tabState[key].total !== '--') {
          const n = Number(tabState[key].total)
          tabState[key].total = Number.isFinite(n) && n > 0 ? String(n - 1) : tabState[key].total
        }
      }

      if (pinnedPost.value?.id === post.id) {
        pinnedPost.value = null
      }

      actionMsg.value = 'Príspevok bol vymazaný.'
      await loadCounts()
    } catch (e) {
      const status = e?.response?.status
      if (status === 401) actionErr.value = 'Prihlás sa.'
      else if (status === 403) actionErr.value = 'Nemáš oprávnenie.'
      else actionErr.value = e?.response?.data?.message || 'Mazanie zlyhalo.'
    } finally {
      deleteLoadingId.value = null
    }
  }

  async function loadCounts() {
    if (!auth.user) return

    const kinds = [
      { key: 'posts', kind: 'roots' },
      { key: 'replies', kind: 'replies' },
      { key: 'media', kind: 'media' },
    ]

    for (const k of kinds) {
      try {
        const { data } = await http.get('/posts', {
          params: { scope: 'me', kind: k.kind, per_page: 1 },
        })

        const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
        stats[k.key] = String(total)
        if (tabState[k.key]) {
          tabState[k.key].total = String(total)
        }
      } catch {
        stats[k.key] = '--'
        if (tabState[k.key]) {
          tabState[k.key].total = '--'
        }
      }
    }

    try {
      const { data } = await listObservations({
        mine: 1,
        page: 1,
        per_page: 1,
      })
      const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
      tabState.observations.total = String(total)
    } catch {
      tabState.observations.total = '--'
    }
  }

  async function loadTab(key, reset = true) {
    const tab = tabs.find((item) => item.key === key)
    const state = tabState[key]
    if (!tab || !state) return

    if (!auth.user) {
      state.err = 'Prihlas sa.'
      return
    }

    if (state.loading) return
    state.loading = true
    state.err = ''

    try {
      if (tab.kind === 'observations') {
        const page = reset ? 1 : Number(state.next || 0)
        if (!page) return

        const { data } = await listObservations({
          mine: 1,
          page,
          per_page: 10,
        })

        const rows = Array.isArray(data?.data) ? data.data : []
        state.items = reset
          ? mergeUniqueById([], rows)
          : mergeUniqueById(state.items, rows)

        const currentPage = Number(data?.current_page || page)
        const lastPage = Number(data?.last_page || currentPage)
        state.next = currentPage < lastPage ? currentPage + 1 : null
        state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
        state.loaded = true
        return
      }

      if (tab.kind === 'likes') {
        state.items = []
        state.next = null
        state.total = '0'
        state.loaded = true
        return
      }

      const url = reset
        ? tab.kind === 'bookmarks'
          ? '/me/bookmarks'
          : tab.kind === 'events'
            ? '/me/followed-events'
            : '/posts'
        : state.next
      if (!url) return

      const { data } = await http.get(url, {
        params:
          reset
            ? tab.kind === 'bookmarks'
              ? { per_page: 10 }
              : tab.kind === 'events'
                ? { per_page: 10 }
                : { scope: 'me', kind: tab.kind, per_page: 10 }
            : undefined,
      })

      const rows = data?.data ?? []
      if (reset) state.items = rows
      else state.items = [...state.items, ...rows]

      if (key === 'posts') {
        syncPinnedPostFromRows(state.items, reset)
      }

      if (tab.kind === 'events') {
        eventFollows.hydrateFromEvents(rows)
      }

      state.next = data?.next_page_url ?? null
      state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
      state.loaded = true
    } catch (e) {
      const status = e?.response?.status
      if (status === 401) state.err = 'Prihlas sa.'
      else state.err = e?.response?.data?.message || 'Nacitanie zlyhalo.'
    } finally {
      state.loading = false
    }
  }

  watch(
    () => activeTab.value,
    (key) => {
      if (auth.user && !tabState[key].loaded) {
        loadTab(key, true)
      }
    },
  )

  watch(
    () => eventFollows.revision,
    () => {
      if (!auth.user || activeTab.value !== 'events' || !tabState.events.loaded) return
      tabState.events.loaded = false
      loadTab('events', true)
    },
  )

  watch(
    () => auth.user?.id,
    (nextUserId, prevUserId) => {
      if (nextUserId === prevUserId) return
      resetObservationTabState()
    },
  )

  async function initializeProfileContent(onUserReady) {
    if (!auth.initialized) await auth.fetchUser()

    if (auth.user) {
      if (typeof onUserReady === 'function') {
        onUserReady()
      }
      await loadCounts()
      await loadTab(activeTab.value, true)
    }
  }

  return {
    actionErr,
    actionMsg,
    activeEventItems,
    activeEventSegment,
    activeTab,
    canPinProfilePost,
    deleteLoadingId,
    deletePost,
    eventSegmentCounts,
    eventSegmentEmptyMessage,
    eventSegmentEmptyTitle,
    eventSegments,
    followingEventItems,
    globalEmptyActionLabel,
    globalEmptyMessage,
    globalEmptyTitle,
    initializeProfileContent,
    isPinnedOnProfile,
    loadTab,
    pinLoadingId,
    pinnedPost,
    plannedEventItems,
    setActiveTab,
    shouldShowEmptyState,
    shouldShowLoadingState,
    stats,
    tabState,
    tabs,
    togglePin,
  }
}
