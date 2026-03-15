<template>
  <section class="min-h-screen bg-[linear-gradient(180deg,rgb(var(--bg-app-rgb)/0.98)_0%,rgb(var(--bg-app-rgb)/0.95)_48%,rgb(var(--bg-surface-rgb)/0.94)_100%)] text-[var(--text-primary)]">
    <div class="mx-auto flex min-h-screen w-full max-w-5xl flex-col">
      <header class="flex items-center justify-between px-5 pb-5 pt-6 sm:px-8">
        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Notifikacie</h1>
        <button
          data-testid="open-notification-settings"
          class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[var(--color-border)] text-[var(--text-secondary)] transition hover:border-[var(--color-border-strong)] hover:text-[var(--text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--primary)]"
          type="button"
          @click="openSettingsModal"
        >
          <span class="sr-only">Otvorit nastavenia notifikacii</span>
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="3.2"></circle>
            <path d="M19.4 14.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.56V20.5a2 2 0 0 1-4 0v-.08a1.7 1.7 0 0 0-1-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1H3.5a2 2 0 0 1 0-4h.08a1.7 1.7 0 0 0 1.56-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.01a1.7 1.7 0 0 0 1-1.56V3.5a2 2 0 0 1 4 0v.08a1.7 1.7 0 0 0 1 1.56h.01a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.01a1.7 1.7 0 0 0 1.56 1H20.5a2 2 0 0 1 0 4h-.08a1.7 1.7 0 0 0-1.56 1z"></path>
          </svg>
        </button>
      </header>

      <div class="border-b border-[var(--divider-color)]"></div>

      <div v-if="isInitialLoading" class="mx-auto w-full max-w-3xl space-y-3 px-5 py-7 sm:px-8" data-testid="notifications-page-loading">
        <div
          v-for="index in 5"
          :key="`notification-list-skeleton-${index}`"
          class="h-16 animate-pulse rounded-2xl bg-[color:rgb(var(--bg-surface-2-rgb)/0.5)]"
        ></div>
      </div>

      <div v-else-if="error" class="flex flex-1 flex-col items-center justify-center px-5 py-12 text-center" data-testid="notifications-page-error">
        <InlineStatus
          variant="error"
          :message="error || 'Nastala chyba pri nacitani notifikacii.'"
          action-label="Skusit znova"
          class="w-full max-w-lg"
          @action="retry"
        />
      </div>

      <div v-else-if="!items.length" class="flex flex-1 flex-col items-center px-5 py-16 text-center">
        <div class="mt-8 flex h-full min-h-[60vh] flex-col items-center pt-16" data-testid="notifications-page-empty">
          <svg viewBox="0 0 24 24" class="h-14 w-14 text-[color:rgb(var(--text-secondary-rgb)/0.88)]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6.5 8a5.5 5.5 0 1 1 11 0c0 2.6.7 4.4 1.8 5.8.5.6.1 1.2-.7 1.2H5.4c-.8 0-1.2-.7-.7-1.2C5.8 12.4 6.5 10.6 6.5 8Z"></path>
            <path d="M9.5 18a2.5 2.5 0 0 0 5 0"></path>
          </svg>
          <p class="mt-4 text-2xl font-semibold tracking-tight text-[color:rgb(var(--text-secondary-rgb)/0.88)] sm:text-3xl">Zatial ziadne notifikacie.</p>
        </div>
      </div>

      <div v-else class="mx-auto w-full max-w-3xl flex-1 px-5 py-7 sm:px-8">
        <div class="mb-4 flex justify-end">
          <button class="ui-pill ui-pill--secondary text-xs uppercase tracking-wide" type="button" @click="markAll">
            Oznacit vsetko ako precitane
          </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[color:rgb(var(--bg-surface-rgb)/0.4)]">
          <button
            v-for="item in items"
            :key="item.id"
            type="button"
            class="group flex w-full items-center gap-4 border-b border-[var(--divider-color)] px-5 py-4 text-left transition hover:bg-[color:rgb(var(--bg-surface-2-rgb)/0.58)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--primary)] last:border-b-0"
            :class="item.read_at ? 'opacity-80' : 'bg-[color:rgb(var(--bg-surface-2-rgb)/0.42)]'"
            @click="openNotification(item)"
          >
            <span
              class="h-2 w-2 flex-none rounded-full"
              :class="item.read_at ? 'bg-transparent border border-[color:rgb(var(--text-secondary-rgb)/0.45)]' : 'bg-[var(--text-primary)] shadow-[0_0_8px_rgb(var(--text-primary-rgb)/0.55)]'"
            ></span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-[var(--text-primary)]">{{ formatTitle(item) }}</span>
              <span class="mt-1 block text-xs text-[var(--text-secondary)]">{{ formatSubtitle(item) }}</span>
            </span>
            <span class="shrink-0 text-xs text-[var(--text-secondary)]">{{ formatTime(item) }}</span>
          </button>
        </div>

        <div v-if="isPaginating" class="px-2 py-4 text-xs text-[var(--text-muted)]" data-testid="notifications-page-paginating">
          Nacitavam dalsie...
        </div>

        <button
          v-if="page < lastPage"
          class="ui-pill ui-pill--secondary mt-5 w-full text-xs uppercase tracking-wide sm:mx-auto sm:block sm:w-auto"
          type="button"
          :disabled="isPaginating"
          @click="loadMore"
        >
          {{ isPaginating ? 'Nacitavam...' : 'Nacitat dalsie' }}
        </button>
      </div>
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
        Skusit znova
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
            class="inline-flex h-11 w-14 items-center rounded-full border border-[var(--border)] px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
            :class="preferences.good_conditions_alerts ? 'justify-end bg-[color:rgb(var(--primary-rgb)/0.32)]' : 'justify-start bg-[color:rgb(var(--text-primary-rgb)/0.05)]'"
            :disabled="isPreferenceToggleDisabled"
            :aria-pressed="preferences.good_conditions_alerts"
            @click="togglePreference('good_conditions_alerts')"
          >
            <span class="h-6 w-6 rounded-full bg-[var(--text-primary)]"></span>
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
            class="inline-flex h-11 w-14 items-center rounded-full border border-[var(--border)] px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
            :class="preferences.iss_alerts ? 'justify-end bg-[color:rgb(var(--primary-rgb)/0.32)]' : 'justify-start bg-[color:rgb(var(--text-primary-rgb)/0.05)]'"
            :disabled="isPreferenceToggleDisabled"
            :aria-pressed="preferences.iss_alerts"
            @click="togglePreference('iss_alerts')"
          >
            <span class="h-6 w-6 rounded-full bg-[var(--text-primary)]"></span>
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
import InlineStatus from '@/components/ui/InlineStatus.vue'
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
  }

  if (isOpen) {
    void fetchPreferences()
  } else if (route.hash === SETTINGS_HASH) {
    void router.replace({ path: route.path, query: route.query, hash: '' })
  }
})

onMounted(() => {
  store.fetchList(1)
  if (route.hash === SETTINGS_HASH) {
    void fetchPreferences()
  }
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
    const name = item.data?.actor_name || item.data?.actor_username || 'Niekto'
    return `${name} lajkol tvoj prispevok`
  }
  if (item.type === 'event_reminder') {
    return 'Pripomienka udalosti'
  }
  if (item.type === 'contest_winner') {
    return 'Vyhral si sutaz'
  }
  if (item.type === 'event_invite') {
    return 'Prisla ti pozvanka na udalost'
  }
  if (item.type === 'account_restricted') {
    return 'Ucet bol obmedzeny'
  }
  if (item.type === 'iss_pass_alert') {
    return 'ISS prelet uz coskoro'
  }
  if (item.type === 'good_conditions_alert') {
    return 'Vyborne podmienky na pozorovanie'
  }
  return 'Notifikacia'
}

const formatSubtitle = (item) => {
  if (item.type === 'post_liked') {
    const username = item.data?.actor_username ? `@${item.data.actor_username}` : ''
    return username || 'Aktivita v komunite'
  }
  if (item.type === 'event_reminder') {
    return item.data?.event_title || 'Udalost sa zacina uz coskoro'
  }
  if (item.type === 'contest_winner') {
    return item.data?.contest_name || 'Vitaz sutaze'
  }
  if (item.type === 'event_invite') {
    const inviter = item.data?.actor_name || item.data?.actor_username
    const title = item.data?.event_title
    if (inviter && title) return `${inviter} ta pozval na ${title}`
    if (inviter) return `${inviter} ta pozval na udalost`
    return title || 'Bol si pozvany na udalost'
  }
  if (item.type === 'account_restricted') {
    return item.data?.reason || 'Pre viac informacii kontaktuj podporu.'
  }
  if (item.type === 'iss_pass_alert') {
    return item.data?.next_pass_at ? `Dalsi prelet: ${formatClock(item.data.next_pass_at)}` : 'Prelet pride uz coskoro.'
  }
  if (item.type === 'good_conditions_alert') {
    const score = Number(item.data?.observing_score)
    return Number.isFinite(score) ? `Skore podmienok ${Math.round(score)}/100.` : 'Podmienky na oblohe vyzeraju dnes vyborne.'
  }
  return 'Nova aktivita'
}

const formatTime = (item) => {
  const createdHuman = String(item?.created_human || '').trim()
  if (createdHuman) return createdHuman

  const iso = item?.created_at
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
