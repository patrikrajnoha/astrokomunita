<template>
  <teleport to="body">
    <transition name="confirm-fade">
      <div v-if="state.open" class="confirmModalRoot" @mousedown="onBackdropMouseDown" @click="onBackdropClick">
        <transition name="confirm-pop">
          <section
            v-if="state.open"
            ref="dialogRef"
            class="confirmModalCard"
            :class="{ 'confirmModalCard--danger': state.options.variant === 'danger' }"
            data-testid="confirm-modal-card"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            :aria-describedby="messageId"
            @click.stop
          >
            <header class="confirmModalHead">
              <span class="confirmModalIcon" aria-hidden="true">
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
                <h2 :id="titleId" class="confirmModalTitle">{{ state.options.title }}</h2>
                <p v-if="state.options.message" :id="messageId" class="confirmModalMessage">
                  {{ state.options.message }}
                </p>
              </div>
            </header>

            <div v-if="state.mode === 'prompt'" class="confirmModalBody">
              <textarea
                v-if="state.options.multiline"
                ref="inputRef"
                v-model="state.value"
                class="ui-textarea confirmModalInput"
                data-testid="confirm-modal-input"
                :placeholder="state.options.placeholder || ''"
                rows="4"
              ></textarea>
              <input
                v-else
                ref="inputRef"
                v-model="state.value"
                class="ui-input confirmModalInput"
                data-testid="confirm-modal-input"
                type="text"
                :placeholder="state.options.placeholder || ''"
              />
            </div>

            <footer class="confirmModalActions">
              <button
                ref="confirmButtonRef"
                type="button"
                class="ui-btn"
                :class="confirmButtonClass"
                data-testid="confirm-modal-confirm"
                :disabled="confirmDisabled"
                @click="confirmProceed"
              >
                {{ state.options.confirmText }}
              </button>
              <button
                ref="cancelButtonRef"
                type="button"
                class="ui-btn btn-cancel"
                data-testid="confirm-modal-cancel"
                @click="cancel"
              >
                {{ state.options.cancelText }}
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
import { isPromptInputValid, useConfirm } from '@/composables/useConfirm'

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
  return !isPromptInputValid(state.value, state.options)
})

const confirmButtonClass = computed(() => {
  return state.options.variant === 'danger' ? 'btn-danger' : 'ui-btn--primary'
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
    return
  }

  confirmButtonRef.value?.focus()
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
      if (!confirmDisabled.value) confirmProceed()
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
.confirmModalRoot {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: grid;
  place-items: center;
  background: var(--color-overlay);
  padding: var(--space-4);
}

.confirmModalCard {
  width: min(440px, 100%);
  border-radius: var(--radius-xl);
  border: 1px solid var(--color-border);
  background: var(--color-card);
  color: var(--color-text-primary);
  box-shadow: var(--shadow-medium);
  padding: var(--space-6);
}

.confirmModalCard--danger {
  border-color: rgb(var(--color-danger-rgb) / 0.48);
}

.confirmModalHead {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: var(--space-3);
  align-items: start;
}

.confirmModalIcon {
  width: 20px;
  height: 20px;
  color: var(--color-accent);
  margin-top: 2px;
}

.confirmModalCard--danger .confirmModalIcon {
  color: var(--color-danger);
}

.confirmModalIcon svg {
  width: 100%;
  height: 100%;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.confirmModalTitle {
  margin: 0;
  font-size: 1.08rem;
  font-weight: 700;
  line-height: 1.25;
}

.confirmModalMessage {
  margin: var(--space-2) 0 0;
  color: var(--color-text-secondary);
  font-size: var(--font-size-md);
  line-height: 1.45;
}

.confirmModalBody {
  margin-top: var(--space-4);
}

.confirmModalInput {
  width: 100%;
}

.confirmModalActions {
  margin-top: var(--space-5);
  display: grid;
  gap: var(--space-3);
}

.confirmModalActions .ui-btn {
  width: 100%;
}

.confirmModalActions .ui-btn:hover {
  transform: none;
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
