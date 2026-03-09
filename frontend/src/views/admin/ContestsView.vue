<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { previewContestHashtags } from '@/services/api/admin/contests'

const loading = ref(false)
const error = ref('')
const hashtags = ref([])
const requestId = ref(0)
const copiedPostId = ref(null)
let debounceTimer = null
let copiedTimer = null

const filters = ref({
  query: '',
  from: toDateInput(nowWithOffsetDays(-30)),
  to: toDateInput(new Date()),
  hashtags_limit: 12,
  posts_limit: 5,
})

const canSearch = computed(() => {
  return Boolean(filters.value.from && filters.value.to)
})

function nowWithOffsetDays(days) {
  const value = new Date()
  value.setDate(value.getDate() + days)
  return value
}

function toDateInput(value) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

async function loadPreview() {
  if (!canSearch.value) return

  const currentRequest = requestId.value + 1
  requestId.value = currentRequest
  loading.value = true
  error.value = ''

  try {
    const response = await previewContestHashtags({
      query: filters.value.query || undefined,
      from: filters.value.from,
      to: filters.value.to,
      hashtags_limit: filters.value.hashtags_limit,
      posts_limit: filters.value.posts_limit,
    })

    if (currentRequest !== requestId.value) return
    hashtags.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (e) {
    if (currentRequest !== requestId.value) return
    error.value = e?.response?.data?.message || 'Nacitavanie hashtagov zlyhalo.'
    hashtags.value = []
  } finally {
    if (currentRequest === requestId.value) {
      loading.value = false
    }
  }
}

function queuePreviewLoad() {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  debounceTimer = setTimeout(() => {
    loadPreview()
  }, 300)
}

watch(
  () => [
    filters.value.query,
    filters.value.from,
    filters.value.to,
    filters.value.hashtags_limit,
    filters.value.posts_limit,
  ],
  () => {
    queuePreviewLoad()
  }
)

onMounted(() => {
  loadPreview()
})

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  if (copiedTimer) {
    clearTimeout(copiedTimer)
  }
})

async function copyEmail(email, postId) {
  if (!email) return

  try {
    await navigator.clipboard.writeText(email)
    copiedPostId.value = postId

    if (copiedTimer) {
      clearTimeout(copiedTimer)
    }
    copiedTimer = setTimeout(() => {
      copiedPostId.value = null
    }, 1600)
  } catch {
    copiedPostId.value = null
  }
}
</script>

<template>
  <AdminPageShell
    title="Hashtag preview"
    subtitle="Vyhladavanie hashtagov podla nazvu a obdobia od-do. Zobrazuje aj prispevky, usera a email."
  >
    <section class="panel">
      <header class="panelHead">
        <h2>Filtre</h2>
        <button type="button" class="btn" :disabled="loading || !canSearch" @click="loadPreview">
          {{ loading ? 'Nacitavam...' : 'Obnovit' }}
        </button>
      </header>

      <div class="formGrid">
        <label>
          <span>Hashtag nazov (bez #)</span>
          <input
            v-model.trim="filters.query"
            type="text"
            placeholder="napr. sutazim"
            autocomplete="off"
          />
        </label>

        <label>
          <span>Datum od</span>
          <input v-model="filters.from" type="date" />
        </label>

        <label>
          <span>Datum do</span>
          <input v-model="filters.to" type="date" />
        </label>

        <label>
          <span>Max hashtagov</span>
          <input v-model.number="filters.hashtags_limit" type="number" min="1" max="30" />
        </label>

        <label>
          <span>Max postov na hashtag</span>
          <input v-model.number="filters.posts_limit" type="number" min="1" max="30" />
        </label>
      </div>
    </section>

    <section class="panel">
      <header class="panelHead">
        <h2>Live preview</h2>
      </header>

      <p v-if="error" class="error">{{ error }}</p>
      <div v-else-if="loading" class="muted">Nacitavam preview...</div>
      <div v-else-if="hashtags.length === 0" class="muted">Pre zvoleny filter sa nenasli hashtagy.</div>

      <div v-else class="previewList">
        <article v-for="item in hashtags" :key="item.id" class="tagCard">
          <header class="tagHead">
            <h3>#{{ item.name }}</h3>
            <span class="count">{{ item.posts_count }} postov</span>
          </header>

          <div v-if="!item.posts || item.posts.length === 0" class="muted small">
            Bez postov v zvolenom rozsahu.
          </div>

          <div v-else class="postList">
            <article v-for="post in item.posts" :key="post.id" class="postCard">
              <div class="postMeta">
                <span class="metaStrong">Post #{{ post.id }}</span>
                <span>{{ formatDate(post.created_at) }}</span>
              </div>

              <p class="postContent">{{ post.content }}</p>

              <figure
                v-if="post.media?.is_image && post.media?.attachment_url"
                class="postImageWrap"
              >
                <img
                  class="postImage"
                  :src="post.media.attachment_url"
                  alt="Prilozeny obrazok v prispevku"
                  loading="lazy"
                />
              </figure>

              <p class="userRow">
                <strong>@{{ post.user?.username || 'unknown' }}</strong>
                <button
                  type="button"
                  class="copyEmailBtn"
                  :disabled="!post.user?.email"
                  @click="copyEmail(post.user?.email, post.id)"
                >
                  {{
                    copiedPostId === post.id
                      ? 'Skopirovane'
                      : (post.user?.email || 'bez emailu')
                  }}
                </button>
              </p>
            </article>
          </div>
        </article>
      </div>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.panel {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.38);
  padding: 14px;
}

.panelHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.panel h2 {
  margin: 0;
  font-size: 1.1rem;
}

.formGrid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.formGrid label {
  display: grid;
  gap: 6px;
  font-size: 0.85rem;
}

input {
  width: 100%;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: inherit;
  padding: 8px 10px;
}

.btn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 7px 12px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.error {
  color: var(--color-danger);
}

.muted {
  opacity: 0.75;
  font-size: 0.92rem;
}

.muted.small {
  font-size: 0.84rem;
}

.previewList {
  display: grid;
  gap: 12px;
}

.tagCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.5);
  padding: 12px;
  display: grid;
  gap: 10px;
}

.tagHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.tagHead h3 {
  margin: 0;
  font-size: 1rem;
}

.count {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 0.78rem;
}

.postList {
  display: grid;
  gap: 8px;
}

.postCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.45);
  padding: 10px;
  display: grid;
  gap: 8px;
}

.postMeta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.8rem;
  opacity: 0.82;
}

.metaStrong {
  font-weight: 600;
}

.postContent {
  margin: 0;
  white-space: pre-wrap;
  line-height: 1.4;
}

.postImageWrap {
  margin: 0;
}

.postImage {
  display: block;
  width: 100%;
  max-width: 460px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.15);
  background: rgb(var(--color-bg-rgb) / 0.35);
  object-fit: cover;
}

.userRow {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  font-size: 0.85rem;
}

.copyEmailBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 4px 10px;
  background: rgb(var(--color-bg-rgb) / 0.55);
  color: inherit;
  cursor: pointer;
  font-size: 0.78rem;
}

.copyEmailBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 980px) {
  .formGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 680px) {
  .formGrid {
    grid-template-columns: 1fr;
  }

  .postMeta {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
