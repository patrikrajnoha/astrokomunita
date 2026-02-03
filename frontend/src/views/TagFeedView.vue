<template>
  <div class="wrap">
    <div class="card">
      <!-- Header -->
      <div class="header">
        <router-link class="back" to="/">&larr; Späť</router-link>
        <div class="title">#{{ tag }}</div>
        <div class="sub">Príspevky s hashtagom #{{ tag }}</div>
      </div>

      <!-- Loading -->
      <div v-if="loading && items.length === 0" class="loading">
        Načítavam príspevky...
      </div>

      <!-- Error -->
      <div v-else-if="error" class="error">
        {{ error }}
      </div>

      <!-- Empty -->
      <div v-else-if="!loading && items.length === 0" class="empty">
        <div class="emptyTitle">Žiadne príspevky</div>
        <div class="emptySub">
          Nikto ešte nepoužil hashtag #{{ tag }}
        </div>
      </div>

      <!-- Feed -->
      <div v-else class="feed">
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
            </div>

            <!-- content -->
            <div class="postText">
              <HashtagText :content="p.content" />
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
              <button class="actBtn" type="button" title="Reagovať" disabled>
                .. <span>{{ p.replies_count ?? 0 }}</span>
              </button>
              <button class="actBtn" type="button" title="Zdieľať" disabled>
                ᯓ➤ <span>0</span>
              </button>
              <button
                class="actBtn likeBtn"
                type="button"
                :class="{ liked: p.liked_by_me, bump: likeBumpId === p.id }"
                :disabled="!auth.isAuthed || isLikeLoading(p)"
                :title="auth.isAuthed ? (p.liked_by_me ? 'Zrušiť like' : 'Páči sa mi') : 'Prihlás sa pre lajkovanie'"
                @click.stop="toggleLike(p)"
              >
                ♡ <span>{{ p.likes_count ?? 0 }}</span>
              </button>
              <div class="spacer"></div>
              <button class="actBtn" type="button" title="View thread" @click.stop="openPost(p)">
                View thread
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
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import HashtagText from '@/components/HashtagText.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const tag = ref(route.params.tag)
const items = ref([])
const nextPageUrl = ref(null)
const loading = ref(false)
const error = ref('')
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
  // TODO: Implement report functionality
  console.log('Report post:', post)
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
    error.value = 'Prihlás sa pre lajkovanie.'
    return
  }

  error.value = ''
  const prevLiked = !!post.liked_by_me
  const prevCount = Number(post.likes_count ?? 0) || 0

  post.liked_by_me = !prevLiked
  post.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(post.id)
  setLikeLoading(post.id, true)

  try {
    const res = prevLiked
      ? await api.delete(`/posts/${post.id}/like`)
      : await api.post(`/posts/${post.id}/like`)
    applyLikeResponse(post, res)
  } catch (e) {
    post.liked_by_me = prevLiked
    post.likes_count = prevCount
    const status = e?.response?.status
    if (status === 401) error.value = 'Prihlás sa.'
    else error.value = e?.response?.data?.message || 'Lajk zlyhal.'
  } finally {
    setLikeLoading(post.id, false)
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

async function load(reset = true) {
  if (loading.value) return
  loading.value = true
  error.value = ''

  try {
    let url = reset ? `/posts?tag=${tag.value}&with=counts` : nextPageUrl.value
    if (!url) return

    const res = await api.get(url)
    const payload = res.data || {}
    const rows = payload.data || []

    if (reset) items.value = rows
    else items.value = [...items.value, ...rows]

    nextPageUrl.value = payload.next_page_url || null
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Načítanie feedu zlyhalo.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load(true)
})
</script>

<style scoped>
.wrap {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
}

.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  border-radius: 1.6rem;
  background: rgb(var(--color-bg-rgb) / 0.55);
  padding: 1.05rem;
}

.header {
  padding: 0.35rem 0.25rem 0.85rem;
}

.back {
  display: inline-flex;
  align-items: center;
  color: var(--color-text-secondary);
  text-decoration: none;
  margin-bottom: 0.5rem;
}

.back:hover {
  color: var(--color-surface);
}

.title {
  font-size: 1.5rem;
  font-weight: 950;
  color: var(--color-surface);
  margin-bottom: 0.25rem;
}

.sub {
  color: var(--color-text-secondary);
  font-size: 0.92rem;
}

.loading, .error, .empty {
  text-align: center;
  padding: 2rem;
  color: var(--color-text-secondary);
}

.emptyTitle {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-surface);
  margin-bottom: 0.5rem;
}

.emptySub {
  color: var(--color-text-secondary);
}

.feed {
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

.postText {
  margin-top: 0.5rem;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.6;
  font-size: 1rem;
}

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

.spacer {
  flex: 1;
}

@keyframes likePop {
  0% { transform: scale(1); }
  50% { transform: scale(1.12); }
  100% { transform: scale(1); }
}

.more {
  display: flex;
  justify-content: center;
  padding: 0.6rem 0.25rem 0.25rem;
}

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

@media (max-width: 480px) {
  .wrap {
    padding: 0.5rem;
  }
  
  .card {
    padding: 0.8rem;
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
}
</style>
