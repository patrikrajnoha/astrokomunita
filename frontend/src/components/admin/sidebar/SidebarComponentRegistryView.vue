<template>
  <section class="registryRoot">
    <header class="registryHeader">
      <div>
        <h2>Widgety</h2>
        <p>{{ summaryLine }}</p>
      </div>
      <button
        v-if="hasActiveFilters"
        type="button"
        class="clearBtn"
        @click="clearFilters"
      >
        Vycistit filtre
      </button>
    </header>

    <div class="toolbar">
      <label class="searchField">
        <input
          v-model="searchQuery"
          type="text"
          class="searchInput"
          placeholder="Hladat widgety podla nazvu, id alebo popisu"
        />
      </label>
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
      <h3>Ziadny widget nevyhovuje filtru.</h3>
      <p>Skus iny vyraz alebo vymaz filtre.</p>
      <button type="button" class="clearBtn" @click="clearFilters">Vycistit filtre</button>
    </div>

    <div v-else class="registryGrid">
      <ComponentPlaygroundCard
        v-for="entry in filteredEntries"
        :key="entry.id"
        :entry="entry"
        :compact="true"
      />
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import ComponentPlaygroundCard from '@/components/admin/sidebar/playground/ComponentPlaygroundCard.vue'
import {
  componentRegistryCategories,
  getRegistryCategoryCount,
  sidebarComponentPlaygroundRegistry,
} from '@/components/admin/sidebar/playground/componentRegistry'

const searchQuery = ref('')
const selectedCategory = ref('all')

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

const normalizedSearch = computed(() => {
  return String(searchQuery.value || '').trim().toLowerCase()
})

const hasActiveFilters = computed(() => {
  return selectedCategory.value !== 'all' || normalizedSearch.value.length > 0
})

const summaryLine = computed(() => {
  if (hasActiveFilters.value) {
    return `${filteredEntries.value.length} zobrazenych z ${allEntriesCount}`
  }

  return `${allEntriesCount} dostupnych widgetov`
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
</script>

<style scoped>
.registryRoot {
  display: grid;
  gap: 0.58rem;
}

.registryHeader {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.8rem;
}

.registryHeader h2 {
  margin: 0;
  font-size: 0.98rem;
}

.registryHeader p {
  margin: 0.18rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.74rem;
}

.clearBtn {
  min-height: 1.86rem;
  border-radius: 0.56rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.34);
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.3rem 0.58rem;
}

.toolbar {
  display: grid;
  gap: 0.4rem;
}

.searchField {
  min-width: 0;
}

.searchInput {
  width: 100%;
  min-width: 0;
  min-height: 2.1rem;
  border-radius: 0.62rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.26);
  color: var(--color-surface);
  padding: 0.4rem 0.58rem;
  font-size: 0.75rem;
}

.categoryRow {
  display: flex;
  gap: 0.24rem;
  overflow-x: auto;
  padding-bottom: 0.08rem;
}

.chipBtn {
  min-height: 1.64rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.67rem;
  font-weight: 700;
  white-space: nowrap;
  padding: 0.14rem 0.52rem;
}

.chipBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
}

.emptyState {
  border-radius: 0.76rem;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.34);
  background: rgb(var(--color-bg-rgb) / 0.17);
  padding: 0.72rem;
  display: grid;
  gap: 0.26rem;
  justify-items: start;
}

.emptyState h3 {
  margin: 0;
  font-size: 0.82rem;
}

.emptyState p {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
}

.registryGrid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}

@media (max-width: 760px) {
  .registryHeader {
    align-items: stretch;
    flex-direction: column;
    gap: 0.4rem;
  }

  .registryGrid {
    grid-template-columns: 1fr;
  }
}
</style>
