<template>
  <section class="feedShell">
    <!-- Header -->
    <div class="feedTop">
      <div>
        <div class="feedTitle">Komunitný feed</div>
        <div class="feedSub">Najnovšie príspevky od používateľov.</div>
      </div>

      <button class="btn btnGhost" :disabled="loading" @click="load(true)">
        <span v-if="!loading">Refresh</span>
        <span v-else>Načítavam…</span>
      </button>
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
            <span>{{ initials(p?.user?.name) }}</span>
          </button>
        </div>

        <div class="postMain">
          <!-- top row -->
          <div class="postHead">
            <div class="who">
              <button class="name linkBtn" type="button" @click.stop="openProfile(p)">
                {{ p?.user?.name ?? 'User' }}
              </button>
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
          <div class="postText">{{ p.content }}</div>

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
            <button class="actBtn" type="button" title="Reagova?" disabled>
              ?? <span>{{ p.replies_count ?? 0 }}</span>
            </button>
            <button class="actBtn" type="button" title="Zdie?a?" disabled>
              ?? <span>0</span>
            </button>
            <button
              class="actBtn likeBtn"
              type="button"
              :class="{ liked: p.liked_by_me, bump: likeBumpId === p.id }"
              :disabled="!auth.isAuthed || isLikeLoading(p)"
              :title="auth.isAuthed ? (p.liked_by_me ? 'Zru?i? like' : 'P??i sa mi') : 'Prihl?s sa pre lajkovanie'"
              @click.stop="toggleLike(p)"
            >
              ?? <span>{{ p.likes_count ?? 0 }}</span>
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

function attachmentSrc(p) {
  const u = p?.attachment_url
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

async function load(reset = true) {
  if (loading.value) return
  loading.value = true
  err.value = ''

  try {
    const url = reset ? '/posts?with=counts' : nextPageUrl.value
    if (!url) return

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
  border: 1px solid rgba(51, 65, 85, 0.75);
  border-radius: 1.6rem;
  background: rgba(15, 23, 42, 0.55);
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
  color: rgb(226 232 240);
}
.feedSub {
  margin-top: 0.25rem;
  color: rgb(148 163 184);
  font-size: 0.92rem;
}

/* Buttons */
.btn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.95rem;
  border: 1px solid transparent;
  font-weight: 700;
}
.btnGhost {
  border-color: rgb(51 65 85);
  color: rgb(203 213 225);
  background: rgba(15, 23, 42, 0.2);
}
.btnGhost:hover {
  border-color: rgb(99, 102, 241);
  color: white;
  background: rgba(99, 102, 241, 0.08);
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
  border: 1px solid rgba(51, 65, 85, 0.65);
  background: rgba(2, 6, 23, 0.25);
  color: rgb(148 163 184);
}
.alertError {
  border-color: rgba(248, 113, 113, 0.35);
  color: rgb(254 202 202);
  background: rgba(248, 113, 113, 0.08);
}

/* Skeleton */
.skeletonWrap {
  padding: 0 0.25rem 0.75rem;
  display: grid;
  gap: 0.75rem;
}
.skeletonPost {
  border: 1px solid rgba(51, 65, 85, 0.55);
  background: rgba(2, 6, 23, 0.25);
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
  background: rgba(148, 163, 184, 0.12);
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
    rgba(148, 163, 184, 0.08),
    rgba(148, 163, 184, 0.16),
    rgba(148, 163, 184, 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}
.skMedia {
  margin-top: 0.8rem;
  height: 160px;
  border-radius: 1rem;
  background: rgba(148, 163, 184, 0.10);
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
  border: 1px solid rgba(51, 65, 85, 0.55);
  border-radius: 1.35rem;
  background: rgba(2, 6, 23, 0.25);
  transition: transform 120ms ease, border-color 120ms ease, background 120ms ease;
}
.postCard:hover {
  border-color: rgba(99, 102, 241, 0.6);
  background: rgba(99, 102, 241, 0.06);
  transform: translateY(-1px);
}
.postCard.clickable {
  cursor: pointer;
}
.postCard.clickable:active {
  transform: translateY(0);
  background: rgba(99, 102, 241, 0.12);
}

.avatar {
  width: 48px;
  height: 48px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 1px solid rgba(99, 102, 241, 0.6);
  background: rgba(99, 102, 241, 0.12);
  color: white;
  font-weight: 950;
  font-size: 0.95rem;
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
.name {
  color: rgb(226 232 240);
  font-weight: 950;
  font-size: 0.98rem;
}
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
  color: rgb(148 163 184);
  font-size: 0.88rem;
}
.dot { opacity: 0.65; }

.iconBtn {
  border: 1px solid rgba(51, 65, 85, 0.7);
  background: rgba(15, 23, 42, 0.2);
  color: rgb(203 213 225);
  border-radius: 0.9rem;
  padding: 0.35rem 0.6rem;
  line-height: 1;
}
.iconBtn:hover {
  border-color: rgba(99, 102, 241, 0.7);
  color: white;
  background: rgba(99, 102, 241, 0.08);
}

/* Text */
.postText {
  margin-top: 0.5rem;
  color: rgb(226 232 240);
  white-space: pre-wrap;
  line-height: 1.6;
  font-size: 1rem;
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
  border: 1px solid rgba(51, 65, 85, 0.55);
}

/* File card */
.fileCard {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 0.9rem;
  border-radius: 1.15rem;
  border: 1px solid rgba(51, 65, 85, 0.55);
  background: rgba(2, 6, 23, 0.25);
  color: rgb(226 232 240);
}
.fileCard:hover {
  border-color: rgba(99, 102, 241, 0.6);
  background: rgba(99, 102, 241, 0.06);
}
.fileIcon {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(99, 102, 241, 0.14);
  border: 1px solid rgba(99, 102, 241, 0.35);
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
  color: rgb(148 163 184);
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
  color: rgb(148 163 184);
}
.actBtn {
  border: 1px solid rgba(51, 65, 85, 0.55);
  background: rgba(15, 23, 42, 0.18);
  color: rgb(148 163 184);
  border-radius: 0.9rem;
  padding: 0.45rem 0.65rem;
  font-size: 0.88rem;
}
.likeBtn {
  transition: transform 120ms ease, border-color 120ms ease, color 120ms ease, background 120ms ease;
}
.likeBtn.liked {
  border-color: rgba(248, 113, 113, 0.7);
  color: rgb(254 202 202);
  background: rgba(248, 113, 113, 0.12);
}
.likeBtn.bump {
  animation: likePop 220ms ease;
}
.actBtn:disabled {
  opacity: 0.9;
  cursor: default;
}
.actBtn.danger {
  border-color: rgba(239, 68, 68, 0.55);
  color: rgb(254 202 202);
}
.actBtn.danger:hover {
  border-color: rgba(239, 68, 68, 0.85);
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
</style>
