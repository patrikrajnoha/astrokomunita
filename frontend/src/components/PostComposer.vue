<template>
  <section class="composerCard">
    <div class="composerRow">
      <div class="avatar" aria-hidden="true">
        <UserAvatar class="avatarImg" :user="auth?.user" :alt="auth?.user?.name || 'avatar'" />
      </div>

      <div class="composerBody">
        <label for="post-composer-textarea" class="srOnly">Text prispevku</label>
        <textarea
          id="post-composer-textarea"
          ref="textareaRef"
          v-model="content"
          class="composerInput"
          :class="{ expanded: isExpanded }"
          rows="1"
          maxlength="2000"
          :placeholder="composerPlaceholder"
          @focus="onFocus"
          @input="onTyping"
          @keydown="onKeydown"
          @blur="onBlur"
        />

        <div
          v-if="showAutocomplete && suggestions.length > 0"
          class="autocompletePopover"
          :style="{ top: autocompletePosition.top + 'px', left: autocompletePosition.left + 'px' }"
        >
          <div
            v-for="(suggestion, index) in suggestions"
            :key="suggestion.name"
            class="autocompleteItem"
            :class="{ active: selectedIndex === index }"
            @click="selectSuggestion(suggestion)"
            @mouseenter="selectedIndex = index"
          >
            <div class="suggestionName">#{{ suggestion.name }}</div>
            <div class="suggestionCount">{{ suggestion.count }} posts</div>
          </div>
        </div>

        <div v-if="imagePreviewUrl" class="mediaCard">
          <img class="mediaImg" :src="imagePreviewUrl" alt="Preview" />
          <button class="removeMedia" type="button" :disabled="posting" aria-label="Odstranit prilohu" @click="removeFile">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
            </svg>
          </button>
        </div>

        <div v-else-if="file" class="fileChip">
          <div class="fileLeft">
            <svg class="clipIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="m21.4 11-8.5 8.5a5.5 5.5 0 1 1-7.8-7.8l8.9-8.9a3.5 3.5 0 1 1 5 5l-9 9a1.5 1.5 0 0 1-2.1-2.1l8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div class="fileText">
              <div class="fileName">{{ file.name }}</div>
              <div class="fileMeta">{{ prettySize(file.size) }}</div>
            </div>
          </div>

          <button class="fileRemove" type="button" :disabled="posting" @click="removeFile">
            Odstranit
          </button>
        </div>

        <PollComposerPanel
          v-if="pollEnabled"
          :model-value="pollOptions"
          :duration-seconds="pollDurationSeconds"
          :disabled="posting"
          @update:model-value="onPollOptionsUpdate"
          @update:duration-seconds="setPollDurationSeconds"
          @remove-poll="disablePoll"
        />

        <div v-if="selectedGif" class="mediaCard mediaCard--gif">
          <img class="mediaImg" :src="selectedGif.preview_url || selectedGif.original_url" :alt="selectedGif.title || 'GIF preview'" />
          <button class="removeMedia" type="button" :disabled="posting" aria-label="Odstranit GIF" @click="removeGif">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
            </svg>
          </button>
        </div>

        <div v-if="selectedEvent" class="eventAttachCard">
          <div>
            <p class="eventAttachTitle">{{ selectedEvent.title || 'Vybrana udalost' }}</p>
            <p class="eventAttachDate">{{ formatEventRange(selectedEvent.start_at, selectedEvent.end_at) }}</p>
          </div>
          <div class="eventAttachActions">
            <button
              class="eventCalendarBtn"
              type="button"
              :disabled="posting || eventFollowLoading"
              @click="markCalendar"
            >
              {{ eventFollowed ? 'Sledujes' : (eventFollowLoading ? 'Ukladam...' : 'Mark your calendar') }}
            </button>
            <button class="eventRemoveBtn" type="button" :disabled="posting" @click="removeEvent">Odstranit</button>
          </div>
        </div>

        <div class="actionsBar">
          <div class="leftActions">
            <input
              ref="fileInput"
              type="file"
              class="fileInput"
              :accept="accept"
              @change="onFileChange"
            />
            <button
              class="actionIconBtn"
              :class="{ 'actionIconBtn--active': !!file }"
              type="button"
              :disabled="posting || isAttachmentDisabled"
              :title="isAttachmentDisabled ? pollAttachmentDisabledHint : 'Obrazok'"
              aria-label="Pridat prilohu"
              @click="pickFile"
            >
              <svg class="actionIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="3.5" y="4.5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7"/>
                <path d="m7 15 3.2-3.2a1 1 0 0 1 1.4 0L14 14l2-2a1 1 0 0 1 1.4 0L20 14.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <circle cx="9" cy="9" r="1.2" fill="currentColor"/>
              </svg>
            </button>

            <button
              class="actionIconBtn"
              :class="{ 'actionIconBtn--active': !!selectedGif }"
              type="button"
              :disabled="posting || pollEnabled"
              aria-label="Vybrat GIF"
              :title="pollEnabled ? pollAttachmentDisabledHint : 'GIF'"
              @click="openGifModal"
            >
              <svg class="actionIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7"/>
                <path d="M8 10.5h2.8M8 13.5h2.8M12 10.5h4.8M12 13.5h4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
            </button>

            <button
              v-if="!pollEnabled"
              class="actionIconBtn"
              type="button"
              :disabled="posting"
              aria-label="Pridat anketu"
              @click="enablePoll"
            >
              <svg class="actionIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M5 7h14M5 12h14M5 17h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <circle cx="8" cy="7" r="1.2" fill="currentColor"/>
                <circle cx="16" cy="12" r="1.2" fill="currentColor"/>
                <circle cx="11" cy="17" r="1.2" fill="currentColor"/>
              </svg>
            </button>

            <button
              v-else
              class="actionIconBtn actionIconBtn--active"
              type="button"
              :disabled="posting"
              aria-label="Odstranit anketu"
              @click="disablePoll"
            >
              <svg class="actionIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
            </button>

            <button
              class="actionIconBtn"
              :class="{ 'actionIconBtn--active': !!selectedEvent }"
              type="button"
              :disabled="posting"
              aria-label="Pridat udalost"
              @click="openEventModal"
            >
              <svg class="actionIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="3.5" y="5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7"/>
                <path d="M8 3.5v3M16 3.5v3M3.5 9.5h17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
            </button>
          </div>

          <div class="rightActions">
            <div class="counter" :class="{ warn: content.length > 1800 && content.length <= 2000, bad: content.length > 2000 }">
              {{ content.length }}/2000
            </div>

            <button class="publishBtn" type="button" :disabled="isSubmitDisabled" aria-label="Publikovat" @click="submit">
              <span>{{ posting ? 'Publikujem...' : 'Post' }}</span>
            </button>
          </div>
        </div>
        <div v-if="pollEnabled" class="pollAttachmentHint">{{ pollAttachmentDisabledHint }}</div>

        <div v-if="err" class="err">{{ err }}</div>
        <div v-else-if="submitBlockReason" class="err">{{ submitBlockReason }}</div>
      </div>
    </div>

    <div v-if="showGifModal" class="modalBackdrop" @click.self="closeGifModal">
      <section class="modalCard" role="dialog" aria-modal="true" aria-labelledby="gif-modal-title">
        <header class="modalHead">
          <h3 id="gif-modal-title">Vybrat GIF</h3>
          <button class="modalClose" type="button" @click="closeGifModal">x</button>
        </header>
        <input
          ref="gifInputRef"
          v-model="gifQuery"
          class="modalInput"
          type="text"
          placeholder="Hladaj GIF..."
          @input="onGifQueryChange"
        />
        <div v-if="gifLoading" class="gifGrid">
          <div v-for="i in 6" :key="`gif-sk-${i}`" class="gifSkeleton"></div>
        </div>
        <p v-else-if="gifError" class="modalError">{{ gifError }}</p>
        <p v-else-if="gifQuery.trim().length < GIF_MIN_QUERY_LENGTH" class="modalHint">Zadaj aspon 2 znaky.</p>
        <p v-else-if="gifResults.length === 0" class="modalHint">Ziadne GIFy.</p>
        <div v-else class="gifGrid">
          <button v-for="gif in gifResults" :key="gif.id" type="button" class="gifTile" @click="selectGif(gif)">
            <img :src="gif.preview_url" :alt="gif.title || 'GIF'" loading="lazy" />
          </button>
        </div>
      </section>
    </div>

    <div v-if="showEventModal" class="modalBackdrop" @click.self="closeEventModal">
      <section class="modalCard" role="dialog" aria-modal="true" aria-labelledby="event-modal-title">
        <header class="modalHead">
          <h3 id="event-modal-title">Pridat udalost</h3>
          <button class="modalClose" type="button" @click="closeEventModal">x</button>
        </header>
        <input
          v-model="eventQuery"
          class="modalInput"
          type="text"
          placeholder="Hladaj udalost..."
          @input="onEventQueryChange"
        />
        <p v-if="eventError" class="modalError">{{ eventError }}</p>
        <p v-else-if="eventLoading" class="modalHint">Nacitavam...</p>
        <div v-else class="eventList">
          <button
            v-for="eventItem in eventResults"
            :key="eventItem.id"
            class="eventItem"
            type="button"
            @click="selectEvent(eventItem)"
          >
            <span class="eventItemTitle">{{ eventItem.title }}</span>
            <span class="eventItemDate">{{ formatEventRange(eventItem.start_at, eventItem.end_at) }}</span>
          </button>
        </div>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, nextTick, watch } from 'vue'
import UserAvatar from '@/components/UserAvatar.vue'
import api from '@/services/api'
import { createPost } from '@/services/posts'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import PollComposerPanel from '@/components/poll/PollComposerPanel.vue'
import { addFavorite } from '@/services/favorites'
import { getNotificationPreferences, updateNotificationPreferences } from '@/services/notificationPreferences'

const DRAFT_KEY = 'post_composer_draft_v1'
const POLL_MIN_OPTIONS = 2
const POLL_MAX_OPTIONS = 4
const POLL_MIN_SECONDS = 300
const POLL_MAX_SECONDS = 604800
const GIF_MIN_QUERY_LENGTH = 2

const emit = defineEmits(['created'])

const props = defineProps({
  accept: { type: String, default: 'image/*,.gif,.pdf,.txt,.doc,.docx' },
  maxBytes: { type: Number, default: 20 * 1024 * 1024 },
})

const auth = useAuthStore()
const toast = useToast()

const content = ref('')
const file = ref(null)
const imagePreviewUrl = ref(null)
const posting = ref(false)
const err = ref('')
const isFocused = ref(false)
const pollEnabled = ref(false)
const pollOptions = ref(createInitialPollOptions())
const pollDurationSeconds = ref(86400)
const selectedGif = ref(null)
const showGifModal = ref(false)
const gifQuery = ref('')
const gifResults = ref([])
const gifLoading = ref(false)
const gifError = ref('')
const gifDebounceTimer = ref(null)
const gifInputRef = ref(null)
const selectedEvent = ref(null)
const showEventModal = ref(false)
const eventQuery = ref('')
const eventResults = ref([])
const eventLoading = ref(false)
const eventError = ref('')
const eventDebounceTimer = ref(null)
const eventFollowed = ref(false)
const eventFollowLoading = ref(false)

const showAutocomplete = ref(false)
const suggestions = ref([])
const selectedIndex = ref(0)
const autocompletePosition = ref({ top: 0, left: 0 })
const currentHashtagStart = ref(0)
const debounceTimer = ref(null)
const suggestionCache = ref(new Map())

const fileInput = ref(null)
const textareaRef = ref(null)

const pollAttachmentDisabledHint = 'Pri ankete sa obrazky pridavaju iba ku konkretnym moznostiam.'
const isAttachmentDisabled = computed(() => pollEnabled.value)

const isExpanded = computed(() => isFocused.value || content.value.trim().length > 0 || !!file.value || !!selectedGif.value || !!selectedEvent.value)
const composerPlaceholder = computed(() => (pollEnabled.value ? 'Napis otazku ankety...' : 'Co sa deje na oblohe?'))

const isSubmitDisabled = computed(() => {
  if (posting.value) return true
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
  if (!content.value.trim()) return 'Dopln otazku ankety do textu prispevku.'
  if (!isPollValid.value) return 'Skontroluj moznosti ankety (2-4, max 25 znakov).'
  return ''
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

function onTyping(event) {
  autoResize()
  if (err.value && content.value.length <= 2000) err.value = ''

  const target = event?.target
  if (!target) return

  const cursorPos = target.selectionStart
  const textBefore = content.value.substring(0, cursorPos)
  const hashtagMatch = textBefore.match(/#([a-zA-Z0-9_]*)$/)

  if (hashtagMatch) {
    const query = hashtagMatch[1]
    if (query.length >= 1) {
      showAutocomplete.value = true
      currentHashtagStart.value = cursorPos - hashtagMatch[0].length
      fetchSuggestions(query)
      updateAutocompletePosition(target)
    } else {
      hideAutocomplete()
    }
  } else {
    hideAutocomplete()
  }
}

function updateAutocompletePosition(textarea) {
  const rect = textarea.getBoundingClientRect()
  const lineHeight = 24
  const lineHeightPx = parseInt(getComputedStyle(textarea).lineHeight) || lineHeight

  const lines = content.value.substring(0, textarea.selectionStart).split('\n')
  const currentLine = lines.length - 1
  const charInLine = lines[lines.length - 1].length

  autocompletePosition.value = {
    top: rect.top + window.scrollY + currentLine * lineHeightPx + lineHeightPx,
    left: rect.left + window.scrollX + Math.min(charInLine * 8, rect.width - 200),
  }
}

async function fetchSuggestions(query) {
  if (suggestionCache.value.has(query)) {
    suggestions.value = suggestionCache.value.get(query)
    selectedIndex.value = 0
    return
  }

  if (debounceTimer.value) {
    clearTimeout(debounceTimer.value)
  }

  debounceTimer.value = setTimeout(async () => {
    try {
      const res = await api.get(`/tags/suggest?q=${encodeURIComponent(query)}&limit=8`)
      const data = res.data || []
      suggestions.value = data
      selectedIndex.value = 0
      suggestionCache.value.set(query, data)
    } catch {
      suggestions.value = []
    }
  }, 200)
}

function hideAutocomplete() {
  showAutocomplete.value = false
  suggestions.value = []
  selectedIndex.value = 0
}

function selectSuggestion(suggestion) {
  if (!suggestion) return

  const cursorPos = textareaRef.value?.selectionStart || content.value.length
  const beforeHashtag = content.value.substring(0, currentHashtagStart.value)
  const afterHashtag = content.value.substring(cursorPos)

  content.value = beforeHashtag + '#' + suggestion.name + ' ' + afterHashtag

  nextTick(() => {
    const newCursorPos = beforeHashtag.length + suggestion.name.length + 2
    if (textareaRef.value) {
      textareaRef.value.setSelectionRange(newCursorPos, newCursorPos)
      textareaRef.value.focus()
      autoResize()
    }
  })

  hideAutocomplete()
}

function onKeydown(event) {
  if (showAutocomplete.value && suggestions.value.length > 0) {
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        selectedIndex.value = (selectedIndex.value + 1) % suggestions.value.length
        return
      case 'ArrowUp':
        event.preventDefault()
        selectedIndex.value = selectedIndex.value === 0 ? suggestions.value.length - 1 : selectedIndex.value - 1
        return
      case 'Enter':
      case 'Tab':
        event.preventDefault()
        if (selectedIndex.value >= 0 && selectedIndex.value < suggestions.value.length) {
          selectSuggestion(suggestions.value[selectedIndex.value])
        }
        return
      case 'Escape':
        event.preventDefault()
        hideAutocomplete()
        return
    }
  }

  if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
    event.preventDefault()
    if (!isSubmitDisabled.value) {
      submit()
    }
  }
}

function onBlur() {
  isFocused.value = false
  if (!content.value.trim() && textareaRef.value) {
    textareaRef.value.style.height = ''
  }
  setTimeout(hideAutocomplete, 200)
}

function pickFile() {
  if (isAttachmentDisabled.value) {
    err.value = pollAttachmentDisabledHint
    return
  }

  fileInput.value?.click()
}

function openGifModal() {
  if (pollEnabled.value) {
    err.value = pollAttachmentDisabledHint
    return
  }

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

  const q = gifQuery.value.trim()
  if (q.length < GIF_MIN_QUERY_LENGTH) {
    gifLoading.value = false
    gifResults.value = []
    return
  }

  gifDebounceTimer.value = setTimeout(() => {
    void fetchGifResults(q)
  }, 500)
}

async function fetchGifResults(query) {
  gifLoading.value = true
  gifError.value = ''
  try {
    const res = await api.get('/integrations/gifs/search', {
      params: { q: query, limit: 18, offset: 0 },
      meta: { skipErrorToast: true },
    })
    const list = Array.isArray(res?.data?.data) ? res.data.data : []
    gifResults.value = list
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
  showEventModal.value = true
  eventError.value = ''
  if (eventResults.value.length === 0) {
    void fetchEventResults('')
  }
}

function closeEventModal() {
  showEventModal.value = false
  eventQuery.value = ''
  eventError.value = ''
  if (eventDebounceTimer.value) {
    clearTimeout(eventDebounceTimer.value)
    eventDebounceTimer.value = null
  }
}

function onEventQueryChange() {
  if (eventDebounceTimer.value) clearTimeout(eventDebounceTimer.value)
  eventDebounceTimer.value = setTimeout(() => {
    void fetchEventResults(eventQuery.value.trim())
  }, 500)
}

async function fetchEventResults(query) {
  eventLoading.value = true
  eventError.value = ''
  try {
    const res = await api.get('/events', {
      params: {
        q: query || undefined,
        per_page: 12,
      },
      meta: { skipErrorToast: true },
    })
    const payload = res?.data?.data
    eventResults.value = Array.isArray(payload) ? payload : []
  } catch (e) {
    eventError.value = e?.response?.data?.message || 'Nepodarilo sa nacitat udalosti.'
  } finally {
    eventLoading.value = false
  }
}

function selectEvent(eventItem) {
  if (!eventItem?.id) return
  selectedEvent.value = eventItem
  eventFollowed.value = false
  closeEventModal()
}

function removeEvent() {
  selectedEvent.value = null
  eventFollowed.value = false
}

async function markCalendar() {
  const eventId = Number(selectedEvent.value?.id || 0)
  if (!eventId || eventFollowLoading.value) return
  if (!auth.isAuthed) {
    const redirect = encodeURIComponent(window.location.pathname + window.location.search)
    window.location.assign(`/login?redirect=${redirect}`)
    return
  }

  eventFollowLoading.value = true
  try {
    await addFavorite(eventId)
    const prefRes = await getNotificationPreferences()
    const current = prefRes?.data || {}
    const inApp = { ...(current.in_app || {}), event_reminder: true }
    const emailMap = { ...(current.email || {}), event_reminder: true }
    await updateNotificationPreferences({
      in_app: inApp,
      email_enabled: true,
      email: emailMap,
    })
    eventFollowed.value = true
  } catch (e) {
    err.value = e?.response?.data?.message || 'Nepodarilo sa zapnut sledovanie udalosti.'
  } finally {
    eventFollowLoading.value = false
  }
}

function enablePoll() {
  if (file.value || selectedGif.value) {
    toast.warn('Anketa sa neda kombinovat s prilohami.', {
      action: {
        label: 'Odstranit prilohy a pokracovat',
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

function isImageFile(f) {
  return typeof f?.type === 'string' && f.type.startsWith('image/')
}

function isAllowedByMvp(f) {
  const name = (f?.name || '').toLowerCase()
  return isImageFile(f) || name.endsWith('.pdf') || name.endsWith('.txt') || name.endsWith('.doc') || name.endsWith('.docx')
}

function onFileChange(e) {
  err.value = ''

  if (pollEnabled.value) {
    err.value = pollAttachmentDisabledHint
    return
  }

  const f = e?.target?.files?.[0] || null
  if (!f) return

  removeFile()

  if (f.size > props.maxBytes) {
    err.value = `Subor je prilis velky. Max ${prettySize(props.maxBytes)}.`
    return
  }

  if (!isAllowedByMvp(f)) {
    err.value = 'Nepovoleny typ suboru.'
    return
  }

  file.value = f
  if (selectedGif.value) removeGif()
  if (isImageFile(f)) {
    imagePreviewUrl.value = createObjectUrl(f)
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
      fd.append('poll[duration_seconds]', String(clampPollDuration(pollDurationSeconds.value)))
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
    if (status === 401) err.value = 'Pre publikovanie sa prihlas.'
    else if (status === 422) err.value = firstValidationError(e, 'Skontroluj text, prilohu a poll moznosti.')
    else err.value = e?.response?.data?.message || 'Publikovanie zlyhalo.'
  } finally {
    posting.value = false
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

function autoResize() {
  const el = textareaRef.value
  if (!el) return
  el.style.height = 'auto'
  const nextHeight = Math.min(150, Math.max(46, el.scrollHeight))
  el.style.height = `${nextHeight}px`
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
    const prev = previousOptions[index] || createEmptyPollOption()
    const text = String(option?.text || '').slice(0, 25)
    const imageFile = option?.imageFile || null
    let imagePreviewUrl = ''

    if (imageFile && imageFile === prev.imageFile && prev.imagePreviewUrl) {
      imagePreviewUrl = prev.imagePreviewUrl
    } else if (imageFile && isImageFile(imageFile)) {
      imagePreviewUrl = createObjectUrl(imageFile)
    } else if (typeof option?.imagePreviewUrl === 'string') {
      imagePreviewUrl = option.imagePreviewUrl
    }

    if (prev.imagePreviewUrl && prev.imagePreviewUrl !== imagePreviewUrl) {
      revokeObjectUrl(prev.imagePreviewUrl)
    }

    return {
      text,
      imageFile,
      imagePreviewUrl,
    }
  })

  if (normalized.length < POLL_MIN_OPTIONS) {
    while (normalized.length < POLL_MIN_OPTIONS) {
      normalized.push(createEmptyPollOption())
    }
  }

  if (previousOptions.length > normalized.length) {
    previousOptions.slice(normalized.length).forEach((option) => {
      if (option?.imagePreviewUrl) {
        revokeObjectUrl(option.imagePreviewUrl)
      }
    })
  }

  return normalized
}

function revokeAllPollOptionPreviews(options) {
  if (!Array.isArray(options)) return
  options.forEach((option) => {
    if (option?.imagePreviewUrl) {
      revokeObjectUrl(option.imagePreviewUrl)
    }
  })
}

function createObjectUrl(file) {
  if (typeof URL?.createObjectURL !== 'function') return ''
  return URL.createObjectURL(file)
}

function revokeObjectUrl(url) {
  if (!url) return
  if (typeof URL?.revokeObjectURL !== 'function') return
  URL.revokeObjectURL(url)
}

function clampPollDuration(value) {
  const n = Number(value || 0)
  if (!Number.isFinite(n)) return 86400
  return Math.max(POLL_MIN_SECONDS, Math.min(POLL_MAX_SECONDS, Math.round(n)))
}

function persistDraft() {
  try {
    const payload = {
      content: content.value,
      pollEnabled: pollEnabled.value,
      pollDurationSeconds: clampPollDuration(pollDurationSeconds.value),
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
    pollDurationSeconds.value = clampPollDuration(draft?.pollDurationSeconds ?? 86400)
    const draftOptions = Array.isArray(draft?.pollOptions) ? draft.pollOptions : []
    pollOptions.value = normalizePollOptions(
      draftOptions.map((option) => ({
        text: String(option?.text || ''),
        imageFile: null,
        imagePreviewUrl: '',
      })),
      [],
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
  if (gifDebounceTimer.value) clearTimeout(gifDebounceTimer.value)
  if (eventDebounceTimer.value) clearTimeout(eventDebounceTimer.value)
})

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
</script>

<style scoped>
.composerCard {
  --surface: rgb(var(--color-surface-rgb) / 0.98);
  --muted: rgb(var(--color-text-secondary-rgb) / 0.92);
  --border: rgb(var(--color-text-secondary-rgb) / 0.35);
  --soft-border: rgb(var(--color-text-secondary-rgb) / 0.24);
  --panel: rgb(var(--color-bg-rgb) / 0.58);
  --panel-strong: rgb(var(--color-bg-rgb) / 0.72);
  --primary: rgb(var(--color-primary-rgb) / 0.9);

  border-bottom: 1px solid var(--soft-border);
  background: rgb(var(--color-bg-rgb) / 0.48);
  padding: 0.65rem 0.7rem 0.55rem;
}

.composerRow {
  display: grid;
  grid-template-columns: 40px 1fr;
  align-items: flex-start;
  gap: 0.55rem;
}

.avatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.15);
  color: var(--surface);
  font-size: 0.88rem;
  font-weight: 800;
}

.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.composerBody {
  display: grid;
  gap: 0.45rem;
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

.composerInput {
  width: 100%;
  min-height: 46px;
  max-height: 150px;
  padding: 0.58rem 0.25rem;
  border-radius: 0;
  border: 0;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.15);
  background: transparent;
  color: var(--surface);
  outline: none;
  resize: none;
  overflow-y: auto;
  line-height: 1.5;
  font-size: 0.98rem;
  transition: min-height 0.2s ease, border-color 0.2s ease;
}

.composerInput::placeholder {
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.composerInput.expanded {
  min-height: 82px;
}

.composerInput:focus {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  box-shadow: none;
}

.mediaCard {
  position: relative;
  border: 1px solid var(--soft-border);
  border-radius: 12px;
  overflow: hidden;
  background: var(--panel);
}

.mediaImg {
  width: 100%;
  max-height: 280px;
  object-fit: cover;
  display: block;
}

.removeMedia {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: rgb(var(--color-bg-rgb) / 0.75);
  color: var(--surface);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.removeMedia svg {
  width: 0.9rem;
  height: 0.9rem;
}

.fileChip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.6rem;
  border: 1px solid var(--soft-border);
  border-radius: 12px;
  background: var(--panel);
  padding: 0.55rem 0.65rem;
}

.pollEditor {
  border: 1px solid var(--soft-border);
  border-radius: 12px;
  background: var(--panel);
  padding: 0.6rem;
  display: grid;
  gap: 0.55rem;
}

.pollHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.pollTitle {
  color: var(--surface);
  font-size: 0.82rem;
  font-weight: 800;
}

.pollOptions {
  display: grid;
  gap: 0.45rem;
}

.pollOptionInput {
  border: 1px solid var(--soft-border);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--surface);
  padding: 0.5rem 0.7rem;
  font-size: 0.82rem;
}

.pollOptionInput:focus {
  outline: none;
  border-color: var(--primary);
}

.pollControls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.pollToggleBtn {
  border: 1px solid var(--border);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--surface);
  padding: 0.32rem 0.62rem;
  font-size: 0.74rem;
  font-weight: 700;
}

.pollToggleBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.pollToggleBtn--remove {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  color: var(--color-danger);
}

.pollDurationLabel {
  color: var(--muted);
  font-size: 0.74rem;
  margin-left: 0.35rem;
}

.pollDurationSelect {
  border: 1px solid var(--soft-border);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--surface);
  padding: 0.32rem 0.62rem;
  font-size: 0.74rem;
}

.pollHint {
  color: var(--muted);
  font-size: 0.74rem;
}

.fileLeft {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.clipIcon {
  width: 0.9rem;
  height: 0.9rem;
  color: var(--muted);
  flex: 0 0 auto;
}

.fileText {
  min-width: 0;
}

.fileName {
  color: var(--surface);
  font-size: 0.84rem;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.fileMeta {
  color: var(--muted);
  font-size: 0.74rem;
}

.fileRemove {
  border: 1px solid rgb(var(--color-danger-rgb) / 0.5);
  border-radius: 10px;
  background: rgb(var(--color-danger-rgb) / 0.15);
  color: var(--color-danger);
  padding: 0.35rem 0.55rem;
  font-size: 0.73rem;
}

.eventAttachCard {
  border: 1px solid var(--soft-border);
  border-radius: 12px;
  background: var(--panel);
  padding: 0.65rem;
  display: flex;
  justify-content: space-between;
  gap: 0.7rem;
}

.eventAttachTitle {
  margin: 0;
  color: var(--surface);
  font-size: 0.9rem;
  font-weight: 700;
}

.eventAttachDate {
  margin: 0.18rem 0 0;
  color: var(--muted);
  font-size: 0.76rem;
}

.eventAttachActions {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.eventCalendarBtn,
.eventRemoveBtn {
  border-radius: 999px;
  min-height: 30px;
  border: 1px solid var(--soft-border);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--surface);
  font-size: 0.74rem;
  font-weight: 700;
  padding: 0.28rem 0.6rem;
}

.eventCalendarBtn {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.actionsBar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.45rem;
  padding-top: 0.1rem;
}

.leftActions,
.rightActions {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.fileInput {
  display: none;
}

.publishBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
  min-height: 32px;
  border-radius: 999px;
  font-size: 0.77rem;
  font-weight: 700;
  transition: background-color 0.16s ease, border-color 0.16s ease, opacity 0.16s ease;
}

.publishBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-surface-rgb) / 0.74);
  color: rgb(var(--color-bg-rgb) / 0.95);
  padding: 0.36rem 0.95rem;
}

.actionIconBtn {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  background: transparent;
  color: var(--surface);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.actionIconBtn--active {
  border-color: rgb(var(--color-primary-rgb) / 0.68);
  color: rgb(var(--color-primary-rgb) / 1);
  background: rgb(var(--color-primary-rgb) / 0.13);
}

.actionIcon {
  width: 1rem;
  height: 1rem;
}

.publishBtn:hover {
  background: rgb(var(--color-surface-rgb) / 0.9);
}

.actionIconBtn:disabled,
.publishBtn:disabled,
.removeMedia:disabled,
.fileRemove:disabled,
.eventCalendarBtn:disabled,
.eventRemoveBtn:disabled {
  opacity: 0.52;
  cursor: not-allowed;
}

.btnIcon {
  width: 0.92rem;
  height: 0.92rem;
  flex: 0 0 auto;
}

.counter {
  font-size: 0.72rem;
  color: var(--muted);
  opacity: 0.9;
}

.counter.warn {
  color: rgb(250 204 21);
}

.counter.bad {
  color: var(--color-danger);
}

.err {
  color: var(--color-danger);
  font-size: 0.84rem;
}

.pollAttachmentHint {
  color: var(--muted);
  font-size: 0.76rem;
}

.autocompletePopover {
  position: fixed;
  z-index: 1000;
  background: var(--color-bg);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 22px rgb(0 0 0 / 0.2);
  max-width: 300px;
  overflow: hidden;
}

.modalBackdrop {
  position: fixed;
  inset: 0;
  z-index: 95;
  background: rgb(0 0 0 / 0.45);
  display: grid;
  place-items: center;
  padding: 1rem;
}

.modalCard {
  width: min(100%, 620px);
  max-height: 80vh;
  overflow: auto;
  border-radius: 14px;
  border: 1px solid var(--soft-border);
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 0.8rem;
  display: grid;
  gap: 0.65rem;
}

.modalHead {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modalHead h3 {
  margin: 0;
  font-size: 1rem;
  color: var(--surface);
}

.modalClose {
  border: 1px solid var(--soft-border);
  border-radius: 8px;
  background: transparent;
  color: var(--surface);
  width: 28px;
  height: 28px;
}

.modalInput {
  border: 1px solid var(--soft-border);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.46);
  color: var(--surface);
  padding: 0.52rem 0.65rem;
}

.gifGrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}

.gifTile {
  border: 1px solid var(--soft-border);
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

.gifSkeleton {
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
  border: 1px solid var(--soft-border);
  border-radius: 10px;
  background: transparent;
  color: var(--surface);
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
  color: var(--muted);
}

.modalHint,
.modalError {
  margin: 0;
  font-size: 0.82rem;
}

.modalHint {
  color: var(--muted);
}

.modalError {
  color: var(--color-danger);
}

.autocompleteItem {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 0.75rem;
  cursor: pointer;
}

.autocompleteItem:hover,
.autocompleteItem.active {
  background: rgb(var(--color-primary-rgb) / 0.1);
}

.suggestionName {
  color: var(--surface);
  font-weight: 600;
  font-size: 0.9rem;
}

.suggestionCount {
  color: var(--muted);
  font-size: 0.8rem;
  margin-left: 0.5rem;
}

@media (max-width: 640px) {
  .composerCard {
    padding: 0.55rem 0.6rem 0.48rem;
  }

  .composerRow {
    grid-template-columns: 34px 1fr;
    gap: 0.45rem;
  }

  .avatar {
    width: 34px;
    height: 34px;
    font-size: 0.8rem;
  }

  .actionsBar {
    gap: 0.5rem;
  }

  .leftActions,
  .rightActions {
    gap: 0.45rem;
  }

  .counter {
    display: none;
  }

  .publishBtn {
    padding-inline: 0.8rem;
  }

  .gifGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
