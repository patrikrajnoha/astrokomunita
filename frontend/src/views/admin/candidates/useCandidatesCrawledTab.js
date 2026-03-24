import { computed, onUnmounted, ref, watch } from 'vue'
import api from '@/services/api'
import { eventCandidates } from '@/services/eventCandidates'
import { candidateDisplayShort, candidateDisplayTitle } from '@/utils/translatedFields'
import { useCandidatesCrawledBatchActions } from './useCandidatesCrawledBatchActions'
import { useCandidatesDuplicateManagement } from './useCandidatesDuplicateManagement'
import { MONTH_OPTIONS, TIME_PRESET_OPTIONS } from './candidatesCrawled.constants'
import {
  resolveUserCoordinates,
  resolveUserLocationLabel,
  resolveUserPreferredTimezone,
} from '@/utils/userTimezone'
import {
  candidatePreviewShort as buildCandidatePreviewShort,
  formatAstronomyTime as formatAstronomyTimeByTimezone,
  formatDate as formatDateByTimezone,
  getIsoWeek,
  isPendingTranslation,
  resolveTimeFilterParams as resolveTimeFilterParamsFromSelection,
} from '../candidatesListView.utils'

export function useCandidatesCrawledTab({
  activeTab,
  auth,
  confirm,
  getPublishDescriptionMode,
  route,
  router,
  toast,
}) {
  const loading = ref(false)
  const error = ref(null)

  const status = ref('pending')
  const type = ref('')
  const descriptionMode = ref('')
  const source = ref('')
  const q = ref('')
  const showAdvancedFilters = ref(false)
  const currentDate = new Date()
  const timePreset = ref('none')
  const filterYear = ref(currentDate.getFullYear())
  const filterMonth = ref(currentDate.getMonth() + 1)
  const filterWeek = ref(getIsoWeek(currentDate))

  const page = ref(1)
  const per_page = ref(20)

  const data = ref(null)
  const candidateDetailOpen = ref(false)
  const candidateDetailLoading = ref(false)
  const candidateDetailError = ref('')
  const candidateDetail = ref(null)

  const publishMode = ref('crawled')
  const astronomyContext = ref(null)
  const astronomyContextLoading = ref(false)
  const showObservationContext = ref(false)
  let translationRefreshTimerId = null

  const timePresetOptions = TIME_PRESET_OPTIONS
  const monthOptions = MONTH_OPTIONS

  const preferredTimezone = computed(() => resolveUserPreferredTimezone(auth.user))
  const preferredLocationLabel = computed(() => resolveUserLocationLabel(auth.user))
  const preferredCoordinates = computed(() => resolveUserCoordinates(auth.user))
  const timezoneInfoLabel = computed(() => `${preferredLocationLabel.value} (${preferredTimezone.value})`)
  const astronomyContextAvailable = computed(() => astronomyContext.value && typeof astronomyContext.value === 'object')

  const runFilter = computed(() => {
    const runId = Number(route.query?.run_id)
    if (!Number.isFinite(runId) || runId <= 0) {
      return null
    }

    const sourceKey = String(route.query?.source_key || route.query?.source || '')
      .trim()
      .toLowerCase()

    const yearValue = Number(route.query?.year)
    const year = Number.isFinite(yearValue) && yearValue >= 2000 ? yearValue : null

    return {
      runId,
      sourceKey,
      year,
    }
  })

  const visiblePendingCandidateIds = computed(() => {
    const rows = Array.isArray(data.value?.data) ? data.value.data : []
    return rows
      .filter((row) => String(row?.status || '').toLowerCase() === 'pending')
      .map((row) => Number(row.id))
      .filter((id) => Number.isFinite(id) && id > 0)
  })

  const hasPendingTranslationsOnPage = computed(() => {
    const rows = Array.isArray(data.value?.data) ? data.value.data : []
    return rows.some((row) => isPendingTranslation(row))
  })

  const crawledStats = computed(() => {
    const rows = Array.isArray(data.value?.data) ? data.value.data : []
    const stats = {
      total: rows.length,
      pending: 0,
      readyToPublish: 0,
      approved: 0,
      rejected: 0,
      translated: 0,
      failedTranslation: 0,
      modeTemplate: 0,
      modeAi: 0,
      modeTranslated: 0,
      modeManual: 0,
    }

    for (const row of rows) {
      const rowStatus = String(row?.status || '').toLowerCase()
      if (rowStatus === 'pending') stats.pending += 1
      if (rowStatus === 'approved') stats.approved += 1
      if (rowStatus === 'rejected') stats.rejected += 1

      const translationStatus = String(row?.translation_status || '').toLowerCase()
      if (translationStatus === 'done' || translationStatus === 'translated') stats.translated += 1
      if (translationStatus === 'failed' || translationStatus === 'error') stats.failedTranslation += 1
      if (
        rowStatus === 'pending' &&
        !['failed', 'error', 'pending', 'queued', 'running', 'processing', 'in_progress'].includes(translationStatus)
      ) {
        stats.readyToPublish += 1
      }

    }

    const mc = data.value?.mode_counts || {}
    stats.modeTemplate = mc.template ?? 0
    stats.modeAi = mc.ai_refined ?? 0
    stats.modeTranslated = mc.translated ?? 0
    stats.modeManual = mc.manual ?? 0

    return stats
  })

  const showConfidenceColumn = computed(() => Boolean(auth.isAdmin))
  const showYearFilter = computed(() => ['week', 'month', 'year'].includes(String(timePreset.value || '')))
  const showMonthFilter = computed(() => String(timePreset.value || '') === 'month')
  const showWeekFilter = computed(() => String(timePreset.value || '') === 'week')
  const detailModalTitle = computed(() => {
    if (!candidateDetail.value) {
      return 'Detail kandidáta'
    }
    return candidateDisplayTitle(candidateDetail.value) || 'Detail kandidáta'
  })

  function applyRunFilterFromRoute() {
    if (!runFilter.value) return

    if (runFilter.value.sourceKey) {
      source.value = runFilter.value.sourceKey
    }
    status.value = 'pending'
    page.value = 1
  }

  function clearRunFilter() {
    const query = { ...route.query }
    delete query.run_id
    delete query.source_key
    delete query.source
    delete query.year
    router.replace({ query })
  }

  function formatDate(value) {
    return formatDateByTimezone(value, preferredTimezone.value)
  }

  function candidatePreviewShort(candidate) {
    return buildCandidatePreviewShort(candidate, candidateDisplayShort)
  }

  function formatAstronomyTime(value) {
    return formatAstronomyTimeByTimezone(value, preferredTimezone.value)
  }

  async function loadAstronomyContext() {
    astronomyContextLoading.value = true
    try {
      const params = { tz: preferredTimezone.value }
      const coordinates = preferredCoordinates.value
      if (coordinates) {
        params.lat = coordinates.lat
        params.lon = coordinates.lon
      }

      const response = await api.get('/sky/astronomy', {
        params,
        meta: { skipErrorToast: true },
      })
      astronomyContext.value = response?.data || null
    } catch {
      astronomyContext.value = null
    } finally {
      astronomyContextLoading.value = false
    }
  }

  function resolveTimeFilterParams() {
    return resolveTimeFilterParamsFromSelection({
      preset: timePreset.value,
      filterYear: filterYear.value,
      filterMonth: filterMonth.value,
      filterWeek: filterWeek.value,
      now: new Date(),
    })
  }

  async function openCandidate(id) {
    const candidateId = Number(id)
    if (!Number.isFinite(candidateId) || candidateId <= 0) return

    candidateDetailOpen.value = true
    candidateDetailLoading.value = true
    candidateDetailError.value = ''
    candidateDetail.value = null

    try {
      candidateDetail.value = await eventCandidates.get(candidateId)
    } catch (fetchError) {
      candidateDetailError.value = fetchError?.response?.data?.message || 'Detail kandidáta sa nepodarilo načítať.'
    } finally {
      candidateDetailLoading.value = false
    }
  }

  function resetCandidateDetailModal() {
    candidateDetailLoading.value = false
    candidateDetailError.value = ''
    candidateDetail.value = null
  }

  function openCandidateFullDetail() {
    const candidateId = Number(candidateDetail.value?.id)
    if (!Number.isFinite(candidateId) || candidateId <= 0) return

    candidateDetailOpen.value = false
    router.push({
      name: 'admin.candidate.detail',
      params: { id: String(candidateId) },
    })
  }

  function stopTranslationRefreshPoll() {
    if (translationRefreshTimerId === null) return
    window.clearTimeout(translationRefreshTimerId)
    translationRefreshTimerId = null
  }

  function scheduleTranslationRefreshPoll() {
    stopTranslationRefreshPoll()

    if (activeTab.value !== 'crawled') return
    if (!hasPendingTranslationsOnPage.value) return
    if (loading.value) return

    translationRefreshTimerId = window.setTimeout(async () => {
      translationRefreshTimerId = null
      if (activeTab.value !== 'crawled' || loading.value) {
        scheduleTranslationRefreshPoll()
        return
      }

      await load()
      scheduleTranslationRefreshPoll()
    }, 5000)
  }

  function openCrawlingHub() {
    router.push({ name: 'admin.event-sources' })
  }

  function resetToFirstPage() {
    page.value = 1
  }

  function buildParams() {
    const sourceValue = source.value?.trim() ? source.value.trim() : undefined
    const timeFilters = resolveTimeFilterParams()

    return {
      status: status.value || undefined,
      type: type.value || undefined,
      description_mode: descriptionMode.value || undefined,
      source: sourceValue,
      source_key: sourceValue,
      run_id: runFilter.value?.runId ? Number(runFilter.value.runId) : undefined,
      q: q.value?.trim() ? q.value.trim() : undefined,
      year: timeFilters.year,
      month: timeFilters.month,
      week: timeFilters.week,
      date_from: timeFilters.date_from,
      date_to: timeFilters.date_to,
      page: page.value,
      per_page: per_page.value,
    }
  }

  const {
    canMergeDuplicates,
    dryRunDuplicateMerge,
    duplicateDryRunning,
    duplicateGroupLimit,
    duplicateGroups,
    duplicateLoading,
    duplicateMerging,
    duplicatePerGroup,
    duplicatePreview,
    duplicateSummary,
    loadDuplicatePreview,
    mergeDuplicateGroups,
    setDuplicateReloadHandler,
  } = useCandidatesDuplicateManagement({
    activeTab,
    buildParams,
    confirm,
    error,
    status,
    toast,
  })

  async function load() {
    loading.value = true
    error.value = null

    try {
      data.value = await eventCandidates.list(buildParams())
      await loadDuplicatePreview()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Chyba pri načítaní kandidátov'
    } finally {
      loading.value = false
    }
  }
  setDuplicateReloadHandler(load)

  const {
    advancePublishProgress,
    finishPublishProgress,
    publishAllPending,
    publishAllByFilter,
    publishAllVisiblePending,
    publishCandidateQuick,
    publishProgressActive,
    publishProgressLabel,
    publishProgressPercent,
    retranslateByFilter,
    retranslateCandidateQuick,
    retranslateVisiblePending,
    startPublishProgress,
  } = useCandidatesCrawledBatchActions({
    buildParams,
    confirm,
    error,
    getPublishDescriptionMode,
    load,
    loading,
    toast,
    visiblePendingCandidateIds,
  })

  function clearFilters() {
    const now = new Date()
    status.value = 'pending'
    type.value = ''
    descriptionMode.value = ''
    source.value = ''
    q.value = ''
    timePreset.value = 'none'
    filterYear.value = now.getFullYear()
    filterMonth.value = now.getMonth() + 1
    filterWeek.value = getIsoWeek(now)
    page.value = 1
    per_page.value = 20
    showAdvancedFilters.value = false
    load()
  }

  function quickSetStatus(nextStatus) {
    if (status.value === nextStatus) return
    status.value = nextStatus
    resetToFirstPage()
    load()
  }

  function prevPage() {
    if (!data.value || page.value <= 1) return
    page.value -= 1
    load()
  }

  function nextPage() {
    if (!data.value || page.value >= data.value.last_page) return
    page.value += 1
    load()
  }

  watch([status, type, descriptionMode, source, per_page, timePreset, filterYear, filterMonth, filterWeek], () => {
    resetToFirstPage()
    if (activeTab.value === 'crawled') load()
  })

  watch(
    () => route.query,
    () => {
      if (activeTab.value !== 'crawled') return
      applyRunFilterFromRoute()
      load()
    },
    { deep: true },
  )

  watch(
    () => [activeTab.value, hasPendingTranslationsOnPage.value, loading.value],
    () => {
      if (activeTab.value !== 'crawled' || loading.value || !hasPendingTranslationsOnPage.value) {
        stopTranslationRefreshPoll()
        return
      }

      scheduleTranslationRefreshPoll()
    },
  )

  watch(
    () => [preferredTimezone.value, preferredCoordinates.value?.lat, preferredCoordinates.value?.lon],
    () => {
      loadAstronomyContext()
    },
    { immediate: true },
  )

  onUnmounted(() => {
    stopTranslationRefreshPoll()
  })

  return {
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
    per_page,
    prevPage,
    publishAllPending,
    publishAllByFilter,
    publishAllVisiblePending,
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
    retranslateCandidateQuick,
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
  }
}
