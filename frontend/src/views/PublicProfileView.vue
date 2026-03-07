<template>
  <div class="page">
    <header class="topbar">
      <button class="iconBtn" @click="goHome">&larr;</button>
      <div class="topmeta">
        <div class="topname">{{ displayName }}</div>
        <div class="topsmall">@{{ handle }}</div>
      </div>
    </header>

    <div v-if="loading" class="card muted">Nacitavam profil...</div>
    <div v-else-if="err" class="card err">{{ err }}</div>

    <template v-else>
      <section class="profileShell">
        <div
          class="cover"
          data-testid="public-profile-cover"
          :class="{ 'cover--bot-fallback': coverMedia.isBotFallback }"
          :style="coverMedia.fallbackStyle"
        >
          <img v-if="coverMedia.hasImage" class="coverImg" :src="coverMedia.imageUrl" alt="cover" />
          <div class="coverGlow"></div>
        </div>

        <div class="profileHead">
          <div class="avatar">
            <UserAvatar class="avatarImg" :user="avatarUser" :alt="displayName" />
          </div>

          <div class="headActions">
            <button class="btn ghost" @click="copyProfileLink">{{ copyLabel }}</button>
          </div>
        </div>

        <div class="identity">
          <div class="nameRow">
            <h1 class="name">{{ displayName }}</h1>
            <span v-if="user?.is_admin" class="badge">Admin</span>
            <span v-if="user?.is_bot || String(user?.role || '').toLowerCase() === 'bot'" class="badge badgeBot">
              BOT
            </span>
          </div>

          <div class="handle">@{{ handle }}</div>

          <p v-if="user?.bio" class="bio">{{ user.bio }}</p>
          <p v-else class="bio muted">Zatial bez popisu.</p>

          <div class="meta">
            <span v-if="user?.location" class="metaItem">Lokalita: {{ user.location }}</span>
          </div>
        </div>

        <div class="statsRow">
          <div class="stat">
            <div class="statNum">{{ stats.posts }}</div>
            <div class="statLabel">Príspevky</div>
          </div>
          <div class="stat">
            <div class="statNum">{{ stats.replies }}</div>
            <div class="statLabel">Odpovede</div>
          </div>
          <div class="stat">
            <div class="statNum">{{ stats.media }}</div>
            <div class="statLabel">Médiá</div>
          </div>
        </div>
      </section>

      <section class="feedShell">
        <div class="tabs">
          <button
            v-for="t in tabs"
            :key="t.key"
            class="tab"
            :class="{ active: activeTab === t.key }"
            @click="setActiveTab(t.key)"
          >
            {{ t.label }}
            <span v-if="tabState[t.key].total !== null" class="tabCount">{{ tabState[t.key].total }}</span>
          </button>
        </div>

        <div v-if="tabState[activeTab].err" class="msg err">{{ tabState[activeTab].err }}</div>

        <div v-if="tabState[activeTab].loading && tabState[activeTab].items.length === 0" class="muted padTop">
          Nacitavam...
        </div>

        <div v-else-if="!tabState[activeTab].loading && tabState[activeTab].items.length === 0" class="muted padTop">
          {{ activeTab === 'observations' ? 'Zatial ziadne pozorovania.' : 'Zatial ziadny obsah.' }}
        </div>

        <div v-else-if="activeTab === 'observations'" class="observationsList">
          <ObservationCard
            v-for="item in tabState.observations.items"
            :key="item.id"
            :observation="item"
            :clickable="true"
            :show-author="false"
            @open="openObservation"
          />
        </div>

        <div v-else class="postList">
          <article v-for="p in tabState[activeTab].items" :key="p.id" class="postItem">
            <div class="avatar sm">
              <UserAvatar class="avatarImg" :user="avatarUser" :alt="displayName" />
            </div>

            <div class="postBody">
              <div class="postMeta">
                <div class="postName">{{ displayName }}</div>
                <div class="dot">·</div>
                <div class="postTime">{{ fmt(p.created_at) }}</div>
              </div>

              <div v-if="p.parent && activeTab === 'replies'" class="replyContext">
                Odpoveď na: <span class="replyAuthor">@{{ parentHandle(p) }}</span>
                <span class="replyText">{{ shorten(p.parent.content) }}</span>
              </div>

              <HashtagText class="postContent" :content="p.content" />

              <div v-if="p.attachment_url" class="attachment">
                <img
                  v-if="isImage(p)"
                  class="attachmentImg"
                  :src="p.attachment_url"
                  alt="attachment"
                />
                <a v-else class="attachmentFile" :href="p.attachment_url" target="_blank" rel="noreferrer">
                  {{ p.attachment_original_name || 'Príloha' }}
                </a>
              </div>

              <div class="postActions">
                <button class="btn outline" @click="openPost(p)">
                  Zobraziť vlákno
                </button>
              </div>
            </div>
          </article>
        </div>

        <div class="loadMore">
          <button
            v-if="tabState[activeTab].next"
            class="btn outline"
            :disabled="tabState[activeTab].loading"
            @click="loadTab(activeTab, false)"
          >
            {{ tabState[activeTab].loading ? 'Nacitavam...' : 'Nacitat viac' }}
          </button>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserAvatar from '@/components/UserAvatar.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import HashtagText from '@/components/HashtagText.vue'
import http from '@/services/api'
import { listObservations } from '@/services/observations'
import { formatDateTimeCompact } from '@/utils/dateUtils'
import { resolveUserProfileMedia } from '@/utils/profileMedia'

const router = useRouter()
const route = useRoute()

const user = ref(null)
const loading = ref(true)
const err = ref('')

const tabs = [
  { key: 'posts', label: 'Prispevky', kind: 'roots' },
  { key: 'replies', label: 'Odpovede', kind: 'replies' },
  { key: 'observations', label: 'Pozorovania', kind: 'observations' },
  { key: 'media', label: 'Media', kind: 'media' },
]

const stats = reactive({ posts: '--', replies: '--', media: '--' })
const activeTab = ref('posts')

const tabState = reactive({
  posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  replies: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  observations: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
})

const copyLabel = ref('Kopirovat link')

const username = computed(() => String(route.params.username || ''))
const displayName = computed(() => user.value?.name || 'Profil')
const handle = computed(() => user.value?.username || safeHandle(user.value?.name || 'user'))
const resolvedMedia = computed(() => resolveUserProfileMedia(user.value))
const avatarUser = computed(() => resolvedMedia.value.avatarUser)
const coverMedia = computed(() => resolvedMedia.value.cover)

function safeHandle(input) {
  return String(input).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
}

function resetTabState(tabKey) {
  if (!tabState[tabKey]) return
  tabState[tabKey].items = []
  tabState[tabKey].next = null
  tabState[tabKey].loading = false
  tabState[tabKey].err = ''
  tabState[tabKey].total = null
  tabState[tabKey].loaded = false
}

function goHome() {
  router.push({ name: 'home' })
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function openObservation(observation) {
  const observationId = Number(observation?.id || 0)
  if (!Number.isInteger(observationId) || observationId <= 0) return
  router.push(`/observations/${observationId}`)
}

function setActiveTab(key) {
  activeTab.value = key
}

function fmt(iso) {
  return formatDateTimeCompact(iso)
}

function shorten(text) {
  if (!text) return ''
  const clean = String(text).trim()
  return clean.length > 80 ? clean.slice(0, 77) + '...' : clean
}

function isImage(post) {
  const mime = post?.attachment_mime || ''
  return mime.startsWith('image/')
}

function parentHandle(post) {
  const parentUser = post?.parent?.user
  if (parentUser?.username) return parentUser.username
  const base = parentUser?.name || 'user'
  return safeHandle(base)
}

async function copyProfileLink() {
  const url = `${window.location.origin}/u/${username.value}`
  try {
    await navigator.clipboard.writeText(url)
    copyLabel.value = 'Skopirovane'
  } catch {
    copyLabel.value = 'Nepodarilo sa kopirovat'
  }
  setTimeout(() => {
    copyLabel.value = 'Kopirovat link'
  }, 1500)
}

async function loadUser() {
  loading.value = true
  err.value = ''
  user.value = null

  try {
    const { data } = await http.get(`/users/${username.value}`)
    user.value = data
  } catch (e) {
    const status = e?.response?.status
    if (status === 404) err.value = 'Profil neexistuje.'
    else err.value = e?.response?.data?.message || 'Nacitanie profilu zlyhalo.'
  } finally {
    loading.value = false
  }
}

async function loadCounts() {
  const kinds = [
    { key: 'posts', kind: 'roots' },
    { key: 'replies', kind: 'replies' },
    { key: 'media', kind: 'media' },
  ]

  for (const k of kinds) {
    try {
      const { data } = await http.get(`/users/${username.value}/posts`, {
        params: { kind: k.kind, per_page: 1 },
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
    const userId = Number(user.value?.id || 0)
    if (Number.isInteger(userId) && userId > 0) {
      const { data } = await listObservations({ user_id: userId, page: 1, per_page: 1 })
      const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
      tabState.observations.total = String(total)
    } else {
      tabState.observations.total = '--'
    }
  } catch {
    tabState.observations.total = '--'
  }
}

async function loadTab(key, reset = true) {
  const tab = tabs.find((t) => t.key === key)
  const state = tabState[key]
  if (!tab || !state) return

  if (state.loading) return
  state.loading = true
  state.err = ''

  try {
    if (tab.kind === 'observations') {
      const userId = Number(user.value?.id || 0)
      if (!Number.isInteger(userId) || userId <= 0) return

      const page = reset ? 1 : Number(state.next || 0)
      if (!page) return

      const { data } = await listObservations({
        user_id: userId,
        page,
        per_page: 10,
      })

      const rows = Array.isArray(data?.data) ? data.data : []
      state.items = reset ? rows : [...state.items, ...rows]

      const currentPage = Number(data?.current_page || page)
      const lastPage = Number(data?.last_page || currentPage)
      state.next = currentPage < lastPage ? currentPage + 1 : null
      state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
      state.loaded = true
      return
    }

    const url = reset ? `/users/${username.value}/posts` : state.next
    if (!url) return

    const { data } = await http.get(url, {
      params: reset ? { kind: tab.kind, per_page: 10 } : undefined,
    })

    const rows = data?.data ?? []
    if (reset) state.items = rows
    else state.items = [...state.items, ...rows]

    state.next = data?.next_page_url ?? null
    state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
    state.loaded = true
  } catch (e) {
    state.err = e?.response?.data?.message || 'Nacitanie zlyhalo.'
  } finally {
    state.loading = false
  }
}

async function refreshProfile() {
  await loadUser()
  if (!err.value) {
    await loadCounts()
    await loadTab(activeTab.value, true)
  }
}

watch(
  () => activeTab.value,
  (key) => {
    if (!tabState[key].loaded) loadTab(key, true)
  }
)

watch(
  () => username.value,
  async () => {
    activeTab.value = 'posts'
    resetTabState('posts')
    resetTabState('replies')
    resetTabState('observations')
    resetTabState('media')
    await refreshProfile()
  }
)

onMounted(async () => {
  await refreshProfile()
})
</script>

<style scoped>
.page {
  max-width: var(--content-max-width);
  margin: 0 auto;
  padding: 0 0 var(--space-7);
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgb(var(--bg-app-rgb) / 0.94);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--divider-color);
  padding: 0.45rem 0.86rem;
  display: flex;
  gap: var(--space-3);
  align-items: center;
}

.iconBtn {
  width: var(--control-height-lg);
  height: var(--control-height-lg);
  border-radius: 999px;
  border: 1px solid var(--border-default);
  background: rgb(var(--bg-surface-rgb) / 0.68);
  color: var(--text-primary);
  transition: border-color var(--motion-fast), background-color var(--motion-fast);
}
.iconBtn:hover { border-color: rgb(var(--primary-rgb) / 0.55); background: var(--interactive-hover); }
.iconBtn:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.topmeta { display: grid; line-height: 1.1; }
.topname { font-weight: 900; color: var(--text-primary); }
.topsmall { color: var(--text-secondary); font-size: var(--font-size-sm); }

.profileShell {
  border: 1px solid var(--border-default);
  border-radius: var(--radius-xl);
  overflow: hidden;
  margin-top: var(--space-3);
  background: rgb(var(--bg-surface-rgb) / 0.88);
}

.cover {
  height: 152px;
  position: relative;
  background:
    radial-gradient(860px 210px at 20% 20%, rgb(var(--primary-rgb) / 0.24), transparent 60%),
    radial-gradient(680px 190px at 82% 30%, rgb(var(--primary-rgb) / 0.11), transparent 60%),
    linear-gradient(180deg, rgb(var(--bg-app-rgb) / 0.24), rgb(var(--bg-app-rgb) / 0.82));
  border-bottom: 1px solid var(--divider-color);
}

.cover--bot-fallback {
  box-shadow: inset 0 0 0 1px rgb(var(--primary-rgb) / 0.24);
}
.coverImg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 0;
}
.coverGlow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(2px 2px at 20% 30%, rgb(var(--text-primary-rgb) / 0.35), transparent 60%),
    radial-gradient(2px 2px at 70% 40%, rgb(var(--text-primary-rgb) / 0.25), transparent 60%),
    radial-gradient(2px 2px at 50% 70%, rgb(var(--text-primary-rgb) / 0.2), transparent 60%);
  opacity: 0.6;
}

.profileHead {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding: 0 var(--space-4);
  transform: translateY(-28px);
}

.avatar {
  width: 88px;
  height: 88px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgb(var(--bg-app-rgb) / 0.95);
  outline: 1px solid rgb(var(--primary-rgb) / 0.55);
  background: rgb(var(--primary-rgb) / 0.16);
  color: var(--text-primary);
  font-weight: 900;
  font-size: 1.25rem;
  overflow: hidden;
}
.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.avatar.sm {
  width: 44px;
  height: 44px;
  font-size: 0.95rem;
  border-width: 1px;
  outline: 1px solid rgb(var(--primary-rgb) / 0.35);
}

.headActions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.identity {
  padding: 0 var(--space-4) var(--space-4);
  margin-top: -18px;
}
.nameRow { display: flex; align-items: center; gap: 0.5rem; }
.name { margin: 0; font-size: 1.35rem; font-weight: 950; color: var(--text-primary); }
.badge {
  font-size: var(--font-size-xs);
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--primary-rgb) / 0.46);
  background: rgb(var(--primary-rgb) / 0.14);
  color: var(--text-primary);
}

.badgeBot {
  border-color: rgb(var(--primary-rgb) / 0.5);
  background: rgb(var(--primary-rgb) / 0.16);
  color: var(--text-primary);
}
.handle { color: var(--text-secondary); margin-top: 0.15rem; }
.bio { margin: 0.75rem 0 0; color: var(--text-primary); }
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  margin-top: 0.75rem;
  color: var(--text-secondary);
  font-size: 0.9rem;
}
.metaItem { white-space: nowrap; }

.statsRow {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-top: 1px solid var(--divider-color);
  border-bottom: 1px solid var(--divider-color);
}
.stat { padding: 0.85rem 1rem; }
.statNum { font-weight: 950; font-size: 1.05rem; color: var(--text-primary); }
.statLabel { color: var(--text-secondary); font-size: var(--font-size-sm); margin-top: 0.25rem; }

.card {
  border: 1px solid var(--border-default);
  background: rgb(var(--bg-surface-rgb) / 0.86);
  border-radius: var(--radius-xl);
  padding: var(--space-4);
  margin-top: var(--space-3);
}

.feedShell {
  margin-top: 0.6rem;
  border: 0;
  border-radius: 0;
  background: transparent;
  padding: 0;
}

.tabs {
  display: flex;
  gap: 0.15rem;
  position: sticky;
  top: calc(var(--app-header-h, 56px) + 4px);
  z-index: 8;
  background: rgb(var(--bg-app-rgb) / 0.9);
  backdrop-filter: blur(8px);
  padding: 0 0 0.25rem;
  border-bottom: 1px solid var(--divider-color);
  overflow-x: auto;
  scrollbar-width: none;
}

.tab {
  padding: 0.7rem 0.25rem 0.55rem;
  border-radius: 0;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--text-secondary);
  font-weight: 700;
  font-size: 0.86rem;
  display: inline-flex;
  gap: 0.35rem;
  justify-content: center;
  align-items: center;
  white-space: nowrap;
  flex: 1;
  min-width: 0;
}
.tab.active {
  border-bottom-color: var(--accent-primary);
  color: var(--text-primary);
}

.tabCount {
  font-size: 0.72rem;
  line-height: 1;
  padding: 0.12rem 0.42rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--text-secondary-rgb) / 0.3);
  color: var(--text-secondary);
}

.padTop { margin-top: 0.75rem; }

.observationsList {
  margin-top: 0.75rem;
  display: grid;
  gap: 0.75rem;
}

.postList {
  margin-top: 0;
  display: grid;
}

.postItem {
  display: grid;
  grid-template-columns: 56px 1fr;
  gap: 0.85rem;
  padding: 0.9rem 0.1rem;
  border-top: 1px solid var(--divider-color);
  min-width: 0;
}
.postItem:first-child { border-top: 0; }

.postBody {
  min-width: 0;
}

.postMeta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  color: var(--text-secondary);
  font-size: 0.9rem;
}
.postName { color: var(--text-primary); font-weight: 950; }
.dot { opacity: 0.6; }

.replyContext {
  margin-top: 0.4rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.75rem;
  background: rgb(var(--bg-app-rgb) / 0.52);
  color: var(--text-secondary);
  font-size: 0.85rem;
}
.replyAuthor { color: var(--text-primary); font-weight: 700; margin: 0 0.25rem; }
.replyText { color: var(--text-primary); margin-left: 0.25rem; }

.postContent {
  display: block;
  max-width: 100%;
  margin-top: 0.25rem;
  color: var(--text-primary);
  white-space: pre-wrap;
  line-height: 1.55;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.attachment { margin-top: 0.6rem; }
.attachmentImg {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-subtle);
}
.attachmentFile {
  display: inline-flex;
  padding: 0.4rem 0.6rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
  text-decoration: none;
}

.postActions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.6rem;
}

.loadMore {
  display: flex;
  justify-content: center;
  padding-top: 0.75rem;
}

.btn {
  min-height: var(--control-height-md);
  padding: 0 0.9rem;
  border-radius: var(--radius-pill);
  border: 1px solid rgb(var(--primary-rgb) / 0.42);
  background: rgb(var(--primary-rgb) / 0.2);
  color: var(--text-primary);
  font-weight: 800;
}
.btn:hover { background: rgb(var(--primary-rgb) / 0.28); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn.outline {
  background: rgb(var(--bg-app-rgb) / 0.32);
  border-color: var(--border-default);
  color: var(--text-primary);
}
.btn.outline:hover { border-color: rgb(var(--primary-rgb) / 0.5); background: var(--interactive-hover); }

.btn.ghost {
  border-color: var(--border-default);
  background: rgb(var(--bg-app-rgb) / 0.3);
  color: var(--text-secondary);
}
.btn.ghost:hover { border-color: rgb(var(--primary-rgb) / 0.5); color: var(--text-primary); background: var(--interactive-hover); }

.msg {
  margin-top: 0.75rem;
  padding: 0.6rem 0.8rem;
  border-radius: 1rem;
  font-size: 0.95rem;
}
.msg.err { border: 1px solid rgb(var(--danger-rgb) / 0.45); background: rgb(var(--danger-rgb) / 0.1); color: var(--danger); }

.muted { color: var(--text-secondary); }
.err { color: var(--danger); }
</style>
