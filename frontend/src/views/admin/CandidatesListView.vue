<script setup>
import { onMounted, ref } from 'vue'
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
  normalizeTranslationStatus,
  sourceLabel,
} from './candidatesListView.utils'

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()
const auth = useAuthStore()

const activeTab = ref('crawled')

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
} = useCandidatesCrawledTab({
  activeTab,
  auth,
  confirm,
  route,
  router,
  toast,
})

function candidateTypeLabel(value) {
  const key = String(value || '').trim().toLowerCase()
  if (key === 'observation_window') return 'Pozorovacie okno'
  if (key === 'meteor_shower') return 'Meteorický roj'
  if (key === 'eclipse_lunar') return 'Zatmenie Mesiaca'
  if (key === 'eclipse_solar') return 'Zatmenie Slnka'
  if (key === 'planetary_event') return 'Planetárny úkaz'
  if (key === 'aurora') return 'Polarna ziara'
  if (key === 'other') return 'Iná udalosť'
  if (key === '') return '-'
  return key.replaceAll('_', ' ')
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
    crawled: 'crawlovane',
    manual: 'manualne',
    all: 'crawlovane aj manualne',
  }

  const ok = await confirm({
    title: 'Publikovat podla rezimu',
    message: `Naozaj publikovat ${labels[publishMode.value] || 'vybrane'} udalosti podla filtra? (max 1000 na typ)`,
    confirmText: 'Publikovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!ok) return

  loading.value = true
  error.value = null
  const modeSteps = publishMode.value === 'all' ? 2 : 1
  startPublishProgress('Publikujem podla rezimu...', modeSteps)
  let completedSteps = 0

  try {
    const params = buildParams()
    const crawledPayload = {
      status: params.status,
      type: params.type,
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
    }
    const manualPayload = buildManualBatchPayload()

    let crawledResult = { published: 0, failed: 0 }
    let manualResult = { published: 0, failed: 0 }

    if (publishMode.value === 'crawled' || publishMode.value === 'all') {
      crawledResult = await eventCandidates.approveBatch(crawledPayload)
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

    if (totalFailed > 0) {
      toast.warn(`Publikovane spolu: ${totalPublished}, zlyhalo: ${totalFailed}.`)
    } else {
      toast.success(`Publikovanych spolu: ${totalPublished}.`)
    }

    await Promise.all([load(), loadManual()])
  } catch (e) {
    error.value = e?.response?.data?.message || 'Hromadne publikovanie podla rezimu zlyhalo'
    toast.error(error.value)
  } finally {
    finishPublishProgress()
    loading.value = false
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
})
</script>

<template src="./candidates/CandidatesListView.template.html"></template>

<style scoped src="./candidates/CandidatesListView.css"></style>
