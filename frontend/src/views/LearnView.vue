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

<template src="./learn/LearnView.template.html"></template>

<style scoped src="./learn/LearnView.css"></style>

