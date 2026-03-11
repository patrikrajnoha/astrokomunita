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

<template src="./learnDetail/LearnDetailView.template.html"></template>

<style scoped src="./learnDetail/LearnDetailView.css"></style>
