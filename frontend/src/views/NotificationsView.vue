<template>
  <section class="min-h-screen bg-[var(--bg-app)] px-4 py-10 text-[var(--text-primary)] sm:px-8">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-4xl font-black tracking-tight sm:text-5xl">Notifications</h1>
          <p class="mt-2 text-sm text-[var(--text-secondary)]">Your latest activity updates.</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            data-testid="open-notification-settings"
            class="ui-pill ui-pill--secondary text-xs uppercase tracking-wide"
            type="button"
            @click="openSettingsModal"
          >
            Nastavenia
          </button>
          <button class="ui-pill ui-pill--secondary text-xs uppercase tracking-wide" type="button" @click="markAll">
            Mark all read
          </button>
        </div>
      </header>

      <div class="rounded-2xl bg-[color:rgb(var(--bg-surface-rgb)/0.42)]">
        <div v-if="isInitialLoading" class="space-y-2 px-6 py-4" data-testid="notifications-page-loading">
          <div
            v-for="index in 4"
            :key="`notification-list-skeleton-${index}`"
            class="h-14 animate-pulse rounded-xl bg-[color:rgb(var(--bg-surface-2-rgb)/0.45)]"
          ></div>
        </div>

        <div v-else-if="error" class="px-6 py-6 text-center" data-testid="notifications-page-error">
          <p class="text-sm text-[var(--primary-active)]">{{ error }}</p>
          <button type="button" class="ui-pill ui-pill--secondary mt-3 text-xs uppercase tracking-wide" @click="retry">
            Retry
          </button>
        </div>

        <div v-else-if="!items.length" class="px-6 py-10 text-center text-sm text-[var(--text-muted)]" data-testid="notifications-page-empty">
          No notifications yet.
        </div>

        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="group flex w-full items-center gap-4 border-b border-[var(--divider-color)] px-6 py-5 text-left transition hover:bg-[color:rgb(var(--bg-surface-2-rgb)/0.52)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--primary)] last:border-b-0"
          :class="item.read_at ? 'opacity-80' : 'bg-[color:rgb(var(--bg-surface-2-rgb)/0.42)]'"
          @click="openNotification(item)"
        >
          <span
            class="h-2 w-2 flex-none rounded-full"
            :class="item.read_at ? 'bg-transparent' : 'bg-[var(--text-primary)] shadow-[0_0_8px_rgb(var(--text-primary-rgb)/0.55)]'"
          ></span>
          <div class="flex-1">
            <p class="text-sm font-semibold text-[var(--text-primary)]">{{ formatTitle(item) }}</p>
            <p class="mt-1 text-xs text-[var(--text-secondary)]">{{ formatSubtitle(item) }}</p>
          </div>
          <span class="text-xs text-[var(--text-secondary)]">{{ formatTime(item.created_at) }}</span>
        </button>

        <div v-if="isPaginating" class="px-6 py-4 text-xs text-[var(--text-muted)]" data-testid="notifications-page-paginating">
          Loading more...
        </div>
      </div>

      <button
        v-if="page < lastPage"
        class="ui-pill ui-pill--secondary mx-auto text-xs uppercase tracking-wide"
        type="button"
        :disabled="isPaginating"
        @click="loadMore"
      >
        {{ isPaginating ? 'Loading...' : 'Load more' }}
      </button>
    </div>
  </section>

  <BaseModal
    v-model:open="isSettingsModalOpen"
    title="Nastavenia notifikacii"
    test-id="notification-settings-modal"
    close-test-id="close-notification-settings"
    @close="handleModalClose"
  >
    <template #description>
      <p class="mt-2 text-sm text-[var(--text-secondary)]">Vyber si upozornenia pre pozorovanie oblohy.</p>
    </template>

    <div id="notification-settings" class="space-y-3">
      <button
        v-if="preferencesError"
        type="button"
        class="ui-pill ui-pill--secondary text-xs uppercase tracking-wide"
        @click="retryPreferences"
      >
        Retry
      </button>

      <div v-if="preferencesLoading" class="space-y-3" aria-hidden="true">
        <div
          v-for="index in 2"
          :key="`preference-skeleton-${index}`"
          class="h-16 animate-pulse rounded-2xl bg-[color:rgb(var(--text-secondary-rgb)/0.15)]"
        ></div>
      </div>

      <div v-else class="rounded-2xl bg-[color:rgb(var(--bg-surface-rgb)/0.32)]">
        <p
          v-if="preferencesError"
          class="border-b border-[var(--divider-color)] px-4 py-3 text-sm text-[var(--text-primary)]"
          role="status"
        >
          Nastavenia notifikacii su docasne nedostupne. Skus znova.
        </p>

        <label
          class="flex items-center justify-between gap-4 border-b border-[var(--divider-color)] bg-[color:rgb(var(--bg-surface-2-rgb)/0.32)] px-4 py-4"
        >
          <div class="min-w-0">
            <p class="text-sm font-semibold text-[var(--text-primary)]">Upozornit ma pri vybornych podmienkach</p>
            <p class="mt-1 text-xs text-[var(--text-secondary)]">Dostanes upozornenie, ked bude obloha vhodna na pozorovanie.</p>
          </div>
          <button
            type="button"
            class="inline-flex h-7 w-12 items-center rounded-full border border-[var(--border)] px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
            :class="preferences.good_conditions_alerts ? 'justify-end bg-[color:rgb(var(--primary-rgb)/0.32)]' : 'justify-start bg-[color:rgb(var(--text-primary-rgb)/0.05)]'"
            :disabled="isPreferenceToggleDisabled"
            :aria-pressed="preferences.good_conditions_alerts"
            @click="togglePreference('good_conditions_alerts')"
          >
            <span class="h-5 w-5 rounded-full bg-[var(--text-primary)]"></span>
          </button>
        </label>

        <label
          class="flex items-center justify-between gap-4 bg-[color:rgb(var(--bg-surface-2-rgb)/0.32)] px-4 py-4"
        >
          <div class="min-w-0">
            <p class="text-sm font-semibold text-[var(--text-primary)]">Upozornit ma na ISS prelet</p>
            <p class="mt-1 text-xs text-[var(--text-secondary)]">Dostanes upozornenie pred dalsim dobre viditelnym preletom ISS.</p>
          </div>
          <button
            type="button"
            class="inline-flex h-7 w-12 items-center rounded-full border border-[var(--border)] px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
            :class="preferences.iss_alerts ? 'justify-end bg-[color:rgb(var(--primary-rgb)/0.32)]' : 'justify-start bg-[color:rgb(var(--text-primary-rgb)/0.05)]'"
            :disabled="isPreferenceToggleDisabled"
            :aria-pressed="preferences.iss_alerts"
            @click="togglePreference('iss_alerts')"
          >
            <span class="h-5 w-5 rounded-full bg-[var(--text-primary)]"></span>
          </button>
        </label>
      </div>
    </div>
  </BaseModal>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseModal from '@/components/ui/BaseModal.vue'
import { useNotificationsStore } from '@/stores/notifications'
import { useNotificationAlertPreferences } from '@/composables/useNotificationAlertPreferences'
import { useAuthStore } from '@/stores/auth'

const SETTINGS_HASH = '#notification-settings'

const store = useNotificationsStore()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const items = computed(() => store.items)
const loading = computed(() => store.loading)
const loadingMore = computed(() => store.loadingMore)
const error = computed(() => store.error)
const page = computed(() => store.page)
const lastPage = computed(() => store.lastPage)
const isSettingsModalOpen = ref(false)
const isInitialLoading = computed(() => loading.value && items.value.length === 0)
const isPaginating = computed(() => loadingMore.value || (loading.value && items.value.length > 0))

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

watch(
  () => route.hash,
  (hash) => {
    isSettingsModalOpen.value = hash === SETTINGS_HASH
  },
  { immediate: true },
)

watch(isSettingsModalOpen, (isOpen) => {
  if (isOpen && route.hash !== SETTINGS_HASH) {
    void router.replace({ path: route.path, query: route.query, hash: SETTINGS_HASH })
    return
  }

  if (!isOpen && route.hash === SETTINGS_HASH) {
    void router.replace({ path: route.path, query: route.query, hash: '' })
  }
})

onMounted(() => {
  store.fetchList(1)
  store.fetchUnreadCount()
  fetchPreferences()
})

const loadMore = () => store.fetchList(store.page + 1)
const markAll = () => store.markAllRead()
const retry = () => store.fetchList(1)
const retryPreferences = () => fetchPreferences()

function openSettingsModal() {
  isSettingsModalOpen.value = true
}

function handleModalClose() {
  isSettingsModalOpen.value = false
}

async function togglePreference(key) {
  if (isPreferenceToggleDisabled.value) return

  await updatePreferences({
    iss_alerts: key === 'iss_alerts' ? !preferences.value.iss_alerts : preferences.value.iss_alerts,
    good_conditions_alerts: key === 'good_conditions_alerts'
      ? !preferences.value.good_conditions_alerts
      : preferences.value.good_conditions_alerts,
  })
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
