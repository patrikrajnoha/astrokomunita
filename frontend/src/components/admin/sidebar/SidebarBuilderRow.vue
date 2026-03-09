<template>
  <article
    class="layoutRow"
    :class="{
      selected: selected,
      disabled: !section.is_enabled,
      custom: section.kind === 'custom_component',
    }"
    role="button"
    tabindex="0"
    @click="emit('select', section)"
    @keydown.enter.prevent="emit('select', section)"
    @keydown.space.prevent="emit('select', section)"
  >
    <button
      type="button"
      class="dragHandle"
      :class="{ inactive: !draggableEnabled }"
      :disabled="!draggableEnabled"
      :aria-label="draggableEnabled ? 'Presunut polozku' : 'Presun je vypnuty pri aktivnom filtri'"
      @click.stop
    >
      <span aria-hidden="true">::</span>
    </button>

    <div class="rowCopy">
      <p class="rowTitle">{{ section.title || 'Bez nazvu' }}</p>
      <p class="rowMeta">{{ identifier }}<span v-if="kindLabel"> | {{ kindLabel }}</span></p>
    </div>

    <label class="rowToggle" @click.stop>
      <input
        :checked="section.is_enabled"
        type="checkbox"
        @change="emit('toggle', section, $event?.target?.checked)"
      />
      <span class="slider"></span>
      <span class="label">{{ section.is_enabled ? 'Zapnute' : 'Vypnute' }}</span>
    </label>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  section: {
    type: Object,
    required: true,
  },
  selected: {
    type: Boolean,
    default: false,
  },
  draggableEnabled: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['select', 'toggle'])

const identifier = computed(() => {
  if (props.section?.kind === 'custom_component') {
    return `custom:${props.section?.custom_component_id || 'n/a'}`
  }

  return String(props.section?.section_key || '')
})

const kindLabel = computed(() => {
  return props.section?.kind === 'custom_component' ? 'Vlastny komponent' : ''
})
</script>

<style scoped>
.layoutRow {
  min-height: 3rem;
  border-radius: 0.8rem;
  border: 1px solid transparent;
  background: rgb(var(--color-bg-rgb) / 0.12);
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.58rem;
  padding: 0.45rem 0.52rem;
  transition: border-color 0.15s ease, background-color 0.15s ease;
  cursor: pointer;
}

.layoutRow:hover {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.2);
}

.layoutRow.selected {
  border-color: rgb(var(--color-primary-rgb) / 0.52);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.layoutRow.disabled {
  opacity: 0.62;
}

.dragHandle {
  width: 1.85rem;
  height: 1.85rem;
  border: 0;
  border-radius: 0.56rem;
  background: transparent;
  color: var(--color-text-secondary);
  font-weight: 700;
  line-height: 1;
  cursor: grab;
  padding: 0;
}

.dragHandle.inactive {
  cursor: default;
  opacity: 0.4;
}

.rowCopy {
  min-width: 0;
}

.rowTitle {
  margin: 0;
  font-size: 0.86rem;
  color: var(--color-surface);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.rowMeta {
  margin: 0.12rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.rowToggle {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
}

.rowToggle input[type='checkbox'] {
  display: none;
}

.slider {
  width: 38px;
  height: 20px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.3);
  position: relative;
}

.slider::before {
  content: '';
  position: absolute;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  top: 2px;
  left: 2px;
  background: white;
  transition: transform 0.2s ease;
}

.rowToggle input[type='checkbox']:checked + .slider {
  background: rgb(var(--color-primary-rgb) / 0.68);
}

.rowToggle input[type='checkbox']:checked + .slider::before {
  transform: translateX(18px);
}

.label {
  font-size: 0.7rem;
  color: var(--color-text-secondary);
}

@media (max-width: 860px) {
  .label {
    display: none;
  }
}
</style>
