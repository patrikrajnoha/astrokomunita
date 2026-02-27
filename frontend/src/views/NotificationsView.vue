<template>
  <section class="min-h-screen bg-[var(--color-bg)] px-4 py-10 text-white sm:px-8">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-4xl font-black tracking-tight sm:text-5xl">Notifications</h1>
          <p class="mt-2 text-sm text-[#9a9a9a]">Your latest activity updates.</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.88)] text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-white"
            type="button"
            aria-label="Nastavenia notifikacii"
            @click="openSettings"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path
                d="M8.2 2h3.6l.5 2a6 6 0 0 1 1.4.8l1.9-.9 2.5 2.5-.9 1.9c.3.4.6.9.8 1.4l2 .5v3.6l-2 .5a6.4 6.4 0 0 1-.8 1.4l.9 1.9-2.5 2.5-1.9-.9a6.4 6.4 0 0 1-1.4.8l-.5 2H8.2l-.5-2a6 6 0 0 1-1.4-.8l-1.9.9-2.5-2.5.9-1.9a6.4 6.4 0 0 1-.8-1.4l-2-.5V9.8l2-.5c.2-.5.5-1 .8-1.4l-.9-1.9 2.5-2.5 1.9.9c.4-.3.9-.6 1.4-.8l.5-2Z"
                stroke="currentColor"
                stroke-width="1.2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.2" />
            </svg>
          </button>
          <button
            class="rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.88)] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-white"
            type="button"
            @click="markAll"
          >
            Mark all read
          </button>
        </div>
      </header>

      <div class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.66)]">
        <div v-if="error && !loading" class="px-6 py-6 text-center">
          <p class="text-sm text-rose-300">{{ error }}</p>
          <button
            type="button"
            class="mt-3 rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-white"
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
          class="group flex w-full items-center gap-4 border-b border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] px-6 py-5 text-left transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.8)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
          :class="item.read_at ? 'opacity-80' : 'bg-[color:rgb(var(--color-bg-rgb)/0.4)]'"
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
        class="mx-auto rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-5 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-white"
        type="button"
        @click="loadMore"
      >
        Load more
      </button>
    </div>

    <NotificationSettingsModal
      :open="settingsOpen"
      @close="settingsOpen = false"
      @saved="onSettingsSaved"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'
import NotificationSettingsModal from '@/components/notifications/NotificationSettingsModal.vue'

const store = useNotificationsStore()
const router = useRouter()

const items = computed(() => store.items)
const loading = computed(() => store.loading)
const error = computed(() => store.error)
const page = computed(() => store.page)
const lastPage = computed(() => store.lastPage)
const settingsOpen = ref(false)

onMounted(() => {
  store.fetchList(1)
  store.fetchUnreadCount()
})

const loadMore = () => store.fetchList(store.page + 1)
const markAll = () => store.markAllRead()
const retry = () => store.fetchList(1)
const openSettings = () => {
  settingsOpen.value = true
}

const onSettingsSaved = () => {
  store.fetchList(1)
}

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
  if (item.type === 'contest_winner') {
    return 'You won the contest'
  }
  if (item.type === 'event_invite') {
    return 'You received an event invite'
  }
  if (item.type === 'account_restricted') {
    return 'Account restricted'
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
  if (item.type === 'contest_winner') {
    return item.data?.contest_name || 'Contest winner'
  }
  if (item.type === 'event_invite') {
    const inviter = item.data?.actor_name || item.data?.actor_username
    const title = item.data?.event_title
    if (inviter && title) return `${inviter} invited you to ${title}`
    if (inviter) return `${inviter} invited you to an event`
    return title || 'You were invited to an event'
  }
  if (item.type === 'account_restricted') {
    return item.data?.reason || 'Contact support for details.'
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
