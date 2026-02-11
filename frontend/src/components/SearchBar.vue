<template>
  <form ref="rootRef" class="relative" role="search" aria-label="Vyhladavanie" @submit.prevent="handleSubmit">
    <label for="sidebar-search" class="sr-only">Vyhladat prispevky a pouzivatelov</label>

    <div class="relative">
      <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[color:rgb(var(--color-text-secondary-rgb)/0.75)]" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </span>

      <input
        id="sidebar-search"
        ref="inputRef"
        v-model="searchQuery"
        type="text"
        placeholder="Hladat..."
        autocomplete="off"
        aria-label="Hladat"
        role="combobox"
        :aria-expanded="isDropdownVisible ? 'true' : 'false'"
        :aria-controls="listboxId"
        aria-autocomplete="list"
        :aria-activedescendant="activeDescendantId"
        class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] py-2.5 pl-9 pr-20 text-sm text-[var(--color-surface)] placeholder-[color:rgb(var(--color-text-secondary-rgb)/0.7)] transition-colors focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[color:rgb(var(--color-primary-rgb)/0.24)]"
        @focus="handleFocus"
        @blur="handleBlur"
        @keydown="handleKeydown"
      />

      <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1.5">
        <button
          v-if="hasQuery"
          type="button"
          class="rounded-md p-1 text-[color:rgb(var(--color-text-secondary-rgb)/0.8)] transition-colors hover:text-[var(--color-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
          aria-label="Vymazat vyhladavanie"
          @mousedown.prevent
          @click="clearQuery"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <div
          v-if="isLoading"
          class="h-4 w-4 animate-spin rounded-full border-2 border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] border-t-[var(--color-primary)]"
          aria-hidden="true"
        ></div>

        <button
          type="submit"
          class="rounded-md p-1 text-[color:rgb(var(--color-text-secondary-rgb)/0.75)] transition-colors hover:text-[var(--color-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
          aria-label="Spustit vyhladavanie"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>
      </div>
    </div>

    <div
      v-if="isDropdownVisible"
      :id="listboxId"
      role="listbox"
      class="absolute left-0 right-0 z-50 mt-2 max-h-72 overflow-y-auto rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.24)] bg-[color:rgb(var(--color-bg-rgb)/0.96)] p-1 shadow-[0_16px_40px_rgb(0_0_0/0.24)] backdrop-blur"
    >
      <div
        v-if="!hasSuggestions"
        class="px-3 py-2.5 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]"
      >
        {{ isLoading ? 'Nacitavam navrhy...' : 'Ziadne navrhy' }}
      </div>

      <button
        v-for="(item, index) in suggestions"
        :id="optionId(index)"
        :key="`${item.type}-${item.id}-${index}`"
        type="button"
        role="option"
        :aria-selected="activeIndex === index ? 'true' : 'false'"
        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm text-[var(--color-surface)] transition-colors"
        :class="activeIndex === index
          ? 'bg-[color:rgb(var(--color-primary-rgb)/0.2)]'
          : 'hover:bg-[color:rgb(var(--color-bg-rgb)/0.75)]'"
        @mousedown.prevent="selectSuggestion(item)"
        @mousemove="activeIndex = index"
      >
        <span class="truncate">
          <template v-for="(part, partIndex) in highlightLabel(item.label)" :key="`${index}-${partIndex}`">
            <mark
              v-if="part.match"
              class="rounded bg-[color:rgb(var(--color-primary-rgb)/0.26)] px-0.5 text-[var(--color-surface)]"
            >
              {{ part.text }}
            </mark>
            <span v-else>{{ part.text }}</span>
          </template>
        </span>

        <span class="shrink-0 rounded-md bg-[color:rgb(var(--color-bg-rgb)/0.55)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.95)]">
          {{ item.type }}
        </span>
      </button>
    </div>
  </form>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()

const rootRef = ref(null)
const inputRef = ref(null)
const searchQuery = ref('')
const suggestions = ref([])
const isLoading = ref(false)
const isOpen = ref(false)
const isFocused = ref(false)
const activeIndex = ref(-1)

const debounceMs = 200
let debounceTimer = null
let blurTimer = null
let requestToken = 0

const comboboxUid = `search-suggest-${Math.random().toString(36).slice(2, 10)}`
const listboxId = `${comboboxUid}-listbox`

const hasQuery = computed(() => searchQuery.value.trim().length > 0)
const hasMinLength = computed(() => searchQuery.value.trim().length >= 2)
const hasSuggestions = computed(() => suggestions.value.length > 0)
const isDropdownVisible = computed(() => isOpen.value && isFocused.value && hasMinLength.value)
const activeDescendantId = computed(() => (activeIndex.value >= 0 ? optionId(activeIndex.value) : undefined))

const resetDropdown = () => {
  isOpen.value = false
  activeIndex.value = -1
}

const clearTimers = () => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  if (blurTimer) {
    clearTimeout(blurTimer)
    blurTimer = null
  }
}

const optionId = (index) => `${comboboxUid}-option-${index}`

const highlightLabel = (label) => {
  const text = String(label || '')
  const query = searchQuery.value.trim()

  if (!query) return [{ text, match: false }]

  const lowerText = text.toLowerCase()
  const lowerQuery = query.toLowerCase()
  const startIndex = lowerText.indexOf(lowerQuery)

  if (startIndex < 0) {
    return [{ text, match: false }]
  }

  const endIndex = startIndex + query.length
  const parts = []

  if (startIndex > 0) {
    parts.push({ text: text.slice(0, startIndex), match: false })
  }

  parts.push({ text: text.slice(startIndex, endIndex), match: true })

  if (endIndex < text.length) {
    parts.push({ text: text.slice(endIndex), match: false })
  }

  return parts
}

const goToSearch = (nextValue = searchQuery.value) => {
  const query = String(nextValue || '').trim()
  const currentQ = typeof route.query.q === 'string' ? route.query.q : ''

  resetDropdown()

  if (route.name === 'search' && currentQ === query) return

  router.push({
    name: 'search',
    query: query ? { q: query } : {},
  }).catch(() => {})
}

const selectSuggestion = (suggestion) => {
  if (!suggestion) return
  searchQuery.value = String(suggestion.value || suggestion.label || '').trim()
  goToSearch(searchQuery.value)
}

const loadSuggestions = async () => {
  const query = searchQuery.value.trim()

  if (query.length < 2) {
    suggestions.value = []
    isLoading.value = false
    resetDropdown()
    return
  }

  const currentToken = ++requestToken
  isLoading.value = true

  try {
    const response = await api.get('/search/suggest', {
      params: {
        q: query,
        limit: 8,
      },
    })

    if (currentToken !== requestToken) return

    const data = Array.isArray(response.data?.data) ? response.data.data : []
    suggestions.value = data.slice(0, 8)
    isOpen.value = true
    activeIndex.value = -1
  } catch {
    if (currentToken !== requestToken) return
    suggestions.value = []
    isOpen.value = true
    activeIndex.value = -1
  } finally {
    if (currentToken === requestToken) {
      isLoading.value = false
    }
  }
}

const scheduleSuggestionsFetch = () => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }

  debounceTimer = setTimeout(() => {
    loadSuggestions()
  }, debounceMs)
}

const handleSubmit = () => {
  if (activeIndex.value >= 0 && suggestions.value[activeIndex.value]) {
    selectSuggestion(suggestions.value[activeIndex.value])
    return
  }

  goToSearch(searchQuery.value)
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    resetDropdown()
    return
  }

  if (event.key === 'Tab') {
    resetDropdown()
    return
  }

  if (!hasMinLength.value) return

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!isOpen.value) isOpen.value = true

    if (!suggestions.value.length) return
    activeIndex.value = (activeIndex.value + 1) % suggestions.value.length
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (!isOpen.value) isOpen.value = true

    if (!suggestions.value.length) return
    activeIndex.value = activeIndex.value <= 0
      ? suggestions.value.length - 1
      : activeIndex.value - 1
    return
  }

  if (event.key === 'Enter' && activeIndex.value >= 0 && suggestions.value[activeIndex.value]) {
    event.preventDefault()
    selectSuggestion(suggestions.value[activeIndex.value])
  }
}

const clearQuery = () => {
  searchQuery.value = ''
  suggestions.value = []
  isLoading.value = false
  resetDropdown()
  inputRef.value?.focus()
}

const handleFocus = () => {
  isFocused.value = true

  if (blurTimer) {
    clearTimeout(blurTimer)
    blurTimer = null
  }

  if (hasMinLength.value) {
    isOpen.value = true
    if (!hasSuggestions.value && !isLoading.value) {
      scheduleSuggestionsFetch()
    }
  }
}

const handleBlur = () => {
  isFocused.value = false
  blurTimer = setTimeout(() => {
    resetDropdown()
  }, 120)
}

const handleOutsideClick = (event) => {
  const target = event.target
  const root = rootRef.value

  if (!root || !(target instanceof Node)) return
  if (!root.contains(target)) {
    isFocused.value = false
    resetDropdown()
  }
}

watch(
  () => route.query.q,
  (q) => {
    searchQuery.value = typeof q === 'string' ? q : ''
    suggestions.value = []
    isLoading.value = false
    resetDropdown()
  },
  { immediate: true },
)

watch(searchQuery, () => {
  activeIndex.value = -1

  if (!hasMinLength.value) {
    suggestions.value = []
    isLoading.value = false
    resetDropdown()
    return
  }

  if (!isFocused.value) return

  isOpen.value = true
  scheduleSuggestionsFetch()
})

onMounted(() => {
  document.addEventListener('mousedown', handleOutsideClick)
})

onBeforeUnmount(() => {
  clearTimers()
  document.removeEventListener('mousedown', handleOutsideClick)
})
</script>
