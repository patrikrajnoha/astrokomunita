<template>
  <div class="space-y-6">
    <!-- Recommended users -->
    <div class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-4">
      <h3 class="font-semibold text-[var(--color-surface)] mb-3">Na koho sledovať</h3>
      
      <div v-if="isLoadingUsers" class="space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-center gap-3 animate-pulse">
          <div class="h-10 w-10 bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)] rounded-full"></div>
          <div class="flex-1">
            <div class="h-4 bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)] rounded w-3/4"></div>
          </div>
        </div>
      </div>

      <div v-else-if="recommendedUsers.length > 0" class="space-y-3">
        <RouterLink
          v-for="user in recommendedUsers"
          :key="user.id"
          :to="`/users/${user.username}`"
          class="flex items-center gap-3 p-2 rounded-lg hover:bg-[color:rgb(var(--color-bg-rgb)/0.8)]"
        >
          <img
            :src="user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`"
            class="h-10 w-10 rounded-full object-cover"
          />
          <div class="flex-1">
            <div class="font-medium text-[var(--color-surface)]">{{ user.name }}</div>
            <div class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">@{{ user.username }}</div>
          </div>
        </RouterLink>
      </div>
    </div>

    <!-- Recommended posts -->
    <div class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-4">
      <h3 class="font-semibold text-[var(--color-surface)] mb-3">Populárne príspevky</h3>
      
      <div v-if="isLoadingPosts" class="space-y-3">
        <div v-for="i in 3" :key="i" class="animate-pulse">
          <div class="h-4 bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)] rounded"></div>
        </div>
      </div>

      <div v-else-if="recommendedPosts.length > 0" class="space-y-3">
        <RouterLink
          v-for="post in recommendedPosts"
          :key="post.id"
          :to="`/posts/${post.id}`"
          class="block p-3 rounded-lg hover:bg-[color:rgb(var(--color-bg-rgb)/0.8)]"
        >
          <div class="text-sm text-[var(--color-surface)] mb-2">{{ post.content.substring(0, 100) }}...</div>
          <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">
            {{ post.user.name }} · {{ post.likes_count }} likes
          </div>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import http from '@/services/api'

const recommendedUsers = ref([])
const recommendedPosts = ref([])
const isLoadingUsers = ref(false)
const isLoadingPosts = ref(false)

const loadRecommendedUsers = async () => {
  try {
    isLoadingUsers.value = true
    const response = await http.get('/recommendations/users?limit=5')
    recommendedUsers.value = response.data || []
  } catch (err) {
    if (err.response?.status === 401) {
      // Neprihlásený používateľ - ignorujeme
      console.log('Recommendations require authentication')
    } else {
      console.error('Error loading recommended users:', err)
    }
    recommendedUsers.value = []
  } finally {
    isLoadingUsers.value = false
  }
}

const loadRecommendedPosts = async () => {
  try {
    isLoadingPosts.value = true
    const response = await http.get('/recommendations/posts?limit=5')
    recommendedPosts.value = response.data || []
  } catch (err) {
    if (err.response?.status === 401) {
      // Neprihlásený používateľ - ignorujeme
      console.log('Recommendations require authentication')
    } else {
      console.error('Error loading recommended posts:', err)
    }
    recommendedPosts.value = []
  } finally {
    isLoadingPosts.value = false
  }
}

onMounted(() => {
  loadRecommendedUsers()
  loadRecommendedPosts()
})
</script>
