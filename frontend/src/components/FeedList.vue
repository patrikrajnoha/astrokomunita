<template>
  <section class="feedShell">
    <!-- Header -->
    <div class="feedTop">
      <div>
        <div class="feedTitle">Komunitný feed</div>
        <div class="feedSub">Najnovšie príspevky od používateľov.</div>
      </div>

      <div class="feedActions">
        <!-- X-like tabs -->
        <div class="feedTabs" role="tablist">
          <button
            class="tabButton"
            role="tab"
            :aria-selected="activeTab === 'for_you'"
            :class="{ active: activeTab === 'for_you' }"
            @click="switchTab('for_you')"
          >
            Pre vás
          </button>
          <button
            class="tabButton"
            role="tab"
            :aria-selected="activeTab === 'astrobot'"
            :class="{ active: activeTab === 'astrobot' }"
            @click="switchTab('astrobot')"
          >
            AstroBot
          </button>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-if="err" class="alert alertError">
      {{ err }}
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && items.length === 0" class="skeletonWrap">
      <div class="skeletonPost" v-for="i in 3" :key="i">
        <div class="skRow">
          <div class="skAvatar"></div>
          <div class="skLines">
            <div class="skLine w60"></div>
            <div class="skLine w35"></div>
          </div>
        </div>
        <div class="skLine w90"></div>
        <div class="skLine w80"></div>
        <div class="skMedia"></div>
      </div>
    </div>

    <!-- Feed -->
    <div class="postList">
      <article
        v-for="p in items"
        :key="p.id"
        class="postCard clickable"
        @click="openPost(p)"
      >
        <div class="postLeft">
          <button class="avatar profileLink" type="button" @click.stop="openProfile(p)">
            <img
              v-if="p?.user?.avatar_url"
              class="avatarImg"
              :src="avatarSrc(p?.user?.avatar_url)"
              :alt="p?.user?.name || 'avatar'"
              loading="lazy"
            />
            <span v-else>{{ initials(p?.user?.name) }}</span>
          </button>
        </div>

        <div class="postMain">
          <!-- top row -->
          <div class="postHead">
            <div class="who">
              <div class="nameRow">
                <button class="name linkBtn" type="button" @click.stop="openProfile(p)">
                  {{ p?.user?.name ?? 'User' }}
                </button>
                <span v-if="p.source_name === 'astrobot'" class="badge badgeAstrobot">🚀 AstroBot</span>
              </div>
              <div class="meta">
                <span class="dot">•</span>
                <span class="time">{{ fmt(p?.created_at) }}</span>
                <span v-if="p?.user?.location" class="dot">•</span>
                <span v-if="p?.user?.location" class="loc">📍 {{ p.user.location }}</span>
              </div>
            </div>

            <!-- placeholder actions -->
            <button class="iconBtn" type="button" title="Možnosti" @click.stop>
              ⋯
            </button>
          </div>

          <!-- content -->
          <div class="postText" v-html="linkify(p.content)"></div>
          
          <!-- source URL for AstroBot posts -->
          <div v-if="p.source_name === 'astrobot' && p.source_url" class="sourceUrl">
            <a :href="p.source_url" target="_blank" rel="noopener noreferrer" class="sourceLink">
              📰 Zobraziť pôvodný článok
            </a>
          </div>

          <!-- attachment -->
          <div v-if="p.attachment_url" class="mediaWrap">
            <img
              v-if="isImage(p)"
              class="mediaImg"
              :src="attachmentSrc(p)"
              alt="Príloha"
              loading="lazy"
              @click.stop
            />

            <a
              v-else
              class="fileCard"
              :href="attachmentSrc(p)"
              target="_blank"
              rel="noopener"
              @click.stop
            >
              <div class="fileIcon">📎</div>
              <div class="fileInfo">
                <div class="fileTitle">Príloha</div>
                <div class="fileName">
                  {{ p.attachment_original_name || 'Súbor' }}
                </div>
              </div>
              <div class="fileArrow">→</div>
            </a>
          </div>

          <!-- bottom actions -->
          <div class="postActions" @click.stop>
            <button class="actBtn" type="button" title="Nahlásiť" @click.stop="openReport(p)">
              ⚑ Report
            </button>
            <button class="actBtn" type="button" title="Reagova?" disabled>
              .. <span>{{ p.replies_count ?? 0 }}</span>
            </button>
            <button class="actBtn" type="button" title="Zdie?a?" disabled>
              ᯓ➤ <span>0</span>
            </button>
            <button
              class="actBtn likeBtn"
              type="button"
              :class="{ liked: p.liked_by_me, bump: likeBumpId === p.id }"
              :disabled="!auth.isAuthed || isLikeLoading(p)"
              :title="auth.isAuthed ? (p.liked_by_me ? 'Zru?i? like' : 'P??i sa mi') : 'Prihl?s sa pre lajkovanie'"
              @click.stop="toggleLike(p)"
            >
              ♡ <span>{{ p.likes_count ?? 0 }}</span>
            </button>
            <div class="spacer"></div>
            <button class="actBtn" type="button" title="View thread" @click.stop="openPost(p)">
              View thread
            </button>
            <button class="actBtn" type="button" title="Uložiť" disabled>
              ⭐
            </button>
            <button
              v-if="canDelete(p)"
              class="actBtn danger"
              type="button"
              title="Delete"
              :disabled="deleteLoadingId === p.id"
              @click.stop="deletePost(p)"
            >
              {{ deleteLoadingId === p.id ? 'Mazem...' : 'Delete' }}
            </button>
          </div>
        </div>
      </article>
    </div>

    <!-- More -->
    <div class="more">
      <button
        v-if="nextPageUrl"
        class="btn btnGhost"
        :disabled="loading"
        @click="load(false)"
      >
        {{ loading ? 'Načítavam…' : 'Načítať viac' }}
      </button>
    </div>

    <div v-if="reportTarget" class="reportBox" @click.stop>
      <div class="reportTitle">Nahlásiť príspevok</div>
      <div class="reportRow">
        <label>Reason</label>
        <select v-model="reportReason">
          <option value="spam">spam</option>
          <option value="abuse">abuse</option>
          <option value="misinfo">misinfo</option>
          <option value="other">other</option>
        </select>
      </div>
      <div class="reportRow">
        <label>Message (optional)</label>
        <textarea v-model="reportMessage" rows="3" placeholder="Popis..." />
      </div>
      <div class="reportActions">
        <button class="actBtn" type="button" @click="closeReport">Cancel</button>
        <button class="actBtn" type="button" @click="submitReport">Submit</button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const items = ref([])
const nextPageUrl = ref(null)
const loading = ref(false)
const err = ref('')
const deleteLoadingId = ref(null)
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const activeTab = ref('for_you')

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function openProfile(post) {
  const username = post?.user?.username
  if (!username) return
  router.push(`/u/${username}`)
}

function canDelete(post) {
  const userId = auth.user?.id
  if (!userId) return false
  return Number(post?.user_id) === Number(userId)
}

function isLikeLoading(post) {
  return likeLoadingIds.value.has(post?.id)
}

function setLikeLoading(id, on) {
  const next = new Set(likeLoadingIds.value)
  if (on) next.add(id)
  else next.delete(id)
  likeLoadingIds.value = next
}

function openReport(post) {
  if (!post?.id) return
  reportTarget.value = post
}

function closeReport() {
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

async function submitReport() {
  const post = reportTarget.value
  if (!post?.id) return

  try {
    await auth.csrf()
    await api.post('/reports', {
      target_id: post.id,
      reason: reportReason.value,
      message: reportMessage.value || null,
    })
    err.value = 'Thanks, we will review it.'
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) err.value = 'Prihlas sa.'
    else if (status === 409) err.value = 'Už si reportoval tento post.'
    else err.value = e?.response?.data?.message || 'Report zlyhal.'
  } finally {
    closeReport()
  }
}
function bumpLike(id) {
  likeBumpId.value = id
  window.setTimeout(() => {
    if (likeBumpId.value === id) likeBumpId.value = null
  }, 220)
}

function applyLikeResponse(post, res) {
  const data = res?.data
  if (!data || !post) return
  if (data.likes_count !== undefined) post.likes_count = data.likes_count
  if (data.liked_by_me !== undefined) post.liked_by_me = data.liked_by_me
}

async function toggleLike(post) {
  if (!post?.id || isLikeLoading(post)) return
  if (!auth.isAuthed) {
    err.value = 'Prihlas sa pre lajkovanie.'
    return
  }

  err.value = ''
  const prevLiked = !!post.liked_by_me
  const prevCount = Number(post.likes_count ?? 0) || 0

  post.liked_by_me = !prevLiked
  post.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(post.id)
  setLikeLoading(post.id, true)

  try {
    await auth.csrf()
    const res = prevLiked
      ? await api.delete(`/posts/${post.id}/like`)
      : await api.post(`/posts/${post.id}/like`)
    applyLikeResponse(post, res)
  } catch (e) {
    post.liked_by_me = prevLiked
    post.likes_count = prevCount
    const status = e?.response?.status
    if (status === 401) err.value = 'Prihlas sa.'
    else err.value = e?.response?.data?.message || 'Lajk zlyhal.'
  } finally {
    setLikeLoading(post.id, false)
  }
}

async function deletePost(post) {
  if (!post?.id || deleteLoadingId.value) return
  const ok = window.confirm('Naozaj zmazat post?')
  if (!ok) return

  err.value = ''
  deleteLoadingId.value = post.id

  try {
    await auth.csrf()
    await api.delete(`/posts/${post.id}`)
    items.value = items.value.filter((x) => x.id !== post.id)
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) err.value = 'Prihlas sa.'
    else if (status === 403) err.value = 'Nemas opravnenie.'
    else err.value = e?.response?.data?.message || 'Mazanie zlyhalo.'
  } finally {
    deleteLoadingId.value = null
  }
}

function initials(name) {
  const n = name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
}

function fmt(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return String(iso)
  }
}

function escapeHtml(input) {
  return String(input)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function linkify(text) {
  if (text === null || text === undefined) return ''
  const escaped = escapeHtml(text)
  const urlRegex = /(https?:\/\/[^\s<]+)/g

  const linked = escaped.replace(urlRegex, (match) => {
    let url = match
    let trailing = ''
    while (/[)\],.;!?]$/.test(url)) {
      trailing = url.slice(-1) + trailing
      url = url.slice(0, -1)
    }
    if (!url) return match

    return `<a href="${url}" target="_blank" rel="noopener noreferrer nofollow">${url}</a>${trailing}`
  })

  return linked.replace(/\n/g, '<br>')
}

function attachmentSrc(p) {
  const u = p?.attachment_url
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')

  if (u.startsWith('/')) return origin + u
  return origin + '/' + u
}

function avatarSrc(url) {
  const u = url || ''
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')

  if (u.startsWith('/')) return origin + u
  return origin + '/' + u
}

function isImage(p) {
  const mime = p?.attachment_mime || ''
  if (typeof mime === 'string' && mime.startsWith('image/')) return true

  const name = (p?.attachment_original_name || p?.attachment_url || '').toLowerCase()
  return (
    name.endsWith('.jpg') ||
    name.endsWith('.jpeg') ||
    name.endsWith('.png') ||
    name.endsWith('.gif') ||
    name.endsWith('.webp')
  )
}

function switchTab(tab) {
  if (activeTab.value === tab || loading.value) return
  activeTab.value = tab
  load(true)
}

async function load(reset = true) {
  if (loading.value) return
  loading.value = true
  err.value = ''

  try {
    let url = reset ? '/posts?with=counts' : nextPageUrl.value
    if (!url) return

    // Add scope filter based on active tab
    if (reset) {
      if (activeTab.value === 'astrobot') {
        url += '&source=astrobot'
      }
      // for_you tab shows all posts (no filter needed)
    }

    const res = await api.get(url)
    const payload = res.data || {}
    const rows = payload.data || []

    if (reset) items.value = rows
    else items.value = [...items.value, ...rows]

    nextPageUrl.value = payload.next_page_url || null
  } catch (e) {
    err.value = e?.response?.data?.message || e?.message || 'Načítanie feedu zlyhalo.'
  } finally {
    loading.value = false
  }
}

function prepend(post) {
  if (!post?.id) return
  items.value = [post, ...items.value]
}

onMounted(() => {
  load(true)
})

defineExpose({ load, prepend })
</script>

<style scoped>
/* Shell */
.feedShell {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  border-radius: 1.6rem;
  background: rgb(var(--color-bg-rgb) / 0.55);
  padding: 1.05rem;
}

/* Header */
.feedTop {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.35rem 0.25rem 0.85rem;
}
.feedTitle {
  font-size: 1.1rem;
  font-weight: 950;
  color: var(--color-surface);
}
.feedSub {
  margin-top: 0.25rem;
  color: var(--color-text-secondary);
  font-size: 0.92rem;
}
.feedActions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
/* X-like Tabs */
.feedTabs {
  display: flex;
  align-items: flex-end;
  gap: 0.25rem;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  padding-bottom: 0;
}

.tabButton {
  position: relative;
  background: transparent;
  border: none;
  padding: 0.75rem 1rem;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: color 0.2s ease;
  border-radius: 0.5rem 0.5rem 0 0;
  min-height: 44px; /* Mobile tap target */
}

.tabButton:hover {
  color: var(--color-surface);
}

.tabButton:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
}

.tabButton.active {
  color: var(--color-surface);
}

.tabButton.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--color-primary);
  border-radius: 3px 3px 0 0;
  animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
  from {
    transform: scaleX(0);
    opacity: 0;
  }
  to {
    transform: scaleX(1);
    opacity: 1;
  }
}


/* Buttons */
.btn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.95rem;
  border: 1px solid transparent;
  font-weight: 700;
}
.btnGhost {
  border-color: var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
}
.btnGhost:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Alerts */
.alert {
  margin: 0.25rem 0.25rem 0.75rem;
  border-radius: 1rem;
  padding: 0.75rem 0.85rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.65);
  background: rgb(var(--color-bg-rgb) / 0.25);
  color: var(--color-text-secondary);
}
.alertError {
  border-color: rgb(var(--color-danger-rgb) / 0.35);
  color: var(--color-danger);
  background: rgb(var(--color-danger-rgb) / 0.08);
}

/* Skeleton */
.skeletonWrap {
  padding: 0 0.25rem 0.75rem;
  display: grid;
  gap: 0.75rem;
}
.skeletonPost {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.25);
  border-radius: 1.25rem;
  padding: 0.9rem;
  overflow: hidden;
}
.skRow {
  display: grid;
  grid-template-columns: 44px 1fr;
  gap: 0.75rem;
  align-items: center;
}
.skAvatar {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.12);
}
.skLines {
  display: grid;
  gap: 0.4rem;
}
.skLine {
  height: 12px;
  border-radius: 999px;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}
.skMedia {
  margin-top: 0.8rem;
  height: 160px;
  border-radius: 1rem;
  background: rgb(var(--color-text-secondary-rgb) / 0.10);
}
.w35 { width: 35%; }
.w60 { width: 60%; }
.w80 { width: 80%; }
.w90 { width: 90%; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Posts */
.postList {
  display: grid;
  gap: 0.75rem;
  padding: 0 0.25rem 0.4rem;
}

.postCard {
  display: grid;
  grid-template-columns: 52px 1fr;
  gap: 0.85rem;
  padding: 0.95rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  border-radius: 1.35rem;
  background: rgb(var(--color-bg-rgb) / 0.25);
  transition: transform 120ms ease, border-color 120ms ease, background 120ms ease;
}
.postCard:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.06);
  transform: translateY(-1px);
}
.postCard.clickable {
  cursor: pointer;
}
.postCard.clickable:active {
  transform: translateY(0);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.avatar {
  width: 48px;
  height: 48px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: var(--color-surface);
  font-weight: 950;
  font-size: 0.95rem;
  overflow: hidden;
}
.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.profileLink {
  border: 0;
  padding: 0;
  cursor: pointer;
}
.linkBtn {
  background: transparent;
  border: 0;
  padding: 0;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.linkBtn:hover {
  text-decoration: underline;
}

/* Head row inside post */
.postHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}
.who {
  display: grid;
  gap: 0.15rem;
}
.nameRow {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.name {
  color: var(--color-surface);
  font-weight: 950;
  font-size: 0.98rem;
}
.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  color: var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.25);
}
.badgeAstrobot {
  border-color: rgb(var(--color-success-rgb) / 0.55);
  color: var(--color-success);
  background: rgb(var(--color-success-rgb) / 0.25);
}
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
  color: var(--color-text-secondary);
  font-size: 0.88rem;
}
.dot { opacity: 0.65; }

.iconBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.7);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: var(--color-surface);
  border-radius: 0.9rem;
  padding: 0.35rem 0.6rem;
  line-height: 1;
}
.iconBtn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

/* Text */
.postText {
  margin-top: 0.5rem;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.6;
  font-size: 1rem;
}
.postText a {
  color: var(--color-primary);
  text-decoration: underline;
  text-underline-offset: 2px;
}
.postText a:hover {
  color: var(--color-primary);
}

.sourceUrl {
  margin-top: 0.75rem;
}

.sourceLink {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  background: rgb(var(--color-primary-rgb) / 0.1);
  border: 1px solid rgb(var(--color-primary-rgb) / 0.3);
  color: var(--color-primary);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
  transition: all 0.2s ease-out;
}

.sourceLink:hover {
  background: rgb(var(--color-primary-rgb) / 0.2);
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  color: var(--color-primary);
}

/* Media */
.mediaWrap {
  margin-top: 0.75rem;
}
.mediaImg {
  width: 100%;
  max-height: 520px;
  object-fit: cover;
  display: block;
  border-radius: 1.15rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
}

/* File card */
.fileCard {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 0.9rem;
  border-radius: 1.15rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.25);
  color: var(--color-surface);
}
.fileCard:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.06);
}
.fileIcon {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgb(var(--color-primary-rgb) / 0.14);
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
}
.fileInfo {
  flex: 1;
  display: grid;
  gap: 0.15rem;
}
.fileTitle {
  font-weight: 900;
}
.fileName {
  color: var(--color-text-secondary);
  font-size: 0.92rem;
}
.fileArrow {
  opacity: 0.85;
  font-weight: 900;
}

/* Actions row (visual only) */
.postActions {
  margin-top: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  color: var(--color-text-secondary);
}
.actBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.18);
  color: var(--color-text-secondary);
  border-radius: 0.9rem;
  padding: 0.45rem 0.65rem;
  font-size: 0.88rem;
}
.likeBtn {
  transition: transform 120ms ease, border-color 120ms ease, color 120ms ease, background 120ms ease;
}
.likeBtn.liked {
  border-color: rgb(var(--color-danger-rgb) / 0.7);
  color: var(--color-danger);
  background: rgb(var(--color-danger-rgb) / 0.12);
}
.likeBtn.bump {
  animation: likePop 220ms ease;
}
.actBtn:disabled {
  opacity: 0.9;
  cursor: default;
}
.actBtn.danger {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  color: var(--color-danger);
}
.actBtn.danger:hover {
  border-color: rgb(var(--color-danger-rgb) / 0.85);
}
.spacer {
  flex: 1;
}

@keyframes likePop {
  0% { transform: scale(1); }
  50% { transform: scale(1.12); }
  100% { transform: scale(1); }
}

/* More */
.more {
  display: flex;
  justify-content: center;
  padding: 0.6rem 0.25rem 0.25rem;
}

@media (max-width: 480px) {
  .feedShell {
    padding: 0.8rem;
  }
  .feedTop {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  .feedActions {
    justify-content: center;
  }
  .feedTabs {
    width: 100%;
    justify-content: center;
  }
  .tabButton {
    flex: 1;
    padding: 0.75rem 0.5rem;
    font-size: 0.9rem;
    min-height: 48px; /* Larger tap target on mobile */
  }
  .postCard {
    grid-template-columns: 44px 1fr;
    padding: 0.75rem;
  }
  .avatar {
    width: 40px;
    height: 40px;
    font-size: 0.85rem;
  }
  .postText {
    font-size: 0.95rem;
  }
  .postActions {
    flex-wrap: wrap;
    row-gap: 0.4rem;
  }
  .actBtn {
    min-height: 40px;
  }
  .fileCard {
    flex-wrap: wrap;
  }
}

.reportBox {
  margin-top: 0.75rem;
  padding: 0.85rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.35);
  display: grid;
  gap: 0.6rem;
}
.reportTitle {
  font-weight: 900;
  color: var(--color-surface);
}
.reportRow {
  display: grid;
  gap: 0.35rem;
}
.reportRow label {
  font-size: 0.8rem;
  color: var(--color-text-secondary);
}
.reportRow select,
.reportRow textarea {
  border-radius: 0.75rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.7);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: var(--color-surface);
  padding: 0.5rem 0.65rem;
}
.reportActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
