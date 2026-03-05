<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventCandidates } from '@/services/eventCandidates'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { candidateDisplayDescription, candidateDisplayShort, candidateDisplayTitle } from '@/utils/translatedFields'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const id = computed(() => Number(route.params.id))

const loading = ref(false)
const error = ref(null)
const candidate = ref(null)
const showRaw = ref(false)
const showTranslationEditor = ref(false)
const translationForm = ref({
  translated_title: '',
  translated_description: '',
})

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
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
  if (key === 'nasa_watch_the_skies') return 'NASA WTS'
  if (key === 'nasa') return 'NASA'
  return key || '-'
}

function sourceBadgeStyle(source) {
  const key = String(source || '').toLowerCase()
  if (key === 'astropixels') {
    return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(30,64,175,.35); background:rgba(30,64,175,.12); font-size:12px;'
  }
  if (key === 'imo') {
    return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(6,95,70,.35); background:rgba(6,95,70,.12); font-size:12px;'
  }
  if (key === 'nasa') {
    return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(107,33,168,.35); background:rgba(107,33,168,.12); font-size:12px;'
  }
  return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:rgb(var(--color-surface-rgb) / .08); font-size:12px;'
}

function translationStatusLabel(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (normalized === 'done' || normalized === 'translated') return 'Translated'
  if (normalized === 'failed' || normalized === 'error') return 'Failed'
  return 'Pending'
}

function translationStatusStyle(value) {
  const label = translationStatusLabel(value)
  if (label === 'Translated') {
    return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(22,163,74,.35); background:rgba(22,163,74,.12); font-size:12px;'
  }
  if (label === 'Failed') {
    return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.12); font-size:12px;'
  }
  return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(245,158,11,.35); background:rgba(245,158,11,.12); font-size:12px;'
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
    error.value = fetchError?.response?.data?.message || 'Chyba pri nacitani detailu'
  } finally {
    loading.value = false
  }
}

async function approve() {
  if (!candidate.value) return

  const ok = await confirm({
    title: 'Schvalit kandidata',
    message: 'Naozaj chces schvalit tohto kandidata?',
    confirmText: 'Schvalit',
    cancelText: 'Zrusit',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  try {
    await eventCandidates.approve(candidate.value.id)
    toast.success('Kandidat bol schvaleny.')
    router.push('/admin/event-candidates')
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Approve zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

async function reject() {
  if (!candidate.value) return

  const ok = await confirm({
    title: 'Zamietnut kandidata',
    message: 'Naozaj chces zamietnut tohto kandidata?',
    confirmText: 'Zamietnut',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  try {
    await eventCandidates.reject(candidate.value.id)
    toast.success('Kandidat bol zamietnuty.')
    router.push('/admin/event-candidates')
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Reject zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

async function retranslate() {
  if (!candidate.value) return

  loading.value = true
  error.value = null

  try {
    await eventCandidates.retranslate(candidate.value.id)
    toast.success('Kandidat bol zaradeny na novy preklad.')
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Retranslate zlyhal'
    toast.error(error.value)
    loading.value = false
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
    toast.error('Prelozeny nazov je povinny.')
    return
  }

  loading.value = true
  error.value = null
  try {
    await eventCandidates.updateTranslation(candidate.value.id, {
      translated_title: title,
      translated_description: String(translationForm.value.translated_description || '').trim() || null,
    })
    toast.success('Preklad bol ulozeny.')
    showTranslationEditor.value = false
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Ulozenie prekladu zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div style="max-width: 940px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px;">
      <div>
        <button
          @click="router.back()"
          style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
        >
          &larr; Spat
        </button>

        <h1 style="margin:12px 0 6px;">Candidate #{{ id }}</h1>
        <div v-if="candidate" style="opacity:.8; font-size: 14px;">
          {{ candidateDisplayTitle(candidate) }}
        </div>
      </div>

      <div v-if="candidate" style="text-align:right; opacity:.85; font-size: 14px;">
        <div><b>Status:</b> {{ candidate.status }}</div>
        <div><b>Type:</b> {{ candidate.type }}</div>
      </div>
    </div>

    <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
      {{ error }}
    </div>
    <div v-if="loading" style="margin-top: 12px; opacity: .85;">
      Loading...
    </div>

    <div v-if="candidate && !loading" style="margin-top: 16px; display:grid; gap: 12px;">
      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Meta</h3>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">ID</div><div>{{ candidate.id }}</div>

          <div style="opacity:.75;">Type</div>
          <div>{{ candidate.type }} <span style="opacity:.7;">(raw: {{ candidate.raw_type || '-' }})</span></div>

          <div style="opacity:.75;">Short</div><div>{{ candidateDisplayShort(candidate) }}</div>

          <div style="opacity:.75;">Canonical key</div>
          <div style="word-break:break-all;">{{ candidate.canonical_key || '-' }}</div>

          <div style="opacity:.75;">Confidence</div>
          <div>{{ formatConfidence(candidate.confidence_score) }}</div>

          <div style="opacity:.75;">Matched sources</div>
          <div style="display:flex; flex-wrap:wrap; gap:6px;">
            <span
              v-for="src in normalizeSources(candidate.matched_sources)"
              :key="`detail-matched-${src}`"
              :style="sourceBadgeStyle(src)"
            >
              {{ sourceLabel(src) }}
            </span>
            <span v-if="normalizeSources(candidate.matched_sources).length === 0">-</span>
          </div>

          <div style="opacity:.75;">Created</div><div>{{ formatDate(candidate.created_at) }}</div>
          <div style="opacity:.75;">Updated</div><div>{{ formatDate(candidate.updated_at) }}</div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Translation</h3>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">Status</div>
          <div>
            <span :style="translationStatusStyle(candidate.translation_status)">
              {{ translationStatusLabel(candidate.translation_status) }}
            </span>
          </div>

          <div style="opacity:.75;">Last error</div>
          <div>{{ candidate.translation_error || '-' }}</div>

          <div style="opacity:.75;">Translated at</div>
          <div>{{ formatDate(candidate.translated_at) }}</div>

          <div style="opacity:.75;">Final title (SK)</div>
          <div>{{ candidateDisplayTitle(candidate) }}</div>

          <div style="opacity:.75;">Final description (SK)</div>
          <div>{{ candidateDisplayDescription(candidate) }}</div>
        </div>

        <div style="margin-top:12px;">
          <button
            type="button"
            :disabled="loading"
            @click="openTranslationEditor"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit; margin-right:8px;"
          >
            Upravit preklad
          </button>
          <button
            type="button"
            data-testid="retranslate-btn"
            :disabled="loading"
            @click="retranslate"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-primary-rgb) / .35); background:rgb(var(--color-primary-rgb) / .12); color:inherit;"
          >
            Prelozit znova
          </button>
        </div>

        <div
          v-if="showTranslationEditor"
          style="margin-top:12px; padding:12px; border:1px solid rgb(var(--color-surface-rgb) / .12); border-radius:12px; display:grid; gap:8px;"
        >
          <div style="font-weight:600;">Rucna uprava prekladu</div>
          <input
            v-model="translationForm.translated_title"
            type="text"
            :disabled="loading"
            placeholder="Prelozeny nazov"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
          <textarea
            v-model="translationForm.translated_description"
            rows="5"
            :disabled="loading"
            placeholder="Prelozeny popis"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          ></textarea>
          <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button
              type="button"
              :disabled="loading"
              @click="showTranslationEditor = false"
              style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
            >
              Zrusit
            </button>
            <button
              type="button"
              :disabled="loading"
              @click="saveTranslationEdit"
              style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-primary-rgb) / .35); background:rgb(var(--color-primary-rgb) / .12); color:inherit;"
            >
              Ulozit preklad
            </button>
          </div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Time</h3>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">Start</div><div>{{ formatDate(candidate.start_at) }}</div>
          <div style="opacity:.75;">End</div><div>{{ formatDate(candidate.end_at) }}</div>
          <div style="opacity:.75;">Max</div><div>{{ formatDate(candidate.max_at) }}</div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Source</h3>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">Source name</div>
          <div>
            <span :style="sourceBadgeStyle(candidate.source_name)">{{ sourceLabel(candidate.source_name) }}</span>
          </div>

          <div style="opacity:.75;">Source URL</div>
          <div>
            <a :href="candidate.source_url" target="_blank" rel="noreferrer">open source</a>
          </div>

          <div style="opacity:.75;">Source UID</div><div style="word-break:break-all;">{{ candidate.source_uid }}</div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Review</h3>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button
            @click="approve"
            :disabled="!canReview()"
            style="padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-success-rgb) / .10); color:inherit;"
          >
            Publikovat
          </button>

          <button
            @click="reject"
            :disabled="!canReview()"
            style="padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-danger-rgb) / .10); color:inherit;"
          >
            Zamietnut
          </button>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <h3 style="margin:0;">Raw payload</h3>

          <button
            @click="showRaw = !showRaw"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            {{ showRaw ? 'Hide' : 'Show' }}
          </button>
        </div>

        <pre
          v-if="showRaw"
          style="margin-top:10px; white-space:pre-wrap; max-height:320px; overflow:auto; border:1px solid rgb(var(--color-surface-rgb) / .18); border-radius:10px; padding:10px;"
        >{{ candidate.raw_payload ?? '' }}</pre>
      </section>
    </div>
  </div>
</template>
