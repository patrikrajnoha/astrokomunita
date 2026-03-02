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
      <p>{{ error }}</p>
      <button type="button" class="errorButton" @click="loadEvent">Skusit znova</button>
    </section>

    <section v-else-if="event" class="eventDetailShell">
      <article class="eventCard">
        <div class="eventCard__glow" aria-hidden="true"></div>

        <div class="eventCard__content">
          <div class="eventChipRow">
            <span class="eventChip eventChip--status">{{ statusLabel }}</span>
            <span class="eventChip">{{ typeLabel }}</span>
            <span v-if="confidenceLabel" class="eventChip">{{ confidenceLabel }}</span>
          </div>

          <div class="eventHeading">
            <h1 class="eventTitle">{{ title }}</h1>
            <p class="eventMeta">{{ metaLine }}</p>
          </div>

          <section class="eventTimeBlock" aria-label="Cas udalosti">
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

          <div class="eventDescriptionBlock">
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
          </div>

          <EventViewingWindowForecast
            :event="event"
            :user-location="resolvedLocation"
            @state="handleViewingForecastState"
          />

          <div class="eventCtaRow">
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
              class="iconButton"
              title="Pozvat"
              aria-label="Pozvat"
              @click="handleInvite"
            >
              +
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
        </div>
      </article>
    </section>

    <InviteTicketModal :open="inviteModalOpen" :event="event" @close="inviteModalOpen = false" />
  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import InviteTicketModal from '@/components/events/InviteTicketModal.vue'
import EventViewingWindowForecast from '@/components/events/EventViewingWindowForecast.vue'
import { useToast } from '@/composables/useToast'
import api from '@/services/api'
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
const viewingForecast = ref(createInitialViewingState())

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
  [metaDateLabel.value, visibilityLabel.value].filter((value) => value !== '').join(' · '),
)
const isFollowed = computed(() => eventFollows.isFollowed(eventId.value))
const followLoading = computed(() => eventFollows.isLoading(eventId.value))
const followButtonLabel = computed(() => {
  if (!auth.isAuthed) return 'Prihlasit sa pre sledovanie'
  return isFollowed.value ? 'Sledujes' : 'Sledovat'
})
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

  try {
    const res = await api.get(`/events/${eventId.value}`)
    event.value = res?.data?.data ?? res?.data ?? null

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
  loadEvent()
})

watch(
  () => route.params.id,
  () => {
    loadEvent()
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
.errorButton,
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
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  background:
    linear-gradient(180deg, rgb(21 29 40 / 0.98), rgb(14 20 30 / 0.98)),
    rgb(21 29 40 / 0.98);
  box-shadow: 0 32px 70px rgb(0 0 0 / 0.22);
}

.eventCard__glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top left, rgb(var(--color-primary-rgb) / 0.2), transparent 40%),
    radial-gradient(circle at 85% 10%, rgb(255 255 255 / 0.06), transparent 24%);
  pointer-events: none;
}

.eventCard__content,
.loadingCard {
  position: relative;
  z-index: 1;
  display: grid;
  gap: 1.25rem;
  padding: 1.15rem;
}

.eventChipRow {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.eventChip {
  display: inline-flex;
  align-items: center;
  min-height: 1.7rem;
  padding: 0 0.72rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background: rgb(255 255 255 / 0.05);
  color: rgb(255 255 255 / 0.72);
  font-size: 0.73rem;
  font-weight: 700;
}

.eventChip--status {
  border-color: rgb(var(--color-primary-rgb) / 0.28);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: rgb(244 248 255 / 0.95);
}

.eventHeading {
  display: grid;
  gap: 0.45rem;
}

.eventTitle {
  margin: 0;
  color: rgb(255 255 255 / 0.98);
  font-size: clamp(2rem, 8vw, 3.05rem);
  line-height: 0.98;
  font-weight: 650;
  letter-spacing: -0.045em;
}

.eventMeta {
  margin: 0;
  color: rgb(255 255 255 / 0.56);
  font-size: 0.9rem;
  line-height: 1.5;
}

.eventTimeBlock {
  display: grid;
  gap: 0.4rem;
  padding: 0.95rem 0;
  border-top: 1px solid rgb(255 255 255 / 0.08);
  border-bottom: 1px solid rgb(255 255 255 / 0.08);
}

.eventTimeBlock__primary {
  margin: 0;
  color: rgb(250 252 255 / 0.98);
  font-size: clamp(1.15rem, 4.8vw, 1.45rem);
  line-height: 1.25;
  font-weight: 650;
  letter-spacing: -0.02em;
}

.eventTimeBlock__secondary,
.eventTimeBlock__microcopy {
  margin: 0;
  color: rgb(255 255 255 / 0.58);
  font-size: 0.92rem;
  line-height: 1.5;
}

.eventTimeBlock__microcopy {
  color: rgb(220 229 242 / 0.7);
}

.eventTimeBlock__timezone {
  color: rgb(255 255 255 / 0.44);
}

.locationButton {
  width: fit-content;
  min-height: 2.3rem;
  margin-top: 0.1rem;
}

.eventDescriptionBlock {
  display: grid;
  gap: 0.7rem;
  max-width: 43.75rem;
}

.eventDescription {
  margin: 0;
  max-width: 43.75rem;
  color: rgb(255 255 255 / 0.86);
  font-size: 1.05rem;
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
  color: rgb(214 231 255 / 0.9);
  font-size: 0.88rem;
  font-weight: 600;
  padding: 0;
}

.eventCtaRow {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

.followButton {
  flex: 1 1 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 3.1rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.34);
  background: linear-gradient(
    180deg,
    rgb(var(--color-primary-rgb) / 0.24),
    rgb(var(--color-primary-rgb) / 0.14)
  );
  color: rgb(247 250 255 / 0.98);
  font-size: 0.98rem;
  font-weight: 650;
  letter-spacing: -0.01em;
  padding: 0 1rem;
  box-shadow: 0 18px 32px rgb(var(--color-primary-rgb) / 0.14);
}

.followButton__icon {
  line-height: 1;
}

.followButton:disabled,
.iconButton:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.iconButton {
  flex: 0 0 auto;
  width: 3rem;
  height: 3rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(255 255 255 / 0.05);
  color: rgb(255 255 255 / 0.92);
  font-size: 1.15rem;
  font-weight: 700;
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
  background: rgb(255 255 255 / 0.05);
  color: rgb(255 255 255 / 0.92);
  padding: 0;
}

.menuRoot :deep(.dropdownTrigger:hover) {
  background: rgb(255 255 255 / 0.08);
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

  .eventCtaRow {
    gap: 0.6rem;
  }

  .followButton,
  .iconButton,
  .menuRoot :deep(.dropdownTrigger) {
    min-height: 2.9rem;
    height: 2.9rem;
  }
}
</style>
