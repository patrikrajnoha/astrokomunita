<template>
  <section class="composerCard">
    <header class="composerHead">
      <div class="titleWrap">
        <div class="title">Zdielaj pozorovanie</div>
        <div class="sub">Kratky prispevok do feedu (max 2000 znakov).</div>
      </div>
    </header>

    <div class="composerRow">
      <div class="avatar" aria-hidden="true">
        <img
          v-if="avatarUrl"
          class="avatarImg"
          :src="avatarUrl"
          :alt="auth?.user?.name || 'avatar'"
        />
        <span v-else>{{ initials }}</span>
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
          placeholder="Co sa deje na oblohe?"
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
              class="attachBtn"
              type="button"
              :disabled="posting"
              aria-label="Pridat prilohu"
              @click="pickFile"
            >
              <svg class="btnIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="m21.4 11-8.5 8.5a5.5 5.5 0 1 1-7.8-7.8l8.9-8.9a3.5 3.5 0 1 1 5 5l-9 9a1.5 1.5 0 0 1-2.1-2.1l8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <span class="attachText">Pridat prilohu</span>
            </button>
          </div>

          <div class="rightActions">
            <div class="counter" :class="{ warn: content.length > 1800 && content.length <= 2000, bad: content.length > 2000 }">
              {{ content.length }}/2000
            </div>

            <button class="publishBtn" type="button" :disabled="isSubmitDisabled" aria-label="Publikovat" @click="submit">
              <svg class="btnIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M22 2 11 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M22 2 15 22l-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <span>{{ posting ? 'Publikujem...' : 'Publikovat' }}</span>
            </button>
          </div>
        </div>

        <div v-if="err" class="err">{{ err }}</div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, nextTick } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['created'])

const props = defineProps({
  accept: { type: String, default: 'image/*,.pdf,.txt,.doc,.docx' },
  maxBytes: { type: Number, default: 5 * 1024 * 1024 },
})

const auth = useAuthStore()

const content = ref('')
const file = ref(null)
const imagePreviewUrl = ref(null)
const posting = ref(false)
const err = ref('')
const isFocused = ref(false)

const showAutocomplete = ref(false)
const suggestions = ref([])
const selectedIndex = ref(0)
const autocompletePosition = ref({ top: 0, left: 0 })
const currentHashtagStart = ref(0)
const debounceTimer = ref(null)
const suggestionCache = ref(new Map())

const fileInput = ref(null)
const textareaRef = ref(null)

const initials = computed(() => {
  const n = auth?.user?.name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
})

const avatarUrl = computed(() => {
  const raw = auth?.user?.avatar_url || auth?.user?.avatarUrl || ''
  if (!raw) return ''
  if (/^https?:\/\//i.test(raw)) return raw

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')

  if (raw.startsWith('/')) return origin + raw
  return origin + '/' + raw
})

const isExpanded = computed(() => isFocused.value || content.value.trim().length > 0 || !!file.value)

const isSubmitDisabled = computed(() => {
  if (posting.value) return true
  if (!content.value.trim()) return true
  if (content.value.length > 2000) return true
  return false
})

function onFocus() {
  isFocused.value = true
}

function onTyping(event) {
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
    top: rect.top + window.scrollY + (currentLine * lineHeightPx) + lineHeightPx,
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
  setTimeout(hideAutocomplete, 200)
}

function pickFile() {
  fileInput.value?.click()
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
  err.value = ''
  posting.value = true

  try {
    const fd = new FormData()
    fd.append('content', content.value.trim())
    if (file.value) fd.append('attachment', file.value)

    const res = await api.post('/posts', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    emit('created', res.data)

    content.value = ''
    isFocused.value = false
    removeFile()
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) err.value = 'Pre publikovanie sa prihlas.'
    else if (status === 422) err.value = 'Skontroluj text (1-2000) a typ/velkost prilohy.'
    else err.value = e?.response?.data?.message || 'Publikovanie zlyhalo.'
  } finally {
    posting.value = false
  }
}

onBeforeUnmount(() => revokePreview())
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

  border: 1px solid var(--border);
  background: linear-gradient(165deg, rgb(var(--color-bg-rgb) / 0.7), rgb(var(--color-bg-rgb) / 0.52));
  border-radius: 14px;
  padding: 0.85rem;
  box-shadow: 0 6px 20px rgb(0 0 0 / 0.12);
}

.composerHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.title {
  font-size: 0.98rem;
  font-weight: 800;
  color: var(--surface);
}

.sub {
  margin-top: 0.18rem;
  color: var(--muted);
  font-size: 0.78rem;
}

.composerRow {
  display: grid;
  grid-template-columns: 42px 1fr;
  align-items: flex-start;
  gap: 0.65rem;
  margin-top: 0.7rem;
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
  gap: 0.55rem;
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
  min-height: 48px;
  max-height: 48px;
  padding: 0.62rem 0.72rem;
  border-radius: 12px;
  border: 1px solid var(--soft-border);
  background: var(--panel);
  color: var(--surface);
  outline: none;
  resize: none;
  overflow-y: auto;
  line-height: 1.45;
  transition: max-height 0.2s ease, min-height 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.composerInput::placeholder {
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.composerInput.expanded {
  min-height: 118px;
  max-height: 190px;
  background: var(--panel-strong);
}

.composerInput:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.18);
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

.actionsBar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
}

.leftActions,
.rightActions {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
}

.fileInput {
  display: none;
}

.attachBtn,
.publishBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-height: 34px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 700;
  transition: background-color 0.16s ease, border-color 0.16s ease, opacity 0.16s ease;
}

.attachBtn {
  border: 1px solid var(--border);
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--surface);
  padding: 0.38rem 0.6rem;
}

.publishBtn {
  border: 1px solid var(--primary);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--surface);
  padding: 0.38rem 0.66rem;
}

.attachBtn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
}

.publishBtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.28);
}

.attachBtn:disabled,
.publishBtn:disabled,
.removeMedia:disabled,
.fileRemove:disabled {
  opacity: 0.52;
  cursor: not-allowed;
}

.btnIcon {
  width: 0.92rem;
  height: 0.92rem;
  flex: 0 0 auto;
}

.counter {
  font-size: 0.74rem;
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
    border-radius: 12px;
    padding: 0.75rem;
  }

  .composerRow {
    grid-template-columns: 38px 1fr;
    gap: 0.55rem;
  }

  .avatar {
    width: 36px;
    height: 36px;
    font-size: 0.8rem;
  }

  .attachText {
    display: none;
  }

  .actionsBar {
    gap: 0.5rem;
  }

  .leftActions,
  .rightActions {
    gap: 0.45rem;
  }

  .publishBtn span {
    font-size: 0.78rem;
  }
}
</style>
