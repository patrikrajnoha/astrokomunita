<template>
  <teleport to="body">
    <transition name="confirm-fade">
      <div v-if="state.open" class="confirmRoot" @mousedown="onBackdropMouseDown" @click="onBackdropClick">
        <transition name="confirm-pop">
          <section
            v-if="state.open"
            ref="dialogRef"
            class="confirmCard"
            :class="{ danger: state.options.variant === 'danger' }"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            :aria-describedby="messageId"
            @click.stop
          >
            <header class="confirmHead">
              <span class="confirmIcon" aria-hidden="true">
                <svg v-if="state.options.variant === 'danger'" viewBox="0 0 20 20" fill="none">
                  <path d="M10 2.8 2.8 16.8h14.4L10 2.8Z" />
                  <path d="M10 7.5v4.2" />
                  <circle cx="10" cy="14.1" r="0.85" fill="currentColor" />
                </svg>
                <svg v-else viewBox="0 0 20 20" fill="none">
                  <circle cx="10" cy="10" r="8" />
                  <path d="M10 8v5" />
                  <circle cx="10" cy="5.7" r="0.85" fill="currentColor" />
                </svg>
              </span>

              <div>
                <h2 :id="titleId" class="confirmTitle">{{ state.options.title }}</h2>
                <p v-if="state.options.message" :id="messageId" class="confirmMessage">{{ state.options.message }}</p>
              </div>
            </header>

            <div v-if="state.mode === 'prompt'" class="confirmBody">
              <textarea
                v-if="state.options.multiline"
                ref="inputRef"
                v-model="state.value"
                class="confirmInput"
                :placeholder="state.options.placeholder || ''"
                rows="4"
              ></textarea>
              <input
                v-else
                ref="inputRef"
                v-model="state.value"
                class="confirmInput"
                type="text"
                :placeholder="state.options.placeholder || ''"
              />
            </div>

            <footer class="confirmActions">
              <button ref="cancelButtonRef" type="button" class="btn cancel" @click="cancel">
                {{ state.options.cancelText }}
              </button>
              <button
                ref="confirmButtonRef"
                type="button"
                class="btn confirm"
                :class="{ danger: state.options.variant === 'danger' }"
                :disabled="confirmDisabled"
                @click="confirmProceed"
              >
                {{ state.options.confirmText }}
              </button>
            </footer>
          </section>
        </transition>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const { state, confirmProceed, cancel } = useConfirm()

const dialogRef = ref(null)
const inputRef = ref(null)
const cancelButtonRef = ref(null)
const confirmButtonRef = ref(null)
const pressedBackdrop = ref(false)
let previousActive = null

const titleId = 'confirm-dialog-title'
const messageId = 'confirm-dialog-message'

const confirmDisabled = computed(() => {
  if (state.mode !== 'prompt') return false
  if (!state.options.required) return false
  return String(state.value || '').trim() === ''
})

watch(
  () => state.open,
  async (isOpen) => {
    if (isOpen) {
      previousActive = document.activeElement
      document.body.style.overflow = 'hidden'
      window.addEventListener('keydown', onKeydown)
      await nextTick()
      focusInitial()
      return
    }

    window.removeEventListener('keydown', onKeydown)
    document.body.style.removeProperty('overflow')
    if (previousActive && typeof previousActive.focus === 'function') {
      previousActive.focus()
    }
  },
)

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.style.removeProperty('overflow')
})

function focusInitial() {
  if (state.mode === 'prompt') {
    inputRef.value?.focus()
    return
  }

  if (state.options.variant === 'danger') {
    cancelButtonRef.value?.focus()
  } else {
    confirmButtonRef.value?.focus()
  }
}

function onBackdropMouseDown(event) {
  pressedBackdrop.value = event.target === event.currentTarget
}

function onBackdropClick(event) {
  if (!pressedBackdrop.value || event.target !== event.currentTarget) return
  if (state.options.closeOnBackdrop === false) return
  cancel()
}

function onKeydown(event) {
  if (!state.open) return

  if (event.key === 'Escape') {
    if (state.options.closeOnEsc === false) return
    event.preventDefault()
    cancel()
    return
  }

  if (event.key === 'Enter') {
    if (state.mode === 'prompt') {
      const isTextArea = state.options.multiline || event.target?.tagName === 'TEXTAREA'
      if (isTextArea) return
      event.preventDefault()
      if (!confirmDisabled.value) {
        confirmProceed()
      }
      return
    }

    if (state.options.variant === 'danger') return

    event.preventDefault()
    confirmProceed()
    return
  }

  if (event.key !== 'Tab') return
  trapFocus(event)
}

function trapFocus(event) {
  const root = dialogRef.value
  if (!root) return

  const focusables = Array.from(
    root.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
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
.confirmRoot {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: grid;
  place-items: center;
  background: rgb(4 10 18 / 0.62);
  padding: 1rem;
}

.confirmCard {
  width: min(520px, 100%);
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.38);
  background: rgb(var(--color-bg-rgb) / 0.98);
  color: var(--color-surface);
  box-shadow: 0 28px 56px rgb(0 0 0 / 0.42);
  padding: 1rem;
}

.confirmCard.danger {
  border-color: rgb(var(--color-danger-rgb) / 0.44);
}

.confirmHead {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.65rem;
}

.confirmIcon {
  width: 1.25rem;
  height: 1.25rem;
  color: var(--color-primary);
  margin-top: 0.08rem;
}

.confirmCard.danger .confirmIcon {
  color: var(--color-danger);
}

.confirmIcon svg {
  width: 100%;
  height: 100%;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.confirmTitle {
  margin: 0;
  font-size: 1.02rem;
  font-weight: 800;
}

.confirmMessage {
  margin: 0.3rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  line-height: 1.35;
}

.confirmBody {
  margin-top: 0.85rem;
}

.confirmInput {
  width: 100%;
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.42);
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: var(--color-surface);
  padding: 0.58rem 0.65rem;
  font-size: 0.9rem;
  resize: vertical;
}

.confirmInput:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.confirmActions {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.55rem;
}

.btn {
  min-height: 2.15rem;
  border-radius: 0.68rem;
  font-size: 0.84rem;
  font-weight: 700;
  padding: 0.46rem 0.8rem;
}

.btn.cancel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.42);
  background: transparent;
  color: var(--color-surface);
}

.btn.confirm {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-primary);
}

.btn.confirm.danger {
  border-color: rgb(var(--color-danger-rgb) / 0.65);
  background: rgb(var(--color-danger-rgb) / 0.18);
  color: var(--color-danger);
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn:hover:not(:disabled) {
  filter: brightness(1.06);
}

.btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 170ms ease;
}

.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}

.confirm-pop-enter-active,
.confirm-pop-leave-active {
  transition: transform 190ms ease, opacity 190ms ease;
}

.confirm-pop-enter-from,
.confirm-pop-leave-to {
  transform: translateY(12px) scale(0.985);
  opacity: 0;
}
</style>
