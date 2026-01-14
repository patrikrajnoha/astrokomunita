<template>
  <main class="min-h-screen bg-black text-white">
    <!-- deck -->
    <div class="mx-auto flex min-h-screen max-w-xl items-center justify-center px-4 py-10">
      <div class="relative h-[70vh] w-full max-w-[420px]">
        <div v-if="loading" class="grid h-full place-items-center text-white/70">
          Načítavam...
        </div>

        <div v-else-if="error" class="grid h-full place-items-center text-red-400">
          {{ error }}
        </div>

        <div v-else>
          <!-- next preview -->
          <div
            v-if="nextEvent"
            class="absolute inset-0 rounded-3xl border border-white/10 bg-white/5 shadow-2xl backdrop-blur
                   translate-y-3 scale-[0.97] opacity-70"
          >
            <div class="p-6">
              <div class="text-sm text-white/60">Ďalšia udalosť</div>
              <div class="mt-2 text-2xl font-bold leading-snug">
                {{ nextEvent.title }}
              </div>
              <div
                class="mt-3 inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/30 px-3 py-1 text-xs text-white/70"
              >
                {{ nextEvent.type }}
              </div>
            </div>
          </div>

          <!-- top card (swipe-enabled) -->
          <div
            v-if="currentEvent"
            class="absolute inset-0 rounded-3xl border border-white/10 bg-white/5 shadow-2xl backdrop-blur
                   select-none touch-none"
            :style="cardStyle"
            @pointerdown="onDown"
            @pointermove="onMove"
            @pointerup="onUp"
            @pointercancel="onUp"
          >
            <!-- swipe badge -->
            <div
              v-if="badge"
              class="absolute left-5 top-5 z-10 rounded-full px-3 py-1 text-xs font-semibold ring-1"
              :class="badgeClass"
            >
              {{ badge }}
            </div>

            <div class="flex h-full flex-col p-6">
              <!-- top meta -->
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-xs text-white/60">Astronomická udalosť</div>
                  <h2 class="mt-2 truncate text-3xl font-extrabold leading-tight">
                    {{ currentEvent.title }}
                  </h2>
                </div>

                <!-- type badge -->
                <span
                  class="shrink-0 rounded-full bg-[#1d9bf0]/20 px-3 py-1 text-xs font-semibold text-[#1d9bf0] ring-1 ring-[#1d9bf0]/30"
                >
                  {{ currentEvent.type }}
                </span>
              </div>

              <!-- date -->
              <div class="mt-4 text-sm text-white/70">
                <span class="text-white/50">Max.:</span>
                {{ formatDate(currentEvent.max_at) }}
              </div>

              <!-- description -->
              <p class="mt-5 text-base leading-relaxed text-white/85">
                {{ currentEvent.short || 'Bez krátkeho popisu.' }}
              </p>

              <div class="flex-1"></div>

              <!-- actions -->
              <div class="mt-6 flex items-center justify-center gap-4">
                <!-- UNDO -->
                <button
                  class="grid h-14 w-14 place-items-center rounded-full bg-white/5 ring-1 ring-white/10
                         hover:bg-white/10 active:scale-95 transition disabled:opacity-40"
                  @click="undo"
                  :disabled="history.length === 0 || index === 0"
                  aria-label="Späť"
                  title="Späť"
                >
                  ↩️
                </button>

                <button
                  class="grid h-14 w-14 place-items-center rounded-full bg-white/5 ring-1 ring-white/10
                         hover:bg-white/10 active:scale-95 transition"
                  @click="skip"
                  aria-label="Preskočiť"
                >
                  ❌
                </button>

                <button
                  class="grid h-14 w-14 place-items-center rounded-full bg-white/5 ring-1 ring-white/10
                         hover:bg-white/10 active:scale-95 transition"
                  @click="openDetail"
                  aria-label="Detail"
                >
                  ℹ️
                </button>

                <button
                  class="grid h-14 w-14 place-items-center rounded-full bg-[#1d9bf0]/20 ring-1 ring-[#1d9bf0]/30
                         hover:bg-[#1d9bf0]/30 active:scale-95 transition"
                  @click="like"
                  aria-label="Uložiť do obľúbených"
                >
                  ⭐
                </button>
              </div>

              <div class="mt-4 text-center text-xs text-white/50">
                {{ index + 1 }} / {{ events.length }}
              </div>
            </div>
          </div>

          <!-- empty -->
          <div v-else class="grid h-[70vh] place-items-center text-white/70">
            Žiadne ďalšie udalosti.
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useFavoritesStore } from '@/stores/favorites'

const router = useRouter()
const favorites = useFavoritesStore()

const events = ref([])
const loading = ref(false)
const error = ref('')

const index = ref(0)
const currentEvent = computed(() => events.value[index.value] || null)
const nextEvent = computed(() => events.value[index.value + 1] || null)

function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleString()
}

/** Undo history */
const history = ref([]) // { eventId, action: 'like'|'skip' }

/** Swipe state */
const dragging = ref(false)
const dx = ref(0)
const dy = ref(0)
const animating = ref(false)

const badge = computed(() => {
  if (!dragging.value) return ''
  if (dx.value > 70) return 'ULOŽIŤ ⭐'
  if (dx.value < -70) return 'PRESKOČIŤ ❌'
  if (dy.value < -70) return 'DETAIL ℹ️'
  return ''
})

const badgeClass = computed(() => {
  if (dx.value > 70) return 'bg-[#1d9bf0]/20 text-[#1d9bf0] ring-[#1d9bf0]/30'
  if (dx.value < -70) return 'bg-red-500/20 text-red-300 ring-red-400/30'
  if (dy.value < -70) return 'bg-white/10 text-white ring-white/20'
  return 'bg-white/10 text-white ring-white/20'
})

const cardStyle = computed(() => {
  const rotate = dx.value / 18
  const transition = animating.value ? 'transform 220ms ease' : dragging.value ? 'none' : 'transform 180ms ease'
  return {
    transform: `translate(${dx.value}px, ${dy.value}px) rotate(${rotate}deg)`,
    transition,
  }
})

let startX = 0
let startY = 0
let pointerId = null

function onDown(e) {
  if (animating.value) return
  dragging.value = true
  pointerId = e.pointerId
  startX = e.clientX
  startY = e.clientY
  e.currentTarget.setPointerCapture?.(pointerId)
}

function onMove(e) {
  if (!dragging.value || animating.value) return
  dx.value = e.clientX - startX
  dy.value = e.clientY - startY
}

function resetCard() {
  dx.value = 0
  dy.value = 0
  dragging.value = false
}

function nextWith(action) {
  const ev = currentEvent.value
  if (ev) history.value.push({ eventId: ev.id, action })
  index.value += 1
}

async function like() {
  if (!currentEvent.value) return
  await favorites.add(currentEvent.value.id)
  nextWith('like')
}

function skip() {
  nextWith('skip')
}

function openDetail() {
  if (!currentEvent.value) return
  router.push({ name: 'event-detail', params: { id: currentEvent.value.id } })
}

async function undo() {
  if (animating.value) return
  if (history.value.length === 0) return
  if (index.value === 0) return

  const last = history.value.pop()

  // vráť index späť
  index.value -= 1

  // ak bol posledný krok like, rollbackni favorites
  if (last?.action === 'like') {
    await favorites.remove(last.eventId)
  }

  // reset transformu karty
  dx.value = 0
  dy.value = 0
}

function flyOut(toX, toY, action) {
  animating.value = true
  dx.value = toX
  dy.value = toY

  window.setTimeout(async () => {
    if (action === 'like') {
      if (currentEvent.value) await favorites.add(currentEvent.value.id)
      nextWith('like')
    } else if (action === 'skip') {
      nextWith('skip')
    } else if (action === 'detail') {
      openDetail()
      // detail NEposúva index
    }

    animating.value = false
    resetCard()
  }, 230)
}

function onUp() {
  if (!dragging.value || animating.value) return
  dragging.value = false

  const thresholdX = 140
  const thresholdY = 140

  if (dx.value > thresholdX) {
    flyOut(600, dy.value, 'like')
    return
  }

  if (dx.value < -thresholdX) {
    flyOut(-600, dy.value, 'skip')
    return
  }

  if (dy.value < -thresholdY) {
    flyOut(dx.value, -600, 'detail')
    return
  }

  // vráť kartu späť
  animating.value = true
  dx.value = 0
  dy.value = 0
  window.setTimeout(() => {
    animating.value = false
  }, 190)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/events')
    events.value = Array.isArray(res.data) ? res.data : (res.data?.data ?? [])
    index.value = 0
    history.value = []
    await favorites.fetch()
  } catch {
    error.value = 'Nepodarilo sa načítať udalosti.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
