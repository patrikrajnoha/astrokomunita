<template>
  <section class="registryRoot" :class="{ compactMode }">
    <header class="registryHeader">
      <div>
        <h2>Sidebar widgety</h2>
        <p>Galeria obsahuje iba komponenty, ktore sa realne pouzivaju v sidebare.</p>
      </div>

      <div class="headerStats">
        <span class="statPill">Vsetky: {{ allEntriesCount }}</span>
        <span class="statPill">Kategoria: {{ selectedCategoryLabel }}</span>
        <span class="statPill">{{ filteredEntries.length }} zobrazenych</span>
      </div>
    </header>

    <div class="toolbar">
      <label class="searchField">
        <span>Vyhladavanie</span>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Nazov, id, popis alebo source path"
        />
      </label>

      <div class="toolbarActions">
        <button type="button" class="ghostBtn" @click="compactMode = !compactMode">
          {{ compactMode ? 'Normal layout' : 'Ultra compact' }}
        </button>
        <button type="button" class="ghostBtn" @click="clearFilters">Reset</button>
      </div>
    </div>

    <div v-if="visibleCategories.length > 1" class="categoryRow">
      <button
        type="button"
        class="chipBtn"
        :class="{ active: selectedCategory === 'all' }"
        @click="selectedCategory = 'all'"
      >
        Vsetko ({{ allEntriesCount }})
      </button>
      <button
        v-for="category in visibleCategories"
        :key="`chip-${category}`"
        type="button"
        class="chipBtn"
        :class="{ active: selectedCategory === category }"
        @click="selectedCategory = category"
      >
        {{ category }} ({{ categoryCounts[category] || 0 }})
      </button>
    </div>

    <div v-if="filteredEntries.length === 0" class="emptyState">
      <h3>Ziadny sidebar komponent nevyhovuje filtru.</h3>
      <p>Skus iny vyraz alebo reset filtrov.</p>
      <button type="button" class="ghostBtn" @click="clearFilters">Resetovat</button>
    </div>

    <div v-else class="registryGrid">
      <ComponentPlaygroundCard
        v-for="entry in filteredEntries"
        :key="entry.id"
        :entry="entry"
        :compact="compactMode"
      />
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ComponentPlaygroundCard from '@/components/admin/sidebar/playground/ComponentPlaygroundCard.vue'
import {
  componentRegistryCategories,
  getRegistryCategoryCount,
  sidebarComponentPlaygroundRegistry,
} from '@/components/admin/sidebar/playground/componentRegistry'

const searchQuery = ref('')
const selectedCategory = ref('all')
const compactMode = ref(false)

const allEntries = sidebarComponentPlaygroundRegistry.slice()
const categories = componentRegistryCategories.slice()

const categoryCounts = computed(() => {
  return categories.reduce((acc, category) => {
    acc[category] = getRegistryCategoryCount(category)
    return acc
  }, {})
})

const visibleCategories = computed(() => {
  return categories.filter((category) => (categoryCounts.value[category] || 0) > 0)
})

const selectedCategoryLabel = computed(() => {
  if (selectedCategory.value === 'all') return 'Vsetky'
  return selectedCategory.value
})

const normalizedSearch = computed(() => {
  return String(searchQuery.value || '').trim().toLowerCase()
})

const filteredEntries = computed(() => {
  return allEntries
    .filter((entry) => {
      if (selectedCategory.value === 'all') return true
      return String(entry.category || '') === selectedCategory.value
    })
    .filter((entry) => {
      if (!normalizedSearch.value) return true

      const haystack = [
        entry.id,
        entry.label,
        entry.description,
        entry.category,
        entry.sourcePath,
      ]
        .map((item) => String(item || '').toLowerCase())
        .join(' ')

      return haystack.includes(normalizedSearch.value)
    })
    .sort((a, b) => {
      const categoryDiff = categories.indexOf(a.category) - categories.indexOf(b.category)
      if (categoryDiff !== 0) return categoryDiff
      return String(a.label || '').localeCompare(String(b.label || ''), 'sk')
    })
})

const allEntriesCount = allEntries.length

const clearFilters = () => {
  searchQuery.value = ''
  selectedCategory.value = 'all'
}

onMounted(() => {
  if (typeof window !== 'undefined' && window.innerWidth <= 1400) {
    compactMode.value = true
  }
})
</script>

<style scoped>
.registryRoot {
  display: grid;
  gap: 0.7rem;
}

.registryHeader {
  display: flex;
  justify-content: space-between;
  gap: 0.7rem;
  align-items: flex-start;
}

.registryHeader h2 {
  margin: 0;
  font-size: 1.02rem;
}

.registryHeader p {
  margin: 0.2rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.77rem;
}

.headerStats {
  display: inline-flex;
  gap: 0.3rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.statPill {
  font-size: 0.68rem;
  padding: 0.18rem 0.5rem;
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.26);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  color: var(--color-text-secondary);
}

.toolbar {
  display: grid;
  gap: 0.45rem;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
}

.toolbarActions {
  display: inline-flex;
  gap: 0.36rem;
}

.searchField {
  display: grid;
  gap: 0.24rem;
}

.searchField span {
  font-size: 0.69rem;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.searchField input {
  min-height: 2rem;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  padding: 0.36rem 0.52rem;
  font-size: 0.77rem;
}

.ghostBtn {
  min-height: 2rem;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: transparent;
  color: var(--color-surface);
  font-size: 0.74rem;
  font-weight: 600;
  padding: 0.36rem 0.68rem;
}

.categoryRow {
  display: flex;
  flex-wrap: wrap;
  gap: 0.28rem;
}

.chipBtn {
  min-height: 1.72rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.69rem;
  font-weight: 700;
  padding: 0.16rem 0.54rem;
}

.chipBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.54);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.emptyState {
  border-radius: 0.82rem;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.34);
  background: rgb(var(--color-bg-rgb) / 0.2);
  padding: 0.78rem;
  display: grid;
  gap: 0.28rem;
  justify-items: start;
}

.emptyState h3 {
  margin: 0;
  font-size: 0.86rem;
}

.emptyState p {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.74rem;
}

.registryGrid {
  display: grid;
  gap: 0.58rem;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
}

.registryRoot.compactMode .registryGrid {
  gap: 0.42rem;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
}

.registryRoot.compactMode .headerStats {
  gap: 0.24rem;
}

.registryRoot.compactMode .statPill {
  font-size: 0.64rem;
  padding: 0.14rem 0.44rem;
}

.registryRoot.compactMode .chipBtn {
  min-height: 1.6rem;
  font-size: 0.64rem;
  padding: 0.14rem 0.46rem;
}

.registryRoot.compactMode .searchField input {
  min-height: 1.86rem;
  font-size: 0.72rem;
}

@media (max-width: 980px) {
  .registryHeader {
    flex-direction: column;
  }

  .headerStats {
    justify-content: flex-start;
  }
}

@media (max-width: 760px) {
  .toolbar {
    grid-template-columns: 1fr;
  }

  .toolbarActions {
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .registryGrid {
    grid-template-columns: 1fr;
  }

  .registryRoot.compactMode .registryGrid {
    grid-template-columns: 1fr;
  }
}
</style>
