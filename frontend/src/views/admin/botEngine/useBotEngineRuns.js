import { computed, ref } from 'vue'
import { runStatusHint, runStatusLabel, statsSummary } from '../botEngineView.utils'

export function useBotEngineRuns({
  props,
  store,
  sources,
  runsPage,
  runItemsPage,
  filters,
  toast,
  normalizeBotIdentity,
  toErrorMessage,
}) {
  const filterForm = ref({
    sourceKey: '',
    bot_identity: '',
    status: '',
    date_from: '',
    date_to: '',
    per_page: 20,
  })

  const normalizedPresetBotIdentity = computed(() => normalizeBotIdentity(props.presetBotIdentity))
  const hasPresetBotIdentity = computed(() => normalizedPresetBotIdentity.value !== '')
  const effectiveBotIdentity = computed(() => {
    if (hasPresetBotIdentity.value) {
      return normalizedPresetBotIdentity.value
    }
    return normalizeBotIdentity(filterForm.value.bot_identity)
  })

  const runs = computed(() => (Array.isArray(runsPage.value?.data) ? runsPage.value.data : []))
  const runsMeta = computed(() => runsPage.value?.meta || null)
  const runItems = computed(() =>
    Array.isArray(runItemsPage.value?.data) ? runItemsPage.value.data : [],
  )
  const runItemsMeta = computed(() => runItemsPage.value?.meta || null)
  const canPrevPage = computed(() => (runsMeta.value?.current_page || 1) > 1)
  const canNextPage = computed(() => {
    const current = runsMeta.value?.current_page || 1
    const last = runsMeta.value?.last_page || 1
    return current < last
  })
  const canPrevItemsPage = computed(() => (runItemsMeta.value?.current_page || 1) > 1)
  const canNextItemsPage = computed(() => {
    const current = runItemsMeta.value?.current_page || 1
    const last = runItemsMeta.value?.last_page || 1
    return current < last
  })

  const filteredSources = computed(() => {
    if (!Array.isArray(sources.value)) {
      return []
    }

    if (effectiveBotIdentity.value === '') {
      return sources.value
    }

    return sources.value.filter(
      (source) => normalizeBotIdentity(source?.bot_identity) === effectiveBotIdentity.value,
    )
  })

  const sourceOptions = computed(() => {
    return Array.isArray(filteredSources.value)
      ? filteredSources.value.map((source) => String(source?.key || '')).filter((key) => key !== '')
      : []
  })

  const enabledSourcesByIdentity = computed(() => {
    const grouped = {
      kozmo: [],
      stela: [],
    }

    for (const source of Array.isArray(sources.value) ? sources.value : []) {
      const identity = normalizeBotIdentity(source?.bot_identity)
      if (!identity || !source?.is_enabled) {
        continue
      }

      if (!Array.isArray(grouped[identity])) {
        continue
      }

      grouped[identity].push(source)
    }

    return grouped
  })

  const hasEnabledSources = computed(() => {
    return (
      enabledSourcesByIdentity.value.kozmo.length > 0 ||
      enabledSourcesByIdentity.value.stela.length > 0
    )
  })

  function syncFilterFormFromStore() {
    const nextBotIdentity = hasPresetBotIdentity.value
      ? normalizedPresetBotIdentity.value
      : normalizeBotIdentity(filters.value?.bot_identity)

    filterForm.value = {
      sourceKey: String(filters.value?.sourceKey || ''),
      bot_identity: nextBotIdentity,
      status: String(filters.value?.status || ''),
      date_from: String(filters.value?.date_from || ''),
      date_to: String(filters.value?.date_to || ''),
      per_page: Number(filters.value?.per_page) || 20,
    }
  }

  function withBotIdentityConstraint(input = {}) {
    const next = { ...(input || {}) }
    if (hasPresetBotIdentity.value) {
      next.bot_identity = normalizedPresetBotIdentity.value
    } else {
      next.bot_identity = normalizeBotIdentity(next.bot_identity)
    }
    return next
  }

  function normalizeSourceKeyForVisibleSources() {
    const selectedSourceKey = String(filterForm.value.sourceKey || '')
    if (selectedSourceKey === '') {
      return
    }

    if (!Array.isArray(filteredSources.value) || filteredSources.value.length === 0) {
      return
    }

    if (sourceOptions.value.includes(selectedSourceKey)) {
      return
    }

    filterForm.value = {
      ...filterForm.value,
      sourceKey: '',
    }
  }

  async function loadSources() {
    try {
      await store.fetchSources()
      normalizeSourceKeyForVisibleSources()
    } catch (error) {
      toast.error(toErrorMessage(error, 'Nepodarilo sa načítať zdroje.'))
    }
  }

  async function loadRuns(params = {}) {
    try {
      await store.fetchRuns(withBotIdentityConstraint(params))
      syncFilterFormFromStore()
      normalizeSourceKeyForVisibleSources()
    } catch (error) {
      toast.error(toErrorMessage(error, 'Nepodarilo sa načítať behy.'))
    }
  }

  async function applyRunsFilters() {
    await loadRuns(
      withBotIdentityConstraint({
        ...filterForm.value,
        page: 1,
      }),
    )
  }

  async function resetRunsFilters() {
    const defaults = withBotIdentityConstraint(store.resetFilters())
    filterForm.value = {
      sourceKey: defaults.sourceKey,
      bot_identity: defaults.bot_identity,
      status: defaults.status,
      date_from: defaults.date_from,
      date_to: defaults.date_to,
      per_page: defaults.per_page,
    }

    await loadRuns({
      ...defaults,
      page: 1,
    })
  }

  async function goToPage(page) {
    await loadRuns({ page })
  }

  async function runNow(sourceKey, mode = 'auto') {
    try {
      const result = await store.runSource(sourceKey, { mode })
      if (!result) {
        return
      }

      const hint = runStatusHint(result)
      toast.success(
        `${runStatusLabel(result)} | ${statsSummary(result.stats)}${hint ? ` | ${hint}` : ''}`,
      )

      await Promise.all([loadSources(), loadRuns()])
    } catch (error) {
      toast.error(toErrorMessage(error, 'Spustenie zlyhalo.'))
    }
  }

  return {
    applyRunsFilters,
    canNextItemsPage,
    canNextPage,
    canPrevItemsPage,
    canPrevPage,
    effectiveBotIdentity,
    enabledSourcesByIdentity,
    filterForm,
    filteredSources,
    goToPage,
    hasEnabledSources,
    hasPresetBotIdentity,
    loadRuns,
    loadSources,
    normalizedPresetBotIdentity,
    resetRunsFilters,
    runItems,
    runItemsMeta,
    runNow,
    runs,
    runsMeta,
    sourceOptions,
    syncFilterFormFromStore,
    withBotIdentityConstraint,
  }
}
