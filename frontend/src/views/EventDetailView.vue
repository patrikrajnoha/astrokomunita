<template src="./eventDetail/EventDetailView.template.html"></template>

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
  getHourInTimezone,
  resolveEventTimeContext,
} from '@/utils/eventTime'
import { eventDisplayDescription, eventDisplayTitle } from '@/utils/translatedFields'
import {
  formatDateKey,
  formatEventMetaDate,
  formatTime,
  mapConfidence,
  mapStatus,
  mapType,
  mapVisibility,
  normalizeEventsList,
  parseDate,
  parseDateTimeLocal,
  resolveAdjacentIds,
  resolveEventAnchorDate,
  resolvePhenomenonDate,
  resolveReminderPresetDate,
  resolveUserLocation,
  sanitizeLocationText,
  syncPlanFormFromEvent,
  toDateTimeLocal,
  toNullableString,
} from './eventDetail/eventDetailView.utils'
import { useEventDetailSwipeNavigation } from './eventDetail/useEventDetailSwipeNavigation'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const eventFollows = useEventFollowsStore()
const toast = useToast()

const event = ref(null)
const loading = ref(true)
const error = ref('')
const missingEvent = ref(false)
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
const secondaryEventTimeLabel = computed(() => {
  const context = eventTimeContext.value
  if (
    showViewingWindowMicrocopy.value &&
    context.timeType === 'peak' &&
    context.timeString
  ) {
    const labelPrefix =
      context.timePrecision === 'approximate'
        ? 'Priblizne maximum javu (cez den)'
        : 'Maximum javu (cez den)'
    return `${labelPrefix} o ${context.timeString}`
  }

  return context.message
})
const secondaryEventTimeTimezoneLabel = computed(() =>
  eventTimeContext.value.showTimezoneLabel ? eventTimeContext.value.timezoneLabelShort : '',
)
const secondaryEventTimeAriaLabel = computed(() => {
  if (!secondaryEventTimeLabel.value) {
    return ''
  }

  if (!eventTimeContext.value.showTimezoneLabel) {
    return secondaryEventTimeLabel.value
  }

  return `${secondaryEventTimeLabel.value} (${eventTimeContext.value.timezoneLabelShort}), cas v ${eventTimeContext.value.timezoneLabelLong}`
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
const viewingWindowMicrocopy = computed(() => {
  if (!showViewingWindowMicrocopy.value) return ''

  if (viewingWindowLabel.value) {
    return `Maximum je cez den; prakticke pozorovanie je az po zotmeni, v okne ${viewingWindowLabel.value}.`
  }

  return 'Maximum je cez den; prakticke pozorovanie je az po zotmeni.'
})
const howToObserveText = computed(() => {
  if (!showViewingWindowMicrocopy.value) return ''

  return 'Cas "Maximum" znamena astronomicky vrchol javu, nie vzdy najlepsi cas na pozorovanie. Ked je cez den, riad sa riadkom "Pozorovanie".'
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

const {
  handleWindowKeydown,
  onCardTouchCancel,
  onCardTouchEnd,
  onCardTouchMove,
  onCardTouchStart,
  resetSwipeGesture,
} = useEventDetailSwipeNavigation({
  adjacentEventIds,
  canGoNext,
  canGoPrev,
  canSwipe,
  eventId,
  router,
  swipeDx,
  swipeNavigating,
  swipeReleaseAnimating,
  swipeTouchActive,
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

function goToEvents() {
  router.push({ name: 'events' })
}

async function loadEvent() {
  if (!Number.isFinite(eventId.value)) {
    error.value = 'Neplatny identifikator udalosti.'
    missingEvent.value = false
    loading.value = false
    return
  }

  loading.value = true
  error.value = ''
  missingEvent.value = false
  event.value = null
  descriptionExpanded.value = false
  viewingForecast.value = createInitialViewingState()
  resetSwipeGesture()

  try {
    const res = await api.get(`/events/${eventId.value}`)
    event.value = res?.data?.data ?? res?.data ?? null
    syncPlanFormFromEvent(planForm, event.value)
    void loadAdjacentEvents(event.value)

    if (auth.isAuthed) {
      await eventFollows.syncFollowState(eventId.value)
    }
  } catch (requestError) {
    const statusCode = Number(requestError?.response?.status)
    if (statusCode === 404) {
      missingEvent.value = true
      error.value = ''
      adjacentEventIds.value = { prev: null, next: null }
      return
    }

    error.value =
      requestError?.response?.data?.message ||
      requestError?.userMessage ||
      'Nepodarilo sa nacitat detail udalosti.'
  } finally {
    loading.value = false
  }
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

  syncPlanFormFromEvent(planForm, event.value)
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

<style scoped src="./eventDetail/EventDetailView.css"></style>
