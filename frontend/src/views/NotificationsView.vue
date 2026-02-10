<template>
  <section class="min-h-screen bg-black px-4 py-10 text-white sm:px-8">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-4xl font-black tracking-tight sm:text-5xl">Notifications</h1>
          <p class="mt-2 text-sm text-[#9a9a9a]">Your latest activity updates.</p>
        </div>
        <button
          class="rounded-full border border-[#1f1f1f] bg-[#0d0d0d] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#cfcfcf] transition hover:border-[#2a2a2a] hover:text-white"
          type="button"
          @click="markAll"
        >
          Mark all read
        </button>
      </header>

      <div class="rounded-2xl border border-[#121212] bg-[#050505]">
        <div v-if="error && !loading" class="px-6 py-6 text-center">
          <p class="text-sm text-rose-300">{{ error }}</p>
          <button
            type="button"
            class="mt-3 rounded-full border border-[#2b2b2b] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#d0d0d0] transition hover:border-[#3a3a3a] hover:text-white"
            @click="retry"
          >
            Retry
          </button>
        </div>

        <div v-else-if="!items.length && !loading" class="px-6 py-10 text-center text-sm text-[#8a8a8a]">
          No notifications yet.
        </div>

        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="group flex w-full items-center gap-4 border-b border-[#1a1a1a] px-6 py-5 text-left transition hover:bg-[#0e0e0e] focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
          :class="item.read_at ? 'opacity-80' : 'bg-[#0a0a0a]'"
          @click="openNotification(item)"
        >
          <span
            class="h-2 w-2 flex-none rounded-full"
            :class="item.read_at ? 'bg-transparent' : 'bg-white shadow-[0_0_8px_rgba(255,255,255,0.55)]'"
          ></span>
          <div class="flex-1">
            <p class="text-sm font-semibold text-white">{{ formatTitle(item) }}</p>
            <p class="mt-1 text-xs text-[#9a9a9a]">{{ formatSubtitle(item) }}</p>
          </div>
          <span class="text-xs text-[#b5b5b5]">{{ formatTime(item.created_at) }}</span>
        </button>

        <div v-if="loading" class="px-6 py-4 text-xs text-[#8a8a8a]">Loading...</div>
      </div>

      <button
        v-if="page < lastPage && !loading"
        class="mx-auto rounded-full border border-[#1c1c1c] px-5 py-2 text-xs font-semibold uppercase tracking-wide text-[#d0d0d0] transition hover:border-[#2a2a2a] hover:text-white"
        type="button"
        @click="loadMore"
      >
        Load more
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'

const store = useNotificationsStore()
const router = useRouter()

const items = computed(() => store.items)
const loading = computed(() => store.loading)
const error = computed(() => store.error)
const page = computed(() => store.page)
const lastPage = computed(() => store.lastPage)

onMounted(() => {
  store.fetchList(1)
  store.fetchUnreadCount()
})

const loadMore = () => store.fetchList(store.page + 1)
const markAll = () => store.markAllRead()
const retry = () => store.fetchList(1)

const openNotification = async (item) => {
  if (!item) return
  if (!item.read_at) await store.markRead(item.id)
  const target = item.target
  if (target?.url) {
    router.push(target.url)
  }
}

const formatTitle = (item) => {
  if (item.type === 'post_liked') {
    const name = item.data?.actor_name || item.data?.actor_username || 'Someone'
    return `${name} liked your post`
  }
  if (item.type === 'event_reminder') {
    return 'Upcoming event reminder'
  }
  return 'Notification'
}

const formatSubtitle = (item) => {
  if (item.type === 'post_liked') {
    const username = item.data?.actor_username ? `@${item.data.actor_username}` : ''
    return username || 'Community activity'
  }
  if (item.type === 'event_reminder') {
    return item.data?.event_title || 'Event starts soon'
  }
  return 'New update'
}

const formatTime = (iso) => {
  if (!iso) return ''
  const created = new Date(iso)
  const diffMs = Date.now() - created.getTime()
  const minutes = Math.floor(diffMs / 60000)
  if (minutes < 60) return `${Math.max(minutes, 1)}m`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h`
  return created.toLocaleDateString('sk-SK')
}
</script>
