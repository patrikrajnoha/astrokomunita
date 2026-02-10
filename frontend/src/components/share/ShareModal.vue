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
          <img class="preview-image" :src="generated.dataUrl" alt="Nahlad zdielaneho obrazku" />

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

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import { buildPostUrl, generateShareImage, shareLink } from '@/utils/sharePost'

type GenericRecord = Record<string, any>

type GeneratedAsset = {
  blob: Blob
  file: File
  dataUrl: string
}

const props = defineProps<{
  open: boolean
  post: GenericRecord | null
}>()

const emit = defineEmits<{
  close: []
}>()

const { showToast } = useToast()

const dialogRef = ref<HTMLElement | null>(null)
const generating = ref(false)
const generated = ref<GeneratedAsset | null>(null)

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
  const first = node.querySelector<HTMLElement>('button:not(:disabled)')
  first?.focus()
}

function onGlobalKeydown(event: KeyboardEvent) {
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
    node.querySelectorAll<HTMLElement>(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  )

  if (!focusables.length) return

  const first = focusables[0]
  const last = focusables[focusables.length - 1]
  const active = document.activeElement as HTMLElement | null

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
    showToast('Nepodarilo sa zdielat odkaz.', 'error')
  }
}

async function onCopyLink() {
  if (!props.post) return
  const url = buildPostUrl(props.post)

  if (!url) {
    showToast('Nepodarilo sa vytvorit odkaz.', 'error')
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
    showToast('Obrazok je pripraveny.', 'success')
  } catch {
    showToast('Generovanie obrazka zlyhalo. Skus odkaz.', 'error')
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
        title: String(props.post?.title || 'Prispevok'),
        text: 'Zdielam post z Astrokomunity',
        files: [generated.value.file],
      })
      return
    } catch (error: any) {
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
  showToast('PNG bolo stiahnute.', 'success')
}
</script>

<style scoped>
.share-modal-root {
  position: fixed;
  inset: 0;
  z-index: 1150;
  background: rgba(3, 7, 18, 0.6);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 16px;
}

.share-sheet {
  width: min(520px, 100%);
  max-height: min(88vh, 820px);
  overflow: auto;
  border-radius: 18px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.98);
  color: var(--color-surface);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
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
}

.icon-close {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 16px;
  cursor: pointer;
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
  border-radius: 10px;
  border: 1px solid transparent;
  font-size: 14px;
  font-weight: 600;
  padding: 0 14px;
  cursor: pointer;
}

.share-btn {
  flex: 1;
}

.share-btn:disabled {
  opacity: 0.7;
  cursor: wait;
}

.share-btn--primary {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: #fff;
}

.share-btn--secondary,
.copy-link {
  background: transparent;
  border-color: rgb(var(--color-text-secondary-rgb) / 0.34);
  color: var(--color-surface);
}

.preview-wrap {
  margin-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preview-image {
  width: 100%;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: #0b0f14;
}

.share-btn:focus,
.copy-link:focus,
.icon-close:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

@media (min-width: 760px) {
  .share-modal-root {
    align-items: center;
  }
}
</style>
