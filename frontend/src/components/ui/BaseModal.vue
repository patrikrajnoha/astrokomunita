<template>
  <teleport to="body">
    <transition name="modal-fade">
      <div
        v-if="open"
        class="modalRoot"
        :data-testid="testId"
        @mousedown="onBackdropMouseDown"
        @click="onBackdropClick"
      >
        <transition name="modal-pop">
          <section
            v-if="open"
            ref="dialogRef"
            class="modalCard"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            @click.stop
          >
            <header class="modalHead">
              <div class="modalHeading">
                <h2 :id="titleId" class="modalTitle">{{ title }}</h2>
                <slot name="description" />
              </div>

              <button
                ref="closeButtonRef"
                type="button"
                class="ui-pill ui-pill--secondary ui-pill--icon modalClose"
                :data-testid="closeTestId"
                aria-label="Close"
                @click="emitClose"
              >
                x
              </button>
            </header>

            <div class="modalBody">
              <slot />
            </div>
          </section>
        </transition>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  testId: {
    type: String,
    default: 'base-modal',
  },
  closeTestId: {
    type: String,
    default: 'base-modal-close',
  },
  closeOnOverlay: {
    type: Boolean,
    default: true,
  },
  closeOnEsc: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:open', 'close', 'open'])

const dialogRef = ref(null)
const closeButtonRef = ref(null)
const pressedBackdrop = ref(false)
let previousActive = null

const titleId = `base-modal-title-${Math.random().toString(36).slice(2, 9)}`

watch(
  () => props.open,
  async (isOpen) => {
    if (typeof document === 'undefined') return

    if (isOpen) {
      previousActive = document.activeElement
      document.body.style.overflow = 'hidden'
      window.addEventListener('keydown', onKeydown)
      emit('open')
      await nextTick()
      focusInitial()
      return
    }

    document.body.style.removeProperty('overflow')
    window.removeEventListener('keydown', onKeydown)
    if (previousActive && typeof previousActive.focus === 'function') {
      previousActive.focus()
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') {
    document.body.style.removeProperty('overflow')
  }
  window.removeEventListener('keydown', onKeydown)
})

function emitClose() {
  emit('update:open', false)
  emit('close')
}

function onBackdropMouseDown(event) {
  pressedBackdrop.value = event.target === event.currentTarget
}

function onBackdropClick(event) {
  if (!props.closeOnOverlay) return
  if (!pressedBackdrop.value || event.target !== event.currentTarget) return
  emitClose()
}

function onKeydown(event) {
  if (!props.open) return

  if (event.key === 'Escape') {
    if (!props.closeOnEsc) return
    event.preventDefault()
    emitClose()
    return
  }

  if (event.key !== 'Tab') return
  trapFocus(event)
}

function focusInitial() {
  const root = dialogRef.value
  if (!root) return

  const focusables = getFocusableElements(root)
  if (!focusables.length) {
    closeButtonRef.value?.focus()
    return
  }

  focusables[0].focus()
}

function trapFocus(event) {
  const root = dialogRef.value
  if (!root) return

  const focusables = getFocusableElements(root)
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

function getFocusableElements(root) {
  return Array.from(
    root.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  )
}
</script>

<style scoped>
.modalRoot {
  position: fixed;
  inset: 0;
  z-index: 1400;
  display: grid;
  place-items: center;
  background: rgb(0 0 0 / 0.66);
  padding: 1rem;
}

.modalCard {
  width: min(42rem, 100%);
  max-height: min(90vh, 48rem);
  overflow: auto;
  border: 1px solid var(--border);
  border-radius: 1.5rem;
  background: var(--bg-surface);
  color: var(--text-primary);
  box-shadow: 0 18px 40px rgb(var(--bg-app-rgb) / 0.34);
}

.modalHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.25rem 0;
}

.modalHeading {
  min-width: 0;
}

.modalTitle {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-primary);
}

.modalClose {
  flex-shrink: 0;
}

.modalBody {
  padding: 1rem 1.25rem 1.25rem;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 180ms ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-pop-enter-active,
.modal-pop-leave-active {
  transition: transform 190ms ease, opacity 190ms ease;
}

.modal-pop-enter-from,
.modal-pop-leave-to {
  transform: translateY(14px) scale(0.985);
  opacity: 0;
}
</style>
