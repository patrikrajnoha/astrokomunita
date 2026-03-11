<script setup>
import { computed } from 'vue'

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  duplicateLoading: {
    type: Boolean,
    default: false,
  },
  duplicateMerging: {
    type: Boolean,
    default: false,
  },
  duplicateDryRunning: {
    type: Boolean,
    default: false,
  },
  canMergeDuplicates: {
    type: Boolean,
    default: false,
  },
  groupLimit: {
    type: Number,
    default: 8,
  },
  perGroup: {
    type: Number,
    default: 3,
  },
  duplicateSummary: {
    type: Object,
    default: () => ({
      group_count: 0,
      duplicate_candidates: 0,
    }),
  },
  duplicateGroups: {
    type: Array,
    default: () => [],
  },
  sourceLabel: {
    type: Function,
    required: true,
  },
  formatDate: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits([
  'change-group-limit',
  'change-per-group',
  'refresh',
  'dry-run',
  'merge',
])

const inputsDisabled = computed(() => {
  return (
    props.loading ||
    props.duplicateLoading ||
    props.duplicateMerging ||
    props.duplicateDryRunning
  )
})

const mergeActionsDisabled = computed(() => {
  return !props.canMergeDuplicates || props.loading || props.duplicateLoading
})

function parseNumberInput(event) {
  const value = Number(event?.target?.value)
  if (!Number.isFinite(value)) {
    return 0
  }
  return value
}

function onGroupLimitInput(event) {
  emit('change-group-limit', parseNumberInput(event))
}

function onPerGroupInput(event) {
  emit('change-per-group', parseNumberInput(event))
}
</script>

<template>
  <div class="duplicatesPanel">
    <div class="duplicatesPanel__head">
      <strong>Deduplikacia pending kandidatom</strong>
      <div class="duplicatesPanel__actions">
        <label>
          Skupiny
          <input
            :value="groupLimit"
            type="number"
            min="1"
            max="50"
            :disabled="inputsDisabled"
            class="filterInput"
            @input="onGroupLimitInput"
          />
        </label>
        <label>
          Kandidati/skup.
          <input
            :value="perGroup"
            type="number"
            min="2"
            max="10"
            :disabled="inputsDisabled"
            class="filterInput"
            @input="onPerGroupInput"
          />
        </label>
        <button
          type="button"
          :disabled="inputsDisabled"
          class="toolbarButton toolbarButton--ghost"
          @click="emit('refresh')"
        >
          {{ duplicateLoading ? 'Kontrolujem...' : 'Obnovit' }}
        </button>
        <button
          type="button"
          :disabled="mergeActionsDisabled"
          class="toolbarButton toolbarButton--primary"
          @click="emit('dry-run')"
        >
          {{ duplicateDryRunning ? 'Dry-run...' : 'Dry-run merge' }}
        </button>
        <button
          type="button"
          :disabled="mergeActionsDisabled"
          class="toolbarButton toolbarButton--success"
          @click="emit('merge')"
        >
          {{ duplicateMerging ? 'Zlucujem...' : 'Zlucit duplicity' }}
        </button>
      </div>
    </div>

    <div class="duplicatesPanel__summary">
      <span>Skupiny: {{ duplicateSummary.group_count }}</span>
      <span>Duplicity: {{ duplicateSummary.duplicate_candidates }}</span>
    </div>

    <div v-if="duplicateGroups.length > 0" class="duplicatesPanel__groups">
      <article
        v-for="group in duplicateGroups"
        :key="group.canonical_key"
        class="dupGroup"
      >
        <div class="dupGroup__key">{{ group.canonical_key }}</div>
        <div class="dupGroup__row">
          keep #{{ group.keeper.id }} {{ group.keeper.title }}
          <span class="cellMuted">
            ({{ sourceLabel(group.keeper.source_name) }}, {{ formatDate(group.keeper.start_at) }})
          </span>
        </div>
        <div
          v-for="duplicate in group.duplicates"
          :key="`dup-${group.canonical_key}-${duplicate.id}`"
          class="dupGroup__row cellMuted"
        >
          dup #{{ duplicate.id }} {{ duplicate.title }}
          <span>({{ sourceLabel(duplicate.source_name) }}, {{ formatDate(duplicate.start_at) }})</span>
        </div>
        <div v-if="group.hidden_duplicates > 0" class="dupGroup__row cellMuted">
          +{{ group.hidden_duplicates }} dalsich duplicit
        </div>
      </article>
    </div>
    <div v-else class="duplicatesPanel__empty">
      {{ duplicateLoading ? 'Kontrolujem duplicity...' : 'Pre aktualny filter zatial nie su najdene zlucitelne duplicity.' }}
    </div>
  </div>
</template>

<style scoped>
.duplicatesPanel {
  margin-top: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 8px;
  display: grid;
  gap: 8px;
}

.duplicatesPanel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
}

.duplicatesPanel__actions {
  display: flex;
  align-items: end;
  gap: 6px;
  flex-wrap: wrap;
}

.duplicatesPanel__actions label {
  display: grid;
  gap: 4px;
  font-size: 12px;
  opacity: 0.86;
}

.duplicatesPanel__actions .filterInput {
  width: 90px;
}

.duplicatesPanel__summary {
  display: flex;
  gap: 12px;
  font-size: 12px;
  opacity: 0.86;
}

.duplicatesPanel__groups {
  display: grid;
  gap: 6px;
}

.dupGroup {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.08);
  border-radius: 8px;
  padding: 6px 8px;
  display: grid;
  gap: 4px;
}

.dupGroup__key {
  font-size: 11px;
  opacity: 0.72;
  overflow-wrap: anywhere;
}

.dupGroup__row {
  font-size: 12px;
}

.duplicatesPanel__empty {
  font-size: 12px;
  opacity: 0.75;
}

.toolbarButton,
.filterInput {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font-size: 12px;
}

.toolbarButton {
  padding: 8px 10px;
  cursor: pointer;
}

.toolbarButton--success {
  border-color: rgb(var(--color-success-rgb) / 0.35);
  background: rgb(var(--color-success-rgb) / 0.1);
}

.toolbarButton--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.toolbarButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.filterInput {
  width: 100%;
  padding: 8px;
}

.cellMuted {
  opacity: 0.7;
}

@media (max-width: 900px) {
  .duplicatesPanel__actions {
    width: 100%;
  }

  .duplicatesPanel__actions .toolbarButton {
    flex: 1 1 auto;
  }

  .duplicatesPanel__actions label {
    width: calc(50% - 3px);
  }

  .duplicatesPanel__actions .filterInput {
    width: 100%;
  }
}
</style>
