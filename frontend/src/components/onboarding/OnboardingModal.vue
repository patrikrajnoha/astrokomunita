<template>
  <transition name="onbFade" appear>
    <div class="onbOverlay">
      <div ref="modalRef" class="onbModal" role="dialog" aria-modal="true" aria-labelledby="onboarding-title">
        <header class="onbHeader">
          <p class="onbProgress">Krok {{ step }} z 2</p>
          <h1 id="onboarding-title">Nastav si uvodny profil</h1>
          <p class="onbLead">
            {{ step === 1 ? 'Vyber oblasti, ktore ta zaujimaju.' : 'Kde najcastejsie pozorujes oblohu?' }}
          </p>
        </header>

        <div class="onbBody">
          <transition name="onbStep" mode="out-in">
            <section v-if="step === 1" key="step-1" class="onbStepSection">
              <p class="onbSectionIntro">Vyber si temy, ktore chces mat vo feede a vo widgetoch stale po ruke.</p>
              <div class="chipWrap">
                <button
                  v-for="item in interestsCatalog"
                  :key="item.key"
                  type="button"
                  class="chip"
                  :class="{ active: isSelected(item.key) }"
                  @click="toggleInterest(item.key)"
                >
                  {{ item.label }}
                </button>
              </div>
            </section>

            <section v-else key="step-2" class="onbStepSection">
              <p class="onbSectionIntro">Tvoju lokalitu pouzijeme pre pocasie, seeing a dalsie pozorovacie widgety.</p>
              <label class="field">
                <span class="fieldLabel">Mesto alebo obec</span>
                <input
                  v-model.trim="locationLabel"
                  class="fieldInput"
                  type="text"
                  placeholder="Napriklad Bratislava"
                  autocomplete="off"
                  @focus="openSuggestions = true"
                />
              </label>

              <ul v-if="openSuggestions && suggestions.length > 0" class="suggestions" role="listbox">
                <li v-for="option in suggestions" :key="option.place_id">
                  <button type="button" class="suggestionItem" @click="selectLocation(option)">
                    <span class="suggestionTitle">{{ option.label }}</span>
                    <span v-if="option.country || option.timezone" class="suggestionMeta">
                      {{ [option.country, option.timezone].filter(Boolean).join(' / ') }}
                    </span>
                  </button>
                </li>
              </ul>
            </section>
          </transition>

          <aside class="onbShowcase" aria-label="Ukazka widgetov">
            <p class="onbShowcaseEyebrow">Widgety na mieru</p>
            <h2 class="onbShowcaseTitle">{{ showcaseContent.title }}</h2>
            <p class="onbShowcaseText">{{ showcaseContent.body }}</p>
            <OnboardingWidgetPreview class="onbShowcasePreview" />
            <div class="onbShowcasePoints">
              <span
                v-for="point in showcaseContent.points"
                :key="point"
                class="onbShowcasePoint"
              >
                {{ point }}
              </span>
            </div>
          </aside>
        </div>

        <footer class="onbActions">
          <button type="button" class="btnGhost" :disabled="loading" @click="emitSkip">Preskocit</button>
          <div class="rightActions">
            <button v-if="step === 2" type="button" class="btnGhost" :disabled="loading" @click="step = 1">Spat</button>
            <button
              v-if="step === 1"
              type="button"
              class="btnPrimary"
              :disabled="loading || selectedInterests.length === 0"
              @click="step = 2"
            >
              Ďalej
            </button>
            <button v-else type="button" class="btnPrimary" :disabled="loading" @click="emitFinish">
              {{ loading ? 'Ukladám...' : 'Dokončiť' }}
            </button>
          </div>
        </footer>
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
      title: 'Lokalita spravi widgety skutocne uzitocne.',
      body: 'Po ulozeni budes mat v pravom paneli alebo v mobile presnejsie podmienky pre tvoje miesto.',
      points: [
        'pocasie a seeing podla lokality',
        'mesiac, ISS a ukazy rychlo po ruke',
        'všetko vieš neskôr zmeniť v nastaveniach',
      ],
    }
  }

  return {
    title: 'Zaujmy menia to, co ti aplikacia prioritne ukazuje.',
    body: 'Feed aj sidebar widgety sa pri prvom pouziti vedia lepsie trafit do toho, co chces sledovat.',
    points: [
      'relevantnejsi feed od prveho otvorenia',
      'widgety pre ukazy, mesiac a oblohu',
      'bez zbytocneho nastavovania navyse',
    ],
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
.onbOverlay {
  position: fixed;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(var(--color-bg-rgb) / 0.55);
  backdrop-filter: blur(4px);
}

.onbModal {
  width: min(100%, 860px);
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background:
    radial-gradient(700px 240px at 0% 0%, rgb(var(--color-primary-rgb) / 0.12), transparent 65%),
    rgb(var(--color-bg-rgb));
  box-shadow: 0 24px 70px rgb(var(--color-bg-rgb) / 0.45);
  padding: 1.1rem;
  display: grid;
  gap: 1rem;
  transition: transform 180ms ease;
}

.onbHeader h1 {
  margin: 0.2rem 0 0;
  font-size: clamp(1.25rem, 2.8vw, 1.6rem);
}

.onbProgress {
  margin: 0;
  font-size: 0.82rem;
  color: var(--color-primary);
  font-weight: 700;
}

.onbLead {
  margin: 0.4rem 0 0;
  color: var(--color-text-secondary);
}

.onbBody {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(260px, 0.9fr);
  gap: 1rem;
  align-items: start;
}

.onbStepSection,
.onbShowcase {
  min-width: 0;
  border-radius: 0.95rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.onbStepSection {
  display: grid;
  gap: 0.85rem;
  align-content: start;
  min-height: 100%;
  padding: 0.95rem;
  background: rgb(var(--color-bg-rgb) / 0.48);
}

.onbSectionIntro {
  margin: 0;
  font-size: 0.88rem;
  color: var(--color-text-secondary);
}

.onbShowcase {
  display: grid;
  gap: 0.8rem;
  padding: 1rem;
  background:
    radial-gradient(140% 120% at 100% 0%, rgb(var(--color-primary-rgb) / 0.16), transparent 55%),
    rgb(var(--color-bg-rgb) / 0.56);
}

.onbShowcaseEyebrow,
.onbShowcaseText {
  margin: 0;
}

.onbShowcaseEyebrow {
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--color-primary);
  font-weight: 700;
}

.onbShowcaseTitle {
  margin: 0;
  font-size: clamp(1rem, 2vw, 1.2rem);
  line-height: 1.2;
}

.onbShowcaseText {
  font-size: 0.84rem;
  color: var(--color-text-secondary);
}

.onbShowcasePoints {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.onbShowcasePoint {
  display: inline-flex;
  align-items: center;
  min-height: 1.8rem;
  border-radius: 999px;
  padding: 0.26rem 0.62rem;
  background: rgb(var(--color-primary-rgb) / 0.12);
  border: 1px solid rgb(var(--color-primary-rgb) / 0.18);
  font-size: 0.75rem;
  color: rgb(var(--color-surface-rgb) / 0.92);
}

.chipWrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.chip {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.6);
  color: inherit;
  border-radius: 999px;
  padding: 0.45rem 0.8rem;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 140ms ease;
}

.chip.active {
  border-color: rgb(var(--color-primary-rgb) / 0.75);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.field {
  display: grid;
  gap: 0.35rem;
}

.fieldLabel {
  font-size: 0.82rem;
  color: var(--color-text-secondary);
}

.fieldInput {
  width: 100%;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 0.7rem;
  padding: 0.64rem 0.72rem;
  background: rgb(var(--color-bg-rgb) / 0.6);
  color: inherit;
}

.fieldInput:focus-visible {
  outline: 0;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
}

.suggestions {
  list-style: none;
  margin: 0.5rem 0 0;
  padding: 0.35rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 0.7rem;
  background: rgb(var(--color-bg-rgb) / 0.9);
  max-height: 220px;
  overflow: auto;
}

.suggestionItem {
  width: 100%;
  text-align: left;
  display: grid;
  gap: 0.12rem;
  border: 0;
  background: transparent;
  color: inherit;
  border-radius: 0.45rem;
  padding: 0.48rem 0.55rem;
  cursor: pointer;
}

.suggestionTitle {
  font-size: 0.88rem;
}

.suggestionMeta {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.suggestionItem:hover {
  background: rgb(var(--color-primary-rgb) / 0.14);
}

.onbActions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
}

.rightActions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btnGhost,
.btnPrimary {
  border-radius: 0.65rem;
  padding: 0.54rem 0.84rem;
  border: 1px solid transparent;
  cursor: pointer;
}

.btnGhost {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.45);
  color: inherit;
}

.btnPrimary {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.btnGhost:disabled,
.btnPrimary:disabled {
  opacity: 0.58;
  cursor: not-allowed;
}

.onbFade-enter-active,
.onbFade-leave-active {
  transition: opacity 180ms ease;
}

.onbFade-enter-from,
.onbFade-leave-to {
  opacity: 0;
}

.onbFade-enter-from .onbModal,
.onbFade-leave-to .onbModal {
  transform: scale(0.98);
}

.onbStep-enter-active,
.onbStep-leave-active {
  transition: all 180ms ease;
}

.onbStep-enter-from {
  opacity: 0;
  transform: translateX(10px);
}

.onbStep-leave-to {
  opacity: 0;
  transform: translateX(-10px);
}

@media (max-width: 680px) {
  .onbOverlay {
    padding: 0.65rem;
  }

  .onbModal {
    border-radius: 0.85rem;
    padding: 0.85rem;
  }

  .onbBody {
    grid-template-columns: 1fr;
  }

  .onbActions {
    flex-wrap: wrap;
  }

  .rightActions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
