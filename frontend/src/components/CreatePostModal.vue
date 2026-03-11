<template>
  <teleport to="body">
    <transition name="fade">
      <div v-if="open" class="overlay" role="presentation" @click.self="onOverlayClick">
        <section class="dialog" :class="{ 'dialog--observation': isObservationMode }" role="dialog" aria-modal="true" aria-labelledby="composer-title" @click.stop>
          <header class="head">
            <button type="button" class="ghost" :disabled="submitting" @click="cancelAndClose">Zrusit</button>
            <div class="headRight">
              <template v-if="isObservationMode">
                <button type="button" class="ghost" :disabled="submitting" @click="switchToPostComposer">
                  Spat na prispevok
                </button>
              </template>
              <template v-else>
                <button type="button" class="ghost" :disabled="submitting" @click="onDraftsClick">Koncepty</button>
                <button type="button" class="primary" :disabled="isSubmitDisabled" @click="submit">
                  {{ submitting ? 'Odosielam...' : 'Pridat' }}
                </button>
              </template>
            </div>
          </header>

          <p id="composer-title" class="srOnly">Vytvorenie prispevku</p>
          <p v-if="isPostMode && errorMessage" class="error">{{ errorMessage }}</p>
          <p v-else-if="isPostMode && submitBlockReason" class="error">{{ submitBlockReason }}</p>

          <div class="body" :class="{ 'body--observation': isObservationMode }">
            <ObservationCreateView
              v-if="isObservationMode"
              embedded
              @cancel="switchToPostComposer"
              @submitted="onObservationSubmitted"
            />

            <template v-else>
              <div class="avatar" aria-hidden="true">
                <UserAvatar class="avatarImg" :user="auth?.user" :alt="auth?.user?.name || 'avatar'" />
              </div>

              <div class="contentCol">
                <label class="srOnly" for="composer-textarea">Text prispevku</label>
                <textarea
                  id="composer-textarea"
                  ref="textareaRef"
                  v-model="content"
                  class="textarea"
                  placeholder="Čo je nové na oblohe?"
                  rows="5"
                  :disabled="submitting"
                  @input="onInput"
                />

                <div v-if="imagePreviewUrl" class="mediaCard">
                  <img class="mediaImg" :src="imagePreviewUrl" alt="Nahlad" />
                  <button class="mediaRemove" type="button" :disabled="submitting" @click="removeFile">x</button>
                </div>

                <PollComposerPanel
                  v-if="pollEnabled"
                  :model-value="pollOptions"
                  :duration-seconds="pollDurationSeconds"
                  :disabled="submitting"
                  @update:model-value="onPollOptionsUpdate"
                  @update:duration-seconds="setPollDurationSeconds"
                  @remove-poll="disablePoll"
                />

                <div v-if="selectedGif" class="mediaCard">
                  <img class="mediaImg" :src="selectedGif.preview_url || selectedGif.original_url" :alt="selectedGif.title || 'GIF'" />
                  <button class="mediaRemove" type="button" :disabled="submitting" @click="removeGif">x</button>
                </div>

                <div v-if="selectedEvent" class="eventCard">
                  <div>
                    <p class="eventTitle">{{ selectedEvent.title || 'Vybrana udalost' }}</p>
                    <p class="eventDate">{{ formatEventRange(selectedEvent.start_at, selectedEvent.end_at) }}</p>
                  </div>
                  <button class="eventRemove" type="button" :disabled="submitting" @click="removeEvent">Odstranit</button>
                </div>
              </div>
            </template>
          </div>

          <footer v-if="isPostMode" class="foot">
            <div class="actions">
              <input ref="fileInput" class="hiddenInput" type="file" accept="image/*,.gif" @change="onFileChange" />

              <button type="button" class="iconBtn" :class="{ active: !!file }" :disabled="submitting || pollEnabled" aria-label="Obrazok" @click="pickFile">
                <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="m7 15 3.2-3.2a1 1 0 0 1 1.4 0L14 14l2-2a1 1 0 0 1 1.4 0L20 14.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="9" cy="9" r="1.2" fill="currentColor"/></svg>
              </button>

              <button type="button" class="iconBtn" :class="{ active: !!selectedGif }" :disabled="submitting || pollEnabled" aria-label="GIF" @click="openGifModal">
                <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M8 10.5h2.8M8 13.5h2.8M12 10.5h4.8M12 13.5h4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
              </button>

              <div class="popWrap">
                <button type="button" class="iconBtn" :class="{ active: showEmoji }" :disabled="submitting" aria-label="Emoji" @click="toggleEmoji">
                  <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M9 10.5h.01M15 10.5h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M8.8 14.2a4.2 4.2 0 0 0 6.4 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
                <div v-if="showEmoji" class="popMenu">
                  <button v-for="emoji in EMOJI_SET" :key="emoji" type="button" class="emojiBtn" @click="insertEmoji(emoji)">{{ emoji }}</button>
                </div>
              </div>

              <div class="popWrap">
                <button type="button" class="iconBtn" :class="{ active: showMore || pollEnabled || !!selectedEvent }" :disabled="submitting" aria-label="Viac" @click="toggleMore">
                  <svg viewBox="0 0 24 24" fill="none"><circle cx="6" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="18" cy="12" r="1.6" fill="currentColor"/></svg>
                </button>
                <div v-if="showMore" class="popMenu actionsMenu">
                  <button type="button" class="menuBtn" :disabled="submitting" @click="openEventModal">
                    <svg class="menuBtnIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <rect x="3.5" y="5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7"/>
                      <path d="M8 3.5v3M16 3.5v3M3.5 9.5h17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <span>{{ selectedEvent ? 'Zmenit udalost' : 'Pridat udalost' }}</span>
                  </button>
                  <button type="button" class="menuBtn" :disabled="submitting" @click="togglePollFromMenu">
                    <svg class="menuBtnIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M5 7h14M5 12h14M5 17h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                      <circle cx="8" cy="7" r="1.2" fill="currentColor"/>
                      <circle cx="16" cy="12" r="1.2" fill="currentColor"/>
                      <circle cx="11" cy="17" r="1.2" fill="currentColor"/>
                    </svg>
                    <span>{{ pollEnabled ? 'Odstranit anketu' : 'Pridat anketu' }}</span>
                  </button>
                  <button type="button" class="menuBtn" :disabled="submitting" @click="openObservationCreate">
                    <svg class="menuBtnIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M7.5 4.75h5l4 4v10.5a1 1 0 0 1-1 1h-8a1 1 0 0 1-1-1v-13.5a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                      <path d="M12.5 4.75v4h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M9.5 12h5M12 9.5v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <span>Pridať pozorovanie</span>
                  </button>
                </div>
              </div>
            </div>

            <div class="counter" :class="{ bad: isOverLimit }">
              <span>{{ remainingChars }}</span>
              <span class="ring" :style="counterRingStyle"></span>
            </div>
          </footer>
        </section>

        <div v-if="showGifModal" class="subBackdrop" @click.self="closeGifModal">
          <section class="subCard" role="dialog" aria-modal="true" aria-labelledby="gif-title">
            <header class="subHead">
              <h3 id="gif-title">Vybrat GIF</h3>
              <button type="button" class="subClose" @click="closeGifModal">x</button>
            </header>
            <input ref="gifInputRef" v-model="gifQuery" class="subInput" type="text" placeholder="Hladaj GIF..." @input="onGifQueryChange" />
            <div v-if="gifLoading" class="gifGrid"><div v-for="i in 6" :key="`gif-${i}`" class="gifSkel"></div></div>
            <p v-else-if="gifError" class="subError">{{ gifError }}</p>
            <p v-else-if="gifQuery.trim().length < GIF_MIN_QUERY_LENGTH" class="subHint">Zadaj aspon 2 znaky.</p>
            <p v-else-if="gifResults.length === 0" class="subHint">Ziadne GIFy.</p>
            <div v-else class="gifGrid">
              <button v-for="gif in gifResults" :key="gif.id" type="button" class="gifTile" @click="selectGif(gif)">
                <img :src="gif.preview_url" :alt="gif.title || 'GIF'" loading="lazy" />
              </button>
            </div>
          </section>
        </div>

        <div v-if="showEventModal" class="subBackdrop" @click.self="closeEventModal">
          <section class="subCard" role="dialog" aria-modal="true" aria-labelledby="event-title">
            <header class="subHead">
              <h3 id="event-title">Pridat udalost</h3>
              <button type="button" class="subClose" @click="closeEventModal">x</button>
            </header>
            <input v-model="eventQuery" class="subInput" type="text" placeholder="Hladaj udalost..." @input="onEventQueryChange" />
            <p v-if="eventError" class="subError">{{ eventError }}</p>
            <p v-else-if="eventLoading" class="subHint">Nacitavam...</p>
            <p v-else-if="eventResults.length === 0" class="subHint">Nenasli sa ziadne udalosti.</p>
            <div v-else class="eventList">
              <button v-for="eventItem in eventResults" :key="eventItem.id" class="eventItem" type="button" @click="selectEvent(eventItem)">
                <span class="eventItemTitle">{{ eventItem.title }}</span>
                <span class="eventItemDate">{{ formatEventRange(eventItem.start_at, eventItem.end_at) }}</span>
              </button>
            </div>
          </section>
        </div>
      </div>
    </transition>
  </teleport>
</template>

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

const MAX_CHARS = 300
const MAX_BYTES = 20 * 1024 * 1024
const MIN_HEIGHT = 120
const MAX_HEIGHT = 220
const GIF_MIN_QUERY_LENGTH = 2
const POLL_MIN_OPTIONS = 2
const POLL_MAX_OPTIONS = 4
const POLL_MIN_SECONDS = 300
const POLL_MAX_SECONDS = 604800
const EMOJI_SET = ['😀', '😂', '😍', '🤩', '🥳', '🚀', '🌙', '✨', '🔭', '🪐', '☄️', '🌌', '👍', '❤️']

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

let bodyOverflow = ''

const isObservationMode = computed(() => composerMode.value === 'observation')
const isPostMode = computed(() => !isObservationMode.value)
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
  if (!content.value.trim()) return 'Dopln otazku ankety do textu prispevku.'
  if (!isPollValid.value) return 'Skontroluj moznosti ankety (2-4, max 25 znakov).'
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

function isImageFile(targetFile) {
  return typeof targetFile?.type === 'string' && targetFile.type.startsWith('image/')
}

function createObjectUrl(targetFile) {
  return typeof URL?.createObjectURL === 'function' ? URL.createObjectURL(targetFile) : ''
}

function revokeObjectUrl(url) {
  if (url && typeof URL?.revokeObjectURL === 'function') URL.revokeObjectURL(url)
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

function prettySize(bytes) {
  const numberValue = Number(bytes || 0)
  if (numberValue < 1024) return `${numberValue} B`
  const kb = numberValue / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
}

function pickFile() {
  if (pollEnabled.value) {
    errorMessage.value = 'Pri ankete nie je mozne pridat obrazok alebo GIF.'
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
    errorMessage.value = 'Pri ankete nie je mozne pridat obrazok alebo GIF.'
    return false
  }
  if (!isImageFile(pickedFile)) {
    errorMessage.value = 'Povolene su iba obrazky.'
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
    errorMessage.value = 'Pri ankete nie je mozne pridat obrazok alebo GIF.'
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
      ? 'GIF vyhladavanie je docasne pretazene. Skus neskor.'
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
    eventError.value = error?.response?.data?.message || 'Nepodarilo sa nacitat udalosti.'
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
    errorMessage.value = 'Anketu nie je mozne kombinovat s obrazkom alebo GIF.'
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

function createInitialPollOptions() {
  return [createEmptyPollOption(), createEmptyPollOption()]
}

function createEmptyPollOption() {
  return {
    text: '',
    imageFile: null,
    imagePreviewUrl: '',
  }
}

function normalizePollOptions(nextOptions, previousOptions = []) {
  const safe = Array.isArray(nextOptions) ? nextOptions.slice(0, POLL_MAX_OPTIONS) : createInitialPollOptions()
  const normalized = safe.map((option, index) => {
    const previous = previousOptions[index] || createEmptyPollOption()
    const imageFile = option?.imageFile || null
    let imagePreviewUrl = ''

    if (imageFile && imageFile === previous.imageFile && previous.imagePreviewUrl) {
      imagePreviewUrl = previous.imagePreviewUrl
    } else if (imageFile && isImageFile(imageFile)) {
      imagePreviewUrl = createObjectUrl(imageFile)
    } else if (typeof option?.imagePreviewUrl === 'string') {
      imagePreviewUrl = option.imagePreviewUrl
    }

    if (previous.imagePreviewUrl && previous.imagePreviewUrl !== imagePreviewUrl) {
      revokeObjectUrl(previous.imagePreviewUrl)
    }

    return {
      text: String(option?.text || '').slice(0, 25),
      imageFile,
      imagePreviewUrl,
    }
  })

  while (normalized.length < POLL_MIN_OPTIONS) {
    normalized.push(createEmptyPollOption())
  }

  if (previousOptions.length > normalized.length) {
    previousOptions.slice(normalized.length).forEach((option) => {
      if (option?.imagePreviewUrl) revokeObjectUrl(option.imagePreviewUrl)
    })
  }

  return normalized
}

function revokeAllPollOptionPreviews(options) {
  if (!Array.isArray(options)) return
  options.forEach((option) => {
    if (option?.imagePreviewUrl) revokeObjectUrl(option.imagePreviewUrl)
  })
}

function clampPollDuration(value) {
  const numberValue = Number(value || 0)
  if (!Number.isFinite(numberValue)) return 86400
  return Math.max(POLL_MIN_SECONDS, Math.min(POLL_MAX_SECONDS, Math.round(numberValue)))
}

function formatEventRange(startAt, endAt) {
  const start = parseEventDate(startAt)
  const end = parseEventDate(endAt)
  if (!start && !end) return 'Datum upresnime'
  if (start && !end) return start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })
  if (!start && end) return end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })
  const startLabel = start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  const endLabel = end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  return startLabel === endLabel ? startLabel : `${startLabel} - ${endLabel}`
}

function parseEventDate(value) {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

function resetState() {
  composerMode.value = 'post'
  content.value = ''
  errorMessage.value = ''
  showEmoji.value = false
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

    toast.success?.('Prispevok bol publikovany.')
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

function firstValidationError(error, fallbackMessage) {
  const errors = error?.response?.data?.errors
  if (!errors || typeof errors !== 'object') {
    return error?.response?.data?.message || fallbackMessage
  }

  const firstKey = Object.keys(errors)[0]
  const firstValue = firstKey ? errors[firstKey] : null
  if (Array.isArray(firstValue) && firstValue.length > 0) {
    return String(firstValue[0] || fallbackMessage)
  }

  return error?.response?.data?.message || fallbackMessage
}
</script>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 1500;
  display: grid;
  place-items: center;
  padding: 1.2rem;
  background: rgb(0 0 0 / 0.7);
}

.dialog {
  width: min(780px, 90vw);
  min-height: 360px;
  max-height: min(420px, 92vh);
  border-radius: 18px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  background: rgb(var(--color-bg-rgb) / 0.96);
  box-shadow: 0 24px 64px rgb(var(--color-bg-rgb) / 0.62);
  display: grid;
  grid-template-rows: auto auto 1fr auto;
  overflow: hidden;
}

.dialog--observation {
  width: min(760px, 90vw);
  min-height: 360px;
  max-height: min(86vh, 720px);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  padding: 0.8rem 1rem;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
}

.headRight {
  display: inline-flex;
  gap: 0.45rem;
}

.ghost,
.primary {
  border: 1px solid transparent;
  border-radius: 999px;
  min-height: 34px;
  padding: 0.3rem 0.8rem;
  font-size: 0.83rem;
  font-weight: 700;
}

.ghost {
  background: transparent;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.primary {
  border-color: rgb(var(--color-primary-rgb) / 0.72);
  background: rgb(var(--color-primary-rgb) / 0.9);
  color: rgb(255 255 255 / 0.98);
}

.ghost:disabled,
.primary:disabled,
.iconBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error {
  margin: 0;
  padding: 0 1rem;
  color: var(--color-danger);
  font-size: 0.82rem;
}

.body {
  min-height: 0;
  display: grid;
  grid-template-columns: 44px 1fr;
  gap: 0.72rem;
  padding: 0.9rem 1rem 0.75rem;
  overflow: auto;
}

.body--observation {
  display: block;
  padding: 0.45rem 0.7rem 0.6rem;
}

.avatar {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.56);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: rgb(var(--color-surface-rgb) / 0.95);
  font-size: 0.9rem;
  font-weight: 800;
}

.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.contentCol {
  min-width: 0;
  display: grid;
  gap: 0.5rem;
}

.textarea {
  width: 100%;
  min-height: 120px;
  max-height: 220px;
  border: 0;
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.97);
  padding: 0.15rem 0.1rem;
  font-size: 1.12rem;
  line-height: 1.48;
  resize: none;
  outline: none;
  overflow-y: hidden;
}

.textarea::placeholder {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
  padding: 0.72rem 1rem 0.85rem;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
}

.actions {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
}

.hiddenInput {
  display: none;
}

.iconBtn {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.36);
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.9);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.iconBtn svg {
  width: 1rem;
  height: 1rem;
}

.iconBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.75);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: rgb(var(--color-primary-rgb) / 1);
}

.counter {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.82rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.counter.bad {
  color: var(--color-danger);
}

.ring {
  width: 18px;
  height: 18px;
  border-radius: 999px;
  background:
    radial-gradient(circle at center, rgb(var(--color-bg-rgb) / 1) 54%, transparent 56%),
    conic-gradient(
      rgb(var(--color-primary-rgb) / 0.92) var(--ring-fill),
      rgb(var(--color-text-secondary-rgb) / 0.26) 0deg
    );
}

.counter.bad .ring {
  background:
    radial-gradient(circle at center, rgb(var(--color-bg-rgb) / 1) 54%, transparent 56%),
    conic-gradient(
      rgb(var(--color-danger-rgb) / 0.94) var(--ring-fill),
      rgb(var(--color-danger-rgb) / 0.24) 0deg
    );
}

.mediaCard {
  position: relative;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 12px;
  overflow: hidden;
  background: rgb(var(--color-bg-rgb) / 0.4);
}

.mediaImg {
  width: 100%;
  max-height: 240px;
  object-fit: cover;
  display: block;
}

.mediaRemove {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.78);
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.eventCard {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.4);
  padding: 0.65rem;
  display: flex;
  justify-content: space-between;
  gap: 0.7rem;
}

.eventTitle {
  margin: 0;
  color: rgb(var(--color-surface-rgb) / 0.95);
  font-size: 0.9rem;
  font-weight: 700;
}

.eventDate {
  margin: 0.18rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
  font-size: 0.76rem;
}

.eventRemove {
  border-radius: 999px;
  min-height: 30px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.38);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: rgb(var(--color-surface-rgb) / 0.95);
  font-size: 0.74rem;
  font-weight: 700;
  padding: 0.28rem 0.6rem;
}

.popWrap {
  position: relative;
}

.popMenu {
  position: absolute;
  left: 0;
  bottom: calc(100% + 0.45rem);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  box-shadow: 0 18px 40px rgb(var(--color-bg-rgb) / 0.45);
  padding: 0.42rem;
  z-index: 8;
}

.actionsMenu {
  min-width: 180px;
  display: grid;
  gap: 0.25rem;
}

.menuBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.45);
  color: rgb(var(--color-surface-rgb) / 0.94);
  text-align: left;
  padding: 0.45rem 0.55rem;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.menuBtn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.menuBtnIcon {
  width: 16px;
  height: 16px;
  flex: 0 0 auto;
}

.emojiBtn {
  width: 2rem;
  height: 2rem;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  font-size: 1rem;
}

.subBackdrop {
  position: fixed;
  inset: 0;
  z-index: 1510;
  background: rgb(0 0 0 / 0.55);
  display: grid;
  place-items: center;
  padding: 1rem;
}

.subCard {
  width: min(100%, 620px);
  max-height: 80vh;
  overflow: auto;
  border-radius: 14px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 0.8rem;
  display: grid;
  gap: 0.65rem;
}

.subHead {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.subHead h3 {
  margin: 0;
  font-size: 1rem;
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.subClose {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 8px;
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.95);
  width: 28px;
  height: 28px;
}

.subInput {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.46);
  color: rgb(var(--color-surface-rgb) / 0.95);
  padding: 0.52rem 0.65rem;
}

.gifGrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}

.gifTile {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 10px;
  overflow: hidden;
  padding: 0;
  background: transparent;
  min-height: 92px;
}

.gifTile img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.gifSkel {
  height: 92px;
  border-radius: 10px;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.18),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

.eventList {
  display: grid;
  gap: 0.4rem;
}

.eventItem {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  border-radius: 10px;
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.95);
  text-align: left;
  padding: 0.5rem 0.58rem;
  display: grid;
}

.eventItemTitle {
  font-size: 0.86rem;
  font-weight: 700;
}

.eventItemDate {
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.subHint,
.subError {
  margin: 0;
  font-size: 0.82rem;
}

.subHint {
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.subError {
  color: var(--color-danger);
}

.srOnly {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 160ms ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (max-width: 767px) {
  .overlay {
    align-items: end;
    padding: 0.7rem;
  }

  .dialog {
    width: min(780px, 100%);
    min-height: 340px;
    max-height: min(88vh, 500px);
    border-radius: 16px;
  }

  .dialog--observation {
    min-height: 340px;
    max-height: min(90vh, 680px);
  }

  .body--observation {
    padding: 0.35rem 0.45rem 0.5rem;
  }

  .gifGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>

