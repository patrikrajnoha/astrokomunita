<template>
  <transition name="onbFade" appear>
    <div class="onbOverlay">
      <div
        ref="modalRef"
        class="onbCard"
        role="dialog"
        aria-modal="true"
        aria-labelledby="onboarding-title"
      >
        <header class="onbHeader">
          <div class="onbHeaderRow">
            <div class="onbStepDots" aria-hidden="true">
              <span
                v-for="n in 2"
                :key="n"
                class="onbStepDot"
                :class="{ 'is-active': n === step }"
              ></span>
            </div>
            <span class="onbStepText">{{ step }} / 2</span>
          </div>

          <h1 id="onboarding-title" class="onbTitle">
            {{ stepTitle }}
          </h1>
          <p class="onbSubtitle">
            {{ stepSubtitle }}
          </p>
        </header>

        <div class="onbBody">
          <div class="onbStepWrap">
            <transition name="onbStep" mode="out-in">
              <section v-if="step === 1" key="step-1" class="onbSection">
                <div class="onbSelectionHeader">
                  <p class="onbHint">
                    {{ widgetSelectionHint }}
                  </p>
                  <span class="onbCounter" :class="{ 'is-full': selectedWidgetKeys.length >= requiredWidgetCount }">
                    {{ selectedWidgetKeys.length }}/{{ requiredWidgetCount }}
                  </span>
                </div>
                <p v-if="widgetSelectionError" class="onbError">{{ widgetSelectionError }}</p>

                <div class="onbWidgetGrid">
                  <button
                    v-for="widget in widgetCatalogNormalized"
                    :key="widget.key"
                    type="button"
                    class="onbWidgetCard"
                    :class="{ 'is-selected': isSelected(widget.key) }"
                    :aria-label="isSelected(widget.key) ? `Odobrať ${widget.label}` : `Vybrať ${widget.label}`"
                    :aria-pressed="isSelected(widget.key) ? 'true' : 'false'"
                    @click="toggleWidget(widget.key)"
                  >
                    <span class="onbWidgetTitle">{{ widget.label }}</span>
                    <span class="onbWidgetDescription">{{ widget.description }}</span>
                    <span class="onbWidgetState">
                      {{ isSelected(widget.key) ? 'Vybraté' : 'Pridať' }}
                    </span>
                  </button>
                </div>
              </section>

              <section v-else key="step-2" class="onbSection">
                <p class="onbHint onbHint--location">
                  Tvoju lokalitu použijeme pre seeing, počasie a ďalšie pozorovacie widgety.
                </p>

                <div class="onbField">
                  <label class="onbLabel" for="onb-location">Mesto alebo obec</label>
                  <input
                    id="onb-location"
                    ref="locationInputRef"
                    v-model.trim="locationLabel"
                    class="onbInput"
                    type="text"
                    placeholder="Napríklad Bratislava"
                    autocomplete="off"
                    @focus="openSuggestions = true"
                  />

                  <Teleport to="body">
                    <ul
                      v-if="openSuggestions && suggestions.length > 0"
                      class="onbSuggestions"
                      role="listbox"
                      :style="suggestionsFixedStyle"
                    >
                      <li v-for="option in suggestions" :key="option.place_id">
                        <button
                          type="button"
                          class="onbSuggestionBtn"
                          @click="selectLocation(option)"
                        >
                          <span class="onbSuggestionPrimary">{{ option.label }}</span>
                          <span v-if="option.country || option.timezone" class="onbSuggestionSecondary">
                            {{ [option.country, option.timezone].filter(Boolean).join(' · ') }}
                          </span>
                        </button>
                      </li>
                    </ul>
                  </Teleport>
                </div>
              </section>
            </transition>
          </div>

          <aside class="onbShowcase" aria-label="Ukážka widgetov">
            <p class="onbShowcaseEyebrow">Widgety na mieru</p>
            <h2 class="onbShowcaseTitle">{{ showcaseContent.title }}</h2>
            <p class="onbShowcaseText">{{ showcaseContent.body }}</p>
            <OnboardingWidgetPreview class="onbShowcasePreview" />
          </aside>
        </div>

        <footer class="onbFooter">
          <button
            type="button"
            class="onbBtn onbBtn--secondary"
            :disabled="loading"
            @click="emitSkip"
          >
            Preskočiť
          </button>

          <div class="onbFooterActions">
            <button
              v-if="step === 2"
              type="button"
              class="onbBtn onbBtn--secondary"
              :disabled="loading"
              @click="step = 1"
            >
              Späť
            </button>

            <button
              v-if="step === 1"
              type="button"
              class="onbBtn onbBtn--primary"
              :disabled="loading || !canContinueFromWidgets"
              @click="step = 2"
            >
              Ďalej
            </button>

            <button
              v-else
              type="button"
              class="onbBtn onbBtn--primary"
              :disabled="loading"
              @click="emitFinish"
            >
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

const MAX_WIDGETS = 3
const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  widgetCatalog: {
    type: Array,
    default: () => [],
  },
  initialWidgetKeys: {
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
const selectedWidgetKeys = ref([])
const widgetSelectionError = ref('')

const locationLabel = ref(String(props.initialLocation?.location_label || ''))
const locationPlaceId = ref(props.initialLocation?.location_place_id || null)
const locationLat = ref(props.initialLocation?.location_lat ?? null)
const locationLon = ref(props.initialLocation?.location_lon ?? null)
const suggestions = ref([])
const openSuggestions = ref(false)
const suppressLocationFieldWatch = ref(false)
const locationInputRef = ref(null)
const suggestionsAnchorRect = ref(null)

const suggestionsFixedStyle = computed(() => {
  const r = suggestionsAnchorRect.value
  if (!r) return { position: 'fixed', top: '-9999px', left: '-9999px' }
  return {
    position: 'fixed',
    top: `${r.bottom + 6}px`,
    left: `${r.left}px`,
    width: `${r.width}px`,
    zIndex: 9999,
  }
})

function updateSuggestionsAnchor() {
  if (!locationInputRef.value) return
  const rect = locationInputRef.value.getBoundingClientRect()
  suggestionsAnchorRect.value = {
    top: rect.top,
    bottom: rect.bottom,
    left: rect.left,
    right: rect.right,
    width: rect.width,
    height: rect.height,
  }
}

let debounceTimer = null
let locationRequestId = 0

function formatWidgetWord(count) {
  const normalizedCount = Math.abs(Number(count) || 0)

  if (normalizedCount === 1) return 'widget'
  if (normalizedCount >= 2 && normalizedCount <= 4) return 'widgety'
  return 'widgetov'
}

const widgetCatalogNormalized = computed(() => {
  return (Array.isArray(props.widgetCatalog) ? props.widgetCatalog : [])
    .map((item) => {
      const key = String(item?.key || '').trim()
      const label = String(item?.label || '').trim()
      const description = String(item?.description || '').trim()
      if (!key || !label) return null
      return {
        key,
        label,
        description: description || 'Prispôsob si sidebar podľa toho, čo chceš sledovať najčastejšie.',
      }
    })
    .filter(Boolean)
})

const requiredWidgetCount = computed(() => {
  if (widgetCatalogNormalized.value.length === 0) return 0
  return Math.min(MAX_WIDGETS, widgetCatalogNormalized.value.length)
})

const canContinueFromWidgets = computed(() => {
  if (requiredWidgetCount.value === 0) return true
  return selectedWidgetKeys.value.length === requiredWidgetCount.value
})

const requiredWidgetCopy = computed(() => `${requiredWidgetCount.value} ${formatWidgetWord(requiredWidgetCount.value)}`)
const stepTitle = computed(() => {
  if (step.value !== 1) {
    return 'Nastav lokalitu pre widgety'
  }

  if (requiredWidgetCount.value === 0) {
    return 'Prispôsob si widgety'
  }

  return `Vyber si ${requiredWidgetCopy.value}`
})

const stepSubtitle = computed(() => {
  if (step.value === 1) {
    return 'Predvolené widgety spravuje administrácia. Tu si vyberieš vlastné pre svoju domovskú stránku.'
  }

  return 'Lokalita pomôže widgetom so seeingom, počasím a pozorovaním oblohy.'
})

const widgetSelectionHint = computed(() => {
  if (requiredWidgetCount.value === 0) {
    return 'Momentálne nie sú dostupné žiadne widgety. Pokračovať môžeš aj bez výberu.'
  }

  return `Vyber presne ${requiredWidgetCopy.value}, ktoré chceš mať hneď po prihlásení.`
})

const showcaseContent = computed(() => {
  if (step.value === 2) {
    return {
      title: 'Lokalita zlepší presnosť widgetov.',
      body: 'Po uložení bude sidebar lepšie reagovať na tvoje miesto pozorovania.',
    }
  }

  return {
    title: 'Začni s widgetmi, ktoré naozaj používaš.',
    body: 'Výber sa uloží ako tvoja osobná konfigurácia, ktorú vieš neskôr zmeniť v nastaveniach.',
  }
})

function normalizeWidgetKeys(value) {
  if (!Array.isArray(value)) return []
  return Array.from(
    new Set(
      value
        .map((entry) => String(entry || '').trim())
        .filter(Boolean),
    ),
  ).slice(0, MAX_WIDGETS)
}

function initializeSelection() {
  const allowed = new Set(widgetCatalogNormalized.value.map((widget) => widget.key))
  if (allowed.size === 0) {
    selectedWidgetKeys.value = []
    return
  }

  const current = selectedWidgetKeys.value.filter((key) => allowed.has(key))
  let nextKeys = current

  if (nextKeys.length === 0) {
    nextKeys = normalizeWidgetKeys(props.initialWidgetKeys).filter((key) => allowed.has(key))
  }

  if (requiredWidgetCount.value > 0 && nextKeys.length < requiredWidgetCount.value) {
    for (const widget of widgetCatalogNormalized.value) {
      if (nextKeys.includes(widget.key)) continue
      nextKeys.push(widget.key)
      if (nextKeys.length >= requiredWidgetCount.value) break
    }
  }

  selectedWidgetKeys.value = nextKeys.slice(0, MAX_WIDGETS)
}

function isSelected(key) {
  return selectedWidgetKeys.value.includes(key)
}

function toggleWidget(key) {
  if (isSelected(key)) {
    selectedWidgetKeys.value = selectedWidgetKeys.value.filter((item) => item !== key)
    widgetSelectionError.value = ''
    return
  }

  if (selectedWidgetKeys.value.length >= MAX_WIDGETS) {
    widgetSelectionError.value = `Môžeš vybrať najviac ${MAX_WIDGETS} ${formatWidgetWord(MAX_WIDGETS)}.`
    return
  }

  selectedWidgetKeys.value = [...selectedWidgetKeys.value, key]
  widgetSelectionError.value = ''
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
  const selectedKeys = selectedWidgetKeys.value.slice(0, MAX_WIDGETS)

  emit('finish', {
    sidebar_widget_keys: selectedKeys,
    sidebar_widget_overrides: {
      home: selectedKeys,
    },
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

watch([widgetCatalogNormalized, () => props.initialWidgetKeys], () => {
  initializeSelection()
}, { immediate: true, deep: true })

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

watch(openSuggestions, async (open) => {
  if (open) {
    await nextTick()
    updateSuggestionsAnchor()
  } else {
    suggestionsAnchorRect.value = null
  }
})

watch(canContinueFromWidgets, (value) => {
  if (value) {
    widgetSelectionError.value = ''
  } else if (step.value === 1 && requiredWidgetCount.value > 0) {
    widgetSelectionError.value = `Vyber presne ${requiredWidgetCopy.value}.`
  }
}, { immediate: true })

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown)
  window.addEventListener('resize', updateSuggestionsAnchor)
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
  window.removeEventListener('resize', updateSuggestionsAnchor)
})
</script>

<style scoped>
.onbOverlay {
  --onb-bg: #151d28;
  --onb-surface-hover: #1c2736;
  --onb-primary: #0f73ff;
  --onb-text: #ffffff;
  --onb-muted: #abb8c9;
  --onb-secondary-btn: #222e3f;
  --onb-danger: #eb2452;
  --onb-focus: rgb(15 115 255 / 0.38);

  position: fixed;
  inset: 0;
  z-index: 1300;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow-y: auto;
  padding: max(0.75rem, env(safe-area-inset-top)) 0.75rem max(0.75rem, env(safe-area-inset-bottom));
  background: rgb(12 18 27 / 0.72);
  backdrop-filter: blur(10px);
}

.onbCard {
  width: 100%;
  max-width: 920px;
  max-height: calc(100dvh - 1.5rem);
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  border-radius: 24px;
  background:
    linear-gradient(180deg, rgb(28 39 54 / 0.72), transparent 28%),
    var(--onb-bg);
  color: var(--onb-text);
  overflow: hidden;
  animation: onbCardIn 240ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.onbHeader {
  padding: 1.35rem 1.35rem 0.7rem;
}

.onbHeaderRow {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 0.8rem;
}

.onbStepDots {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.onbStepDot {
  width: 0.42rem;
  height: 0.42rem;
  border-radius: 999px;
  background: rgb(171 184 201 / 0.35);
  transition: width 180ms ease, background-color 180ms ease, opacity 180ms ease;
}

.onbStepDot.is-active {
  width: 1.25rem;
  background: #0f73ff;
}

.onbStepText {
  color: var(--onb-muted);
  font-size: 0.78rem;
}

.onbTitle {
  margin: 0;
  font-size: clamp(1.2rem, 1.35vw, 1.38rem);
  line-height: 1.2;
  color: var(--onb-text);
}

.onbSubtitle {
  margin: 0.45rem 0 0;
  color: var(--onb-muted);
  font-size: 0.9rem;
  line-height: 1.45;
}

.onbBody {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 285px);
  gap: 1rem;
  padding: 0.65rem 1.35rem 1rem;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

.onbStepWrap {
  min-width: 0;
  min-height: 0;
}

.onbSection {
  min-height: 0;
}

.onbSelectionHeader {
  display: flex;
  align-items: flex-start;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
}

.onbHint {
  margin: 0;
  color: var(--onb-muted);
  font-size: 0.88rem;
  line-height: 1.45;
}

.onbHint--location {
  margin-bottom: 1rem;
}

.onbCounter {
  border-radius: 999px;
  padding: 0.28rem 0.68rem;
  background: rgb(34 46 63 / 0.94);
  color: var(--onb-muted);
  font-size: 0.78rem;
  font-weight: 600;
  transition: background-color 180ms ease, color 180ms ease;
}

.onbCounter.is-full {
  background: rgb(15 115 255 / 0.18);
  color: var(--onb-primary);
}

.onbError {
  margin: 0.6rem 0 0;
  color: var(--onb-danger);
  font-size: 0.82rem;
}

.onbWidgetGrid {
  margin-top: 0.85rem;
  display: grid;
  gap: 0.6rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.onbWidgetCard {
  position: relative;
  border: none;
  box-shadow: none;
  border-radius: 20px;
  background: rgb(34 46 63 / 0.92);
  color: var(--onb-muted);
  text-align: left;
  padding: 0.92rem 0.95rem;
  display: grid;
  gap: 0.38rem;
  cursor: pointer;
  transition: background-color 180ms ease, color 180ms ease, opacity 180ms ease;
}

.onbWidgetCard::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(180deg, rgb(255 255 255 / 0.04), transparent 46%);
  opacity: 0.9;
  pointer-events: none;
  transition: opacity 180ms ease;
}

.onbWidgetCard:hover,
.onbWidgetCard:focus-visible {
  background: var(--onb-surface-hover);
}

.onbWidgetCard:focus-visible {
  outline: 2px solid var(--onb-focus);
  outline-offset: 3px;
}

.onbWidgetCard.is-selected {
  background: linear-gradient(180deg, #1185fe, var(--onb-primary));
  color: var(--onb-text);
}

.onbWidgetCard.is-selected::after {
  opacity: 0.22;
}

.onbWidgetTitle {
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.25;
}

.onbWidgetDescription {
  font-size: 0.76rem;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.onbWidgetState {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: fit-content;
  min-width: 4.5rem;
  margin-top: 0.08rem;
  padding: 0.3rem 0.62rem;
  border-radius: 999px;
  background: rgb(255 255 255 / 0.08);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.onbWidgetCard:not(.is-selected) .onbWidgetState {
  background: rgb(255 255 255 / 0.04);
}

.onbField {
  position: relative;
}

.onbLabel {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--onb-muted);
  font-size: 0.78rem;
  font-weight: 600;
}

.onbInput {
  width: 100%;
  border: none;
  box-shadow: none;
  border-radius: 18px;
  background: var(--onb-secondary-btn);
  color: var(--onb-text);
  padding: 0.82rem 0.95rem;
  font-size: 0.9rem;
  box-sizing: border-box;
  outline: 2px solid transparent;
  outline-offset: 2px;
  transition: background-color 160ms ease, outline-color 160ms ease;
}

.onbInput::placeholder {
  color: rgb(171 184 201 / 0.68);
}

.onbInput:focus-visible {
  background: var(--onb-surface-hover);
  outline-color: var(--onb-focus);
}

.onbSuggestions {
  list-style: none;
  margin: 0;
  padding: 0;
  overflow: hidden;
  border-radius: 16px;
  background: rgb(34 46 63 / 0.98);
  box-shadow: 0 8px 28px rgb(0 0 0 / 0.36);
  max-height: min(14rem, 42vh);
  overflow-y: auto;
}

.onbSuggestionBtn {
  width: 100%;
  border: none;
  box-shadow: none;
  border-radius: 0;
  background: transparent;
  text-align: left;
  padding: 0.68rem 0.85rem;
  cursor: pointer;
  transition: background-color 160ms ease, color 160ms ease;
}

.onbSuggestionBtn:hover,
.onbSuggestionBtn:focus-visible {
  background: var(--onb-surface-hover);
}

.onbSuggestionBtn:focus-visible {
  outline: 2px solid var(--onb-focus);
  outline-offset: -2px;
}

.onbSuggestionPrimary {
  display: block;
  color: var(--onb-text);
  font-size: 0.86rem;
}

.onbSuggestionSecondary {
  display: block;
  margin-top: 0.18rem;
  color: var(--onb-muted);
  font-size: 0.74rem;
}

.onbShowcase {
  border-radius: 20px;
  padding: 0.95rem;
  background: rgb(28 39 54 / 0.86);
  align-self: start;
  position: sticky;
  top: 0;
}

.onbShowcaseEyebrow {
  margin: 0;
  color: var(--onb-primary);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.onbShowcaseTitle {
  margin: 0.5rem 0 0;
  color: var(--onb-text);
  font-size: 0.95rem;
  line-height: 1.35;
}

.onbShowcaseText {
  margin: 0.48rem 0 0;
  color: var(--onb-muted);
  font-size: 0.79rem;
  line-height: 1.45;
}

.onbShowcasePreview {
  margin-top: 0.8rem;
}

.onbFooter {
  padding: 0 1.35rem 1.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.onbFooterActions {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.onbBtn {
  border: none;
  box-shadow: none;
  border-radius: 999px;
  min-height: 2.7rem;
  padding: 0.72rem 1.18rem;
  font-size: 0.87rem;
  font-weight: 600;
  line-height: 1.1;
  cursor: pointer;
  transition: background-color 180ms ease, color 180ms ease, opacity 180ms ease;
}

.onbBtn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.onbBtn--secondary {
  background: var(--onb-secondary-btn);
  color: var(--onb-muted);
}

.onbBtn--secondary:hover:not(:disabled) {
  background: var(--onb-surface-hover);
}

.onbBtn--primary {
  background: var(--onb-primary);
  color: var(--onb-text);
}

.onbBtn--primary:hover:not(:disabled) {
  background: #1185fe;
}

.onbBtn:focus-visible {
  outline: 2px solid var(--onb-focus);
  outline-offset: 3px;
}

.onbFade-enter-active {
  transition: opacity 240ms ease;
}

.onbFade-leave-active {
  transition: opacity 180ms ease;
}

.onbFade-enter-from,
.onbFade-leave-to {
  opacity: 0;
}

.onbStep-enter-active,
.onbStep-leave-active {
  transition: opacity 220ms ease, transform 220ms ease;
}

.onbStep-enter-from {
  opacity: 0;
  transform: translateX(4px);
}

.onbStep-leave-to {
  opacity: 0;
  transform: translateX(-4px);
}

@keyframes onbCardIn {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.992);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (max-width: 920px) {
  .onbOverlay {
    align-items: flex-start;
  }

  .onbCard {
    max-height: calc(100dvh - 1rem);
    border-radius: 22px;
  }

  .onbHeader {
    padding: 1.05rem 1rem 0.6rem;
  }

  .onbTitle {
    font-size: 1.14rem;
  }

  .onbSubtitle {
    margin-top: 0.35rem;
    font-size: 0.83rem;
  }

  .onbBody {
    grid-template-columns: 1fr;
    gap: 0.85rem;
    padding: 0.45rem 1rem 0.8rem;
  }

  .onbShowcase {
    display: none;
  }

  .onbWidgetGrid {
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.5rem;
  }

  .onbSection {
    min-height: 0;
  }

  .onbFooter {
    padding: 0 1rem calc(1rem + env(safe-area-inset-bottom));
    gap: 0.5rem;
  }

  .onbFooterActions {
    gap: 0.42rem;
  }

}

@media (max-width: 640px) {
  .onbFooter {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .onbFooterActions {
    width: 100%;
    justify-content: flex-end;
  }

  .onbBtn {
    min-height: 2.5rem;
  }
}

@media (max-width: 420px) {
  .onbStepText {
    font-size: 0.72rem;
  }

  .onbSelectionHeader {
    align-items: flex-start;
    gap: 0.45rem;
  }

  .onbHint {
    font-size: 0.82rem;
  }

  .onbCounter {
    font-size: 0.72rem;
    padding: 0.22rem 0.55rem;
  }

  .onbWidgetCard {
    border-radius: 16px;
    padding: 0.72rem 0.78rem;
  }

  .onbWidgetTitle {
    font-size: 0.84rem;
  }

  .onbWidgetDescription {
    font-size: 0.72rem;
  }

  .onbWidgetState {
    font-size: 0.68rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .onbCard,
  .onbStepDot,
  .onbWidgetCard,
  .onbBtn,
  .onbFade-enter-active,
  .onbFade-leave-active,
  .onbStep-enter-active,
  .onbStep-leave-active {
    animation: none !important;
    transition: none !important;
    transform: none !important;
  }
}
</style>
