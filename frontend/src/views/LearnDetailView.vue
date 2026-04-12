<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { blogPosts } from '@/services/blogPosts'
import { blogComments } from '@/services/blogComments'
import { useAuthStore } from '@/stores/auth'
import { renderArticleContent, stripHtml } from '@/utils/articleContent'

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

  const pageUrl = typeof window !== 'undefined' ? window.location.href : ''

  ensure('description', false).setAttribute('content', description)
  ensure('og:title', true).setAttribute('content', title)
  ensure('og:description', true).setAttribute('content', description)
  ensure('og:url', true).setAttribute('content', pageUrl)
  ensure('og:type', true).setAttribute('content', 'article')
  ensure('og:site_name', true).setAttribute('content', 'Astrokomunita')

  if (image) {
    ensure('og:image', true).setAttribute('content', image)
  }

  ensure('twitter:card', false).setAttribute('content', 'summary_large_image')
  ensure('twitter:title', false).setAttribute('content', title)
  ensure('twitter:description', false).setAttribute('content', description)
  if (image) {
    ensure('twitter:image', false).setAttribute('content', image)
  }

  let canonical = document.querySelector('link[rel="canonical"]')
  if (!canonical) {
    canonical = document.createElement('link')
    canonical.setAttribute('rel', 'canonical')
    document.head.appendChild(canonical)
  }
  canonical.setAttribute('href', pageUrl)
}

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString('sk-SK', { dateStyle: 'long' })
}

function excerpt(text, limit = 180) {
  const clean = stripHtml(text).replace(/\s+/g, ' ').trim()
  if (!clean) return ''
  if (clean.length <= limit) return clean
  return `${clean.slice(0, limit).trim()}...`
}

const renderedArticle = computed(() => renderArticleContent(post.value?.content || ''))
const contentHtml = computed(() => renderedArticle.value.html)

const articleWordCount = computed(() => {
  const clean = String(renderedArticle.value.plainText || '').trim()
  if (!clean) return 0
  return clean.split(/\s+/).filter(Boolean).length
})

const readTime = computed(() => {
  const words = articleWordCount.value
  if (!words) return '1 min čítania'
  const minutes = Math.max(1, Math.round(words / 220))
  return `${minutes} min čítania`
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
    parts.push(`${post.value.views} zobrazení`)
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

const tocItems = computed(() => renderedArticle.value.toc)

const showToc = computed(() => {
  if (tocItems.value.length === 0) return false
  if (isLongRead.value) return tocItems.value.length >= 2
  return tocItems.value.length >= 3
})

function postLink(value) {
  return `/articles/${value.slug || value.id}`
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
    commentsError.value = e?.response?.data?.message || 'Komentáre sa nepodarilo načítať.'
  } finally {
    commentsLoading.value = false
  }
}

async function load() {
  if (!slug.value) {
    error.value = 'Neplatný článok.'
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

    const metaTitle = `${payload?.title || 'Článok'} | Astrokomunita`
    const metaDesc = excerpt(payload?.content || '', 160) || 'Článok o astronómii a pozorovaní oblohy.'

    setMeta({
      title: metaTitle,
      description: metaDesc,
      image: payload?.cover_image_url || null,
    })

    // schema.org/Article — enables Google rich results for blog posts
    if (typeof document !== 'undefined') {
      const id = 'page-json-ld'
      let ld = document.getElementById(id)
      if (!ld) {
        ld = document.createElement('script')
        ld.id = id
        ld.type = 'application/ld+json'
        document.head.appendChild(ld)
      }
      ld.textContent = JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: payload?.title || 'Článok',
        description: metaDesc,
        datePublished: payload?.published_at ?? undefined,
        image: payload?.cover_image_url ?? undefined,
        url: typeof window !== 'undefined' ? window.location.href : undefined,
        author: {
          '@type': 'Person',
          name: payload?.user?.name || 'Redakcia',
        },
        publisher: {
          '@type': 'Organization',
          name: 'Astrokomunita',
          url: 'https://astrokomunita.sk',
        },
      })
    }

    loadComments()
    loadRelated()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Článok sa nepodarilo načítať.'
  } finally {
    loading.value = false
  }
}

async function submitComment() {
  if (!auth.isAuthed) {
    commentsError.value = 'Pre pridanie komentára sa prihlás.'
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
    commentsError.value = e?.response?.data?.message || 'Komentár sa nepodarilo odoslať.'
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
    commentsError.value = e?.response?.data?.message || 'Komentár sa nepodarilo zmazať.'
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

onBeforeUnmount(() => {
  const ld = document.getElementById('page-json-ld')
  if (ld) ld.remove()
})

watch(
  () => route.params.slug,
  () => {
    load()
  },
)
</script>

<template src="./learnDetail/LearnDetailView.template.html"></template>

<style scoped src="./learnDetail/LearnDetailView.css"></style>
