<template src="./postComposer/PostComposer.template.html"></template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { createPost } from '@/services/posts'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import PollComposerPanel from '@/components/poll/PollComposerPanel.vue'
import { IMAGE_UPLOAD_LIMITS, prepareImageFileForUpload } from '@/utils/imageUpload'
import {
  clampPollDuration,
  createInitialPollOptions,
  createObjectUrl,
  firstValidationError,
  formatEventRange,
  isImageFile,
  normalizePollOptions,
  prettySize,
  resolveRequestErrorMessage,
  revokeAllPollOptionPreviews,
  revokeObjectUrl,
} from '@/components/createPostModal/createPostModal.utils'
import { usePostComposerAutocomplete } from './postComposer/usePostComposerAutocomplete'
import {
  GIF_MIN_QUERY_LENGTH,
  usePostComposerMediaPickers,
} from './postComposer/usePostComposerMediaPickers'

const DRAFT_KEY = 'post_composer_draft_v1'
const POLL_MIN_OPTIONS = 2
const POLL_MAX_OPTIONS = 4
const POLL_MIN_SECONDS = 300
const POLL_MAX_SECONDS = 604800

const emit = defineEmits(['created'])

const props = defineProps({
  accept: { type: String, default: 'image/*,.gif,.pdf,.txt,.doc,.docx' },
  maxBytes: { type: Number, default: 32 * 1024 * 1024 },
})

const auth = useAuthStore()
const toast = useToast()

const content = ref('')
const file = ref(null)
const imagePreviewUrl = ref(null)
const posting = ref(false)
const mediaPreparing = ref(false)
const mediaStatusMessage = ref('')
const err = ref('')
const isFocused = ref(false)
const pollEnabled = ref(false)
const pollOptions = ref(createInitialPollOptions())
const pollDurationSeconds = ref(86400)

const fileInput = ref(null)
const textareaRef = ref(null)

const pollAttachmentDisabledHint = 'Pri ankete sa obrázky pridávajú iba ku konkrétnym možnostiam.'
const isAttachmentDisabled = computed(() => pollEnabled.value)

const isExpanded = computed(() => isFocused.value || content.value.trim().length > 0 || !!file.value || !!selectedGif.value || !!selectedEvent.value)
const composerPlaceholder = computed(() => (pollEnabled.value ? 'Napíš otázku ankety...' : 'Čo sa deje na oblohe?'))

const isSubmitDisabled = computed(() => {
  if (posting.value) return true
  if (mediaPreparing.value) return true
  if (!content.value.trim()) return true
  if (content.value.length > 2000) return true
  if (pollEnabled.value && !isPollValid.value) return true
  return false
})

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

const {
  autocompletePosition,
  cleanupAutocomplete,
  onBlur: onAutocompleteBlur,
  onKeydown,
  onTyping,
  selectSuggestion,
  selectedIndex,
  showAutocomplete,
  suggestions,
} = usePostComposerAutocomplete({
  autoResize,
  content,
  err,
  isSubmitDisabled,
  submit,
  textareaRef,
})

const {
  cleanupMediaPickers,
  closeEventModal,
  closeGifModal,
  eventError,
  eventFollowLoading,
  eventFollowed,
  eventLoading,
  eventQuery,
  eventResults,
  gifError,
  gifInputRef,
  gifLoading,
  gifQuery,
  gifResults,
  markCalendar,
  onEventQueryChange,
  onGifQueryChange,
  openEventModal,
  openGifModal,
  removeEvent,
  removeGif,
  selectEvent,
  selectGif,
  selectedEvent,
  selectedGif,
  showEventModal,
  showGifModal,
} = usePostComposerMediaPickers({
  auth,
  err,
  pollAttachmentDisabledHint,
  pollEnabled,
  removeFile,
})

watch(
  [content, pollEnabled, pollOptions, pollDurationSeconds, selectedGif, selectedEvent],
  () => {
    persistDraft()
  },
  { deep: true },
)

function onFocus() {
  isFocused.value = true
  autoResize()
}

function onBlur() {
  isFocused.value = false
  if (!content.value.trim() && textareaRef.value) {
    textareaRef.value.style.height = ''
  }
  onAutocompleteBlur()
}

function pickFile() {
  if (isAttachmentDisabled.value) {
    err.value = pollAttachmentDisabledHint
    return
  }

  fileInput.value?.click()
}

function enablePoll() {
  if (file.value || selectedGif.value) {
    toast.warn('Anketa sa nedá kombinovať s prílohami.', {
      action: {
        label: 'Odstrániť prílohy a pokračovať',
        onClick: async () => {
          removeFile()
          removeGif()
          pollEnabled.value = true
          ensurePollDefaults()
        },
      },
    })
    return
  }

  pollEnabled.value = true
  ensurePollDefaults()
}

function disablePoll() {
  pollEnabled.value = false
  resetPollState()
}

function onPollOptionsUpdate(nextOptions) {
  pollOptions.value = normalizePollOptions(nextOptions, pollOptions.value, {
    minOptions: POLL_MIN_OPTIONS,
    maxOptions: POLL_MAX_OPTIONS,
  })
}

async function preparePollOptionImageFile(pickedFile) {
  const preparedFile = await prepareComposerImageFile(
    pickedFile,
    'poll',
    'Optimalizujem obrazok pre anketu...',
  )
  err.value = ''
  return preparedFile
}

function onPollImageError(message) {
  err.value = String(message || 'Obrázok pre anketu sa nepodarilo spracovat.')
}

function setPollDurationSeconds(value) {
  pollDurationSeconds.value = normalizeComposerPollDuration(value)
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

function normalizeComposerPollDuration(value) {
  const numericValue = Number(value)
  if (!Number.isFinite(numericValue)) return 86400
  return clampPollDuration(numericValue, {
    min: POLL_MIN_SECONDS,
    max: POLL_MAX_SECONDS,
  })
}

function revokePreview() {
  if (imagePreviewUrl.value) {
    revokeObjectUrl(imagePreviewUrl.value)
    imagePreviewUrl.value = null
  }
}

function removeFile() {
  revokePreview()
  file.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function isAllowedByMvp(f) {
  const name = (f?.name || '').toLowerCase()
  return isLikelySupportedImage(f) || name.endsWith('.pdf') || name.endsWith('.txt') || name.endsWith('.doc') || name.endsWith('.docx')
}

function isLikelySupportedImage(f) {
  if (isImageFile(f)) return true
  const name = String(f?.name || '').toLowerCase()
  return name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.png') || name.endsWith('.webp') || name.endsWith('.gif')
}

async function prepareComposerImageFile(pickedFile, context, statusMessage) {
  mediaPreparing.value = true
  mediaStatusMessage.value = statusMessage
  err.value = ''

  try {
    const prepared = await prepareImageFileForUpload(pickedFile, {
      context,
      maxBytes: context === 'poll' ? IMAGE_UPLOAD_LIMITS.pollOptionImageBytes : props.maxBytes,
    })
    return prepared.file
  } finally {
    mediaPreparing.value = false
    mediaStatusMessage.value = ''
  }
}

async function onFileChange(e) {
  err.value = ''

  if (pollEnabled.value) {
    err.value = pollAttachmentDisabledHint
    return
  }

  const f = e?.target?.files?.[0] || null
  if (!f) return

  removeFile()
  let nextFile = f

  if (isLikelySupportedImage(f)) {
    try {
      nextFile = await prepareComposerImageFile(
        f,
        'post-image',
        'Optimalizujem obrazok pred odoslaním...',
      )
    } catch (error) {
      err.value = String(error?.userMessage || error?.message || `Obrázok je príliš veľký. Max ${prettySize(props.maxBytes)}.`)
      return
    }
  } else if (f.size > props.maxBytes) {
    err.value = `Súbor je príliš veľký. Max ${prettySize(props.maxBytes)}.`
    return
  }

  if (!isAllowedByMvp(nextFile)) {
    err.value = 'Nepovolený typ súboru.'
    return
  }

  file.value = nextFile
  if (selectedGif.value) removeGif()
  if (isImageFile(nextFile)) {
    imagePreviewUrl.value = createObjectUrl(nextFile)
  }
}

async function submit() {
  err.value = ''
  posting.value = true

  try {
    const fd = new FormData()
    fd.append('content', content.value.trim())
    if (file.value) fd.append('attachment', file.value)
    if (selectedGif.value) {
      fd.append('gif[id]', selectedGif.value.id)
      fd.append('gif[title]', selectedGif.value.title || '')
      fd.append('gif[preview_url]', selectedGif.value.preview_url || '')
      fd.append('gif[original_url]', selectedGif.value.original_url || '')
      if (selectedGif.value.width) fd.append('gif[width]', String(selectedGif.value.width))
      if (selectedGif.value.height) fd.append('gif[height]', String(selectedGif.value.height))
    }
    if (selectedEvent.value?.id) {
      fd.append('event_id', String(selectedEvent.value.id))
    }

    if (pollEnabled.value) {
      fd.append('poll[duration_seconds]', String(normalizeComposerPollDuration(pollDurationSeconds.value)))
      pollOptions.value.forEach((option, index) => {
        fd.append(`poll[options][${index}][text]`, String(option?.text || '').trim())
        if (option?.imageFile) {
          fd.append(`poll[options][${index}][image]`, option.imageFile)
        }
      })
    }

    const res = await createPost(fd)

    emit('created', res.data)

    content.value = ''
    isFocused.value = false
    removeFile()
    removeGif()
    removeEvent()
    disablePoll()
    clearDraft()
    if (textareaRef.value) textareaRef.value.style.height = ''
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) err.value = 'Pre publikovanie sa prihlás.'
    else if (status === 422) err.value = firstValidationError(e, 'Skontroluj text, prílohu a poll možnosti.')
    else err.value = resolveRequestErrorMessage(e, 'Publikovanie zlyhalo.')
  } finally {
    posting.value = false
  }
}

function autoResize() {
  const el = textareaRef.value
  if (!el) return
  el.style.height = 'auto'
  const nextHeight = Math.min(150, Math.max(46, el.scrollHeight))
  el.style.height = `${nextHeight}px`
}


function persistDraft() {
  try {
    const payload = {
      content: content.value,
      pollEnabled: pollEnabled.value,
      pollDurationSeconds: normalizeComposerPollDuration(pollDurationSeconds.value),
      pollOptions: pollOptions.value.map((option) => ({
        text: String(option?.text || ''),
      })),
      gif: selectedGif.value
        ? {
          id: selectedGif.value.id,
          title: selectedGif.value.title,
          preview_url: selectedGif.value.preview_url,
          original_url: selectedGif.value.original_url,
          width: selectedGif.value.width,
          height: selectedGif.value.height,
        }
        : null,
      event: selectedEvent.value
        ? {
          id: selectedEvent.value.id,
          title: selectedEvent.value.title,
          start_at: selectedEvent.value.start_at,
          end_at: selectedEvent.value.end_at,
        }
        : null,
    }
    window.localStorage.setItem(DRAFT_KEY, JSON.stringify(payload))
  } catch {
    // no-op
  }
}

function loadDraft() {
  try {
    const raw = window.localStorage.getItem(DRAFT_KEY)
    if (!raw) return
    const draft = JSON.parse(raw)
    content.value = String(draft?.content || '')
    pollEnabled.value = Boolean(draft?.pollEnabled)
    pollDurationSeconds.value = normalizeComposerPollDuration(draft?.pollDurationSeconds ?? 86400)
    const draftOptions = Array.isArray(draft?.pollOptions) ? draft.pollOptions : []
    pollOptions.value = normalizePollOptions(
      draftOptions.map((option) => ({
        text: String(option?.text || ''),
        imageFile: null,
        imagePreviewUrl: '',
      })),
      [],
      {
        minOptions: POLL_MIN_OPTIONS,
        maxOptions: POLL_MAX_OPTIONS,
      },
    )
    selectedGif.value = draft?.gif && typeof draft.gif === 'object' ? draft.gif : null
    selectedEvent.value = draft?.event && typeof draft.event === 'object' ? draft.event : null
  } catch {
    // no-op
  }
}

function clearDraft() {
  try {
    window.localStorage.removeItem(DRAFT_KEY)
  } catch {
    // no-op
  }
}

onMounted(() => {
  loadDraft()
  autoResize()
})

onBeforeUnmount(() => {
  revokePreview()
  revokeAllPollOptionPreviews(pollOptions.value)
  cleanupAutocomplete()
  cleanupMediaPickers()
})
</script>

<style scoped src="./postComposer/PostComposer.css"></style>
