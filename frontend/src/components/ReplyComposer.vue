<template>
  <section class="card" :class="{ compact }">
    <div class="head">
      <div>
        <div class="title">{{ compact ? 'Odpoved' : 'Napis reply' }}</div>
        <div class="sub">
          {{ compact ? 'Reaguj na komentar.' : 'Strucna odpoved k prispevku (max 2000 znakov).' }}
        </div>
      </div>
      <div class="limitChip">max 2000</div>
    </div>

    <div class="row">
      <div class="avatar" aria-hidden="true">
        <UserAvatar class="avatarImg" :user="auth?.user" :alt="auth?.user?.name || 'avatar'" />
      </div>

      <div class="body">
        <textarea
          ref="textareaEl"
          v-model="content"
          class="input"
          :rows="compact ? 2 : 3"
          maxlength="2000"
          :placeholder="placeholder"
          @input="onTyping"
          @keydown="onTextareaKeydown"
        />

        <div class="helperRow">
          <div class="shortcut">Ctrl/Cmd + Enter odosle reply</div>
          <div class="counter" :class="{ warn: remaining <= 200 && remaining >= 0, bad: remaining < 0 }">
            {{ content.length }}/2000
          </div>
        </div>

        <div v-if="!auth.isAuthed" class="authHint">
          Pre odoslanie reply sa prihlas.
        </div>

        <div v-if="selectedGif" class="mediaCard mediaCard--gif">
          <img class="mediaImg" :src="selectedGif.preview_url || selectedGif.original_url" :alt="selectedGif.title || 'GIF'" />
          <button class="removeMedia" type="button" :disabled="posting" @click="removeGif">
            x
          </button>
        </div>

        <div v-else-if="imagePreviewUrl" class="mediaCard">
          <img class="mediaImg" :src="imagePreviewUrl" alt="Preview" />
          <button class="removeMedia" type="button" :disabled="posting" @click="removeFile">
            x
          </button>
        </div>

        <div v-else-if="file" class="fileChip">
          <div class="fileLeft">
            <span class="clip">[file]</span>
            <div class="fileText">
              <div class="fileName">{{ file.name }}</div>
              <div class="fileMeta">{{ prettySize(file.size) }}</div>
            </div>
          </div>

          <button class="fileRemove" type="button" :disabled="posting" @click="removeFile">
            Odstranit
          </button>
        </div>

        <div class="bar">
          <div class="left mediaTools">
            <input
              ref="fileInput"
              type="file"
              class="fileInput"
              :accept="activePickerAccept"
              @change="onFileChange"
            />

            <button
              class="iconBtn"
              type="button"
              :class="{ active: !!file && !isGifFile(file) }"
              :disabled="posting"
              aria-label="Obrazok"
              title="Obrazok"
              @click="pickImage"
            >
              <svg viewBox="0 0 24 24" fill="none">
                <rect x="3.5" y="4.5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7" />
                <path
                  d="m7 15 3.2-3.2a1 1 0 0 1 1.4 0L14 14l2-2a1 1 0 0 1 1.4 0L20 14.6"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round"
                />
                <circle cx="9" cy="9" r="1.2" fill="currentColor" />
              </svg>
            </button>

            <button
              class="iconBtn"
              type="button"
              :class="{ active: !!selectedGif || isGifFile(file) }"
              :disabled="posting"
              aria-label="GIF"
              title="GIF"
              @click="pickGif"
            >
              <svg viewBox="0 0 24 24" fill="none">
                <rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7" />
                <path
                  d="M8 10.5h2.8M8 13.5h2.8M12 10.5h4.8M12 13.5h4.8"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round"
                />
              </svg>
            </button>

            <div ref="emojiWrapRef" class="popWrap">
              <button
                class="iconBtn"
                type="button"
                :class="{ active: showEmojiMenu }"
                :disabled="posting"
                aria-label="Emoji"
                title="Emoji"
                @click="toggleEmojiMenu"
              >
                <svg viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7" />
                  <path d="M9 10.5h.01M15 10.5h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                  <path
                    d="M8.8 14.2a4.2 4.2 0 0 0 6.4 0"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                  />
                </svg>
              </button>
              <div v-if="showEmojiMenu" class="emojiMenu">
                <button
                  v-for="emoji in EMOJI_SET"
                  :key="emoji"
                  class="emojiBtn"
                  type="button"
                  @click="insertEmoji(emoji)"
                >
                  {{ emoji }}
                </button>
              </div>
            </div>
          </div>

          <button class="actionbtn" type="button" :disabled="isSubmitDisabled" @click="submit">
            {{ posting ? 'Odosielam...' : 'Odoslat reply' }}
          </button>
        </div>

        <div v-if="err" class="err">{{ err }}</div>
      </div>
    </div>

    <div v-if="showGifModal" class="modalBackdrop" @click.self="closeGifModal">
      <section class="modalCard" role="dialog" aria-modal="true" aria-labelledby="reply-gif-modal-title">
        <header class="modalHead">
          <h3 id="reply-gif-modal-title">Vybrat GIF</h3>
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
          <div v-for="i in 6" :key="`reply-gif-sk-${i}`" class="gifSkeleton"></div>
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
  </section>
</template>

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

<style scoped>
.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  background:
    radial-gradient(circle at 100% 0%, rgb(var(--color-primary-rgb) / 0.14), transparent 50%),
    rgb(var(--color-bg-rgb) / 0.5);
  border-radius: 1.1rem;
  padding: 0.8rem;
}

.card.compact {
  border-radius: 0.95rem;
  padding: 0.68rem;
  background:
    radial-gradient(circle at 100% 0%, rgb(var(--color-primary-rgb) / 0.09), transparent 50%),
    rgb(var(--color-bg-rgb) / 0.44);
}

.head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.7rem;
}

.title {
  font-size: 0.9rem;
  font-weight: 900;
  color: var(--color-surface);
  line-height: 1.15;
}

.sub {
  margin-top: 0.16rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
  font-size: 0.76rem;
}

.limitChip {
  border-radius: 999px;
  padding: 0.18rem 0.46rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  color: var(--color-text-secondary);
  font-size: 0.67rem;
  font-weight: 800;
  white-space: nowrap;
}

.row {
  display: grid;
  grid-template-columns: 42px 1fr;
  gap: 0.66rem;
  margin-top: 0.58rem;
}

.avatar {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.12);
  overflow: hidden;
}

.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.body {
  display: grid;
  gap: 0.48rem;
}

.input {
  width: 100%;
  padding: 0.62rem 0.72rem;
  border-radius: 0.82rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.56);
  background: rgb(var(--color-bg-rgb) / 0.36);
  color: var(--color-surface);
  resize: none;
  min-height: 92px;
  line-height: 1.42;
  font-size: 0.92rem;
  transition: border-color 0.16s ease, box-shadow 0.16s ease;
}

.card.compact .input {
  min-height: 72px;
}

.input:focus {
  border-color: rgb(var(--color-primary-rgb) / 0.95);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.18);
  outline: none;
}

.helperRow {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.55rem;
}

.shortcut {
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 0.72rem;
}

.counter {
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  font-weight: 700;
}

.counter.warn {
  color: var(--color-primary);
}

.counter.bad {
  color: var(--color-danger);
}

.authHint {
  border-radius: 0.75rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.34);
  background: rgb(var(--color-bg-rgb) / 0.22);
  padding: 0.42rem 0.56rem;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

.mediaCard {
  position: relative;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  border-radius: 0.84rem;
  overflow: hidden;
  background: rgb(var(--color-bg-rgb) / 0.3);
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
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.72);
  background: rgb(var(--color-bg-rgb) / 0.66);
  color: var(--color-surface);
  font-weight: 800;
}

.removeMedia:hover {
  background: rgb(var(--color-bg-rgb) / 0.88);
}

.removeMedia:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fileChip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.55rem;
  padding: 0.6rem 0.68rem;
  border-radius: 0.82rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  background: rgb(var(--color-bg-rgb) / 0.3);
}

.fileLeft {
  display: flex;
  align-items: center;
  gap: 0.66rem;
  min-width: 0;
}

.clip {
  border-radius: 0.52rem;
  padding: 0.12rem 0.3rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  font-weight: 700;
}

.fileText {
  min-width: 0;
}

.fileName {
  color: var(--color-surface);
  font-weight: 800;
  font-size: 0.86rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 540px;
}

.fileMeta {
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  margin-top: 0.06rem;
}

.fileRemove {
  padding: 0.34rem 0.58rem;
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.5);
  background: rgb(var(--color-danger-rgb) / 0.12);
  color: var(--color-danger);
  font-size: 0.78rem;
  font-weight: 700;
}

.fileRemove:hover {
  background: rgb(var(--color-danger-rgb) / 0.2);
}

.fileRemove:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.56rem;
  flex-wrap: wrap;
}

.left {
  display: flex;
  align-items: center;
  gap: 0.62rem;
}

.mediaTools {
  gap: 0.42rem;
}

.popWrap {
  position: relative;
}

.emojiMenu {
  position: absolute;
  left: 0;
  bottom: calc(100% + 0.45rem);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 0.78rem;
  background: rgb(var(--color-bg-rgb) / 0.96);
  box-shadow: 0 14px 30px rgb(var(--color-bg-rgb) / 0.42);
  padding: 0.38rem;
  z-index: 7;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.2rem;
}

.emojiBtn {
  width: 1.9rem;
  height: 1.9rem;
  border: 1px solid transparent;
  border-radius: 0.55rem;
  background: transparent;
  font-size: 1rem;
  line-height: 1;
}

.emojiBtn:hover {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.4);
  background: rgb(var(--color-bg-rgb) / 0.55);
}

.fileInput {
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
  transition: border-color 0.16s ease, background 0.16s ease, color 0.16s ease;
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

.iconBtn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

.iconBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.actionbtn {
  padding: 0.5rem 0.76rem;
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.64);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 800;
  transition: border-color 0.16s ease, background 0.16s ease, transform 0.16s ease;
}

.actionbtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.28);
  border-color: rgb(var(--color-primary-rgb) / 0.8);
  transform: translateY(-1px);
}

.actionbtn:disabled {
  opacity: 0.52;
  cursor: not-allowed;
  transform: none;
}

.err {
  color: var(--color-danger);
  font-size: 0.82rem;
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
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
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
  color: var(--color-surface);
}

.modalClose {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 8px;
  background: transparent;
  color: var(--color-surface);
  width: 28px;
  height: 28px;
}

.modalInput {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.46);
  color: var(--color-surface);
  padding: 0.52rem 0.65rem;
}

.gifGrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}

.gifTile {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
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
  animation: replyGifShimmer 1.2s infinite;
}

.modalHint,
.modalError {
  margin: 0;
  font-size: 0.82rem;
}

.modalHint {
  color: var(--color-text-secondary);
}

.modalError {
  color: var(--color-danger);
}

@keyframes replyGifShimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

@media (max-width: 640px) {
  .card {
    padding: 0.68rem;
    border-radius: 1rem;
  }

  .sub {
    display: none;
  }

  .row {
    grid-template-columns: 1fr;
  }

  .avatar {
    width: 36px;
    height: 36px;
  }

  .helperRow {
    gap: 0.35rem;
  }

  .shortcut {
    font-size: 0.68rem;
  }

  .bar {
    flex-direction: column;
    align-items: stretch;
  }

  .left {
    justify-content: flex-start;
  }

  .actionbtn,
  .iconBtn {
    min-height: 38px;
  }

  .actionbtn {
    width: 100%;
  }

  .fileName {
    max-width: 220px;
  }

  .gifGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
