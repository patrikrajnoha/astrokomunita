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

const HASHTAG_PRESETS = [5, 12, 20]
const POSTS_PRESETS = [3, 5, 10]

const filters = ref({
  query: '',
  from: toDateInput(nowWithOffsetDays(-30)),
  to: toDateInput(new Date()),
  hashtags_limit: 12,
  posts_limit: 5,
})

const canSearch = computed(() => Boolean(filters.value.from && filters.value.to))

const totalPostsCount = computed(() =>
  hashtags.value.reduce((sum, tag) => sum + (tag.posts?.length ?? 0), 0)
)

const hashtagsWithPosts = computed(() => hashtags.value.filter((t) => t.posts?.length > 0))
const hashtagsEmpty = computed(() => hashtags.value.filter((t) => !t.posts?.length))

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

function countClass(count) {
  if (!count) return 'count--zero'
  if (count >= 10) return 'count--high'
  return 'count--some'
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
    error.value = e?.response?.data?.message || 'Načítavanie hashtagov zlyhalo.'
    hashtags.value = []
  } finally {
    if (currentRequest === requestId.value) {
      loading.value = false
    }
  }
}

function queuePreviewLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { loadPreview() }, 300)
}

watch(
  () => [
    filters.value.query,
    filters.value.from,
    filters.value.to,
    filters.value.hashtags_limit,
    filters.value.posts_limit,
  ],
  () => { queuePreviewLoad() }
)

onMounted(() => { loadPreview() })

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
  clearTimeout(copiedTimer)
})

async function copyEmail(email, postId) {
  if (!email) return
  try {
    await navigator.clipboard.writeText(email)
    copiedPostId.value = postId
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => { copiedPostId.value = null }, 1600)
  } catch {
    copiedPostId.value = null
  }
}
</script>

<template>
  <AdminPageShell
    title="Súťaže — prehľad hashtagov"
    subtitle="Vyhľadávanie hashtagov podľa názvu a obdobia od–do. Zobrazuje príspevky, používateľa a e-mail."
  >
    <div class="layout">
      <!-- Filters sidebar -->
      <aside class="filterPanel">
        <div class="filterPanelHead">
          <h2>Filtre</h2>
          <button type="button" class="refreshBtn" :disabled="loading || !canSearch" :title="'Obnoviť'" @click="loadPreview">
            {{ loading ? '…' : '↺' }}
          </button>
        </div>

        <div class="formGrid">
          <label class="fieldLabel">
            <span>Hashtag (bez #)</span>
            <input
              v-model.trim="filters.query"
              type="text"
              placeholder="napr. sutazim"
              autocomplete="off"
            />
          </label>

          <label class="fieldLabel">
            <span>Dátum od</span>
            <input v-model="filters.from" type="date" />
          </label>

          <label class="fieldLabel">
            <span>Dátum do</span>
            <input v-model="filters.to" type="date" />
          </label>

          <div class="fieldLabel">
            <span>Max hashtagov</span>
            <div class="presetRow">
              <input v-model.number="filters.hashtags_limit" type="number" min="1" max="30" class="numInput" />
              <div class="presets">
                <button
                  v-for="p in HASHTAG_PRESETS"
                  :key="p"
                  type="button"
                  class="presetBtn"
                  :class="{ active: filters.hashtags_limit === p }"
                  @click="filters.hashtags_limit = p"
                >{{ p }}</button>
              </div>
            </div>
          </div>

          <div class="fieldLabel">
            <span>Max postov / hashtag</span>
            <div class="presetRow">
              <input v-model.number="filters.posts_limit" type="number" min="1" max="30" class="numInput" />
              <div class="presets">
                <button
                  v-for="p in POSTS_PRESETS"
                  :key="p"
                  type="button"
                  class="presetBtn"
                  :class="{ active: filters.posts_limit === p }"
                  @click="filters.posts_limit = p"
                >{{ p }}</button>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <!-- Results -->
      <div class="resultsPanel">
        <div class="resultsPanelHead">
          <h2>Výsledky</h2>
          <span v-if="!loading && hashtags.length > 0" class="summary">
            {{ hashtags.length }} hashtagov · {{ totalPostsCount }} príspevkov
          </span>
        </div>

        <p v-if="error" class="error">{{ error }}</p>
        <div v-else-if="loading" class="muted">Načítavam výsledky...</div>
        <div v-else-if="hashtags.length === 0" class="muted">Pre zvolený filter sa nenašli hashtagy.</div>

        <div v-else class="previewList">
          <!-- Tags with posts -->
          <article v-for="item in hashtagsWithPosts" :key="item.id" class="tagCard">
            <header class="tagHead">
              <h3>#{{ item.name }}</h3>
              <span class="count" :class="countClass(item.posts_count)">{{ item.posts_count }} postov</span>
            </header>

            <div class="postList">
              <article v-for="post in item.posts" :key="post.id" class="postCard">
                <div class="postMeta">
                  <span class="metaStrong">Post #{{ post.id }}</span>
                  <span class="metaMuted">{{ formatDate(post.created_at) }}</span>
                </div>

                <div class="postBody">
                  <p class="postContent">{{ post.content }}</p>

                  <figure
                    v-if="post.media?.is_image && post.media?.attachment_url"
                    class="postImageWrap"
                  >
                    <img
                      class="postImage"
                      :src="post.media.attachment_url"
                      alt="Priložený obrázok"
                      loading="lazy"
                    />
                  </figure>
                </div>

                <div class="userRow">
                  <span class="username">@{{ post.user?.username || 'unknown' }}</span>
                  <button
                    type="button"
                    class="copyEmailBtn"
                    :class="{ copied: copiedPostId === post.id }"
                    :disabled="!post.user?.email"
                    @click="copyEmail(post.user?.email, post.id)"
                  >
                    <span v-if="copiedPostId === post.id">✓ Skopírované</span>
                    <span v-else>{{ post.user?.email || 'bez e-mailu' }}</span>
                  </button>
                </div>
              </article>
            </div>
          </article>

          <!-- Tags without posts — dimmed -->
          <div v-if="hashtagsEmpty.length > 0" class="emptyTagsSection">
            <p class="muted emptyTagsLabel">Hashtagy bez príspevkov v zvolenom rozsahu ({{ hashtagsEmpty.length }})</p>
            <div class="emptyTagsList">
              <span v-for="item in hashtagsEmpty" :key="item.id" class="emptyTagChip">#{{ item.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminPageShell>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  gap: 14px;
  align-items: start;
}

/* Filter panel */
.filterPanel {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 14px;
  background: rgba(21, 29, 40, 0.38);
  padding: 12px;
  position: sticky;
  top: 12px;
}

.filterPanelHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}

.filterPanelHead h2 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
}

.refreshBtn {
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 8px;
  padding: 4px 8px;
  background: transparent;
  color: #ABB8C9;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  min-width: 28px;
  transition: background 0.14s, color 0.14s;
}

.refreshBtn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.07);
  color: #ffffff;
}

.refreshBtn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.formGrid {
  display: grid;
  gap: 10px;
}

.fieldLabel {
  display: grid;
  gap: 5px;
  font-size: 0.8rem;
  color: rgba(171, 184, 201, 0.9);
}

.fieldLabel input {
  width: 100%;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(21, 29, 40, 0.5);
  color: #ffffff;
  padding: 6px 9px;
  font-size: 13px;
  outline: none;
  color-scheme: dark;
}

.fieldLabel input:focus {
  border-color: rgba(15, 115, 255, 0.6);
}

.presetRow {
  display: grid;
  gap: 5px;
}

.numInput {
  width: 100%;
}

.presets {
  display: flex;
  gap: 4px;
}

.presetBtn {
  flex: 1;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 6px;
  padding: 3px 0;
  background: transparent;
  color: rgba(171, 184, 201, 0.65);
  cursor: pointer;
  font-size: 11px;
  transition: background 0.12s, color 0.12s, border-color 0.12s;
}

.presetBtn.active {
  border-color: rgba(15, 115, 255, 0.5);
  background: rgba(15, 115, 255, 0.14);
  color: #0F73FF;
}

.presetBtn:hover:not(.active) {
  background: rgba(255, 255, 255, 0.06);
  color: #ffffff;
}

/* Results panel */
.resultsPanel {
  min-width: 0;
}

.resultsPanelHead {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.resultsPanelHead h2 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
}

.summary {
  font-size: 12px;
  color: rgba(171, 184, 201, 0.8);
  padding: 3px 9px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 999px;
}

.error {
  color: #EB2452;
  font-size: 13px;
}

.muted {
  opacity: 0.7;
  font-size: 0.88rem;
}

.previewList {
  display: grid;
  gap: 10px;
}

/* Tag card */
.tagCard {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 14px;
  background: rgba(21, 29, 40, 0.5);
  padding: 12px;
  display: grid;
  gap: 8px;
}

.tagHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.tagHead h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
}

.count {
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 999px;
  padding: 2px 9px;
  font-size: 0.75rem;
  font-weight: 500;
}

.count--zero {
  opacity: 0.45;
}

.count--some {
  border-color: rgba(15, 115, 255, 0.35);
  background: rgba(15, 115, 255, 0.08);
  color: #0F73FF;
}

.count--high {
  border-color: rgba(34, 197, 94, 0.4);
  background: rgba(34, 197, 94, 0.1);
  color: #22C55E;
}

/* Post list */
.postList {
  display: grid;
  gap: 6px;
}

.postCard {
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 10px;
  background: rgba(21, 29, 40, 0.45);
  padding: 9px 10px;
  display: grid;
  gap: 6px;
}

.postMeta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.metaStrong {
  font-size: 11px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
}

.metaMuted {
  font-size: 11px;
  color: rgba(171, 184, 201, 0.7);
}

.postBody {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.postContent {
  flex: 1;
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
  white-space: pre-wrap;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.postImageWrap {
  margin: 0;
  flex-shrink: 0;
}

.postImage {
  display: block;
  width: 80px;
  height: 80px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  object-fit: cover;
}

.userRow {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.username {
  font-size: 12px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
}

.copyEmailBtn {
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  padding: 3px 10px;
  background: rgba(21, 29, 40, 0.55);
  color: #ABB8C9;
  cursor: pointer;
  font-size: 11px;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.copyEmailBtn:hover:not(:disabled):not(.copied) {
  border-color: rgba(255, 255, 255, 0.28);
  color: #ffffff;
}

.copyEmailBtn.copied {
  border-color: rgba(34, 197, 94, 0.5);
  background: rgba(34, 197, 94, 0.12);
  color: #22C55E;
}

.copyEmailBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Empty tags */
.emptyTagsSection {
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 10px;
  padding: 10px 12px;
  background: rgba(21, 29, 40, 0.25);
}

.emptyTagsLabel {
  margin: 0 0 8px;
}

.emptyTagsList {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.emptyTagChip {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 999px;
  padding: 2px 9px;
  font-size: 11px;
  opacity: 0.55;
}

@media (max-width: 860px) {
  .layout {
    grid-template-columns: 1fr;
  }

  .filterPanel {
    position: static;
  }

  .formGrid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 520px) {
  .formGrid {
    grid-template-columns: 1fr;
  }
}
</style>
