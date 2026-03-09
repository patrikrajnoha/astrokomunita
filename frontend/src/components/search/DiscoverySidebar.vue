<template>
  <div class="ui-section-stack rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.66)] backdrop-blur">
    <section class="p-4">
      <header class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-[var(--color-surface)]">Trendy</h2>
        <button type="button" class="text-xs text-[var(--color-primary)] transition hover:opacity-80" @click="emit('refresh')">
          Obnovit
        </button>
      </header>

      <div v-if="loadingTrending" class="space-y-2">
        <div v-for="index in 6" :key="`trend-skeleton-${index}`" class="h-8 animate-pulse rounded-lg bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)]"></div>
      </div>

      <div v-else-if="trending.length" class="ui-list-divider">
        <RouterLink
          v-for="(item, index) in trending"
          :key="`trending-${item.id || item.name}`"
          :to="{ name: 'hashtag-feed', params: { name: item.name } }"
          class="flex items-center justify-between px-0 py-2.5 text-sm text-[var(--color-surface)] transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.88)]"
        >
          <span class="truncate">{{ index + 1 }}. #{{ item.name }}</span>
          <svg class="h-4 w-4 text-[color:rgb(var(--color-text-secondary-rgb)/0.65)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </RouterLink>
      </div>

      <p v-else class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.86)]">Zatial bez trending poloziek.</p>
    </section>

    <section class="p-4">
      <h2 class="mb-3 text-sm font-semibold text-[var(--color-surface)]">Na koho sledovat</h2>

      <div v-if="loadingUsers" class="space-y-2">
        <div v-for="index in 5" :key="`users-skeleton-${index}`" class="h-12 animate-pulse rounded-lg bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)]"></div>
      </div>

      <div v-else-if="recommendedUsers.length" class="ui-list-divider">
        <RouterLink
          v-for="user in recommendedUsers.slice(0, 5)"
          :key="`sidebar-user-${user.id}`"
          :to="{ name: 'user-profile', params: { username: user.username } }"
          class="flex items-center gap-3 px-0 py-2.5 transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.88)]"
        >
          <UserAvatar class="h-9 w-9 rounded-full object-cover" :user="user" :size="36" :alt="user.name || user.username" />
          <div class="min-w-0">
            <div class="truncate text-sm font-medium text-[var(--color-surface)]">{{ user.name || user.username }}</div>
            <div class="truncate text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">@{{ user.username }}</div>
          </div>
        </RouterLink>
      </div>

      <p v-else class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.86)]">Odporucania pre ucty zatial nie su dostupne.</p>
    </section>

    <section class="p-4">
      <h2 class="mb-3 text-sm font-semibold text-[var(--color-surface)]">Popularne prispevky</h2>

      <div v-if="loadingPosts" class="space-y-2">
        <div v-for="index in 5" :key="`posts-skeleton-${index}`" class="h-14 animate-pulse rounded-lg bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)]"></div>
      </div>

      <div v-else-if="popularPosts.length" class="ui-list-divider">
        <RouterLink
          v-for="post in popularPosts.slice(0, 5)"
          :key="`sidebar-post-${post.id}`"
          :to="{ name: 'post-detail', params: { id: post.id } }"
          class="block px-0 py-2.5 transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.88)]"
        >
          <p class="line-clamp-2 text-sm text-[var(--color-surface)]">{{ snippet(post.content) }}</p>
          <p class="mt-1 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
            {{ post.user?.name || 'Neznamy autor' }} • {{ post.likes_count || 0 }} lajkov
          </p>
        </RouterLink>
      </div>

      <p v-else class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.86)]">Popularne prispevky sa zatial nenasli.</p>
    </section>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import UserAvatar from '@/components/UserAvatar.vue'

defineProps({
  trending: {
    type: Array,
    default: () => [],
  },
  recommendedUsers: {
    type: Array,
    default: () => [],
  },
  popularPosts: {
    type: Array,
    default: () => [],
  },
  loadingTrending: {
    type: Boolean,
    default: false,
  },
  loadingUsers: {
    type: Boolean,
    default: false,
  },
  loadingPosts: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['refresh'])

const snippet = (content) => {
  const text = String(content || '').replace(/\s+/g, ' ').trim()
  if (!text) return '(Bez textu)'
  return text.length > 92 ? `${text.slice(0, 92)}...` : text
}
</script>


