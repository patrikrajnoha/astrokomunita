<template>
  <section class="card">
    <div class="head">
      <div>
        <div class="title">Zdieľaj pozorovanie</div>
        <div class="sub">Krátky príspevok do feedu (max 280 znakov).</div>
      </div>
    </div>

    <div class="row">
      <div class="avatar">
        <span>{{ initials }}</span>
      </div>

      <div class="body">
        <textarea
          v-model="content"
          class="input"
          rows="3"
          maxlength="280"
          placeholder="Čo sa deje na oblohe?"
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
              {{ posting ? 'Publikujem…' : 'Publikovať' }}
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

  // reset predchádzajúceho stavu (pre konzistentné preview/input)
  removeFile()

  if (f.size > props.maxBytes) {
    err.value = `Súbor je príliš veľký. Max ${prettySize(props.maxBytes)}.`
    return
  }

  if (!isAllowedByMvp(f)) {
    err.value = 'Nepovolený typ súboru.'
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
    removeFile()
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) err.value = 'Pre publikovanie sa prihlás.'
    else if (status === 422) err.value = 'Skontroluj text (1–280) a typ/veľkosť prílohy.'
    else err.value = e?.response?.data?.message || 'Publikovanie zlyhalo.'
  } finally {
    posting.value = false
  }
}

onBeforeUnmount(() => revokePreview())
</script>

<style scoped>
.card {
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.55);
  border-radius: 1.5rem;
  padding: 1.15rem;
}
.head { display: flex; justify-content: space-between; gap: 1rem; }
.title { font-size: 1.05rem; font-weight: 900; color: rgb(226 232 240); }
.sub { margin-top: 0.25rem; color: rgb(148 163 184); font-size: 0.9rem; }

.row { display: grid; grid-template-columns: 56px 1fr; gap: 0.85rem; margin-top: 0.85rem; }
.avatar {
  width: 56px; height: 56px; border-radius: 999px;
  display: grid; place-items: center;
  border: 1px solid rgba(99, 102, 241, 0.6);
  background: rgba(99, 102, 241, 0.12);
  color: white; font-weight: 900; font-size: 1.05rem;
}
.body { display: grid; gap: 0.6rem; }

.input {
  width: 100%;
  padding: 0.75rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid rgba(51, 65, 85, 0.9);
  background: rgba(2, 6, 23, 0.25);
  color: rgb(226 232 240);
  outline: none;
  resize: vertical;
}
.input:focus { border-color: rgba(99, 102, 241, 0.9); }

.mediaCard {
  position: relative;
  border: 1px solid rgba(51, 65, 85, 0.6);
  border-radius: 1rem;
  overflow: hidden;
  background: rgba(2, 6, 23, 0.25);
}
.mediaImg { width: 100%; max-height: 360px; object-fit: cover; display: block; }
.removeMedia {
  position: absolute; top: 10px; right: 10px;
  width: 34px; height: 34px;
  border-radius: 999px;
  border: 1px solid rgba(51, 65, 85, 0.9);
  background: rgba(2, 6, 23, 0.65);
  color: rgb(226 232 240);
}
.removeMedia:hover { background: rgba(2, 6, 23, 0.85); }
.removeMedia:disabled { opacity: .6; cursor: not-allowed; }

.fileChip {
  display: flex; align-items: center; justify-content: space-between;
  gap: .75rem;
  padding: .75rem .85rem;
  border-radius: 1rem;
  border: 1px solid rgba(51, 65, 85, 0.6);
  background: rgba(2, 6, 23, 0.25);
}
.fileLeft { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.clip { opacity: .9; }
.fileText { min-width: 0; }
.fileName { color: rgb(226 232 240); font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 520px; }
.fileMeta { color: rgb(148 163 184); font-size: .85rem; margin-top: .15rem; }
.fileRemove {
  padding: .45rem .7rem; border-radius: .9rem;
  border: 1px solid rgba(248, 113, 113, 0.5);
  background: rgba(248, 113, 113, 0.12);
  color: rgb(254 202 202);
}
.fileRemove:hover { background: rgba(248, 113, 113, 0.2); }
.fileRemove:disabled { opacity: .6; cursor: not-allowed; }

.bar { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
.left { display: flex; align-items: center; gap: .6rem; }
.right { display: flex; align-items: center; gap: .75rem; }

.fileInput { display: none; }

.counter { color: rgb(100 116 139); font-size: 0.85rem; }
.counter.warn { color: rgb(251 191 36); }
.counter.bad { color: rgb(248 113 113); }

.actionbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(99 102 241);
  background: rgba(99, 102, 241, 0.16);
  color: white;
}
.actionbtn:hover { background: rgba(99, 102, 241, 0.28); }
.actionbtn:disabled { opacity: 0.55; cursor: not-allowed; }

.ghostbtn {
  padding: 0.55rem 0.85rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(51 65 85);
  color: rgb(203 213 225);
  background: rgba(15, 23, 42, 0.2);
}
.ghostbtn:hover { border-color: rgb(99 102 241); color: white; background: rgba(99, 102, 241, 0.08); }
.ghostbtn:disabled { opacity: 0.6; cursor: not-allowed; }

.err { color: rgb(254 202 202); font-size: .95rem; }
</style>
