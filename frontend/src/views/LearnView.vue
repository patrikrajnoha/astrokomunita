<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { blogPosts } from '@/services/blogPosts'

const loading = ref(false)
const error = ref('')
const data = ref(null)
const page = ref(1)
const tags = ref([])
const selectedTag = ref('')
const search = ref('')
const searchInput = ref('')

const totalResults = computed(() => Number(data.value?.total || 0))

const featuredPost = computed(() => {
  if (!data.value || page.value !== 1) return null
  return data.value.data?.[0] || null
})

const listPosts = computed(() => {
  if (!data.value) return []
  if (page.value === 1) return data.value.data?.slice(1) || []
  return data.value.data || []
})

const hasAnyPosts = computed(() => {
  return Boolean((data.value?.data || []).length)
})

const resultLabel = computed(() => {
  const total = totalResults.value
  if (total === 0) return 'Ziadne vysledky'
  if (total === 1) return '1 clanok'
  if (total < 5) return `${total} clanky`
  return `${total} clankov`
})

function setMeta({ title, description }) {
  if (typeof document === 'undefined') return

  document.title = title

  const ensure = (name, property) => {
    let tag = document.querySelector(`meta[${property ? 'property' : 'name'}='${name}']`)
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
}

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('sk-SK', { dateStyle: 'long' })
}

function excerpt(text, limit = 180) {
  if (!text) return ''
  const cleaned = stripHtml(String(text)).replace(/\s+/g, ' ').trim()
  if (cleaned.length <= limit) return cleaned
  return `${cleaned.slice(0, limit).trim()}...`
}

function readTime(text) {
  if (!text) return '1 min citania'
  const words = stripHtml(String(text)).trim().split(/\s+/).filter(Boolean).length
  const minutes = Math.max(1, Math.round(words / 220))
  return `${minutes} min citania`
}

function postLink(post) {
  return `/clanky/${post.slug || post.id}`
}

function stripHtml(text) {
  return String(text).replace(/<[^>]*>/g, ' ')
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function highlight(text) {
  if (!text) return ''
  const escaped = escapeHtml(text)
  if (!search.value) return escaped
  const safe = search.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const re = new RegExp(`(${safe})`, 'gi')
  return escaped.replace(re, '<mark>$1</mark>')
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    data.value = await blogPosts.listPublic({
      page: page.value,
      tag: selectedTag.value || undefined,
      q: search.value || undefined,
    })
  } catch (e) {
    error.value = e?.response?.data?.message || 'Chyba pri nacitani clankov.'
  } finally {
    loading.value = false
  }
}

async function loadTags() {
  try {
    tags.value = await blogPosts.listTagsPublic()
  } catch {
    tags.value = []
  }
}

function selectTag(slug) {
  selectedTag.value = slug
  page.value = 1
  load()
}

function applySearch() {
  search.value = searchInput.value.trim()
  page.value = 1
  load()
}

function clearSearch() {
  selectedTag.value = ''
  search.value = ''
  searchInput.value = ''
  page.value = 1
  load()
}

function prevPage() {
  if (!data.value || page.value <= 1) return
  page.value -= 1
  load()
}

function nextPage() {
  if (!data.value || page.value >= data.value.last_page) return
  page.value += 1
  load()
}

watch(
  () => [selectedTag.value, search.value],
  () => {
    const tagLabel = selectedTag.value
      ? ` - ${tags.value.find((t) => t.slug === selectedTag.value)?.name || 'Tag'}`
      : ''
    const searchLabel = search.value ? ` - Hladanie: ${search.value}` : ''

    setMeta({
      title: `Vzdelavanie${tagLabel}${searchLabel} | Astrokomunita`,
      description: 'Miesto s clankami o astronomii, pozorovani a nocnej oblohe.',
    })
  },
  { immediate: true },
)

onMounted(() => {
  load()
  loadTags()
})
</script>

<template>
  <section class="learnPage">
    <header class="hero">
      <p class="hero__eyebrow">Clanky</p>
      <h1 class="hero__title">Vzdelavanie o nocnej oblohe</h1>
      <p class="hero__text">
        Prakticke temy, kratke vysvetlenia a citatelny obsah bez balastu.
      </p>
    </header>

    <section class="panel">
      <form class="search" @submit.prevent="applySearch">
        <input
          v-model="searchInput"
          type="text"
          placeholder="Hladat temu alebo klucove slovo"
          aria-label="Hladat v clankoch"
        />
        <button type="submit">Hladat</button>
      </form>

      <div v-if="tags.length" class="tags">
        <button
          class="tag"
          :class="{ 'is-active': !selectedTag }"
          type="button"
          @click="selectTag('')"
        >
          Vsetko
        </button>
        <button
          v-for="tag in tags"
          :key="tag.id"
          class="tag"
          :class="{ 'is-active': selectedTag === tag.slug }"
          type="button"
          :aria-pressed="selectedTag === tag.slug ? 'true' : 'false'"
          @click="selectTag(tag.slug)"
        >
          {{ tag.name }}
        </button>
      </div>

      <div class="resultBar">
        <p>{{ resultLabel }}</p>
        <button
          v-if="selectedTag || search"
          type="button"
          class="clearBtn"
          @click="clearSearch"
        >
          Zrusit filtre
        </button>
      </div>

      <div v-if="error" class="state state--error">{{ error }}</div>

      <div v-if="loading" class="loadingRows">
        <div v-for="row in 5" :key="`learn-loading-${row}`" class="loadingRows__item"></div>
      </div>

      <template v-else>
        <article v-if="featuredPost" class="featured">
          <router-link class="featured__media" :to="postLink(featuredPost)">
            <img
              v-if="featuredPost.cover_image_url"
              :src="featuredPost.cover_image_url"
              :alt="featuredPost.title"
              loading="lazy"
            />
            <span v-else>Odporucany clanok</span>
          </router-link>
          <div class="featured__body">
            <p class="featured__label">Odporucane</p>
            <h2>
              <router-link :to="postLink(featuredPost)">{{ featuredPost.title }}</router-link>
            </h2>
            <p class="featured__excerpt" v-html="highlight(excerpt(featuredPost.content, 220))"></p>
            <p class="featured__meta">
              {{ formatDate(featuredPost.published_at) }} ·
              {{ featuredPost.user?.name || 'Redakcia' }} ·
              {{ readTime(featuredPost.content) }}
            </p>
          </div>
        </article>

        <div v-if="listPosts.length" class="postList">
          <article v-for="post in listPosts" :key="post.id" class="postItem">
            <router-link v-if="post.cover_image_url" class="postItem__thumb" :to="postLink(post)">
              <img :src="post.cover_image_url" :alt="post.title" loading="lazy" />
            </router-link>
            <div class="postItem__body">
              <h3>
                <router-link :to="postLink(post)">{{ post.title }}</router-link>
              </h3>
              <p class="postItem__excerpt" v-html="highlight(excerpt(post.content, 170))"></p>
              <div class="postItem__meta">
                <span>{{ formatDate(post.published_at) }}</span>
                <span>·</span>
                <span>{{ readTime(post.content) }}</span>
              </div>
            </div>
            <router-link class="postItem__open" :to="postLink(post)">Citat</router-link>
          </article>
        </div>

        <div v-if="!hasAnyPosts" class="state">
          <p class="state__title">Zatial bez vysledkov</p>
          <p class="state__text">Skus iny vyraz alebo zrus filter tagu.</p>
        </div>
      </template>

      <div v-if="data && data.last_page > 1" class="pager">
        <p>Strana {{ data.current_page }} z {{ data.last_page }}</p>
        <div class="pager__actions">
          <button type="button" :disabled="loading || page <= 1" @click="prevPage">Predosla</button>
          <button
            type="button"
            :disabled="loading || page >= data.last_page"
            @click="nextPage"
          >
            Dalsia
          </button>
        </div>
      </div>
    </section>
  </section>
</template>

<style scoped>
.learnPage {
  display: grid;
  gap: 0.9rem;
}

.hero {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  padding: 1rem 1.1rem;
  background:
    radial-gradient(circle at 12% 0%, rgb(var(--color-primary-rgb) / 0.2), transparent 38%),
    rgb(var(--color-bg-rgb) / 0.8);
}

.hero__eyebrow {
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  font-size: 0.68rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.hero__title {
  margin: 0.25rem 0 0;
  font-size: clamp(1.3rem, 3.4vw, 1.75rem);
}

.hero__text {
  margin: 0.45rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
  font-size: 0.89rem;
  line-height: 1.45;
}

.panel {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  background: rgb(var(--color-bg-rgb) / 0.45);
  padding: 0.9rem;
  display: grid;
  gap: 0.7rem;
}

.search {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.45rem;
}

.search input {
  min-height: 38px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--color-bg-rgb) / 0.72);
  color: var(--color-text-primary);
  padding: 0.5rem 0.75rem;
  font-size: 0.9rem;
}

.search button,
.clearBtn,
.pager button,
.postItem__open {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  min-height: 36px;
  background: rgb(var(--color-bg-rgb) / 0.62);
  color: var(--color-text-primary);
  padding: 0 0.8rem;
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: border-color var(--motion-base), background-color var(--motion-base);
}

.search button:hover,
.clearBtn:hover,
.pager button:hover,
.postItem__open:hover {
  border-color: var(--color-border-strong);
  background: var(--interactive-hover);
}

.search button:disabled,
.clearBtn:disabled,
.pager button:disabled {
  opacity: var(--interactive-disabled-opacity);
  cursor: not-allowed;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.tag {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--color-text-secondary);
  padding: 0.25rem 0.62rem;
  font-size: 0.74rem;
  font-weight: 600;
  cursor: pointer;
}

.tag.is-active {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: var(--color-text-primary);
}

.resultBar {
  border-top: 1px solid var(--divider-color);
  padding-top: 0.58rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

.resultBar p {
  margin: 0;
}

.clearBtn {
  min-height: 30px;
  font-size: 0.72rem;
  padding-inline: 0.6rem;
}

.state {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: rgb(var(--color-bg-rgb) / 0.45);
  padding: 0.9rem;
}

.state--error {
  border-color: rgb(var(--color-danger-rgb) / 0.45);
  background: rgb(var(--color-danger-rgb) / 0.1);
}

.state__title {
  margin: 0;
  font-weight: 700;
  color: var(--color-text-primary);
}

.state__text {
  margin: 0.25rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.83rem;
}

.loadingRows {
  display: grid;
  gap: 0.5rem;
}

.loadingRows__item {
  height: 3.25rem;
  border-radius: var(--radius-md);
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.1),
    rgb(var(--color-text-secondary-rgb) / 0.18),
    rgb(var(--color-text-secondary-rgb) / 0.1)
  );
  background-size: 220% 100%;
  animation: learn-shimmer 1.2s linear infinite;
}

.featured {
  display: grid;
  grid-template-columns: minmax(0, 132px) minmax(0, 1fr);
  gap: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: rgb(var(--color-bg-rgb) / 0.56);
  padding: 0.6rem;
}

.featured__media {
  border-radius: var(--radius-md);
  overflow: hidden;
  border: 1px solid var(--color-border);
  min-height: 100%;
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  display: grid;
  place-items: center;
}

.featured__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.featured__body {
  display: grid;
  gap: 0.35rem;
  min-width: 0;
}

.featured__label {
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-size: 0.66rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}

.featured h2 {
  margin: 0;
  font-size: 1.16rem;
  line-height: 1.22;
}

.featured h2 a,
.postItem h3 a {
  color: var(--color-text-primary);
  text-decoration: none;
}

.featured h2 a:hover,
.postItem h3 a:hover {
  color: var(--color-primary);
}

.featured__excerpt,
.postItem__excerpt {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.93);
  font-size: 0.83rem;
  line-height: 1.46;
}

.featured__meta,
.postItem__meta {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
  font-size: 0.72rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.postList {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.postItem {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 0.65rem;
  align-items: start;
  padding: 0.72rem;
  background: rgb(var(--color-bg-rgb) / 0.56);
}

.postItem + .postItem {
  border-top: 1px solid var(--divider-color);
}

.postItem__thumb {
  width: 84px;
  height: 64px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.postItem__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.postItem__body {
  min-width: 0;
  display: grid;
  gap: 0.28rem;
}

.postItem h3 {
  margin: 0;
  font-size: 0.97rem;
  line-height: 1.28;
}

.postItem__open {
  min-height: 32px;
  align-self: center;
  font-size: 0.72rem;
  padding-inline: 0.62rem;
}

.pager {
  border-top: 1px solid var(--divider-color);
  padding-top: 0.62rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

.pager p {
  margin: 0;
}

.pager__actions {
  display: flex;
  gap: 0.45rem;
}

mark {
  background: rgb(var(--color-primary-rgb) / 0.3);
  color: inherit;
  border-radius: 4px;
  padding: 0 0.2em;
}

@media (max-width: 620px) {
  .featured {
    grid-template-columns: 1fr;
  }

  .featured__media {
    min-height: 160px;
  }

  .postItem {
    grid-template-columns: minmax(0, 1fr);
  }

  .postItem__thumb {
    width: 100%;
    height: 138px;
  }

  .postItem__open {
    justify-self: flex-start;
  }
}

@keyframes learn-shimmer {
  from {
    background-position: 200% 0;
  }

  to {
    background-position: -200% 0;
  }
}
</style>

