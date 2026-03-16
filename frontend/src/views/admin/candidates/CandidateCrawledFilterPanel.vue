<script setup>
import { computed } from 'vue'

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  q: {
    type: String,
    default: '',
  },
  perPage: {
    type: Number,
    default: 20,
  },
  status: {
    type: String,
    default: 'pending',
  },
  timePreset: {
    type: String,
    default: 'none',
  },
  timePresetOptions: {
    type: Array,
    default: () => [],
  },
  showYearFilter: {
    type: Boolean,
    default: false,
  },
  filterYear: {
    type: Number,
    default: 2026,
  },
  showMonthFilter: {
    type: Boolean,
    default: false,
  },
  filterMonth: {
    type: Number,
    default: 1,
  },
  monthOptions: {
    type: Array,
    default: () => [],
  },
  showWeekFilter: {
    type: Boolean,
    default: false,
  },
  filterWeek: {
    type: Number,
    default: 1,
  },
  showAdvancedFilters: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: '',
  },
  source: {
    type: String,
    default: '',
  },
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
  'update:source',
  'search',
])

const qModel = computed({
  get: () => props.q,
  set: (value) => emit('update:q', value),
})

const perPageModel = computed({
  get: () => props.perPage,
  set: (value) => emit('update:perPage', value),
})

const statusModel = computed({
  get: () => props.status,
  set: (value) => emit('update:status', value),
})

const timePresetModel = computed({
  get: () => props.timePreset,
  set: (value) => emit('update:timePreset', value),
})

const filterYearModel = computed({
  get: () => props.filterYear,
  set: (value) => emit('update:filterYear', value),
})

const filterMonthModel = computed({
  get: () => props.filterMonth,
  set: (value) => emit('update:filterMonth', value),
})

const filterWeekModel = computed({
  get: () => props.filterWeek,
  set: (value) => emit('update:filterWeek', value),
})

const showAdvancedFiltersModel = computed({
  get: () => props.showAdvancedFilters,
  set: (value) => emit('update:showAdvancedFilters', value),
})

const typeModel = computed({
  get: () => props.type,
  set: (value) => emit('update:type', value),
})

const sourceModel = computed({
  get: () => props.source,
  set: (value) => emit('update:source', value),
})

function triggerSearch() {
  emit('search')
}
</script>

<template>
  <div class="filterPanel">
    <div class="filterGrid">
      <div class="filterField filterField--wide">
        <label>Hladaj</label>
        <input
          v-model="qModel"
          :disabled="loading"
          placeholder="title / short / description"
          class="filterInput"
          @keyup.enter="triggerSearch"
        />
      </div>

      <div class="filterField">
        <label>Na stránku</label>
        <select v-model.number="perPageModel" :disabled="loading" class="filterInput">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
      </div>

      <div class="filterField">
        <label>Obdobie</label>
        <select v-model="timePresetModel" :disabled="loading" class="filterInput">
          <option v-for="option in timePresetOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>

      <div v-if="showYearFilter" class="filterField">
        <label>Rok</label>
        <input
          v-model.number="filterYearModel"
          type="number"
          min="2000"
          max="2100"
          :disabled="loading"
          class="filterInput"
        />
      </div>

      <div v-if="showMonthFilter" class="filterField">
        <label>Mesiac</label>
        <select v-model.number="filterMonthModel" :disabled="loading" class="filterInput">
          <option v-for="option in monthOptions" :key="`month-${option.value}`" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>

      <div v-if="showWeekFilter" class="filterField">
        <label>Tyzden</label>
        <input
          v-model.number="filterWeekModel"
          type="number"
          min="1"
          max="53"
          :disabled="loading"
          class="filterInput"
        />
      </div>

      <div class="filterActions">
        <button type="button" :disabled="loading" class="toolbarButton toolbarButton--primary" @click="triggerSearch">
          Hladat
        </button>
        <button
          type="button"
          :disabled="loading"
          class="toolbarButton toolbarButton--ghost"
          @click="showAdvancedFiltersModel = !showAdvancedFiltersModel"
        >
          {{ showAdvancedFiltersModel ? 'Skryt pokrocile' : 'Pokrocile filtre' }}
        </button>
      </div>

      <template v-if="showAdvancedFiltersModel">
        <div class="filterField">
          <label>Typ</label>
          <select v-model="typeModel" :disabled="loading" class="filterInput">
            <option value="">vsetky</option>
            <option value="eclipse_lunar">Zatmenie Mesiaca</option>
            <option value="eclipse_solar">Zatmenie Slnka</option>
            <option value="meteor_shower">Meteoricky roj</option>
            <option value="planetary_event">Planetarny ukaz</option>
            <option value="aurora">Polarna ziara</option>
            <option value="observation_window">Pozorovacie okno</option>
            <option value="other">Ina udalost</option>
          </select>
        </div>

        <div class="filterField">
          <label>Zdroj</label>
          <select v-model="sourceModel" :disabled="loading" class="filterInput">
            <option value="">Všetky zdroje</option>
            <option value="astropixels">AstroPixels</option>
            <option value="imo">IMO</option>
            <option value="nasa">NASA</option>
            <option value="nasa_wts">NASA WTS</option>
          </select>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
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

.toolbarButton--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.toolbarButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.filterPanel {
  margin-top: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  padding: 8px;
  background: rgb(var(--color-bg-rgb) / 0.96);
}

.filterGrid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 8px;
}

.filterField {
  grid-column: span 3;
  display: grid;
  gap: 4px;
}

.filterField--wide {
  grid-column: span 6;
}

.filterField label {
  font-size: 12px;
  opacity: 0.8;
}

.filterInput {
  width: 100%;
  padding: 8px;
}

.filterActions {
  grid-column: span 3;
  display: flex;
  align-items: end;
  gap: 6px;
  justify-content: flex-end;
}

@media (max-width: 900px) {
  .filterField,
  .filterField--wide,
  .filterActions {
    grid-column: span 12;
  }

  .filterActions {
    justify-content: flex-start;
  }
}
</style>
