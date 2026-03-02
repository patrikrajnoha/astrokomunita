<template>
  <div class="page">
    <header class="topbar">
      <button class="iconBtn" @click="goHome">&larr;</button>
      <div class="topmeta">
        <div class="topname">{{ displayName }}</div>
        <div class="topsmall">{{ auth.user ? `${stats.posts} postov` : `@${handle}` }}</div>
      </div>
    </header>

    <div v-if="!auth.initialized" class="card muted">Nacitavam profil...</div>

    <template v-else>
      <div v-if="!auth.user" class="card info">
        <div class="infoTitle">Profil je dostupny po prihlaseni.</div>
        <div class="infoSub">Prihlas sa a uvidis svoje posty, replies, media a zalozky.</div>
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
              {{ editOpen ? 'Zatvorit edit' : 'Upravit profil' }}
            </button>
            <button class="btn ghost copyBtn" @click="copyProfileLink">{{ copyLabel }}</button>
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
            {{ activeTab === 'events' ? 'Zatiaľ nesleduješ žiadne udalosti.' : 'Zatial ziadny obsah.' }}
          </div>

          <div v-else-if="activeTab === 'events'" class="eventGrid">
            <ProfileEventCard
              v-for="eventItem in tabState.events.items"
              :key="eventItem.id"
              :event="eventItem"
              @open="openFollowedEvent"
            />
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
                  <div class="postTime">{{ activeTab === 'bookmarks' ? fmt(p.bookmarked_at || p.created_at) : fmt(p.created_at) }}</div>
                </div>

                <div v-if="p.parent && activeTab === 'replies'" class="replyContext">
                  Reply to: <span class="replyAuthor">@{{ parentHandle(p) }}</span>
                  <span class="replyText">{{ shorten(p.parent.content) }}</span>
                </div>

                <div class="postContent">{{ p.content }}</div>

                <div v-if="attachedEventForPost(p)" class="attachedEventCard">
                  <div class="attachedEventCopy">
                    <p class="attachedEventTitle">{{ attachedEventForPost(p).title || 'Udalost' }}</p>
                    <p class="attachedEventDate">
                      {{ formatEventRange(attachedEventForPost(p).start_at, attachedEventForPost(p).end_at) }}
                    </p>
                  </div>
                  <button type="button" class="btn outline" @click="openAttachedEvent(p)">
                    Otvorit udalost
                  </button>
                </div>

                <div v-if="postGifUrl(p)" class="attachment">
                  <img class="attachmentImg" :src="postGifUrl(p)" :alt="postGifTitle(p)" />
                </div>

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
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEventFollowsStore } from '@/stores/eventFollows'
import http from '@/services/api'
import api from '@/services/api'
import { useConfirm } from '@/composables/useConfirm'
import ProfileEventCard from '@/components/profile/ProfileEventCard.vue'
import { EVENT_TIMEZONE, formatEventDate, formatEventDateKey } from '@/utils/eventTime'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const eventFollows = useEventFollowsStore()
const { confirm } = useConfirm()

const tabs = [
  { key: 'posts', label: 'Prispevky', kind: 'roots' },
  { key: 'replies', label: 'Odpovede', kind: 'replies' },
  { key: 'events', label: 'Udalosti', kind: 'events' },
  { key: 'bookmarks', label: 'Zalozky', kind: 'bookmarks' },
  { key: 'media', label: 'Media', kind: 'media' },
  { key: 'likes', label: 'Paci sa', kind: 'likes' },
]

const stats = reactive({ posts: '--', replies: '--', media: '--' })
const activeTab = ref('posts')

const tabState = reactive({
  posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  replies: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  events: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  bookmarks: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  likes: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
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

function absoluteUrl(url) {
  const value = String(url || '').trim()
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')
  if (!origin) return value

  if (value.startsWith('/')) return origin + value
  return origin + '/' + value
}

function postGifUrl(post) {
  const gif = post?.meta?.gif
  if (!gif || typeof gif !== 'object') return ''

  const original = absoluteUrl(gif.original_url)
  if (original) return original

  return absoluteUrl(gif.preview_url)
}

function postGifTitle(post) {
  const title = String(post?.meta?.gif?.title || '').trim()
  return title || 'GIF'
}

function attachedEventForPost(post) {
  const event = post?.attached_event
  if (event && typeof event === 'object') return event

  const fallbackId = Number(post?.meta?.event?.event_id || 0)
  if (!Number.isInteger(fallbackId) || fallbackId <= 0) return null

  return {
    id: fallbackId,
    title: `Udalost #${fallbackId}`,
    start_at: null,
    end_at: null,
  }
}

function openAttachedEvent(post) {
  const eventId = Number(attachedEventForPost(post)?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function openFollowedEvent(event) {
  const eventId = Number(event?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function formatEventRange(startAt, endAt) {
  const startLabel = formatShortEventDate(startAt, true)
  const endLabel = formatShortEventDate(endAt, true)

  if (!startLabel && !endLabel) return 'Datum upresnime'
  if (startLabel && !endLabel) return startLabel
  if (!startLabel && endLabel) return endLabel

  const sameDay = formatEventDateKey(startAt, EVENT_TIMEZONE) === formatEventDateKey(endAt, EVENT_TIMEZONE)
  return sameDay ? startLabel : `${startLabel} - ${endLabel}`
}

function formatShortEventDate(value, includeYear = false) {
  if (!value) return ''

  const label = formatEventDate(value, EVENT_TIMEZONE, {
    day: '2-digit',
    month: 'short',
    ...(includeYear ? { year: 'numeric' } : {}),
  })

  return label === '-' ? '' : label
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

function openEditFromRoute() {
  if (!auth.user) return
  if (String(route.query?.edit || '') !== '1') return
  if (!editOpen.value) toggleEdit()
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
      bio: editForm.bio,
      location: editForm.location,
    })

    auth.user = {
      ...data,
      activity: auth.user?.activity || null,
    }
    editMsg.value = 'Profil ulozeny.'
  } catch (e) {
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 401) {
      editErr.value = 'Prihlas sa.'
    } else if (status === 422 && data?.errors) {
      editFieldErr.bio = extractFirstError(data.errors, 'bio')
      editFieldErr.location = extractFirstError(data.errors, 'location')
      const fallbackFieldError = Object.values(data.errors)
        .flat()
        .map((value) => String(value))
        .find(Boolean)
      editErr.value = editFieldErr.bio || editFieldErr.location || fallbackFieldError || 'Skontroluj polia.'
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
  const ok = await confirm({
    title: 'Zmazat post',
    message: 'Naozaj zmazat post?',
    confirmText: 'Delete',
    cancelText: 'Cancel',
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
  }
)

watch(
  () => route.query?.edit,
  () => {
    openEditFromRoute()
  }
)

watch(
  () => eventFollows.revision,
  () => {
    if (!auth.user || activeTab.value !== 'events' || !tabState.events.loaded) return
    tabState.events.loaded = false
    loadTab('events', true)
  }
)

onMounted(async () => {
  if (!auth.initialized) await auth.fetchUser()

  if (auth.user) {
    openEditFromRoute()
    loadPinned()
    await loadCounts()
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
  width: 100%;
  margin: 0 auto;
  padding: 0 0 1.2rem;
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgb(var(--bg-app-rgb) / 0.92);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--border);
  padding: 0.4rem 0.8rem;
  display: flex;
  gap: 0.65rem;
  align-items: center;
}

.iconBtn {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--bg-surface-2);
  color: var(--text-primary);
  font-weight: 700;
  transition: background-color 180ms ease, border-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}
.iconBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.35);
  background: rgb(var(--text-primary-rgb) / 0.09);
  transform: translateY(-1px);
}
.iconBtn:active { transform: translateY(1px); }
.iconBtn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.32);
}

.topmeta { display: grid; line-height: 1.1; }
.topname { font-weight: 850; color: var(--text-primary); font-size: 1.05rem; }
.topsmall { color: var(--text-secondary); font-size: 0.78rem; }

.profileShell {
  border: 0;
  border-radius: 0;
  overflow: hidden;
  margin-top: 0;
  background: transparent;
}

.cover {
  height: 158px;
  position: relative;
  background:
    radial-gradient(900px 220px at 20% 20%, rgb(var(--color-primary-rgb) / 0.25), transparent 60%),
    radial-gradient(700px 220px at 80% 30%, rgb(var(--color-primary-rgb) / 0.12), transparent 60%),
    linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.2), rgb(var(--color-bg-rgb) / 0.9));
  border-bottom: 1px solid var(--border);
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
  padding: 0 0.85rem;
  transform: translateY(-26px);
}

.avatar {
  width: 92px;
  height: 92px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgb(var(--color-bg-rgb) / 0.95);
  outline: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--text-primary);
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
  border: 1px solid var(--border);
  background: var(--bg-surface-2);
  color: var(--text-primary);
  font-weight: 700;
  padding: 0.35rem 0.6rem;
  font-size: 0.72rem;
  opacity: 0;
  transition: opacity 0.15s ease, background-color 180ms ease, border-color 180ms ease, transform 180ms ease;
  z-index: 2;
}
.mediaBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.35);
  background: rgb(var(--text-primary-rgb) / 0.09);
  transform: translateY(-1px);
}
.mediaBtn:active { transform: translateY(1px); }
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
  margin-left: auto;
}

.identity {
  padding: 0 0.85rem 0.85rem;
  margin-top: -6px;
  border-bottom: 1px solid var(--border);
}
.nameRow { display: flex; align-items: center; gap: 0.5rem; }
.name { margin: 0; font-size: 1.9rem; font-weight: 900; color: var(--text-primary); line-height: 1.05; }
.badge {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--primary-rgb) / 0.45);
  background: rgb(var(--primary-rgb) / 0.12);
  color: var(--primary);
}
.handle { color: var(--text-secondary); margin-top: 0.15rem; }
.bio { margin: 0.55rem 0 0; color: var(--text-primary); }
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  margin-top: 0.6rem;
  color: var(--text-secondary);
  font-size: 0.84rem;
}
.metaItem { white-space: nowrap; }

.card {
  border: 1px solid var(--border);
  background: var(--bg-surface);
  border-radius: 1rem;
  padding: 0.72rem;
  margin-top: 0.6rem;
}

.infoTitle { font-weight: 900; color: var(--text-primary); }
.infoSub { color: var(--text-secondary); margin-top: 0.35rem; }

.editCard { margin-top: 0.7rem; }

.pinCard { margin-top: 0.7rem; }
.pinHeader { display: flex; justify-content: space-between; align-items: center; }
.pinTitle { font-weight: 900; color: var(--text-primary); }
.pinBody { margin-top: 0.5rem; }
.pinContent { color: var(--text-primary); white-space: pre-wrap; }

.form { margin-top: 0.75rem; display: grid; gap: 0.9rem; }

.field label {
  display: block;
  font-size: 0.8rem;
  color: var(--text-primary);
  margin-bottom: 0.35rem;
}

.input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid var(--border);
  background: rgb(var(--bg-app-rgb) / 0.35);
  color: var(--text-primary);
  outline: none;
}
.input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.2);
}
.textarea { resize: vertical; }

.hint {
  margin-top: 0.35rem;
  color: var(--text-secondary);
  font-size: 0.85rem;
  text-align: right;
}

.fieldErr {
  margin-top: 0.35rem;
  font-size: 0.85rem;
  color: var(--primary-active);
}

.actions {
  display: flex;
  gap: 0.5rem;
  padding-top: 0.25rem;
  justify-content: flex-end;
}

.btn {
  min-height: 44px;
  padding: 0 1.25rem;
  border-radius: 999px;
  border: 1px solid transparent;
  background: var(--primary);
  color: var(--text-primary);
  font-weight: 600;
  font-size: 0.92rem;
  line-height: 1;
  transition: background-color 180ms ease, border-color 180ms ease, transform 180ms ease, box-shadow 180ms ease, color 180ms ease;
}
.btn:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
}
.btn:active { transform: translateY(1px); }
.btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.32);
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

.btn.outline {
  background: var(--bg-surface-2);
  border-color: var(--border);
  color: var(--text-secondary);
}
.btn.outline:hover { border-color: rgb(var(--primary-rgb) / 0.35); color: var(--text-primary); background: rgb(var(--text-primary-rgb) / 0.09); }
.btn.outline.danger { border-color: var(--primary-active); color: var(--primary-active); }
.btn.outline.danger:hover { border-color: var(--primary-active); background: rgb(var(--primary-active-rgb) / 0.12); color: var(--text-primary); }

.btn.ghost {
  border-color: var(--border);
  background: transparent;
  color: var(--text-secondary);
}
.btn.ghost:hover { border-color: rgb(var(--primary-rgb) / 0.35); background: rgb(var(--text-primary-rgb) / 0.06); color: var(--text-primary); }

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
  background: rgb(var(--bg-app-rgb) / 0.86);
  backdrop-filter: blur(8px);
  padding: 0 0 0.25rem;
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  scrollbar-width: none;
}

.tab {
  padding: 0.7rem 0.25rem 0.55rem;
  border-radius: 0;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--text-primary);
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
  border-bottom-color: var(--primary);
  color: var(--text-primary);
}

.padTop { margin-top: 0.75rem; }

.eventGrid {
  display: grid;
  gap: 0.9rem;
  margin-top: 0.85rem;
}

.postList {
  margin-top: 0;
  display: grid;
}

.postItem {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: 0.7rem;
  padding: 0.6rem 0.1rem;
  border-top: 1px solid var(--border);
}
.postItem:first-child { border-top: 0; }

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
  background: rgb(var(--bg-app-rgb) / 0.5);
  color: var(--text-secondary);
  font-size: 0.85rem;
}
.replyAuthor { color: var(--text-primary); font-weight: 700; margin: 0 0.25rem; }
.replyText { color: var(--text-primary); margin-left: 0.25rem; }

.postContent {
  margin-top: 0.25rem;
  color: var(--text-primary);
  white-space: pre-wrap;
  line-height: 1.55;
}

.attachedEventCard {
  margin-top: 0.55rem;
  border: 1px solid rgb(var(--primary-rgb) / 0.45);
  background: rgb(var(--primary-rgb) / 0.08);
  border-radius: 0.85rem;
  padding: 0.55rem 0.7rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
}

.attachedEventTitle {
  margin: 0;
  color: var(--text-primary);
  font-weight: 800;
}

.attachedEventDate {
  margin: 0.2rem 0 0;
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.attachment { margin-top: 0.6rem; }
.attachmentImg {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: 0.9rem;
  border: 1px solid var(--border);
}
.attachmentFile {
  display: inline-flex;
  padding: 0.4rem 0.6rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border);
  color: var(--text-primary);
  text-decoration: none;
}

.postActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
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
.msg.ok { border: 1px solid var(--primary); background: rgb(var(--primary-rgb) / 0.1); color: var(--primary); }
.msg.err { border: 1px solid var(--primary-active); background: rgb(var(--primary-active-rgb) / 0.1); color: var(--primary-active); }
.msg.info { border: 1px solid rgb(var(--primary-rgb) / 0.45); background: rgb(var(--primary-rgb) / 0.12); color: var(--primary); }

.muted { color: var(--text-secondary); }

@media (max-width: 767px) {
  .page {
    padding-bottom: 1.4rem;
  }

  .cover {
    height: 136px;
  }

  .avatar {
    width: 78px;
    height: 78px;
  }

  .tabs {
    top: calc(var(--app-header-h, 56px) + 2px);
  }

  .copyBtn {
    display: none;
  }
}

@media (min-width: 768px) {
  .topbar {
    padding-left: 0.65rem;
    padding-right: 0.65rem;
  }

  .eventGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .copyBtn {
    display: none;
  }
}
</style>
