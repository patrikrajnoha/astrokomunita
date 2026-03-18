<template>
  <section class="min-h-screen bg-[linear-gradient(180deg,rgb(var(--bg-app-rgb)/0.98)_0%,rgb(var(--bg-app-rgb)/0.95)_48%,rgb(var(--bg-surface-rgb)/0.94)_100%)] text-[var(--text-primary)]">
    <div class="mx-auto flex min-h-screen w-full max-w-5xl flex-col">
      <div class="px-5 pt-6 pb-2 sm:px-8">
        <PageHeader title="Notifikácie">
          <template #actions>
            <button
              v-if="items.length"
              type="button"
              class="rounded-full border border-[var(--color-border)] px-3 py-1.5 text-xs text-[var(--text-secondary)] transition hover:border-[var(--color-border-strong)] hover:text-[var(--text-primary)]"
              @click="markAll"
            >
              Označiť všetko
            </button>
            <button
              data-testid="open-notification-settings"
              class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--color-border)] text-[var(--text-secondary)] transition hover:border-[var(--color-border-strong)] hover:text-[var(--text-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--primary)]"
              type="button"
              @click="openSettingsModal"
            >
              <span class="sr-only">Otvoriť nastavenia notifikácií</span>
              <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="3.2"></circle>
                <path d="M19.4 14.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.56V20.5a2 2 0 0 1-4 0v-.08a1.7 1.7 0 0 0-1-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1H3.5a2 2 0 0 1 0-4h.08a1.7 1.7 0 0 0 1.56-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.01a1.7 1.7 0 0 0 1-1.56V3.5a2 2 0 0 1 4 0v.08a1.7 1.7 0 0 0 1 1.56h.01a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.01a1.7 1.7 0 0 0 1.56 1H20.5a2 2 0 0 1 0 4h-.08a1.7 1.7 0 0 0-1.56 1z"></path>
              </svg>
            </button>
          </template>
        </PageHeader>
      </div>

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
          action-label="Skúsiť znova"
          class="w-full max-w-lg"
          @action="retry"
        />
      </div>

      <div v-else-if="!items.length" class="flex flex-1 flex-col items-center px-5 py-16 text-center">
        <div class="mt-8 flex h-full min-h-[60vh] flex-col items-center pt-16" data-testid="notifications-page-empty">
          <svg viewBox="0 0 24 24" class="h-14 w-14 text-[color:rgb(var(--text-secondary-rgb)/0.6)]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6.5 8a5.5 5.5 0 1 1 11 0c0 2.6.7 4.4 1.8 5.8.5.6.1 1.2-.7 1.2H5.4c-.8 0-1.2-.7-.7-1.2C5.8 12.4 6.5 10.6 6.5 8Z"></path>
            <path d="M9.5 18a2.5 2.5 0 0 0 5 0"></path>
          </svg>
          <p class="mt-4 text-xl font-semibold tracking-tight text-[color:rgb(var(--text-secondary-rgb)/0.88)] sm:text-2xl">Zatiaľ žiadne notifikácie.</p>
          <p class="mt-2 text-sm text-[var(--text-secondary)]">Keď nastane aktivita, zobrazí sa tu.</p>
          <button
            type="button"
            class="mt-6 rounded-full border border-[var(--color-border)] px-4 py-2 text-sm text-[var(--text-secondary)] transition hover:border-[var(--color-border-strong)] hover:text-[var(--text-primary)]"
            @click="openSettingsModal"
          >
            Nastaviť upozornenia
          </button>
        </div>
      </div>

      <div v-else class="mx-auto w-full max-w-3xl flex-1 px-5 py-7 sm:px-8">
        <div class="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[color:rgb(var(--bg-surface-rgb)/0.4)]">
          <button
            v-for="item in items"
            :key="item.id"
            type="button"
            class="group flex w-full items-center gap-3 border-b border-[var(--divider-color)] px-5 py-3.5 text-left transition hover:bg-[color:rgb(var(--bg-surface-2-rgb)/0.58)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--primary)] last:border-b-0"
            :class="item.read_at ? 'opacity-75' : 'border-l-[3px] border-l-[color:rgb(var(--primary-rgb)/0.65)] bg-[color:rgb(var(--bg-surface-2-rgb)/0.38)]'"
            @click="openNotification(item)"
          >
            <span class="flex-none text-base leading-none" aria-hidden="true">{{ formatIcon(item) }}</span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-[var(--text-primary)]">{{ formatTitle(item) }}</span>
              <span class="mt-0.5 block text-xs text-[var(--text-secondary)]">{{ formatSubtitle(item) }}</span>
            </span>
            <span class="shrink-0 flex items-center gap-1.5 text-xs text-[var(--text-secondary)]">
              <span>{{ formatTime(item) }}</span>
              <span aria-hidden="true" class="opacity-30 transition-opacity group-hover:opacity-60">›</span>
            </span>
          </button>
        </div>

        <div v-if="isPaginating" class="px-2 py-4 text-xs text-[var(--text-muted)]" data-testid="notifications-page-paginating">
          Načítavam ďalšie...
        </div>

        <button
          v-if="page < lastPage"
          class="mt-4 w-full rounded-xl border border-[var(--color-border)] py-2.5 text-sm text-[var(--text-secondary)] transition hover:border-[var(--color-border-strong)] hover:text-[var(--text-primary)]"
          type="button"
          :disabled="isPaginating"
          @click="loadMore"
        >
          {{ isPaginating ? 'Načítavam...' : 'Načítať ďalšie' }}
        </button>
      </div>
    </div>
  </section>

  <BaseModal
    v-model:open="isSettingsModalOpen"
    title="Nastavenia notifikácií"
    test-id="notification-settings-modal"
    close-test-id="close-notification-settings"
    @close="handleModalClose"
  >
    <template #description>
      <p class="mt-2 text-sm text-[var(--text-secondary)]">Vyber si upozornenia pre pozorovanie oblohy.</p>

    </template>

    <div id="notification-settings" class="space-y-4">
      <section class="overflow-hidden rounded-2xl bg-[color:rgb(var(--bg-surface-rgb)/0.32)]">
        <div class="border-b border-[var(--divider-color)] px-4 py-3">
          <p class="text-sm font-semibold text-[var(--text-primary)]">Tipy na oblohu</p>
          <p class="mt-1 text-xs text-[var(--text-secondary)]">Rýchle upozornenia na lokálne podmienky a ISS prelety.</p>
        </div>

        <button
          v-if="preferencesError"
          type="button"
          class="mx-4 mt-4 ui-pill ui-pill--secondary text-xs uppercase tracking-wide"
          @click="retrySkyPreferences"
        >
          Skúsiť znova
        </button>

        <div v-if="preferencesLoading" class="space-y-3 px-4 py-4" aria-hidden="true">
          <div
            v-for="index in 2"
            :key="`preference-skeleton-${index}`"
            class="h-16 animate-pulse rounded-2xl bg-[color:rgb(var(--text-secondary-rgb)/0.15)]"
          ></div>
        </div>

        <div v-else>
          <p
            v-if="preferencesError"
            class="border-b border-[var(--divider-color)] px-4 py-3 text-sm text-[var(--text-primary)]"
            role="status"
          >
            Nastavenia sky alertov sú dočasne nedostupné. Skús znova.
          </p>

          <label
            class="flex items-center justify-between gap-4 border-b border-[var(--divider-color)] bg-[color:rgb(var(--bg-surface-2-rgb)/0.32)] px-4 py-4"
          >
            <div class="min-w-0">
              <p class="text-sm font-semibold text-[var(--text-primary)]">Upozorniť ma pri výborných podmienkach</p>
              <p class="mt-1 text-xs text-[var(--text-secondary)]">Dostaneš upozornenie, keď bude obloha vhodná na pozorovanie.</p>
            </div>
            <button
              type="button"
              class="inline-flex h-11 w-14 items-center rounded-full border border-[var(--border)] px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
              :class="preferences.good_conditions_alerts ? 'justify-end bg-[color:rgb(var(--primary-rgb)/0.32)]' : 'justify-start bg-[color:rgb(var(--text-primary-rgb)/0.05)]'"
              :disabled="isSkyPreferenceToggleDisabled"
              :aria-pressed="preferences.good_conditions_alerts"
              @click="toggleSkyPreference('good_conditions_alerts')"
            >
              <span class="h-6 w-6 rounded-full bg-[var(--text-primary)]"></span>
            </button>
          </label>

          <label
            class="flex items-center justify-between gap-4 bg-[color:rgb(var(--bg-surface-2-rgb)/0.32)] px-4 py-4"
          >
            <div class="min-w-0">
              <p class="text-sm font-semibold text-[var(--text-primary)]">Upozorniť ma na ISS prelet</p>
              <p class="mt-1 text-xs text-[var(--text-secondary)]">Dostaneš upozornenie pred ďalším dobre viditeľným preletom ISS.</p>
            </div>
            <button
              type="button"
              class="inline-flex h-11 w-14 items-center rounded-full border border-[var(--border)] px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
              :class="preferences.iss_alerts ? 'justify-end bg-[color:rgb(var(--primary-rgb)/0.32)]' : 'justify-start bg-[color:rgb(var(--text-primary-rgb)/0.05)]'"
              :disabled="isSkyPreferenceToggleDisabled"
              :aria-pressed="preferences.iss_alerts"
              @click="toggleSkyPreference('iss_alerts')"
            >
              <span class="h-6 w-6 rounded-full bg-[var(--text-primary)]"></span>
            </button>
          </label>
        </div>
      </section>

      <section class="overflow-hidden rounded-2xl bg-[color:rgb(var(--bg-surface-rgb)/0.32)]">
        <div class="border-b border-[var(--divider-color)] px-4 py-3">
          <p class="text-sm font-semibold text-[var(--text-primary)]">Pripomienky udalostí</p>
          <p class="mt-1 text-xs text-[var(--text-secondary)]">Vyber si, pre aké typy udalostí chceš app notifikácie a e-maily.</p>
        </div>

        <div class="border-b border-[var(--divider-color)] bg-[color:rgb(var(--bg-surface-2-rgb)/0.32)] px-4 py-4">
          <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-[var(--text-primary)]">Povoliť e-mailové upozornenia</p>
              <p class="mt-1 text-xs text-[var(--text-secondary)]">E-mail sa odošle len pre riadky, ktoré máš zapnuté v stĺpci Email.</p>
            </div>
            <button
              type="button"
              data-testid="delivery-email-enabled-toggle"
              class="inline-flex h-11 w-14 items-center rounded-full border border-[var(--border)] px-1 transition disabled:cursor-not-allowed disabled:opacity-50"
              :class="deliveryPreferences.email_enabled ? 'justify-end bg-[color:rgb(var(--primary-rgb)/0.32)]' : 'justify-start bg-[color:rgb(var(--text-primary-rgb)/0.05)]'"
              :disabled="deliveryPreferencesLoading"
              :aria-pressed="deliveryPreferences.email_enabled"
              @click="toggleDeliveryEmailEnabled"
            >
              <span class="h-6 w-6 rounded-full bg-[var(--text-primary)]"></span>
            </button>
          </div>
        </div>

        <button
          v-if="deliveryPreferencesError"
          type="button"
          class="mx-4 mt-4 ui-pill ui-pill--secondary text-xs uppercase tracking-wide"
          @click="retryDeliveryPreferences"
        >
          Skúsiť znova
        </button>

        <div v-if="deliveryPreferencesLoading" class="space-y-3 px-4 py-4" aria-hidden="true">
          <div
            v-for="index in 4"
            :key="`delivery-preference-skeleton-${index}`"
            class="h-20 animate-pulse rounded-2xl bg-[color:rgb(var(--text-secondary-rgb)/0.15)]"
          ></div>
        </div>

        <div v-else>
          <p
            v-if="deliveryPreferencesError"
            class="border-b border-[var(--divider-color)] px-4 py-3 text-sm text-[var(--text-primary)]"
            role="status"
          >
            Event reminder nastavenia sú dočasne nedostupné. Skús znova.
          </p>

          <div v-else class="divide-y divide-[var(--divider-color)]">
            <div
              v-for="row in eventReminderPreferenceRows"
              :key="row.key"
              class="flex items-center justify-between gap-4 bg-[color:rgb(var(--bg-surface-2-rgb)/0.32)] px-4 py-4"
            >
              <div class="min-w-0">
                <p class="text-sm font-semibold text-[var(--text-primary)]">{{ row.label }}</p>
                <p class="mt-1 text-xs text-[var(--text-secondary)]">{{ row.description }}</p>
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <button
                  type="button"
                  :data-testid="`delivery-in-app-${row.key}`"
                  class="inline-flex min-w-[58px] justify-center rounded-full border px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.12em] transition disabled:cursor-not-allowed disabled:opacity-50"
                  :class="deliveryPreferences.in_app[row.key] ? activePreferenceButtonClass : inactivePreferenceButtonClass"
                  :disabled="deliveryPreferencesLoading"
                  :aria-pressed="deliveryPreferences.in_app[row.key]"
                  @click="toggleDeliveryPreference('in_app', row.key)"
                >
                  App
                </button>
                <button
                  type="button"
                  :data-testid="`delivery-email-${row.key}`"
                  class="inline-flex min-w-[68px] justify-center rounded-full border px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.12em] transition disabled:cursor-not-allowed disabled:opacity-50"
                  :class="deliveryPreferences.email[row.key] ? activePreferenceButtonClass : inactivePreferenceButtonClass"
                  :disabled="deliveryPreferencesLoading"
                  :aria-pressed="deliveryPreferences.email[row.key]"
                  @click="toggleDeliveryPreference('email', row.key)"
                >
                  Email
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </BaseModal>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import {
  EVENT_REMINDER_PREFERENCE_ROWS,
  buildNotificationPreferenceMap,
  normalizeNotificationPreferenceMap,
} from '@/constants/notificationPreferences'
import { useNotificationsStore } from '@/stores/notifications'
import { useNotificationAlertPreferences } from '@/composables/useNotificationAlertPreferences'
import {
  getNotificationPreferences,
  updateNotificationPreferences,
} from '@/services/notificationPreferences'
import { useAuthStore } from '@/stores/auth'

const SETTINGS_HASH = '#notification-settings'

const store = useNotificationsStore()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const eventReminderPreferenceRows = EVENT_REMINDER_PREFERENCE_ROWS

const items = computed(() => store.items)
const loading = computed(() => store.loading)
const loadingMore = computed(() => store.loadingMore)
const error = computed(() => store.error)
const page = computed(() => store.page)
const lastPage = computed(() => store.lastPage)
const isSettingsModalOpen = ref(false)
const isInitialLoading = computed(() => loading.value && items.value.length === 0)
const isPaginating = computed(() => loadingMore.value || (loading.value && items.value.length > 0))
const activePreferenceButtonClass = 'border-[color:rgb(var(--primary-rgb)/0.42)] bg-[color:rgb(var(--primary-rgb)/0.2)] text-[var(--text-primary)]'
const inactivePreferenceButtonClass = 'border-[var(--border)] bg-[color:rgb(var(--text-primary-rgb)/0.04)] text-[var(--text-secondary)]'

const {
  preferences,
  preferencesLoading,
  preferencesError,
  fetchPreferences,
  updatePreferences,
} = useNotificationAlertPreferences({
  isAuthenticated: computed(() => auth.isAuthed),
})

const deliveryPreferences = ref({
  in_app: buildNotificationPreferenceMap(true),
  email_enabled: false,
  email: buildNotificationPreferenceMap(false),
})
const deliveryPreferencesLoading = ref(false)
const deliveryPreferencesError = ref('')

const isSkyPreferenceToggleDisabled = computed(() => preferencesLoading.value || preferencesError.value)

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
    void Promise.all([
      fetchPreferences(),
      fetchDeliveryPreferences(),
    ])
  } else if (route.hash === SETTINGS_HASH) {
    void router.replace({ path: route.path, query: route.query, hash: '' })
  }
})

onMounted(() => {
  store.fetchList(1)
  if (route.hash === SETTINGS_HASH) {
    void Promise.all([
      fetchPreferences(),
      fetchDeliveryPreferences(),
    ])
  }
})

const loadMore = () => store.fetchList(store.page + 1)
const markAll = () => store.markAllRead()
const retry = () => store.fetchList(1)
const retrySkyPreferences = () => fetchPreferences()
const retryDeliveryPreferences = () => fetchDeliveryPreferences()

function openSettingsModal() {
  isSettingsModalOpen.value = true
}

function handleModalClose() {
  isSettingsModalOpen.value = false
}

function applyDeliveryPreferences(payload = {}) {
  deliveryPreferences.value = {
    in_app: normalizeNotificationPreferenceMap(payload.in_app, true),
    email_enabled: Boolean(payload.email_enabled),
    email: normalizeNotificationPreferenceMap(payload.email, false),
  }
}

async function fetchDeliveryPreferences() {
  deliveryPreferencesLoading.value = true
  deliveryPreferencesError.value = ''

  try {
    const response = await getNotificationPreferences()
    applyDeliveryPreferences(response?.data || {})
  } catch (error) {
    deliveryPreferencesError.value =
      error?.response?.data?.message ||
      error?.message ||
      'Nepodarilo sa načítať event reminder nastavenia.'
  } finally {
    deliveryPreferencesLoading.value = false
  }
}

async function persistDeliveryPreferences(nextPreferences) {
  deliveryPreferencesLoading.value = true
  deliveryPreferencesError.value = ''

  try {
    const response = await updateNotificationPreferences(nextPreferences)
    applyDeliveryPreferences(response?.data || nextPreferences)
  } catch (error) {
    deliveryPreferencesError.value =
      error?.response?.data?.message ||
      error?.message ||
      'Nepodarilo sa uložiť event reminder nastavenia.'
  } finally {
    deliveryPreferencesLoading.value = false
  }
}

async function toggleSkyPreference(key) {
  if (isSkyPreferenceToggleDisabled.value) return

  await updatePreferences({
    iss_alerts: key === 'iss_alerts' ? !preferences.value.iss_alerts : preferences.value.iss_alerts,
    good_conditions_alerts: key === 'good_conditions_alerts'
      ? !preferences.value.good_conditions_alerts
      : preferences.value.good_conditions_alerts,
  })
}

async function toggleDeliveryEmailEnabled() {
  if (deliveryPreferencesLoading.value) return

  await persistDeliveryPreferences({
    in_app: { ...deliveryPreferences.value.in_app },
    email_enabled: !deliveryPreferences.value.email_enabled,
    email: { ...deliveryPreferences.value.email },
  })
}

async function toggleDeliveryPreference(scope, key) {
  if (deliveryPreferencesLoading.value) return

  const nextPreferences = {
    in_app: { ...deliveryPreferences.value.in_app },
    email_enabled: deliveryPreferences.value.email_enabled,
    email: { ...deliveryPreferences.value.email },
  }

  if (scope === 'email') {
    const nextValue = !deliveryPreferences.value.email[key]
    nextPreferences.email[key] = nextValue
    if (nextValue) {
      nextPreferences.email_enabled = true
    }
  } else {
    nextPreferences.in_app[key] = !deliveryPreferences.value.in_app[key]
  }

  await persistDeliveryPreferences(nextPreferences)
}

const openNotification = async (item) => {
  if (!item) return
  if (!item.read_at) await store.markRead(item.id)
  const target = item.target
  if (target?.url) {
    router.push(target.url)
  }
}

const formatIcon = (item) => {
  if (item.type === 'post_liked') return '❤️'
  if (item.type === 'event_reminder') return '🔔'
  if (item.type === 'contest_winner') return '🏆'
  if (item.type === 'event_invite') return '📅'
  if (item.type === 'account_restricted') return '⚠️'
  if (item.type === 'iss_pass_alert') return '🛰️'
  if (item.type === 'good_conditions_alert') return '✨'
  return '🔔'
}

const formatTitle = (item) => {
  if (item.type === 'post_liked') {
    const name = item.data?.actor_name || item.data?.actor_username || 'Niekto'
    return `${name} lajkol tvoj príspevok`
  }
  if (item.type === 'event_reminder') {
    return 'Pripomienka udalosti'
  }
  if (item.type === 'contest_winner') {
    return 'Vyhral si súťaž'
  }
  if (item.type === 'event_invite') {
    return 'Prišla ti pozvánka na udalosť'
  }
  if (item.type === 'account_restricted') {
    return 'Účet bol obmedzený'
  }
  if (item.type === 'iss_pass_alert') {
    return 'ISS prelet už čoskoro'
  }
  if (item.type === 'good_conditions_alert') {
    return 'Výborné podmienky na pozorovanie'
  }
  return 'Notifikácia'
}

const formatSubtitle = (item) => {
  if (item.type === 'post_liked') {
    const username = item.data?.actor_username ? `@${item.data.actor_username}` : ''
    return username || 'Aktivita v komunite'
  }
  if (item.type === 'event_reminder') {
    return item.data?.event_title || 'Udalosť sa začína už čoskoro'
  }
  if (item.type === 'contest_winner') {
    return item.data?.contest_name || 'Víťaz súťaže'
  }
  if (item.type === 'event_invite') {
    const inviter = item.data?.actor_name || item.data?.actor_username
    const title = item.data?.event_title
    if (inviter && title) return `${inviter} ťa pozval na ${title}`
    if (inviter) return `${inviter} ťa pozval na udalosť`
    return title || 'Bol si pozvaný na udalosť'
  }
  if (item.type === 'account_restricted') {
    return item.data?.reason || 'Pre viac informácií kontaktuj podporu.'
  }
  if (item.type === 'iss_pass_alert') {
    return item.data?.next_pass_at ? `Ďalší prelet: ${formatClock(item.data.next_pass_at)}` : 'Prelet príde už čoskoro.'
  }
  if (item.type === 'good_conditions_alert') {
    const score = Number(item.data?.observing_score)
    return Number.isFinite(score) ? `Skóre podmienok ${Math.round(score)}/100.` : 'Podmienky na oblohe vyzerajú dnes výborne.'
  }
  return 'Nová aktivita'
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
