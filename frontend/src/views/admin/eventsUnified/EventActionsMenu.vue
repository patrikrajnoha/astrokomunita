<script setup>
import { computed } from 'vue'

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  deleteActionLoading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['edit', 'toggle-visibility', 'delete'])

const visibilityLabel = computed(() => (Number(props.event?.visibility) === 1 ? 'Skryť' : 'Zverejniť'))
const visibilityTitle = computed(() => (Number(props.event?.visibility) === 1
  ? 'Skryť udalosť'
  : 'Zverejniť udalosť'))

function runAction(event, key) {
  const details = event?.currentTarget?.closest('details')
  if (details) {
    details.open = false
  }
  emit(key)
}
</script>

<template>
  <details class="eventActionsMenu" @click.stop>
    <summary class="eventActionsMenu__trigger" :aria-label="`Akcie pre udalosť #${event.id}`">⋯</summary>
    <div class="eventActionsMenu__dropdown">
      <button type="button" class="eventActionsMenu__item" @click="runAction($event, 'edit')">
        Upraviť
      </button>
      <button
        type="button"
        class="eventActionsMenu__item"
        :title="visibilityTitle"
        @click="runAction($event, 'toggle-visibility')"
      >
        {{ visibilityLabel }}
      </button>
      <button
        type="button"
        class="eventActionsMenu__item eventActionsMenu__item--danger"
        :disabled="deleteActionLoading"
        @click="runAction($event, 'delete')"
      >
        Zmazať
      </button>
    </div>
  </details>
</template>

<style scoped>
.eventActionsMenu {
  position: relative;
}

.eventActionsMenu__trigger {
  list-style: none;
  width: 30px;
  height: 30px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 17px;
  line-height: 1;
}

.eventActionsMenu__trigger::-webkit-details-marker {
  display: none;
}

.eventActionsMenu__trigger:hover {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.eventActionsMenu__dropdown {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 12;
  min-width: 128px;
  display: grid;
  gap: 2px;
  padding: 4px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.98);
  box-shadow: 0 10px 24px rgb(0 0 0 / 0.24);
}

.eventActionsMenu__item {
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: inherit;
  text-align: left;
  padding: 6px 8px;
  font-size: 12px;
  cursor: pointer;
}

.eventActionsMenu__item:hover:not(:disabled) {
  background: rgb(var(--color-surface-rgb) / 0.1);
}

.eventActionsMenu__item:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.eventActionsMenu__item--danger {
  color: rgb(220 38 38);
}
</style>
