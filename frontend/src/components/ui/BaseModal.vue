<template>
  <teleport to="body">
    <transition name="modal-fade">
      <div
        v-if="open"
        class="modalRoot"
        :class="{ 'modalRoot--eventPlan': isEventPlanModal }"
        :data-testid="testId"
        @mousedown="onBackdropMouseDown"
        @click="onBackdropClick"
      >
        <transition name="modal-pop">
          <section
            v-if="open"
            ref="dialogRef"
            class="modalCard"
            :class="{
              'modalCard--compact': compact,
              'modalCard--eventPlan': isEventPlanModal,
            }"
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
                :class="{ 'modalClose--eventPlan': isEventPlanModal }"
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
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

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
  compact: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:open', 'close', 'open'])

const dialogRef = ref(null)
const closeButtonRef = ref(null)
const pressedBackdrop = ref(false)
let previousActive = null

const titleId = `base-modal-title-${Math.random().toString(36).slice(2, 9)}`
const isEventPlanModal = computed(() => props.testId === 'event-plan-modal')

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
  background: var(--bg-overlay);
  padding: 1rem;
}

.modalCard {
  width: min(42rem, 100%);
  max-height: min(90vh, 48rem);
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  background: var(--color-card);
  color: var(--color-text-primary);
  box-shadow: var(--shadow-medium);
}

.modalCard.modalCard--compact {
  width: min(36rem, 100%);
  max-height: min(88vh, 42rem);
}

.modalCard.modalCard--compact .modalHead {
  padding: var(--space-4) var(--space-4) 0;
  padding-bottom: var(--space-3);
}

.modalCard.modalCard--compact .modalBody {
  padding: var(--space-3) var(--space-4) var(--space-4);
}

.modalHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: var(--space-5) var(--space-5) 0;
  border-bottom: 1px solid var(--divider-color);
  padding-bottom: var(--space-4);
}

.modalHeading {
  min-width: 0;
}

.modalTitle {
  margin: 0;
  font-size: var(--font-size-xl);
  font-weight: 700;
  color: var(--color-text-primary);
}

.modalClose {
  flex-shrink: 0;
}

.modalBody {
  padding: var(--space-4) var(--space-5) var(--space-5);
}

.modalRoot.modalRoot--eventPlan {
  background: rgb(6 10 16 / 0.8);
}

.modalCard.modalCard--eventPlan {
  width: min(40rem, 100%);
  max-height: min(90vh, 44rem);
  border: 1px solid rgb(var(--color-btn-secondary-bg-rgb) / 0.9);
  border-radius: 24px;
  background: #151d28;
  box-shadow: none;
}

.modalCard.modalCard--eventPlan .modalHead {
  padding: 24px 24px 14px;
  border-bottom: 1px solid rgb(var(--color-btn-secondary-bg-rgb) / 0.9);
}

.modalCard.modalCard--eventPlan .modalTitle {
  color: #ffffff;
  font-size: 1.125rem;
  letter-spacing: -0.01em;
}

.modalCard.modalCard--eventPlan .modalBody {
  padding: 18px 24px 24px;
}

.modalClose.modalClose--eventPlan {
  background: #222e3f;
  color: #abb8c9;
}

.modalClose.modalClose--eventPlan:hover:not(:disabled) {
  background: #1c2736;
  color: #ffffff;
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
