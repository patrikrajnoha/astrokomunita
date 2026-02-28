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
            class="rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.88)] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-white"
            type="button"
            @click="scrollToSettings"
          >
            Nastavenia
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

      <section
        id="notification-settings"
        class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.66)] p-6"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-xl font-black tracking-tight text-white">Nastavenia notifikácií</h2>
            <p class="mt-2 text-sm text-[#9a9a9a]">Vyber si upozornenia pre pozorovanie oblohy.</p>
          </div>
          <button
            v-if="preferencesError"
            type="button"
            class="rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-white"
            @click="retryPreferences"
          >
            Retry
          </button>
        </div>

        <div v-if="preferencesLoading" class="mt-5 space-y-3" aria-hidden="true">
          <div
            v-for="index in 2"
            :key="`preference-skeleton-${index}`"
            class="h-16 animate-pulse rounded-2xl bg-[color:rgb(var(--color-text-secondary-rgb)/0.15)]"
          ></div>
        </div>

        <div v-else class="mt-5 space-y-3">
          <p
            v-if="preferencesError"
            class="rounded-2xl border border-rose-500/35 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
            role="status"
          >
            Nastavenia notifikácií sú dočasne nedostupné. Skús znova.
          </p>

          <label
            class="flex items-center justify-between gap-4 rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.35)] px-4 py-4"
          >
            <div class="min-w-0">
              <p class="text-sm font-semibold text-white">Upozorniť ma pri výborných podmienkach</p>
              <p class="mt-1 text-xs text-[#9a9a9a]">Dostaneš upozornenie, keď bude obloha vhodná na pozorovanie.</p>
            </div>
            <button
              type="button"
              class="inline-flex h-7 w-12 items-center rounded-full border border-white/10 px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
              :class="preferences.good_conditions_alerts ? 'justify-end bg-emerald-500/30' : 'justify-start bg-white/5'"
              :disabled="isPreferenceToggleDisabled"
              :aria-pressed="preferences.good_conditions_alerts"
              @click="togglePreference('good_conditions_alerts')"
            >
              <span class="h-5 w-5 rounded-full bg-white"></span>
            </button>
          </label>

          <label
            class="flex items-center justify-between gap-4 rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.35)] px-4 py-4"
          >
            <div class="min-w-0">
              <p class="text-sm font-semibold text-white">Upozorniť ma na ISS prelet</p>
              <p class="mt-1 text-xs text-[#9a9a9a]">Dostaneš upozornenie pred ďalším dobre viditeľným preletom ISS.</p>
            </div>
            <button
              type="button"
              class="inline-flex h-7 w-12 items-center rounded-full border border-white/10 px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
              :class="preferences.iss_alerts ? 'justify-end bg-emerald-500/30' : 'justify-start bg-white/5'"
              :disabled="isPreferenceToggleDisabled"
              :aria-pressed="preferences.iss_alerts"
              @click="togglePreference('iss_alerts')"
            >
              <span class="h-5 w-5 rounded-full bg-white"></span>
            </button>
          </label>
        </div>
      </section>

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
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'
import { useNotificationAlertPreferences } from '@/composables/useNotificationAlertPreferences'
import { useAuthStore } from '@/stores/auth'

const store = useNotificationsStore()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const items = computed(() => store.items)
const loading = computed(() => store.loading)
const error = computed(() => store.error)
const page = computed(() => store.page)
const lastPage = computed(() => store.lastPage)

const {
  preferences,
  preferencesLoading,
  preferencesError,
  fetchPreferences,
  updatePreferences,
} = useNotificationAlertPreferences({
  isAuthenticated: computed(() => auth.isAuthed),
})

const isPreferenceToggleDisabled = computed(() => preferencesLoading.value || preferencesError.value)

onMounted(async () => {
  store.fetchList(1)
  store.fetchUnreadCount()
  fetchPreferences()

  if (route.hash === '#notification-settings') {
    await nextTick()
    scrollToSettings(false)
  }
})

const loadMore = () => store.fetchList(store.page + 1)
const markAll = () => store.markAllRead()
const retry = () => store.fetchList(1)
const retryPreferences = () => fetchPreferences()

async function togglePreference(key) {
  if (isPreferenceToggleDisabled.value) return

  await updatePreferences({
    iss_alerts: key === 'iss_alerts' ? !preferences.value.iss_alerts : preferences.value.iss_alerts,
    good_conditions_alerts: key === 'good_conditions_alerts'
      ? !preferences.value.good_conditions_alerts
      : preferences.value.good_conditions_alerts,
  })
}

function scrollToSettings(updateHash = true) {
  const element = document.getElementById('notification-settings')
  if (updateHash && route.hash !== '#notification-settings') {
    router.replace({ hash: '#notification-settings' })
  }
  element?.scrollIntoView({ behavior: 'smooth', block: 'start' })
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
  if (item.type === 'iss_pass_alert') {
    return 'ISS pass soon'
  }
  if (item.type === 'good_conditions_alert') {
    return 'Great observing conditions'
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
  if (item.type === 'iss_pass_alert') {
    return item.data?.next_pass_at ? `Next pass: ${formatClock(item.data.next_pass_at)}` : 'A pass is coming soon.'
  }
  if (item.type === 'good_conditions_alert') {
    const score = Number(item.data?.observing_score)
    return Number.isFinite(score) ? `Observing score ${Math.round(score)}/100.` : 'Sky conditions look strong tonight.'
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

const formatClock = (iso) => {
  if (!iso) return ''
  const value = new Date(iso)
  if (Number.isNaN(value.getTime())) return ''
  return value.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit', hour12: false })
}
</script>
