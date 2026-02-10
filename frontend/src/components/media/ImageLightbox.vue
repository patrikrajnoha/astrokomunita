<template>
  <teleport to="body">
    <div v-if="open" class="lightbox-root" @click="close">
      <section
        ref="dialogRef"
        class="lightbox-panel"
        role="dialog"
        aria-modal="true"
        aria-label="Celý obrázok"
        @click.stop
      >
        <button
          ref="closeBtnRef"
          class="lightbox-close"
          type="button"
          aria-label="Zavrieť obrázok"
          @click="close"
        >
          x
        </button>
        <div class="lightbox-stage">
          <img :src="src" :alt="alt || 'Celý obrázok'" class="lightbox-image" />
        </div>
      </section>
    </div>
  </teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  src: { type: String, default: '' },
  alt: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const dialogRef = ref(null)
const closeBtnRef = ref(null)

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      window.removeEventListener('keydown', onKeydown)
      document.body.style.removeProperty('overflow')
      return
    }

    await nextTick()
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', onKeydown)
    closeBtnRef.value?.focus()
  },
)

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.style.removeProperty('overflow')
})

function close() {
  emit('close')
}

function onKeydown(event) {
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
</script>

<style scoped>
.lightbox-root {
  position: fixed;
  inset: 0;
  z-index: 1200;
  background: rgba(2, 6, 23, 0.84);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.lightbox-panel {
  position: relative;
  width: min(1200px, 100%);
  height: min(92vh, 980px);
  border-radius: 14px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.92);
  box-shadow: 0 24px 68px rgba(0, 0, 0, 0.45);
}

.lightbox-stage {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 22px;
  box-sizing: border-box;
}

.lightbox-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: 10px;
}

.lightbox-close {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 36px;
  height: 36px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.9);
  color: var(--color-surface);
  cursor: pointer;
  z-index: 2;
}

.lightbox-close:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}
</style>
