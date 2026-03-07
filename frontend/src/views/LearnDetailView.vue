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

const readTime = computed(() => {
  const clean = stripHtml(post.value?.content || '').trim()
  if (!clean) return '1 min citania'
  const words = clean.split(/\s+/).filter(Boolean).length
  const minutes = Math.max(1, Math.round(words / 220))
  return `${minutes} min citania`
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
  <article class="detailPage">
    <router-link class="backLink" to="/clanky">‹ Spat na clanky</router-link>

    <div v-if="error" class="state state--error">{{ error }}</div>
    <div v-else-if="loading" class="state">Nacitavam clanok...</div>

    <template v-else-if="post">
      <header class="hero">
        <p class="hero__eyebrow">Clanok</p>
        <h1>{{ post.title }}</h1>
        <p class="hero__summary">{{ excerpt(post.content, 210) }}</p>

        <p class="hero__meta">
          {{ formatDate(post.published_at) }} ·
          {{ post.user?.name || 'Redakcia' }} ·
          {{ readTime }}
          <template v-if="typeof post.views === 'number'"> · {{ post.views }} zobrazeni</template>
        </p>

        <div v-if="post.tags?.length" class="hero__tags">
          <span v-for="tag in post.tags" :key="tag.id">{{ tag.name }}</span>
        </div>

        <button class="hero__share" type="button" @click="copyLink">
          {{ copied ? 'Link skopirovany' : 'Kopirovat link' }}
        </button>
      </header>

      <figure v-if="post.cover_image_url" class="cover">
        <img :src="post.cover_image_url" :alt="post.title" loading="lazy" />
      </figure>

      <section v-if="tocItems.length" class="toc">
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

      <section class="comments">
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
            <span v-if="commentDepth(comment) > 0" class="commentItem__line"></span>
            <div class="commentItem__card">
              <p class="commentItem__meta">
                <strong>{{ comment.user?.name || 'Pouzivatel' }}</strong>
                <span>·</span>
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

      <section v-if="relatedLoading || related.length" class="related">
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
  gap: 0.85rem;
}

.backLink {
  color: var(--color-text-secondary);
  font-size: 0.82rem;
  width: fit-content;
}

.backLink:hover {
  color: var(--color-text-primary);
}

.hero,
.cover,
.content,
.toc,
.comments,
.related,
.state {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: rgb(var(--color-bg-rgb) / 0.56);
}

.hero {
  padding: 0.95rem;
  display: grid;
  gap: 0.45rem;
  background:
    radial-gradient(circle at 12% 0%, rgb(var(--color-primary-rgb) / 0.17), transparent 38%),
    rgb(var(--color-bg-rgb) / 0.72);
}

.hero__eyebrow {
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  font-size: 0.66rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
}

.hero h1 {
  margin: 0;
  font-size: clamp(1.3rem, 3.5vw, 1.9rem);
  line-height: 1.2;
}

.hero__summary {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.9rem;
  line-height: 1.5;
}

.hero__meta {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
  font-size: 0.76rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.26rem;
  align-items: center;
}

.hero__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.36rem;
}

.hero__tags span {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  padding: 0.22rem 0.56rem;
  font-size: 0.7rem;
  color: var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.58);
}

.hero__share,
.commentForm button,
.commentsPager button,
.commentItem__remove {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  min-height: 34px;
  background: rgb(var(--color-bg-rgb) / 0.64);
  color: var(--color-text-primary);
  padding: 0 0.75rem;
  font-size: 0.76rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color var(--motion-base), background-color var(--motion-base);
}

.hero__share {
  width: fit-content;
}

.hero__share:hover,
.commentForm button:hover,
.commentsPager button:hover,
.commentItem__remove:hover {
  border-color: var(--color-border-strong);
  background: var(--interactive-hover);
}

.cover {
  overflow: hidden;
}

.cover img {
  width: 100%;
  max-height: 360px;
  display: block;
  object-fit: cover;
}

.toc {
  padding: 0.85rem;
}

.toc__title {
  margin: 0;
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
}

.toc ul {
  margin: 0.55rem 0 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.35rem;
}

.toc li {
  margin: 0;
}

.toc li.h3 {
  padding-left: 0.7rem;
}

.toc a {
  color: var(--color-text-secondary);
  font-size: 0.85rem;
}

.toc a:hover {
  color: var(--color-primary);
}

.content {
  padding: 0.95rem;
  line-height: 1.72;
  color: rgb(var(--color-text-primary-rgb) / 0.92);
}

.content h2,
.content h3 {
  margin: 1.1rem 0 0.5rem;
  line-height: 1.35;
}

.content h2 {
  font-size: 1.2rem;
}

.content h3 {
  font-size: 1rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.content p {
  margin: 0 0 0.78rem;
  font-size: 0.95rem;
}

.content ul {
  margin: 0 0 0.85rem;
  padding-left: 1.1rem;
}

.content li {
  margin: 0.32rem 0;
  font-size: 0.93rem;
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

.comments,
.related,
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
  font-size: 1.05rem;
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
  background: rgb(var(--color-bg-rgb) / 0.7);
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
  gap: 0.48rem;
}

.commentItem {
  position: relative;
}

.commentItem__line {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 1px;
  background: rgb(var(--color-text-secondary-rgb) / 0.4);
}

.commentItem__card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--color-bg-rgb) / 0.64);
  padding: 0.62rem;
}

.commentItem__meta {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.commentItem__text {
  margin: 0.38rem 0 0.44rem;
  color: rgb(var(--color-text-primary-rgb) / 0.94);
  line-height: 1.52;
  font-size: 0.87rem;
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
  background: rgb(var(--color-bg-rgb) / 0.62);
  padding: 0.56rem 0.66rem;
  color: var(--color-text-primary);
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
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

@media (max-width: 540px) {
  .content {
    padding: 0.8rem;
  }

  .hero,
  .comments,
  .related,
  .state,
  .toc {
    padding: 0.75rem;
  }
}
</style>


