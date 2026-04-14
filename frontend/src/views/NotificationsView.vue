<template>
  <section class="min-h-screen bg-[#151d28] text-white">
    <div class="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-4 pb-8 pt-5 sm:px-6 lg:px-8">
      <div class="mb-5">
        <PageHeader title="Notifikácie">
          <template #actions>
            <div class="flex flex-wrap items-center justify-end gap-3">
              <button
                v-if="items.length"
                type="button"
                class="btn btn--secondary"
                :disabled="deletingAll || markAllReading"
                @click="markAll"
              >
                Označiť všetko
              </button>

              <button
                v-if="items.length"
                type="button"
                data-testid="delete-all-notifications"
                class="btn btn--danger"
                :disabled="deletingAll || markAllReading"
                @click="deleteAll"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M4 7h16" />
                  <path d="M10 11v6" />
                  <path d="M14 11v6" />
                  <path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
                  <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                </svg>
                <span>{{ deletingAll ? 'Mažem...' : 'Vymazať všetko' }}</span>
              </button>

              <button
                data-testid="open-notification-settings"
                class="icon-btn"
                type="button"
                @click="openSettingsModal"
              >
                <span class="sr-only">Otvoriť nastavenia notifikácií</span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="3.2" />
                  <path d="M19.4 14.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.56V20.5a2 2 0 0 1-4 0v-.08a1.7 1.7 0 0 0-1-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1H3.5a2 2 0 0 1 0-4h.08a1.7 1.7 0 0 0 1.56-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.01a1.7 1.7 0 0 0 1-1.56V3.5a2 2 0 0 1 4 0v.08a1.7 1.7 0 0 0 1 1.56h.01a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.01a1.7 1.7 0 0 0 1.56 1H20.5a2 2 0 0 1 0 4h-.08a1.7 1.7 0 0 0-1.56 1z" />
                </svg>
              </button>
            </div>
          </template>
        </PageHeader>
      </div>

      <Transition name="notificationsState" mode="out-in">
        <div
          v-if="isInitialLoading"
          key="notifications-loading"
          class="mx-auto w-full max-w-3xl space-y-3"
          data-testid="notifications-page-loading"
        >
          <div
            v-for="index in 5"
            :key="`notification-skeleton-${index}`"
            class="h-[84px] animate-pulse rounded-[28px] bg-[#1c2736]"
          ></div>
        </div>

        <div
          v-else-if="error"
          key="notifications-error"
          class="flex flex-1 items-center justify-center py-10"
          data-testid="notifications-page-error"
        >
          <InlineStatus
            variant="error"
            :message="error || 'Nastala chyba pri načítaní notifikácií.'"
            action-label="Skúsiť znova"
            class="w-full max-w-lg"
            @action="retry"
          />
        </div>

        <div
          v-else-if="!items.length"
          key="notifications-empty"
          class="flex flex-1 items-center justify-center py-12"
          data-testid="notifications-page-empty"
        >
          <div class="flex w-full max-w-md flex-col items-center gap-3 text-center">
            <svg
              viewBox="0 0 24 24"
              class="h-14 w-14 text-[#ABB8C9]"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M6.5 8a5.5 5.5 0 1 1 11 0c0 2.6.7 4.4 1.8 5.8.5.6.1 1.2-.7 1.2H5.4c-.8 0-1.2-.7-.7-1.2C5.8 12.4 6.5 10.6 6.5 8Z" />
              <path d="M9.5 18a2.5 2.5 0 0 0 5 0" />
            </svg>
            <p class="text-[1.45rem] font-semibold tracking-[-0.02em] text-white">Zatiaľ žiadne notifikácie.</p>
            <p class="max-w-sm text-sm leading-6 text-[#ABB8C9]">Keď nastane aktivita, zobrazí sa tu.</p>
            <button type="button" class="btn btn--secondary" @click="openSettingsModal">
              Nastaviť upozornenia
            </button>
          </div>
        </div>

        <div v-else key="notifications-list" class="mx-auto w-full max-w-3xl flex-1">
          <TransitionGroup name="notificationItem" tag="div" class="notification-list">
            <div
              v-for="item in items"
              :key="item.id"
              class="notification-row"
              :class="{ 'notification-row--deleting': isDeleting(item.id) }"
              :data-testid="`notification-row-${item.id}`"
            >
              <button
                type="button"
                class="notification-delete-swipe"
                :data-testid="`swipe-delete-notification-${item.id}`"
                :disabled="isDeleting(item.id) || deletingAll"
                @click="deleteOne(item, { confirm: false, closeSwipe: true })"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M4 7h16" />
                  <path d="M10 11v6" />
                  <path d="M14 11v6" />
                  <path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
                  <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                </svg>
                <span>Vymazať</span>
              </button>

              <button
                type="button"
                class="notification-surface"
                :class="{ 'notification-surface--unread': !item.read_at }"
                :data-testid="`notification-surface-${item.id}`"
                :disabled="isDeleting(item.id) || deletingAll"
                :style="notificationSurfaceStyle(item.id)"
                @pointerdown="onRowPointerDown(item, $event)"
                @pointermove="onRowPointerMove(item, $event)"
                @pointerup="onRowPointerEnd(item, $event)"
                @pointercancel="onRowPointerCancel(item, $event)"
                @click="openNotification(item)"
              >
                <span class="notification-surface__tone" :style="{ backgroundColor: notificationTone(item) }"></span>

                <span class="min-w-0 flex-1">
                  <span class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <span class="min-w-0 inline-flex items-center gap-2">
                      <span class="truncate text-[0.96rem] font-semibold leading-6 text-white">
                        {{ formatTitle(item) }}
                      </span>
                      <span v-if="!item.read_at" class="h-2 w-2 shrink-0 rounded-full bg-[#0F73FF]"></span>
                    </span>
                    <span class="shrink-0 text-xs font-medium text-[#ABB8C9]">{{ formatTime(item) }}</span>
                  </span>
                  <span class="mt-1 block text-sm leading-6 text-[#ABB8C9]">
                    {{ formatSubtitle(item) }}
                  </span>
                </span>
              </button>

              <button
                type="button"
                :data-testid="`delete-notification-${item.id}`"
                class="notification-quick-delete"
                :disabled="isDeleting(item.id) || deletingAll"
                @click.stop="deleteOne(item, { confirm: false, closeSwipe: true })"
              >
                <span class="sr-only">Vymazať notifikáciu</span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M4 7h16" />
                  <path d="M10 11v6" />
                  <path d="M14 11v6" />
                  <path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
                  <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                </svg>
              </button>
            </div>
          </TransitionGroup>

          <div
            v-if="isPaginating"
            class="px-1 pt-4 text-xs font-medium text-[#ABB8C9]"
            data-testid="notifications-page-paginating"
          >
            Načítavam ďalšie...
          </div>

          <button
            v-if="page < lastPage"
            type="button"
            class="btn btn--secondary mt-4 w-full"
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
      <p class="mt-2 text-sm leading-6 text-[#ABB8C9]">Vyber si upozornenia pre pozorovanie oblohy.</p>
    </template>

    <div id="notification-settings" class="settings-stack">
      <section class="settings-card">
        <div class="settings-head">
          <p class="settings-title">Tipy na oblohu</p>
          <p class="settings-desc">Rýchle upozornenia na lokálne podmienky a ISS prelety.</p>
        </div>

        <div v-if="preferencesLoading" class="space-y-3 p-4" aria-hidden="true">
          <div v-for="index in 2" :key="`preference-skeleton-${index}`" class="settings-skeleton"></div>
        </div>

        <div v-else>
          <p v-if="preferencesError" class="px-4 pt-4 text-sm text-white" role="status">
            Nastavenia upozornení na oblohu sú dočasne nedostupné.
          </p>
          <button v-if="preferencesError" type="button" class="btn btn--secondary m-4" @click="retrySkyPreferences">
            Skúsiť znova
          </button>

          <label class="settings-row settings-row--border">
            <div class="min-w-0">
              <p class="settings-row__title">Výborné podmienky na pozorovanie</p>
              <p class="settings-row__desc">Dostaneš upozornenie, keď bude obloha vhodná na pozorovanie.</p>
            </div>
            <button
              type="button"
              class="toggle"
              :class="preferences.good_conditions_alerts ? 'toggle--on' : 'toggle--off'"
              :disabled="isSkyPreferenceToggleDisabled"
              :aria-pressed="preferences.good_conditions_alerts"
              @click="toggleSkyPreference('good_conditions_alerts')"
            >
              <span class="toggle__knob"></span>
            </button>
          </label>

          <label class="settings-row">
            <div class="min-w-0">
              <p class="settings-row__title">ISS prelet</p>
              <p class="settings-row__desc">Dostaneš upozornenie pred ďalším dobre viditeľným preletom ISS.</p>
            </div>
            <button
              type="button"
              class="toggle"
              :class="preferences.iss_alerts ? 'toggle--on' : 'toggle--off'"
              :disabled="isSkyPreferenceToggleDisabled"
              :aria-pressed="preferences.iss_alerts"
              @click="toggleSkyPreference('iss_alerts')"
            >
              <span class="toggle__knob"></span>
            </button>
          </label>
        </div>
      </section>

      <section class="settings-card">
        <div class="settings-head">
          <p class="settings-title">Pripomienky udalostí</p>
          <p class="settings-desc">Vyber si, pre aké typy udalostí chceš appku notifikácie a e-maily.</p>
        </div>

        <div class="settings-row settings-row--border">
          <div class="min-w-0">
            <p class="settings-row__title">E-mailové upozornenia</p>
            <p class="settings-row__desc">E-mail sa odošle len pre riadky, ktoré máš zapnuté v stĺpci Email.</p>
          </div>
          <button
            type="button"
            data-testid="delivery-email-enabled-toggle"
            class="toggle"
            :class="deliveryPreferences.email_enabled ? 'toggle--on' : 'toggle--off'"
            :disabled="deliveryPreferencesLoading"
            :aria-pressed="deliveryPreferences.email_enabled"
            @click="toggleDeliveryEmailEnabled"
          >
            <span class="toggle__knob"></span>
          </button>
        </div>

        <div v-if="deliveryPreferencesLoading" class="space-y-3 p-4" aria-hidden="true">
          <div v-for="index in 4" :key="`delivery-preference-skeleton-${index}`" class="settings-skeleton h-[72px]"></div>
        </div>

        <div v-else>
          <p v-if="deliveryPreferencesError" class="px-4 pt-4 text-sm text-white" role="status">
            Nastavenia pripomienok udalostí sú dočasne nedostupné.
          </p>
          <button
            v-if="deliveryPreferencesError"
            type="button"
            class="btn btn--secondary m-4"
            @click="retryDeliveryPreferences"
          >
            Skúsiť znova
          </button>

          <div v-else>
            <div
              v-for="row in eventReminderPreferenceRows"
              :key="row.key"
              class="settings-row settings-row--border"
            >
              <div class="min-w-0">
                <p class="settings-row__title">{{ row.label }}</p>
                <p class="settings-row__desc">{{ row.description }}</p>
              </div>

              <div class="flex shrink-0 gap-2">
                <button
                  type="button"
                  :data-testid="`delivery-in-app-${row.key}`"
                  class="pill"
                  :class="deliveryPreferences.in_app[row.key] ? 'pill--active' : 'pill--inactive'"
                  :disabled="deliveryPreferencesLoading"
                  :aria-pressed="deliveryPreferences.in_app[row.key]"
                  @click="toggleDeliveryPreference('in_app', row.key)"
                >
                  App
                </button>
                <button
                  type="button"
                  :data-testid="`delivery-email-${row.key}`"
                  class="pill"
                  :class="deliveryPreferences.email[row.key] ? 'pill--active' : 'pill--inactive'"
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
import { useConfirm } from '@/composables/useConfirm'
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
const SWIPE_ACTION_WIDTH = 96
const SWIPE_OPEN_THRESHOLD = 48
const SWIPE_DELETE_THRESHOLD = 124
const SWIPE_LOCK_THRESHOLD = 10
const SWIPE_MAX_OFFSET = 148
const CLICK_SUPPRESS_MS = 260

const store = useNotificationsStore()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { confirm } = useConfirm()
const eventReminderPreferenceRows = EVENT_REMINDER_PREFERENCE_ROWS

const items = computed(() => store.items)
const loading = computed(() => store.loading)
const loadingMore = computed(() => store.loadingMore)
const error = computed(() => store.error)
const page = computed(() => store.page)
const lastPage = computed(() => store.lastPage)
const markAllReading = computed(() => store.markAllReading)
const deletingAll = computed(() => store.deletingAll)
const isSettingsModalOpen = ref(false)
const isInitialLoading = computed(() => loading.value && items.value.length === 0)
const isPaginating = computed(() => loadingMore.value || (loading.value && items.value.length > 0))

const swipeActiveId = ref(null)
const swipeOpenId = ref(null)
const swipeOffset = ref(0)

let swipePointerId = null
let swipeStartX = 0
let swipeStartY = 0
let swipeStartOffset = 0
let swipeLocked = false
let swipeSuppressedUntil = 0

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
const isDeleting = (id) => store.isDeleting(id)
const loadMore = () => store.fetchList(page.value + 1)
const markAll = () => store.markAllRead()
const retry = () => store.fetchList(1)
const retrySkyPreferences = () => fetchPreferences()
const retryDeliveryPreferences = () => fetchDeliveryPreferences()

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
    void Promise.all([fetchPreferences(), fetchDeliveryPreferences()])
  } else if (route.hash === SETTINGS_HASH) {
    void router.replace({ path: route.path, query: route.query, hash: '' })
  }
})

onMounted(() => {
  store.fetchList(1)
  if (route.hash === SETTINGS_HASH) {
    void Promise.all([fetchPreferences(), fetchDeliveryPreferences()])
  }
})

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
  } catch (requestError) {
    deliveryPreferencesError.value =
      requestError?.response?.data?.message ||
      requestError?.message ||
      'Nepodarilo sa nacitat nastavenia pripomienok.'
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
  } catch (requestError) {
    deliveryPreferencesError.value =
      requestError?.response?.data?.message ||
      requestError?.message ||
      'Nepodarilo sa ulozit nastavenia pripomienok.'
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

function clearSwipeTracking() {
  swipeActiveId.value = null
  swipeOffset.value = 0
  swipePointerId = null
  swipeStartX = 0
  swipeStartY = 0
  swipeStartOffset = 0
  swipeLocked = false
}

function closeSwipe(id = null) {
  if (id === null || swipeOpenId.value === id) {
    swipeOpenId.value = null
  }
  clearSwipeTracking()
}

function notificationSurfaceStyle(id) {
  const offset = swipeActiveId.value === id
    ? swipeOffset.value
    : swipeOpenId.value === id
      ? -SWIPE_ACTION_WIDTH
      : 0

  return {
    transform: `translateX(${offset}px)`,
    transition: swipeActiveId.value === id ? 'none' : 'transform 180ms ease, background-color 160ms ease, opacity 160ms ease',
  }
}

function notificationTone(item) {
  if (item.type === 'post_liked') return '#EF75EA'
  if (item.type === 'event_reminder') return '#FE8311'
  if (item.type === 'contest_winner') return '#FED811'
  if (item.type === 'event_invite') return '#1185FE'
  if (item.type === 'account_restricted') return '#F55454'
  if (item.type === 'iss_pass_alert') return '#0F73FF'
  if (item.type === 'good_conditions_alert') return '#73DF84'
  return '#ABB8C9'
}

function onRowPointerDown(item, event) {
  if (!item?.id || deletingAll.value || isDeleting(item.id)) return
  if (event.pointerType === 'mouse' && event.button !== 0) return

  if (swipeOpenId.value && swipeOpenId.value !== item.id) {
    swipeOpenId.value = null
  }

  swipeActiveId.value = item.id
  swipePointerId = event.pointerId ?? null
  swipeStartX = event.clientX
  swipeStartY = event.clientY
  swipeStartOffset = swipeOpenId.value === item.id ? -SWIPE_ACTION_WIDTH : 0
  swipeOffset.value = swipeStartOffset
  swipeLocked = false
  event.currentTarget?.setPointerCapture?.(swipePointerId)
}

function onRowPointerMove(item, event) {
  if (!item?.id || swipeActiveId.value !== item.id) return
  if (swipePointerId !== null && event.pointerId !== swipePointerId) return

  const deltaX = event.clientX - swipeStartX
  const deltaY = event.clientY - swipeStartY

  if (!swipeLocked) {
    if (Math.abs(deltaX) < SWIPE_LOCK_THRESHOLD && Math.abs(deltaY) < SWIPE_LOCK_THRESHOLD) {
      return
    }

    if (Math.abs(deltaY) > Math.abs(deltaX)) {
      closeSwipe()
      return
    }

    swipeLocked = true
  }

  swipeOffset.value = Math.min(0, Math.max(-SWIPE_MAX_OFFSET, swipeStartOffset + deltaX))
  event.preventDefault?.()
}

async function onRowPointerEnd(item, event) {
  if (!item?.id || swipeActiveId.value !== item.id) return
  if (swipePointerId !== null && event.pointerId !== swipePointerId) return

  event.currentTarget?.releasePointerCapture?.(swipePointerId)

  const finalOffset = swipeOffset.value
  const shouldDelete = finalOffset <= -SWIPE_DELETE_THRESHOLD
  const shouldOpen = finalOffset <= -SWIPE_OPEN_THRESHOLD

  clearSwipeTracking()

  if (shouldDelete) {
    swipeSuppressedUntil = Date.now() + CLICK_SUPPRESS_MS
    await deleteOne(item, { confirm: false, closeSwipe: true })
    return
  }

  if (shouldOpen) {
    swipeOpenId.value = item.id
    swipeSuppressedUntil = Date.now() + CLICK_SUPPRESS_MS
    return
  }

  swipeOpenId.value = null
}

function onRowPointerCancel(item, event) {
  if (!item?.id || swipeActiveId.value !== item.id) return
  if (swipePointerId !== null && event.pointerId !== swipePointerId) return
  event.currentTarget?.releasePointerCapture?.(swipePointerId)
  clearSwipeTracking()
}

async function openNotification(item) {
  if (!item) return
  if (Date.now() < swipeSuppressedUntil) return

  if (swipeOpenId.value) {
    closeSwipe()
    return
  }

  if (!item.read_at) {
    await store.markRead(item.id)
  }

  const target = item.target
  if (target?.url) {
    void router.push(target.url)
  }
}

async function deleteOne(item, options = {}) {
  if (!item?.id || isDeleting(item.id) || deletingAll.value) return

  if (options.closeSwipe) {
    closeSwipe()
  }

  if (options.confirm === true) {
    const approved = await confirm({
      title: 'Vymazať notifikáciu?',
      message: 'Táto notifikácia sa odstráni z tvojho zoznamu.',
      confirmText: 'Vymazať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    })

    if (!approved) return
  }

  await store.deleteNotification(item.id)
}

async function deleteAll() {
  if (!items.value.length || deletingAll.value || markAllReading.value) return

  const approved = await confirm({
    title: 'Vymazať všetky notifikácie?',
    message: 'Táto akcia odstráni všetky notifikácie z tvojho účtu.',
    confirmText: 'Vymazať všetko',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!approved) return
  closeSwipe()
  await store.deleteAllNotifications()
}

function formatTitle(item) {
  if (item.type === 'post_liked') {
    const name = item.data?.actor_name || item.data?.actor_username || 'Niekto'
    return `${name} lajkol tvoj príspevok`
  }
  if (item.type === 'event_reminder') return 'Pripomienka udalosti'
  if (item.type === 'contest_winner') return 'Vyhral si súťaž'
  if (item.type === 'event_invite') return 'Prišla ti pozvánka na udalosť'
  if (item.type === 'account_restricted') return 'Účet bol obmedzený'
  if (item.type === 'iss_pass_alert') return 'ISS prelet už čoskoro'
  if (item.type === 'good_conditions_alert') return 'Výborné podmienky na pozorovanie'
  return 'Notifikácia'
}

function formatSubtitle(item) {
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
    return item.data?.next_pass_at
      ? `Ďalší prelet: ${formatClock(item.data.next_pass_at)}`
      : 'Prelet príde už čoskoro.'
  }

  if (item.type === 'good_conditions_alert') {
    const score = Number(item.data?.observing_score)
    return Number.isFinite(score)
      ? `Skóre podmienok ${Math.round(score)}/100.`
      : 'Podmienky na oblohe vyzerajú dnes výborne.'
  }

  return 'Nová aktivita'
}

function formatTime(item) {
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

function formatClock(iso) {
  if (!iso) return ''
  const value = new Date(iso)
  if (Number.isNaN(value.getTime())) return ''
  return value.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit', hour12: false })
}
</script>

<style scoped>
.btn,
.icon-btn,
.notification-quick-delete,
.toggle,
.pill {
  border: none;
  border-radius: 999px;
  box-shadow: none;
  font: inherit;
  transition: background-color 160ms ease, color 160ms ease, opacity 160ms ease, transform 160ms ease;
}

.btn {
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  padding: 0.8rem 1rem;
  font-size: 0.92rem;
  font-weight: 600;
  line-height: 1;
}

.btn--secondary,
.icon-btn,
.notification-quick-delete,
.toggle--off,
.pill--inactive {
  background: #222e3f;
  color: #abb8c9;
}

.btn--secondary:hover:not(:disabled),
.icon-btn:hover:not(:disabled),
.notification-quick-delete:hover:not(:disabled),
.btn--secondary:focus-visible,
.icon-btn:focus-visible,
.notification-quick-delete:focus-visible,
.pill--inactive:hover:not(:disabled),
.pill--inactive:focus-visible {
  background: #1c2736;
  color: #ffffff;
}

.btn--danger {
  background: #eb2452;
  color: #ffffff;
}

.btn--danger:hover:not(:disabled),
.btn--danger:focus-visible {
  background: #f55454;
}

.icon-btn,
.notification-quick-delete {
  width: 2.75rem;
  height: 2.75rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn svg,
.icon-btn svg,
.notification-quick-delete svg,
.notification-delete-swipe svg {
  width: 1rem;
  height: 1rem;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.btn:disabled,
.icon-btn:disabled,
.notification-quick-delete:disabled,
.notification-delete-swipe:disabled,
.toggle:disabled,
.pill:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn:active:not(:disabled),
.icon-btn:active:not(:disabled),
.notification-quick-delete:active:not(:disabled) {
  transform: scale(0.98);
}

.notification-list {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.notification-row {
  position: relative;
  display: grid;
  overflow: hidden;
  border-radius: 1.75rem;
}

.notification-row--deleting {
  opacity: 0.62;
}

.notification-delete-swipe,
.notification-surface,
.notification-quick-delete {
  grid-area: 1 / 1;
}

.notification-delete-swipe {
  width: 6rem;
  justify-self: end;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  border: none;
  background: #eb2452;
  color: #ffffff;
  font-size: 0.76rem;
  font-weight: 700;
}

.notification-surface {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.95rem;
  border: none;
  border-radius: 1.75rem;
  background: #1c2736;
  padding: 1rem 1rem 1rem 1.1rem;
  color: #ffffff;
  text-align: left;
  touch-action: pan-y;
}

.notification-surface:hover:not(:disabled),
.notification-surface:focus-visible {
  background: #223043;
}

.notification-surface--unread {
  background:
    linear-gradient(0deg, rgb(15 115 255 / 0.08), rgb(15 115 255 / 0.08)),
    #1c2736;
}

.notification-surface__tone {
  width: 0.72rem;
  height: 0.72rem;
  flex: none;
  border-radius: 999px;
}

.notification-quick-delete {
  z-index: 2;
  align-self: center;
  justify-self: end;
  margin-right: 0.75rem;
  display: none;
}

.settings-stack {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.settings-card {
  overflow: hidden;
  border-radius: 1.5rem;
  background: #1c2736;
}

.settings-head {
  padding: 1rem 1rem 0.9rem;
}

.settings-title {
  margin: 0;
  color: #ffffff;
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.35;
}

.settings-desc {
  margin: 0.28rem 0 0;
  color: #abb8c9;
  font-size: 0.8rem;
  line-height: 1.5;
}

.settings-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem;
}

.settings-row--border {
  border-top: 1px solid rgb(171 184 201 / 0.08);
}

.settings-row__title {
  margin: 0;
  color: #ffffff;
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.35;
}

.settings-row__desc {
  margin: 0.25rem 0 0;
  color: #abb8c9;
  font-size: 0.78rem;
  line-height: 1.5;
}

.settings-skeleton {
  height: 3.4rem;
  border-radius: 1rem;
  background: rgb(171 184 201 / 0.1);
  animation: notificationPulse 1.4s ease-in-out infinite;
}

.toggle {
  width: 3.2rem;
  height: 1.9rem;
  flex: none;
  display: inline-flex;
  align-items: center;
  padding: 0.18rem;
}

.toggle--on,
.pill--active {
  background: #0f73ff;
  color: #ffffff;
}

.toggle--on {
  justify-content: flex-end;
}

.toggle__knob {
  display: block;
  width: 1.52rem;
  height: 1.52rem;
  border-radius: 999px;
  background: #ffffff;
}

.pill {
  min-width: 3.4rem;
  padding: 0.62rem 0.8rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

:deep(.page-header) {
  border: none;
  border-radius: 0;
  background: transparent;
  padding: 0;
}

:deep(.page-header__title) {
  color: #ffffff;
  font-size: clamp(1.75rem, 2vw, 2.1rem);
  letter-spacing: -0.03em;
}

:deep([data-testid='notification-settings-modal']) {
  background: rgb(8 12 18 / 0.82);
}

:deep([data-testid='notification-settings-modal'] .modalCard) {
  border: none;
  border-radius: 1.75rem;
  background: #151d28;
  box-shadow: none;
}

:deep([data-testid='notification-settings-modal'] .modalHead) {
  border-bottom: none;
  padding: 1.4rem 1.4rem 0;
}

:deep([data-testid='notification-settings-modal'] .modalTitle) {
  color: #ffffff;
}

:deep([data-testid='notification-settings-modal'] .modalBody) {
  padding: 1rem 1.4rem 1.4rem;
}

:deep([data-testid='notification-settings-modal'] .modalClose) {
  border: none;
  background: #222e3f;
  color: #abb8c9;
  box-shadow: none;
}

:deep([data-testid='notification-settings-modal'] .modalClose:hover:not(:disabled)) {
  background: #1c2736;
  color: #ffffff;
}

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

.btn:focus-visible,
.icon-btn:focus-visible,
.notification-delete-swipe:focus-visible,
.notification-quick-delete:focus-visible,
.notification-surface:focus-visible,
.toggle:focus-visible,
.pill:focus-visible {
  outline: 2px solid #0f73ff;
  outline-offset: 2px;
}

@keyframes notificationPulse {
  0%,
  100% {
    opacity: 0.52;
  }

  50% {
    opacity: 1;
  }
}

@media (hover: hover) and (pointer: fine) {
  .notification-quick-delete {
    display: inline-flex;
    opacity: 0;
  }

  .notification-row:hover .notification-quick-delete,
  .notification-row:focus-within .notification-quick-delete {
    opacity: 1;
  }
}

@media (max-width: 767px) {
  .notification-surface {
    padding: 0.95rem 0.95rem 0.95rem 1rem;
  }

  .settings-row {
    align-items: flex-start;
  }
}

@media (max-width: 560px) {
  :deep(.page-header--row) {
    flex-direction: column;
    align-items: flex-start;
  }

  :deep(.page-header__actions) {
    width: 100%;
  }

  .btn {
    flex: 1 1 calc(50% - 0.375rem);
  }

  .icon-btn {
    flex: none;
  }

  .settings-row {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
