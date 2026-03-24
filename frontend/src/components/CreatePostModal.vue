<template src="./createPostModal/CreatePostModal.template.html"></template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import UserAvatar from '@/components/UserAvatar.vue'
import api from '@/services/api'
import { createPost } from '@/services/posts'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import PollComposerPanel from '@/components/poll/PollComposerPanel.vue'
import ObservationCreateView from '@/views/ObservationCreateView.vue'
import {
  clampPollDuration,
  createInitialPollOptions,
  createObjectUrl,
  firstValidationError,
  formatEventRange,
  isImageFile,
  normalizePollOptions,
  prettySize,
  revokeAllPollOptionPreviews,
  revokeObjectUrl,
} from './createPostModal/createPostModal.utils'

const MAX_CHARS = 300
const MAX_BYTES = 20 * 1024 * 1024
const MIN_HEIGHT = 120
const MAX_HEIGHT = 220
const GIF_MIN_QUERY_LENGTH = 2
const POLL_MIN_OPTIONS = 2
const POLL_MAX_OPTIONS = 4
const EMOJI_GROUPS = [
  {
    key: 'moon',
    label: 'Mesiac',
    icon: '\u{1F319}',
    emojis: [
      { char: '\u{1F311}', label: 'Nov' },
      { char: '\u{1F312}', label: 'Dorastajúci kosáčik' },
      { char: '\u{1F313}', label: 'Prvá štvrt' },
      { char: '\u{1F314}', label: 'Dorastajúci mesiac' },
      { char: '\u{1F315}', label: 'Spln' },
      { char: '\u{1F316}', label: 'Ubúdajúci mesiac' },
      { char: '\u{1F317}', label: 'Posledná štvrt' },
      { char: '\u{1F318}', label: 'Ubúdajúci kosáčik' },
      { char: '\u{1F319}', label: 'Polmesiac' },
      { char: '\u{1F31A}', label: 'Tmavý mesiac' },
      { char: '\u{1F31B}', label: 'Mesiačová štvrt' },
      { char: '\u{1F31C}', label: 'Mesiačová štvrt 2' },
      { char: '\u{1F31D}', label: 'Mesiačová tvár' },
    ],
  },
  {
    key: 'sky',
    label: 'Obloha',
    icon: '\u2728',
    emojis: [
      { char: '\u2600\uFE0F', label: 'Slnko' },
      { char: '\u{1F31E}', label: 'Slnko s tvarou' },
      { char: '\u{1F324}\uFE0F', label: 'Slnko za mrakom' },
      { char: '\u26C5', label: 'Polooblačno' },
      { char: '\u{1F325}\uFE0F', label: 'Oblačno' },
      { char: '\u2601\uFE0F', label: 'Mrak' },
      { char: '\u{1F326}\uFE0F', label: 'Prehánky' },
      { char: '\u{1F327}\uFE0F', label: 'Dážď' },
      { char: '\u26C8\uFE0F', label: 'Búrka' },
      { char: '\u{1F329}\uFE0F', label: 'Blesk' },
      { char: '\u{1F328}\uFE0F', label: 'Sneženie' },
      { char: '\u2744\uFE0F', label: 'Snehová vločka' },
      { char: '\u2614\uFE0F', label: 'Dáždnik' },
      { char: '\u{1F32A}\uFE0F', label: 'Vichor' },
      { char: '\u{1F32B}\uFE0F', label: 'Hmla' },
      { char: '\u{1F308}', label: 'Duha' },
    ],
  },
  {
    key: 'space',
    label: 'Vesmír',
    icon: '\u{1F680}',
    emojis: [
      { char: '\u{1F680}', label: 'Raketa' },
      { char: '\u{1F6F8}', label: 'Lietajúci tanier' },
      { char: '\u{1F6F0}\uFE0F', label: 'Satelit' },
      { char: '\u{1F4E1}', label: 'Anténa' },
      { char: '\u{1F52D}', label: 'Teleskop' },
      { char: '\u{1FA90}', label: 'Planéta' },
      { char: '\u{1F30D}', label: 'Zemeguľa Amerika' },
      { char: '\u{1F30E}', label: 'Zemeguľa Afrika' },
      { char: '\u{1F30F}', label: 'Zemeguľa Ázia' },
      { char: '\u2604\uFE0F', label: 'Kométa' },
      { char: '\u{1F30C}', label: 'Mliečna dráha' },
      { char: '\u{1F9D1}\u200D\u{1F680}', label: 'Astronaut' },
      { char: '\u{1F468}\u200D\u{1F680}', label: 'Astronaut muz' },
      { char: '\u{1F469}\u200D\u{1F680}', label: 'Astronaut zena' },
      { char: '\u{1F9ED}', label: 'Kompas' },
      { char: '\u{1F5FA}\uFE0F', label: 'Mapa oblohy' },
    ],
  },
  {
    key: 'react',
    label: 'Reakcie',
    icon: '\u{1F389}',
    emojis: [
      { char: '\u{1F600}', label: 'Usmev' },
      { char: '\u{1F601}', label: 'Radost' },
      { char: '\u{1F604}', label: 'Široký úsmev' },
      { char: '\u{1F60A}', label: 'Príjemný úsmev' },
      { char: '\u{1F60D}', label: 'Laska' },
      { char: '\u{1F929}', label: 'Nadsenie' },
      { char: '\u{1F60E}', label: 'Cool' },
      { char: '\u{1F914}', label: 'Premýšľanie' },
      { char: '\u{1F62E}', label: 'Prekvapenie' },
      { char: '\u{1F92F}', label: 'Ohromenie' },
      { char: '\u{1F44D}', label: 'Palec hore' },
      { char: '\u{1F44E}', label: 'Palec dole' },
      { char: '\u{1F64F}', label: 'Ďakujem' },
      { char: '\u{1F44F}', label: 'Potlesk' },
      { char: '\u{1F64C}', label: 'Oslava' },
      { char: '\u{1F389}', label: 'Konfety' },
    ],
  },
]

const props = defineProps({
  open: { type: Boolean, default: false },
  initialAction: { type: String, default: 'post' },
  initialAttachmentFile: { type: Object, default: null },
})
const emit = defineEmits(['close', 'created'])

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const textareaRef = ref(null)
const fileInput = ref(null)
const gifInputRef = ref(null)
const content = ref('')
const errorMessage = ref('')
const submitting = ref(false)
const file = ref(null)
const imagePreviewUrl = ref('')
const selectedGif = ref(null)
const selectedEvent = ref(null)
const showEmoji = ref(false)
const showMore = ref(false)
const showGifModal = ref(false)
const showEventModal = ref(false)
const gifQuery = ref('')
const gifResults = ref([])
const gifLoading = ref(false)
const gifError = ref('')
const eventQuery = ref('')
const eventResults = ref([])
const eventLoading = ref(false)
const eventError = ref('')
const gifDebounce = ref(null)
const eventDebounce = ref(null)
const pollEnabled = ref(false)
const pollOptions = ref(createInitialPollOptions())
const pollDurationSeconds = ref(86400)
const composerMode = ref('post')
const activeEmojiGroupKey = ref(EMOJI_GROUPS[0].key)

let bodyOverflow = ''

const isObservationMode = computed(() => composerMode.value === 'observation')
const isPostMode = computed(() => !isObservationMode.value)
const activeEmojiGroup = computed(() => (
  EMOJI_GROUPS.find((group) => group.key === activeEmojiGroupKey.value) || EMOJI_GROUPS[0]
))
const activeEmojiOptions = computed(() => (
  Array.isArray(activeEmojiGroup.value?.emojis) ? activeEmojiGroup.value.emojis : []
))
const normalizedLength = computed(() => content.value.trimEnd().length)
const remainingChars = computed(() => MAX_CHARS - normalizedLength.value)
const isOverLimit = computed(() => remainingChars.value < 0)
const isPollValid = computed(() => {
  if (!pollEnabled.value) return true
  if (pollOptions.value.length < POLL_MIN_OPTIONS || pollOptions.value.length > POLL_MAX_OPTIONS) return false
  return pollOptions.value.every((option) => {
    const text = String(option?.text || '').trim()
    return text.length >= 1 && text.length <= 25
  })
})
const submitBlockReason = computed(() => {
  if (!pollEnabled.value) return ''
  if (!content.value.trim()) return 'Doplň otázku ankety do textu príspevku.'
  if (!isPollValid.value) return 'Skontroluj možnosti ankety (2-4, max 25 znakov).'
  return ''
})
const isSubmitDisabled = computed(() => {
  if (!isPostMode.value) return true
  return submitting.value || normalizedLength.value === 0 || isOverLimit.value || !isPollValid.value
})
const counterRingStyle = computed(() => {
  const ratio = Math.max(0, Math.min(1, normalizedLength.value / MAX_CHARS))
  return { '--ring-fill': `${Math.round(ratio * 360)}deg` }
})

watch(() => props.open, async (isOpen) => {
  if (typeof window === 'undefined' || typeof document === 'undefined') return
  if (isOpen) {
    bodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', onKeydown)
    applyInitialAction()
    if (isPostMode.value) {
      await nextTick()
      focusTextarea()
      autoResize()
      applyInitialAttachment()
    }
    return
  }
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = bodyOverflow
  resetState()
}, { immediate: true })

watch(
  () => props.initialAttachmentFile,
  (nextFile) => {
    if (!props.open || !nextFile || !isPostMode.value) return
    applyInitialAttachment(nextFile)
  },
)

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') window.removeEventListener('keydown', onKeydown)
  if (typeof document !== 'undefined') document.body.style.overflow = bodyOverflow
  if (gifDebounce.value) clearTimeout(gifDebounce.value)
  if (eventDebounce.value) clearTimeout(eventDebounce.value)
  revokePreview()
  revokeAllPollOptionPreviews(pollOptions.value)
})

function onDraftsClick() {
  toast.info('Koncepty este nie su implementovane.')
}

function onOverlayClick() {
  if (submitting.value || showGifModal.value || showEventModal.value) return
  cancelAndClose()
}

function onKeydown(event) {
  if (!props.open || event.key !== 'Escape') return
  event.preventDefault()
  if (showGifModal.value) return closeGifModal()
  if (showEventModal.value) return closeEventModal()
  if (showEmoji.value) return (showEmoji.value = false)
  if (showMore.value) return (showMore.value = false)
  if (!submitting.value) cancelAndClose()
}

function focusTextarea() {
  const el = textareaRef.value
  if (!el) return
  el.focus()
  const end = String(content.value || '').length
  el.setSelectionRange(end, end)
}

function onInput() {
  if (errorMessage.value) errorMessage.value = ''
  autoResize()
}

function autoResize() {
  const el = textareaRef.value
  if (!el) return
  el.style.height = 'auto'
  const next = Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, el.scrollHeight))
  el.style.height = `${next}px`
  el.style.overflowY = el.scrollHeight > MAX_HEIGHT ? 'auto' : 'hidden'
}

function revokePreview() {
  if (imagePreviewUrl.value) {
    revokeObjectUrl(imagePreviewUrl.value)
    imagePreviewUrl.value = ''
  }
}

function removeFile() {
  revokePreview()
  file.value = null
}

function pickFile() {
  if (pollEnabled.value) {
    errorMessage.value = 'Pri ankete nie je možné pridať obrázok alebo GIF.'
    return
  }
  fileInput.value?.click()
}

function onFileChange(event) {
  const pickedFile = event?.target?.files?.[0] || null
  if (pickedFile) {
    setSelectedImageFile(pickedFile)
  }
  if (fileInput.value) fileInput.value.value = ''
}

function setSelectedImageFile(pickedFile) {
  if (pollEnabled.value) {
    errorMessage.value = 'Pri ankete nie je možné pridať obrázok alebo GIF.'
    return false
  }
  if (!isImageFile(pickedFile)) {
    errorMessage.value = 'Povolené sú iba obrázky.'
    return false
  }
  if (pickedFile.size > MAX_BYTES) {
    errorMessage.value = `Subor je prilis velky. Max ${prettySize(MAX_BYTES)}.`
    return false
  }

  errorMessage.value = ''
  removeFile()
  file.value = pickedFile
  if (selectedGif.value) removeGif()
  imagePreviewUrl.value = createObjectUrl(pickedFile)
  return true
}

function toggleEmoji() {
  showMore.value = false
  showEmoji.value = !showEmoji.value
}

function selectEmojiGroup(groupKey) {
  if (!EMOJI_GROUPS.some((group) => group.key === groupKey)) return
  activeEmojiGroupKey.value = groupKey
}

function insertEmoji(emoji) {
  const el = textareaRef.value
  if (!el) return
  const start = Number(el.selectionStart || 0)
  const end = Number(el.selectionEnd || 0)
  content.value = `${content.value.slice(0, start)}${emoji}${content.value.slice(end)}`
  showEmoji.value = false
  nextTick(() => {
    const nextPosition = start + emoji.length
    el.focus()
    el.setSelectionRange(nextPosition, nextPosition)
    autoResize()
  })
}

function toggleMore() {
  showEmoji.value = false
  showMore.value = !showMore.value
}

function normalizeInitialAction(value) {
  const normalized = String(value || 'post').toLowerCase()
  if (normalized === 'observation') return 'observation'
  if (normalized === 'poll') return 'poll'
  if (normalized === 'event') return 'event'
  return 'post'
}

function applyInitialAction() {
  const action = normalizeInitialAction(props.initialAction)
  if (action === 'observation') {
    composerMode.value = 'observation'
    showEmoji.value = false
    showMore.value = false
    closeGifModal()
    closeEventModal()
    return
  }

  composerMode.value = 'post'
  if (action === 'poll') {
    enablePoll()
    return
  }
  if (action === 'event') {
    openEventModal()
  }
}

function applyInitialAttachment(nextFile = props.initialAttachmentFile) {
  if (!nextFile) return
  setSelectedImageFile(nextFile)
}

function openObservationCreate() {
  if (submitting.value) return
  composerMode.value = 'observation'
  showMore.value = false
  showEmoji.value = false
}

function switchToPostComposer() {
  if (submitting.value) return
  composerMode.value = 'post'
  showMore.value = false
  showEmoji.value = false
  nextTick(() => {
    focusTextarea()
    autoResize()
  })
}

async function onObservationSubmitted(payload) {
  if (submitting.value) return

  const observationId = Number(payload?.observationId || 0)
  const feedPostId = Number(payload?.feedPostId || 0)
  const shouldOpenPost = Boolean(payload?.isPublic) && Boolean(payload?.openPostAfterCreate) && feedPostId > 0

  resetState()
  emit('close')

  if (shouldOpenPost) {
    await router.push(`/posts/${feedPostId}`)
    return
  }

  if (observationId > 0) {
    await router.push(`/observations/${observationId}`)
    return
  }

  await router.push('/observations')
}

function openGifModal() {
  if (pollEnabled.value) {
    errorMessage.value = 'Pri ankete nie je možné pridať obrázok alebo GIF.'
    return
  }
  showEmoji.value = false
  showMore.value = false
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
  if (gifDebounce.value) clearTimeout(gifDebounce.value)
}

function onGifQueryChange() {
  gifError.value = ''
  if (gifDebounce.value) clearTimeout(gifDebounce.value)
  const query = gifQuery.value.trim()
  if (query.length < GIF_MIN_QUERY_LENGTH) {
    gifLoading.value = false
    gifResults.value = []
    return
  }
  gifDebounce.value = setTimeout(() => { void fetchGifResults(query) }, 450)
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
  } catch (error) {
    const status = Number(error?.response?.status || 0)
    gifError.value = status === 429
      ? 'GIF vyhladavanie je dočasne pretazene. Skus neskor.'
      : (error?.response?.data?.message || 'GIF vyhladavanie zlyhalo.')
  } finally {
    gifLoading.value = false
  }
}

function selectGif(gif) {
  if (!gif || pollEnabled.value) return
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

function openEventModal() {
  showMore.value = false
  showEmoji.value = false
  showEventModal.value = true
  eventError.value = ''
  if (eventResults.value.length === 0) void fetchEventResults('')
}

function closeEventModal() {
  showEventModal.value = false
  eventQuery.value = ''
  eventError.value = ''
  if (eventDebounce.value) clearTimeout(eventDebounce.value)
}

function onEventQueryChange() {
  if (eventDebounce.value) clearTimeout(eventDebounce.value)
  eventDebounce.value = setTimeout(() => { void fetchEventResults(eventQuery.value.trim()) }, 400)
}

async function fetchEventResults(query) {
  eventLoading.value = true
  eventError.value = ''
  try {
    const response = await api.get('/events', {
      params: { q: query || undefined, per_page: 12 },
      meta: { skipErrorToast: true },
    })
    eventResults.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (error) {
    eventError.value = error?.response?.data?.message || 'Nepodarilo sa načítať udalosti.'
  } finally {
    eventLoading.value = false
  }
}

function selectEvent(eventItem) {
  if (!eventItem?.id) return
  selectedEvent.value = eventItem
  closeEventModal()
}

function removeEvent() {
  selectedEvent.value = null
}

function togglePollFromMenu() {
  if (pollEnabled.value) {
    disablePoll()
    return
  }
  enablePoll()
}

function enablePoll() {
  if (file.value || selectedGif.value) {
    errorMessage.value = 'Anketu nie je možné kombinovať s obrázkom alebo GIF.'
    return
  }
  pollEnabled.value = true
  ensurePollDefaults()
  showMore.value = false
}

function disablePoll() {
  pollEnabled.value = false
  resetPollState()
  showMore.value = false
}

function onPollOptionsUpdate(nextOptions) {
  pollOptions.value = normalizePollOptions(nextOptions, pollOptions.value)
}

function setPollDurationSeconds(value) {
  pollDurationSeconds.value = clampPollDuration(value)
}

function ensurePollDefaults() {
  if (!Array.isArray(pollOptions.value) || pollOptions.value.length < POLL_MIN_OPTIONS) {
    pollOptions.value = createInitialPollOptions()
  }
}

function resetPollState() {
  revokeAllPollOptionPreviews(pollOptions.value)
  pollOptions.value = createInitialPollOptions()
  pollDurationSeconds.value = 86400
}

function resetState() {
  composerMode.value = 'post'
  content.value = ''
  errorMessage.value = ''
  showEmoji.value = false
  activeEmojiGroupKey.value = EMOJI_GROUPS[0].key
  showMore.value = false
  closeGifModal()
  closeEventModal()
  removeFile()
  removeGif()
  removeEvent()
  disablePoll()
  if (textareaRef.value) {
    textareaRef.value.style.height = `${MIN_HEIGHT}px`
    textareaRef.value.style.overflowY = 'hidden'
  }
}

function cancelAndClose() {
  if (submitting.value) return
  resetState()
  emit('close')
}

async function submit() {
  if (isSubmitDisabled.value) return

  submitting.value = true
  errorMessage.value = ''
  try {
    const response = await createPost({
      content: content.value.trim(),
      attachment: file.value,
      gif: selectedGif.value,
      eventId: selectedEvent.value?.id || null,
      poll: pollEnabled.value
        ? {
          durationSeconds: clampPollDuration(pollDurationSeconds.value),
          options: pollOptions.value,
        }
        : null,
    })

    emit('created', response?.data)
    resetState()
    emit('close')
  } catch (error) {
    const status = Number(error?.response?.status || 0)
    if (status === 401) {
      errorMessage.value = 'Pre publikovanie sa prihlas.'
    } else if (status === 422) {
      errorMessage.value = firstValidationError(error, 'Skontroluj text, prilohy a anketu.')
    } else {
      errorMessage.value = error?.response?.data?.message || 'Odoslanie zlyhalo.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped src="./createPostModal/CreatePostModal.css"></style>

