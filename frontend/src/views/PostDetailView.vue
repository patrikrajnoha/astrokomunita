<template>
  <div class="wrap">
    <div class="card">
      <!-- top -->
      <div class="top">
        <router-link class="back" to="/">&larr; Späť</router-link>
      </div>

      <!-- loading -->
      <div v-if="loading" class="loading">
        Nacitavam prispevok...
      </div>

      <!-- error -->
      <div v-else-if="error" class="error">
        {{ error }}
      </div>

      <!-- content -->
      <div v-else>
        <!-- ROOT POST -->
        <article class="postCard">
          <div class="postLeft">
            <button class="avatar profileLink" type="button" @click="openProfile(root?.user)">
              <span>{{ initials(root?.user?.name) }}</span>
            </button>
          </div>

          <div class="postMain">
            <div class="postHead">
              <div class="who">
                <button class="name linkBtn" type="button" @click="openProfile(root?.user)">
                  {{ root?.user?.name ?? 'User' }}
                </button>
                <span v-if="root?.source_name === 'astrobot'" class="badge badgeAstrobot">🚀 AstroBot</span>
                <div class="meta">
                  <span class="time">{{ fmt(root?.created_at) }}</span>
                  <span class="dot">.</span>
                  <span class="viewMeta" title="Počet zobrazení" aria-label="Počet zobrazení">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <span>{{ Number(root?.views ?? 0) }}</span>
                  </span>
                  <span v-if="root?.user?.location" class="dot">.</span>
                  <span v-if="root?.user?.location" class="loc">
                    Location: {{ root.user.location }}
                  </span>
                  <span v-if="root?.source_name === 'astrobot'" class="botLabel">Automated news · replies disabled</span>
                </div>
              </div>
            </div>

            <div class="postText">
              <HashtagText :content="root?.content" />
            </div>

            <div v-if="root?.attachment_url" class="mediaWrap">
              <PostMediaImage
                v-if="isImage(root)"
                :src="attachmentSrc(root)"
                alt="Priloha prispevku"
              />

              <a
                v-else
                class="fileCard"
                :href="attachmentSrc(root)"
                target="_blank"
                rel="noopener"
              >
                <div class="fileIcon">[file]</div>
                <div class="fileInfo">
                  <div class="fileTitle">Priloha</div>
                  <div class="fileName">
                    {{ root?.attachment_original_name || 'Subor' }}
                  </div>
                </div>
                <div class="fileArrow">-></div>
              </a>
            </div>

            <div class="postActions">
              <button class="replyBtn" type="button" @click="openReport(root)">
                Report
              </button>
            </div>
          </div>
        </article>

        <!-- REPLY COMPOSER - only show for non-AstroBot posts -->
        <div v-if="root?.source_name !== 'astrobot'" class="composerWrap">
          <ReplyComposer
            v-if="root?.id"
            :parent-id="root.id"
            @created="onReplyCreated"
          />
        </div>
        <div v-else class="repliesDisabledNotice">
          <p>Replies are disabled on automated news posts.</p>
        </div>

        <!-- REPLIES -->
        <div class="replies">
          <div class="repliesHead">
            <div class="repliesTitle">Replies</div>
            <div class="repliesSub">
              Replies: {{ repliesCount }}
            </div>
          </div>

          <div v-if="replies.length === 0" class="repliesEmpty">
            Zatial bez reply.
          </div>

          <div v-else class="replyList">
            <article
              v-for="r in replies"
              :key="r.id"
              class="replyCard"
            >
              <div class="replyLeft">
                <button class="avatar avatarSm profileLink" type="button" @click="openProfile(r?.user)">
                  <span>{{ initials(r?.user?.name) }}</span>
                </button>
              </div>

              <div class="replyMain">
                <div class="replyHead">
                  <button class="name linkBtn" type="button" @click="openProfile(r?.user)">
                    {{ r?.user?.name ?? 'User' }}
                  </button>
                  <div class="meta">
                    <span class="time">{{ fmt(r?.created_at) }}</span>
                    <span v-if="r?.user?.location" class="dot">.</span>
                    <span v-if="r?.user?.location" class="loc">Location: {{ r.user.location }}</span>
                  </div>
                </div>

                <div class="replyText">
                  <HashtagText :content="r.content" />
                </div>

                <div v-if="r.attachment_url" class="mediaWrapSm">
                  <PostMediaImage
                    v-if="isImage(r)"
                    :src="attachmentSrc(r)"
                    alt="Priloha prispevku"
                  />

                  <a
                    v-else
                    class="fileCard fileCardSm"
                    :href="attachmentSrc(r)"
                    target="_blank"
                    rel="noopener"
                  >
                    <div class="fileIcon">[file]</div>
                    <div class="fileInfo">
                      <div class="fileTitle">Priloha</div>
                      <div class="fileName">
                        {{ r.attachment_original_name || 'Subor' }}
                      </div>
                    </div>
                    <div class="fileArrow">-></div>
                  </a>
                </div>

                <div class="replyActions">
                  <button
                    v-if="r.depth === 1"
                    class="replyBtn"
                    type="button"
                    @click="toggleReplyComposer(r.id)"
                  >
                    Reply
                  </button>
                  <button class="replyBtn" type="button" @click="openReport(r)">
                    Report
                  </button>
                </div>

                <div v-if="activeReplyId === r.id" class="composerWrapSm">
                  <ReplyComposer :parent-id="r.id" @created="onReplyCreated" />
                </div>

                <div v-if="r.replies && r.replies.length" class="replyChildren">
                  <article
                    v-for="c in r.replies"
                    :key="c.id"
                    class="replyCard replyCardChild"
                  >
                    <div class="replyLeft">
                      <button class="avatar avatarSm profileLink" type="button" @click="openProfile(c?.user)">
                        <span>{{ initials(c?.user?.name) }}</span>
                      </button>
                    </div>

                    <div class="replyMain">
                      <div class="replyHead">
                        <button class="name linkBtn" type="button" @click="openProfile(c?.user)">
                          {{ c?.user?.name ?? 'User' }}
                        </button>
                        <div class="meta">
                          <span class="time">{{ fmt(c?.created_at) }}</span>
                          <span v-if="c?.user?.location" class="dot">.</span>
                          <span v-if="c?.user?.location" class="loc">Location: {{ c.user.location }}</span>
                        </div>
                    </div>

                    <div class="replyText">
                      <HashtagText :content="c.content" />
                    </div>

                      <div v-if="c.attachment_url" class="mediaWrapSm">
                        <PostMediaImage
                          v-if="isImage(c)"
                          :src="attachmentSrc(c)"
                          alt="Priloha prispevku"
                        />

                        <a
                          v-else
                          class="fileCard fileCardSm"
                          :href="attachmentSrc(c)"
                          target="_blank"
                          rel="noopener"
                        >
                          <div class="fileIcon">[file]</div>
                          <div class="fileInfo">
                            <div class="fileTitle">Priloha</div>
                            <div class="fileName">
                              {{ c.attachment_original_name || 'Subor' }}
                            </div>
                          </div>
                          <div class="fileArrow">-></div>
                        </a>
                      </div>
                      <div class="replyActions">
                        <button class="replyBtn" type="button" @click="openReport(c)">
                          Report
                        </button>
                      </div>
                    </div>
                  </article>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>
      <div v-if="reportNotice" class="reportNotice">
        {{ reportNotice }}
      </div>
      <div v-if="reportTarget" class="reportBox">
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
          <button class="replyBtn" type="button" @click="closeReport">Cancel</button>
          <button class="replyBtn" type="button" @click="submitReport">Submit</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import HashtagText from '@/components/HashtagText.vue'
import api from '@/services/api'
import ReplyComposer from '@/components/ReplyComposer.vue'
import PostMediaImage from '@/components/media/PostMediaImage.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const post = ref(null)
const root = ref(null)
const replies = ref([])
const activeReplyId = ref(null)

const loading = ref(true)
const error = ref('')
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const reportNotice = ref('')
const lastTrackedViewKey = ref('')
let viewAnimationFrame = null

function openProfile(user) {
  const username = user?.username
  if (!username) return
  router.push(`/u/${username}`)
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

function attachmentSrc(p) {
  const u = p?.attachment_url
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')

  if (u.startsWith('/')) return origin + u
  return origin + '/' + u
}

function onReplyCreated(newReply) {
  if (!newReply?.id) return

  const rootId = root.value?.id
  if (!rootId) return

  if (Number(newReply.parent_id) === Number(rootId)) {
    const next = [...replies.value, { ...newReply, replies: [] }]
    next.sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
    replies.value = next
  } else {
    const parent = replies.value.find((r) => Number(r.id) === Number(newReply.parent_id))
    if (parent) {
      const children = Array.isArray(parent.replies) ? [...parent.replies, newReply] : [newReply]
      children.sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
      parent.replies = children
      replies.value = [...replies.value]
    }
  }

  // keep local counter (if exists)
  if (root.value && typeof root.value === 'object') {
    const curr = Number(root.value.replies_count ?? replies.value.length - 1)
    root.value.replies_count = Number.isFinite(curr) ? curr + 1 : replies.value.length
  }

  activeReplyId.value = null
}

function toggleReplyComposer(id) {
  activeReplyId.value = activeReplyId.value === id ? null : id
}

function openReport(post) {
  if (!post?.id) return
  reportTarget.value = post
  reportNotice.value = ''
}

function closeReport() {
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

function stopViewAnimation() {
  if (viewAnimationFrame !== null) {
    cancelAnimationFrame(viewAnimationFrame)
    viewAnimationFrame = null
  }
}

function animateRootViewsTo(targetViews) {
  if (!root.value) return

  const target = Number(targetViews)
  if (!Number.isFinite(target)) return

  const start = Number(root.value.views ?? 0)
  if (!Number.isFinite(start) || start === target) {
    root.value.views = target
    return
  }

  stopViewAnimation()

  const durationMs = 220
  const startedAt = performance.now()

  const tick = (now) => {
    if (!root.value) {
      stopViewAnimation()
      return
    }

    const progress = Math.min(1, (now - startedAt) / durationMs)
    const eased = 1 - Math.pow(1 - progress, 3)
    root.value.views = Math.round(start + (target - start) * eased)

    if (progress < 1) {
      viewAnimationFrame = requestAnimationFrame(tick)
      return
    }

    viewAnimationFrame = null
  }

  viewAnimationFrame = requestAnimationFrame(tick)
}

async function registerPostView(postId) {
  if (!postId) return

  const key = String(postId)
  if (lastTrackedViewKey.value === key) return
  lastTrackedViewKey.value = key

  try {
    const res = await api.post(`/posts/${postId}/view`)
    const nextViews = Number(res?.data?.views)
    if (Number.isFinite(nextViews) && root.value) {
      animateRootViewsTo(nextViews)
    }
  } catch {
    // Intentionally silent: view tracking must never block UI.
  }
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
    reportNotice.value = 'Thanks, we will review it.'
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) reportNotice.value = 'Prihlas sa.'
    else if (status === 409) reportNotice.value = 'Už si reportoval tento post.'
    else reportNotice.value = e?.response?.data?.message || 'Report zlyhal.'
  } finally {
    closeReport()
  }
}

async function loadPost() {
  loading.value = true
  error.value = ''
  post.value = null
  root.value = null
  replies.value = []

  try {
    const res = await api.get(`/posts/${route.params.id}`)
    const payload = res.data || {}

    post.value = payload.post ?? null
    root.value = payload.root ?? payload.post ?? null

    if (Array.isArray(payload.replies) && payload.replies.length > 0) {
      replies.value = payload.replies
    } else {
      const thread = Array.isArray(payload.thread) ? payload.thread : []
      const rootId = root.value?.id
      const byParent = thread.reduce((acc, p) => {
        const key = p?.parent_id ?? null
        if (!acc[key]) acc[key] = []
        acc[key].push(p)
        return acc
      }, {})

      const rootReplies = (byParent[rootId] || []).map((p) => ({
        ...p,
        replies: (byParent[p.id] || []).slice(),
      }))

      replies.value = rootReplies
    }

    void registerPostView(root.value?.id ?? route.params.id)
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      'Prispevok sa nepodarilo nacitat.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadPost()
})

onBeforeUnmount(() => {
  stopViewAnimation()
})

watch(
  () => route.params.id,
  () => loadPost()
)

const repliesCount = computed(() => {
  return replies.value.reduce((acc, r) => {
    const childCount = Array.isArray(r.replies) ? r.replies.length : 0
    return acc + 1 + childCount
  }, 0)
})
</script>

<style scoped>
.wrap {
  display: grid;
  gap: 1rem;
  width: 100%;
  min-width: 0;
}

.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  border-radius: 1.6rem;
  background: rgb(var(--color-bg-rgb) / 0.55);
  padding: clamp(0.85rem, 1.8vw, 1.15rem);
  width: 100%;
  min-width: 0;
  overflow: clip;
}

/* top */
.top {
  margin-bottom: 0.75rem;
}
.back {
  display: inline-flex;
  padding: 0.5rem 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
}
.back:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

/* states */
.loading {
  color: var(--color-text-secondary);
}
.error {
  color: var(--color-danger);
}

/* post layout (same as feed) */
.postCard {
  display: grid;
  grid-template-columns: 52px 1fr;
  gap: 0.85rem;
  padding: 0.95rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  border-radius: 1.35rem;
  background: rgb(var(--color-bg-rgb) / 0.25);
  min-width: 0;
  align-items: start;
}

.postMain,
.replyMain,
.who {
  min-width: 0;
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
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}

.name {
  color: var(--color-surface);
  font-weight: 950;
  max-width: 100%;
  overflow-wrap: anywhere;
}

.meta {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
  overflow-wrap: anywhere;
}
.dot { opacity: 0.65; }
.viewMeta {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.78rem;
  color: var(--color-text-secondary);
}

.postText {
  margin-top: 0.5rem;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.6;
  font-size: 1rem;
  overflow-wrap: anywhere;
  word-break: break-word;
}

/* media */
.mediaWrap {
  margin-top: 0.75rem;
}

/* file */
.fileCard {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem;
  border-radius: 1.15rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.25);
  color: var(--color-surface);
  min-width: 0;
  overflow: hidden;
}
.fileIcon {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgb(var(--color-primary-rgb) / 0.14);
}
.fileInfo {
  flex: 1;
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}
.fileTitle {
  font-weight: 900;
}
.fileName {
  color: var(--color-text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fileArrow {
  font-weight: 900;
}

/* composer wrap */
.composerWrap {
  margin-top: 0.9rem;
}

/* Replies section */
.replies {
  margin-top: 1rem;
  padding-top: 0.85rem;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
}
.repliesHead {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.15rem 0.2rem 0.6rem;
}
.repliesTitle {
  color: var(--color-surface);
  font-weight: 950;
  font-size: 1rem;
}
.repliesSub {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.repliesEmpty {
  padding: 0.6rem 0.25rem 0.25rem;
  color: var(--color-text-secondary);
}

.replyList {
  display: grid;
  gap: 0.6rem;
  padding: 0 0.15rem 0.25rem;
}
.replyCard {
  display: grid;
  grid-template-columns: 44px 1fr;
  gap: 0.75rem;
  padding: 0.8rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  border-radius: 1.2rem;
  background: rgb(var(--color-bg-rgb) / 0.22);
  min-width: 0;
  align-items: start;
}
.replyChildren {
  display: grid;
  gap: 0.6rem;
  margin: 0.25rem 0 0.1rem clamp(0.8rem, 2.5vw, 1.5rem);
}
.replyCardChild {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.18);
}

.avatarSm {
  width: 40px;
  height: 40px;
  font-size: 0.9rem;
}

.replyHead {
  display: grid;
  gap: 0.15rem;
}

.replyText {
  margin-top: 0.4rem;
  color: var(--color-surface);
  white-space: pre-wrap;
  line-height: 1.55;
  font-size: 0.98rem;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.mediaWrapSm {
  margin-top: 0.6rem;
}
.fileCardSm {
  padding: 0.7rem 0.75rem;
  border-radius: 1.05rem;
}

.replyActions {
  margin-top: 0.5rem;
}
.postActions {
  margin-top: 0.5rem;
}
.reportNotice {
  margin-top: 0.75rem;
  color: var(--color-text-secondary);
}
.reportBox {
  margin-top: 0.75rem;
  padding: 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.35);
  display: grid;
  gap: 0.6rem;
}
.reportTitle {
  font-weight: 800;
}
.reportRow {
  display: grid;
  gap: 0.35rem;
}
.reportRow label {
  font-size: 0.8rem;
  opacity: 0.8;
}
.reportRow select,
.reportRow textarea {
  border-radius: 0.75rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.7);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: inherit;
  padding: 0.5rem 0.65rem;
}
.reportActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  border: 1px solid rgb(var(--color-success-rgb) / 0.55);
  color: var(--color-success);
  background: rgb(var(--color-success-rgb) / 0.25);
}
.badgeAstrobot {
  border-color: rgb(var(--color-success-rgb) / 0.55);
  color: var(--color-success);
  background: rgb(var(--color-success-rgb) / 0.25);
}
.botLabel {
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  font-style: italic;
  opacity: 0.8;
}
.repliesDisabledNotice {
  margin: 1rem 0;
  padding: 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  background: rgb(var(--color-bg-rgb) / 0.25);
  text-align: center;
  color: var(--color-text-secondary);
  font-style: italic;
}
.replyBtn {
  padding: 0.45rem 0.7rem;
  border-radius: 0.85rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
  white-space: nowrap;
}
.replyBtn:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}
.composerWrapSm { margin-top: 0.6rem; }

@media (max-width: 768px) {
  .card {
    border-radius: 1.2rem;
  }

  .postCard,
  .replyCard {
    grid-template-columns: 42px 1fr;
    gap: 0.6rem;
    padding: 0.72rem;
    border-radius: 1rem;
  }

  .avatar,
  .avatarSm {
    width: 38px;
    height: 38px;
    font-size: 0.82rem;
  }

  .meta {
    font-size: 0.82rem;
    gap: 0.35rem;
  }

  .reportActions {
    justify-content: flex-start;
  }
}

@media (max-width: 480px) {
  .card {
    padding: 0.72rem;
    border-radius: 1rem;
  }

  .replyCardChild {
    margin-left: 0.45rem;
  }

  .postText,
  .replyText {
    font-size: 0.92rem;
  }

  .fileCard,
  .fileCardSm {
    padding: 0.62rem;
  }
}
</style>
