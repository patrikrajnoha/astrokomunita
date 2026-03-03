<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import { getEvents } from '@/services/api/admin/events'
import { draftNewsletterCopy, getAdminAiConfig, primeNewsletterInsights } from '@/services/api/admin/ai'
import {
  getNewsletterPreview,
  getNewsletterRuns,
  sendNewsletterPreview,
  sendNewsletter,
  updateNewsletterFeaturedEvents,
} from '@/services/api/admin/newsletter'

const loading = ref(false)
const savingSelection = ref(false)
const sending = ref(false)
const previewSending = ref(false)
const aiConfigLoading = ref(false)
const aiPrimeLoading = ref(false)
const aiPrimeError = ref('')
const aiPrimeStatus = ref('idle')
const aiPrimeLastRun = ref(null)
const aiPrimeResult = ref(null)
const aiPrimeLimit = ref(5)
const aiPrimeNotice = ref('')
const aiPrimeRawStatus = ref(null)
const aiDraftLoading = ref(false)
const aiDraftError = ref('')
const aiDraftStatus = ref('idle')
const aiDraftLastRun = ref(null)
const aiDraftResult = ref(null)
const aiDraftSelectedIndex = ref(0)
const aiDraftNotice = ref('')
const error = ref('')
const success = ref('')
const previewEmail = ref('')
const newsletterSubject = ref('')
const newsletterIntro = ref('')
const newsletterTipText = ref('')
const localCopyEdited = ref(false)

const preview = ref(null)
const runs = ref([])
const aiConfig = ref(null)
const selectedEventIds = ref([])
const candidateEvents = ref([])
const maxFeaturedEvents = ref(10)

const sendOptions = reactive({
  force: false,
  dry_run: false,
})

const selectedCount = computed(() => selectedEventIds.value.length)
const aiEnabled = computed(() => Boolean(aiConfig.value?.events_ai_humanized_enabled))
const aiInsightsTtlSeconds = computed(() => Number(aiConfig.value?.insights_cache_ttl_seconds || 0))
const aiInsightsTtlDays = computed(() => {
  const seconds = aiInsightsTtlSeconds.value
  if (!Number.isFinite(seconds) || seconds <= 0) return null
  return Math.max(1, Math.round(seconds / 86400))
})
const aiPrimeMaxLimit = computed(() => {
  const value = Number(aiConfig.value?.prime_insights_max_limit || 10)
  if (!Number.isFinite(value)) return 10
  return Math.max(1, Math.min(Math.round(value), 10))
})
const aiNewsletterFeature = computed(() => aiConfig.value?.features?.newsletter_prime_insights || null)
const aiCopyFeature = computed(() => aiConfig.value?.features?.newsletter_copy_draft || null)
const aiCopyEnabled = computed(() => Boolean(aiCopyFeature.value?.enabled))
const aiPanelLastRun = computed(
  () => aiPrimeLastRun.value || aiNewsletterFeature.value?.last_run || null,
)
const canSaveSelection = computed(
  () =>
    !loading.value &&
    !savingSelection.value &&
    selectedCount.value <= maxFeaturedEvents.value,
)

function normalizeAiStatus(value, fallback = 'idle') {
  const normalized = String(value || '').trim().toLowerCase()
  if (['idle', 'success', 'fallback', 'error'].includes(normalized)) {
    return normalized
  }

  const fallbackNormalized = String(fallback || '').trim().toLowerCase()
  return ['idle', 'success', 'fallback', 'error'].includes(fallbackNormalized)
    ? fallbackNormalized
    : 'idle'
}

function defaultNewsletterSubject() {
  return 'Nebesky sprievodca: Tyzdenny newsletter'
}

function defaultNewsletterIntro() {
  const start = preview.value?.week?.start || '-'
  const end = preview.value?.week?.end || '-'
  return `Prehlad na tyzden ${start} az ${end}.`
}

function syncLocalCopyFieldsFromPreview() {
  if (localCopyEdited.value) return

  newsletterSubject.value = defaultNewsletterSubject()
  newsletterIntro.value = defaultNewsletterIntro()
  newsletterTipText.value = String(preview.value?.astronomical_tip || '').trim()
  if (!newsletterTipText.value) {
    newsletterTipText.value = 'Tip pripraveny z udalosti.'
  }
}

function markLocalCopyEdited() {
  localCopyEdited.value = true
}

async function load() {
  loading.value = true
  aiConfigLoading.value = true
  error.value = ''

  try {
    const [previewRes, runsRes, eventsRes, aiConfigRes] = await Promise.all([
      getNewsletterPreview(),
      getNewsletterRuns({ per_page: 20 }),
      getEvents({ per_page: 100 }),
      getAdminAiConfig(),
    ])

    preview.value = previewRes?.data?.data || null
    maxFeaturedEvents.value = Number(previewRes?.data?.meta?.max_featured_events || 10)
    selectedEventIds.value = Array.isArray(preview.value?.top_events)
      ? preview.value.top_events
          .map((row) => Number(row?.id || 0))
          .filter((id) => id > 0)
      : []

    runs.value = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    const eventsPayload = eventsRes?.data?.data || []
    candidateEvents.value = Array.isArray(eventsPayload)
      ? eventsPayload.map((item) => ({
          id: Number(item.id),
          title: item.title,
          start_at: item.start_at,
        }))
      : []

    aiConfig.value = aiConfigRes?.data?.data || null
    aiPrimeLimit.value = Math.max(1, Math.min(Number(aiPrimeLimit.value || 5), aiPrimeMaxLimit.value))
    if (aiConfig.value?.features?.newsletter_prime_insights?.last_run) {
      aiPrimeLastRun.value = aiConfig.value.features.newsletter_prime_insights.last_run
      aiPrimeStatus.value = normalizeAiStatus(aiPrimeLastRun.value?.status)
    }
    if (aiConfig.value?.features?.newsletter_copy_draft?.last_run) {
      aiDraftLastRun.value = aiConfig.value.features.newsletter_copy_draft.last_run
      aiDraftStatus.value = normalizeAiStatus(aiDraftLastRun.value?.status)
    }
    syncLocalCopyFieldsFromPreview()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa načítať dáta newslettera.'
  } finally {
    loading.value = false
    aiConfigLoading.value = false
  }
}

function isSelected(eventId) {
  return selectedEventIds.value.includes(Number(eventId))
}

function toggleSelected(eventId, checked) {
  const id = Number(eventId)
  if (id <= 0) return

  if (checked) {
    if (selectedEventIds.value.includes(id)) return
    if (selectedEventIds.value.length >= maxFeaturedEvents.value) return
    selectedEventIds.value = [...selectedEventIds.value, id]
    return
  }

  selectedEventIds.value = selectedEventIds.value.filter((value) => value !== id)
}

async function saveFeaturedEvents() {
  if (!canSaveSelection.value) return

  savingSelection.value = true
  error.value = ''
  success.value = ''
  try {
    await updateNewsletterFeaturedEvents({
      event_ids: selectedEventIds.value,
    })
    success.value = 'Vybrané udalosti boli uložené.'
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa uložiť vybrané udalosti.'
  } finally {
    savingSelection.value = false
  }
}

async function triggerPrimeInsights() {
  if (aiPrimeLoading.value) return
  if (!aiEnabled.value) {
    aiPrimeStatus.value = 'idle'
    aiPrimeError.value = 'AI pomocnik je momentalne vypnuty.'
    return
  }

  aiPrimeLoading.value = true
  aiPrimeError.value = ''
  aiPrimeNotice.value = ''
  aiPrimeRawStatus.value = null
  aiPrimeStatus.value = 'idle'
  aiPrimeResult.value = null

  const limit = Math.max(1, Math.min(Number(aiPrimeLimit.value || 5), aiPrimeMaxLimit.value))

  try {
    const response = await primeNewsletterInsights({ limit })
    aiPrimeResult.value = response?.data?.data || null
    aiPrimeLastRun.value = response?.data?.last_run || null
    aiPrimeStatus.value = normalizeAiStatus(aiPrimeLastRun.value?.status, 'success')
    if (
      Number(aiPrimeResult.value?.primed || 0) === 0
      && Number(aiPrimeResult.value?.failed || 0) === 0
    ) {
      aiPrimeStatus.value = 'idle'
    }
    await load()
    aiPrimeNotice.value = 'Tip pripraveny.'
  } catch (e) {
    aiPrimeStatus.value = 'error'
    const responseStatus = Number(e?.response?.status || 0)
    const retryAfterSeconds = Number(e?.response?.data?.retry_after_seconds || 0)
    aiPrimeRawStatus.value = responseStatus > 0 ? responseStatus : null

    if (responseStatus === 409 && retryAfterSeconds > 0) {
      aiPrimeError.value = `Skus znova o ${Math.ceil(retryAfterSeconds)} s.`
    } else {
      aiPrimeError.value = 'Tip sa nepodarilo pripravit.'
    }
  } finally {
    aiPrimeLoading.value = false
  }
}

async function triggerDraftCopy() {
  if (aiDraftLoading.value) return
  if (!aiCopyEnabled.value) {
    aiDraftStatus.value = 'idle'
    aiDraftError.value = 'AI navrh copy je momentalne vypnuty.'
    return
  }

  aiDraftLoading.value = true
  aiDraftError.value = ''
  aiDraftNotice.value = ''
  aiDraftStatus.value = 'idle'
  aiDraftResult.value = null
  aiDraftSelectedIndex.value = 0

  try {
    const response = await draftNewsletterCopy()
    const payload = response?.data || {}
    aiDraftResult.value = {
      subjects: Array.isArray(payload?.subjects) ? payload.subjects : [],
      intro: String(payload?.intro || ''),
      tip_text: String(payload?.tip_text || ''),
      fallback_used: Boolean(payload?.fallback_used),
    }
    aiDraftLastRun.value = payload?.last_run || null
    aiDraftStatus.value = normalizeAiStatus(payload?.status, 'success')
    aiDraftNotice.value = 'Navrh pripraveny.'
  } catch (e) {
    aiDraftStatus.value = 'error'
    aiDraftError.value = e?.response?.data?.message || e?.userMessage || 'Navrh sa nepodarilo pripravit.'
  } finally {
    aiDraftLoading.value = false
  }
}

function applyDraftCopy() {
  const draft = aiDraftResult.value
  if (!draft || !Array.isArray(draft.subjects) || draft.subjects.length === 0) return

  const selectedIndex = Math.max(
    0,
    Math.min(Number(aiDraftSelectedIndex.value || 0), draft.subjects.length - 1),
  )
  const selectedSubject = String(draft.subjects[selectedIndex] || draft.subjects[0] || '').trim()
  const intro = String(draft.intro || '').trim()
  const tipText = String(draft.tip_text || '').trim()

  if (selectedSubject) {
    newsletterSubject.value = selectedSubject
  }
  if (intro) {
    newsletterIntro.value = intro
  }
  if (tipText) {
    newsletterTipText.value = tipText
  }

  markLocalCopyEdited()
  aiDraftNotice.value = 'Navrh aplikovany do local preview.'
}

async function triggerSend() {
  if (sending.value) return

  sending.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await sendNewsletter({
      force: Boolean(sendOptions.force),
      dry_run: Boolean(sendOptions.dry_run),
    })

    const reason = response?.data?.reason || 'created'
    const runId = response?.data?.data?.id
    success.value = runId
      ? `Newsletter run ${runId} accepted (${reason}).`
      : `Newsletter action completed (${reason}).`

    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa spustiť odoslanie newslettera.'
  } finally {
    sending.value = false
  }
}

async function triggerPreviewSend() {
  if (previewSending.value) return

  const normalizedEmail = String(previewEmail.value || '').trim()
  if (!normalizedEmail) {
    error.value = 'Email pre náhľad je povinný.'
    success.value = ''
    return
  }

  previewSending.value = true
  error.value = ''
  success.value = ''
  try {
    const payload = {
      email: normalizedEmail,
    }
    const subjectOverride = String(newsletterSubject.value || '').trim()
    const introOverride = String(newsletterIntro.value || '').trim()
    const tipOverride = String(newsletterTipText.value || '').trim()

    if (subjectOverride) {
      payload.subject_override = subjectOverride
    }
    if (introOverride) {
      payload.intro_override = introOverride
    }
    if (tipOverride) {
      payload.tip_override = tipOverride
    }

    const response = await sendNewsletterPreview(payload)

    const data = response?.data?.data || {}
    const email = data?.email || normalizedEmail
    const eventsCount = Number(data?.events_count || 0)
    const articlesCount = Number(data?.articles_count || 0)

    success.value = `Náhľad odoslaný na ${email} (${eventsCount} udalosti, ${articlesCount} články).`
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Nepodarilo sa odoslať preview email.'
  } finally {
    previewSending.value = false
  }
}

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(load)
</script>

<template>
  <AdminPageShell
    title="Newsletter"
    subtitle="Vyber top obsah týždňa, skontroluj payload a spusti newsletter run."
  >
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-if="success" class="alert alert-success">{{ success }}</div>

    <AdminAiActionPanel
      v-if="!aiConfigLoading && aiConfig"
      title="AI pomocnik"
      description="Pripravi AI tip a navrhne predmet s uvodom."
      action-label="Pripravit AI tip"
      :enabled="aiEnabled"
      :status="aiPrimeStatus"
      :latency-ms="aiPanelLastRun?.latency_ms ?? null"
      :last-run-at="aiPanelLastRun?.updated_at ?? null"
      :retry-count="aiPanelLastRun?.retry_count ?? null"
      :raw-status-code="aiPrimeRawStatus"
      :is-loading="aiPrimeLoading"
      :error-message="aiPrimeError"
      @run="triggerPrimeInsights"
    >
      <div class="aiInlineActions">
        <button
          type="button"
          class="aiSecondaryBtn"
          :disabled="aiDraftLoading || !aiCopyEnabled"
          @click="triggerDraftCopy"
        >
          {{ aiDraftLoading ? 'Pripravujem navrh...' : 'Navrhnut predmet a uvod' }}
        </button>
        <span
          v-if="aiPrimeResult && Number(aiPrimeResult.fallback || 0) > 0"
          class="aiBadge aiBadge--fallback"
        >
          Pouzity fallback
        </span>
        <span
          v-if="aiDraftResult?.fallback_used"
          class="aiBadge aiBadge--fallback"
        >
          Pouzity fallback
        </span>
      </div>
      <p v-if="aiPrimeNotice" class="aiNotice">{{ aiPrimeNotice }}</p>
      <p v-if="aiDraftNotice" class="aiNotice">{{ aiDraftNotice }}</p>
      <p v-if="aiDraftError" class="aiError">{{ aiDraftError }}</p>

      <div
        v-if="aiDraftResult && Array.isArray(aiDraftResult.subjects) && aiDraftResult.subjects.length === 3"
        class="aiDraftBox"
      >
        <label
          v-for="(subject, index) in aiDraftResult.subjects"
          :key="`subject-${index}`"
          class="aiDraftOption"
        >
          <input
            v-model.number="aiDraftSelectedIndex"
            type="radio"
            name="ai-subject-choice"
            :value="index"
          />
          <span>{{ subject }}</span>
        </label>
        <button type="button" :disabled="aiDraftLoading" @click="applyDraftCopy">Pouzit</button>
      </div>

      <p v-else-if="!aiPrimeNotice && !aiDraftNotice" class="muted">
        Klikni na akciu a navrh sa pripravi lokalne.
      </p>
      <template #advanced>
        <div class="aiRow">
          <label for="ai-prime-limit" class="aiLabel">Limit eventov</label>
          <input
            id="ai-prime-limit"
            v-model.number="aiPrimeLimit"
            type="number"
            min="1"
            :max="aiPrimeMaxLimit"
            class="aiInput"
            :disabled="aiPrimeLoading"
          />
        </div>
        <p class="muted">
          Cache TTL:
          {{ aiInsightsTtlSeconds > 0 ? `${aiInsightsTtlSeconds}s` : '-' }}
          <span v-if="aiInsightsTtlDays">(~{{ aiInsightsTtlDays }} dni)</span>
        </p>
        <p v-if="aiPrimeResult" class="muted">
          Spracovane: {{ Number(aiPrimeResult.processed || 0) }},
          primed: {{ Number(aiPrimeResult.primed || 0) }},
          fallback: {{ Number(aiPrimeResult.fallback || 0) }},
          failed: {{ Number(aiPrimeResult.failed || 0) }}.
        </p>
        <p class="muted">
          Draft-copy:
          {{ aiDraftStatus }}
          <span v-if="aiDraftLastRun?.updated_at">
            ({{ formatDateTime(aiDraftLastRun.updated_at) }})
          </span>
        </p>
      </template>
    </AdminAiActionPanel>
    <section v-else class="card">
      <p class="muted">Načítavam AI konfiguráciu...</p>
    </section>

    <section class="card">
      <div class="cardHead">
        <h3>Vybrané udalosti na budúci týždeň</h3>
        <p class="counter">{{ selectedCount }}/{{ maxFeaturedEvents }}</p>
      </div>
      <p class="muted">Vyber max {{ maxFeaturedEvents }} udalosti pre sekciu "Top udalosti budúceho týždňa".</p>

      <div class="eventsGrid">
        <label
          v-for="event in candidateEvents"
          :key="event.id"
          class="eventOption"
          :class="{ active: isSelected(event.id) }"
        >
          <input
            type="checkbox"
            :checked="isSelected(event.id)"
            :disabled="savingSelection || (!isSelected(event.id) && selectedCount >= maxFeaturedEvents)"
            @change="toggleSelected(event.id, $event.target.checked)"
          />
          <span class="eventTitle">{{ event.title }}</span>
          <span class="eventDate">{{ formatDateTime(event.start_at) }}</span>
        </label>
      </div>

      <div class="actions">
        <button type="button" :disabled="!canSaveSelection" @click="saveFeaturedEvents">
          {{ savingSelection ? 'Ukladám...' : 'Uložiť vybrané udalosti' }}
        </button>
      </div>
    </section>

    <section class="card">
      <h3>Náhľad payloadu</h3>
      <p v-if="loading" class="muted">Načítavam náhľad...</p>
      <template v-else>
        <p class="muted">
          Týždeň: {{ preview?.week?.start || '-' }} - {{ preview?.week?.end || '-' }}
        </p>

        <h4>Top udalosti</h4>
        <ul>
          <li v-for="event in preview?.top_events || []" :key="event.id">
            {{ event.title }}
          </li>
        </ul>

        <h4>Top články</h4>
        <ul>
          <li v-for="article in preview?.top_articles || []" :key="article.id">
            {{ article.title }} ({{ article.views }})
          </li>
        </ul>

        <h4>Astronomický tip</h4>
        <p class="muted">{{ preview?.astronomical_tip || '-' }}</p>
      </template>
    </section>

    <section class="card">
      <h3>Predmet a uvod (local preview)</h3>
      <p class="muted">AI navrh sa ulozi az cez existujuci newsletter flow.</p>

      <div class="copyEditor">
        <label class="copyField">
          <span>Predmet</span>
          <input
            v-model.trim="newsletterSubject"
            type="text"
            maxlength="80"
            class="copyInput"
            @input="markLocalCopyEdited"
          />
        </label>

        <label class="copyField">
          <span>Uvod</span>
          <textarea
            v-model.trim="newsletterIntro"
            rows="2"
            maxlength="280"
            class="copyTextarea"
            @input="markLocalCopyEdited"
          />
        </label>

        <label class="copyField">
          <span>Tip</span>
          <textarea
            v-model.trim="newsletterTipText"
            rows="3"
            maxlength="320"
            class="copyTextarea"
            @input="markLocalCopyEdited"
          />
        </label>
      </div>
    </section>

    <section class="card">
      <h3>Náhľad emailu</h3>
      <p v-if="loading" class="muted">Načítavam náhľad...</p>
      <article v-else class="emailPreview">
        <header class="emailHero">
          <p class="emailEyebrow">Nebeský sprievodca</p>
          <p class="emailSubjectLine">Predmet: {{ newsletterSubject || '-' }}</p>
          <h4 class="emailTitle">Top udalosti buduceho tyzdna</h4>
          <p class="emailIntro">{{ newsletterIntro || '-' }}</p>
        </header>

        <section class="emailSection">
          <h5>Top udalosti</h5>
          <ul>
            <li v-for="event in preview?.top_events || []" :key="`email-event-${event.id}`">
              <a :href="event.url || '#'" target="_blank" rel="noopener">{{ event.title || 'Udalosť' }}</a>
              <span>{{ event.start_at || '-' }}</span>
            </li>
            <li v-if="(preview?.top_events || []).length === 0">Tento týždeň zatiaľ nemá vybrané udalosti.</li>
          </ul>
        </section>

        <section class="emailSection">
          <h5>Najčítanejšie články (7 dní)</h5>
          <ul>
            <li v-for="article in preview?.top_articles || []" :key="`email-article-${article.id}`">
              <a :href="article.url || '#'" target="_blank" rel="noopener">{{ article.title || 'Článok' }}</a>
              <span>Čítania: {{ Number(article.views || 0) }}</span>
            </li>
            <li v-if="(preview?.top_articles || []).length === 0">Za posledný týždeň ešte nie sú dostupné články.</li>
          </ul>
        </section>

        <section class="emailSection">
          <h5>Astronomický tip týždňa</h5>
          <p>{{ newsletterTipText || '-' }}</p>
        </section>

        <footer class="emailFooter">
          <a class="emailBtn emailBtnPrimary" :href="preview?.cta?.calendar_url || '#'" target="_blank" rel="noopener">
            Otvoriť kalendár
          </a>
          <a class="emailBtn emailBtnSecondary" :href="preview?.cta?.events_url || '#'" target="_blank" rel="noopener">
            Prejsť na udalosti
          </a>
        </footer>
      </article>
    </section>

    <section class="card">
      <h3>Manuálne odoslanie</h3>
      <div class="toggles">
        <label class="toggleLabel">
          <input v-model="sendOptions.force" type="checkbox" />
          <span>Vynútiť odoslanie</span>
        </label>
        <label class="toggleLabel">
          <input v-model="sendOptions.dry_run" type="checkbox" />
          <span>Dry-run</span>
        </label>
      </div>
      <button type="button" :disabled="sending || loading" @click="triggerSend">
        {{ sending ? 'Odosielam...' : 'Spustiť newsletter run' }}
      </button>
    </section>

    <section class="card">
      <h3>Náhľad</h3>
      <p class="muted">Odošli preview email na existujúci používateľský účet.</p>
      <div class="actions">
        <input
          v-model.trim="previewEmail"
          type="email"
          class="previewInput"
          placeholder="user@example.com"
        />
        <button type="button" :disabled="previewSending || loading" @click="triggerPreviewSend">
          {{ previewSending ? 'Odosielam...' : 'Odoslať preview' }}
        </button>
      </div>
    </section>

    <section class="card">
      <h3>Predošlé runy</h3>
      <div v-if="runs.length === 0" class="muted">Zatiaľ žiadne runy.</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Week</th>
            <th>Status</th>
            <th>Total</th>
            <th>Sent</th>
            <th>Preview</th>
            <th>Unsubscribe</th>
            <th>Failed</th>
            <th>Flags</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="run in runs" :key="run.id">
            <td>{{ run.id }}</td>
            <td>{{ run.week_start_date }}</td>
            <td>{{ run.status }}</td>
            <td>{{ run.total_recipients }}</td>
            <td>{{ run.sent_count }}</td>
            <td>{{ run.preview_count }}</td>
            <td>{{ run.unsubscribe_count }}</td>
            <td>{{ run.failed_count }}</td>
            <td>
              <span v-if="run.forced">forced </span>
              <span v-if="run.dry_run">dry-run</span>
            </td>
            <td>{{ formatDateTime(run.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 14px;
  background: rgb(var(--color-bg-rgb) / 0.65);
}

.card + .card {
  margin-top: 12px;
}

.cardHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.counter {
  margin: 0;
  font-weight: 700;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.eventsGrid {
  margin-top: 10px;
  display: grid;
  gap: 8px;
  max-height: 260px;
  overflow: auto;
}

.eventOption {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 8px 10px;
  padding: 8px 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
}

.eventOption.active {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.eventTitle {
  font-weight: 600;
}

.eventDate {
  grid-column: 2 / 3;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
}

.actions,
.toggles {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
}

.toggleLabel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

button {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  border-radius: 10px;
  padding: 8px 12px;
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: inherit;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 8px;
}

.table th,
.table td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 8px 6px;
  text-align: left;
  font-size: 13px;
}

.alert {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 10px;
}

.alert-error {
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
  color: rgb(185 28 28);
}

.alert-success {
  border: 1px solid rgb(34 197 94 / 0.35);
  background: rgb(34 197 94 / 0.12);
  color: rgb(22 101 52);
}

.aiRow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.aiInlineActions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.aiSecondaryBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.3);
  background: transparent;
}

.aiError {
  margin: 0;
  font-size: 13px;
  color: rgb(185 28 28);
}

.aiDraftBox {
  display: grid;
  gap: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
  padding: 10px;
}

.aiDraftOption {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.aiLabel {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.aiInput {
  width: 86px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.75);
  color: inherit;
  padding: 6px 8px;
}

.aiBadge {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  padding: 2px 8px;
  font-size: 11px;
}

.aiBadge--fallback {
  border-color: rgb(245 158 11 / 0.42);
  background: rgb(245 158 11 / 0.12);
}

.aiNotice {
  margin: 0;
  font-size: 13px;
  color: rgb(22 101 52);
}

.copyEditor {
  display: grid;
  gap: 10px;
  margin-top: 10px;
}

.copyField {
  display: grid;
  gap: 6px;
  font-size: 13px;
}

.copyInput,
.copyTextarea {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: inherit;
  padding: 8px 10px;
}

.copyTextarea {
  resize: vertical;
}

.previewInput {
  min-width: 260px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: inherit;
  padding: 8px 10px;
}

.emailPreview {
  margin-top: 10px;
  border: 1px solid #263247;
  border-radius: 16px;
  background: #121a2a;
  color: #e5ecff;
  overflow: hidden;
}

.emailHero {
  padding: 24px;
  background: linear-gradient(135deg, #1f3c88, #0f7490);
}

.emailEyebrow {
  margin: 0 0 8px;
  font-size: 12px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #dbeafe;
}

.emailTitle {
  margin: 0;
  font-size: 28px;
  line-height: 1.2;
  color: #ffffff;
}

.emailSubjectLine {
  margin: 0 0 8px;
  font-size: 12px;
  color: #dbeafe;
}

.emailIntro {
  margin: 10px 0 0;
  font-size: 14px;
  line-height: 1.5;
  color: #e2e8f0;
}

.emailSection {
  padding: 16px 24px 4px;
}

.emailSection h5 {
  margin: 0 0 10px;
  font-size: 18px;
  color: #ffffff;
}

.emailSection ul {
  margin: 0;
  padding: 0 0 0 18px;
  color: #dbeafe;
}

.emailSection li {
  margin-bottom: 10px;
  line-height: 1.45;
}

.emailSection a {
  color: #93c5fd;
  text-decoration: none;
  font-weight: 700;
}

.emailSection span {
  display: block;
  margin-top: 2px;
  font-size: 13px;
  color: #9fb2d1;
}

.emailSection p {
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  color: #dbeafe;
}

.emailFooter {
  padding: 18px 24px 24px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.emailBtn {
  display: inline-block;
  padding: 10px 14px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 700;
  font-size: 14px;
  color: #ffffff;
}

.emailBtnPrimary {
  background: #2563eb;
}

.emailBtnSecondary {
  background: #0f766e;
}
</style>




