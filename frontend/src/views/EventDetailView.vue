<template>
  <main class="eventDetailPage">
    <header class="eventDetailTopbar">
      <button type="button" class="backButton" @click="goBack">&larr; Spat</button>
      <p class="topbarTitle">{{ pageHeaderTitle }}</p>
    </header>

    <section v-if="loading" class="loadingState" aria-label="Nacitavam detail udalosti">
      <div class="loadingCard">
        <div class="loadingLine loadingLine--chips"></div>
        <div class="loadingLine loadingLine--title"></div>
        <div class="loadingLine loadingLine--meta"></div>
        <div class="loadingPanel"></div>
        <div class="loadingLine"></div>
        <div class="loadingLine"></div>
        <div class="loadingLine loadingLine--short"></div>
      </div>
    </section>

    <section v-else-if="error" class="errorState">
      <InlineStatus
        variant="error"
        :message="error"
        action-label="Skusit znova"
        @action="loadEvent"
      />
    </section>

    <section v-else-if="event" class="eventDetailShell">
      <article
        class="eventCard"
        :class="{ 'eventCard--swipeReady': canSwipe }"
        :style="eventCardStyle"
        @touchstart.passive="onCardTouchStart"
        @touchmove.passive="onCardTouchMove"
        @touchend.passive="onCardTouchEnd"
        @touchcancel.passive="onCardTouchCancel"
      >
        <div class="eventCard__glow" aria-hidden="true"></div>

        <div class="eventCard__content">
          <div class="eventChipRow">
            <span class="eventChip">{{ typeLabel }}</span>
            <span v-if="confidenceLabel" class="eventChip">{{ confidenceLabel }}</span>
            <span class="eventChip eventChip--status">{{ statusLabel }}</span>
          </div>

          <div class="eventHeading">
            <h1 class="eventTitle">{{ title }}</h1>
            <p class="eventMeta">{{ metaLine }}</p>
          </div>

          <section class="eventSection eventTimeBlock" aria-label="Cas udalosti">
            <p class="eventSection__label">Kedy</p>
            <p class="eventTimeBlock__primary">{{ primaryObservationLine }}</p>
            <p
              v-if="secondaryEventTimeLabel"
              class="eventTimeBlock__secondary"
              :title="secondaryEventTimeAriaLabel"
              :aria-label="secondaryEventTimeAriaLabel"
            >
              {{ secondaryEventTimeLabel }}
              <span v-if="secondaryEventTimeTimezoneLabel" class="eventTimeBlock__timezone">
                ({{ secondaryEventTimeTimezoneLabel }})
              </span>
            </p>
            <p v-if="showViewingWindowMicrocopy" class="eventTimeBlock__microcopy">
              Jav nastava cez den, pozorovanie je mozne az po zotmeni.
            </p>
            <button
              v-if="viewingForecast.missingLocation"
              type="button"
              class="locationButton"
              @click="goToLocationSettings"
            >
              Nastavit polohu
            </button>
          </section>

          <section v-if="showViewingWindowMicrocopy" class="eventSection">
            <p class="eventSection__label">Ako pozorovat</p>
            <p class="eventSection__text">
              Jav nastava cez den, pozorovanie je mozne az po zotmeni.
            </p>
          </section>

          <section class="eventSection forecastSection">
            <p class="eventSection__label">Podmienky</p>
            <EventViewingWindowForecast
              :event="event"
              :user-location="resolvedLocation"
              @state="handleViewingForecastState"
            />
          </section>

          <section class="eventSection eventDescriptionBlock">
            <p class="eventSection__label">Popis</p>
            <p
              class="eventDescription"
              :class="{
                'eventDescription--collapsed': shouldCollapseDescription && !descriptionExpanded,
              }"
            >
              {{ description }}
            </p>
            <button
              v-if="shouldCollapseDescription"
              type="button"
              class="descriptionToggle"
              @click="descriptionExpanded = !descriptionExpanded"
            >
              {{ descriptionExpanded ? 'Zobrazit menej' : 'Zobrazit viac' }}
            </button>
          </section>

          <div class="eventCtaRow">
            <button
              type="button"
              class="planButton planButton--primary"
              :disabled="planSaving"
              @click="openPlanModal"
            >
              {{ planButtonLabel }}
            </button>

            <button
              type="button"
              class="followButton"
              :disabled="followLoading"
              :aria-pressed="auth.isAuthed ? String(isFollowed) : 'false'"
              @click="handleFollowToggle"
            >
              <span v-if="auth.isAuthed" class="followButton__icon" aria-hidden="true">
                &#10084;
              </span>
              <span>{{ followButtonLabel }}</span>
            </button>

            <button
              type="button"
              class="inviteButton"
              title="Pozvat"
              aria-label="Pozvat"
              @click="handleInvite"
            >
              Pozvat
            </button>

            <DropdownMenu
              class="menuRoot"
              :items="menuItems"
              label="Viac moznosti"
              menu-label="Akcie udalosti"
              @select="handleMenuSelect"
            >
              <template #trigger>
                <span class="menuTrigger" aria-hidden="true">&#8943;</span>
              </template>
            </DropdownMenu>
          </div>

          <div class="eventSwipeRow">
            <button
              type="button"
              class="swipeNavButton"
              :disabled="!canGoPrev || swipeNavigating"
              @click="goToAdjacentEvent('prev')"
            >
              &larr; Predosla
            </button>
            <p class="swipeHint">
              {{ swipeHint }}
            </p>
            <button
              type="button"
              class="swipeNavButton"
              :disabled="!canGoNext || swipeNavigating"
              @click="goToAdjacentEvent('next')"
            >
              Dalsia &rarr;
            </button>
          </div>
        </div>
      </article>
    </section>

    <InviteTicketModal :open="inviteModalOpen" :event="event" @close="inviteModalOpen = false" />

    <BaseModal
      v-model:open="planModalOpen"
      title="Naplanovat udalost"
      test-id="event-plan-modal"
      close-test-id="event-plan-modal-close"
    >
      <template #description>
        <p class="planModalHint">Osobny plan ostane pri tejto planovanej udalosti.</p>
      </template>

      <form class="planForm" @submit.prevent="savePlan">
        <label class="planField">
          <span class="planField__label">Poznamka</span>
          <textarea
            v-model="planForm.personal_note"
            class="planField__textarea"
            rows="3"
            maxlength="4000"
            placeholder="Napriklad: vyhliadka, vybava, koho beriem so sebou."
          />
        </label>

        <label class="planField">
          <span class="planField__label">Pripomenut</span>
          <select v-model="planForm.reminder_mode" class="planField__input">
            <option value="none">Bez pripomienky</option>
            <option value="one_hour_before" :disabled="!canUseReminderPresets">1 hodinu pred</option>
            <option value="same_day_morning" :disabled="!canUseReminderPresets">V den udalosti rano</option>
            <option value="day_before" :disabled="!canUseReminderPresets">Den vopred</option>
            <option value="custom">Vlastny cas</option>
          </select>
        </label>

        <label v-if="planForm.reminder_mode === 'custom'" class="planField">
          <span class="planField__label">Cas pripomienky</span>
          <input
            v-model="planForm.reminder_custom_at"
            class="planField__input"
            type="datetime-local"
          />
        </label>

        <label class="planField">
          <span class="planField__label">Cas pozorovania (volitelne)</span>
          <input
            v-model="planForm.planned_time"
            class="planField__input"
            type="datetime-local"
          />
        </label>

        <label class="planField">
          <span class="planField__label">Miesto pozorovania (volitelne)</span>
          <input
            v-model="planForm.planned_location_label"
            class="planField__input"
            type="text"
            maxlength="160"
            placeholder="Napriklad: Hradza, observatorium, kopec za mestom"
          />
        </label>

        <p v-if="recommendedPlanHint" class="planRecommendation">
          {{ recommendedPlanHint }}
        </p>

        <InlineStatus v-if="planError" variant="error" :message="planError" />

        <div class="planActions">
          <button type="button" class="planGhostButton" :disabled="planSaving" @click="planModalOpen = false">
            Zrusit
          </button>
          <button type="submit" class="planSaveButton" :disabled="planSaving">
            {{ planSaving ? 'Ukladam...' : 'Ulozit plan' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import InviteTicketModal from '@/components/events/InviteTicketModal.vue'
import EventViewingWindowForecast from '@/components/events/EventViewingWindowForecast.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useToast } from '@/composables/useToast'
import api from '@/services/api'
import { updateEventPlan } from '@/services/eventFollows'
import { getEvents } from '@/services/events'
import { useAuthStore } from '@/stores/auth'
import { useEventFollowsStore } from '@/stores/eventFollows'
import {
  EVENT_TIMEZONE,
  formatEventDate,
  formatEventDateKey,
  formatEventTime,
  getHourInTimezone,
  parseEventDate,
  resolveEventTimeContext,
} from '@/utils/eventTime'
import { eventDisplayDescription, eventDisplayTitle } from '@/utils/translatedFields'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const eventFollows = useEventFollowsStore()
const toast = useToast()

const event = ref(null)
const loading = ref(true)
const error = ref('')
const descriptionExpanded = ref(false)
const inviteModalOpen = ref(false)
const planModalOpen = ref(false)
const planSaving = ref(false)
const planError = ref('')
const planForm = reactive({
  personal_note: '',
  reminder_mode: 'none',
  reminder_custom_at: '',
  planned_time: '',
  planned_location_label: '',
})
const viewingForecast = ref(createInitialViewingState())
const adjacentEventIds = ref({
  prev: null,
  next: null,
})
const swipeNavigating = ref(false)
const swipeDx = ref(0)
const swipeTouchActive = ref(false)
const swipeReleaseAnimating = ref(false)

let adjacentLoadToken = 0
let touchStartX = null
let touchStartY = null
let touchStartAt = 0

const eventId = computed(() => Number(route.params.id))
const title = computed(() => {
  const value = eventDisplayTitle(event.value)
  return value === '-' ? 'Detail udalosti' : value
})
const description = computed(() => {
  const value = eventDisplayDescription(event.value)
  return value === '-' ? 'Popis tejto udalosti zatial doplname.' : value
})
const shouldCollapseDescription = computed(() => description.value.length > 320)
const resolvedLocation = computed(() => resolveUserLocation(auth.user))
const viewingTimezone = computed(() => sanitizeLocationText(resolvedLocation.value?.tz) || EVENT_TIMEZONE)
const metaDateLabel = computed(() => formatEventMetaDate(event.value, EVENT_TIMEZONE))
const typeLabel = computed(() => mapType(event.value?.type))
const statusLabel = computed(() => mapStatus(event.value))
const visibilityLabel = computed(() => mapVisibility(event.value?.visibility))
const confidenceLabel = computed(() => mapConfidence(event.value?.public_confidence?.level))
const metaLine = computed(() =>
  [metaDateLabel.value, visibilityLabel.value].filter((value) => value !== '').join(' - '),
)
const isFollowed = computed(() => eventFollows.isFollowed(eventId.value))
const followLoading = computed(() => eventFollows.isLoading(eventId.value))
const followButtonLabel = computed(() => {
  if (!auth.isAuthed) return 'Prihlasit sa pre sledovanie'
  return isFollowed.value ? 'Sledujes' : 'Sledovat'
})
const hasSavedPlan = computed(() => Boolean(event.value?.plan?.has_data))
const planButtonLabel = computed(() => (hasSavedPlan.value ? 'Upravit plan' : 'Naplanovat pozorovanie'))
const pageHeaderTitle = computed(() => (event.value ? typeLabel.value : 'Detail udalosti'))
const menuItems = computed(() => [
  { key: 'calendar', label: 'Pridat do kalendara' },
  { key: 'share', label: 'Zdielat odkaz' },
])
const viewingWindowStart = computed(() => parseDate(viewingForecast.value.viewingWindow?.start_at))
const viewingWindowEnd = computed(() => parseDate(viewingForecast.value.viewingWindow?.end_at))
const viewingWindowLabel = computed(() => {
  if (!viewingWindowStart.value || !viewingWindowEnd.value) return ''
  return `${formatTime(viewingWindowStart.value, viewingTimezone.value)} - ${formatTime(viewingWindowEnd.value, viewingTimezone.value)}`
})
const canUseReminderPresets = computed(() => resolveEventAnchorDate(event.value) !== null)
const resolvedReminderAt = computed(() => {
  if (planForm.reminder_mode === 'none') return null

  if (planForm.reminder_mode === 'custom') {
    return parseDateTimeLocal(planForm.reminder_custom_at)
  }

  return resolveReminderPresetDate(planForm.reminder_mode, event.value)
})
const recommendedPlanHint = computed(() => {
  if (viewingWindowLabel.value) {
    return `Odporucane sledovanie: ${viewingWindowLabel.value}`
  }

  const fallback = sanitizeLocationText(event.value?.recommended_viewing_label)
  return fallback ? `Odporucane sledovanie: ${fallback}` : ''
})
const eventTimeContext = computed(() => resolveEventTimeContext(event.value, EVENT_TIMEZONE))
const primaryObservationLine = computed(() => {
  if (viewingForecast.value.loading && !viewingWindowLabel.value) {
    return 'Pozorovanie: nacitavam'
  }

  if (viewingForecast.value.missingLocation) {
    return 'Pozorovanie: nastav polohu'
  }

  if (viewingWindowLabel.value) {
    return `Pozorovanie: ${viewingWindowLabel.value}`
  }

  return 'Pozorovanie: upresnime'
})
const secondaryEventTimeLabel = computed(() => eventTimeContext.value.message)
const secondaryEventTimeTimezoneLabel = computed(() =>
  eventTimeContext.value.showTimezoneLabel ? eventTimeContext.value.timezoneLabelShort : '',
)
const secondaryEventTimeAriaLabel = computed(() => {
  if (!eventTimeContext.value.showTimezoneLabel) {
    return eventTimeContext.value.message
  }

  return `${eventTimeContext.value.message} (${eventTimeContext.value.timezoneLabelShort}), cas v ${eventTimeContext.value.timezoneLabelLong}`
})
const showViewingWindowMicrocopy = computed(() => {
  const phenomenonAt = resolvePhenomenonDate(event.value)
  const startAt = viewingWindowStart.value

  if (!phenomenonAt || !startAt) return false
  if (formatDateKey(phenomenonAt, viewingTimezone.value) !== formatDateKey(startAt, viewingTimezone.value)) {
    return false
  }
  if (phenomenonAt.getTime() >= startAt.getTime()) return false

  const localHour = getHourInTimezone(phenomenonAt, viewingTimezone.value)
  return localHour !== null && localHour >= 6 && localHour < 18
})
const canGoPrev = computed(() => Number.isInteger(adjacentEventIds.value.prev))
const canGoNext = computed(() => Number.isInteger(adjacentEventIds.value.next))
const canSwipe = computed(() => canGoPrev.value || canGoNext.value)
const swipeHint = computed(() => {
  if (!canSwipe.value) return 'V tomto obdobi nie je dalsia udalost.'
  if (!canGoPrev.value) return 'Potiahni dolava pre dalsiu udalost.'
  if (!canGoNext.value) return 'Potiahni doprava pre predoslu udalost.'
  return 'Potiahni dolava alebo doprava pre prechod medzi udalostami.'
})
const eventCardStyle = computed(() => {
  if (!canSwipe.value) return {}

  const clampedDx = Math.max(-240, Math.min(240, swipeDx.value))
  const rotate = (clampedDx / 240) * 5
  const transition = swipeTouchActive.value
    ? 'none'
    : swipeReleaseAnimating.value
      ? 'transform 200ms cubic-bezier(0.2, 0.8, 0.2, 1)'
      : 'transform 160ms ease'

  return {
    transform: `translate3d(${clampedDx}px, 0, 0) rotate(${rotate}deg)`,
    transition,
    willChange: 'transform',
  }
})

function createInitialViewingState() {
  return {
    loading: false,
    viewingWindow: null,
    summary: null,
    missingLocation: false,
    unavailable: false,
  }
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
    return
  }

  router.push({ name: 'events' })
}

async function loadEvent() {
  if (!Number.isFinite(eventId.value)) {
    error.value = 'Neplatny identifikator udalosti.'
    loading.value = false
    return
  }

  loading.value = true
  error.value = ''
  descriptionExpanded.value = false
  viewingForecast.value = createInitialViewingState()
  resetSwipeGesture()

  try {
    const res = await api.get(`/events/${eventId.value}`)
    event.value = res?.data?.data ?? res?.data ?? null
    syncPlanFormFromEvent(event.value)
    void loadAdjacentEvents(event.value)

    if (auth.isAuthed) {
      await eventFollows.syncFollowState(eventId.value)
    }
  } catch (requestError) {
    error.value =
      requestError?.response?.data?.message ||
      requestError?.userMessage ||
      'Nepodarilo sa nacitat detail udalosti.'
  } finally {
    loading.value = false
  }
}

function onCardTouchStart(eventValue) {
  if (!canSwipe.value || swipeNavigating.value || isInteractiveTarget(eventValue.target)) return
  const touch = eventValue.touches?.[0]
  if (!touch) return
  swipeTouchActive.value = true
  swipeReleaseAnimating.value = false
  swipeDx.value = 0
  touchStartX = touch.clientX
  touchStartY = touch.clientY
  touchStartAt = Date.now()
}

function onCardTouchMove(eventValue) {
  if (!canSwipe.value || !swipeTouchActive.value || touchStartX === null || touchStartY === null) return
  const touch = eventValue.touches?.[0]
  if (!touch) return

  const dx = touch.clientX - touchStartX
  const dy = touch.clientY - touchStartY

  if (Math.abs(dy) > Math.abs(dx) * 1.1) {
    swipeDx.value = Math.max(-40, Math.min(40, dx * 0.15))
    return
  }

  swipeDx.value = Math.max(-240, Math.min(240, dx))
}

function onCardTouchEnd(eventValue) {
  if (!canSwipe.value || touchStartX === null || touchStartY === null) {
    resetSwipeGesture()
    return
  }

  const touch = eventValue.changedTouches?.[0]
  if (!touch) {
    animateSwipeBack()
    return
  }

  const dx = touch.clientX - touchStartX
  const dy = touch.clientY - touchStartY
  const elapsed = Date.now() - touchStartAt
  swipeTouchActive.value = false
  touchStartX = null
  touchStartY = null
  touchStartAt = 0

  if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.2) {
    animateSwipeBack()
    return
  }
  if (elapsed > 700) {
    animateSwipeBack()
    return
  }

  if (dx < 0) {
    if (!canGoNext.value) {
      animateSwipeBack()
      return
    }
    void goToAdjacentEvent('next', { animate: true })
    return
  }

  if (!canGoPrev.value) {
    animateSwipeBack()
    return
  }
  void goToAdjacentEvent('prev', { animate: true })
}

function onCardTouchCancel() {
  animateSwipeBack()
}

function isInteractiveTarget(target) {
  if (!(target instanceof Element)) return false
  return Boolean(target.closest('button, a, input, textarea, select, label, [role="button"]'))
}

async function goToAdjacentEvent(direction, options = {}) {
  const targetId = direction === 'prev' ? adjacentEventIds.value.prev : adjacentEventIds.value.next
  if (!Number.isInteger(targetId) || swipeNavigating.value || Number(targetId) === eventId.value) return

  const animate = options?.animate === true
  swipeNavigating.value = true

  if (animate) {
    swipeTouchActive.value = false
    swipeReleaseAnimating.value = true
    swipeDx.value = direction === 'prev' ? 220 : -220
    await waitForMs(90)
  }

  try {
    await router.push({ name: 'event-detail', params: { id: Number(targetId) } })
  } finally {
    swipeNavigating.value = false
    resetSwipeGesture()
  }
}

function animateSwipeBack() {
  swipeTouchActive.value = false
  swipeReleaseAnimating.value = true
  swipeDx.value = 0
  window.setTimeout(() => {
    swipeReleaseAnimating.value = false
  }, 220)
}

function resetSwipeGesture() {
  swipeTouchActive.value = false
  swipeReleaseAnimating.value = false
  swipeDx.value = 0
  touchStartX = null
  touchStartY = null
  touchStartAt = 0
}

function waitForMs(delayMs) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, delayMs)
  })
}

async function loadAdjacentEvents(currentEvent) {
  const currentId = Number(currentEvent?.id)
  if (!Number.isInteger(currentId)) {
    adjacentEventIds.value = { prev: null, next: null }
    return
  }

  const token = ++adjacentLoadToken
  adjacentEventIds.value = { prev: null, next: null }

  const anchor = resolveEventAnchorDate(currentEvent) || new Date()

  try {
    const nearby = await fetchEventsAroundAnchor(anchor, 60)
    let neighbors = resolveAdjacentIds(nearby, currentId)

    if (!neighbors.prev || !neighbors.next) {
      const expanded = await fetchEventsAroundAnchor(anchor, 180)
      neighbors = resolveAdjacentIds(expanded, currentId)
    }

    if (token !== adjacentLoadToken) return
    adjacentEventIds.value = neighbors
  } catch {
    if (token !== adjacentLoadToken) return
    adjacentEventIds.value = { prev: null, next: null }
  }
}

async function fetchEventsAroundAnchor(anchorDate, dayRadius) {
  const from = new Date(anchorDate.getTime() - dayRadius * 24 * 60 * 60 * 1000)
  from.setUTCHours(0, 0, 0, 0)

  const to = new Date(anchorDate.getTime() + dayRadius * 24 * 60 * 60 * 1000)
  to.setUTCHours(23, 59, 59, 999)

  const response = await getEvents({
    from: from.toISOString(),
    to: to.toISOString(),
    scope: 'all',
  })

  return normalizeEventsList(response)
}

function normalizeEventsList(response) {
  const payload = response?.data
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  return []
}

function resolveAdjacentIds(items, currentId) {
  const byId = new Map()

  for (const item of items) {
    const normalizedId = Number(item?.id)
    if (!Number.isInteger(normalizedId) || byId.has(normalizedId)) continue
    byId.set(normalizedId, item)
  }

  const ordered = Array.from(byId.values()).sort((a, b) => {
    const aDate = resolveSortableEventDate(a)
    const bDate = resolveSortableEventDate(b)
    const aTime = aDate ? aDate.getTime() : Number.POSITIVE_INFINITY
    const bTime = bDate ? bDate.getTime() : Number.POSITIVE_INFINITY
    if (aTime !== bTime) return aTime - bTime
    return Number(a?.id || 0) - Number(b?.id || 0)
  })

  const index = ordered.findIndex((item) => Number(item?.id) === currentId)
  if (index < 0) {
    return { prev: null, next: null }
  }

  const prev = Number(ordered[index - 1]?.id)
  const next = Number(ordered[index + 1]?.id)

  return {
    prev: Number.isInteger(prev) ? prev : null,
    next: Number.isInteger(next) ? next : null,
  }
}

function resolveSortableEventDate(item) {
  return parseDate(
    item?.event_date ||
      item?.start_at ||
      item?.starts_at ||
      item?.max_at ||
      item?.end_at ||
      item?.ends_at,
  )
}

function handleWindowKeydown(eventValue) {
  if (eventValue.defaultPrevented) return
  if (isInteractiveTarget(eventValue.target)) return

  if (eventValue.key === 'ArrowLeft') {
    eventValue.preventDefault()
    void goToAdjacentEvent('prev')
    return
  }

  if (eventValue.key === 'ArrowRight') {
    eventValue.preventDefault()
    void goToAdjacentEvent('next')
  }
}

function redirectToLogin() {
  router.push({
    name: 'login',
    query: { redirect: route.fullPath },
  })
}

async function handleFollowToggle() {
  if (!event.value?.id) return

  if (!auth.isAuthed) {
    redirectToLogin()
    return
  }

  try {
    const followed = await eventFollows.toggle(event.value.id)
    if (followed) {
      toast.success('Udalost teraz sledujes.')
    } else {
      toast.info('Udalost uz nesledujes.')
    }
  } catch (toggleError) {
    toast.error(
      toggleError?.response?.data?.message ||
        toggleError?.userMessage ||
        'Nepodarilo sa upravit sledovanie.',
    )
  }
}

function openPlanModal() {
  if (!event.value?.id) return

  if (!auth.isAuthed) {
    redirectToLogin()
    return
  }

  syncPlanFormFromEvent(event.value)
  planError.value = ''
  planModalOpen.value = true
}

async function savePlan() {
  if (!event.value?.id || planSaving.value) return

  if (!auth.isAuthed) {
    redirectToLogin()
    return
  }

  planSaving.value = true
  planError.value = ''

  try {
    await auth.csrf()

    const response = await updateEventPlan(event.value.id, {
      personal_note: toNullableString(planForm.personal_note),
      reminder_at: resolvedReminderAt.value ? resolvedReminderAt.value.toISOString() : null,
      planned_time: parseDateTimeLocal(planForm.planned_time)?.toISOString() || null,
      planned_location_label: toNullableString(planForm.planned_location_label),
    })

    const nextEvent = response?.data?.data
    if (nextEvent && typeof nextEvent === 'object') {
      event.value = nextEvent
    }

    eventFollows.setFollowed(event.value.id, true)
    eventFollows.revision += 1
    planModalOpen.value = false
    toast.success('Plan udalosti bol ulozeny.')
  } catch (saveError) {
    planError.value =
      saveError?.response?.data?.message ||
      saveError?.userMessage ||
      'Nepodarilo sa ulozit plan.'
  } finally {
    planSaving.value = false
  }
}

function handleInvite() {
  if (!event.value?.id) return

  if (!auth.isAuthed) {
    redirectToLogin()
    return
  }

  inviteModalOpen.value = true
}

async function handleMenuSelect(item) {
  if (!item?.key) return

  if (item.key === 'calendar') {
    await downloadCalendarIcs()
    return
  }

  if (item.key === 'share') {
    await copyEventLink()
  }
}

async function downloadCalendarIcs() {
  if (!event.value?.id) return

  try {
    const response = await api.get(`/events/${event.value.id}/calendar.ics`, {
      responseType: 'blob',
      meta: { skipErrorToast: true },
      headers: {
        Accept: 'text/calendar',
      },
    })

    const blob = response?.data instanceof Blob
      ? response.data
      : new Blob([response?.data ?? ''], { type: 'text/calendar;charset=utf-8' })

    const objectUrl = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = objectUrl
    anchor.download = `astrokomunita-event-${event.value.id}.ics`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(objectUrl)
    toast.success('Kalendar bol stiahnuty.')
  } catch (downloadError) {
    toast.error(
      downloadError?.response?.data?.message ||
        downloadError?.userMessage ||
        'Nepodarilo sa stiahnut kalendar.',
    )
  }
}

async function copyEventLink() {
  const url = `${window.location.origin}${route.fullPath}`

  try {
    await copyText(url)
    toast.success('Odkaz na udalost bol skopirovany.')
  } catch (copyError) {
    toast.error(copyError?.message || 'Nepodarilo sa skopirovat odkaz.')
  }
}

function handleViewingForecastState(nextState) {
  viewingForecast.value = {
    ...createInitialViewingState(),
    ...(nextState && typeof nextState === 'object' ? nextState : {}),
  }
}

function goToLocationSettings() {
  router.push('/profile/edit')
}

async function copyText(value) {
  if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value)
    return
  }

  const helper = document.createElement('textarea')
  helper.value = value
  helper.setAttribute('readonly', 'readonly')
  helper.style.position = 'fixed'
  helper.style.opacity = '0'
  document.body.appendChild(helper)
  helper.select()
  document.execCommand('copy')
  helper.remove()
}

function resolvePhenomenonDate(item) {
  return parseDate(
    item?.max_at ||
      item?.start_at ||
      item?.starts_at ||
      item?.end_at ||
      item?.ends_at,
  )
}

function syncPlanFormFromEvent(item) {
  const plan = item?.plan && typeof item.plan === 'object' ? item.plan : null

  planForm.personal_note = normalizeFieldText(plan?.personal_note)
  planForm.planned_location_label = normalizeFieldText(plan?.planned_location_label)
  planForm.planned_time = toDateTimeLocal(plan?.planned_time)

  const reminder = toDateTimeLocal(plan?.reminder_at)
  if (reminder) {
    planForm.reminder_mode = 'custom'
    planForm.reminder_custom_at = reminder
    return
  }

  planForm.reminder_mode = 'none'
  planForm.reminder_custom_at = ''
}

function resolveReminderPresetDate(mode, item) {
  const anchor = resolveEventAnchorDate(item)
  if (!anchor) return null

  if (mode === 'one_hour_before') {
    return new Date(anchor.getTime() - 60 * 60 * 1000)
  }

  if (mode === 'day_before') {
    return new Date(anchor.getTime() - 24 * 60 * 60 * 1000)
  }

  if (mode === 'same_day_morning') {
    const dateKey = formatDateKey(anchor, EVENT_TIMEZONE)
    if (!dateKey) return null
    return parseDateTimeLocal(`${dateKey}T08:00`)
  }

  return null
}

function resolveEventAnchorDate(item) {
  return parseDate(
    item?.start_at ||
      item?.starts_at ||
      item?.max_at ||
      item?.end_at ||
      item?.ends_at,
  )
}

function toDateTimeLocal(value) {
  const parsed = parseDate(value)
  if (!parsed) return ''

  const local = new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60 * 1000)
  return local.toISOString().slice(0, 16)
}

function parseDateTimeLocal(value) {
  if (typeof value !== 'string' || value.trim() === '') return null

  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function normalizeFieldText(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

function toNullableString(value) {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

function formatEventMetaDate(item, timeZone) {
  if (!item) return 'Datum upresnime'

  const startAt = parseDate(item.start_at || item.starts_at || item.max_at || item.end_at || item.ends_at)
  const endAt = parseDate(item.end_at || item.ends_at)

  if (!startAt) return 'Datum upresnime'
  if (!endAt || formatDateKey(startAt, timeZone) === formatDateKey(endAt, timeZone)) {
    return formatDateLabel(startAt, timeZone)
  }

  return `${formatDateLabel(startAt, timeZone)} - ${formatDateLabel(endAt, timeZone)}`
}

function formatDateLabel(value, timeZone) {
  return formatEventDate(value, timeZone, {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

function formatDateKey(value, timeZone) {
  return formatEventDateKey(value, timeZone)
}

function formatTime(value, timeZone) {
  return formatEventTime(value, timeZone).timeString
}

function parseDate(value) {
  return parseEventDate(value)
}

function mapType(type) {
  const types = {
    meteors: 'Meteory',
    meteor_shower: 'Meteoricky roj',
    eclipse_lunar: 'Zatmenie Mesiaca',
    eclipse_solar: 'Zatmenie Slnka',
    planetary_event: 'Planetarny jav',
    conjunction: 'Konjunkcia',
    comet: 'Kometa',
    asteroid: 'Asteroid',
    mission: 'Misia',
    other: 'Udalost',
  }

  return types[type] || 'Udalost'
}

function mapStatus(item) {
  const startRaw = item?.start_at || item?.starts_at || item?.max_at
  if (!startRaw) return 'Termin caka'

  const start = parseDate(startRaw)
  if (!start) return 'Termin caka'

  const eventDayKey = formatDateKey(start, EVENT_TIMEZONE)
  const todayKey = formatDateKey(new Date(), EVENT_TIMEZONE)
  if (!eventDayKey || !todayKey) return 'Termin caka'

  if (eventDayKey < todayKey) return 'Prebehlo'
  if (eventDayKey === todayKey) return 'Dnes'
  return 'Planovane'
}

function mapVisibility(value) {
  if (value === 1 || value === '1') return 'Viditelne zo Slovenska'
  if (value === 0 || value === '0') return 'Mimo Slovenska'
  return 'Viditelnost sa upresni'
}

function mapConfidence(level) {
  if (level === 'verified') return 'Overene'
  if (level === 'partial') return 'Ciastocne overene'
  if (level === 'low') return 'Nizsia dovera'
  return ''
}

function resolveUserLocation(user) {
  if (!user || typeof user !== 'object') return null

  const locationData = user.location_data && typeof user.location_data === 'object'
    ? user.location_data
    : null
  const locationMeta = user.location_meta && typeof user.location_meta === 'object'
    ? user.location_meta
    : null

  const lat = toFiniteNumber(locationData?.latitude ?? locationMeta?.lat)
  const lon = toFiniteNumber(locationData?.longitude ?? locationMeta?.lon)
  const tz = sanitizeLocationText(locationData?.timezone ?? locationMeta?.tz) || EVENT_TIMEZONE
  const label = sanitizeLocationText(
    locationData?.label ?? locationMeta?.label ?? user.location_label ?? user.location,
  )

  if (lat === null || lon === null) {
    return null
  }

  return {
    lat,
    lon,
    tz,
    label,
  }
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function sanitizeLocationText(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

onMounted(() => {
  void loadEvent()
  window.addEventListener('keydown', handleWindowKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleWindowKeydown)
  adjacentLoadToken += 1
})

watch(
  () => route.params.id,
  () => {
    void loadEvent()
  },
)

watch(
  () => planForm.reminder_mode,
  (mode) => {
    if (mode === 'none') {
      planForm.reminder_custom_at = ''
      return
    }

    if (mode === 'custom') {
      return
    }

    const presetDate = resolveReminderPresetDate(mode, event.value)
    planForm.reminder_custom_at = presetDate ? toDateTimeLocal(presetDate) : ''
  },
)

watch(
  () => auth.isAuthed,
  (isAuthed) => {
    if (isAuthed && event.value?.id) {
      eventFollows.syncFollowState(event.value.id).catch(() => {})
    }
  },
)
</script>

<style scoped>
.eventDetailPage {
  min-height: calc(100vh - 3rem);
  width: min(100%, 50rem);
  margin: 0 auto;
  padding: 1rem 0.95rem 3rem;
}

.eventDetailTopbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.backButton,
.locationButton {
  min-height: 2.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(255 255 255 / 0.04);
  color: rgb(245 248 255 / 0.92);
  padding: 0 0.95rem;
  font-size: 0.84rem;
  font-weight: 600;
}

.topbarTitle {
  margin: 0;
  color: rgb(255 255 255 / 0.48);
  font-size: 0.78rem;
  line-height: 1.4;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.eventDetailShell,
.loadingState {
  display: grid;
}

.eventCard,
.loadingCard {
  position: relative;
  overflow: hidden;
  border-radius: 1.65rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.11);
  background:
    linear-gradient(180deg, rgb(20 29 41 / 0.98), rgb(13 20 30 / 0.98)),
    rgb(21 29 40 / 0.98);
  box-shadow: 0 22px 48px rgb(0 0 0 / 0.2);
}

.eventCard--swipeReady {
  touch-action: pan-y;
}

.eventCard__glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top left, rgb(var(--color-primary-rgb) / 0.12), transparent 42%),
    radial-gradient(circle at 85% 10%, rgb(255 255 255 / 0.04), transparent 24%);
  pointer-events: none;
}

.eventCard__content,
.loadingCard {
  position: relative;
  z-index: 1;
  display: grid;
  gap: 1rem;
  padding: 1.2rem;
}

.eventChipRow {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.eventChip {
  display: inline-flex;
  align-items: center;
  min-height: 1.7rem;
  padding: 0 0.7rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  background: rgb(255 255 255 / 0.04);
  color: rgb(255 255 255 / 0.76);
  font-size: 0.73rem;
  font-weight: 700;
}

.eventChip--status {
  border-color: rgb(var(--color-primary-rgb) / 0.2);
  background: rgb(var(--color-primary-rgb) / 0.1);
  color: rgb(244 248 255 / 0.95);
}

.eventHeading {
  display: grid;
  gap: 0.4rem;
}

.eventTitle {
  margin: 0;
  color: rgb(255 255 255 / 0.98);
  font-size: clamp(1.95rem, 7.3vw, 2.9rem);
  line-height: 0.98;
  font-weight: 650;
  letter-spacing: -0.04em;
}

.eventMeta {
  margin: 0;
  color: rgb(255 255 255 / 0.62);
  font-size: 0.9rem;
  line-height: 1.5;
}

.eventSection {
  display: grid;
  gap: 0.45rem;
  padding: 0.95rem 0;
  border-top: 1px solid var(--color-divider);
}

.eventSection__label {
  margin: 0;
  color: rgb(255 255 255 / 0.62);
  font-size: 0.77rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.eventSection__text {
  margin: 0;
  color: rgb(255 255 255 / 0.72);
  font-size: 0.92rem;
  line-height: 1.55;
}

.eventTimeBlock {
  gap: 0.5rem;
}

.eventTimeBlock__primary {
  margin: 0;
  color: rgb(250 252 255 / 0.98);
  font-size: clamp(1.2rem, 4.9vw, 1.52rem);
  line-height: 1.24;
  font-weight: 650;
  letter-spacing: -0.017em;
}

.eventTimeBlock__secondary,
.eventTimeBlock__microcopy {
  margin: 0;
  color: rgb(255 255 255 / 0.62);
  font-size: 0.9rem;
  line-height: 1.5;
}

.eventTimeBlock__microcopy {
  color: rgb(220 229 242 / 0.72);
}

.eventTimeBlock__timezone {
  color: rgb(255 255 255 / 0.48);
}

.locationButton {
  width: fit-content;
  min-height: 2.3rem;
  margin-top: 0.18rem;
}

.forecastSection {
  padding-bottom: 0.85rem;
}

.forecastSection :deep(.forecastWrap) {
  margin-top: 0.1rem;
}

.eventDescriptionBlock {
  gap: 0.7rem;
  max-width: 43.75rem;
}

.eventDescription {
  margin: 0;
  max-width: 43.75rem;
  color: rgb(255 255 255 / 0.84);
  font-size: 1rem;
  line-height: 1.6;
  white-space: pre-wrap;
}

.eventDescription--collapsed {
  display: -webkit-box;
  -webkit-line-clamp: 5;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.descriptionToggle {
  width: fit-content;
  border: 0;
  background: transparent;
  color: rgb(214 231 255 / 0.88);
  font-size: 0.86rem;
  font-weight: 600;
  padding: 0;
}

.eventCtaRow {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.7rem;
  padding-top: 0.2rem;
}

.planButton,
.followButton,
.inviteButton,
.swipeNavButton {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 3rem;
  border-radius: 999px;
  font-size: 0.9rem;
  font-weight: 650;
  padding: 0 1rem;
}

.planButton--primary {
  flex: 1 1 14rem;
  gap: 0.5rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.34);
  background: linear-gradient(
    180deg,
    rgb(var(--color-primary-rgb) / 0.22),
    rgb(var(--color-primary-rgb) / 0.12)
  );
  color: rgb(247 250 255 / 0.98);
  letter-spacing: -0.008em;
  box-shadow: 0 12px 24px rgb(var(--color-primary-rgb) / 0.12);
}

.followButton {
  flex: 1 1 10rem;
  gap: 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  background: rgb(255 255 255 / 0.06);
  color: rgb(247 250 255 / 0.95);
}

.inviteButton {
  flex: 0 0 auto;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(255 255 255 / 0.04);
  color: rgb(255 255 255 / 0.9);
}

.followButton__icon {
  line-height: 1;
}

.followButton:disabled,
.planButton:disabled,
.inviteButton:disabled,
.swipeNavButton:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.eventSwipeRow {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  border-top: 1px solid var(--color-divider);
  padding-top: 0.9rem;
}

.swipeNavButton {
  flex: 0 0 auto;
  min-height: 2.65rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(255 255 255 / 0.03);
  color: rgb(255 255 255 / 0.84);
  font-size: 0.82rem;
  padding: 0 0.82rem;
}

.swipeHint {
  flex: 1 1 auto;
  margin: 0;
  color: rgb(255 255 255 / 0.52);
  font-size: 0.8rem;
  line-height: 1.4;
  text-align: center;
}

.menuRoot {
  flex: 0 0 auto;
}

.menuTrigger {
  display: inline-grid;
  place-items: center;
  width: 1.1rem;
  font-size: 1.1rem;
  line-height: 1;
}

.menuRoot :deep(.dropdownTrigger) {
  display: inline-grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(255 255 255 / 0.04);
  color: rgb(255 255 255 / 0.92);
  padding: 0;
}

.menuRoot :deep(.dropdownTrigger:hover) {
  background: rgb(255 255 255 / 0.07);
  color: rgb(255 255 255 / 0.98);
}

.menuRoot :deep(.dropdownMenu) {
  min-width: 12rem;
  border-color: rgb(var(--color-text-secondary-rgb) / 0.14);
  background: rgb(18 25 36 / 0.98);
  box-shadow: 0 18px 36px rgb(0 0 0 / 0.24);
}

.menuRoot :deep(.dropdownItem) {
  color: rgb(255 255 255 / 0.88);
  padding: 0.6rem 0.72rem;
}

.menuRoot :deep(.dropdownItem:hover) {
  background: rgb(255 255 255 / 0.08);
}

.errorState {
  display: grid;
  gap: 0.9rem;
  justify-items: start;
  color: rgb(255 255 255 / 0.72);
  padding: 1rem 0;
}

.errorState :deep(.inlineStatus) {
  max-width: 34rem;
}

.planModalHint {
  margin: 0.2rem 0 0;
  color: rgb(255 255 255 / 0.64);
  font-size: 0.84rem;
}

.planForm {
  display: grid;
  gap: 0.8rem;
}

.planField {
  display: grid;
  gap: 0.38rem;
}

.planField__label {
  color: rgb(255 255 255 / 0.68);
  font-size: 0.82rem;
}

.planField__input,
.planField__textarea {
  width: 100%;
  border-radius: 0.86rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  background: rgb(255 255 255 / 0.04);
  color: rgb(255 255 255 / 0.92);
  font-size: 0.92rem;
  padding: 0.62rem 0.72rem;
}

.planField__textarea {
  resize: vertical;
  min-height: 5.6rem;
}

.planRecommendation {
  margin: 0;
  border-radius: 0.82rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.25);
  background: rgb(var(--color-primary-rgb) / 0.1);
  color: rgb(236 243 255 / 0.94);
  font-size: 0.84rem;
  line-height: 1.45;
  padding: 0.58rem 0.68rem;
}

.planActions {
  display: flex;
  justify-content: flex-end;
  gap: 0.55rem;
}

.planGhostButton,
.planSaveButton {
  min-height: 2.5rem;
  border-radius: 999px;
  padding: 0 0.95rem;
  font-size: 0.84rem;
  font-weight: 600;
}

.planGhostButton {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: transparent;
  color: rgb(255 255 255 / 0.74);
}

.planSaveButton {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.42);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: rgb(250 253 255 / 0.98);
}

.loadingLine,
.loadingPanel {
  border-radius: 999px;
  background: linear-gradient(
    90deg,
    rgb(255 255 255 / 0.06),
    rgb(255 255 255 / 0.14),
    rgb(255 255 255 / 0.06)
  );
  background-size: 200% 100%;
  animation: shimmer 1.3s linear infinite;
}

.loadingLine {
  height: 0.9rem;
}

.loadingLine--chips {
  width: 48%;
}

.loadingLine--title {
  width: 78%;
  height: 2.9rem;
  border-radius: 1rem;
}

.loadingLine--meta {
  width: 56%;
}

.loadingLine--short {
  width: 34%;
}

.loadingPanel {
  min-height: 6.8rem;
  border-radius: 1.05rem;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }

  100% {
    background-position: -200% 0;
  }
}

@media (max-width: 767px) {
  .eventDetailPage {
    padding-inline: 0.85rem;
    padding-bottom: 2.2rem;
  }

  .eventCard__content,
  .loadingCard {
    padding: 1rem;
  }

  .eventTitle {
    font-size: clamp(1.9rem, 9vw, 2.45rem);
  }

  .eventCtaRow {
    gap: 0.55rem;
  }

  .planButton--primary {
    flex: 1 1 100%;
  }

  .followButton,
  .planButton,
  .inviteButton,
  .swipeNavButton,
  .menuRoot :deep(.dropdownTrigger) {
    min-height: 2.9rem;
    height: 2.9rem;
  }

  .eventSwipeRow {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
  }

  .swipeHint {
    grid-column: 1 / -1;
    order: -1;
    text-align: left;
  }
}
</style>
