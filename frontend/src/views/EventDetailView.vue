<template>
  <main class="event-detail-view">
    <header class="detail-header">
      <router-link to="/events" class="back-link">&lt;- Spat na udalosti</router-link>
      <p v-if="deck.length" class="deck-count">{{ Math.min(activeIndex + 1, deck.length) }} / {{ deck.length }}</p>
    </header>

    <div v-if="loading" class="state">Nacitavam detail...</div>
    <div v-else-if="error" class="state error">{{ error }}</div>

    <section v-else class="detail-content">
      <div v-if="currentEvent" class="deck-shell">
        <div v-if="thirdCard" class="stack-card stack-3" aria-hidden="true">
          <div class="stack-preview">
            <p class="stack-title">{{ thirdCard.title }}</p>
            <p class="stack-time">{{ formatDateRange(thirdCard) }}</p>
          </div>
        </div>

        <div v-if="secondCard" class="stack-card stack-2" aria-hidden="true">
          <div class="stack-preview">
            <p class="stack-title">{{ secondCard.title }}</p>
            <p class="stack-time">{{ formatDateRange(secondCard) }}</p>
          </div>
        </div>

        <div
          class="stack-card stack-active"
          :style="cardStyle"
          @pointerdown="onPointerDown"
          @pointermove="onPointerMove"
          @pointerup="onPointerUp"
          @pointercancel="onPointerUp"
        >
          <div v-if="badge" class="swipe-badge" :class="badgeClass">{{ badge }}</div>

          <EventCard
            :event="currentEvent"
            :formatted-time="formattedTime"
            :visibility-icon="visibilityIcon"
            :bio-expanded="bioExpanded"
            @toggle-bio="toggleBio"
            @open-sheet="openSheet"
          />
        </div>
      </div>

      <div v-else class="state">Ziadne dalsie udalosti.</div>

      <EventActions
        class="actions"
        :disabled="loading || !currentEvent"
        @dismiss="dismissCurrent"
        @favorite="favoriteCurrent"
        @calendar="addToCalendar"
      />
    </section>

    <EventDetailSheet
      :open="sheetOpen"
      :event="currentEvent"
      :type-label="typeLabel"
      :format-date-time="formatDateTime"
      :visibility-text="visibilityText"
      :is-debug="isDebug"
      :auth-is-authed="auth.isAuthed"
      :notify-email="notifyEmail"
      :notify-loading="notifyLoading"
      :notify-msg="notifyMsg"
      :notify-err="notifyErr"
      @close="closeSheet"
      @send-notify="sendEmailAlert"
      @update:notify-email="notifyEmail = $event"
    />
  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import EventCard from '@/components/events/EventCard.vue'
import EventActions from '@/components/events/EventActions.vue'
import EventDetailSheet from '@/components/events/EventDetailSheet.vue'
import { useSwipeCard } from '@/composables/useSwipeCard'
import { useFavoritesStore } from '@/stores/favorites'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const favorites = useFavoritesStore()
const auth = useAuthStore()

const event = ref(null)
const deck = ref([])
const activeIndex = ref(0)
const loading = ref(true)
const error = ref('')
const bioExpanded = ref(false)
const sheetOpen = ref(false)
const notifyEmail = ref('')
const notifyLoading = ref(false)
const notifyMsg = ref('')
const notifyErr = ref('')

const eventId = computed(() => Number(route.params.id))
const isDebug = computed(() => route.query.debug === '1')
const currentEvent = computed(() => deck.value[activeIndex.value] || null)
const secondCard = computed(() => deck.value[activeIndex.value + 1] || null)
const thirdCard = computed(() => deck.value[activeIndex.value + 2] || null)
const formattedTime = computed(() => formatDateRange(currentEvent.value))

const visibilityMeta = computed(() => mapVisibility(currentEvent.value))
const visibilityIcon = computed(() => visibilityMeta.value.icon)
const visibilityText = computed(() => visibilityMeta.value.text)

const {
  badge,
  cardStyle,
  onPointerDown,
  onPointerMove,
  onPointerUp,
} = useSwipeCard({
  threshold: 110,
  onLeft: async () => {
    dismissCurrent()
  },
  onRight: async () => {
    await favoriteCurrent()
  },
  onUp: async () => {
    bioExpanded.value = true
    sheetOpen.value = true
  },
})

const badgeClass = computed(() => {
  if (badge.value === 'STAR') return 'badge-star'
  if (badge.value === 'IGNORE') return 'badge-ignore'
  return 'badge-detail'
})

function advanceDeck() {
  bioExpanded.value = false
  if (activeIndex.value < deck.value.length - 1) {
    activeIndex.value += 1
    return
  }

  activeIndex.value = deck.value.length
}

function dismissCurrent() {
  if (!currentEvent.value) return
  advanceDeck()
}

async function favoriteCurrent() {
  if (!currentEvent.value) return
  await toggleFavorite(currentEvent.value.id)
  advanceDeck()
}

function toggleBio() {
  bioExpanded.value = !bioExpanded.value
}

function openSheet() {
  bioExpanded.value = true
  sheetOpen.value = true
}

function closeSheet() {
  sheetOpen.value = false
}

async function fetchEventDetail() {
  const res = await api.get(`/events/${eventId.value}`)
  return res.data?.data ?? res.data
}

async function fetchDeckCandidates(primaryEvent) {
  const related =
    (Array.isArray(primaryEvent?.related_events) && primaryEvent.related_events) ||
    (Array.isArray(primaryEvent?.related) && primaryEvent.related) ||
    (Array.isArray(primaryEvent?.next_events) && primaryEvent.next_events) ||
    (Array.isArray(primaryEvent?.recommendations) && primaryEvent.recommendations) ||
    []

  if (related.length) return related

  const res = await api.get('/events', { params: { limit: 10 } })
  const rows = Array.isArray(res.data?.data) ? res.data.data : res.data
  return Array.isArray(rows) ? rows : []
}

function buildDeck(primaryEvent, candidates) {
  const known = new Set([Number(primaryEvent?.id)])
  const items = [primaryEvent]

  candidates.forEach((candidate) => {
    const id = Number(candidate?.id)
    if (!Number.isFinite(id) || known.has(id)) return
    known.add(id)
    items.push(candidate)
  })

  return items.slice(0, 10)
}

async function loadEventAndDeck() {
  loading.value = true
  error.value = ''
  sheetOpen.value = false

  try {
    const primaryEvent = await fetchEventDetail()
    event.value = primaryEvent

    const candidates = await fetchDeckCandidates(primaryEvent)
    deck.value = buildDeck(primaryEvent, candidates)
    activeIndex.value = 0
    bioExpanded.value = false
  } catch (err) {
    error.value = err?.response?.data?.message || err?.message || 'Nepodarilo sa nacitat detail.'
  } finally {
    loading.value = false
  }
}

async function toggleFavorite(id) {
  return favorites.toggle(id)
}

async function sendEmailAlert() {
  if (auth.isAuthed || !currentEvent.value?.id) return

  const email = String(notifyEmail.value || '').trim()
  if (!email) {
    notifyErr.value = 'Zadaj email.'
    return
  }

  notifyLoading.value = true
  notifyErr.value = ''
  notifyMsg.value = ''

  try {
    await api.post(`/events/${currentEvent.value.id}/notify-email`, { email })
    notifyMsg.value = 'Hotovo. Upozornenie bolo ulozene.'
    notifyEmail.value = ''
  } catch (err) {
    notifyErr.value = err?.response?.data?.message || 'Nepodarilo sa ulozit upozornenie.'
  } finally {
    notifyLoading.value = false
  }
}

function typeLabel(type) {
  const map = {
    meteor_shower: 'Meteory',
    eclipse_lunar: 'Zatmenie (L)',
    eclipse_solar: 'Zatmenie (S)',
    planetary_event: 'Konjunkcia',
    other: 'Ine',
  }
  return map[type] || type || '—'
}

function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatDateRange(item) {
  if (!item) return '—'

  const startRaw = item.start_at || item.starts_at || item.max_at
  const endRaw = item.end_at || item.ends_at

  const start = startRaw ? new Date(startRaw) : null
  const end = endRaw ? new Date(endRaw) : null

  if (!start || Number.isNaN(start.getTime())) return '—'

  const time = start.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
  const startDay = start.getDate()
  const startMonth = start.getMonth() + 1
  const startYear = start.getFullYear()

  if (!end || Number.isNaN(end.getTime())) {
    return `${startDay}. ${startMonth}. ${startYear} \u00b7 ${time}`
  }

  const endDay = end.getDate()
  const endMonth = end.getMonth() + 1
  const endYear = end.getFullYear()

  if (startYear === endYear && startMonth === endMonth) {
    if (startDay === endDay) return `${startDay}. ${startMonth}. ${startYear} \u00b7 ${time}`
    return `${startDay}.\u2013${endDay}. ${startMonth}. ${startYear} \u00b7 ${time}`
  }

  return `${startDay}. ${startMonth}. ${startYear} - ${endDay}. ${endMonth}. ${endYear} \u00b7 ${time}`
}

function mapVisibility(item) {
  const raw =
    item?.visibilityLevelSK ??
    item?.visibility_level_sk ??
    item?.visibility_sk ??
    item?.visibility

  if (typeof raw === 'boolean') {
    return raw
      ? { icon: '\u2714', text: 'Viditelne zo Slovenska' }
      : { icon: '\u2716', text: 'Neviditelne zo Slovenska' }
  }

  if (typeof raw === 'number') {
    if (raw <= 0) return { icon: '\u2716', text: 'Neviditelne zo Slovenska' }
    if (raw === 1) return { icon: '\u2714', text: 'Viditelne zo Slovenska' }
    return { icon: '\u25d1', text: 'Ciastocne viditelne zo Slovenska' }
  }

  if (typeof raw === 'string') {
    const value = raw.trim().toLowerCase()
    if (['0', 'none', 'hidden', 'not_visible', 'not visible', 'no'].includes(value) || value.includes('nevid')) {
      return { icon: '\u2716', text: 'Neviditelne zo Slovenska' }
    }
    if (value.includes('partial') || value.includes('castoc') || value.includes('partly')) {
      return { icon: '\u25d1', text: 'Ciastocne viditelne zo Slovenska' }
    }
    if (['1', 'visible', 'public', 'yes', 'full'].includes(value) || value.includes('viditel')) {
      return { icon: '\u2714', text: 'Viditelne zo Slovenska' }
    }
  }

  return { icon: '\u25d1', text: 'Viditelnost zo Slovenska nie je upresnena' }
}

function addToCalendar() {
  const selected = currentEvent.value
  if (!selected) return

  const date = selected.start_at || selected.starts_at || selected.max_at || selected.end_at || selected.ends_at
  const ymd = toYMD(date)

  router.push({
    name: 'calendar',
    query: ymd ? { date: ymd } : {},
  })
}

function toYMD(value) {
  if (!value) return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

onMounted(async () => {
  await Promise.all([loadEventAndDeck(), favorites.fetch()])
})

watch(
  () => route.params.id,
  async () => {
    await Promise.all([loadEventAndDeck(), favorites.fetch()])
  }
)
</script>

<style scoped>
.event-detail-view {
  min-height: calc(100vh - 3rem);
  width: min(100%, 460px);
  margin: 0 auto;
  padding: 0.8rem 0.8rem 1.1rem;
}

.detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  margin-bottom: 0.7rem;
}

.back-link {
  color: var(--color-primary);
  font-size: 0.88rem;
  text-decoration: none;
}

.back-link:hover {
  text-decoration: underline;
}

.deck-count {
  color: rgb(var(--color-surface-rgb) / 0.66);
  font-size: 0.8rem;
}

.detail-content {
  display: grid;
  gap: 1rem;
}

.deck-shell {
  height: min(72vh, 620px);
  position: relative;
}

.stack-card {
  position: absolute;
  inset: 0;
  border-radius: 1.4rem;
}

.stack-2 {
  transform: translateY(10px) scale(0.97);
  opacity: 0.6;
  background: rgb(var(--color-bg-rgb) / 0.58);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.stack-3 {
  transform: translateY(18px) scale(0.94);
  opacity: 0.35;
  background: rgb(var(--color-bg-rgb) / 0.4);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
}

.stack-preview {
  padding: 1rem;
}

.stack-title {
  color: rgb(var(--color-surface-rgb) / 0.75);
  font-weight: 600;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.stack-time {
  margin-top: 0.35rem;
  color: rgb(var(--color-surface-rgb) / 0.56);
  font-size: 0.82rem;
}

.stack-active {
  z-index: 8;
  touch-action: none;
  user-select: none;
}

.swipe-badge {
  position: absolute;
  top: 0.8rem;
  left: 0.8rem;
  z-index: 10;
  border-radius: 999px;
  padding: 0.28rem 0.62rem;
  font-size: 0.73rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  border: 1px solid transparent;
}

.badge-star {
  color: #9ad4ff;
  background: rgb(41 88 166 / 0.42);
  border-color: rgb(112 181 255 / 0.45);
}

.badge-ignore {
  color: #ff9ba9;
  background: rgb(149 35 51 / 0.38);
  border-color: rgb(255 116 139 / 0.45);
}

.badge-detail {
  color: #d9dee9;
  background: rgb(var(--color-bg-rgb) / 0.58);
  border-color: rgb(var(--color-text-secondary-rgb) / 0.4);
}

.actions {
  margin-top: 0.4rem;
}

.state {
  display: grid;
  place-items: center;
  min-height: 220px;
  color: rgb(var(--color-surface-rgb) / 0.75);
}

.state.error {
  color: var(--color-danger);
}
</style>

