<template>
  <section class="card">
    <div class="head">
      <div>
        <div class="title">Napíš reply</div>
        <div class="sub">Krátka odpoveď k príspevku (max 280 znakov).</div>
      </div>
    </div>

    <div class="row">
      <div class="avatar">
        <img
          v-if="avatarUrl"
          class="avatarImg"
          :src="avatarUrl"
          :alt="auth?.user?.name || 'avatar'"
        />
        <span v-else>{{ initials }}</span>
      </div>

      <div class="body">
        <textarea
          v-model="content"
          class="input"
          rows="3"
          maxlength="280"
          placeholder="Napíš reply…"
          @input="onTyping"
        />

        <!-- Media preview -->
        <div v-if="imagePreviewUrl" class="mediaCard">
          <img class="mediaImg" :src="imagePreviewUrl" alt="Preview" />
          <button class="removeMedia" type="button" :disabled="posting" @click="removeFile">
            ✕
          </button>
        </div>

        <!-- File chip (non-image) -->
        <div v-else-if="file" class="fileChip">
          <div class="fileLeft">
            <span class="clip">📎</span>
            <div class="fileText">
              <div class="fileName">{{ file.name }}</div>
              <div class="fileMeta">{{ prettySize(file.size) }}</div>
            </div>
          </div>

          <button class="fileRemove" type="button" :disabled="posting" @click="removeFile">
            Odstrániť
          </button>
        </div>

        <div class="bar">
          <div class="left">
            <input
              ref="fileInput"
              type="file"
              class="fileInput"
              :accept="accept"
              @change="onFileChange"
            />
            <button class="ghostbtn" type="button" :disabled="posting" @click="pickFile">
              Pridať prílohu
            </button>
          </div>

          <div class="right">
            <div class="counter" :class="{ warn: remaining <= 20 && remaining >= 0, bad: remaining < 0 }">
              {{ content.length }}/280
            </div>

            <button class="actionbtn" type="button" :disabled="isSubmitDisabled" @click="submit">
              {{ posting ? 'Odosielam…' : 'Odoslať reply' }}
            </button>
          </div>
        </div>

        <div v-if="err" class="err">{{ err }}</div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['created'])

const props = defineProps({
  parentId: { type: [Number, String], required: true },

  // držíme sa backendu (max 5MB + mimes)
  accept: { type: String, default: 'image/*,.pdf,.txt,.doc,.docx' },
  maxBytes: { type: Number, default: 5 * 1024 * 1024 }, // 5MB
})

const auth = useAuthStore()

const content = ref('')
const file = ref(null)
const imagePreviewUrl = ref(null)
const posting = ref(false)
const err = ref('')

const fileInput = ref(null)

const initials = computed(() => {
  const n = auth?.user?.name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
})

const remaining = computed(() => 280 - content.value.length)

const avatarUrl = computed(() => {
  const raw = auth?.user?.avatar_url || auth?.user?.avatarUrl || ''
  if (!raw) return ''
  if (/^https?:\/\//i.test(raw)) return raw

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')

  if (raw.startsWith('/')) return origin + raw
  return origin + '/' + raw
})

const isSubmitDisabled = computed(() => {
  if (posting.value) return true
  if (!content.value.trim()) return true
  if (content.value.length > 280) return true
  return false
})

function onTyping() {
  if (err.value && content.value.length <= 280) err.value = ''
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
  // frontend check len ako UX; backend je zdroj pravdy
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

  if (f.size > props.maxBytes) {
    removeFile()
    err.value = `Súbor je príliš veľký. Max ${prettySize(props.maxBytes)}.`
    return
  }

  if (!isAllowedByMvp(f)) {
    removeFile()
    err.value = 'Nepovolený typ súboru.'
    return
  }

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
  err.value = ''
  posting.value = true

  try {
    const fd = new FormData()
    fd.append('content', content.value)
    if (file.value) fd.append('attachment', file.value)

    const res = await api.post(`/posts/${props.parentId}/reply`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    emit('created', res.data)

    content.value = ''
    removeFile()
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) err.value = 'Pre odoslanie reply sa prihlás.'
    else if (status === 422) err.value = 'Skontroluj text (1–280) a typ/veľkosť prílohy.'
    else err.value = e?.response?.data?.message || 'Odoslanie reply zlyhalo.'
  } finally {
    posting.value = false
  }
}

onBeforeUnmount(() => revokePreview())
</script>

<style scoped>
.card {
  border: 1px solid var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.55);
  border-radius: 1.5rem;
  padding: 1.15rem;
}
.head { display: flex; justify-content: space-between; gap: 1rem; }
.title { font-size: 1.05rem; font-weight: 900; color: var(--color-surface); }
.sub { margin-top: 0.25rem; color: var(--color-text-secondary); font-size: 0.9rem; }

.row { display: grid; grid-template-columns: 56px 1fr; gap: 0.85rem; margin-top: 0.85rem; }
.avatar {
  width: 56px; height: 56px; border-radius: 999px;
  display: grid; place-items: center;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: var(--color-surface); font-weight: 900; font-size: 1.05rem;
  overflow: hidden;
}
.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.body { display: grid; gap: 0.6rem; }

.input {
  width: 100%;
  padding: 0.75rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-bg-rgb) / 0.25);
  color: var(--color-surface);
  outline: none;
  resize: vertical;
}
.input:focus { border-color: rgb(var(--color-primary-rgb) / 0.9); }

.mediaCard {
  position: relative;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
  border-radius: 1rem;
  overflow: hidden;
  background: rgb(var(--color-bg-rgb) / 0.25);
}
.mediaImg { width: 100%; max-height: 360px; object-fit: cover; display: block; }
.removeMedia {
  position: absolute; top: 10px; right: 10px;
  width: 34px; height: 34px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-bg-rgb) / 0.65);
  color: var(--color-surface);
}
.removeMedia:hover { background: rgb(var(--color-bg-rgb) / 0.85); }
.removeMedia:disabled { opacity: .6; cursor: not-allowed; }

.fileChip {
  display: flex; align-items: center; justify-content: space-between;
  gap: .75rem;
  padding: .75rem .85rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
  background: rgb(var(--color-bg-rgb) / 0.25);
}
.fileLeft { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.clip { opacity: .9; }
.fileText { min-width: 0; }
.fileName { color: var(--color-surface); font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 520px; }
.fileMeta { color: var(--color-text-secondary); font-size: .85rem; margin-top: .15rem; }
.fileRemove {
  padding: .45rem .7rem; border-radius: .9rem;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.5);
  background: rgb(var(--color-danger-rgb) / 0.12);
  color: var(--color-danger);
}
.fileRemove:hover { background: rgb(var(--color-danger-rgb) / 0.2); }
.fileRemove:disabled { opacity: .6; cursor: not-allowed; }

.bar { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
.left { display: flex; align-items: center; gap: .6rem; }
.right { display: flex; align-items: center; gap: .75rem; }

.fileInput { display: none; }

.counter { color: var(--color-text-secondary); font-size: 0.85rem; }
.counter.warn { color: var(--color-primary); }
.counter.bad { color: var(--color-danger); }

.actionbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
}
.actionbtn:hover { background: rgb(var(--color-primary-rgb) / 0.28); }
.actionbtn:disabled { opacity: 0.55; cursor: not-allowed; }

.ghostbtn {
  padding: 0.55rem 0.85rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
}
.ghostbtn:hover { border-color: var(--color-primary); color: var(--color-surface); background: rgb(var(--color-primary-rgb) / 0.08); }
.ghostbtn:disabled { opacity: 0.6; cursor: not-allowed; }

.err { color: var(--color-danger); font-size: .95rem; }

@media (max-width: 480px) {
  .card { padding: 0.9rem; }
  .row { grid-template-columns: 1fr; }
  .avatar { width: 44px; height: 44px; font-size: 0.9rem; }
  .bar { flex-direction: column; align-items: stretch; }
  .left, .right { justify-content: space-between; width: 100%; }
  .actionbtn, .ghostbtn { min-height: 44px; }
  .fileName { max-width: 200px; }
}
</style>
