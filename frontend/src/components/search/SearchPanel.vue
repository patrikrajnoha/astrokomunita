<template>
  <section class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.66)] p-3 shadow-[0_14px_34px_rgb(0_0_0/0.22)] backdrop-blur sm:p-4">
    <div class="mb-3 inline-flex w-full rounded-xl bg-[color:rgb(var(--color-bg-rgb)/0.78)] p-1 ring-1 ring-[color:rgb(var(--color-text-secondary-rgb)/0.24)]">
      <button
        type="button"
        class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
        :class="mode === 'users'
          ? 'bg-[color:rgb(var(--color-primary-rgb)/0.9)] text-white shadow'
          : 'text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] hover:text-[var(--color-surface)]'"
        @click="emit('update:mode', 'users')"
      >
        Pouzivatelia
      </button>
      <button
        type="button"
        class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
        :class="mode === 'posts'
          ? 'bg-[color:rgb(var(--color-primary-rgb)/0.9)] text-white shadow'
          : 'text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] hover:text-[var(--color-surface)]'"
        @click="emit('update:mode', 'posts')"
      >
        Prispevky
      </button>
    </div>

    <form ref="rootRef" class="relative" role="search" @submit.prevent="onSubmit">
      <label for="search-input-main" class="sr-only">Vyhladavanie</label>

      <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[color:rgb(var(--color-text-secondary-rgb)/0.75)]" aria-hidden="true">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m1-4a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </span>

        <input
          id="search-input-main"
          ref="inputRef"
          v-model="localQuery"
          type="search"
          :placeholder="mode === 'users' ? 'Hladat pouzivatelov...' : 'Hladat prispevky...'"
          autocomplete="off"
          class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.78)] py-3 pl-9 pr-20 text-sm text-[var(--color-surface)] placeholder-[color:rgb(var(--color-text-secondary-rgb)/0.85)] transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[color:rgb(var(--color-primary-rgb)/0.24)]"
          role="combobox"
          :aria-expanded="isDropdownVisible ? 'true' : 'false'"
          :aria-controls="listboxId"
          aria-autocomplete="list"
          :aria-activedescendant="activeDescendantId"
          @focus="onFocus"
          @blur="onBlur"
          @keydown="onKeydown"
        />

        <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1.5">
          <button
            v-if="hasQuery"
            type="button"
            class="rounded-md p-1 text-[color:rgb(var(--color-text-secondary-rgb)/0.8)] transition hover:text-[var(--color-primary)]"
            aria-label="Vymazat"
            @mousedown.prevent
            @click="clearQuery"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div
            v-if="isLoadingSuggestions"
            class="h-4 w-4 animate-spin rounded-full border-2 border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] border-t-[var(--color-primary)]"
            aria-hidden="true"
          ></div>

          <button
            type="submit"
            class="rounded-md p-1 text-[color:rgb(var(--color-text-secondary-rgb)/0.75)] transition hover:text-[var(--color-primary)]"
            aria-label="Spustit hladanie"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m1-4a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </button>
        </div>
      </div>

      <p class="mt-2 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.86)]">
        {{ mode === 'users' ? 'Tip: pouzi meno alebo @username.' : 'Tip: hladaj klucove slova v obsahu prispevkov.' }}
      </p>

      <div
        v-if="isDropdownVisible"
        :id="listboxId"
        role="listbox"
        class="absolute left-0 right-0 z-50 mt-2 max-h-80 overflow-y-auto rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.28)] bg-[color:rgb(var(--color-bg-rgb)/0.96)] p-1 shadow-[0_18px_44px_rgb(0_0_0/0.32)] backdrop-blur"
      >
        <div
          v-if="!suggestions.length"
          class="px-3 py-2.5 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]"
        >
          {{ isLoadingSuggestions ? 'Nacitavam navrhy...' : 'Ziadne navrhy' }}
        </div>

        <button
          v-for="(item, index) in suggestions"
          :id="optionId(index)"
          :key="item.key"
          type="button"
          role="option"
          :aria-selected="activeIndex === index ? 'true' : 'false'"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition"
          :class="activeIndex === index
            ? 'bg-[color:rgb(var(--color-primary-rgb)/0.2)]'
            : 'hover:bg-[color:rgb(var(--color-bg-rgb)/0.75)]'"
          @mousedown.prevent="selectSuggestion(item)"
          @mousemove="activeIndex = index"
        >
          <img
            v-if="item.avatarUrl"
            :src="item.avatarUrl"
            :alt="item.title"
            class="h-8 w-8 shrink-0 rounded-full object-cover"
          />
          <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[color:rgb(var(--color-primary-rgb)/0.22)] text-[var(--color-primary)]">
            <svg v-if="item.kind === 'post'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
            </svg>
            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14c3.866 0 7 1.343 7 3v1H5v-1c0-1.657 3.134-3 7-3zm0-1a4 4 0 100-8 4 4 0 000 8z" />
            </svg>
          </div>

          <div class="min-w-0">
            <div class="truncate text-sm font-medium text-[var(--color-surface)]">{{ item.title }}</div>
            <div class="truncate text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">{{ item.subtitle }}</div>
          </div>
        </button>
      </div>
    </form>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  mode: {
    type: String,
    default: 'users',
  },
})

const emit = defineEmits(['update:modelValue', 'update:mode', 'submit'])

const rootRef = ref(null)
const inputRef = ref(null)
const localQuery = ref(props.modelValue || '')
const isFocused = ref(false)
const isOpen = ref(false)
const isLoadingSuggestions = ref(false)
const suggestions = ref([])
const activeIndex = ref(-1)

let debounceTimer = null
let blurTimer = null
let requestController = null
let requestToken = 0

const comboboxUid = `search-main-${Math.random().toString(36).slice(2, 10)}`
const listboxId = `${comboboxUid}-listbox`

const normalizedQuery = computed(() => localQuery.value.trim())
const hasQuery = computed(() => normalizedQuery.value.length > 0)
const hasMinLength = computed(() => normalizedQuery.value.length >= 2)
const isDropdownVisible = computed(() => isFocused.value && isOpen.value && hasMinLength.value)
const activeDescendantId = computed(() => (activeIndex.value >= 0 ? optionId(activeIndex.value) : undefined))

const optionId = (index) => `${comboboxUid}-option-${index}`

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

const resetDropdown = () => {
  isOpen.value = false
  activeIndex.value = -1
}

const cancelInFlight = () => {
  requestController?.abort()
  requestController = null
}

const toAvatarUrl = (candidate) => {
  const avatarUrl = candidate?.avatar_url || ''
  if (avatarUrl) return avatarUrl
  if (candidate?.name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(candidate.name)}&background=0f172a&color=fff&size=80`
  }
  return ''
}

const mapUserSuggestions = (payload) => {
  const items = Array.isArray(payload?.data) ? payload.data : []
  return items.slice(0, 8).map((user) => ({
    key: `user-${user.id}`,
    kind: 'user',
    title: user.name || `@${user.username || ''}`,
    subtitle: `@${user.username || ''}`,
    queryValue: user.username || '',
    avatarUrl: toAvatarUrl(user),
  }))
}

const mapPostSuggestions = (payload) => {
  const items = Array.isArray(payload?.data) ? payload.data : []
  return items.slice(0, 8).map((post) => {
    const text = String(post.content || '').replace(/\s+/g, ' ').trim()
    const snippet = text.length > 84 ? `${text.slice(0, 84)}...` : text || '(Bez textu)'
    const authorName = post.user?.name || 'Neznamy autor'
    const authorHandle = post.user?.username ? `@${post.user.username}` : ''

    return {
      key: `post-${post.id}`,
      kind: 'post',
      title: snippet,
      subtitle: authorHandle ? `${authorName} • ${authorHandle}` : authorName,
      queryValue: snippet,
      avatarUrl: '',
    }
  })
}

const fetchSuggestions = async () => {
  if (!hasMinLength.value) {
    suggestions.value = []
    resetDropdown()
    isLoadingSuggestions.value = false
    cancelInFlight()
    return
  }

  cancelInFlight()
  requestController = new AbortController()
  const token = ++requestToken
  isLoadingSuggestions.value = true

  try {
    const endpoint = props.mode === 'posts' ? '/search/posts' : '/search/users'
    const response = await api.get(endpoint, {
      params: {
        q: normalizedQuery.value,
        limit: 8,
      },
      signal: requestController.signal,
      meta: { skipErrorToast: true },
    })

    if (token !== requestToken) return
    suggestions.value = props.mode === 'posts'
      ? mapPostSuggestions(response.data)
      : mapUserSuggestions(response.data)
    isOpen.value = true
    activeIndex.value = -1
  } catch (error) {
    if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return
    if (token !== requestToken) return
    suggestions.value = []
    isOpen.value = true
    activeIndex.value = -1
  } finally {
    if (token === requestToken) {
      isLoadingSuggestions.value = false
    }
  }
}

const scheduleFetch = () => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchSuggestions, 300)
}

const applySubmit = (nextQuery = localQuery.value) => {
  emit('submit', String(nextQuery || '').trim())
  resetDropdown()
}

const selectSuggestion = (item) => {
  if (!item) return
  localQuery.value = item.queryValue
  emit('update:modelValue', item.queryValue)
  applySubmit(item.queryValue)
}

const onSubmit = () => {
  if (activeIndex.value >= 0 && suggestions.value[activeIndex.value]) {
    selectSuggestion(suggestions.value[activeIndex.value])
    return
  }
  applySubmit(localQuery.value)
}

const onKeydown = (event) => {
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

const onFocus = () => {
  isFocused.value = true
  if (blurTimer) {
    clearTimeout(blurTimer)
    blurTimer = null
  }
  if (hasMinLength.value) {
    isOpen.value = true
    if (!suggestions.value.length) scheduleFetch()
  }
}

const onBlur = () => {
  isFocused.value = false
  blurTimer = setTimeout(() => {
    resetDropdown()
  }, 120)
}

const clearQuery = () => {
  localQuery.value = ''
  suggestions.value = []
  isLoadingSuggestions.value = false
  resetDropdown()
  emit('update:modelValue', '')
  emit('submit', '')
  inputRef.value?.focus()
}

const onOutsideClick = (event) => {
  const target = event.target
  const root = rootRef.value
  if (!root || !(target instanceof Node)) return
  if (!root.contains(target)) {
    isFocused.value = false
    resetDropdown()
  }
}

watch(
  () => props.modelValue,
  (nextValue) => {
    const normalized = String(nextValue || '')
    if (normalized !== localQuery.value) {
      localQuery.value = normalized
    }
  },
  { immediate: true },
)

watch(
  () => props.mode,
  () => {
    suggestions.value = []
    activeIndex.value = -1
    cancelInFlight()
    if (hasMinLength.value && isFocused.value) {
      scheduleFetch()
    } else {
      resetDropdown()
    }
  },
)

watch(localQuery, (value) => {
  emit('update:modelValue', value)
  activeIndex.value = -1

  if (!hasMinLength.value) {
    suggestions.value = []
    isLoadingSuggestions.value = false
    cancelInFlight()
    resetDropdown()
    return
  }

  if (!isFocused.value) return
  isOpen.value = true
  scheduleFetch()
})

onMounted(() => {
  document.addEventListener('mousedown', onOutsideClick)
})

onBeforeUnmount(() => {
  clearTimers()
  cancelInFlight()
  document.removeEventListener('mousedown', onOutsideClick)
})
</script>
