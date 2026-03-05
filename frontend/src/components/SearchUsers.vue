<template>
  <div class="w-full space-y-4">
    <input
      v-model="searchQuery"
      type="search"
      placeholder="Hladat pouzivatelov"
      class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"
      @input="onSearchInput"
    >

    <div v-if="searchQuery.length < 2" class="text-sm text-slate-500">
      Zadaj aspon 2 znaky.
    </div>

    <div v-else-if="isLoading" class="text-sm text-slate-500">Nacitavam...</div>

    <div v-else-if="users.length === 0" class="text-sm text-slate-500">
      Nenasli sa ziadni pouzivatelia.
    </div>

    <ul v-else class="space-y-2">
      <li
        v-for="user in users"
        :key="user.id"
        class="rounded-xl border border-slate-200 bg-white p-3"
      >
        <RouterLink :to="`/users/${user.username}`" class="block hover:underline">
          <div class="text-sm font-medium text-slate-900">{{ user.name }}</div>
          <div class="text-xs text-slate-500">@{{ user.username }}</div>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/services/api'

const props = defineProps({
  initialQuery: {
    type: String,
    default: '',
  },
})

const searchQuery = ref('')
const users = ref([])
const isLoading = ref(false)

watch(
  () => props.initialQuery,
  (newQuery) => {
    if (newQuery !== searchQuery.value) {
      searchQuery.value = newQuery
    }
  },
  { immediate: true }
)

let timeoutId = null

const searchUsers = (query) => {
  if (timeoutId) clearTimeout(timeoutId)

  timeoutId = setTimeout(async () => {
    if ((query || '').length < 2) {
      users.value = []
      return
    }

    try {
      isLoading.value = true
      const response = await api.get('/search/users', {
        params: { q: query, limit: 10 },
      })
      users.value = Array.isArray(response?.data) ? response.data : []
    } catch (error) {
      console.error('Search users failed:', error)
      users.value = []
    } finally {
      isLoading.value = false
    }
  }, 250)
}

watch(
  searchQuery,
  (newQuery) => {
    searchUsers(newQuery)
  },
  { immediate: true }
)

onUnmounted(() => {
  if (timeoutId) clearTimeout(timeoutId)
})

const onSearchInput = (event) => {
  searchQuery.value = event.target.value
}
</script>
