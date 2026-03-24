import { computed, ref } from 'vue'
import {
  getEventTranslationHealth,
  getTranslationArtifactsReport,
  repairTranslationArtifacts,
} from '@/services/api/admin/eventSources'
import { eventCandidates } from '@/services/eventCandidates'

export function useEventSourcesTranslationQuality({
  t,
  toast,
  formatDate,
  beginOperation,
  endOperation,
  setError,
  onRefreshAfterRepair,
}) {
  const translationHealth = ref(null)
  const translationHealthLoading = ref(false)
  const translationPollFailureCount = ref(0)
  let translationPollId = null
  const translationPollBaseDelayMs = 3500
  const translationPollMaxDelayMs = 28000

  const artifactsSummary = ref({
    suspicious_candidates: 0,
    sample_limit: 20,
    sample_count: 0,
    checked_at: null,
  })
  const artifactsSamples = ref([])
  const artifactsSampleLimit = ref(20)
  const artifactsRepairLimit = ref(300)
  const artifactsLoading = ref(false)
  const artifactsRepairing = ref(false)

  const translationPendingCount = computed(() => Number(translationHealth.value?.pending_candidates_total || 0))
  const translationQueuedJobs = computed(() => Number(translationHealth.value?.queue?.queued_event_translation_jobs || 0))
  const translationQueueTotal = computed(() => translationPendingCount.value + translationQueuedJobs.value)
  const translationCounts = computed(() => translationHealth.value?.counts_24h || {})

  const translationProgressPercent = computed(() => {
    const done = Number(translationCounts.value?.done || 0)
    const failed = Number(translationCounts.value?.failed || 0)
    const pending = Number(translationCounts.value?.pending || 0)
    const total = done + failed + pending
    if (total <= 0) return 0
    return Math.max(0, Math.min(100, Math.round(((done + failed) / total) * 100)))
  })

  const translationIsActive = computed(() => {
    return translationPendingCount.value > 0 || translationQueuedJobs.value > 0
  })

  const translationProgressLabel = computed(() => {
    if (!translationIsActive.value) return t('progress.translateIdle')
    return t('progress.translateRunning')
  })

  const artifactsSuspiciousCount = computed(() => Number(artifactsSummary.value?.suspicious_candidates || 0))
  const artifactsCheckedAtLabel = computed(() => formatDate(artifactsSummary.value?.checked_at))
  const artifactsHasFindings = computed(() => artifactsSuspiciousCount.value > 0)
  const artifactsReportTone = computed(() => (artifactsHasFindings.value ? 'danger' : 'success'))
  const canRepairArtifacts = computed(() => !artifactsRepairing.value && artifactsSuspiciousCount.value > 0)

  function normalizePositiveInt(value, fallback) {
    const n = Number(value)
    if (!Number.isFinite(n)) return fallback
    return Math.max(1, Math.floor(n))
  }

  async function loadTranslationHealth() {
    translationHealthLoading.value = true
    try {
      const response = await getEventTranslationHealth()
      translationHealth.value = response?.data || null
      translationPollFailureCount.value = 0
      return true
    } catch {
      translationPollFailureCount.value = Math.min(4, translationPollFailureCount.value + 1)
      return false
    } finally {
      translationHealthLoading.value = false
    }
  }

  function resolveNextTranslationPollDelay() {
    if (translationPollFailureCount.value <= 0) {
      return translationPollBaseDelayMs
    }

    return Math.min(
      translationPollMaxDelayMs,
      translationPollBaseDelayMs * (2 ** translationPollFailureCount.value),
    )
  }

  function startTranslationPoll() {
    if (translationPollId !== null) return
    translationPollId = window.setTimeout(async () => {
      translationPollId = null
      await loadTranslationHealth()
      startTranslationPoll()
    }, resolveNextTranslationPollDelay())
  }

  function stopTranslationPoll() {
    if (translationPollId === null) return
    window.clearTimeout(translationPollId)
    translationPollId = null
  }

  function handleVisibilityChange() {
    if (typeof document === 'undefined') return

    if (document.visibilityState === 'hidden') {
      stopTranslationPoll()
      return
    }

    void loadTranslationHealth()
    startTranslationPoll()
  }

  function attachVisibilityListener() {
    if (typeof document === 'undefined') return
    document.addEventListener('visibilitychange', handleVisibilityChange)
  }

  function detachVisibilityListener() {
    if (typeof document === 'undefined') return
    document.removeEventListener('visibilitychange', handleVisibilityChange)
  }

  async function loadTranslationArtifactsReport(showSuccessToast = false) {
    artifactsLoading.value = true

    try {
      const response = await getTranslationArtifactsReport({
        sample: normalizePositiveInt(artifactsSampleLimit.value, 20),
      })

      const summary = response?.data?.summary || {}
      const samples = Array.isArray(response?.data?.samples) ? response.data.samples : []

      artifactsSummary.value = {
        suspicious_candidates: Number(summary.suspicious_candidates || 0),
        sample_limit: Number(summary.sample_limit || normalizePositiveInt(artifactsSampleLimit.value, 20)),
        sample_count: Number(summary.sample_count || samples.length),
        checked_at: summary.checked_at || null,
      }
      artifactsSamples.value = samples

      if (showSuccessToast) {
        const count = Number(summary.suspicious_candidates || 0)
        if (count > 0) {
          toast.warn(t('messages.qualityFound', { count }))
        } else {
          toast.success(t('messages.qualityClear'))
        }
      }
    } catch (fetchError) {
      if (showSuccessToast) {
        setError(fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.artifactsLoadError'))
      }
    } finally {
      artifactsLoading.value = false
    }
  }

  async function runTranslationArtifactsRepair() {
    if (!canRepairArtifacts.value) return

    artifactsRepairing.value = true
    beginOperation()
    setError('')

    try {
      const response = await repairTranslationArtifacts({
        limit: normalizePositiveInt(artifactsRepairLimit.value, 300),
        dry_run: false,
        sample: normalizePositiveInt(artifactsSampleLimit.value, 20),
      })

      const payload = response?.data || {}
      const summary = payload.summary || {}
      const processed = Number(summary.processed || 0)
      const translated = Number(summary.translated || 0)
      const failed = Number(summary.failed || 0)

      toast.success(t('messages.repairDone', { processed, translated, failed }))

      await Promise.all([loadTranslationArtifactsReport(false), onRefreshAfterRepair(), loadTranslationHealth()])
    } catch (fetchError) {
      setError(fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.artifactsRepairError'))
    } finally {
      artifactsRepairing.value = false
      endOperation()
    }
  }

  return {
    artifactsCheckedAtLabel,
    artifactsHasFindings,
    artifactsLoading,
    artifactsRepairLimit,
    artifactsRepairing,
    artifactsReportTone,
    artifactsSampleLimit,
    artifactsSamples,
    artifactsSummary,
    artifactsSuspiciousCount,
    attachVisibilityListener,
    canRepairArtifacts,
    detachVisibilityListener,
    loadTranslationArtifactsReport,
    loadTranslationHealth,
    runTranslationArtifactsRepair,
    startTranslationPoll,
    stopTranslationPoll,
    translationCounts,
    translationHealth,
    translationHealthLoading,
    translationIsActive,
    translationPendingCount,
    translationProgressLabel,
    translationProgressPercent,
    translationQueueTotal,
    translationQueuedJobs,
  }
}
