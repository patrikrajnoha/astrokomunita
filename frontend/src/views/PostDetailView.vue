<template>
  <div class="wrap">
    <div class="card">
      <!-- top -->
      <div class="top">
        <router-link class="back" to="/">← Späť</router-link>
      </div>

      <!-- loading -->
      <div v-if="loading" class="loading">
        Načítavam príspevok…
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
            <div class="avatar">
              <span>{{ initials(post?.user?.name) }}</span>
            </div>
          </div>

          <div class="postMain">
            <div class="postHead">
              <div class="who">
                <div class="name">{{ post?.user?.name ?? 'User' }}</div>
                <div class="meta">
                  <span class="time">{{ fmt(post?.created_at) }}</span>
                  <span v-if="post?.user?.location" class="dot">•</span>
                  <span v-if="post?.user?.location" class="loc">
                    📍 {{ post.user.location }}
                  </span>
                </div>
              </div>
            </div>

            <div class="postText">
              {{ post.content }}
            </div>

            <div v-if="post.attachment_url" class="mediaWrap">
              <img
                v-if="isImage(post)"
                class="mediaImg"
                :src="attachmentSrc(post)"
                alt="Príloha"
              />

              <a
                v-else
                class="fileCard"
                :href="attachmentSrc(post)"
                target="_blank"
                rel="noopener"
              >
                <div class="fileIcon">📎</div>
                <div class="fileInfo">
                  <div class="fileTitle">Príloha</div>
                  <div class="fileName">
                    {{ post.attachment_original_name || 'Súbor' }}
                  </div>
                </div>
                <div class="fileArrow">→</div>
              </a>
            </div>
          </div>
        </article>

        <!-- REPLY COMPOSER (MVP: nech je vždy viditeľný; ak user nie je prihlásený, backend vráti 401) -->
        <div class="composerWrap">
          <ReplyComposer
            v-if="post?.id"
            :parent-id="post.id"
            @created="onReplyCreated"
          />
        </div>

        <!-- REPLIES -->
        <div class="replies">
          <div class="repliesHead">
            <div class="repliesTitle">Replies</div>
            <div class="repliesSub">
              💬 {{ replies.length }}
            </div>
          </div>

          <div v-if="replies.length === 0" class="repliesEmpty">
            Zatiaľ bez reply.
          </div>

          <div v-else class="replyList">
            <article
              v-for="r in replies"
              :key="r.id"
              class="replyCard"
            >
              <div class="replyLeft">
                <div class="avatar avatarSm">
                  <span>{{ initials(r?.user?.name) }}</span>
                </div>
              </div>

              <div class="replyMain">
                <div class="replyHead">
                  <div class="name">{{ r?.user?.name ?? 'User' }}</div>
                  <div class="meta">
                    <span class="time">{{ fmt(r?.created_at) }}</span>
                    <span v-if="r?.user?.location" class="dot">•</span>
                    <span v-if="r?.user?.location" class="loc">📍 {{ r.user.location }}</span>
                  </div>
                </div>

                <div class="replyText">{{ r.content }}</div>

                <div v-if="r.attachment_url" class="mediaWrapSm">
                  <img
                    v-if="isImage(r)"
                    class="mediaImgSm"
                    :src="attachmentSrc(r)"
                    alt="Príloha"
                    loading="lazy"
                  />

                  <a
                    v-else
                    class="fileCard fileCardSm"
                    :href="attachmentSrc(r)"
                    target="_blank"
                    rel="noopener"
                  >
                    <div class="fileIcon">📎</div>
                    <div class="fileInfo">
                      <div class="fileTitle">Príloha</div>
                      <div class="fileName">
                        {{ r.attachment_original_name || 'Súbor' }}
                      </div>
                    </div>
                    <div class="fileArrow">→</div>
                  </a>
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
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import ReplyComposer from '@/components/ReplyComposer.vue'

const route = useRoute()

const post = ref(null)
const replies = ref([])

const loading = ref(true)
const error = ref('')

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

  // pridaj navrch (novšie reply najprv)
  replies.value = [newReply, ...replies.value]

  // udrž lokálny counter (ak existuje)
  if (post.value && typeof post.value === 'object') {
    const curr = Number(post.value.replies_count ?? replies.value.length - 1)
    // bezpečne inkrementni
    post.value.replies_count = Number.isFinite(curr) ? curr + 1 : replies.value.length
  }
}

async function loadPost() {
  loading.value = true
  error.value = ''
  post.value = null
  replies.value = []

  try {
    const res = await api.get(`/posts/${route.params.id}`)
    const payload = res.data || {}

    post.value = payload.post ?? null
    replies.value = Array.isArray(payload.replies) ? payload.replies : []
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      'Príspevok sa nepodarilo načítať.'
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

/* post layout (rovnaké ako feed) */
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
</style>
