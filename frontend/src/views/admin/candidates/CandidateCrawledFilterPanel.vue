<script setup>
import { computed, onUnmounted } from 'vue'

const props = defineProps({
  loading: { type: Boolean, default: false },
  q: { type: String, default: '' },
  perPage: { type: Number, default: 20 },
  status: { type: String, default: 'pending' },
  timePreset: { type: String, default: 'none' },
  timePresetOptions: { type: Array, default: () => [] },
  showYearFilter: { type: Boolean, default: false },
  filterYear: { type: Number, default: 2026 },
  showMonthFilter: { type: Boolean, default: false },
  filterMonth: { type: Number, default: 1 },
  monthOptions: { type: Array, default: () => [] },
  showWeekFilter: { type: Boolean, default: false },
  filterWeek: { type: Number, default: 1 },
  showAdvancedFilters: { type: Boolean, default: false },
  type: { type: String, default: '' },
  translationMode: { type: String, default: '' },
  source: { type: String, default: '' },
})

const emit = defineEmits([
  'update:q',
  'update:perPage',
  'update:status',
  'update:timePreset',
  'update:filterYear',
  'update:filterMonth',
  'update:filterWeek',
  'update:showAdvancedFilters',
  'update:type',
  'update:translationMode',
  'update:source',
  'search',
])

const qModel = computed({ get: () => props.q, set: (value) => emit('update:q', value) })
const perPageModel = computed({ get: () => props.perPage, set: (value) => emit('update:perPage', value) })
const statusModel = computed({ get: () => props.status, set: (value) => emit('update:status', value) })
const timePresetModel = computed({ get: () => props.timePreset, set: (value) => emit('update:timePreset', value) })
const filterYearModel = computed({ get: () => props.filterYear, set: (value) => emit('update:filterYear', value) })
const filterMonthModel = computed({ get: () => props.filterMonth, set: (value) => emit('update:filterMonth', value) })
const filterWeekModel = computed({ get: () => props.filterWeek, set: (value) => emit('update:filterWeek', value) })
const showAdvancedFiltersModel = computed({
  get: () => props.showAdvancedFilters,
  set: (value) => emit('update:showAdvancedFilters', value),
})
const typeModel = computed({ get: () => props.type, set: (value) => emit('update:type', value) })
const translationModeModel = computed({ get: () => props.translationMode, set: (value) => emit('update:translationMode', value) })
const sourceModel = computed({ get: () => props.source, set: (value) => emit('update:source', value) })

let searchTimer = null
function triggerSearch() {
  emit('search')
}
function triggerSearchDebounced() {
  if (searchTimer !== null) window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    searchTimer = null
    triggerSearch()
  }, 220)
}

onUnmounted(() => {
  if (searchTimer !== null) window.clearTimeout(searchTimer)
})
</script>

<template>
  <div class="filterPanel">
    <input
      v-model="qModel"
      :disabled="loading"
      placeholder="Hľadať: názov, short, popis..."
      class="filterInput filterInput--search"
      @input="triggerSearchDebounced"
      @keyup.enter="triggerSearch"
    />

    <select v-model="sourceModel" :disabled="loading" class="filterInput" @change="triggerSearch">
      <option value="">Zdroj: všetky</option>
      <option value="astropixels">AstroPixels</option>
      <option value="imo">IMO</option>
      <option value="nasa">NASA</option>
      <option value="nasa_wts">NASA WTS</option>
    </select>

    <select v-model.number="perPageModel" :disabled="loading" class="filterInput filterInput--perPage" @change="triggerSearch">
      <option :value="10">10 / str.</option>
      <option :value="20">20 / str.</option>
      <option :value="50">50 / str.</option>
      <option :value="100">100 / str.</option>
      <option :value="9999">Všetky</option>
    </select>

    <button type="button" :disabled="loading" class="toolbarButton toolbarButton--ghost" @click="showAdvancedFiltersModel = !showAdvancedFiltersModel">
      {{ showAdvancedFiltersModel ? 'Menej filtrov' : 'Viac filtrov' }}
    </button>

    <div v-if="showAdvancedFiltersModel" class="advancedRow">
      <select v-model="timePresetModel" :disabled="loading" class="filterInput" @change="triggerSearch">
        <option v-for="option in timePresetOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>

      <select v-model="typeModel" :disabled="loading" class="filterInput" @change="triggerSearch">
        <option value="">Typ: všetky</option>
        <option value="eclipse_lunar">Zatmenie Mesiaca</option>
        <option value="eclipse_solar">Zatmenie Slnka</option>
        <option value="meteor_shower">Meteoritický roj</option>
        <option value="planetary_event">Planetárny úkaz</option>
        <option value="aurora">Polárna žiara</option>
        <option value="observation_window">Pozorovacie okno</option>
        <option value="other">Iná udalosť</option>
      </select>

      <select v-model="translationModeModel" :disabled="loading" class="filterInput" @change="triggerSearch">
        <option value="">Popis: všetky</option>
        <option value="template">Šablóna</option>
        <option value="ai_refined">AI: title + popis</option>
        <option value="ai_title">AI: iba title</option>
        <option value="ai_description">AI: iba popis</option>
        <option value="translated">Strojový preklad</option>
        <option value="manual">Ručne upravené</option>
        <option value="missing">Chýba popis</option>
      </select>

      <input
        v-if="showYearFilter"
        v-model.number="filterYearModel"
        type="number"
        min="2000"
        max="2100"
        :disabled="loading"
        class="filterInput"
        placeholder="Rok"
        @change="triggerSearch"
      />

      <select v-if="showMonthFilter" v-model.number="filterMonthModel" :disabled="loading" class="filterInput" @change="triggerSearch">
        <option v-for="option in monthOptions" :key="`month-${option.value}`" :value="option.value">{{ option.label }}</option>
      </select>

      <input
        v-if="showWeekFilter"
        v-model.number="filterWeekModel"
        type="number"
        min="1"
        max="53"
        :disabled="loading"
        class="filterInput"
        placeholder="Týždeň"
        @change="triggerSearch"
      />
    </div>
  </div>
</template>

<style scoped>
.filterPanel {
  border-radius: 10px;
  padding: 8px;
  background: rgb(var(--color-surface-rgb) / 0.05);
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto;
  gap: 6px;
  align-items: center;
}

.filterPanel > * {
  min-width: 0;
}

.filterInput,
.toolbarButton {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font-size: 12px;
  min-height: 34px;
}

.filterInput {
  width: 100%;
  padding: 6px 9px;
}

.toolbarButton {
  padding: 6px 10px;
  cursor: pointer;
  white-space: nowrap;
}

.toolbarButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.filterInput--search,
.filterInput--perPage {
  min-width: 0;
}

.advancedRow {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
}

@media (max-width: 1200px) {
  .filterPanel {
    grid-template-columns: 1.6fr 1fr auto;
  }
}

@media (max-width: 900px) {
  .filterPanel {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filterInput--search {
    grid-column: 1 / -1;
  }

  .advancedRow {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .filterPanel,
  .advancedRow {
    grid-template-columns: 1fr;
  }
}
</style>
