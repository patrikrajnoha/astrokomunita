<template>
  <transition name="onbFade" appear>
    <div class="fixed inset-0 z-[1300] flex items-center justify-center p-4" style="background:rgba(1,6,15,0.72);backdrop-filter:blur(12px) saturate(180%)">
      <div
        ref="modalRef"
        class="onbCard w-full max-w-[860px] bg-app rounded-2xl overflow-hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="onboarding-title"
      >
        <!-- Header -->
        <div class="px-6 pt-6 pb-0">
          <!-- Step dots -->
          <div class="flex items-center gap-1.5 mb-4">
            <span
              v-for="n in 2"
              :key="n"
              class="h-1.5 rounded-full transition-all duration-300"
              :class="n === step ? 'w-5 bg-vivid' : 'w-1.5 bg-muted/25'"
            ></span>
            <span class="ml-1.5 text-muted text-xs">{{ step }} / 2</span>
          </div>

          <h1 id="onboarding-title" class="text-white text-xl font-semibold leading-snug">
            Nastav si úvodný profil
          </h1>
          <p class="mt-1.5 text-muted text-sm">
            {{ step === 1 ? 'Vyber oblasti, ktoré ťa zaujímajú.' : 'Kde najčastejšie pozoruješ oblohu?' }}
          </p>
        </div>

        <!-- Body -->
        <div class="onbBody px-6 py-5">
          <!-- Step content -->
          <div class="onbStepWrap min-w-0">
            <transition name="onbStep" mode="out-in">
              <!-- Step 1: Interests -->
              <section v-if="step === 1" key="step-1">
                <p class="text-muted text-sm mb-4">Vyber si témy, ktoré chceš mať vo feede a vo widgetoch stále po ruke.</p>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="item in interestsCatalog"
                    :key="item.key"
                    type="button"
                    class="onbChip rounded-2xl px-4 py-2 text-sm font-medium transition-all"
                    :class="isSelected(item.key) ? 'bg-vivid text-white' : 'bg-hover text-muted hover:text-white'"
                    @click="toggleInterest(item.key)"
                  >
                    {{ item.label }}
                  </button>
                </div>
              </section>

              <!-- Step 2: Location -->
              <section v-else key="step-2">
                <p class="text-muted text-sm mb-4">Tvoju lokalitu použijeme pre počasie, seeing a ďalšie pozorovacie widgety.</p>
                <div class="relative">
                  <label class="block text-muted text-xs font-medium mb-1.5">Mesto alebo obec</label>
                  <input
                    v-model.trim="locationLabel"
                    class="onbInput w-full bg-hover rounded-xl px-4 py-3 text-white text-sm transition-shadow"
                    type="text"
                    placeholder="Napríklad Bratislava"
                    autocomplete="off"
                    @focus="openSuggestions = true"
                  />
                  <ul
                    v-if="openSuggestions && suggestions.length > 0"
                    class="absolute left-0 right-0 mt-1.5 bg-app rounded-xl overflow-hidden z-10 divide-y divide-white/5"
                    style="box-shadow:0 8px 24px rgba(0,0,0,0.4);"
                    role="listbox"
                  >
                    <li v-for="option in suggestions" :key="option.place_id">
                      <button
                        type="button"
                        class="w-full text-left px-4 py-2.5 hover:bg-hover transition-colors"
                        @click="selectLocation(option)"
                      >
                        <span class="block text-white text-sm">{{ option.label }}</span>
                        <span v-if="option.country || option.timezone" class="block text-muted text-xs mt-0.5">
                          {{ [option.country, option.timezone].filter(Boolean).join(' · ') }}
                        </span>
                      </button>
                    </li>
                  </ul>
                </div>
              </section>
            </transition>
          </div>

          <!-- Showcase -->
          <aside class="onbShowcase min-w-0 rounded-xl overflow-hidden" aria-label="Ukážka widgetov" style="background:rgba(15,115,255,0.05);border:1px solid rgba(15,115,255,0.12);">
            <div class="px-4 pt-4 pb-3">
              <p class="text-vivid text-[0.68rem] font-semibold uppercase tracking-widest mb-1">Widgety na mieru</p>
              <h2 class="text-white text-[0.95rem] font-semibold leading-snug">{{ showcaseContent.title }}</h2>
              <p class="text-muted text-xs leading-relaxed mt-1.5">{{ showcaseContent.body }}</p>
            </div>
            <OnboardingWidgetPreview class="mx-3 mb-3" />
          </aside>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-6 pb-6 gap-3">
          <button
            type="button"
            class="rounded-2xl bg-secondary-btn text-muted font-medium px-5 py-2.5 text-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed hover:bg-secondary-btn-hover active:scale-[0.97]"
            :disabled="loading"
            @click="emitSkip"
          >
            Preskočiť
          </button>
          <div class="flex items-center gap-2">
            <button
              v-if="step === 2"
              type="button"
              class="rounded-2xl bg-secondary-btn text-muted font-medium px-5 py-2.5 text-sm transition-all disabled:opacity-40 hover:bg-secondary-btn-hover active:scale-[0.97]"
              :disabled="loading"
              @click="step = 1"
            >
              Späť
            </button>
            <button
              v-if="step === 1"
              type="button"
              class="rounded-2xl bg-vivid text-white font-medium px-5 py-2.5 text-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed hover:bg-vivid-hover active:scale-[0.97]"
              :disabled="loading || selectedInterests.length === 0"
              @click="step = 2"
            >
              Ďalej
            </button>
            <button
              v-else
              type="button"
              class="rounded-2xl bg-vivid text-white font-medium px-5 py-2.5 text-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed hover:bg-vivid-hover active:scale-[0.97]"
              :disabled="loading"
              @click="emitFinish"
            >
              {{ loading ? 'Ukladám…' : 'Dokončiť' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { searchOnboardingLocations } from '@/services/events'
import OnboardingWidgetPreview from '@/components/onboarding/OnboardingWidgetPreview.vue'

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  interestsCatalog: {
    type: Array,
    default: () => [],
  },
  initialInterests: {
    type: Array,
    default: () => [],
  },
  initialLocation: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['finish', 'skip'])

const step = ref(1)
const modalRef = ref(null)
const selectedInterests = ref(Array.isArray(props.initialInterests) ? [...props.initialInterests] : [])
const locationLabel = ref(String(props.initialLocation?.location_label || ''))
const locationPlaceId = ref(props.initialLocation?.location_place_id || null)
const locationLat = ref(props.initialLocation?.location_lat ?? null)
const locationLon = ref(props.initialLocation?.location_lon ?? null)
const suggestions = ref([])
const openSuggestions = ref(false)
const suppressLocationFieldWatch = ref(false)
let debounceTimer = null
let locationRequestId = 0

const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
const showcaseContent = computed(() => {
  if (step.value === 2) {
    return {
      title: 'Lokalita spraví widgety skutočne užitočné.',
      body: 'Po uložení budeš mať v pravom paneli alebo v mobile presnejšie podmienky pre tvoje miesto.',
    }
  }

  return {
    title: 'Záujmy menia to, čo ti aplikácia prioritne ukazuje.',
    body: 'Feed aj sidebar widgety sa pri prvom použití vedia lepšie trafiť do toho, čo chceš sledovať.',
  }
})

function isSelected(key) {
  return selectedInterests.value.includes(key)
}

function toggleInterest(key) {
  if (isSelected(key)) {
    selectedInterests.value = selectedInterests.value.filter((item) => item !== key)
    return
  }

  selectedInterests.value = [...selectedInterests.value, key]
}

function selectLocation(option) {
  suppressLocationFieldWatch.value = true
  locationLabel.value = option.label
  locationPlaceId.value = option.place_id
  locationLat.value = option.lat
  locationLon.value = option.lon
  suggestions.value = []
  openSuggestions.value = false
  Promise.resolve().then(() => {
    suppressLocationFieldWatch.value = false
  })
}

function emitFinish() {
  emit('finish', {
    interests: [...selectedInterests.value],
    location_label: locationLabel.value || '',
    location_place_id: locationPlaceId.value || null,
    location_lat: locationLat.value,
    location_lon: locationLon.value,
  })
}

function emitSkip() {
  emit('skip')
}

function handleKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    emitSkip()
    return
  }

  if (event.key !== 'Tab' || !modalRef.value) return

  const nodes = Array.from(modalRef.value.querySelectorAll(focusableSelector))
    .filter((el) => !el.hasAttribute('disabled'))

  if (nodes.length === 0) return

  const first = nodes[0]
  const last = nodes[nodes.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

watch(locationLabel, (nextValue) => {
  if (suppressLocationFieldWatch.value) return

  locationPlaceId.value = null
  locationLat.value = null
  locationLon.value = null

  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  if (step.value !== 2) return

  const query = String(nextValue || '').trim()
  if (query.length < 2) {
    suggestions.value = []
    return
  }

  debounceTimer = setTimeout(async () => {
    const requestId = ++locationRequestId
    try {
      const response = await searchOnboardingLocations(query, 8)
      if (requestId !== locationRequestId) return
      suggestions.value = Array.isArray(response?.data?.data) ? response.data.data : []
      openSuggestions.value = true
    } catch {
      if (requestId !== locationRequestId) return
      suggestions.value = []
    }
  }, 300)
})

watch(step, async () => {
  await nextTick()
  const firstFocusable = modalRef.value?.querySelector(focusableSelector)
  firstFocusable?.focus()
})

const locationSummary = computed(() => locationLabel.value.trim())

watch(locationSummary, (value) => {
  if (!value) {
    openSuggestions.value = false
  }
})

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown)
  await nextTick()
  const firstFocusable = modalRef.value?.querySelector(focusableSelector)
  firstFocusable?.focus()
})

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
/* Modal entrance */
.onbFade-enter-active {
  transition: opacity 220ms ease;
}
.onbFade-leave-active {
  transition: opacity 180ms ease;
}
.onbFade-enter-from,
.onbFade-leave-to {
  opacity: 0;
}
.onbFade-enter-active .onbCard {
  animation: onbCardIn 300ms cubic-bezier(0.34, 1.4, 0.64, 1) both;
}
.onbFade-leave-active .onbCard {
  animation: onbCardOut 180ms ease forwards;
}

@keyframes onbCardIn {
  from {
    transform: scale(0.93) translateY(16px);
    opacity: 0;
  }
  to {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}
@keyframes onbCardOut {
  to {
    transform: scale(0.96) translateY(8px);
    opacity: 0;
  }
}

/* Step transition */
.onbStep-enter-active,
.onbStep-leave-active {
  transition: opacity 160ms ease, transform 160ms ease;
}
.onbStep-enter-from {
  opacity: 0;
  transform: translateX(12px);
}
.onbStep-leave-to {
  opacity: 0;
  transform: translateX(-12px);
}

/* Layout */
.onbBody {
  display: grid;
  grid-template-columns: 1fr minmax(0, 260px);
  gap: 1.25rem;
  align-items: start;
}

/* Input */
.onbInput {
  border: none;
  outline: none;
}
.onbInput::placeholder {
  color: rgba(171, 184, 201, 0.35);
}
.onbInput:focus {
  box-shadow: 0 0 0 2px rgba(15, 115, 255, 0.35);
}

/* Chip */
.onbChip {
  border: none;
  cursor: pointer;
}

/* Mobile */
@media (max-width: 640px) {
  .onbBody {
    grid-template-columns: 1fr;
  }

  .onbShowcase {
    display: none;
  }
}
</style>
