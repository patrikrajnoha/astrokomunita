import { computed, ref } from 'vue'
import { eventCandidates } from '@/services/eventCandidates'

export function useCandidatesDuplicateManagement({
  activeTab,
  buildParams,
  confirm,
  error,
  status,
  toast,
}) {
  const duplicatePreview = ref(null)
  const duplicateLoading = ref(false)
  const duplicateMerging = ref(false)
  const duplicateDryRunning = ref(false)
  const duplicateGroupLimit = ref(8)
  const duplicatePerGroup = ref(3)

  let reloadCandidates = async () => {}

  const duplicateSummary = computed(() => duplicatePreview.value?.summary || {
    group_count: 0,
    duplicate_candidates: 0,
    limit_groups: duplicateGroupLimit.value,
    per_group: duplicatePerGroup.value,
  })

  const duplicateGroups = computed(() => {
    const groups = duplicatePreview.value?.groups
    return Array.isArray(groups) ? groups : []
  })

  const canMergeDuplicates = computed(() => {
    return !duplicateMerging.value && !duplicateDryRunning.value && duplicateGroups.value.length > 0
  })

  function buildDuplicateParams() {
    const params = buildParams()
    const limitGroups = Math.max(1, Math.min(50, Number(duplicateGroupLimit.value) || 8))
    const perGroup = Math.max(2, Math.min(10, Number(duplicatePerGroup.value) || 3))

    return {
      status: 'pending',
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
      limit_groups: limitGroups,
      per_group: perGroup,
    }
  }

  async function loadDuplicatePreview() {
    if (activeTab.value !== 'crawled') return

    if (String(status.value || '').toLowerCase() !== 'pending') {
      duplicatePreview.value = null
      return
    }

    duplicateLoading.value = true
    try {
      duplicatePreview.value = await eventCandidates.duplicatesPreview(buildDuplicateParams())
    } catch {
      duplicatePreview.value = null
    } finally {
      duplicateLoading.value = false
    }
  }

  async function mergeDuplicateGroups() {
    if (!canMergeDuplicates.value) return

    const plannedGroups = Number(duplicateSummary.value?.group_count || 0)
    const plannedCandidates = Number(duplicateSummary.value?.duplicate_candidates || 0)

    const ok = await confirm({
      title: 'Zlúčiť duplicity',
      message: `Označiť duplicity ako duplicate? Skupiny: ${plannedGroups}, kandidáti: ${plannedCandidates}.`,
      confirmText: 'Zlúčiť',
      cancelText: 'Zrušiť',
      variant: 'danger',
    })
    if (!ok) return

    duplicateMerging.value = true
    try {
      const params = buildDuplicateParams()
      const result = await eventCandidates.mergeDuplicates({
        ...params,
        limit_groups: params.limit_groups,
        dry_run: false,
      })
      const merged = Number(result?.summary?.merged_candidates || 0)
      toast.success(`Deduplikacia hotova, oznacene duplicate: ${merged}.`)
      await reloadCandidates()
    } catch (e) {
      const message = e?.response?.data?.message || 'Deduplikacia zlyhala'
      error.value = message
      toast.error(message)
    } finally {
      duplicateMerging.value = false
    }
  }

  async function dryRunDuplicateMerge() {
    if (!canMergeDuplicates.value) return

    duplicateDryRunning.value = true
    try {
      const params = buildDuplicateParams()
      const result = await eventCandidates.mergeDuplicates({
        ...params,
        limit_groups: params.limit_groups,
        dry_run: true,
      })

      const groups = Number(result?.summary?.group_count || 0)
      const merged = Number(result?.summary?.merged_candidates || 0)
      toast.success(`Dry-run: skupiny ${groups}, navrh duplicit ${merged}.`)
      await loadDuplicatePreview()
    } catch (e) {
      const message = e?.response?.data?.message || 'Dry-run deduplikacie zlyhal'
      error.value = message
      toast.error(message)
    } finally {
      duplicateDryRunning.value = false
    }
  }

  function setDuplicateReloadHandler(handler) {
    if (typeof handler === 'function') {
      reloadCandidates = handler
    }
  }

  return {
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
  }
}
