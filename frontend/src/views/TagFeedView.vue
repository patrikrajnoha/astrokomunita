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
              <UserAvatar class="avatarImg" :user="p?.user" :alt="p?.user?.name || 'avatar'" />
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

            <PollCard
              v-if="p.poll"
              :poll="p.poll"
              :post-id="p.id"
              :is-authed="auth.isAuthed"
              @updated="(nextPoll) => updatePostPoll(p, nextPoll)"
              @login-required="onPollLoginRequired"
            />

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
              <button class="actBtn" type="button" title="Nahlasit vo vlakne" @click.stop="openReport(p)">
                ⚑ Nahlasit
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
              <button class="actBtn" type="button" title="Zobrazit vlakno" @click.stop="openPost(p)">
                Zobrazit vlakno
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
      <div v-if="reportTarget" class="reportModal" @click="closeReport()">
        <div class="reportCard" role="dialog" aria-modal="true" @click.stop>
          <h3 class="reportTitle">Nahlasit prispevok</h3>
          <p class="reportHint">Odosle sa moderacii na preverenie.</p>

          <label class="reportLabel" for="tag-feed-report-reason">Dovod</label>
          <select
            id="tag-feed-report-reason"
            v-model="reportReason"
            class="reportSelect"
            :disabled="reportLoading"
          >
            <option value="spam">Spam</option>
            <option value="abuse">Zneuzitie</option>
            <option value="misinfo">Dezinformacie</option>
            <option value="other">Ine</option>
          </select>

          <label class="reportLabel" for="tag-feed-report-message">Poznamka (volitelne)</label>
          <textarea
            id="tag-feed-report-message"
            v-model="reportMessage"
            class="reportTextarea"
            rows="4"
            maxlength="500"
            :disabled="reportLoading"
            placeholder="Doplnujuce info pre moderatorov..."
          />

          <div class="reportActions">
            <button class="btn btnGhost" type="button" :disabled="reportLoading" @click="closeReport()">
              Zrusit
            </button>
            <button class="btn btnDanger" type="button" :disabled="reportLoading" @click="submitReport">
              {{ reportLoading ? 'Odosielam...' : 'Odoslat report' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserAvatar from '@/components/UserAvatar.vue'
import HashtagText from '@/components/HashtagText.vue'
import PollCard from '@/components/PollCard.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const tag = computed(() => {
  const raw = route.params.tag
  const value = Array.isArray(raw) ? raw[0] : raw
  return String(value || '').trim().toLowerCase()
})

const items = ref([])
const nextPageUrl = ref(null)
const loading = ref(false)
const error = ref('')
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const requestSequence = ref(0)
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const reportLoading = ref(false)

function resetFeedState() {
  items.value = []
  nextPageUrl.value = null
  error.value = ''
  loading.value = false
}

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

function closeReport(force = false) {
  if (reportLoading.value && !force) return
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

function openReport(post) {
  if (!post?.id) return
  if (!auth.isAuthed) {
    const message = 'Prihlas sa pre nahlasenie prispevku.'
    error.value = message
    toast.warn(message)
    return
  }

  error.value = ''
  reportTarget.value = post
  reportReason.value = 'spam'
  reportMessage.value = ''
}

async function submitReport() {
  const post = reportTarget.value
  if (!post?.id || reportLoading.value) return

  reportLoading.value = true
  error.value = ''

  try {
    await api.post('/reports', {
      target_id: post.id,
      reason: reportReason.value,
      message: reportMessage.value.trim() || null,
      _hp: '',
    })

    toast.success('Nahlasenie bolo odoslane. Dakujeme.')
    closeReport(true)
  } catch (e) {
    const status = e?.response?.status
    const message =
      status === 401
        ? 'Prihlas sa.'
        : status === 403
          ? 'Svoj vlastny prispevok nemozes nahlasit.'
          : status === 409
            ? 'Tento prispevok ste uz nahlasili.'
            : e?.response?.data?.message || 'Nahlasenie sa nepodarilo odoslat.'

    error.value = message
    toast.warn(message)
  } finally {
    reportLoading.value = false
  }
}

function updatePostPoll(post, nextPoll) {
  if (!post || !nextPoll) return
  post.poll = nextPoll
}

function onPollLoginRequired() {
  error.value = 'Prihlas sa pre hlasovanie.'
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
    error.value = 'Prihlas sa pre lajkovanie.'
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
    if (status === 401) error.value = 'Prihlas sa.'
    else error.value = e?.response?.data?.message || 'Lajk zlyhal.'
  } finally {
    setLikeLoading(post.id, false)
  }
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

function normalizePaginationUrl(url) {
  const raw = String(url || '').trim()
  if (!raw) return ''

  const baseOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost'
  const apiPrefix = String(api?.defaults?.baseURL || '/api').replace(/\/+$/, '')

  try {
    const parsed = new URL(raw, baseOrigin)
    const pathWithQuery = `${parsed.pathname}${parsed.search || ''}`
    if (apiPrefix && pathWithQuery.startsWith(`${apiPrefix}/`)) {
      return pathWithQuery.slice(apiPrefix.length)
    }
    if (apiPrefix && pathWithQuery === apiPrefix) {
      return '/'
    }
    return pathWithQuery
  } catch {
    return raw
  }
}

async function load(reset = true, force = false) {
  if (loading.value && !force) return

  const activeTag = tag.value
  if (reset && !activeTag) {
    resetFeedState()
    error.value = 'Tag nie je zadany.'
    return
  }

  const requestId = ++requestSequence.value
  loading.value = true
  if (reset) {
    error.value = ''
  }

  try {
    let res = null

    if (reset) {
      res = await api.get('/posts', {
        params: {
          tag: activeTag,
          with: 'counts',
        },
      })
    } else {
      const url = normalizePaginationUrl(nextPageUrl.value)
      if (!url) {
        return
      }
      res = await api.get(url)
    }

    if (requestId !== requestSequence.value) {
      return
    }

    const payload = res?.data || {}
    const rows = Array.isArray(payload.data) ? payload.data : []

    if (reset) items.value = rows
    else items.value = [...items.value, ...rows]

    nextPageUrl.value = payload.next_page_url || null
  } catch (e) {
    if (requestId !== requestSequence.value) {
      return
    }

    const message = e?.response?.data?.message || e?.message || 'Nacitanie feedu zlyhalo.'
    if (reset) {
      error.value = message
    } else {
      toast.warn(message)
    }
  } finally {
    if (requestId === requestSequence.value) {
      loading.value = false
    }
  }
}

watch(tag, () => {
  requestSequence.value += 1
  closeReport(true)
  resetFeedState()
  void load(true, true)
}, { immediate: true })
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

.btnDanger {
  border-color: rgb(var(--color-danger-rgb) / 0.65);
  color: var(--color-danger);
  background: rgb(var(--color-danger-rgb) / 0.14);
}

.btnDanger:hover {
  border-color: rgb(var(--color-danger-rgb) / 0.85);
  background: rgb(var(--color-danger-rgb) / 0.22);
}

.reportModal {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgb(0 0 0 / 0.45);
}

.reportCard {
  width: min(520px, 100%);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  border-radius: 1rem;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 1rem;
  color: var(--color-surface);
}

.reportTitle {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 900;
}

.reportHint {
  margin: 0.35rem 0 0.85rem;
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.reportLabel {
  display: block;
  margin: 0.8rem 0 0.35rem;
  color: var(--color-text-secondary);
  font-size: 0.86rem;
}

.reportSelect,
.reportTextarea {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: var(--color-surface);
  padding: 0.55rem 0.65rem;
}

.reportTextarea {
  resize: vertical;
  min-height: 100px;
}

.reportActions {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
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
