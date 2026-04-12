<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { getEventTranslationHealth } from '@/services/api/admin/eventSources'
import { useRoute, useRouter } from 'vue-router'
import { eventCandidates } from '@/services/eventCandidates'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import CandidateDuplicatesPanel from './candidates/CandidateDuplicatesPanel.vue'
import CandidateCrawledFilterPanel from './candidates/CandidateCrawledFilterPanel.vue'
import CandidateCrawledResults from './candidates/CandidateCrawledResults.vue'
import CandidateManualTab from './candidates/CandidateManualTab.vue'
import { useCandidatesManualEvents } from './candidates/useCandidatesManualEvents'
import { useCandidatesCrawledTab } from './candidates/useCandidatesCrawledTab'
import {
  formatConfidence,
  moonPhaseLabel,
  normalizeSources,
  normalizeTranslationMode,
  normalizeTranslationStatus,
  sourceLabel,
} from './candidatesListView.utils'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()
const auth = useAuthStore()

const activeTab = ref('crawled')
const showScoreInList = ref(false)
const aiDescriptionRunning = ref(false)
const aiDescriptionProgress = ref(0)
const aiDescriptionLabel = ref('')
const aiDescriptionScope = ref('all')
const aiDescriptionMode = ref('template')
const aiDescriptionLimit = ref(500)

const aiResultModalOpen = ref(false)
const aiResultSummary = ref(null)

const duplicatesModalOpen = ref(false)
const showOriginalDescription = ref(false)

const selectedIds = ref(new Set())
const manualEditOpen = ref(false)
const manualEditTitle = ref('')
const manualEditDescription = ref('')
const manualEditSaving = ref(false)
const detailRetranslateRunning = ref(false)
const detailRetranslateProgress = ref(0)
const detailRetranslateLabel = ref('')
const detailRetranslateError = ref('')
const detailRetranslateBackgroundPolling = ref(false)
const detailRetranslateBackgroundStartedAt = ref(0)
let detailRetranslateToken = 0
let detailRetranslateBackgroundTimer = null
const DETAIL_RETRANSLATE_BACKGROUND_INTERVAL_MS = 4000
const DETAIL_RETRANSLATE_BACKGROUND_MAX_MS = 10 * 60 * 1000

const detailRetranslateBusy = computed(() => {
  return detailRetranslateRunning.value || detailRetranslateBackgroundPolling.value
})

function toggleSelect(id) {
  const next = new Set(selectedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selectedIds.value = next
}

function selectAll(ids) {
  const next = new Set(selectedIds.value)
  ids.forEach((id) => next.add(id))
  selectedIds.value = next
}

function deselectAll(ids) {
  const next = new Set(selectedIds.value)
  ids.forEach((id) => next.delete(id))
  selectedIds.value = next
}

function clearSelection() {
  selectedIds.value = new Set()
}

async function runBatchForSelected(mode) {
  const ids = [...selectedIds.value]
  if (!ids.length) return
  loading.value = true
  error.value = null
  try {
    const result = await eventCandidates.retranslateBatch({ ids, mode })
    const queued = Number(result.queued || 0)
    toast.success(`Spustené pre ${queued} kandidátov (${mode === 'template' ? 'šablóna' : 'AI'}).`)
    startTranslationPolling()
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Zlyhalo'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

function openManualEdit(candidate) {
  manualEditTitle.value = candidate.translated_title || candidate.title || ''
  manualEditDescription.value = candidate.translated_description || ''
  manualEditOpen.value = true
}

async function saveManualEdit(candidate) {
  if (!candidate?.id || manualEditSaving.value) return
  manualEditSaving.value = true
  try {
    await eventCandidates.updateTranslation(candidate.id, {
      translated_title: manualEditTitle.value,
      translated_description: manualEditDescription.value || null,
    })
    toast.success('Uložené.')
    manualEditOpen.value = false
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Uloženie zlyhalo')
  } finally {
    manualEditSaving.value = false
  }
}

const translationHealth = ref(null)
let translationPollId = null

const translationQueueTotal = computed(() => {
  return Number(translationHealth.value?.pending_candidates_total || 0)
})

const translationStartedAt = ref(null)
const translationElapsedSeconds = ref(0)
let translationElapsedTimerId = null

const translationElapsedLabel = computed(() => {
  const s = translationElapsedSeconds.value
  if (s < 60) return `${s}s`
  const m = Math.floor(s / 60)
  const rem = s % 60
  return rem === 0 ? `${m}min` : `${m}min ${rem}s`
})

watch(translationQueueTotal, (val) => {
  if (val > 0 && translationStartedAt.value === null) {
    translationStartedAt.value = Date.now()
    translationElapsedSeconds.value = 0
    translationElapsedTimerId = setInterval(() => {
      translationElapsedSeconds.value = Math.floor((Date.now() - translationStartedAt.value) / 1000)
    }, 1000)
  } else if (val === 0) {
    if (translationElapsedTimerId) {
      clearInterval(translationElapsedTimerId)
      translationElapsedTimerId = null
    }
    translationStartedAt.value = null
    translationElapsedSeconds.value = 0
  }
})

const translationProgressPercent = computed(() => {
  const counts = translationHealth.value?.counts_24h || {}
  const done = Number(counts.done || 0)
  const failed = Number(counts.failed || 0)
  const pending = Number(counts.pending || 0)
  const total = done + failed + pending
  if (total <= 0) return 0
  return Math.max(0, Math.min(100, Math.round(((done + failed) / total) * 100)))
})

async function pollTranslationHealth() {
  try {
    const res = await getEventTranslationHealth()
    translationHealth.value = res?.data || null
  } catch {
    // ignore
  }
}

function startTranslationPolling() {
  if (translationPollId) return
  pollTranslationHealth()
  translationPollId = setInterval(pollTranslationHealth, 4000)
}

function stopTranslationPolling() {
  if (translationPollId) {
    clearInterval(translationPollId)
    translationPollId = null
  }
}

const cancellingQueue = ref(false)
async function cancelTranslationQueue() {
  if (cancellingQueue.value) return
  cancellingQueue.value = true
  try {
    await eventCandidates.cancelTranslationQueue()
    await pollTranslationHealth()
  } finally {
    cancellingQueue.value = false
  }
}

const AI_DESCRIPTION_HARD_CAP = 2000

const {
  advancePublishProgress,
  applyRunFilterFromRoute,
  astronomyContext,
  astronomyContextAvailable,
  astronomyContextLoading,
  buildParams,
  canMergeDuplicates,
  candidateDetail,
  candidateDetailError,
  candidateDetailLoading,
  candidateDetailOpen,
  candidatePreviewShort,
  clearFilters,
  clearRunFilter,
  crawledStats,
  data,
  detailModalTitle,
  descriptionMode,
  dryRunDuplicateMerge,
  duplicateDryRunning,
  duplicateGroupLimit,
  duplicateGroups,
  duplicateLoading,
  duplicateMerging,
  duplicatePerGroup,
  duplicatePreview,
  duplicateSummary,
  error,
  filterMonth,
  filterWeek,
  filterYear,
  finishPublishProgress,
  formatAstronomyTime,
  formatDate,
  load,
  loadDuplicatePreview,
  loading,
  mergeDuplicateGroups,
  monthOptions,
  nextPage,
  openCandidate,
  openCandidateFullDetail,
  openCrawlingHub,
  page,
  per_page,
  prevPage,
  publishAllPending,
  publishAllByFilter,
  publishCandidateQuick,
  publishMode,
  publishProgressActive,
  publishProgressLabel,
  publishProgressPercent,
  q,
  quickSetStatus,
  resetCandidateDetailModal,
  resetToFirstPage,
  resolveTimeFilterParams,
  retranslateByFilter,
  retranslateVisiblePending,
  runFilter,
  showAdvancedFilters,
  showConfidenceColumn,
  showMonthFilter,
  showObservationContext,
  showWeekFilter,
  showYearFilter,
  source,
  startPublishProgress,
  status,
  timePreset,
  timePresetOptions,
  timezoneInfoLabel,
  type,
  visiblePendingCandidateIds,
} = useCandidatesCrawledTab({
  activeTab,
  auth,
  confirm,
  getPublishDescriptionMode: () => aiDescriptionMode.value,
  route,
  router,
  toast,
})

const CRAWLED_PUBLISH_TERMINAL_STATUSES = new Set(['completed', 'completed_with_failures', 'failed'])
const CRAWLED_PUBLISH_RUN_POLL_INTERVAL_MS = 1200
const CRAWLED_PUBLISH_RUN_POLL_TIMEOUT_MS = 10 * 60 * 1000
const CRAWLED_PUBLISH_RUN_STALL_POLL_THRESHOLD = 8

function normalizeCrawledPublishRun(run) {
  const status = String(run?.status || '').trim().toLowerCase()
  const totalSelectedRaw = Number(run?.total_selected ?? 0)
  const totalSelected = Number.isFinite(totalSelectedRaw) ? Math.max(0, Math.round(totalSelectedRaw)) : 0
  const processedRaw = Number(run?.processed ?? 0)
  const processed = Number.isFinite(processedRaw)
    ? Math.max(0, Math.min(totalSelected > 0 ? totalSelected : processedRaw, processedRaw))
    : 0

  return {
    id: Number(run?.id || 0),
    status,
    isTerminal: Boolean(run?.is_terminal) || CRAWLED_PUBLISH_TERMINAL_STATUSES.has(status),
    totalSelected,
    processed,
    published: Math.max(0, Number(run?.published || 0)),
    failed: Math.max(0, Number(run?.failed || 0)),
    errorMessage: String(run?.error_message || '').trim(),
  }
}

async function runCrawledBatchPublishWithProgress(payload, modeSteps, completedSteps) {
  const started = await eventCandidates.approveBatchStart(payload)
  let run = normalizeCrawledPublishRun(started?.run)
  let queuedWithoutProgressPolls = run.status === 'queued' && run.processed <= 0 ? 1 : 0
  if (!run.id) {
    throw new Error('Server nevratil ID publish runu.')
  }

  const updateStepProgress = (currentRun) => {
    if (currentRun.totalSelected <= 0) return
    const ratio = Math.max(0, Math.min(1, currentRun.processed / currentRun.totalSelected))
    advancePublishProgress(completedSteps + ratio, modeSteps)
  }

  if (run.totalSelected <= 0) {
    return { ...run, timedOut: false }
  }

  updateStepProgress(run)

  if (run.isTerminal) {
    if (run.status === 'failed') {
      throw new Error(run.errorMessage || 'Publikovanie crawlovaných kandidátov zlyhalo.')
    }
    return { ...run, timedOut: false }
  }

  const startedAt = Date.now()
  let lastPollError = null
  while (Date.now() - startedAt < CRAWLED_PUBLISH_RUN_POLL_TIMEOUT_MS) {
    await new Promise((resolve) => window.setTimeout(resolve, CRAWLED_PUBLISH_RUN_POLL_INTERVAL_MS))

    try {
      const response = await eventCandidates.approveBatchRunStatus(run.id)
      run = normalizeCrawledPublishRun(response?.run)
      lastPollError = null
    } catch (pollError) {
      lastPollError = pollError
    }

    updateStepProgress(run)

    if (run.isTerminal) {
      if (run.status === 'failed') {
        throw new Error(run.errorMessage || 'Publikovanie crawlovaných kandidátov zlyhalo.')
      }
      return { ...run, timedOut: false, usedSyncFallback: false }
    }

    if (run.status === 'queued' && run.processed <= 0) {
      queuedWithoutProgressPolls += 1
    } else {
      queuedWithoutProgressPolls = 0
    }

    if (queuedWithoutProgressPolls >= CRAWLED_PUBLISH_RUN_STALL_POLL_THRESHOLD) {
      publishProgressLabel.value = 'Queue worker nereaguje, dokoncujem publikovanie priamo...'
      const fallbackResult = await eventCandidates.approveBatch(payload)
      const fallbackTotal = Math.max(
        0,
        Number(
          fallbackResult?.total_selected
          ?? run.totalSelected
          ?? 0
        )
      )

      run = {
        ...run,
        status: Number(fallbackResult?.failed || 0) > 0 ? 'completed_with_failures' : 'completed',
        isTerminal: true,
        totalSelected: fallbackTotal,
        processed: fallbackTotal,
        published: Math.max(0, Number(fallbackResult?.published || 0)),
        failed: Math.max(0, Number(fallbackResult?.failed || 0)),
        errorMessage: '',
      }
      updateStepProgress(run)

      return { ...run, timedOut: false, usedSyncFallback: true }
    }
  }

  if (run.id > 0) {
    return { ...run, timedOut: true, usedSyncFallback: false }
  }

  if (lastPollError) {
    throw lastPollError
  }

  return { ...run, timedOut: true, usedSyncFallback: false }
}

function candidateTypeLabel(value) {
  const key = String(value || '').trim().toLowerCase()
  if (key === 'observation_window') return 'Pozorovacie okno'
  if (key === 'meteor_shower') return 'Meteoritick\u00fd roj'
  if (key === 'eclipse_lunar') return 'Zatmenie Mesiaca'
  if (key === 'eclipse_solar') return 'Zatmenie Slnka'
  if (key === 'planetary_event') return 'Planet\u00e1rny \u00fakaz'
  if (key === 'aurora') return 'Pol\u00e1rna \u017eiara'
  if (key === 'other') return 'In\u00e1 udalos\u0165'
  if (key === '') return '-'
  return key.replaceAll('_', ' ')
}

function resetDetailRetranslateState() {
  stopDetailRetranslateBackgroundPolling()
  detailRetranslateRunning.value = false
  detailRetranslateProgress.value = 0
  detailRetranslateLabel.value = ''
  detailRetranslateError.value = ''
  detailRetranslateBackgroundStartedAt.value = 0
  detailRetranslateToken += 1
}

function isTranslationStillPending(item) {
  const raw = String(item?.translation_status || '').trim().toLowerCase()
  return ['pending', 'queued', 'running', 'processing', 'in_progress'].includes(raw)
}

function isTranslationModeResolving(item) {
  const hasMode = String(item?.translation_mode || '').trim() !== ''
  return isTranslationStillPending(item) && !hasMode
}

function resolveTranslationModeLabel(item) {
  if (isTranslationModeResolving(item)) return 'Nacitava sa'

  const rawMode = String(item?.translation_mode || '').trim().toLowerCase()
  if (rawMode === '') return 'Nezname'

  return normalizeTranslationMode(rawMode)
}

function resolveDetailRetranslateOutcome(mode, candidateItem) {
  const selectedMode = String(mode || '').trim().toLowerCase() === 'template' ? 'template' : 'ai'
  const modeLabel = selectedMode === 'template' ? 'Sablona' : 'AI popis'
  const translationMode = String(candidateItem?.translation_mode || '').trim().toLowerCase()

  if (selectedMode === 'ai' && translationMode === 'template') {
    return {
      ok: false,
      modeLabel,
      message: `${modeLabel}: fallback na sablonu (AI nevratila pouzitelny vystup).`,
    }
  }

  return {
    ok: true,
    modeLabel,
    message: `${modeLabel}: hotovo.`,
  }
}

async function refreshDetailCandidate(candidateId) {
  const refreshed = await eventCandidates.get(candidateId)
  if (Number(refreshed?.id || 0) === candidateId) {
    candidateDetail.value = refreshed
  }
  return refreshed
}

function stopDetailRetranslateBackgroundPolling() {
  detailRetranslateBackgroundPolling.value = false
  detailRetranslateBackgroundStartedAt.value = 0
  if (detailRetranslateBackgroundTimer) {
    clearInterval(detailRetranslateBackgroundTimer)
    detailRetranslateBackgroundTimer = null
  }
}

async function pollDetailRetranslateBackground(mode, candidateId, token) {
  if (!candidateDetailOpen.value || token !== detailRetranslateToken) {
    stopDetailRetranslateBackgroundPolling()
    return
  }

  if (Date.now() - detailRetranslateBackgroundStartedAt.value > DETAIL_RETRANSLATE_BACKGROUND_MAX_MS) {
    stopDetailRetranslateBackgroundPolling()
    detailRetranslateError.value = 'Spracovanie trvá príliš dlho. Skontroluj, či beží queue worker.'
    toast.warn(detailRetranslateError.value)
    return
  }

  let refreshed = null
  try {
    refreshed = await refreshDetailCandidate(candidateId)
  } catch {
    return
  }

  if (!refreshed || isTranslationStillPending(refreshed)) {
    return
  }

  stopDetailRetranslateBackgroundPolling()
  await load()

  const outcome = resolveDetailRetranslateOutcome(mode, refreshed)
  detailRetranslateLabel.value = outcome.message
  if (outcome.ok) {
    toast.success(`${outcome.modeLabel} bol aktualizovany.`)
  } else {
    detailRetranslateError.value = outcome.message
    toast.warn(outcome.message)
  }
}

function startDetailRetranslateBackgroundPolling(mode, candidateId, token) {
  stopDetailRetranslateBackgroundPolling()
  detailRetranslateBackgroundStartedAt.value = Date.now()
  detailRetranslateBackgroundPolling.value = true

  void pollDetailRetranslateBackground(mode, candidateId, token)
  detailRetranslateBackgroundTimer = setInterval(() => {
    void pollDetailRetranslateBackground(mode, candidateId, token)
  }, DETAIL_RETRANSLATE_BACKGROUND_INTERVAL_MS)
}

async function pollDetailRetranslateUntilSettled(candidateId, token) {
  const startedAt = Date.now()
  let progress = Math.max(30, detailRetranslateProgress.value)

  while (detailRetranslateRunning.value && token === detailRetranslateToken && candidateDetailOpen.value) {
    let refreshed = null
    try {
      refreshed = await refreshDetailCandidate(candidateId)
    } catch {
      // Keep polling; queue may be busy and transient failures can happen.
    }

    if (refreshed && !isTranslationStillPending(refreshed)) {
      detailRetranslateProgress.value = 100
      return { done: true }
    }

    if (Date.now() - startedAt > 120000) {
      return { done: false, timedOut: true }
    }

    progress = Math.min(92, progress + 8)
    detailRetranslateProgress.value = progress
    await new Promise((resolve) => window.setTimeout(resolve, 2500))
  }

  return { done: false, cancelled: true }
}

async function runDetailRetranslate(mode = 'ai') {
  const current = candidateDetail.value
  const candidateId = Number(current?.id || 0)
  if (!Number.isFinite(candidateId) || candidateId <= 0 || detailRetranslateBusy.value) return

  const selectedMode = String(mode || '').trim().toLowerCase() === 'template' ? 'template' : 'ai'
  const modeLabel = selectedMode === 'template' ? 'Sablona' : 'AI popis'

  stopDetailRetranslateBackgroundPolling()
  detailRetranslateToken += 1
  const token = detailRetranslateToken
  detailRetranslateRunning.value = true
  detailRetranslateProgress.value = 10
  detailRetranslateLabel.value = `${modeLabel}: odosielam poziadavku...`
  detailRetranslateError.value = ''
  error.value = null

  try {
    await eventCandidates.retranslate(candidateId, { mode: selectedMode })
    startTranslationPolling()

    detailRetranslateProgress.value = 30
    detailRetranslateLabel.value = `${modeLabel}: zaradene do fronty, cakam na vysledok...`

    await load()
    const pollResult = await pollDetailRetranslateUntilSettled(candidateId, token)
    await load()

    if (pollResult.done) {
      const outcome = resolveDetailRetranslateOutcome(selectedMode, candidateDetail.value)
      detailRetranslateProgress.value = 100
      detailRetranslateLabel.value = outcome.message
      if (outcome.ok) {
        toast.success(`${modeLabel} bol aktualizovany.`)
      } else {
        detailRetranslateError.value = outcome.message
        toast.warn(outcome.message)
      }
      return
    }

    if (pollResult.timedOut) {
      detailRetranslateLabel.value = `${modeLabel}: spracovanie ešte beží na pozadí.`
      toast.warn(`${modeLabel} bol spustený, dokončenie môže trvať dlhšie.`)
      startDetailRetranslateBackgroundPolling(selectedMode, candidateId, token)
      return
    }
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    const messageText = String(e?.message || '').toLowerCase()
    const isTimeout = e?.code === 'ECONNABORTED' || messageText.includes('timeout')
    const isNetwork = !status && (e?.code === 'ERR_NETWORK' || messageText.includes('network'))

    if (isTimeout || isNetwork) {
      detailRetranslateProgress.value = Math.max(70, detailRetranslateProgress.value)
      detailRetranslateLabel.value = `${modeLabel}: požiadavka trvá dlhšie, overujem stav...`
      try {
        const refreshed = await refreshDetailCandidate(candidateId)
        await load()
        startTranslationPolling()

        if (refreshed && !isTranslationStillPending(refreshed)) {
          const outcome = resolveDetailRetranslateOutcome(selectedMode, refreshed)
          detailRetranslateProgress.value = 100
          detailRetranslateLabel.value = outcome.message
          if (outcome.ok) {
            toast.success(`${modeLabel} bol aktualizovany.`)
          } else {
            detailRetranslateError.value = outcome.message
            toast.warn(outcome.message)
          }
        } else {
          detailRetranslateProgress.value = Math.max(90, detailRetranslateProgress.value)
          detailRetranslateLabel.value = `${modeLabel}: spustené, dokončenie beží na pozadí.`
          toast.warn(`${modeLabel} je spustený, dokončenie môže trvať dlhšie.`)
          startDetailRetranslateBackgroundPolling(selectedMode, candidateId, token)
        }
      } catch {
        const timeoutMessage = `${modeLabel}: požiadavka trvá dlhšie, stav sa nepodarilo overiť.`
        detailRetranslateError.value = timeoutMessage
        error.value = timeoutMessage
        toast.warn(timeoutMessage)
        startDetailRetranslateBackgroundPolling(selectedMode, candidateId, token)
      }
      return
    }

    const message =
      e?.response?.data?.message
      || e?.userMessage
      || `${modeLabel}: spustenie generovania popisu zlyhalo.`
    detailRetranslateError.value = message
    error.value = message
    toast.error(message)
  } finally {
    detailRetranslateRunning.value = false
  }
}

const {
  buildManualBatchPayload,
  clearManualFilters,
  closeManualForm,
  deleteManual,
  loadManual,
  manualCanSave,
  manualData,
  manualEditingId,
  manualError,
  manualForm,
  manualFormErrors,
  manualLoading,
  manualPage,
  manualPerPage,
  manualQ,
  manualStats,
  manualStatus,
  manualType,
  manualTypeOptions,
  nextManualPage,
  openManualFormCreate,
  openManualFormEdit,
  prevManualPage,
  publishManual,
  resetManualToFirstPage,
  saveManual,
  setManualEndByHours,
  setManualStartNow,
  showManualForm,
} = useCandidatesManualEvents({
  activeTab,
  confirm,
  toast,
  resolveTimeFilterParams,
})

async function publishBySelectedMode() {
  const labels = {
    crawled: 'crawlovan\u00e9',
    manual: 'manu\u00e1lne',
    all: 'crawlovan\u00e9 aj manu\u00e1lne',
  }

  const ok = await confirm({
    title: 'Publikova\u0165 pod\u013ea re\u017eimu',
    message: `Naozaj publikova\u0165 ${labels[publishMode.value] || 'vybran\u00e9'} udalosti pod\u013ea filtra? (max 1000 na typ)`,
    confirmText: 'Publikova\u0165',
    cancelText: 'Zru\u0161i\u0165',
    variant: 'danger',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  const modeSteps = publishMode.value === 'all' ? 2 : 1
  startPublishProgress('Publikujem pod\u013ea re\u017eimu...', modeSteps)
  let completedSteps = 0

  try {
    const selectedDescriptionMode = ['template', 'ai', 'mix'].includes(aiDescriptionMode.value)
      ? aiDescriptionMode.value
      : 'template'
    const params = buildParams()
    const crawledPayload = {
      status: params.status,
      type: params.type,
      description_mode: params.description_mode,
      source: params.source,
      source_key: params.source_key,
      run_id: params.run_id,
      q: params.q,
      year: params.year,
      month: params.month,
      week: params.week,
      date_from: params.date_from,
      date_to: params.date_to,
      limit: 1000,
      mode: selectedDescriptionMode,
    }
    const manualPayload = buildManualBatchPayload()

    let crawledResult = { published: 0, failed: 0 }
    let manualResult = { published: 0, failed: 0 }
    let crawledTimedOut = false

    if (publishMode.value === 'crawled' || publishMode.value === 'all') {
      const crawledRunResult = await runCrawledBatchPublishWithProgress(crawledPayload, modeSteps, completedSteps)
      crawledResult = {
        published: crawledRunResult.published,
        failed: crawledRunResult.failed,
      }
      crawledTimedOut = crawledRunResult.timedOut
      completedSteps += 1
      advancePublishProgress(completedSteps, modeSteps)
    }

    if (publishMode.value === 'manual' || publishMode.value === 'all') {
      manualResult = await eventCandidates.publishManualBatch(manualPayload)
      completedSteps += 1
      advancePublishProgress(completedSteps, modeSteps)
    }

    const totalPublished = Number(crawledResult.published || 0) + Number(manualResult.published || 0)
    const totalFailed = Number(crawledResult.failed || 0) + Number(manualResult.failed || 0)

    if (crawledTimedOut) {
      toast.warn('Publikovanie crawlovaných kandidátov beží na pozadí. Obnov zoznam o chvíľu.')
    } else if (crawledResult.usedSyncFallback) {
      if (totalFailed > 0) {
        toast.warn(`Queue worker nereagoval, publikovane spolu: ${totalPublished}, zlyhalo: ${totalFailed}.`)
      } else {
        toast.success(`Queue worker nereagoval, publikovanych spolu: ${totalPublished}.`)
      }
    } else if (totalFailed > 0) {
      toast.warn(`Publikovan\u00e9 spolu: ${totalPublished}, zlyhalo: ${totalFailed}.`)
    } else {
      toast.success(`Publikovan\u00fdch spolu: ${totalPublished}.`)
    }

    await Promise.all([load(), loadManual()])
  } catch (e) {
    error.value = e?.response?.data?.message || 'Hromadn\u00e9 publikovanie pod\u013ea re\u017eimu zlyhalo'
    toast.error(error.value)
  } finally {
    finishPublishProgress()
    loading.value = false
  }
}

const AI_SCOPE_LABELS = {
  all: 'všetci kandidáti podľa filtra',
  missing: 'kandidáti bez preloženého popisu',
  template: 'kandidáti so šablónovým popisom',
}

function clampInt(value, min, max, fallback) {
  const parsed = Number.parseInt(String(value ?? ''), 10)
  if (!Number.isFinite(parsed)) return fallback
  return Math.min(max, Math.max(min, parsed))
}

async function generateAiDescriptionsForCandidates() {
  if (aiDescriptionRunning.value) return

  try {
    const requestedLimit = clampInt(aiDescriptionLimit.value, 1, AI_DESCRIPTION_HARD_CAP, 500)
    const selectedScope = ['all', 'missing', 'template'].includes(aiDescriptionScope.value) ? aiDescriptionScope.value : 'missing'
    const selectedMode = ['template', 'ai', 'mix'].includes(aiDescriptionMode.value) ? aiDescriptionMode.value : 'template'
    const modeLabel = selectedMode === 'template' ? 'Šablóna' : selectedMode === 'mix' ? 'Mix' : 'AI popis'

    aiDescriptionLimit.value = requestedLimit
    aiDescriptionScope.value = selectedScope
    aiDescriptionMode.value = selectedMode

    const approved = await confirm({
      title: 'Generovanie popisov',
      message: `Spustit ${modeLabel.toLowerCase()} pre ${AI_SCOPE_LABELS[selectedScope]}? (max ${requestedLimit})`,
      confirmText: 'Spustit',
      cancelText: 'Zrušiť',
    })
    if (!approved) return

    aiDescriptionRunning.value = true
    aiDescriptionProgress.value = 10
    aiDescriptionLabel.value = 'Pripravujem kandidatov...'

    const params = buildParams()
    aiDescriptionProgress.value = 45
    aiDescriptionLabel.value = 'Spustam generovanie AI popisov...'

    const result = await eventCandidates.retranslateBatch({
      status: params.status,
      type: params.type,
      description_mode: params.description_mode,
      source: params.source,
      source_key: params.source_key,
      run_id: params.run_id,
      q: params.q,
      year: params.year,
      month: params.month,
      week: params.week,
      date_from: params.date_from,
      date_to: params.date_to,
      limit: requestedLimit,
      mode: selectedMode,
      ai_scope: selectedScope,
    })

    aiDescriptionProgress.value = 100

    if (Number(result.total_selected || 0) === 0) {
      if (selectedScope === 'missing') {
        toast.warn('Nenašli sa kandidáti bez preloženého popisu. Pre AI prepis šablóny zvoľ rozsah "kandidáti so šablónovým popisom" alebo "všetci kandidáti".')
      } else {
        toast.warn(`Nenašli sa kandidáti pre rozsah: ${AI_SCOPE_LABELS[selectedScope]}. Skús zmeniť rozsah alebo filter.`)
      }
      return
    }

    aiResultSummary.value = {
      mode: modeLabel,
      queued: Number(result.queued || 0),
      failed: Number(result.failed || 0),
      total: Number(result.total_selected || 0),
      scope: AI_SCOPE_LABELS[selectedScope] || selectedScope,
      items: Array.isArray(result.items) ? result.items : [],
    }
    aiResultModalOpen.value = true
    startTranslationPolling()

    await load()
  } catch (error) {
    const message = error?.response?.data?.message || error?.userMessage || ''
    const isTimeout = error?.code === 'ECONNABORTED'
      || String(error?.message || '').toLowerCase().includes('timeout')

    if (isTimeout) {
      toast.warn('Požiadavka trvá dlhšie. Generovanie mohlo byť spustené na pozadí. Obnov zoznam kandidátov o chvíľu.')
    } else {
      toast.error(message || 'Hromadne AI popisy pre kandidatov zlyhali.')
    }
  } finally {
    aiDescriptionRunning.value = false
    aiDescriptionLabel.value = ''
    aiDescriptionProgress.value = 0
  }
}

function setTab(tab) {
  activeTab.value = tab
  if (tab !== 'crawled') {
    duplicatePreview.value = null
  }
  if (tab === 'crawled') {
    if (!data.value) {
      load()
    } else {
      loadDuplicatePreview()
    }
  }
  if (tab === 'manual' && !manualData.value) loadManual()
}

onMounted(() => {
  applyRunFilterFromRoute()
  load()
  pollTranslationHealth()
})

onUnmounted(() => {
  stopDetailRetranslateBackgroundPolling()
  stopTranslationPolling()
  if (translationElapsedTimerId) clearInterval(translationElapsedTimerId)
})
</script>

<template src="./candidates/CandidatesListView.template.html"></template>

<style scoped src="./candidates/CandidatesListView.css"></style>

