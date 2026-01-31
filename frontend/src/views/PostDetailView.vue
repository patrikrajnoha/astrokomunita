<template>
  <div class="wrap">
    <div class="card">
      <!-- top -->
      <div class="top">
        <router-link class="back" to="/"><- Spat</router-link>
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
                <div class="meta">
                  <span class="time">{{ fmt(root?.created_at) }}</span>
                  <span v-if="root?.user?.location" class="dot">.</span>
                  <span v-if="root?.user?.location" class="loc">
                    Location: {{ root.user.location }}
                  </span>
                </div>
              </div>
            </div>

            <div class="postText">
              {{ root?.content }}
            </div>

            <div v-if="root?.attachment_url" class="mediaWrap">
              <img
                v-if="isImage(root)"
                class="mediaImg"
                :src="attachmentSrc(root)"
                alt="Priloha"
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
          </div>
        </article>

        <!-- REPLY COMPOSER (MVP: nech je vzdy viditelny; ak user nie je prihlaseny, backend vrati 401) -->
        <div class="composerWrap">
          <ReplyComposer
            v-if="root?.id"
            :parent-id="root.id"
            @created="onReplyCreated"
          />
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

                <div class="replyText">{{ r.content }}</div>

                <div v-if="r.attachment_url" class="mediaWrapSm">
                  <img
                    v-if="isImage(r)"
                    class="mediaImgSm"
                    :src="attachmentSrc(r)"
                    alt="Priloha"
                    loading="lazy"
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

                <div v-if="r.depth === 1" class="replyActions">
                  <button class="replyBtn" type="button" @click="toggleReplyComposer(r.id)">
                    Reply
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

                      <div class="replyText">{{ c.content }}</div>

                      <div v-if="c.attachment_url" class="mediaWrapSm">
                        <img
                          v-if="isImage(c)"
                          class="mediaImgSm"
                          :src="attachmentSrc(c)"
                          alt="Priloha"
                          loading="lazy"
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
                    </div>
                  </article>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import ReplyComposer from '@/components/ReplyComposer.vue'

const route = useRoute()
const router = useRouter()

const post = ref(null)
const root = ref(null)
const replies = ref([])
const activeReplyId = ref(null)

const loading = ref(true)
const error = ref('')

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
}

.card {
  border: 1px solid rgba(51, 65, 85, 0.75);
  border-radius: 1.6rem;
  background: rgba(15, 23, 42, 0.55);
  padding: 1.15rem;
}

/* top */
.top {
  margin-bottom: 0.75rem;
}
.back {
  display: inline-flex;
  padding: 0.5rem 0.75rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(51 65 85);
  color: rgb(203 213 225);
  background: rgba(15, 23, 42, 0.2);
}
.back:hover {
  border-color: rgb(99 102 241);
  color: white;
  background: rgba(99, 102, 241, 0.08);
}

/* states */
.loading {
  color: rgb(148 163 184);
}
.error {
  color: rgb(254 202 202);
}

/* post layout (same as feed) */
.postCard {
  display: grid;
  grid-template-columns: 52px 1fr;
  gap: 0.85rem;
  padding: 0.95rem;
  border: 1px solid rgba(51, 65, 85, 0.55);
  border-radius: 1.35rem;
  background: rgba(2, 6, 23, 0.25);
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

.postHead {
  display: flex;
  justify-content: space-between;
}

.name {
  color: rgb(226 232 240);
  font-weight: 950;
}

.meta {
  color: rgb(148 163 184);
  font-size: 0.9rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
}
.dot { opacity: 0.65; }

.postText {
  margin-top: 0.5rem;
  color: rgb(226 232 240);
  white-space: pre-wrap;
  line-height: 1.6;
  font-size: 1rem;
}

/* media */
.mediaWrap {
  margin-top: 0.75rem;
}
.mediaImg {
  width: 100%;
  max-height: 520px;
  object-fit: cover;
  border-radius: 1.15rem;
  border: 1px solid rgba(51, 65, 85, 0.55);
}

/* file */
.fileCard {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem;
  border-radius: 1.15rem;
  border: 1px solid rgba(51, 65, 85, 0.55);
  background: rgba(2, 6, 23, 0.25);
  color: rgb(226 232 240);
}
.fileIcon {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(99, 102, 241, 0.14);
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
  border-top: 1px solid rgba(51, 65, 85, 0.45);
}
.repliesHead {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.15rem 0.2rem 0.6rem;
}
.repliesTitle {
  color: rgb(226 232 240);
  font-weight: 950;
  font-size: 1rem;
}
.repliesSub {
  color: rgb(148 163 184);
  font-size: 0.9rem;
}

.repliesEmpty {
  padding: 0.6rem 0.25rem 0.25rem;
  color: rgb(148 163 184);
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
  border: 1px solid rgba(51, 65, 85, 0.45);
  border-radius: 1.2rem;
  background: rgba(2, 6, 23, 0.22);
}
.replyChildren {
  display: grid;
  gap: 0.6rem;
  margin: 0.25rem 0 0.1rem 1.5rem;
}
.replyCardChild {
  border-color: rgba(51, 65, 85, 0.35);
  background: rgba(2, 6, 23, 0.18);
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
  color: rgb(226 232 240);
  white-space: pre-wrap;
  line-height: 1.55;
  font-size: 0.98rem;
}

.mediaWrapSm {
  margin-top: 0.6rem;
}
.mediaImgSm {
  width: 100%;
  max-height: 360px;
  object-fit: cover;
  border-radius: 1.05rem;
  border: 1px solid rgba(51, 65, 85, 0.5);
}
.fileCardSm {
  padding: 0.7rem 0.75rem;
  border-radius: 1.05rem;
}

.replyActions {
  margin-top: 0.5rem;
}
.replyBtn {
  padding: 0.45rem 0.7rem;
  border-radius: 0.85rem;
  border: 1px solid rgb(51 65 85);
  color: rgb(203 213 225);
  background: rgba(15, 23, 42, 0.2);
}
.replyBtn:hover {
  border-color: rgb(99 102 241);
  color: white;
  background: rgba(99, 102, 241, 0.08);
}
.composerWrapSm {
  margin-top: 0.6rem;
}
</style>
