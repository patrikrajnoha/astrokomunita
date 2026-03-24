<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminSectionHeader from '@/components/admin/AdminSectionHeader.vue'
import { eventCandidates } from '@/services/eventCandidates'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { candidateDisplayDescription, candidateDisplayShort, candidateDisplayTitle } from '@/utils/translatedFields'
import { resolveUserLocationLabel, resolveUserPreferredTimezone } from '@/utils/userTimezone'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()
const auth = useAuthStore()

const id = computed(() => Number(route.params.id))
const candidateListRoute = computed(() => ({
  name: 'admin.event-candidates',
  query: { ...route.query },
}))

const loading = ref(false)
const error = ref(null)
const candidate = ref(null)
const retranslateLoading = ref(false)
const retranslateMessage = ref('')
const retranslateError = ref('')
const retranslateRawStatus = ref(null)
const showRaw = ref(false)
const showOriginal = ref(false)
const showTranslationEditor = ref(false)
const translationForm = ref({
  translated_title: '',
  translated_description: '',
})
const preferredTimezone = computed(() => resolveUserPreferredTimezone(auth.user))
const timezoneInfoLabel = computed(() => `${resolveUserLocationLabel(auth.user)} (${preferredTimezone.value})`)
const matchedSources = computed(() => normalizeSources(candidate.value?.matched_sources))

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('sk-SK', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: preferredTimezone.value,
  })
}

function formatConfidence(value) {
  if (value === null || value === undefined || value === '') return '-'
  const numeric = Number(value)
  if (Number.isNaN(numeric)) return '-'
  return numeric.toFixed(2)
}

function normalizeSources(values) {
  if (!Array.isArray(values)) return []
  return values
    .map((item) => String(item || '').trim().toLowerCase())
    .filter((item) => item.length > 0)
}

function sourceLabel(source) {
  const key = String(source || '').toLowerCase()
  if (key === 'astropixels') return 'AstroPixels'
  if (key === 'imo') return 'IMO'
  if (key === 'nasa_watch_the_skies' || key === 'nasa_wts') return 'NASA WTS'
  if (key === 'nasa') return 'NASA'
  return key || '-'
}

function sourceToneClass(source) {
  const key = String(source || '').toLowerCase()
  if (key === 'astropixels') return 'chip--source-astropixels'
  if (key === 'imo') return 'chip--source-imo'
  if (key === 'nasa' || key === 'nasa_wts' || key === 'nasa_watch_the_skies') return 'chip--source-nasa'
  return 'chip--source-default'
}

function translationStatusKey(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (normalized === 'done' || normalized === 'translated') return 'success'
  if (normalized === 'failed' || normalized === 'error') return 'error'
  return 'pending'
}

function translationStatusLabel(value) {
  const status = translationStatusKey(value)
  if (status === 'success') return '✅ Preložené'
  if (status === 'error') return '❌ Zlyhalo'
  return '⏳ Čaká'
}

function translationStatusClass(value) {
  const status = translationStatusKey(value)
  if (status === 'success') return 'statusBadge--success'
  if (status === 'error') return 'statusBadge--error'
  return 'statusBadge--pending'
}

function translationModeLabel(value) {
  const mode = String(value || '').trim().toLowerCase()
  if (mode === 'template') return '📄 Šablóna'
  if (mode === 'ai_refined') return '🤖 AI — titul aj popis'
  if (mode === 'ai_title') return '🤖 AI — len titul'
  if (mode === 'ai_description') return '🤖 AI — len popis'
  if (mode === 'translated') return '🌐 Strojový preklad'
  if (mode === 'manual') return '✍️ Ručne upravené'
  return '❓ Neznáme'
}

function canReview() {
  return candidate.value && candidate.value.status === 'pending' && !loading.value
}

async function load() {
  loading.value = true
  error.value = null

  try {
    candidate.value = await eventCandidates.get(id.value)
    translationForm.value = {
      translated_title: candidateDisplayTitle(candidate.value) || '',
      translated_description: candidateDisplayDescription(candidate.value) || '',
    }
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Chyba pri načítaní detailu'
  } finally {
    loading.value = false
  }
}

async function approve() {
  if (!candidate.value) return

  const ok = await confirm({
    title: 'Schváliť kandidáta',
    message: 'Naozaj chceš schváliť tohto kandidáta?',
    confirmText: 'Schváliť',
    cancelText: 'Zrušiť',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  try {
    await eventCandidates.approve(candidate.value.id)
    toast.success('Kandidát bol schválený.')
    router.push(candidateListRoute.value)
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Schválenie zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

async function reject() {
  if (!candidate.value) return

  const ok = await confirm({
    title: 'Zamietnuť kandidáta',
    message: 'Naozaj chceš zamietnuť tohto kandidáta?',
    confirmText: 'Zamietnuť',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  try {
    await eventCandidates.reject(candidate.value.id)
    toast.success('Kandidát bol zamietnutý.')
    router.push(candidateListRoute.value)
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Zamietnutie zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

async function retranslate(mode = 'ai') {
  if (!candidate.value || retranslateLoading.value) return
  const selectedMode = String(mode || '').trim().toLowerCase() === 'template' ? 'template' : 'ai'
  const modeLabel = selectedMode === 'template' ? '📄 Šablóna' : '🤖 AI popis'

  retranslateLoading.value = true
  retranslateMessage.value = ''
  retranslateError.value = ''
  retranslateRawStatus.value = null
  error.value = null

  try {
    const response = await eventCandidates.retranslate(candidate.value.id, { mode: selectedMode })
    const fallbackUsed = Boolean(
      response?.fallback_used
      || response?.candidate?.fallback_used
      || response?.candidate?.translation_fallback_used,
    )

    retranslateMessage.value = fallbackUsed
      ? `${modeLabel}: použitá šablóna (routing).`
      : `${modeLabel}: spustené.`
    toast.success(`${modeLabel} bol spustený.`)
    await load()

    const resolvedMode = String(candidate.value?.translation_mode || '').trim().toLowerCase()
    if (selectedMode === 'ai' && resolvedMode === 'template') {
      retranslateMessage.value = `⚠️ AI nevrátila použiteľný výstup — použitá záložná šablóna.`
      toast.warn(retranslateMessage.value)
      return
    }
  } catch (fetchError) {
    const status = Number(fetchError?.response?.status || 0)
    const messageText = String(fetchError?.message || '').toLowerCase()
    const isTimeout = fetchError?.code === 'ECONNABORTED' || messageText.includes('timeout')
    const isNetwork = !status && (fetchError?.code === 'ERR_NETWORK' || messageText.includes('network'))

    if (isTimeout || isNetwork) {
      retranslateMessage.value = `⏳ Požiadavka trvá dlhšie — spracovanie beží na pozadí.`
      toast.warn(retranslateMessage.value)
      await load()
      return
    }

    const resolvedError =
      fetchError?.response?.data?.message
      || fetchError?.userMessage
      || `${modeLabel}: spustenie generovania zlyhalo.`
    retranslateError.value = resolvedError
    retranslateRawStatus.value = status || null
    error.value = resolvedError
    toast.error(resolvedError)
  } finally {
    retranslateLoading.value = false
  }
}

function openTranslationEditor() {
  if (!candidate.value) return
  translationForm.value = {
    translated_title: candidateDisplayTitle(candidate.value) || '',
    translated_description: candidateDisplayDescription(candidate.value) || '',
  }
  showTranslationEditor.value = true
}

async function saveTranslationEdit() {
  if (!candidate.value) return

  const title = String(translationForm.value.translated_title || '').trim()
  if (!title) {
    toast.error('Preložený názov je povinný.')
    return
  }

  loading.value = true
  error.value = null
  try {
    await eventCandidates.updateTranslation(candidate.value.id, {
      translated_title: title,
      translated_description: String(translationForm.value.translated_description || '').trim() || null,
    })
    toast.success('Preklad bol uložený.')
    showTranslationEditor.value = false
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Uloženie prekladu zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="candidateDetailView">
    <AdminSectionHeader
      section="events"
      :title="`Detail kandidáta #${id}`"
      back-label="Späť na kandidátov"
      :back-to="candidateListRoute"
    />

    <header class="candidateDetailView__hero">
      <div class="candidateDetailView__heroMain">
        <h1 class="candidateDetailView__title">Kandidát #{{ id }}</h1>
        <p v-if="candidate" class="candidateDetailView__subtitle">
          {{ candidateDisplayTitle(candidate) }}
        </p>
      </div>

      <div v-if="candidate" class="candidateDetailView__heroMeta">
        <div><b>Status:</b> {{ candidate.status }}</div>
        <div><b>Typ:</b> {{ candidate.type }}</div>
      </div>
    </header>

    <p v-if="error" class="candidateDetailView__alert candidateDetailView__alert--error">
      {{ error }}
    </p>
    <p v-if="loading" class="candidateDetailView__alert candidateDetailView__alert--loading">
      Načítavam...
    </p>

    <div v-if="candidate && !loading" class="candidateDetailView__sections">
      <section class="card card--wide">
        <h3 class="card__title">📋 Meta</h3>

        <div class="detailGrid">
          <div class="detailLabel">ID</div><div class="detailValue">{{ candidate.id }}</div>

          <div class="detailLabel">Typ</div>
          <div class="detailValue">
            {{ candidate.type }}
            <span class="detailValueMuted">(raw: {{ candidate.raw_type || '-' }})</span>
          </div>

          <div class="detailLabel">Skrátený popis</div><div class="detailValue">{{ candidateDisplayShort(candidate) }}</div>

          <div class="detailLabel">Kanonický kľúč</div>
          <div class="detailValue detailValue--break">{{ candidate.canonical_key || '-' }}</div>

          <div class="detailLabel">Dôveryhodnosť</div>
          <div class="detailValue">{{ formatConfidence(candidate.confidence_score) }}</div>

          <div class="detailLabel">Spárované zdroje</div>
          <div class="detailValue badgesRow">
            <span
              v-for="src in matchedSources"
              :key="`detail-matched-${src}`"
              class="chip"
              :class="sourceToneClass(src)"
            >
              {{ sourceLabel(src) }}
            </span>
            <span v-if="matchedSources.length === 0" class="detailValueMuted">-</span>
          </div>

          <div class="detailLabel">Vytvorené</div><div class="detailValue">{{ formatDate(candidate.created_at) }}</div>
          <div class="detailLabel">Aktualizované</div><div class="detailValue">{{ formatDate(candidate.updated_at) }}</div>
        </div>
      </section>

      <section class="card card--wide">
        <h3 class="card__title">🌐 Preklad & popis</h3>

        <div class="actionsRow">
          <button
            type="button"
            class="btn btn--primary"
            :disabled="!candidate || retranslateLoading"
            @click="retranslate('ai')"
          >
            {{ retranslateLoading ? '⏳ Pracujem...' : '🤖 Generovať AI popis' }}
          </button>
          <button
            type="button"
            class="btn btn--ghost"
            :disabled="!candidate || retranslateLoading"
            @click="retranslate('template')"
          >
            📄 Šablóna
          </button>
        </div>
        <p class="translationPanel__note">🤖 AI — Ollama vylepší titul aj popis do prirodzenej slovenčiny. &nbsp;📄 Šablóna — deterministický text podľa typu udalosti.</p>
        <p v-if="retranslateMessage" class="translationPanel__note">{{ retranslateMessage }}</p>
        <p v-if="retranslateError" class="translationPanel__note">{{ retranslateError }}</p>
        <div v-if="retranslateLoading" class="translationPanel__progress">
          <div class="translationPanel__progressBar"></div>
        </div>
        <p v-if="retranslateRawStatus" class="translationPanel__advanced">
          HTTP status: {{ retranslateRawStatus }}
        </p>
        <div class="detailGrid">
          <div class="detailLabel">Stav</div>
          <div class="detailValue">
            <span class="statusBadge" :class="translationStatusClass(candidate.translation_status)">
              {{ translationStatusLabel(candidate.translation_status) }}
            </span>
          </div>

          <div class="detailLabel">Typ popisu</div>
          <div class="detailValue">{{ translationModeLabel(candidate.translation_mode) }}</div>
          <div class="detailLabel">Posledná chyba</div>
          <div class="detailValue">{{ candidate.translation_error || '-' }}</div>

          <div class="detailLabel">Preložené o</div>
          <div class="detailValue">{{ formatDate(candidate.translated_at) }}</div>

          <div class="detailLabel">Finálny názov (SK)</div>
          <div class="detailValue">{{ candidateDisplayTitle(candidate) }}</div>

          <div class="detailLabel">Finálny popis (SK)</div>
          <div class="detailValue">{{ candidateDisplayDescription(candidate) }}</div>
        </div>

        <button
          type="button"
          class="btn btn--ghost originalToggle"
          @click="showOriginal = !showOriginal"
        >
          {{ showOriginal ? '🙈 Skryť originál' : '🔍 Zobraziť originál (EN)' }}
        </button>

        <div v-if="showOriginal" class="originalPanel">
          <div class="originalPanel__label">Originálny názov (EN)</div>
          <div class="originalPanel__value">{{ candidate.original_title || candidate.title || '—' }}</div>
          <div class="originalPanel__label">Originálny popis (EN)</div>
          <div class="originalPanel__value originalPanel__value--desc">{{ candidate.original_description || candidate.description || '—' }}</div>
        </div>

        <div class="actionsRow">
          <button
            type="button"
            class="btn btn--ghost"
            :disabled="loading"
            @click="openTranslationEditor"
          >
            ✏️ Upraviť preklad
          </button>
        </div>

        <div v-if="showTranslationEditor" class="editorCard">
          <div class="editorCard__title">Ručná úprava prekladu</div>
          <input
            v-model="translationForm.translated_title"
            type="text"
            class="input"
            :disabled="loading"
            placeholder="Preložený názov"
          />
          <textarea
            v-model="translationForm.translated_description"
            rows="5"
            class="input textarea"
            :disabled="loading"
            placeholder="Preložený popis"
          ></textarea>
          <div class="editorCard__actions">
            <button
              type="button"
              class="btn btn--ghost"
              :disabled="loading"
              @click="showTranslationEditor = false"
            >
              Zrušiť
            </button>
            <button
              type="button"
              class="btn btn--primary"
              :disabled="loading"
              @click="saveTranslationEdit"
            >
              Uložiť preklad
            </button>
          </div>
        </div>
      </section>

      <section class="card">
        <h3 class="card__title">🕐 Čas</h3>

        <div class="detailGrid">
          <div class="timezoneInfo">
            Časové pásmo: {{ timezoneInfoLabel }}
          </div>
          <div class="detailLabel">Start</div><div class="detailValue">{{ formatDate(candidate.start_at) }}</div>
          <div class="detailLabel">End</div><div class="detailValue">{{ formatDate(candidate.end_at) }}</div>
          <div class="detailLabel">Max</div><div class="detailValue">{{ formatDate(candidate.max_at) }}</div>
        </div>
      </section>

      <section class="card">
        <h3 class="card__title">🔗 Zdroj</h3>

        <div class="detailGrid">
          <div class="detailLabel">Názov zdroja</div>
          <div class="detailValue">
            <span class="chip" :class="sourceToneClass(candidate.source_name)">{{ sourceLabel(candidate.source_name) }}</span>
          </div>

          <div class="detailLabel">URL zdroja</div>
          <div class="detailValue">
            <a class="sourceLink" :href="candidate.source_url" target="_blank" rel="noreferrer">otvoriť zdroj</a>
          </div>

          <div class="detailLabel">UID zdroja</div><div class="detailValue detailValue--break">{{ candidate.source_uid }}</div>
        </div>
      </section>

      <section class="card">
        <h3 class="card__title">⚖️ Moderácia</h3>

        <div class="actionsRow">
          <button
            class="btn btn--success"
            @click="approve"
            :disabled="!canReview()"
          >
            ✅ Publikovať
          </button>

          <button
            class="btn btn--danger"
            @click="reject"
            :disabled="!canReview()"
          >
            ❌ Zamietnuť
          </button>
        </div>
      </section>

      <section class="card card--wide">
        <div class="rawHeader">
          <h3 class="card__title">📦 Raw payload</h3>

          <button
            class="btn btn--ghost"
            @click="showRaw = !showRaw"
          >
            {{ showRaw ? 'Skryť' : 'Zobraziť' }}
          </button>
        </div>

        <pre v-if="showRaw" class="rawPayload">{{ candidate.raw_payload ?? '' }}</pre>
      </section>
    </div>
  </div>
</template>

<style scoped>
.candidateDetailView {
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
  padding: 0;
  display: grid;
  gap: 10px;
  min-width: 0;
}

.candidateDetailView__hero {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.candidateDetailView__heroMain {
  min-width: 0;
}

.candidateDetailView__title {
  margin: 0;
  font-size: clamp(1.2rem, 1.8vw, 1.55rem);
  line-height: 1.18;
}

.candidateDetailView__subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  max-width: 680px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.candidateDetailView__heroMeta {
  text-align: right;
  display: grid;
  gap: 3px;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.candidateDetailView__alert {
  margin: 0;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  padding: 9px 10px;
  font-size: 13px;
}

.candidateDetailView__alert--error {
  border-color: rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  background: rgb(var(--color-danger-rgb, 239 68 68) / 0.08);
  color: var(--color-danger);
}

.candidateDetailView__alert--loading {
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  background: rgb(var(--color-bg-rgb) / 0.35);
}

.candidateDetailView__sections {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 11px;
  background: rgb(var(--color-bg-rgb) / 0.32);
  display: grid;
  gap: 10px;
}

.card--wide {
  grid-column: 1 / -1;
}

.card__title {
  margin: 0;
  font-size: 15px;
  line-height: 1.25;
}

.detailGrid {
  display: grid;
  grid-template-columns: minmax(120px, 170px) minmax(0, 1fr);
  gap: 6px 10px;
  align-items: start;
  font-size: 13px;
}

.detailLabel {
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 11px;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.detailValue {
  min-width: 0;
}

.detailValue--break {
  word-break: break-all;
}

.detailValueMuted {
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
}

.badgesRow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.chip,
.statusBadge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.08);
  font-size: 12px;
  line-height: 1.2;
  padding: 2px 8px;
}

.chip--source-astropixels {
  border-color: rgb(30 64 175 / 0.35);
  background: rgb(30 64 175 / 0.12);
}

.chip--source-imo {
  border-color: rgb(6 95 70 / 0.35);
  background: rgb(6 95 70 / 0.12);
}

.chip--source-nasa {
  border-color: rgb(107 33 168 / 0.35);
  background: rgb(107 33 168 / 0.12);
}

.statusBadge--success {
  border-color: rgb(22 163 74 / 0.35);
  background: rgb(22 163 74 / 0.12);
}

.statusBadge--error {
  border-color: rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.12);
}

.statusBadge--pending,
.statusBadge--fallback {
  border-color: rgb(245 158 11 / 0.4);
  background: rgb(245 158 11 / 0.12);
}

.translationPanel__note,
.translationPanel__advanced {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.translationPanel__progress {
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: rgb(var(--color-surface-rgb) / 0.2);
}

.translationPanel__progressBar {
  width: 38%;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, rgb(var(--color-primary-rgb)) 0%, rgb(var(--color-primary-rgb) / 0.68) 100%);
  animation: translationPanelSlide 1.1s ease-in-out infinite alternate;
}

@keyframes translationPanelSlide {
  from { transform: translateX(-40%); }
  to { transform: translateX(170%); }
}

.actionsRow {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.editorCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
  padding: 10px;
  display: grid;
  gap: 8px;
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.editorCard__title {
  font-size: 13px;
  font-weight: 700;
}

.editorCard__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.btn,
.input,
.textarea {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 9px;
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: inherit;
  font: inherit;
}

.btn {
  min-height: 34px;
  padding: 6px 11px;
  cursor: pointer;
  transition: border-color var(--motion-fast), background-color var(--motion-fast), transform var(--motion-fast);
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: rgb(var(--color-surface-rgb) / 0.35);
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  transform: none;
}

.btn--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.btn--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.42);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.btn--success {
  border-color: rgb(var(--color-success-rgb, 22 163 74) / 0.35);
  background: rgb(var(--color-success-rgb, 22 163 74) / 0.11);
}

.btn--danger {
  border-color: rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  background: rgb(var(--color-danger-rgb, 239 68 68) / 0.11);
}

.input,
.textarea {
  width: 100%;
  min-height: 36px;
  padding: 8px 10px;
}

.textarea {
  min-height: 110px;
  resize: vertical;
}

.timezoneInfo {
  grid-column: 1 / -1;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.87);
}

.sourceLink {
  color: rgb(var(--color-primary-rgb) / 0.94);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.originalToggle {
  align-self: start;
  font-size: 12px;
}

.originalPanel {
  display: grid;
  gap: 4px 10px;
  grid-template-columns: minmax(120px, 170px) minmax(0, 1fr);
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  background: rgb(var(--color-bg-rgb) / 0.4);
  font-size: 13px;
}

.originalPanel__label {
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 11px;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  align-self: start;
  padding-top: 2px;
}

.originalPanel__value {
  min-width: 0;
  word-break: break-word;
}

.originalPanel__value--desc {
  white-space: pre-line;
  line-height: 1.5;
}

.rawHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}

.rawPayload {
  margin: 0;
  max-height: 320px;
  overflow: auto;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 10px;
  background: rgb(var(--color-bg-rgb) / 0.6);
  white-space: pre-wrap;
  font-size: 12px;
  line-height: 1.36;
}

@media (max-width: 940px) {
  .candidateDetailView__sections {
    grid-template-columns: 1fr;
  }

  .card--wide {
    grid-column: auto;
  }
}

@media (max-width: 720px) {
  .candidateDetailView {
    padding: 0;
  }

  .candidateDetailView__heroMeta {
    width: 100%;
    text-align: left;
  }

  .candidateDetailView__subtitle {
    white-space: normal;
  }

  .detailGrid {
    grid-template-columns: 1fr;
    gap: 3px;
  }

  .detailLabel {
    margin-top: 5px;
  }

  .editorCard__actions {
    justify-content: stretch;
    flex-wrap: wrap;
  }

  .editorCard__actions .btn {
    flex: 1 1 150px;
  }
}
</style>


