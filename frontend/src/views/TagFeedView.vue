<template src="./tagFeed/TagFeedView.template.html"></template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserAvatar from '@/components/UserAvatar.vue'
import HashtagText from '@/components/HashtagText.vue'
import PollCard from '@/components/PollCard.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const tag = computed(() => {
  const raw = route.params.tag
  const value = Array.isArray(raw) ? raw[0] : raw
  return String(value || '').trim().toLowerCase()
})

const items = ref([])
const nextPageUrl = ref(null)
const loading = ref(false)
const error = ref('')
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const requestSequence = ref(0)
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const reportLoading = ref(false)

function resetFeedState() {
  items.value = []
  nextPageUrl.value = null
  error.value = ''
  loading.value = false
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function openProfile(post) {
  const username = post?.user?.username
  if (!username) return
  router.push(`/u/${username}`)
}

function isLikeLoading(post) {
  return likeLoadingIds.value.has(post?.id)
}

function setLikeLoading(id, on) {
  const next = new Set(likeLoadingIds.value)
  if (on) next.add(id)
  else next.delete(id)
  likeLoadingIds.value = next
}

function closeReport(force = false) {
  if (reportLoading.value && !force) return
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

function openReport(post) {
  if (!post?.id) return
  if (!auth.isAuthed) {
    const message = 'Prihlas sa pre nahlasenie prispevku.'
    error.value = message
    toast.warn(message)
    return
  }

  error.value = ''
  reportTarget.value = post
  reportReason.value = 'spam'
  reportMessage.value = ''
}

async function submitReport() {
  const post = reportTarget.value
  if (!post?.id || reportLoading.value) return

  reportLoading.value = true
  error.value = ''

  try {
    await api.post('/reports', {
      target_id: post.id,
      reason: reportReason.value,
      message: reportMessage.value.trim() || null,
      _hp: '',
    })

    toast.success('Nahlasenie bolo odoslane. Dakujeme.')
    closeReport(true)
  } catch (e) {
    const status = e?.response?.status
    const message =
      status === 401
        ? 'Prihlas sa.'
        : status === 403
          ? 'Svoj vlastny prispevok nemozes nahlasit.'
          : status === 409
            ? 'Tento prispevok ste uz nahlasili.'
            : e?.response?.data?.message || 'Nahlasenie sa nepodarilo odoslat.'

    error.value = message
    toast.warn(message)
  } finally {
    reportLoading.value = false
  }
}

function updatePostPoll(post, nextPoll) {
  if (!post || !nextPoll) return
  post.poll = nextPoll
}

function onPollLoginRequired() {
  error.value = 'Prihlas sa pre hlasovanie.'
}

function bumpLike(id) {
  likeBumpId.value = id
  window.setTimeout(() => {
    if (likeBumpId.value === id) likeBumpId.value = null
  }, 220)
}

function applyLikeResponse(post, res) {
  const data = res?.data
  if (!data || !post) return
  if (data.likes_count !== undefined) post.likes_count = data.likes_count
  if (data.liked_by_me !== undefined) post.liked_by_me = data.liked_by_me
}

async function toggleLike(post) {
  if (!post?.id || isLikeLoading(post)) return
  if (!auth.isAuthed) {
    error.value = 'Prihlas sa pre lajkovanie.'
    return
  }

  error.value = ''
  const prevLiked = !!post.liked_by_me
  const prevCount = Number(post.likes_count ?? 0) || 0

  post.liked_by_me = !prevLiked
  post.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(post.id)
  setLikeLoading(post.id, true)

  try {
    const res = prevLiked
      ? await api.delete(`/posts/${post.id}/like`)
      : await api.post(`/posts/${post.id}/like`)
    applyLikeResponse(post, res)
  } catch (e) {
    post.liked_by_me = prevLiked
    post.likes_count = prevCount
    const status = e?.response?.status
    if (status === 401) error.value = 'Prihlas sa.'
    else error.value = e?.response?.data?.message || 'Lajk zlyhal.'
  } finally {
    setLikeLoading(post.id, false)
  }
}

function fmt(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return String(iso)
  }
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

function normalizePaginationUrl(url) {
  const raw = String(url || '').trim()
  if (!raw) return ''

  const baseOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost'
  const apiPrefix = String(api?.defaults?.baseURL || '/api').replace(/\/+$/, '')

  try {
    const parsed = new URL(raw, baseOrigin)
    const pathWithQuery = `${parsed.pathname}${parsed.search || ''}`
    if (apiPrefix && pathWithQuery.startsWith(`${apiPrefix}/`)) {
      return pathWithQuery.slice(apiPrefix.length)
    }
    if (apiPrefix && pathWithQuery === apiPrefix) {
      return '/'
    }
    return pathWithQuery
  } catch {
    return raw
  }
}

async function load(reset = true, force = false) {
  if (loading.value && !force) return

  const activeTag = tag.value
  if (reset && !activeTag) {
    resetFeedState()
    error.value = 'Tag nie je zadany.'
    return
  }

  const requestId = ++requestSequence.value
  loading.value = true
  if (reset) {
    error.value = ''
  }

  try {
    let res = null

    if (reset) {
      res = await api.get('/posts', {
        params: {
          tag: activeTag,
          with: 'counts',
        },
      })
    } else {
      const url = normalizePaginationUrl(nextPageUrl.value)
      if (!url) {
        return
      }
      res = await api.get(url)
    }

    if (requestId !== requestSequence.value) {
      return
    }

    const payload = res?.data || {}
    const rows = Array.isArray(payload.data) ? payload.data : []

    if (reset) items.value = rows
    else items.value = [...items.value, ...rows]

    nextPageUrl.value = payload.next_page_url || null
  } catch (e) {
    if (requestId !== requestSequence.value) {
      return
    }

    const message = e?.response?.data?.message || e?.message || 'Nacitanie feedu zlyhalo.'
    if (reset) {
      error.value = message
    } else {
      toast.warn(message)
    }
  } finally {
    if (requestId === requestSequence.value) {
      loading.value = false
    }
  }
}

watch(tag, () => {
  requestSequence.value += 1
  closeReport(true)
  resetFeedState()
  void load(true, true)
}, { immediate: true })
</script>

<style scoped src="./tagFeed/TagFeedView.css"></style>
