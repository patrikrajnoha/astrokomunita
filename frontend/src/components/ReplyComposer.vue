<template src="./replyComposer/ReplyComposer.template.html"></template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import UserAvatar from '@/components/UserAvatar.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['created'])
const GIF_MIN_QUERY_LENGTH = 2

const props = defineProps({
  parentId: { type: [Number, String], required: true },

  // Keep aligned with backend constraints (max 5MB + mimes)
  accept: { type: String, default: 'image/*,.pdf,.txt,.doc,.docx' },
  maxBytes: { type: Number, default: 5 * 1024 * 1024 },
  compact: { type: Boolean, default: false },
  autofocus: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Napis reply...' },
})

const auth = useAuthStore()
const EMOJI_SET = ['😀', '😊', '😂', '😍', '🤝', '👍', '🎉', '✨', '🔥', '🚀']

const content = ref('')
const file = ref(null)
const imagePreviewUrl = ref(null)
const selectedGif = ref(null)
const showGifModal = ref(false)
const gifQuery = ref('')
const gifResults = ref([])
const gifLoading = ref(false)
const gifError = ref('')
const gifDebounceTimer = ref(null)
const posting = ref(false)
const err = ref('')
const showEmojiMenu = ref(false)
const activePickerAccept = ref('image/*')

const fileInput = ref(null)
const textareaEl = ref(null)
const emojiWrapRef = ref(null)
const gifInputRef = ref(null)

const remaining = computed(() => 2000 - content.value.length)

const isSubmitDisabled = computed(() => {
  if (!auth.isAuthed) return true
  if (posting.value) return true
  if (!content.value.trim()) return true
  if (content.value.length > 2000) return true
  return false
})

function autoGrow() {
  const el = textareaEl.value
  if (!el) return

  const min = props.compact ? 72 : 92
  el.style.height = 'auto'
  el.style.height = `${Math.max(min, el.scrollHeight)}px`
}

function onTyping() {
  if (err.value && content.value.length <= 2000) err.value = ''
  autoGrow()
}

function onTextareaKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    e.preventDefault()
    if (!isSubmitDisabled.value) {
      void submit()
    }
  }
}

function focusInput({ scroll = true } = {}) {
  nextTick(() => {
    const el = textareaEl.value
    if (!el) return

    if (scroll) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }

    el.focus()
    const len = el.value.length
    el.setSelectionRange(len, len)
    autoGrow()
  })
}

function pickImage() {
  activePickerAccept.value = 'image/*'
  fileInput.value?.click()
}

function pickGif() {
  showEmojiMenu.value = false
  openGifModal()
}

function toggleEmojiMenu() {
  showEmojiMenu.value = !showEmojiMenu.value
}

function insertEmoji(emoji) {
  const normalized = String(emoji || '')
  if (!normalized) return

  const el = textareaEl.value
  const start = Number(el?.selectionStart ?? content.value.length)
  const end = Number(el?.selectionEnd ?? content.value.length)

  content.value = `${content.value.slice(0, start)}${normalized}${content.value.slice(end)}`
  showEmojiMenu.value = false

  nextTick(() => {
    focusInput({ scroll: false })
    if (!textareaEl.value) return
    const nextPosition = start + normalized.length
    textareaEl.value.setSelectionRange(nextPosition, nextPosition)
  })
}

function onPointerDown(event) {
  if (!showEmojiMenu.value) return
  const target = event?.target
  if (!emojiWrapRef.value || emojiWrapRef.value.contains(target)) return
  showEmojiMenu.value = false
}

function openGifModal() {
  showGifModal.value = true
  gifError.value = ''
  gifQuery.value = ''
  gifResults.value = []
  nextTick(() => gifInputRef.value?.focus())
}

function closeGifModal() {
  showGifModal.value = false
  gifQuery.value = ''
  gifResults.value = []
  gifError.value = ''
  if (gifDebounceTimer.value) {
    clearTimeout(gifDebounceTimer.value)
    gifDebounceTimer.value = null
  }
}

function onGifQueryChange() {
  gifError.value = ''
  if (gifDebounceTimer.value) {
    clearTimeout(gifDebounceTimer.value)
  }

  const query = gifQuery.value.trim()
  if (query.length < GIF_MIN_QUERY_LENGTH) {
    gifLoading.value = false
    gifResults.value = []
    return
  }

  gifDebounceTimer.value = setTimeout(() => {
    void fetchGifResults(query)
  }, 450)
}

async function fetchGifResults(query) {
  gifLoading.value = true
  gifError.value = ''
  try {
    const response = await api.get('/integrations/gifs/search', {
      params: { q: query, limit: 18, offset: 0 },
      meta: { skipErrorToast: true },
    })
    gifResults.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    gifError.value = status === 429
      ? 'GIF vyhladavanie je docasne pretazene. Skus neskor.'
      : (e?.response?.data?.message || 'GIF vyhladavanie zlyhalo.')
  } finally {
    gifLoading.value = false
  }
}

function selectGif(gif) {
  if (!gif) return
  removeFile()
  selectedGif.value = {
    id: String(gif.id || ''),
    title: String(gif.title || ''),
    preview_url: String(gif.preview_url || ''),
    original_url: String(gif.original_url || ''),
    width: Number(gif.width || 0) || null,
    height: Number(gif.height || 0) || null,
  }
  closeGifModal()
}

function removeGif() {
  selectedGif.value = null
}

function revokePreview() {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
    imagePreviewUrl.value = null
  }
}

function removeFile() {
  revokePreview()
  file.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function isImageFile(f) {
  return typeof f?.type === 'string' && f.type.startsWith('image/')
}

function isGifFile(f) {
  const mime = String(f?.type || '').toLowerCase()
  if (mime === 'image/gif') return true
  const name = String(f?.name || '').toLowerCase()
  return name.endsWith('.gif')
}

function isAllowedByMvp(f) {
  const name = (f?.name || '').toLowerCase()
  return (
    isImageFile(f) ||
    name.endsWith('.pdf') ||
    name.endsWith('.txt') ||
    name.endsWith('.doc') ||
    name.endsWith('.docx')
  )
}

function onFileChange(e) {
  err.value = ''
  const f = e?.target?.files?.[0] || null
  if (!f) {
    return
  }

  if (!isImageFile(f)) {
    removeFile()
    err.value = 'Vyber obrazok.'
    return
  }

  if (f.size > props.maxBytes) {
    removeFile()
    err.value = `Subor je prilis velky. Max ${prettySize(props.maxBytes)}.`
    return
  }

  if (!isAllowedByMvp(f)) {
    removeFile()
    err.value = 'Nepovoleny typ suboru.'
    return
  }

  if (selectedGif.value) removeGif()
  file.value = f
  revokePreview()
  if (isImageFile(f)) {
    imagePreviewUrl.value = URL.createObjectURL(f)
  }
}

function prettySize(bytes) {
  const b = Number(bytes || 0)
  if (b < 1024) return `${b} B`
  const kb = b / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  const mb = kb / 1024
  return `${mb.toFixed(1)} MB`
}

async function submit() {
  if (!auth.isAuthed) {
    err.value = 'Pre odoslanie reply sa prihlas.'
    return
  }

  const trimmed = content.value.trim()
  if (!trimmed) {
    err.value = 'Napis aspon kratku odpoved.'
    return
  }

  err.value = ''
  posting.value = true

  try {
    const fd = new FormData()
    fd.append('content', trimmed)
    if (file.value) fd.append('attachment', file.value)
    if (selectedGif.value) {
      fd.append('gif[id]', selectedGif.value.id)
      fd.append('gif[title]', selectedGif.value.title || '')
      fd.append('gif[preview_url]', selectedGif.value.preview_url || '')
      fd.append('gif[original_url]', selectedGif.value.original_url || '')
      if (selectedGif.value.width) fd.append('gif[width]', String(selectedGif.value.width))
      if (selectedGif.value.height) fd.append('gif[height]', String(selectedGif.value.height))
    }

    const res = await api.post(`/posts/${props.parentId}/reply`, fd)

    emit('created', res.data)

    content.value = ''
    removeFile()
    removeGif()
    await nextTick()
    autoGrow()
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) err.value = 'Pre odoslanie reply sa prihlas.'
    else if (status === 422) err.value = 'Skontroluj text (1-2000), prilohu a GIF.'
    else err.value = e?.response?.data?.message || 'Odoslanie reply zlyhalo.'
  } finally {
    posting.value = false
  }
}

onMounted(() => {
  autoGrow()
  if (props.autofocus) {
    focusInput({ scroll: false })
  }
  window.addEventListener('pointerdown', onPointerDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('pointerdown', onPointerDown)
  if (gifDebounceTimer.value) {
    clearTimeout(gifDebounceTimer.value)
    gifDebounceTimer.value = null
  }
  revokePreview()
})

defineExpose({ focusInput })
</script>

<style scoped src="./replyComposer/ReplyComposer.css"></style>
