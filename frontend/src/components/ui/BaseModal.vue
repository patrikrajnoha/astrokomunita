<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="baseModalOverlay"
      :data-testid="testId || null"
      @click="onOverlayClick"
    >
      <section class="baseModalPanel" role="dialog" aria-modal="true" :aria-label="title || 'Modal'">
        <header class="baseModalHead">
          <h2 v-if="title" class="baseModalTitle">{{ title }}</h2>
          <button
            type="button"
            class="baseModalClose"
            :data-testid="closeTestId || null"
            aria-label="Close modal"
            @click="closeModal"
          >
            x
          </button>
        </header>

        <div v-if="$slots.description" class="baseModalDescription">
          <slot name="description" />
        </div>

        <div class="baseModalBody">
          <slot />
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
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
    default: '',
  },
  closeTestId: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:open', 'close'])

function closeModal() {
  emit('update:open', false)
  emit('close')
}

function onOverlayClick(event) {
  if (event?.target !== event?.currentTarget) return
  closeModal()
}
</script>

<style scoped>
.baseModalOverlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(15 23 42 / 0.6);
}

.baseModalPanel {
  width: min(100%, 36rem);
  max-height: min(90vh, 48rem);
  overflow: auto;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.3);
  background: rgb(var(--color-bg-rgb, 15 23 42) / 0.98);
  color: var(--color-surface, #e2e8f0);
  padding: 1rem;
}

.baseModalHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.baseModalTitle {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.baseModalClose {
  border: 1px solid rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.4);
  border-radius: 0.5rem;
  background: transparent;
  color: inherit;
  min-width: 2rem;
  min-height: 2rem;
  line-height: 1;
}

.baseModalDescription {
  margin-top: 0.5rem;
}

.baseModalBody {
  margin-top: 0.75rem;
}
</style>
