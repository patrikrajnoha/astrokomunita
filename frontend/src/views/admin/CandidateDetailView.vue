<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminSectionHeader from '@/components/admin/AdminSectionHeader.vue'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import { eventCandidates } from '@/services/eventCandidates'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { candidateDisplayDescription, candidateDisplayShort, candidateDisplayTitle } from '@/utils/translatedFields'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const id = computed(() => Number(route.params.id))
const candidateListRoute = computed(() => ({
  name: 'admin.event-candidates',
  query: { ...route.query },
}))

const loading = ref(false)
const error = ref(null)
const candidate = ref(null)
const retranslateLoading = ref(false)
const retranslateStatus = ref('idle')
const retranslateMessage = ref('')
const retranslateError = ref('')
const retranslateRawStatus = ref(null)
const showRaw = ref(false)
const showTranslationEditor = ref(false)
const translationForm = ref({
  translated_title: '',
  translated_description: '',
})
const aiTranslationStatus = computed(() => {
  const normalized = String(candidate.value?.translation_status || '').trim().toLowerCase()
  if (normalized === 'done' || normalized === 'translated') return 'success'
  if (normalized === 'failed' || normalized === 'error') return 'error'
  if (normalized === 'pending') return 'idle'
  return 'idle'
})
const aiPanelStatus = computed(() => {
  if (retranslateStatus.value !== 'idle') {
    return retranslateStatus.value
  }

  return aiTranslationStatus.value
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
  if (normalized === 'done' || normalized === 'translated') return 'PreloĹľenĂ©'
  if (normalized === 'failed' || normalized === 'error') return 'Zlyhalo'
  return 'ÄŚakĂˇ'
}

function translationStatusStyle(value) {
  const label = translationStatusLabel(value)
  if (label === 'PreloĹľenĂ©') {
    return 'display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(22,163,74,.35); background:rgba(22,163,74,.12); font-size:12px;'
  }
  if (label === 'Zlyhalo') {
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
    error.value = fetchError?.response?.data?.message || 'Chyba pri naÄŤĂ­tanĂ­ detailu'
  } finally {
    loading.value = false
  }
}

async function approve() {
  if (!candidate.value) return

  const ok = await confirm({
    title: 'SchvĂˇliĹĄ kandidĂˇta',
    message: 'Naozaj chceĹˇ schvĂˇliĹĄ tohto kandidĂˇta?',
    confirmText: 'SchvĂˇliĹĄ',
    cancelText: 'ZruĹˇiĹĄ',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  try {
    await eventCandidates.approve(candidate.value.id)
    toast.success('KandidĂˇt bol schvĂˇlenĂ˝.')
    router.push(candidateListRoute.value)
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'SchvĂˇlenie zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

async function reject() {
  if (!candidate.value) return

  const ok = await confirm({
    title: 'ZamietnuĹĄ kandidĂˇta',
    message: 'Naozaj chceĹˇ zamietnuĹĄ tohto kandidĂˇta?',
    confirmText: 'ZamietnuĹĄ',
    cancelText: 'ZruĹˇiĹĄ',
    variant: 'danger',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  try {
    await eventCandidates.reject(candidate.value.id)
    toast.success('KandidĂˇt bol zamietnutĂ˝.')
    router.push(candidateListRoute.value)
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'Zamietnutie zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

async function retranslate() {
  if (!candidate.value || retranslateLoading.value) return

  retranslateLoading.value = true
  retranslateStatus.value = 'idle'
  retranslateMessage.value = ''
  retranslateError.value = ''
  retranslateRawStatus.value = null
  error.value = null

  try {
    const response = await eventCandidates.retranslate(candidate.value.id)
    const fallbackUsed = Boolean(
      response?.fallback_used
      || response?.candidate?.fallback_used
      || response?.candidate?.translation_fallback_used,
    )

    retranslateStatus.value = fallbackUsed ? 'fallback' : 'success'
    retranslateMessage.value = fallbackUsed ? 'Použitý fallback' : 'Dokončené'
    toast.success('Preklad bol znovu spustený.')
    await load()
  } catch (fetchError) {
    retranslateStatus.value = 'error'
    retranslateError.value = 'Nepodarilo sa preložiť znova.'
    retranslateRawStatus.value = Number(fetchError?.response?.status || 0) || null
    error.value = fetchError?.response?.data?.message || 'Retranslate zlyhal'
    toast.error(retranslateError.value)
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
    toast.error('PreloĹľenĂ˝ nĂˇzov je povinnĂ˝.')
    return
  }

  loading.value = true
  error.value = null
  try {
    await eventCandidates.updateTranslation(candidate.value.id, {
      translated_title: title,
      translated_description: String(translationForm.value.translated_description || '').trim() || null,
    })
    toast.success('Preklad bol uloĹľenĂ˝.')
    showTranslationEditor.value = false
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || 'UloĹľenie prekladu zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div style="max-width: 940px; margin: 0 auto; padding: 24px 16px;">
    <AdminSectionHeader
      section="events"
      :title="`Detail kandidata #${id}`"
      back-label="Spat na kandidatov"
      :back-to="{ name: 'admin.event-candidates' }"
    />

    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px;">
      <div>
        <h1 style="margin:0 0 6px;">KandidĂˇt #{{ id }}</h1>
        <div v-if="candidate" style="opacity:.8; font-size: 14px;">
          {{ candidateDisplayTitle(candidate) }}
        </div>
      </div>

      <div v-if="candidate" style="text-align:right; opacity:.85; font-size: 14px;">
        <div><b>Status:</b> {{ candidate.status }}</div>
        <div><b>Typ:</b> {{ candidate.type }}</div>
      </div>
    </div>

    <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
      {{ error }}
    </div>
    <div v-if="loading" style="margin-top: 12px; opacity: .85;">
      NaÄŤĂ­tavam...
    </div>

    <div v-if="candidate && !loading" style="margin-top: 16px; display:grid; gap: 12px;">
      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Meta</h3>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">ID</div><div>{{ candidate.id }}</div>

          <div style="opacity:.75;">Typ</div>
          <div>{{ candidate.type }} <span style="opacity:.7;">(raw: {{ candidate.raw_type || '-' }})</span></div>

          <div style="opacity:.75;">SkrĂˇtenĂ˝ popis</div><div>{{ candidateDisplayShort(candidate) }}</div>

          <div style="opacity:.75;">KanonickĂ˝ kÄľĂşÄŤ</div>
          <div style="word-break:break-all;">{{ candidate.canonical_key || '-' }}</div>

          <div style="opacity:.75;">DĂ´veryhodnosĹĄ</div>
          <div>{{ formatConfidence(candidate.confidence_score) }}</div>

          <div style="opacity:.75;">SpĂˇrovanĂ© zdroje</div>
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

          <div style="opacity:.75;">VytvorenĂ©</div><div>{{ formatDate(candidate.created_at) }}</div>
          <div style="opacity:.75;">AktualizovanĂ©</div><div>{{ formatDate(candidate.updated_at) }}</div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Preklad</h3>

        <AdminAiActionPanel
          title="AI pomocnik"
          description="Spusti preklad znova pre tohto kandidata."
          action-label="Preložiť znova"
          :enabled="Boolean(candidate)"
          :status="aiPanelStatus"
          :latency-ms="null"
          :last-run-at="candidate?.translated_at || candidate?.updated_at || null"
          :raw-status-code="retranslateRawStatus"
          :is-loading="retranslateLoading"
          :error-message="retranslateError"
          @run="retranslate"
        >
          <p v-if="retranslateMessage" style="margin:0; font-size:12px; opacity:.9;">{{ retranslateMessage }}</p>
          <span
            v-if="retranslateStatus === 'fallback'"
            style="display:inline-flex; border-radius:999px; border:1px solid rgb(245 158 11 / .45); background:rgb(245 158 11 / .12); padding:2px 8px; font-size:11px;"
          >
            Použitý fallback
          </span>
          <template #advanced>
            <p style="margin:0; font-size:12px; opacity:.85;">
              Posledna chyba: {{ candidate.translation_error || '-' }}
            </p>
          </template>
        </AdminAiActionPanel>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">Stav</div>
          <div>
            <span :style="translationStatusStyle(candidate.translation_status)">
              {{ translationStatusLabel(candidate.translation_status) }}
            </span>
          </div>

          <div style="opacity:.75;">PoslednĂˇ chyba</div>
          <div>{{ candidate.translation_error || '-' }}</div>

          <div style="opacity:.75;">PreloĹľenĂ© o</div>
          <div>{{ formatDate(candidate.translated_at) }}</div>

          <div style="opacity:.75;">FinĂˇlny nĂˇzov (SK)</div>
          <div>{{ candidateDisplayTitle(candidate) }}</div>

          <div style="opacity:.75;">FinĂˇlny popis (SK)</div>
          <div>{{ candidateDisplayDescription(candidate) }}</div>
        </div>

        <div style="margin-top:12px;">
          <button
            type="button"
            :disabled="loading"
            @click="openTranslationEditor"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit; margin-right:8px;"
          >
            UpraviĹĄ preklad
          </button>
        </div>

        <div
          v-if="showTranslationEditor"
          style="margin-top:12px; padding:12px; border:1px solid rgb(var(--color-surface-rgb) / .12); border-radius:12px; display:grid; gap:8px;"
        >
          <div style="font-weight:600;">RuÄŤnĂˇ Ăşprava prekladu</div>
          <input
            v-model="translationForm.translated_title"
            type="text"
            :disabled="loading"
            placeholder="PreloĹľenĂ˝ nĂˇzov"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
          <textarea
            v-model="translationForm.translated_description"
            rows="5"
            :disabled="loading"
            placeholder="PreloĹľenĂ˝ popis"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          ></textarea>
          <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button
              type="button"
              :disabled="loading"
              @click="showTranslationEditor = false"
              style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
            >
              ZruĹˇiĹĄ
            </button>
            <button
              type="button"
              :disabled="loading"
              @click="saveTranslationEdit"
              style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-primary-rgb) / .35); background:rgb(var(--color-primary-rgb) / .12); color:inherit;"
            >
              UloĹľiĹĄ preklad
            </button>
          </div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">ÄŚas</h3>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">Start</div><div>{{ formatDate(candidate.start_at) }}</div>
          <div style="opacity:.75;">End</div><div>{{ formatDate(candidate.end_at) }}</div>
          <div style="opacity:.75;">Max</div><div>{{ formatDate(candidate.max_at) }}</div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">Zdroj</h3>

        <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">NĂˇzov zdroja</div>
          <div>
            <span :style="sourceBadgeStyle(candidate.source_name)">{{ sourceLabel(candidate.source_name) }}</span>
          </div>

          <div style="opacity:.75;">URL zdroja</div>
          <div>
            <a :href="candidate.source_url" target="_blank" rel="noreferrer">otvoriĹĄ zdroj</a>
          </div>

          <div style="opacity:.75;">UID zdroja</div><div style="word-break:break-all;">{{ candidate.source_uid }}</div>
        </div>
      </section>

      <section style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;">
        <h3 style="margin:0 0 10px;">ModerĂˇcia</h3>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button
            @click="approve"
            :disabled="!canReview()"
            style="padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-success-rgb) / .10); color:inherit;"
          >
            PublikovaĹĄ
          </button>

          <button
            @click="reject"
            :disabled="!canReview()"
            style="padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-danger-rgb) / .10); color:inherit;"
          >
            ZamietnuĹĄ
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
            {{ showRaw ? 'SkryĹĄ' : 'ZobraziĹĄ' }}
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

