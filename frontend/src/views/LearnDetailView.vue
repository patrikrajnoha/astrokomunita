<script setup>
import { computed, onMounted, ref, watch } from 'vue'
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

const tocItems = computed(() => renderedArticle.value.toc)

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

<template src="./learnDetail/LearnDetailView.template.html"></template>

<style scoped src="./learnDetail/LearnDetailView.css"></style>
