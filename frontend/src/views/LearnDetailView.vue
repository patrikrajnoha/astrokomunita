<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { blogPosts } from '@/services/blogPosts'
import { blogComments } from '@/services/blogComments'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const loading = ref(false)
const error = ref('')
const post = ref(null)
const related = ref([])
const relatedLoading = ref(false)

const copied = ref(false)
const commentsData = ref(null)
const commentsLoading = ref(false)
const commentsError = ref('')
const commentInput = ref('')
const commentSubmitting = ref(false)
const commentPage = ref(1)

const slug = computed(() => String(route.params.slug || ''))
const comments = computed(() => commentsData.value?.data || [])

function setMeta({ title, description, image }) {
  if (typeof document === 'undefined') return
  document.title = title

  const ensure = (name, property) => {
    const selector = property ? `meta[property='${name}']` : `meta[name='${name}']`
    let tag = document.querySelector(selector)
    if (!tag) {
      tag = document.createElement('meta')
      tag.setAttribute(property ? 'property' : 'name', name)
      document.head.appendChild(tag)
    }
    return tag
  }

  ensure('description', false).setAttribute('content', description)
  ensure('og:title', true).setAttribute('content', title)
  ensure('og:description', true).setAttribute('content', description)

  if (image) {
    ensure('og:image', true).setAttribute('content', image)
  }
}

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString('sk-SK', { dateStyle: 'long' })
}

function stripHtml(text) {
  return String(text || '').replace(/<[^>]*>/g, ' ')
}

function excerpt(text, limit = 180) {
  const clean = stripHtml(text).replace(/\s+/g, ' ').trim()
  if (!clean) return ''
  if (clean.length <= limit) return clean
  return `${clean.slice(0, limit).trim()}...`
}

const articleWordCount = computed(() => {
  const clean = stripHtml(post.value?.content || '').trim()
  if (!clean) return 0
  return clean.split(/\s+/).filter(Boolean).length
})

const readTime = computed(() => {
  const words = articleWordCount.value
  if (!words) return '1 min citania'
  const minutes = Math.max(1, Math.round(words / 220))
  return `${minutes} min citania`
})

const isLongRead = computed(() => articleWordCount.value >= 500)

const metaParts = computed(() => {
  if (!post.value) return []
  const parts = [
    formatDate(post.value.published_at),
    post.value.user?.name || 'Redakcia',
    readTime.value,
  ]

  if (typeof post.value.views === 'number') {
    parts.push(`${post.value.views} zobrazeni`)
  }

  return parts
})

function currentUrl() {
  if (typeof window === 'undefined') return ''
  return window.location.href
}

async function copyLink() {
  const value = currentUrl()
  if (!value) return

  try {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value)
    } else {
      const input = document.createElement('input')
      input.value = value
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
    }

    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 1800)
  } catch {
    copied.value = false
  }
}

function commentDepth(comment) {
  const depth = Number(comment?.depth ?? 0)
  if (!Number.isFinite(depth) || depth <= 0) return 0
  return Math.min(depth, 3)
}

function commentThreadStyle(comment) {
  return {
    marginLeft: `${commentDepth(comment) * 14}px`,
  }
}

function slugifyHeading(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .slice(0, 80)
}

function escapeHtml(text) {
  return String(text || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function inlineMarkdown(text) {
  let html = escapeHtml(text)
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>')
  html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>')
  html = html.replace(
    /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
  )
  return html
}

const contentBlocks = computed(() => {
  const raw = post.value?.content || ''
  if (!raw.trim()) return []

  const lines = raw.split(/\r?\n/)
  const blocks = []
  let paragraph = []
  let list = []

  const flushParagraph = () => {
    const text = paragraph.join(' ').trim()
    if (text) {
      blocks.push({ type: 'p', html: inlineMarkdown(text) })
    }
    paragraph = []
  }

  const flushList = () => {
    if (!list.length) return
    blocks.push({ type: 'ul', items: list.map((item) => inlineMarkdown(item)) })
    list = []
  }

  lines.forEach((line) => {
    const trimmed = line.trim()
    const h2 = trimmed.startsWith('## ')
    const h3 = trimmed.startsWith('### ')
    const isList = trimmed.startsWith('- ') || trimmed.startsWith('* ')

    if (h2 || h3) {
      flushList()
      flushParagraph()
      const title = trimmed.replace(/^###?\s+/, '')
      blocks.push({
        type: h3 ? 'h3' : 'h2',
        text: title,
        id: slugifyHeading(title),
      })
      return
    }

    if (!trimmed) {
      flushList()
      flushParagraph()
      return
    }

    if (isList) {
      flushParagraph()
      list.push(trimmed.replace(/^[-*]\s+/, ''))
      return
    }

    paragraph.push(trimmed)
  })

  flushList()
  flushParagraph()
  return blocks
})

const tocItems = computed(() => {
  return contentBlocks.value.filter((item) => item.type === 'h2' || item.type === 'h3')
})

const showToc = computed(() => {
  if (tocItems.value.length === 0) return false
  if (isLongRead.value) return tocItems.value.length >= 2
  return tocItems.value.length >= 3
})

function postLink(value) {
  return `/clanky/${value.slug || value.id}`
}

async function loadRelated() {
  if (!slug.value) return
  relatedLoading.value = true

  try {
    related.value = await blogPosts.getRelated(slug.value)
  } catch {
    related.value = []
  } finally {
    relatedLoading.value = false
  }
}

async function loadComments() {
  if (!slug.value) return

  commentsLoading.value = true
  commentsError.value = ''

  try {
    commentsData.value = await blogComments.list(slug.value, {
      page: commentPage.value,
      withDepth: 1,
    })
  } catch (e) {
    commentsError.value = e?.response?.data?.message || 'Komentare sa nepodarilo nacitat.'
  } finally {
    commentsLoading.value = false
  }
}

async function load() {
  if (!slug.value) {
    error.value = 'Neplatny clanok.'
    return
  }

  loading.value = true
  error.value = ''
  post.value = null
  related.value = []
  commentPage.value = 1
  commentsData.value = null

  try {
    const payload = await blogPosts.getPublic(slug.value)
    post.value = payload

    setMeta({
      title: `${payload?.title || 'Clanok'} | Astrokomunita`,
      description: excerpt(payload?.content || '', 160) || 'Clanok o astronomii a pozorovani oblohy.',
      image: payload?.cover_image_url || null,
    })

    loadComments()
    loadRelated()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Clanok sa nepodarilo nacitat.'
  } finally {
    loading.value = false
  }
}

async function submitComment() {
  if (!auth.isAuthed) {
    commentsError.value = 'Pre pridanie komentara sa prihlas.'
    return
  }

  const content = commentInput.value.trim()
  if (!content || !slug.value) return

  commentSubmitting.value = true
  commentsError.value = ''

  try {
    await blogComments.create(slug.value, { content })
    commentInput.value = ''
    commentPage.value = 1
    await loadComments()
  } catch (e) {
    commentsError.value = e?.response?.data?.message || 'Komentar sa nepodarilo odoslat.'
  } finally {
    commentSubmitting.value = false
  }
}

async function removeComment(id) {
  if (!slug.value) return

  try {
    await blogComments.remove(slug.value, id)
    await loadComments()
  } catch (e) {
    commentsError.value = e?.response?.data?.message || 'Komentar sa nepodarilo zmazat.'
  }
}

function prevComments() {
  if (!commentsData.value || commentPage.value <= 1) return
  commentPage.value -= 1
  loadComments()
}

function nextComments() {
  if (!commentsData.value || commentPage.value >= commentsData.value.last_page) return
  commentPage.value += 1
  loadComments()
}

onMounted(load)

watch(
  () => route.params.slug,
  () => {
    load()
  },
)
</script>

<template>
  <article class="detailPage" :class="{ 'detailPage--reading': isLongRead }">
    <router-link class="backLink" to="/clanky">Spat na clanky</router-link>

    <div v-if="error" class="state state--error">{{ error }}</div>
    <div v-else-if="loading" class="state">Nacitavam clanok...</div>

    <template v-else-if="post">
      <header class="header">
        <h1>{{ post.title }}</h1>

        <ul class="headerMeta">
          <li v-for="(part, index) in metaParts" :key="`meta-${index}`">{{ part }}</li>
        </ul>

        <div class="headerActions">
          <div v-if="post.tags?.length" class="tags">
            <span v-for="tag in post.tags" :key="tag.id">{{ tag.name }}</span>
          </div>

          <button class="shareBtn" type="button" @click="copyLink">
            {{ copied ? 'Link skopirovany' : 'Kopirovat link' }}
          </button>
        </div>
      </header>

      <section class="articleCard">
        <figure v-if="post.cover_image_url" class="cover">
          <img :src="post.cover_image_url" :alt="post.title" loading="lazy" />
        </figure>

        <div class="articleBody">
          <section v-if="showToc" class="toc">
            <p class="toc__title">Obsah clanku</p>
            <ul>
              <li v-for="item in tocItems" :key="item.id" :class="item.type">
                <a :href="`#${item.id}`">{{ item.text }}</a>
              </li>
            </ul>
          </section>

          <section class="content">
            <template v-for="(block, index) in contentBlocks" :key="index">
              <h2 v-if="block.type === 'h2'" :id="block.id">{{ block.text }}</h2>
              <h3 v-else-if="block.type === 'h3'" :id="block.id">{{ block.text }}</h3>
              <ul v-else-if="block.type === 'ul'">
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex" v-html="item"></li>
              </ul>
              <p v-else v-html="block.html"></p>
            </template>
          </section>
        </div>
      </section>

      <section class="section comments">
        <div class="comments__head">
          <h2>Komentare</h2>
          <span>{{ commentsData?.total || 0 }} celkom</span>
        </div>

        <div v-if="commentsError" class="state state--error">{{ commentsError }}</div>

        <div v-if="auth.isAuthed" class="commentForm">
          <textarea
            v-model="commentInput"
            rows="3"
            placeholder="Napis komentar..."
          ></textarea>
          <button
            type="button"
            :disabled="commentSubmitting || !commentInput.trim()"
            @click="submitComment"
          >
            {{ commentSubmitting ? 'Odosielam...' : 'Pridat komentar' }}
          </button>
        </div>
        <p v-else class="comments__hint">Pre pridanie komentara sa prihlas.</p>

        <p v-if="commentsLoading" class="comments__hint">Nacitavam komentare...</p>

        <div v-else-if="comments.length" class="commentList">
          <article
            v-for="comment in comments"
            :key="comment.id"
            class="commentItem"
            :style="commentThreadStyle(comment)"
          >
            <div class="commentItem__card">
              <p class="commentItem__meta">
                <strong>{{ comment.user?.name || 'Pouzivatel' }}</strong>
                <span>{{ formatDate(comment.created_at) }}</span>
              </p>
              <p class="commentItem__text">{{ comment.content }}</p>
              <button
                v-if="auth.user && (auth.user.id === comment.user_id || auth.user.is_admin)"
                type="button"
                class="commentItem__remove"
                @click="removeComment(comment.id)"
              >
                Zmazat
              </button>
            </div>
          </article>
        </div>

        <p v-else class="comments__hint">Zatial bez komentarov.</p>

        <div v-if="commentsData && commentsData.last_page > 1" class="commentsPager">
          <button type="button" :disabled="commentsLoading || commentPage <= 1" @click="prevComments">
            Predosle
          </button>
          <p>Strana {{ commentsData.current_page }} z {{ commentsData.last_page }}</p>
          <button
            type="button"
            :disabled="commentsLoading || commentPage >= commentsData.last_page"
            @click="nextComments"
          >
            Dalsie
          </button>
        </div>
      </section>

      <section v-if="relatedLoading || related.length" class="section related">
        <div class="related__head">
          <h2>Podobne clanky</h2>
          <span v-if="relatedLoading">Nacitavam...</span>
        </div>

        <div v-if="related.length" class="related__list">
          <router-link
            v-for="item in related"
            :key="item.id"
            :to="postLink(item)"
            class="related__item"
          >
            <strong>{{ item.title }}</strong>
            <span>{{ formatDate(item.published_at) }}</span>
          </router-link>
        </div>
        <p v-else-if="!relatedLoading" class="related__empty">K tomuto clanku zatial nie su podobne temy.</p>
      </section>
    </template>
  </article>
</template>

<style scoped>
.detailPage {
  display: grid;
  gap: 1rem;
}

.backLink {
  width: fit-content;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: var(--color-text-secondary);
  font-size: 0.82rem;
}

.backLink::before {
  content: '<';
  opacity: 0.7;
}

.backLink:hover {
  color: var(--color-text-primary);
}

.header {
  display: grid;
  gap: 0.72rem;
  padding-bottom: 0.95rem;
  border-bottom: 1px solid var(--color-border);
}

.header h1 {
  margin: 0;
  font-size: clamp(1.35rem, 3.4vw, 2.05rem);
  line-height: 1.2;
}

.headerMeta {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem 0.62rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.78rem;
}

.headerMeta li {
  display: inline-flex;
  align-items: center;
}

.headerMeta li + li::before {
  content: '/';
  margin-right: 0.62rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.56);
}

.headerActions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
  flex-wrap: wrap;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.tags span {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  padding: 0.22rem 0.56rem;
  font-size: 0.72rem;
  color: var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.44);
}

.shareBtn,
.commentForm button,
.commentsPager button,
.commentItem__remove {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  min-height: 34px;
  background: rgb(var(--color-bg-rgb) / 0.44);
  color: var(--color-text-primary);
  padding: 0 0.75rem;
  font-size: 0.76rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color var(--motion-base), background-color var(--motion-base);
}

.shareBtn:hover,
.commentForm button:hover,
.commentsPager button:hover,
.commentItem__remove:hover {
  border-color: var(--color-border-strong);
  background: var(--interactive-hover);
}

.articleCard,
.section,
.state {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: rgb(var(--color-bg-rgb) / 0.46);
}

.articleCard {
  overflow: hidden;
}

.cover {
  margin: 0;
  border-bottom: 1px solid var(--color-border);
}

.cover img {
  width: 100%;
  max-height: 420px;
  display: block;
  object-fit: cover;
}

.articleBody {
  padding: clamp(0.85rem, 2vw, 1.25rem);
}

.detailPage--reading .header {
  gap: 0.62rem;
  padding-bottom: 0.82rem;
}

.detailPage--reading .articleBody {
  padding: clamp(1rem, 2.4vw, 1.6rem);
}

.toc {
  max-width: 68ch;
  margin: 0 auto 1.05rem;
  padding-left: 0.8rem;
  border-left: 2px solid rgb(var(--color-text-secondary-rgb) / 0.32);
}

.toc__title {
  margin: 0;
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.11em;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
}

.toc ul {
  margin: 0.55rem 0 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.34rem;
}

.toc li.h3 {
  padding-left: 0.65rem;
}

.toc a {
  color: var(--color-text-secondary);
  font-size: 0.84rem;
}

.toc a:hover {
  color: var(--color-primary);
}

.content {
  max-width: 68ch;
  margin: 0 auto;
  line-height: 1.78;
  color: rgb(var(--color-text-primary-rgb) / 0.94);
}

.detailPage--reading .toc,
.detailPage--reading .content {
  max-width: 74ch;
}

.detailPage--reading .content {
  line-height: 1.9;
}

.content h2,
.content h3 {
  margin: 1.2rem 0 0.5rem;
  line-height: 1.34;
}

.content h2 {
  font-size: 1.18rem;
}

.content h3 {
  font-size: 1rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.content p {
  margin: 0 0 0.82rem;
  font-size: 0.98rem;
}

.detailPage--reading .content p {
  margin-bottom: 0.95rem;
  font-size: 1.02rem;
}

.content ul {
  margin: 0 0 0.85rem;
  padding-left: 1.1rem;
}

.content li {
  margin: 0.3rem 0;
  font-size: 0.94rem;
}

.content a {
  color: var(--color-primary);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.content code {
  padding: 0.08rem 0.34rem;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  background: rgb(var(--color-bg-rgb) / 0.72);
  font-size: 0.86em;
}

.section,
.state {
  padding: 0.9rem;
}

.state {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.state--error {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  background: rgb(var(--color-danger-rgb) / 0.12);
  color: var(--color-text-primary);
}

.comments__head,
.related__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.65rem;
}

.comments__head h2,
.related__head h2 {
  margin: 0;
  font-size: 1.02rem;
}

.comments__head span,
.related__head span,
.comments__hint {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

.commentForm {
  display: grid;
  gap: 0.45rem;
  margin-bottom: 0.75rem;
}

.commentForm textarea {
  width: 100%;
  min-height: 92px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--color-bg-rgb) / 0.68);
  color: var(--color-text-primary);
  padding: 0.62rem 0.7rem;
  resize: vertical;
}

.commentForm button:disabled,
.commentsPager button:disabled {
  opacity: var(--interactive-disabled-opacity);
  cursor: not-allowed;
}

.commentList {
  display: grid;
  gap: 0.52rem;
}

.commentItem__card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--color-bg-rgb) / 0.58);
  padding: 0.62rem;
}

.commentItem__meta {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.73rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.commentItem__text {
  margin: 0.42rem 0 0.44rem;
  color: rgb(var(--color-text-primary-rgb) / 0.94);
  line-height: 1.56;
  font-size: 0.88rem;
}

.commentItem__remove {
  min-height: 30px;
  color: var(--color-danger);
  border-color: rgb(var(--color-danger-rgb) / 0.44);
  background: rgb(var(--color-danger-rgb) / 0.08);
}

.commentsPager {
  margin-top: 0.72rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.commentsPager p {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.75rem;
}

.related__list {
  display: grid;
  gap: 0.42rem;
}

.related__item {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--color-bg-rgb) / 0.48);
  padding: 0.6rem 0.7rem;
  color: var(--color-text-primary);
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  transition: border-color var(--motion-base), background-color var(--motion-base);
}

.related__item:hover {
  border-color: var(--color-border-strong);
  background: var(--interactive-hover);
}

.related__item strong {
  font-size: 0.89rem;
  line-height: 1.33;
}

.related__item span,
.related__empty {
  color: var(--color-text-secondary);
  font-size: 0.76rem;
}

.related__empty {
  margin: 0;
}

@media (max-width: 640px) {
  .detailPage {
    gap: 0.85rem;
  }

  .header {
    gap: 0.65rem;
    padding-bottom: 0.8rem;
  }

  .section,
  .state {
    padding: 0.76rem;
  }

  .articleBody {
    padding: 0.8rem;
  }

  .detailPage--reading .articleBody {
    padding: 0.9rem;
  }

  .detailPage--reading .content p {
    font-size: 1rem;
  }
}
</style>
