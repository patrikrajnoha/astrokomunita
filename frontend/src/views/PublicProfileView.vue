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
        <div class="cover">
          <div class="coverGlow"></div>
        </div>

        <div class="profileHead">
          <div class="avatar">
            <span>{{ initials }}</span>
          </div>

          <div class="headActions">
            <button class="btn ghost" @click="copyProfileLink">{{ copyLabel }}</button>
          </div>
        </div>

        <div class="identity">
          <div class="nameRow">
            <h1 class="name">{{ displayName }}</h1>
            <span v-if="user?.is_admin" class="badge">Admin</span>
          </div>

          <div class="handle">@{{ handle }}</div>

          <p v-if="user?.bio" class="bio">{{ user.bio }}</p>
          <p v-else class="bio muted">Zatial bez popisu.</p>

          <div class="meta">
            <span v-if="user?.location" class="metaItem">Location: {{ user.location }}</span>
          </div>
        </div>

        <div class="statsRow">
          <div class="stat">
            <div class="statNum">{{ stats.posts }}</div>
            <div class="statLabel">Posts</div>
          </div>
          <div class="stat">
            <div class="statNum">{{ stats.replies }}</div>
            <div class="statLabel">Replies</div>
          </div>
          <div class="stat">
            <div class="statNum">{{ stats.media }}</div>
            <div class="statLabel">Media</div>
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
          Zatial ziadny obsah.
        </div>

        <div v-else class="postList">
          <article v-for="p in tabState[activeTab].items" :key="p.id" class="postItem">
            <div class="avatar sm">
              <span>{{ initials }}</span>
            </div>

            <div class="postBody">
              <div class="postMeta">
                <div class="postName">{{ displayName }}</div>
                <div class="dot">.</div>
                <div class="postTime">{{ fmt(p.created_at) }}</div>
              </div>

              <div v-if="p.parent && activeTab === 'replies'" class="replyContext">
                Reply to: <span class="replyAuthor">@{{ parentHandle(p) }}</span>
                <span class="replyText">{{ shorten(p.parent.content) }}</span>
              </div>

              <div class="postContent">{{ p.content }}</div>

              <div v-if="p.attachment_url" class="attachment">
                <img
                  v-if="isImage(p)"
                  class="attachmentImg"
                  :src="p.attachment_url"
                  alt="attachment"
                />
                <a v-else class="attachmentFile" :href="p.attachment_url" target="_blank" rel="noreferrer">
                  {{ p.attachment_original_name || 'Attachment' }}
                </a>
              </div>

              <div class="postActions">
                <button class="btn outline" @click="openPost(p)">
                  View thread
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
import { http } from '@/lib/http'

const router = useRouter()
const route = useRoute()

const user = ref(null)
const loading = ref(true)
const err = ref('')

const tabs = [
  { key: 'posts', label: 'Posts', kind: 'roots' },
  { key: 'replies', label: 'Replies', kind: 'replies' },
  { key: 'media', label: 'Media', kind: 'media' },
]

const stats = reactive({ posts: '--', replies: '--', media: '--' })
const activeTab = ref('posts')

const tabState = reactive({
  posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  replies: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
})

const copyLabel = ref('Kopirovat link')

const username = computed(() => String(route.params.username || ''))
const displayName = computed(() => user.value?.name || 'Profil')
const handle = computed(() => user.value?.username || safeHandle(user.value?.name || 'user'))

const initials = computed(() => {
  const n = user.value?.name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
})

function safeHandle(input) {
  return String(input).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
}

function goHome() {
  router.push({ name: 'home' })
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function setActiveTab(key) {
  activeTab.value = key
}

function fmt(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return String(iso)
  }
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
    const { data } = await http.get(`/api/users/${username.value}`)
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
      const { data } = await http.get(`/api/users/${username.value}/posts`, {
        params: { kind: k.kind, per_page: 1 },
      })

      const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
      stats[k.key] = String(total)
      tabState[k.key].total = String(total)
    } catch {
      stats[k.key] = '--'
      tabState[k.key].total = '--'
    }
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
    const url = reset ? `/api/users/${username.value}/posts` : state.next
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
    tabState.posts.loaded = false
    tabState.replies.loaded = false
    tabState.media.loaded = false
    tabState.posts.items = []
    tabState.replies.items = []
    tabState.media.items = []
    await refreshProfile()
  }
)

onMounted(async () => {
  await refreshProfile()
})
</script>

<style scoped>
.page {
  max-width: 820px;
  margin: 0 auto;
  padding: 0 1rem 2rem;
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgba(2, 6, 23, 0.72);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(51, 65, 85, 0.6);
  padding: 0.75rem 0.5rem;
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.iconBtn {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  border: 1px solid rgba(51, 65, 85, 0.8);
  background: rgba(15, 23, 42, 0.35);
  color: rgb(226 232 240);
}
.iconBtn:hover { border-color: rgba(99, 102, 241, 0.85); }

.topmeta { display: grid; line-height: 1.1; }
.topname { font-weight: 900; color: rgb(226 232 240); }
.topsmall { color: rgb(148 163 184); font-size: 0.85rem; }

.profileShell {
  border: 1px solid rgba(51, 65, 85, 0.75);
  border-radius: 1.25rem;
  overflow: hidden;
  margin-top: 1rem;
  background: rgba(2, 6, 23, 0.55);
}

.cover {
  height: 160px;
  position: relative;
  background:
    radial-gradient(900px 220px at 20% 20%, rgba(99, 102, 241, 0.25), transparent 60%),
    radial-gradient(700px 220px at 80% 30%, rgba(34, 197, 94, 0.12), transparent 60%),
    linear-gradient(180deg, rgba(15, 23, 42, 0.2), rgba(2, 6, 23, 0.9));
  border-bottom: 1px solid rgba(51, 65, 85, 0.6);
}
.coverGlow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,0.35), transparent 60%),
    radial-gradient(2px 2px at 70% 40%, rgba(255,255,255,0.25), transparent 60%),
    radial-gradient(2px 2px at 50% 70%, rgba(255,255,255,0.2), transparent 60%);
  opacity: 0.6;
}

.profileHead {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding: 0 1rem;
  transform: translateY(-28px);
}

.avatar {
  width: 88px;
  height: 88px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgba(2, 6, 23, 0.95);
  outline: 1px solid rgba(99, 102, 241, 0.55);
  background: rgba(99, 102, 241, 0.16);
  color: white;
  font-weight: 900;
  font-size: 1.25rem;
}
.avatar.sm {
  width: 44px;
  height: 44px;
  font-size: 0.95rem;
  border-width: 1px;
  outline: 1px solid rgba(99, 102, 241, 0.35);
}

.headActions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.identity {
  padding: 0 1rem 1rem;
  margin-top: -18px;
}
.nameRow { display: flex; align-items: center; gap: 0.5rem; }
.name { margin: 0; font-size: 1.35rem; font-weight: 950; color: rgb(226 232 240); }
.badge {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgba(34, 197, 94, 0.55);
  background: rgba(34, 197, 94, 0.12);
  color: rgb(187 247 208);
}
.handle { color: rgb(148 163 184); margin-top: 0.15rem; }
.bio { margin: 0.75rem 0 0; color: rgb(226 232 240); }
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  margin-top: 0.75rem;
  color: rgb(148 163 184);
  font-size: 0.9rem;
}
.metaItem { white-space: nowrap; }

.statsRow {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-top: 1px solid rgba(51, 65, 85, 0.55);
  border-bottom: 1px solid rgba(51, 65, 85, 0.55);
}
.stat { padding: 0.85rem 1rem; }
.statNum { font-weight: 950; font-size: 1.05rem; color: rgb(226 232 240); }
.statLabel { color: rgb(148 163 184); font-size: 0.85rem; margin-top: 0.25rem; }

.card {
  border: 1px solid rgba(51, 65, 85, 0.85);
  background: rgba(2, 6, 23, 0.55);
  border-radius: 1.25rem;
  padding: 1rem;
  margin-top: 1rem;
}

.feedShell {
  margin-top: 1rem;
  border: 1px solid rgba(51, 65, 85, 0.75);
  border-radius: 1.25rem;
  background: rgba(2, 6, 23, 0.55);
  padding: 1rem;
}

.tabs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}

.tab {
  padding: 0.6rem 0.8rem;
  border-radius: 999px;
  border: 1px solid rgba(51, 65, 85, 0.75);
  background: rgba(15, 23, 42, 0.35);
  color: rgb(226 232 240);
  font-weight: 800;
  display: inline-flex;
  gap: 0.4rem;
  justify-content: center;
  align-items: center;
}
.tab.active {
  border-color: rgba(99, 102, 241, 0.85);
  background: rgba(99, 102, 241, 0.2);
}

.tabCount {
  font-size: 0.75rem;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  border: 1px solid rgba(51, 65, 85, 0.65);
  color: rgb(148 163 184);
}

.padTop { margin-top: 0.75rem; }

.postList {
  margin-top: 0.75rem;
  display: grid;
}

.postItem {
  display: grid;
  grid-template-columns: 56px 1fr;
  gap: 0.85rem;
  padding: 0.9rem 0.1rem;
  border-top: 1px solid rgba(51, 65, 85, 0.55);
}
.postItem:first-child { border-top: 0; }

.postMeta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  color: rgb(148 163 184);
  font-size: 0.9rem;
}
.postName { color: rgb(226 232 240); font-weight: 950; }
.dot { opacity: 0.6; }

.replyContext {
  margin-top: 0.4rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.75rem;
  background: rgba(15, 23, 42, 0.5);
  color: rgb(148 163 184);
  font-size: 0.85rem;
}
.replyAuthor { color: rgb(226 232 240); font-weight: 700; margin: 0 0.25rem; }
.replyText { color: rgb(203 213 225); margin-left: 0.25rem; }

.postContent {
  margin-top: 0.25rem;
  color: rgb(226 232 240);
  white-space: pre-wrap;
  line-height: 1.55;
}

.attachment { margin-top: 0.6rem; }
.attachmentImg {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: 0.9rem;
  border: 1px solid rgba(51, 65, 85, 0.6);
}
.attachmentFile {
  display: inline-flex;
  padding: 0.4rem 0.6rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(51, 65, 85, 0.6);
  color: rgb(226 232 240);
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
  padding: 0.6rem 0.9rem;
  border-radius: 999px;
  border: 1px solid rgba(99, 102, 241, 0.85);
  background: rgba(99, 102, 241, 0.15);
  color: white;
  font-weight: 800;
}
.btn:hover { background: rgba(99, 102, 241, 0.25); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn.outline {
  background: rgba(15, 23, 42, 0.2);
  border-color: rgba(51, 65, 85, 0.85);
  color: rgb(226 232 240);
}
.btn.outline:hover { border-color: rgba(99, 102, 241, 0.85); }

.btn.ghost {
  border-color: rgba(51, 65, 85, 0.95);
  background: rgba(15, 23, 42, 0.2);
  color: rgb(203 213 225);
}
.btn.ghost:hover { border-color: rgba(99, 102, 241, 0.85); color: white; }

.msg {
  margin-top: 0.75rem;
  padding: 0.6rem 0.8rem;
  border-radius: 1rem;
  font-size: 0.95rem;
}
.msg.err { border: 1px solid rgba(239, 68, 68, 0.45); background: rgba(239, 68, 68, 0.1); color: rgb(254 202 202); }

.muted { color: rgb(148 163 184); }
.err { color: rgb(254 202 202); }
</style>
