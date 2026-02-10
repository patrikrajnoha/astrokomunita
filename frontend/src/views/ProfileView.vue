<template>
  <div class="page">
    <header class="topbar">
      <button class="iconBtn" @click="goHome">&larr;</button>
      <div class="topmeta">
        <div class="topname">{{ displayName }}</div>
        <div class="topsmall">@{{ handle }}</div>
      </div>
    </header>

    <div v-if="!auth.initialized" class="card muted">Nacitavam profil...</div>

    <template v-else>
      <div v-if="!auth.user" class="card info">
        <div class="infoTitle">Profil je dostupny po prihlaseni.</div>
        <div class="infoSub">Prihlas sa a uvidis svoje posty, replies a media.</div>
        <button class="btn" @click="goLogin">Prihlasit sa</button>
      </div>

      <section class="profileShell">
        <div class="cover" :class="{ uploading: coverUploading }">
          <img v-if="coverSrc" class="coverImg" :src="coverSrc" alt="cover" />
          <div class="coverGlow"></div>
          <button
            v-if="auth.user"
            class="mediaBtn coverBtn"
            type="button"
            :disabled="coverUploading"
            @click="openPicker('cover')"
          >
            {{ coverUploading ? 'Nahravam...' : 'Change cover' }}
          </button>
          <input
            ref="coverInput"
            class="fileInput"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            @change="onMediaChange('cover', $event)"
          />
        </div>

        <div class="profileHead">
          <div class="avatar avatarEditable" :class="{ uploading: avatarUploading }">
            <img v-if="avatarSrc" class="avatarImg" :src="avatarSrc" alt="avatar" />
            <span v-else>{{ initials }}</span>
            <button
              v-if="auth.user"
              class="mediaBtn avatarBtn"
              type="button"
              :disabled="avatarUploading"
              @click="openPicker('avatar')"
            >
              {{ avatarUploading ? 'Nahravam...' : 'Change' }}
            </button>
            <input
              ref="avatarInput"
              class="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              @change="onMediaChange('avatar', $event)"
            />
          </div>

          <div class="headActions">
            <button
              v-if="auth.user"
              class="btn outline"
              @click="toggleEdit"
            >
              {{ editOpen ? 'Zatvorit edit' : 'Edit profile' }}
            </button>
            <button class="btn ghost" @click="copyProfileLink">{{ copyLabel }}</button>
          </div>
        </div>

        <div v-if="mediaErr" class="msg err">{{ mediaErr }}</div>

        <div class="identity">
          <div class="nameRow">
            <h1 class="name">{{ displayName }}</h1>
            <span v-if="auth.user?.is_admin" class="badge">Admin</span>
          </div>

          <div class="handle">@{{ handle }}</div>

          <p v-if="auth.user?.bio" class="bio">{{ auth.user.bio }}</p>
          <p v-else class="bio muted">Zatial bez popisu.</p>

          <div class="meta">
            <span v-if="auth.user?.location" class="metaItem">Location: {{ auth.user.location }}</span>
            <span v-if="auth.user?.email" class="metaItem">Email: {{ auth.user.email }}</span>
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

      <section v-if="editOpen" class="card editCard">
        <div v-if="editMsg" class="msg ok">{{ editMsg }}</div>
        <div v-if="editErr" class="msg err">{{ editErr }}</div>

        <div class="form">
          <div class="field">
            <label>Bio</label>
            <textarea
              class="input textarea"
              v-model="editForm.bio"
              rows="3"
              maxlength="160"
            ></textarea>
            <div class="hint">{{ (editForm.bio || '').length }}/160</div>
            <p v-if="editFieldErr.bio" class="fieldErr">{{ editFieldErr.bio }}</p>
          </div>

          <div class="field">
            <label>Location</label>
            <input class="input" v-model="editForm.location" type="text" maxlength="60" />
            <p v-if="editFieldErr.location" class="fieldErr">{{ editFieldErr.location }}</p>
          </div>

          <div class="actions">
            <button class="btn" @click="saveEdit" :disabled="editSaving">
              {{ editSaving ? 'Ukladam...' : 'Ulozit' }}
            </button>
            <button class="btn ghost" @click="toggleEdit" :disabled="editSaving">Zrusit</button>
          </div>
        </div>
      </section>

      <section v-if="pinnedPost" class="card pinCard">
        <div class="pinHeader">
          <div class="pinTitle">Pinned</div>
          <button class="btn ghost" @click="clearPinned">Unpin</button>
        </div>
        <div class="pinBody">
          <div class="pinContent">{{ pinnedPost.content }}</div>
          <div v-if="pinnedPost.attachment_url" class="attachment">
            <img
              v-if="isImage(pinnedPost)"
              class="attachmentImg"
              :src="pinnedPost.attachment_url"
              alt="attachment"
            />
            <a v-else class="attachmentFile" :href="pinnedPost.attachment_url" target="_blank" rel="noreferrer">
              {{ pinnedPost.attachment_original_name || 'Attachment' }}
            </a>
          </div>
        </div>
      </section>

      <section v-if="auth.user" class="card favCard">
        <div class="favHeader">
          <div class="favTitle">Obľúbené udalosti</div>
        </div>

        <div v-if="favoritesLoading" class="muted">Načítavam obľúbené…</div>
        <div v-else-if="favoritesError" class="msg err">{{ favoritesError }}</div>
        <div v-else-if="favoriteEvents.length === 0" class="muted">
          Zatiaľ nemáš žiadne obľúbené udalosti.
        </div>

        <div v-else class="favGrid">
          <article v-for="e in favoriteEvents" :key="e.id" class="favItem">
            <div class="favMeta">
              <div class="favTitleRow">
                <div class="favName">{{ e.title }}</div>
                <button class="btn ghost" @click="removeFavorite(e.id)">Odobrať</button>
              </div>
              <div class="favTime">Max: {{ formatEventDate(e.max_at) }}</div>
              <div class="favDesc">{{ e.short || '—' }}</div>
            </div>
            <div class="favActions">
              <button class="btn outline" @click="openEvent(e.id)">Detail</button>
            </div>
          </article>
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

        <div v-if="!auth.user" class="msg info">Prihlas sa.</div>

        <template v-else>
          <div v-if="actionMsg" class="msg ok">{{ actionMsg }}</div>
          <div v-if="actionErr" class="msg err">{{ actionErr }}</div>

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
                  <button class="btn ghost" @click="togglePin(p)">
                    {{ pinnedPost?.id === p.id ? 'Unpin' : 'Pin' }}
                  </button>
                  <button class="btn outline danger" :disabled="deleteLoadingId === p.id" @click="deletePost(p)">
                    {{ deleteLoadingId === p.id ? 'Mazem...' : 'Delete' }}
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
        </template>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { http } from '@/lib/http'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()

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

const editOpen = ref(false)
const editSaving = ref(false)
const editMsg = ref('')
const editErr = ref('')
const editForm = reactive({ bio: '', location: '' })
const editFieldErr = reactive({ bio: '', location: '' })

const copyLabel = ref('Kopirovat link')
const actionMsg = ref('')
const actionErr = ref('')
const deleteLoadingId = ref(null)
const mediaErr = ref('')
const avatarUploading = ref(false)
const coverUploading = ref(false)
const avatarPreview = ref('')
const coverPreview = ref('')
const avatarInput = ref(null)
const coverInput = ref(null)

const pinnedPost = ref(null)
const favoriteEvents = ref([])
const favoritesLoading = ref(false)
const favoritesError = ref('')

const displayName = computed(() => auth.user?.name || 'Profil')

const initials = computed(() => {
  const n = auth.user?.name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
})

const handle = computed(() => {
  const email = auth.user?.email || ''
  const base = email.split('@')[0] || auth.user?.name || 'user'
  return String(base).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
})

const avatarSrc = computed(() => avatarPreview.value || auth.user?.avatar_url || '')
const coverSrc = computed(() => coverPreview.value || auth.user?.cover_url || '')

const pinKey = computed(() => {
  const username = auth.user?.username || 'me'
  return `pinned_post_${username}`
})

function goHome() {
  router.push({ name: 'home' })
}

function goLogin() {
  router.push({ name: 'login', query: { redirect: '/profile' } })
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function openEvent(eventId) {
  if (!eventId) return
  router.push(`/events/${eventId}`)
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

function formatEventDate(value) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return String(value)
  }
}

function parentHandle(post) {
  const parentUser = post?.parent?.user
  const base = parentUser?.email?.split('@')[0] || parentUser?.name || 'user'
  return String(base).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
}

function toggleEdit() {
  if (!auth.user) return
  editOpen.value = !editOpen.value
  if (editOpen.value) {
    editForm.bio = auth.user.bio || ''
    editForm.location = auth.user.location || ''
    editMsg.value = ''
    editErr.value = ''
    editFieldErr.bio = ''
    editFieldErr.location = ''
  }
}

function clearEditErrors() {
  editMsg.value = ''
  editErr.value = ''
  editFieldErr.bio = ''
  editFieldErr.location = ''
}

function extractFirstError(errorsObj, field) {
  const v = errorsObj?.[field]
  return Array.isArray(v) && v.length ? String(v[0]) : ''
}

function openPicker(type) {
  const input = type === 'avatar' ? avatarInput.value : coverInput.value
  if (input && !avatarUploading.value && !coverUploading.value) {
    input.click()
  }
}

function setPreview(type, file) {
  const url = URL.createObjectURL(file)
  if (type === 'avatar') {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
    avatarPreview.value = url
  } else {
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
    coverPreview.value = url
  }
}

async function uploadMedia(type, file) {
  if (!auth.user) {
    mediaErr.value = 'Prihlas sa.'
    return
  }

  mediaErr.value = ''
  if (type === 'avatar') avatarUploading.value = true
  else coverUploading.value = true

  try {
    await auth.csrf()

    const form = new FormData()
    form.append('type', type)
    form.append('file', file)

    await http.post('/profile/media', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (type === 'avatar' && avatarPreview.value) {
      URL.revokeObjectURL(avatarPreview.value)
      avatarPreview.value = ''
    }
    if (type === 'cover' && coverPreview.value) {
      URL.revokeObjectURL(coverPreview.value)
      coverPreview.value = ''
    }

    await auth.fetchUser()
  } catch (e) {
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 401) {
      mediaErr.value = 'Prihlas sa.'
    } else if (status === 422 && data?.errors) {
      mediaErr.value =
        extractFirstError(data.errors, 'file') ||
        extractFirstError(data.errors, 'type') ||
        'Skontroluj subor.'
    } else {
      mediaErr.value = data?.message || 'Upload zlyhal.'
    }
  } finally {
    if (type === 'avatar') avatarUploading.value = false
    else coverUploading.value = false
  }
}

function onMediaChange(type, event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  setPreview(type, file)
  uploadMedia(type, file)
  event.target.value = ''
}

async function saveEdit() {
  if (!auth.user) return
  clearEditErrors()
  editSaving.value = true

  try {
    await auth.csrf()

    const { data } = await http.patch('/profile', {
      name: auth.user.name,
      email: auth.user.email,
      bio: editForm.bio,
      location: editForm.location,
    })

    auth.user = data
    editMsg.value = 'Profil ulozeny.'
  } catch (e) {
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 401) {
      editErr.value = 'Prihlas sa.'
    } else if (status === 422 && data?.errors) {
      editFieldErr.bio = extractFirstError(data.errors, 'bio')
      editFieldErr.location = extractFirstError(data.errors, 'location')
      editErr.value = editFieldErr.bio || editFieldErr.location || 'Skontroluj polia.'
    } else {
      editErr.value = data?.message || 'Ulozenie zlyhalo.'
    }
  } finally {
    editSaving.value = false
  }
}

async function copyProfileLink() {
  const url = `${window.location.origin}/profile`
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

function loadPinned() {
  try {
    const raw = localStorage.getItem(pinKey.value)
    pinnedPost.value = raw ? JSON.parse(raw) : null
  } catch {
    pinnedPost.value = null
  }
}

function savePinned(post) {
  pinnedPost.value = post
  localStorage.setItem(pinKey.value, JSON.stringify(post))
}

function clearPinned() {
  pinnedPost.value = null
  localStorage.removeItem(pinKey.value)
}

function togglePin(post) {
  if (!post?.id) return
  if (pinnedPost.value?.id === post.id) {
    clearPinned()
  } else {
    savePinned(post)
  }
}

async function deletePost(post) {
  if (!post?.id || deleteLoadingId.value) return
  const ok = window.confirm('Naozaj zmazat post?')
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
      clearPinned()
    }

    actionMsg.value = 'Post zmazany.'
    await loadCounts()
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) actionErr.value = 'Prihlas sa.'
    else if (status === 403) actionErr.value = 'Nemas opravnenie.'
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
      tabState[k.key].total = String(total)
    } catch (e) {
      if (e?.response?.status === 401) {
        stats[k.key] = '--'
        tabState[k.key].total = '--'
      } else {
        stats[k.key] = '--'
        tabState[k.key].total = '--'
      }
    }
  }
}

async function loadFavorites() {
  if (!auth.user) {
    favoriteEvents.value = []
    return
  }

  favoritesLoading.value = true
  favoritesError.value = ''

  try {
    const res = await api.get('/favorites')
    const items = Array.isArray(res.data) ? res.data : []
    favoriteEvents.value = items
      .map((f) => f.event)
      .filter((e) => e && e.id)
  } catch (e) {
    favoritesError.value =
      e?.response?.data?.message || 'Nepodarilo sa načítať obľúbené udalosti.'
  } finally {
    favoritesLoading.value = false
  }
}

async function removeFavorite(eventId) {
  await auth.csrf()
  await api.delete(`/favorites/${eventId}`)
  favoriteEvents.value = favoriteEvents.value.filter((e) => e.id !== eventId)
}

async function loadTab(key, reset = true) {
  const tab = tabs.find((t) => t.key === key)
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
    const url = reset ? '/posts' : state.next
    if (!url) return

    const { data } = await http.get(url, {
      params: reset ? { scope: 'me', kind: tab.kind, per_page: 10 } : undefined,
    })

    const rows = data?.data ?? []
    if (reset) state.items = rows
    else state.items = [...state.items, ...rows]

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
  }
)

onMounted(async () => {
  if (!auth.initialized) await auth.fetchUser()

  if (auth.user) {
    loadPinned()
    await loadCounts()
    await loadFavorites()
    await loadTab(activeTab.value, true)
  }
})

onBeforeUnmount(() => {
  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
  if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
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
  background: rgb(var(--color-bg-rgb) / 0.72);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
  padding: 0.75rem 0.5rem;
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.iconBtn {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.8);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
}
.iconBtn:hover { border-color: rgb(var(--color-primary-rgb) / 0.85); }

.topmeta { display: grid; line-height: 1.1; }
.topname { font-weight: 900; color: var(--color-surface); }
.topsmall { color: var(--color-text-secondary); font-size: 0.85rem; }

.profileShell {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  border-radius: 1.25rem;
  overflow: hidden;
  margin-top: 1rem;
  background: rgb(var(--color-bg-rgb) / 0.55);
}

.cover {
  height: 220px;
  position: relative;
  background:
    radial-gradient(900px 220px at 20% 20%, rgb(var(--color-primary-rgb) / 0.25), transparent 60%),
    radial-gradient(700px 220px at 80% 30%, rgb(var(--color-primary-rgb) / 0.12), transparent 60%),
    linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.2), rgb(var(--color-bg-rgb) / 0.9));
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
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
    radial-gradient(2px 2px at 20% 30%, rgb(var(--color-surface-rgb) / 0.35), transparent 60%),
    radial-gradient(2px 2px at 70% 40%, rgb(var(--color-surface-rgb) / 0.25), transparent 60%),
    radial-gradient(2px 2px at 50% 70%, rgb(var(--color-surface-rgb) / 0.2), transparent 60%);
  opacity: 0.6;
}

.profileHead {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding: 0 1rem;
  transform: translateY(-40px);
}

.avatar {
  width: 112px;
  height: 112px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgb(var(--color-bg-rgb) / 0.95);
  outline: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
  font-weight: 900;
  font-size: 1.25rem;
}
.avatarEditable {
  position: relative;
  overflow: hidden;
}
.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 999px;
}
.avatar.sm {
  width: 44px;
  height: 44px;
  font-size: 0.95rem;
  border-width: 1px;
  outline: 1px solid rgb(var(--color-primary-rgb) / 0.35);
}

.fileInput {
  display: none;
}

.mediaBtn {
  position: absolute;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: var(--color-surface);
  font-weight: 700;
  padding: 0.35rem 0.6rem;
  font-size: 0.72rem;
  opacity: 0;
  transition: opacity 0.15s ease;
  z-index: 2;
}
.mediaBtn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.coverBtn {
  right: 12px;
  bottom: 12px;
}
.avatarBtn {
  right: 6px;
  bottom: 6px;
  padding: 0.25rem 0.45rem;
  font-size: 0.65rem;
}
.cover:hover .mediaBtn,
.avatarEditable:hover .mediaBtn {
  opacity: 1;
}
@media (hover: none) {
  .mediaBtn {
    opacity: 1;
  }
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
.name { margin: 0; font-size: 1.35rem; font-weight: 950; color: var(--color-surface); }
.badge {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-success-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: var(--color-success);
}
.handle { color: var(--color-text-secondary); margin-top: 0.15rem; }
.bio { margin: 0.75rem 0 0; color: var(--color-surface); }
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  margin-top: 0.75rem;
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}
.metaItem { white-space: nowrap; }

.statsRow {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
}
.stat { padding: 0.85rem 1rem; }
.statNum { font-weight: 950; font-size: 1.05rem; color: var(--color-surface); }
.statLabel { color: var(--color-text-secondary); font-size: 0.85rem; margin-top: 0.25rem; }

.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.85);
  background: rgb(var(--color-bg-rgb) / 0.55);
  border-radius: 1.25rem;
  padding: 1rem;
  margin-top: 1rem;
}

.infoTitle { font-weight: 900; color: var(--color-surface); }
.infoSub { color: var(--color-text-secondary); margin-top: 0.35rem; }

.editCard { margin-top: 1rem; }

.pinCard { margin-top: 1rem; }
.pinHeader { display: flex; justify-content: space-between; align-items: center; }
.pinTitle { font-weight: 900; color: var(--color-surface); }
.pinBody { margin-top: 0.5rem; }
.pinContent { color: var(--color-surface); white-space: pre-wrap; }

.favCard { margin-top: 1rem; }
.favHeader { display: flex; justify-content: space-between; align-items: center; }
.favTitle { font-weight: 900; color: var(--color-surface); }
.favGrid { margin-top: 0.75rem; display: grid; gap: 0.75rem; }
.favItem {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
  border-radius: 1rem;
  padding: 0.85rem;
  background: rgb(var(--color-bg-rgb) / 0.35);
  display: grid;
  gap: 0.5rem;
}
.favTitleRow { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.favName { color: var(--color-surface); font-weight: 900; }
.favTime { color: var(--color-text-secondary); font-size: 0.85rem; margin-top: 0.25rem; }
.favDesc { color: var(--color-surface); margin-top: 0.35rem; }
.favActions { display: flex; justify-content: flex-end; }

.form { margin-top: 0.75rem; display: grid; gap: 0.9rem; }

.field label {
  display: block;
  font-size: 0.8rem;
  color: var(--color-surface);
  margin-bottom: 0.35rem;
}

.input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  outline: none;
}
.input:focus { border-color: rgb(var(--color-primary-rgb) / 0.9); }
.textarea { resize: vertical; }

.hint {
  margin-top: 0.35rem;
  color: var(--color-text-secondary);
  font-size: 0.85rem;
  text-align: right;
}

.fieldErr {
  margin-top: 0.35rem;
  font-size: 0.85rem;
  color: var(--color-danger);
}

.actions {
  display: flex;
  gap: 0.5rem;
  padding-top: 0.25rem;
  justify-content: flex-end;
}

.btn {
  padding: 0.6rem 0.9rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.85);
  background: rgb(var(--color-primary-rgb) / 0.15);
  color: var(--color-surface);
  font-weight: 800;
}
.btn:hover { background: rgb(var(--color-primary-rgb) / 0.25); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn.outline {
  background: rgb(var(--color-bg-rgb) / 0.2);
  border-color: rgb(var(--color-text-secondary-rgb) / 0.85);
  color: var(--color-surface);
}
.btn.outline:hover { border-color: rgb(var(--color-primary-rgb) / 0.85); }
.btn.outline.danger { border-color: rgb(var(--color-danger-rgb) / 0.55); color: var(--color-danger); }
.btn.outline.danger:hover { border-color: rgb(var(--color-danger-rgb) / 0.85); }

.btn.ghost {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.95);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: var(--color-surface);
}
.btn.ghost:hover { border-color: rgb(var(--color-primary-rgb) / 0.85); color: var(--color-surface); }

.feedShell {
  margin-top: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  border-radius: 1.25rem;
  background: rgb(var(--color-bg-rgb) / 0.55);
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
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  font-weight: 800;
  display: inline-flex;
  gap: 0.4rem;
  justify-content: center;
  align-items: center;
}
.tab.active {
  border-color: rgb(var(--color-primary-rgb) / 0.85);
  background: rgb(var(--color-primary-rgb) / 0.2);
}

.tabCount {
  font-size: 0.75rem;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.65);
  color: var(--color-text-secondary);
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
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
}
.postItem:first-child { border-top: 0; }

.postMeta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}
.postName { color: var(--color-surface); font-weight: 950; }
.dot { opacity: 0.6; }

.replyContext {
  margin-top: 0.4rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.75rem;
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: var(--color-text-secondary);
  font-size: 0.85rem;
}
.replyAuthor { color: var(--color-surface); font-weight: 700; margin: 0 0.25rem; }
.replyText { color: var(--color-surface); margin-left: 0.25rem; }

.postContent {
  margin-top: 0.25rem;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.55;
}

.attachment { margin-top: 0.6rem; }
.attachmentImg {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
}
.attachmentFile {
  display: inline-flex;
  padding: 0.4rem 0.6rem;
  border-radius: 0.75rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
  color: var(--color-surface);
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

.msg {
  margin-top: 0.75rem;
  padding: 0.6rem 0.8rem;
  border-radius: 1rem;
  font-size: 0.95rem;
}
.msg.ok { border: 1px solid rgb(var(--color-success-rgb) / 0.45); background: rgb(var(--color-success-rgb) / 0.1); color: var(--color-success); }
.msg.err { border: 1px solid rgb(var(--color-danger-rgb) / 0.45); background: rgb(var(--color-danger-rgb) / 0.1); color: var(--color-danger); }
.msg.info { border: 1px solid rgb(var(--color-primary-rgb) / 0.45); background: rgb(var(--color-primary-rgb) / 0.12); color: var(--color-primary); }

.muted { color: var(--color-text-secondary); }
</style>
