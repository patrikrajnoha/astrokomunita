<template>
  <div>
  <section class="min-h-screen bg-app text-white">
    <div class="mx-auto flex min-h-screen w-full max-w-5xl flex-col">
      <div class="px-5 pt-6 pb-2 sm:px-8">
        <PageHeader title="Notifikácie">
          <template #actions>
            <button
              v-if="items.length"
              type="button"
              class="rounded-full border border-white/[0.08] px-3 py-1.5 text-xs text-muted transition hover:border-white/[0.16] hover:text-white"
              @click="markAll"
            >
              Označiť všetko
            </button>
            <button
              data-testid="open-notification-settings"
              class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/[0.08] text-muted transition hover:border-white/[0.16] hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0F73FF]"
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

      <Transition name="notificationsState" mode="out-in">
      <div v-if="isInitialLoading" key="notifications-loading" class="mx-auto w-full max-w-3xl space-y-3 px-5 py-7 sm:px-8" data-testid="notifications-page-loading">
        <div
          v-for="index in 5"
          :key="`notification-list-skeleton-${index}`"
          class="h-16 animate-pulse rounded-2xl bg-[rgba(28,39,54,0.5)]"
        ></div>
      </div>

      <div v-else-if="error" key="notifications-error" class="flex flex-1 flex-col items-center justify-center px-5 py-12 text-center" data-testid="notifications-page-error">
        <InlineStatus
          variant="error"
          :message="error || 'Nastala chyba pri načítaní notifikácií.'"
          action-label="Skúsiť znova"
          class="w-full max-w-lg"
          @action="retry"
        />
      </div>

      <div v-else-if="!items.length" key="notifications-empty" class="flex flex-1 flex-col items-center px-5 py-16 text-center">
        <div class="mt-8 flex h-full min-h-[60vh] flex-col items-center pt-16" data-testid="notifications-page-empty">
          <svg viewBox="0 0 24 24" class="h-14 w-14 text-[rgba(171,184,201,0.6)]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6.5 8a5.5 5.5 0 1 1 11 0c0 2.6.7 4.4 1.8 5.8.5.6.1 1.2-.7 1.2H5.4c-.8 0-1.2-.7-.7-1.2C5.8 12.4 6.5 10.6 6.5 8Z"></path>
            <path d="M9.5 18a2.5 2.5 0 0 0 5 0"></path>
          </svg>
          <p class="mt-4 text-xl font-semibold tracking-tight text-[rgba(171,184,201,0.88)] sm:text-2xl">Zatiaľ žiadne notifikácie.</p>
          <p class="mt-2 text-sm text-muted">Keď nastane aktivita, zobrazí sa tu.</p>
          <button
            type="button"
            class="mt-6 rounded-full border border-white/[0.08] px-4 py-2 text-sm text-muted transition hover:border-white/[0.16] hover:text-white"
            @click="openSettingsModal"
          >
            Nastaviť upozornenia
          </button>
        </div>
      </div>

      <div v-else key="notifications-list" class="mx-auto w-full max-w-3xl flex-1 px-5 py-7 sm:px-8">
        <TransitionGroup
          name="notificationItem"
          tag="div"
          class="overflow-hidden rounded-2xl border border-white/[0.08] bg-[rgba(28,39,54,0.4)]"
        >
          <button
            v-for="item in items"
            :key="item.id"
            type="button"
            class="group flex w-full items-center gap-3 border-b border-[rgba(255,255,255,0.07)] px-5 py-3.5 text-left transition hover:bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0F73FF] last:border-b-0"
            :class="item.read_at ? 'opacity-75' : 'border-l-[3px] border-l-[rgba(15,115,255,0.65)] bg-[rgba(28,39,54,0.38)]'"
            @click="openNotification(item)"
          >
            <span class="flex-none text-base leading-none" aria-hidden="true">{{ formatIcon(item) }}</span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-white">{{ formatTitle(item) }}</span>
              <span class="mt-0.5 block text-xs text-muted">{{ formatSubtitle(item) }}</span>
            </span>
            <span class="shrink-0 flex items-center gap-1.5 text-xs text-muted">
              <span>{{ formatTime(item) }}</span>
              <span aria-hidden="true" class="opacity-30 transition-opacity group-hover:opacity-60">›</span>
            </span>
          </button>
        </TransitionGroup>

        <div v-if="isPaginating" class="px-2 py-4 text-xs text-muted" data-testid="notifications-page-paginating">
          Načítavam ďalšie...
        </div>

        <button
          v-if="page < lastPage"
          class="mt-4 w-full rounded-xl border border-white/[0.08] py-2.5 text-sm text-muted transition hover:border-white/[0.16] hover:text-white"
          type="button"
          :disabled="isPaginating"
          @click="loadMore"
        >
          {{ isPaginating ? 'Načítavam...' : 'Načítať ďalšie' }}
        </button>
      </div>
      </Transition>
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
      <p class="mt-2 text-sm text-muted">Vyber si upozornenia pre pozorovanie oblohy.</p>

    </template>

    <div id="notification-settings" class="ns-wrap">
      <section class="ns-section">
        <div class="ns-section-header">
          <p class="ns-section-title">Tipy na oblohu</p>
          <p class="ns-section-desc">Rýchle upozornenia na lokálne podmienky a ISS prelety.</p>
        </div>

        <div v-if="preferencesLoading" class="ns-skeletons" aria-hidden="true">
          <div v-for="index in 2" :key="`preference-skeleton-${index}`" class="ns-skeleton"></div>
        </div>

        <div v-else>
          <p v-if="preferencesError" class="ns-error-text" role="status">
            Nastavenia upozornení na oblohu sú dočasne nedostupné.
          </p>
          <button v-if="preferencesError" type="button" class="ns-retry-btn" @click="retrySkyPreferences">
            Skúsiť znova
          </button>

          <label class="ns-row ns-row--border">
            <div class="ns-row-text">
              <p class="ns-row-title">Výborné podmienky na pozorovanie</p>
              <p class="ns-row-desc">Dostaneš upozornenie, keď bude obloha vhodná na pozorovanie.</p>
            </div>
            <button
              type="button"
              class="ns-toggle"
              :class="preferences.good_conditions_alerts ? 'ns-toggle--on' : 'ns-toggle--off'"
              :disabled="isSkyPreferenceToggleDisabled"
              :aria-pressed="preferences.good_conditions_alerts"
              @click="toggleSkyPreference('good_conditions_alerts')"
            >
              <span class="ns-toggle-knob"></span>
            </button>
          </label>

          <label class="ns-row">
            <div class="ns-row-text">
              <p class="ns-row-title">ISS prelet</p>
              <p class="ns-row-desc">Dostaneš upozornenie pred ďalším dobre viditeľným preletom ISS.</p>
            </div>
            <button
              type="button"
              class="ns-toggle"
              :class="preferences.iss_alerts ? 'ns-toggle--on' : 'ns-toggle--off'"
              :disabled="isSkyPreferenceToggleDisabled"
              :aria-pressed="preferences.iss_alerts"
              @click="toggleSkyPreference('iss_alerts')"
            >
              <span class="ns-toggle-knob"></span>
            </button>
          </label>
        </div>
      </section>

      <section class="ns-section">
        <div class="ns-section-header">
          <p class="ns-section-title">Pripomienky udalostí</p>
          <p class="ns-section-desc">Vyber si, pre aké typy udalostí chceš appku notifikácie a e-maily.</p>
        </div>

        <div class="ns-row ns-row--border">
          <div class="ns-row-text">
            <p class="ns-row-title">E-mailové upozornenia</p>
            <p class="ns-row-desc">E-mail sa odošle len pre riadky, ktoré máš zapnuté v stĺpci Email.</p>
          </div>
          <button
            type="button"
            data-testid="delivery-email-enabled-toggle"
            class="ns-toggle"
            :class="deliveryPreferences.email_enabled ? 'ns-toggle--on' : 'ns-toggle--off'"
            :disabled="deliveryPreferencesLoading"
            :aria-pressed="deliveryPreferences.email_enabled"
            @click="toggleDeliveryEmailEnabled"
          >
            <span class="ns-toggle-knob"></span>
          </button>
        </div>

        <div v-if="deliveryPreferencesLoading" class="ns-skeletons" aria-hidden="true">
          <div v-for="index in 4" :key="`delivery-preference-skeleton-${index}`" class="ns-skeleton ns-skeleton--tall"></div>
        </div>

        <div v-else>
          <p v-if="deliveryPreferencesError" class="ns-error-text" role="status">
            Nastavenia pripomienok udalostí sú dočasne nedostupné.
          </p>
          <button v-if="deliveryPreferencesError" type="button" class="ns-retry-btn" @click="retryDeliveryPreferences">
            Skúsiť znova
          </button>

          <div v-else class="ns-rows">
            <div
              v-for="row in eventReminderPreferenceRows"
              :key="row.key"
              class="ns-row"
            >
              <div class="ns-row-text">
                <p class="ns-row-title">{{ row.label }}</p>
                <p class="ns-row-desc">{{ row.description }}</p>
              </div>

              <div class="ns-pill-group">
                <button
                  type="button"
                  :data-testid="`delivery-in-app-${row.key}`"
                  class="ns-pill"
                  :class="deliveryPreferences.in_app[row.key] ? 'ns-pill--active' : 'ns-pill--inactive'"
                  :disabled="deliveryPreferencesLoading"
                  :aria-pressed="deliveryPreferences.in_app[row.key]"
                  @click="toggleDeliveryPreference('in_app', row.key)"
                >
                  App
                </button>
                <button
                  type="button"
                  :data-testid="`delivery-email-${row.key}`"
                  class="ns-pill"
                  :class="deliveryPreferences.email[row.key] ? 'ns-pill--active' : 'ns-pill--inactive'"
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
  </div>
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
      'Nepodarilo sa načítať nastavenia pripomienok.'
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
      'Nepodarilo sa uložiť nastavenia pripomienok.'
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

<style scoped>
/* ── Notification settings ── */
.ns-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ns-section {
  overflow: hidden;
  border-radius: 20px;
  background: #151d28;
}

.ns-section-header {
  padding: 14px 16px;
  border-bottom: 1px solid rgb(255 255 255 / 0.07);
}

.ns-section-title {
  margin: 0;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.3;
}

.ns-section-desc {
  margin: 3px 0 0;
  color: #abb8c9;
  font-size: 0.75rem;
  line-height: 1.45;
}

.ns-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  background: #151d28;
}

.ns-row--border {
  border-bottom: 1px solid rgb(255 255 255 / 0.07);
}

.ns-rows {
  display: flex;
  flex-direction: column;
}

.ns-rows .ns-row {
  border-bottom: 1px solid rgb(255 255 255 / 0.07);
}

.ns-rows .ns-row:last-child {
  border-bottom: none;
}

.ns-row-text {
  min-width: 0;
}

.ns-row-title {
  margin: 0;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.3;
}

.ns-row-desc {
  margin: 3px 0 0;
  color: #abb8c9;
  font-size: 0.75rem;
  line-height: 1.45;
}

/* Toggle switch */
.ns-toggle {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  width: 52px;
  height: 30px;
  border: none;
  border-radius: 999px;
  padding: 3px;
  cursor: pointer;
  transition: background-color 180ms ease;
}

.ns-toggle:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.ns-toggle--on {
  justify-content: flex-end;
  background: #0f73ff;
}

.ns-toggle--off {
  justify-content: flex-start;
  background: #222e3f;
}

.ns-toggle-knob {
  display: block;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #fff;
}

/* App / Email pill buttons */
.ns-pill-group {
  display: flex;
  flex-shrink: 0;
  gap: 6px;
}

.ns-pill {
  display: inline-flex;
  justify-content: center;
  min-width: 54px;
  border: none;
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease, opacity 150ms ease;
}

.ns-pill:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.ns-pill--active {
  background: #0f73ff;
  color: #fff;
}

.ns-pill--inactive {
  background: #222e3f;
  color: #abb8c9;
}

/* Retry button */
.ns-retry-btn {
  display: block;
  margin: 12px 16px;
  padding: 8px 18px;
  border: none;
  border-radius: 999px;
  background: #222e3f;
  color: #abb8c9;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 140ms ease, color 140ms ease;
}

.ns-retry-btn:hover {
  background: #1c2736;
  color: #fff;
}

/* Skeletons */
.ns-skeletons {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
}

.ns-skeleton {
  height: 52px;
  border-radius: 14px;
  background: rgb(171 184 201 / 0.1);
  animation: nsPulse 1.4s ease-in-out infinite;
}

.ns-skeleton--tall {
  height: 68px;
}

.ns-error-text {
  margin: 0;
  padding: 12px 16px;
  color: #fff;
  font-size: 0.875rem;
  border-bottom: 1px solid rgb(255 255 255 / 0.07);
}

@keyframes nsPulse {
  0%, 100% { opacity: 0.5; }
  50%       { opacity: 1; }
}

@media (max-width: 480px) {
  .ns-row {
    gap: 10px;
    padding: 12px 14px;
  }

  .ns-pill {
    min-width: 46px;
    padding: 6px 10px;
    font-size: 0.65rem;
  }

  .ns-toggle {
    width: 46px;
    height: 27px;
  }

  .ns-toggle-knob {
    width: 21px;
    height: 21px;
  }
}

/* ── Animations ── */
.notificationsState-enter-active,
.notificationsState-leave-active {
  transition: opacity var(--motion-base), transform var(--motion-base);
}

.notificationsState-enter-from,
.notificationsState-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.notificationItem-enter-active,
.notificationItem-leave-active {
  transition: opacity var(--motion-fast), transform var(--motion-fast);
}

.notificationItem-enter-from,
.notificationItem-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

.notificationItem-move {
  transition: transform var(--motion-base);
}
</style>
