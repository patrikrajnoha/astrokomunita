<template>
  <teleport to="body">
    <div v-if="open" class="share-modal-root" @click="close">
      <section
        ref="dialogRef"
        class="share-sheet"
        role="dialog"
        aria-modal="true"
        aria-labelledby="share-title"
        @click.stop
      >
        <header class="share-sheet-head">
          <h2 id="share-title">Zdieľať</h2>
          <button type="button" class="icon-close" aria-label="Zavrieť zdieľanie" @click="close">x</button>
        </header>

        <div class="share-actions-row">
          <button
            type="button"
            class="share-btn share-btn--primary"
            aria-label="Zdieľať odkaz"
            @click="onShareLink"
          >
            Odkaz
          </button>
          <button
            type="button"
            class="share-btn share-btn--secondary"
            :disabled="generating"
            aria-label="Vytvoriť obrázok"
            @click="onGenerateImage"
          >
            {{ generating ? 'Generujem obrázok...' : 'Obrázok' }}
          </button>
        </div>

        <div class="share-actions-secondary">
          <button type="button" class="copy-link" aria-label="Kopírovať odkaz" @click="onCopyLink">
            Kopírovať odkaz
          </button>
        </div>

        <div v-if="generated?.dataUrl" class="preview-wrap">
          <img class="preview-image" :src="generated.dataUrl" alt="Náhľad zdieľaného obrázku" />

          <button
            v-if="canShareFile"
            type="button"
            class="share-btn share-btn--primary"
            aria-label="Zdieľať obrázok"
            @click="onShareGenerated"
          >
            Zdieľať obrázok
          </button>
          <button
            v-else
            type="button"
            class="share-btn share-btn--secondary"
            aria-label="Stiahnuť PNG"
            @click="onDownloadGenerated"
          >
            Stiahnuť PNG
          </button>
        </div>
      </section>
    </div>
  </teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import { buildPostUrl, generateShareImage, shareLink } from '@/utils/sharePost'

const props = defineProps({
  open: { type: Boolean, default: false },
  post: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const { showToast } = useToast()

const dialogRef = ref(null)
const generating = ref(false)
const generated = ref(null)

const canShareFile = computed(() => {
  if (!generated.value?.file) return false
  if (typeof navigator === 'undefined') return false
  if (typeof navigator.canShare !== 'function') return false
  return navigator.canShare({ files: [generated.value.file] })
})

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      generated.value = null
      generating.value = false
      window.removeEventListener('keydown', onGlobalKeydown)
      document.body.style.removeProperty('overflow')
      return
    }

    await nextTick()
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', onGlobalKeydown)
    focusFirstControl()
  },
)

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
  document.body.style.removeProperty('overflow')
})

function close() {
  emit('close')
}

function focusFirstControl() {
  const node = dialogRef.value
  if (!node) return
  const first = node.querySelector('button:not(:disabled)')
  first?.focus()
}

function onGlobalKeydown(event) {
  if (!props.open) return

  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }

  if (event.key !== 'Tab') return

  const node = dialogRef.value
  if (!node) return

  const focusables = Array.from(
    node.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  )

  if (!focusables.length) return

  const first = focusables[0]
  const last = focusables[focusables.length - 1]
  const active = document.activeElement

  if (event.shiftKey && active === first) {
    event.preventDefault()
    last.focus()
    return
  }

  if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

async function onShareLink() {
  if (!props.post) return

  try {
    await shareLink(props.post)
    showToast('Odkaz je pripraveny.', 'success')
  } catch {
    showToast('Nepodarilo sa zdieľať odkaz.', 'error')
  }
}

async function onCopyLink() {
  if (!props.post) return
  const url = buildPostUrl(props.post)

  if (!url) {
    showToast('Nepodarilo sa vytvoriť odkaz.', 'error')
    return
  }

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(url)
    } else {
      const input = document.createElement('textarea')
      input.value = url
      input.setAttribute('readonly', '')
      input.style.position = 'fixed'
      input.style.left = '-9999px'
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
    }

    showToast('Odkaz bol skopirovany.', 'success')
  } catch {
    showToast('Kopirovanie odkazu zlyhalo.', 'error')
  }
}

async function onGenerateImage() {
  if (!props.post || generating.value) return

  generating.value = true
  try {
    generated.value = await generateShareImage(props.post)
    showToast('Obrázok je pripravený.', 'success')
  } catch {
    showToast('Generovanie obrázka zlyhalo. Skús odkaz.', 'error')
    await onCopyLink()
  } finally {
    generating.value = false
  }
}

async function onShareGenerated() {
  if (!props.post || !generated.value) {
    await onGenerateImage()
  }

  if (!generated.value) return

  if (canShareFile.value && typeof navigator.share === 'function') {
    try {
      await navigator.share({
        title: String(props.post?.title || 'Príspevok'),
        text: 'Zdieľam post z Astrokomunity',
        files: [generated.value.file],
      })
      return
    } catch (error) {
      if (error?.name === 'AbortError') return
    }
  }

  onDownloadGenerated()
}

function onDownloadGenerated() {
  if (!generated.value) return

  const link = document.createElement('a')
  link.href = generated.value.dataUrl
  link.download = generated.value.file.name
  link.rel = 'noopener'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  showToast('PNG bolo stiahnuté.', 'success')
}
</script>

<style scoped>
.share-modal-root {
  position: fixed;
  inset: 0;
  z-index: 1150;
  background: rgb(6 10 16 / 0.72);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 16px;
}

.share-sheet {
  width: min(520px, 100%);
  max-height: min(88vh, 820px);
  overflow: auto;
  border-radius: 24px;
  border: 0;
  background: #151d28;
  color: #ffffff;
  box-shadow: none;
  padding: 16px;
}

.share-sheet-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.share-sheet-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
}

.icon-close {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  border: 0;
  box-shadow: none;
  background: #222e3f;
  color: #abb8c9;
  font-size: 16px;
  cursor: pointer;
  transition: background-color 140ms ease, color 140ms ease;
}

.icon-close:hover {
  background: #1c2736;
  color: #ffffff;
}

.share-actions-row {
  display: flex;
  gap: 10px;
}

.share-actions-secondary {
  margin-top: 12px;
}

.share-btn,
.copy-link {
  min-height: 40px;
  border-radius: 999px;
  border: 0;
  box-shadow: none;
  font-size: 14px;
  font-weight: 500;
  padding: 0 14px;
  cursor: pointer;
  transition: background-color 140ms ease, color 140ms ease, opacity 140ms ease;
}

.share-btn {
  flex: 1;
}

.share-btn:disabled {
  opacity: 0.7;
  cursor: wait;
}

.share-btn--primary {
  background: #0f73ff;
  color: #ffffff;
}

.share-btn--primary:hover:not(:disabled) {
  background: #0d65e6;
}

.share-btn--secondary,
.copy-link {
  background: #222e3f;
  color: #abb8c9;
}

.share-btn--secondary:hover:not(:disabled),
.copy-link:hover:not(:disabled) {
  background: #1c2736;
  color: #ffffff;
}

.preview-wrap {
  margin-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preview-image {
  width: 100%;
  border-radius: 14px;
  border: 0;
  background: #1c2736;
}

.share-btn:focus-visible,
.copy-link:focus-visible,
.icon-close:focus-visible {
  outline: 2px solid #0f73ff;
  outline-offset: 2px;
}

@media (min-width: 760px) {
  .share-modal-root {
    align-items: center;
  }
}
</style>
