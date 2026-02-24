<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { useBotEngineStore } from '@/stores/botEngine'
import { useToast } from '@/composables/useToast'
import { BOT_FAILURE_REASONS, BOT_FAILURE_REASON_MESSAGES } from '@/constants/botFailureReasons'

const props = defineProps({
  presetBotIdentity: {
    type: String,
    default: '',
  },
  presetLabel: {
    type: String,
    default: '',
  },
})

const store = useBotEngineStore()
const toast = useToast()
const DEFAULT_PUBLISH_ALL_LIMIT = 3
const VALID_BOT_IDENTITIES = ['kozmo', 'stela']
const BOT_LABELS = Object.freeze({
  kozmo: 'KozmoBot',
  stela: 'StellarBot',
})

const {
  sources,
  runsPage,
  runItemsPage,
  filters,
  loadingSources,
  loadingRuns,
  loadingRunItems,
  translationHealth,
  loadingTranslationHealth,
  savingTranslationOutage,
} = storeToRefs(store)
const selectedRun = ref(null)
const selectedPreviewItem = ref(null)
const publishAllLimit = ref(DEFAULT_PUBLISH_ALL_LIMIT)
const retryTranslationLimit = ref(10)
const translationTestText = ref('NASA studies the Sun and planets in our Solar System.')
const translationTestResult = ref(null)
const translationOutageProvider = ref('none')

const filterForm = ref({
  sourceKey: '',
  bot_identity: '',
  status: '',
  date_from: '',
  date_to: '',
  per_page: 20,
})

function normalizeBotIdentity(value) {
  const normalized = String(value || '').trim().toLowerCase()
  return VALID_BOT_IDENTITIES.includes(normalized) ? normalized : ''
}

const normalizedPresetBotIdentity = computed(() => normalizeBotIdentity(props.presetBotIdentity))
const hasPresetBotIdentity = computed(() => normalizedPresetBotIdentity.value !== '')
const effectiveBotIdentity = computed(() => {
  if (hasPresetBotIdentity.value) {
    return normalizedPresetBotIdentity.value
  }
  return normalizeBotIdentity(filterForm.value.bot_identity)
})

const pageTitle = computed(() => {
  if (String(props.presetLabel || '').trim() !== '') {
    return `${props.presetLabel} Bot Engine`
  }
  if (effectiveBotIdentity.value === 'kozmo') {
    return 'KozmoBot Engine'
  }
  if (effectiveBotIdentity.value === 'stela') {
    return 'StelaBot Engine'
  }
  return 'Bot Engine'
})

const pageSubtitle = computed(() => {
  if (effectiveBotIdentity.value === 'kozmo') {
    return 'Run Kozmo sources manually and inspect run history.'
  }
  if (effectiveBotIdentity.value === 'stela') {
    return 'Run Stela sources manually and inspect run history.'
  }
  return 'Run bot sources manually and inspect run history.'
})

const runs = computed(() => (Array.isArray(runsPage.value?.data) ? runsPage.value.data : []))
const runsMeta = computed(() => runsPage.value?.meta || null)
const runItems = computed(() => (Array.isArray(runItemsPage.value?.data) ? runItemsPage.value.data : []))
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

  return sources.value.filter((source) => normalizeBotIdentity(source?.bot_identity) === effectiveBotIdentity.value)
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

const quickRunBusyIdentity = ref('')

function toErrorMessage(error, fallbackMessage) {
  const status = Number(error?.response?.status || 0)
  const code = String(error?.code || '').trim().toUpperCase()
  const messageText = String(error?.message || '').trim().toLowerCase()
  const retryAfter = Number(error?.response?.data?.retry_after || 0)
  const failureReason = String(
    error?.response?.data?.failure_reason || error?.response?.data?.meta?.failure_reason || '',
  )
    .trim()
    .toLowerCase()
  const baseMessage = error?.response?.data?.message || error?.userMessage || error?.message || fallbackMessage
  const isTimeoutOrNetwork = code === 'ECONNABORTED' || code === 'ERR_NETWORK' || messageText.includes('timeout')

  if (isTimeoutOrNetwork) {
    return 'Run trva dlhsie. Skus to o chvilu, alebo otvor detail runu.'
  }

  if ([
    BOT_FAILURE_REASONS.RATE_LIMITED,
    BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED,
    BOT_FAILURE_REASONS.NEEDS_API_KEY,
  ].includes(failureReason)) {
    return (
      error?.response?.data?.ui_message ||
      error?.response?.data?.meta?.ui_message ||
      BOT_FAILURE_REASON_MESSAGES[failureReason] ||
      baseMessage
    )
  }

  if (BOT_FAILURE_REASON_MESSAGES[failureReason]) {
    return BOT_FAILURE_REASON_MESSAGES[failureReason]
  }

  if (status === 429 && retryAfter > 0) {
    return `${baseMessage} Retry in ${retryAfter}s.`
  }

  return (
    baseMessage
  )
}

function toStatNumber(value) {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

function statsSummary(stats) {
  const source = stats && typeof stats === 'object' ? stats : {}

  return [
    `fetched ${toStatNumber(source.fetched_count)}`,
    `new ${toStatNumber(source.new_count)}`,
    `dupes ${toStatNumber(source.dupes_count)}`,
    `published ${toStatNumber(source.published_count)}`,
    `skipped ${toStatNumber(source.skipped_count)}`,
    `failed ${toStatNumber(source.failed_count)}`,
  ].join(' | ')
}

function formatDateTime(value) {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

function formatStatsJson(stats) {
  if (!stats || typeof stats !== 'object') {
    return '{}'
  }

  return JSON.stringify(stats, null, 2)
}

function formatBool(value) {
  if (value === true) return 'yes'
  if (value === false) return 'no'
  return '-'
}

function formatStableKey(value) {
  const stableKey = String(value || '').trim()
  if (stableKey.length <= 44) {
    return stableKey || '-'
  }

  return `${stableKey.slice(0, 22)}...${stableKey.slice(-18)}`
}

function itemStatusClass(status) {
  const normalized = String(status || '').toLowerCase()
  if (normalized === 'published' || normalized === 'done') return 'statusBadge statusBadge--success'
  if (normalized === 'skipped') return 'statusBadge statusBadge--partial'
  if (normalized === 'failed') return 'statusBadge statusBadge--failed'
  return 'statusBadge statusBadge--muted'
}

function translationProviderLabel(provider) {
  const normalized = String(provider || '').trim().toLowerCase()
  if (!normalized) return '-'
  if (normalized === 'libretranslate') return 'LT'
  if (normalized === 'ollama') return 'Ollama'
  if (normalized === 'ollama_postedit') return 'Ollama PE'
  if (normalized === 'mixed') return 'Mixed'
  return normalized
}

function translationProviderClass(provider) {
  const normalized = String(provider || '').trim().toLowerCase()
  if (normalized === 'libretranslate') return 'providerBadge providerBadge--lt'
  if (normalized === 'ollama') return 'providerBadge providerBadge--ollama'
  if (normalized === 'ollama_postedit') return 'providerBadge providerBadge--ollama'
  if (normalized === 'mixed') return 'providerBadge providerBadge--mixed'
  return 'providerBadge providerBadge--muted'
}

function translationModeLabel(mode) {
  const normalized = String(mode || '').trim().toLowerCase()
  if (normalized === 'lt_ollama_postedit') return 'LT+Ollama post-edit'
  if (normalized === 'ollama_direct') return 'Ollama direct'
  if (normalized === 'lt_only') return 'LT-only'
  return normalized || '-'
}

function toPositiveIntOrNull(value) {
  const parsed = Number(value)
  if (Number.isInteger(parsed) && parsed > 0) {
    return parsed
  }

  return null
}

function normalizeRunMode(value) {
  return String(value || '').trim().toLowerCase() === 'dry' ? 'dry' : 'auto'
}

function runModeLabel(run) {
  return normalizeRunMode(run?.meta?.mode) === 'dry' ? 'DRY' : 'AUTO'
}

function runModeClass(run) {
  return runModeLabel(run) === 'DRY' ? 'modeBadge modeBadge--dry' : 'modeBadge modeBadge--auto'
}

function runPublishLimit(run) {
  return toPositiveIntOrNull(run?.meta?.publish_limit)
}

function resolvePublishAllLimitDefault(run) {
  return runPublishLimit(run) ?? DEFAULT_PUBLISH_ALL_LIMIT
}

function normalizeMaybeBool(value) {
  if (value === true) return true
  if (value === false) return false

  if (typeof value === 'number') {
    if (value === 1) return true
    if (value === 0) return false
    return null
  }

  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase()
    if (['1', 'true', 'yes'].includes(normalized)) return true
    if (['0', 'false', 'no'].includes(normalized)) return false
  }

  return null
}

function isPublishedItem(item) {
  const status = String(item?.publish_status || '').toLowerCase()
  if (status === 'published') return true
  return Number(item?.post_id || 0) > 0
}

function isManualPublishedItem(item) {
  if (!isPublishedItem(item)) {
    return false
  }

  const candidates = [
    item?.published_manually,
    item?.meta?.published_manually,
    item?.post_meta?.published_manually,
    item?.post?.meta?.published_manually,
  ]

  return candidates.some((value) => normalizeMaybeBool(value) === true)
}

function resolveItemSourceKey(item) {
  const candidates = [
    item?.source_key,
    item?.bot_source_key,
    item?.meta?.bot_source_key,
    item?.meta?.source_key,
    item?.post_meta?.bot_source_key,
    item?.post?.bot_source_key,
    item?.post?.meta?.bot_source_key,
    selectedRun.value?.source_key,
  ]

  for (const candidate of candidates) {
    const normalized = String(candidate || '').trim().toLowerCase()
    if (normalized !== '') {
      return normalized
    }
  }

  return ''
}

function requiresPublishConfirm(item) {
  return resolveItemSourceKey(item) === 'wiki_onthisday_astronomy'
}

function confirmPublishToAstroFeed() {
  if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
    return window.confirm('Publikovať do AstroFeed?')
  }

  return true
}

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

function botIdentityLabel(identity) {
  return BOT_LABELS[identity] || identity
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
    toast.error(toErrorMessage(error, 'Failed to load bot sources.'))
  }
}

async function loadRuns(params = {}) {
  try {
    await store.fetchRuns(withBotIdentityConstraint(params))
    syncFilterFormFromStore()
    normalizeSourceKeyForVisibleSources()
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to load bot runs.'))
  }
}

function normalizeOutageProvider(value) {
  const normalized = String(value || '').trim().toLowerCase()
  return ['none', 'ollama', 'libretranslate'].includes(normalized) ? normalized : 'none'
}

async function loadTranslationHealth() {
  try {
    const health = await store.fetchTranslationHealth()
    translationOutageProvider.value = normalizeOutageProvider(health?.simulate_outage_provider)
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to load translation health.'))
  }
}

async function initialize() {
  await Promise.all([loadSources(), loadRuns(), loadTranslationHealth()])
}

async function applyRunsFilters() {
  await loadRuns(withBotIdentityConstraint({
    ...filterForm.value,
    page: 1,
  }))
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
    toast.success(`${runStatusLabel(result)} | ${statsSummary(result.stats)}${hint ? ` | ${hint}` : ''}`)

    await Promise.all([loadSources(), loadRuns()])
  } catch (error) {
    toast.error(toErrorMessage(error, 'Bot run failed.'))
  }
}

async function quickRunIdentity(identity) {
  const normalizedIdentity = normalizeBotIdentity(identity)
  if (normalizedIdentity === '') {
    return
  }

  const enabledSources = Array.isArray(enabledSourcesByIdentity.value[normalizedIdentity])
    ? enabledSourcesByIdentity.value[normalizedIdentity]
    : []

  if (enabledSources.length === 0) {
    toast.info(`${botIdentityLabel(normalizedIdentity)} has no enabled sources.`)
    return
  }

  quickRunBusyIdentity.value = normalizedIdentity

  let successCount = 0
  let partialCount = 0
  let failedCount = 0
  let lastErrorMessage = ''

  for (const source of enabledSources) {
    try {
      const result = await store.runSource(source.key, {
        mode: 'auto',
        force_manual_override: true,
      })
      const status = String(result?.status || '').toLowerCase()

      if (status === 'success') {
        successCount++
      } else if (status === 'partial') {
        partialCount++
      } else {
        failedCount++
      }
    } catch (error) {
      failedCount++
      lastErrorMessage = toErrorMessage(error, `Failed to run source "${source.key}".`)
    }
  }

  quickRunBusyIdentity.value = ''

  await Promise.all([loadSources(), loadRuns()])

  const processedCount = successCount + partialCount + failedCount
  const summary = `${botIdentityLabel(normalizedIdentity)} run done. Sources ${processedCount}, success ${successCount}, partial ${partialCount}, failed ${failedCount}.`

  if (failedCount > 0 && lastErrorMessage !== '') {
    toast.error(`${summary} ${lastErrorMessage}`)
    return
  }

  if (failedCount > 0) {
    toast.error(summary)
    return
  }

  toast.success(summary)
}

async function dryRun(sourceKey) {
  await runNow(sourceKey, 'dry')
}

async function openRunDetail(run) {
  selectedRun.value = run
  publishAllLimit.value = resolvePublishAllLimitDefault(run)

  try {
    await store.fetchItemsForRun(run?.id, { page: 1, per_page: 20 })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to load run items.'))
  }
}

function closeRunDetail() {
  selectedRun.value = null
  selectedPreviewItem.value = null
  publishAllLimit.value = DEFAULT_PUBLISH_ALL_LIMIT
  retryTranslationLimit.value = 10
  store.clearRunItems()
}

function openItemPreview(item) {
  selectedPreviewItem.value = item || null
}

function closeItemPreview() {
  selectedPreviewItem.value = null
}

function canPublishItem(item) {
  const status = String(item?.publish_status || '').toLowerCase()
  if (status === 'published' || status === 'skipped') return false
  return !item?.post_id
}

function canDeleteItemPost(item) {
  const postId = Number(item?.post_id || 0)
  return Number.isInteger(postId) && postId > 0
}

function confirmDeletePublishedPost() {
  if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
    return window.confirm('Delete published bot post from feed?')
  }

  return true
}

function confirmBackfillTranslation() {
  if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
    return window.confirm('Backfill translation for already published posts?')
  }

  return true
}

async function goToItemsPage(page) {
  if (!selectedRun.value?.id) {
    return
  }

  try {
    await store.fetchItemsForRun(selectedRun.value.id, {
      page,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to load run items.'))
  }
}

async function publishItem(item) {
  if (!item?.id || !canPublishItem(item)) return
  if (requiresPublishConfirm(item) && !confirmPublishToAstroFeed()) return

  try {
    const response = await store.publishItem(item.id, { force: false })
    if (response?.already_published) {
      toast.info('Item is already published.')
    } else {
      toast.success('Item published.')
    }

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to publish item.'))
  }
}

async function deleteItemPost(item) {
  if (!item?.id || !canDeleteItemPost(item)) return
  if (!confirmDeletePublishedPost()) return

  try {
    await store.deleteItemPost(item.id)
    toast.success('Published post deleted.')

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to delete published post.'))
  }
}

async function publishAllForRun() {
  const runId = Number(selectedRun.value?.id || 0)
  if (!Number.isInteger(runId) || runId <= 0) return

  const limit = Number(publishAllLimit.value)
  const normalizedLimit = Number.isInteger(limit) && limit > 0 ? limit : DEFAULT_PUBLISH_ALL_LIMIT

  try {
    const response = await store.publishRun(runId, { publish_limit: normalizedLimit })
    const publishedCount = Number(response?.published_count || 0)
    const skippedCount = Number(response?.skipped_count || 0)
    const failedCount = Number(response?.failed_count || 0)

    toast.success(
      `Published ${publishedCount} item(s). Skipped ${skippedCount}, failed ${failedCount}.`,
    )

    await store.fetchItemsForRun(runId, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to publish run items.'))
  }
}

async function testTranslation() {
  try {
    const payload = {
      text: String(translationTestText.value || '').trim(),
    }
    const result = await store.testTranslation(payload)
    if (!result) {
      return
    }

    translationTestResult.value = result
    const provider = translationProviderLabel(result.provider)
    toast.success(`Translation test OK (${provider}, ${Number(result.latency_ms || 0)} ms).`)
  } catch (error) {
    translationTestResult.value = null
    toast.error(toErrorMessage(error, 'Translation test failed.'))
  }
}

async function saveTranslationOutageSimulation() {
  const provider = normalizeOutageProvider(translationOutageProvider.value)

  try {
    const response = await store.setTranslationOutageProvider(provider)
    translationOutageProvider.value = normalizeOutageProvider(response?.new_value || provider)
    await loadTranslationHealth()
    toast.success(`Outage simulation saved (${translationOutageProvider.value}).`)
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to save outage simulation setting.'))
  }
}

async function retryTranslateForRun() {
  const sourceKey = String(selectedRun.value?.source_key || '').trim()
  if (!sourceKey) {
    return
  }

  const limit = Number(retryTranslationLimit.value)
  const normalizedLimit = Number.isInteger(limit) && limit > 0 ? limit : 10

  try {
    const result = await store.retryTranslation(sourceKey, {
      limit: normalizedLimit,
      run_id: selectedRun.value?.id,
    })
    if (!result) {
      return
    }

    toast.success(
      `Retry done: ${Number(result.done_count || 0)} ok, ${Number(result.skipped_count || 0)} skipped, ${Number(result.failed_count || 0)} failed.`,
    )

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Retry translation failed.'))
  }
}

async function backfillTranslateForRun() {
  const sourceKey = String(selectedRun.value?.source_key || '').trim()
  if (!sourceKey) {
    return
  }

  if (!confirmBackfillTranslation()) {
    return
  }

  const limit = Number(retryTranslationLimit.value)
  const normalizedLimit = Number.isInteger(limit) && limit > 0 ? limit : 10

  try {
    const result = await store.backfillTranslation(sourceKey, {
      limit: normalizedLimit,
      run_id: selectedRun.value?.id,
    })
    if (!result) {
      return
    }

    toast.success(
      `Backfill done: updated ${Number(result.updated_posts || 0)}, skipped ${Number(result.skipped || 0)}, failed ${Number(result.failed || 0)}.`,
    )

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Backfill translation failed.'))
  }
}

function statusClass(status) {
  const normalizedReason = String(status?.failure_reason || status?.meta?.failure_reason || '').toLowerCase()
  if ([
    BOT_FAILURE_REASONS.RATE_LIMITED,
    BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED,
    BOT_FAILURE_REASONS.NEEDS_API_KEY,
  ].includes(normalizedReason)) {
    return 'statusBadge statusBadge--partial'
  }

  const normalized = String(status?.status || status || '').toLowerCase()
  if (normalized === 'success') return 'statusBadge statusBadge--success'
  if (normalized === 'partial') return 'statusBadge statusBadge--partial'
  if (normalized === 'failed') return 'statusBadge statusBadge--failed'
  return 'statusBadge statusBadge--muted'
}

function runStatusLabel(run) {
  const reason = String(run?.failure_reason || run?.meta?.failure_reason || '').toLowerCase()
  if (reason === BOT_FAILURE_REASONS.RATE_LIMITED || reason === BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED) {
    return 'Rate limited'
  }
  if (reason === BOT_FAILURE_REASONS.NEEDS_API_KEY) {
    return 'Needs API key'
  }

  return String(run?.status || 'unknown')
}

function runStatusHint(run) {
  return (
    run?.ui_message ||
    run?.meta?.ui_message ||
    run?.error_text ||
    ''
  )
}

onMounted(async () => {
  syncFilterFormFromStore()
  if (hasPresetBotIdentity.value) {
    filterForm.value = {
      ...filterForm.value,
      bot_identity: normalizedPresetBotIdentity.value,
      sourceKey: '',
    }
  }
  await initialize()
})
</script>

<template>
  <AdminPageShell :title="pageTitle" :subtitle="pageSubtitle">
    <section class="card">
      <header class="sectionHeader">
        <div>
          <h2 class="sectionTitle">Quick Run</h2>
          <p class="sectionSubtitle">One click run in AUTO mode. New items are published immediately.</p>
        </div>
      </header>

      <div class="quickRunGrid">
        <button
          type="button"
          class="runBtn quickRunBtn"
          data-testid="quick-run-kozmo"
          :disabled="quickRunBusyIdentity !== ''"
          @click="quickRunIdentity('kozmo')"
        >
          {{ quickRunBusyIdentity === 'kozmo' ? 'Running KozmoBot...' : 'Run KozmoBot' }}
          <small class="quickRunHint">{{ enabledSourcesByIdentity.kozmo.length }} enabled source(s)</small>
        </button>

        <button
          type="button"
          class="runBtn quickRunBtn"
          data-testid="quick-run-stela"
          :disabled="quickRunBusyIdentity !== ''"
          @click="quickRunIdentity('stela')"
        >
          {{ quickRunBusyIdentity === 'stela' ? 'Running StellarBot...' : 'Run StellarBot' }}
          <small class="quickRunHint">{{ enabledSourcesByIdentity.stela.length }} enabled source(s)</small>
        </button>
      </div>
    </section>

    <section class="card">
      <header class="sectionHeader">
        <div>
          <h2 class="sectionTitle">Sources</h2>
          <p class="sectionSubtitle">Registered bot sources with manual run action.</p>
        </div>
        <button type="button" class="ghostBtn" :disabled="loadingSources" @click="loadSources">
          Refresh
        </button>
      </header>

      <div class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>key</th>
              <th>bot_identity</th>
              <th>source_type</th>
              <th>is_enabled</th>
              <th>last_run_at</th>
              <th>cooldown_until</th>
              <th class="alignRight">actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loadingSources">
              <tr v-for="index in 5" :key="`sources-skeleton-${index}`">
                <td colspan="7">
                  <div class="skeletonRow"></div>
                </td>
              </tr>
            </template>
            <tr v-else-if="filteredSources.length === 0">
              <td colspan="7" class="emptyCell">No sources available.</td>
            </tr>
            <tr v-else v-for="source in filteredSources" :key="source.id || source.key">
              <td><code>{{ source.key }}</code></td>
              <td>{{ source.bot_identity || '-' }}</td>
              <td>
                <div class="sourceTypeCell">
                  <span>{{ source.source_type || '-' }}</span>
                  <small v-if="source.key === 'nasa_apod_daily'" class="sourceHint">
                    Set NASA_API_KEY in .env to enable APOD.
                  </small>
                </div>
              </td>
              <td>
                <span class="statusBadge" :class="source.is_enabled ? 'statusBadge--success' : 'statusBadge--muted'">
                  {{ source.is_enabled ? 'enabled' : 'disabled' }}
                </span>
              </td>
              <td>{{ formatDateTime(source.last_run_at) }}</td>
              <td>{{ formatDateTime(source.cooldown_until) }}</td>
              <td class="alignRight">
                <div class="inlineActions inlineActions--end">
                  <button
                    type="button"
                    class="runBtn"
                    :disabled="!source.is_enabled || store.isSourceRunning(source.key)"
                    @click="runNow(source.key, 'auto')"
                  >
                    {{ store.isSourceRunning(source.key) ? 'Running...' : 'Run now' }}
                  </button>
                  <button
                    type="button"
                    class="ghostBtn"
                    :disabled="!source.is_enabled || store.isSourceRunning(source.key)"
                    @click="dryRun(source.key)"
                  >
                    Dry run
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <header class="sectionHeader">
        <div>
          <h2 class="sectionTitle">Translation Tools</h2>
          <p class="sectionSubtitle">1-click test for EN→SK translation via primary provider with fallback.</p>
        </div>
      </header>

      <div class="translationTools">
        <label class="filterField">
          <span>simulate provider outage</span>
          <div class="inlineActions">
            <select v-model="translationOutageProvider" data-testid="translation-outage-provider">
              <option value="none">none</option>
              <option value="ollama">ollama</option>
              <option value="libretranslate">libretranslate</option>
            </select>
            <button
              type="button"
              class="ghostBtn"
              data-testid="translation-outage-save"
              :disabled="savingTranslationOutage"
              @click="saveTranslationOutageSimulation"
            >
              {{ savingTranslationOutage ? 'Saving...' : 'Save outage toggle' }}
            </button>
          </div>
        </label>
        <p
          v-if="translationHealth"
          data-testid="translation-health-state"
          class="translationSummary"
        >
          Health: {{ translationHealth.degraded ? 'degraded (fallback active)' : (translationHealth.result?.ok ? 'ok' : 'down') }}
          | provider: {{ translationHealth.provider || '-' }}
          | simulated outage: {{ translationHealth.simulate_outage_provider || 'none' }}
        </p>
        <label class="filterField">
          <span>test text</span>
          <textarea
            v-model="translationTestText"
            rows="3"
            maxlength="5000"
            placeholder="Enter short English text for translation test."
          />
        </label>
        <div class="inlineActions">
          <button type="button" class="runBtn" :disabled="store.testingTranslation || loadingTranslationHealth" @click="testTranslation">
            {{ store.testingTranslation ? 'Testing...' : 'Test translation' }}
          </button>
        </div>
        <div v-if="translationTestResult" class="translationResult">
          <div class="inlineActions">
            <span :class="translationProviderClass(translationTestResult.provider)">
              {{ translationProviderLabel(translationTestResult.provider) }}
            </span>
            <span class="modeHint">{{ Number(translationTestResult.latency_ms || 0) }} ms</span>
            <span class="modeHint">mode: {{ translationModeLabel(translationTestResult.mode) }}</span>
          </div>
          <p class="translationSummary">
            chain: {{ Array.isArray(translationTestResult.provider_chain) && translationTestResult.provider_chain.length > 0 ? translationTestResult.provider_chain.join(' -> ') : '-' }}
            | quality: {{ Array.isArray(translationTestResult.quality_flags) && translationTestResult.quality_flags.length > 0 ? translationTestResult.quality_flags.join(', ') : 'ok' }}
          </p>
          <p>{{ translationTestResult.translated_text || '-' }}</p>
        </div>
      </div>
    </section>

    <section class="card">
      <header class="sectionHeader">
        <div>
          <h2 class="sectionTitle">Runs</h2>
          <p class="sectionSubtitle">Filter and inspect historical bot runs.</p>
        </div>
        <button type="button" class="ghostBtn" :disabled="loadingRuns" @click="loadRuns()">
          Refresh
        </button>
      </header>

      <form class="filters" @submit.prevent="applyRunsFilters">
        <label v-if="!hasPresetBotIdentity" class="filterField">
          <span>bot_identity</span>
          <select v-model="filterForm.bot_identity">
            <option value="">All</option>
            <option value="kozmo">kozmo</option>
            <option value="stela">stela</option>
          </select>
        </label>
        <label v-else class="filterField">
          <span>bot_identity</span>
          <input :value="filterForm.bot_identity || '-'" type="text" readonly />
        </label>

        <label class="filterField">
          <span>sourceKey</span>
          <select v-model="filterForm.sourceKey">
            <option value="">All</option>
            <option v-for="sourceKey in sourceOptions" :key="sourceKey" :value="sourceKey">
              {{ sourceKey }}
            </option>
          </select>
        </label>

        <label class="filterField">
          <span>status</span>
          <select v-model="filterForm.status">
            <option value="">All</option>
            <option value="success">success</option>
            <option value="partial">partial</option>
            <option value="failed">failed</option>
          </select>
        </label>

        <label class="filterField">
          <span>date_from</span>
          <input v-model="filterForm.date_from" type="date" />
        </label>

        <label class="filterField">
          <span>date_to</span>
          <input v-model="filterForm.date_to" type="date" />
        </label>

        <label class="filterField">
          <span>per_page</span>
          <select v-model.number="filterForm.per_page">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
        </label>

        <div class="filterActions">
          <button type="submit" class="runBtn" :disabled="loadingRuns">Apply</button>
          <button type="button" class="ghostBtn" :disabled="loadingRuns" @click="resetRunsFilters">Reset</button>
        </div>
      </form>

      <div class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>started_at</th>
              <th>source_key</th>
              <th>status</th>
              <th>mode</th>
              <th>stats summary</th>
              <th class="alignRight">actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loadingRuns">
              <tr v-for="index in 6" :key="`runs-skeleton-${index}`">
                <td colspan="6">
                  <div class="skeletonRow"></div>
                </td>
              </tr>
            </template>
            <tr v-else-if="runs.length === 0">
              <td colspan="6" class="emptyCell">No runs found for selected filters.</td>
            </tr>
            <tr v-else v-for="run in runs" :key="run.id">
              <td>{{ formatDateTime(run.started_at) }}</td>
              <td><code>{{ run.source_key || '-' }}</code></td>
              <td>
                <span :class="statusClass(run)" :title="runStatusHint(run)" data-testid="run-status-badge">
                  {{ runStatusLabel(run) }}
                </span>
              </td>
              <td>
                <div class="runModeCell">
                  <span :class="runModeClass(run)" data-testid="run-mode-badge">{{ runModeLabel(run) }}</span>
                  <span v-if="runPublishLimit(run) !== null" class="modeHint" data-testid="run-mode-limit">
                    limit: {{ runPublishLimit(run) }}
                  </span>
                </div>
              </td>
              <td>{{ statsSummary(run.stats) }}</td>
              <td class="alignRight">
                <button type="button" class="ghostBtn" @click="openRunDetail(run)">Detail</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="runsMeta" class="pagination">
        <div class="paginationInfo">
          Page {{ runsMeta.current_page }} / {{ runsMeta.last_page }} (total {{ runsMeta.total }})
        </div>
        <div class="paginationActions">
          <button type="button" class="ghostBtn" :disabled="!canPrevPage || loadingRuns" @click="goToPage((runsMeta.current_page || 1) - 1)">
            Prev
          </button>
          <button type="button" class="ghostBtn" :disabled="!canNextPage || loadingRuns" @click="goToPage((runsMeta.current_page || 1) + 1)">
            Next
          </button>
        </div>
      </footer>
    </section>

    <teleport to="body">
      <div v-if="selectedRun" class="modalBackdrop" @click.self="closeRunDetail">
        <article class="modalCard" role="dialog" aria-modal="true" aria-label="Bot run detail">
          <header class="modalHeader">
            <h3>Run detail</h3>
            <button type="button" class="ghostBtn" @click="closeRunDetail">Close</button>
          </header>

          <dl class="detailGrid">
            <div>
              <dt>source_key</dt>
              <dd><code>{{ selectedRun.source_key || '-' }}</code></dd>
            </div>
            <div>
              <dt>started_at</dt>
              <dd>{{ formatDateTime(selectedRun.started_at) }}</dd>
            </div>
            <div>
              <dt>finished_at</dt>
              <dd>{{ formatDateTime(selectedRun.finished_at) }}</dd>
            </div>
            <div>
              <dt>status</dt>
              <dd>
                <span :class="statusClass(selectedRun)" :title="runStatusHint(selectedRun)">
                  {{ runStatusLabel(selectedRun) }}
                </span>
              </dd>
            </div>
            <div>
              <dt>cooldown_until</dt>
              <dd>{{ formatDateTime(selectedRun.cooldown_until || selectedRun.meta?.cooldown_until) }}</dd>
            </div>
          </dl>

          <div class="detailBlock">
            <h4>stats</h4>
            <pre>{{ formatStatsJson(selectedRun.stats) }}</pre>
          </div>

          <div class="detailBlock">
            <h4>error_text</h4>
            <p>{{ selectedRun.error_text || '-' }}</p>
          </div>

          <div class="detailBlock">
            <div class="detailBlockHeader">
              <h4>items</h4>
              <div class="inlineActions">
                <label class="inlineField">
                  <span>Limit</span>
                  <input v-model.number="publishAllLimit" data-testid="publish-all-limit" type="number" min="1" max="100" />
                </label>
                <label class="inlineField">
                  <span>Retry N</span>
                  <input v-model.number="retryTranslationLimit" type="number" min="1" max="100" />
                </label>
                <button
                  type="button"
                  class="runBtn"
                  :disabled="store.isRunPublishing(selectedRun?.id)"
                  @click="publishAllForRun"
                >
                  {{ store.isRunPublishing(selectedRun?.id) ? 'Publishing...' : 'Publish all' }}
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="store.isTranslationRetrying(selectedRun?.source_key)"
                  @click="retryTranslateForRun"
                >
                  {{ store.isTranslationRetrying(selectedRun?.source_key) ? 'Retrying...' : 'Retry translate' }}
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="store.isTranslationBackfilling(selectedRun?.source_key)"
                  @click="backfillTranslateForRun"
                >
                  {{ store.isTranslationBackfilling(selectedRun?.source_key) ? 'Backfilling...' : 'Backfill translation (update posts)' }}
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="loadingRunItems"
                  @click="goToItemsPage(1)"
                >
                  Refresh items
                </button>
              </div>
            </div>

            <div class="tableWrap">
              <table class="table table--compact">
                <thead>
                  <tr>
                    <th>stable_key</th>
                    <th>publish_status</th>
                    <th>translation_status</th>
                    <th>provider</th>
                    <th>post_id</th>
                    <th>used_translation</th>
                    <th>skip_reason</th>
                    <th>fetched_at</th>
                    <th class="alignRight">actions</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-if="loadingRunItems">
                    <tr v-for="index in 4" :key="`items-skeleton-${index}`">
                      <td colspan="9">
                        <div class="skeletonRow"></div>
                      </td>
                    </tr>
                  </template>
                  <tr v-else-if="runItems.length === 0">
                    <td colspan="9" class="emptyCell">No items found for this run.</td>
                  </tr>
                  <tr v-else v-for="item in runItems" :key="item.id || item.stable_key">
                    <td>
                      <code :title="item.stable_key">{{ formatStableKey(item.stable_key) }}</code>
                    </td>
                    <td>
                      <div class="itemStatusCell">
                        <span :class="itemStatusClass(item.publish_status)">
                          {{ item.publish_status || 'unknown' }}
                        </span>
                        <span v-if="isManualPublishedItem(item)" class="manualBadge">MANUAL</span>
                      </div>
                    </td>
                    <td>
                      <span :class="itemStatusClass(item.translation_status)" :title="item.translation_error || ''">
                        {{ item.translation_status || 'unknown' }}
                      </span>
                    </td>
                    <td>
                      <span :class="translationProviderClass(item.translation_provider)" :title="item.translation_error || ''">
                        {{ translationProviderLabel(item.translation_provider) }}
                      </span>
                    </td>
                    <td>
                      <router-link
                        v-if="item.post_id"
                        :to="{ name: 'post-detail', params: { id: item.post_id } }"
                        class="itemLink"
                      >
                        #{{ item.post_id }}
                      </router-link>
                      <span v-else>-</span>
                    </td>
                    <td>{{ formatBool(item.used_translation) }}</td>
                    <td>{{ item.skip_reason || '-' }}</td>
                    <td>{{ formatDateTime(item.fetched_at) }}</td>
                    <td class="alignRight">
                      <div class="inlineActions inlineActions--end">
                        <button type="button" class="ghostBtn" @click="openItemPreview(item)">Preview</button>
                        <button
                          type="button"
                          class="runBtn"
                          :disabled="!canPublishItem(item) || store.isItemPublishing(item.id) || store.isItemDeleting(item.id)"
                          @click="publishItem(item)"
                        >
                          {{ store.isItemPublishing(item.id) ? 'Publishing...' : 'Publish' }}
                        </button>
                        <button
                          type="button"
                          class="dangerBtn"
                          :disabled="!canDeleteItemPost(item) || store.isItemDeleting(item.id) || store.isItemPublishing(item.id)"
                          @click="deleteItemPost(item)"
                        >
                          {{ store.isItemDeleting(item.id) ? 'Deleting...' : 'Delete post' }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <footer v-if="runItemsMeta" class="pagination pagination--inner">
              <div class="paginationInfo">
                Page {{ runItemsMeta.current_page }} / {{ runItemsMeta.last_page }} (total {{ runItemsMeta.total }})
              </div>
              <div class="paginationActions">
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="!canPrevItemsPage || loadingRunItems"
                  @click="goToItemsPage((runItemsMeta.current_page || 1) - 1)"
                >
                  Prev
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="!canNextItemsPage || loadingRunItems"
                  @click="goToItemsPage((runItemsMeta.current_page || 1) + 1)"
                >
                  Next
                </button>
              </div>
            </footer>
          </div>
        </article>
      </div>

      <div v-if="selectedPreviewItem" class="modalBackdrop modalBackdrop--inner" @click.self="closeItemPreview">
        <article class="modalCard modalCard--preview" role="dialog" aria-modal="true" aria-label="Bot item preview">
          <header class="modalHeader">
            <h3>Item preview</h3>
            <button type="button" class="ghostBtn" @click="closeItemPreview">Close</button>
          </header>

          <dl class="detailGrid detailGrid--single">
            <div>
              <dt>source link</dt>
              <dd>
                <a
                  v-if="selectedPreviewItem.url"
                  class="itemLink"
                  :href="selectedPreviewItem.url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ selectedPreviewItem.url }}
                </a>
                <span v-else>-</span>
              </dd>
            </div>
          </dl>

          <div class="detailBlock">
            <h4>status</h4>
            <div class="inlineActions">
              <span :class="itemStatusClass(selectedPreviewItem.publish_status)">
                publish: {{ selectedPreviewItem.publish_status || 'unknown' }}
              </span>
              <span :class="itemStatusClass(selectedPreviewItem.translation_status)">
                translation: {{ selectedPreviewItem.translation_status || 'unknown' }}
              </span>
            </div>
          </div>

          <div class="detailBlock">
            <h4>translation preview</h4>
            <p>{{ selectedPreviewItem.title || '-' }}</p>
            <p>{{ selectedPreviewItem.content || '-' }}</p>
          </div>

          <div class="detailBlock">
            <h4>originál</h4>
            <p>{{ selectedPreviewItem.title_original || '-' }}</p>
            <p>{{ selectedPreviewItem.content_original || '-' }}</p>
          </div>

          <div class="detailBlock">
            <h4>preklad</h4>
            <p>{{ selectedPreviewItem.title_translated || '-' }}</p>
            <p>{{ selectedPreviewItem.content_translated || '-' }}</p>
          </div>
        </article>
      </div>
    </teleport>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 14px;
  background: rgb(var(--color-bg-rgb) / 0.65);
}

.sectionHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.sectionTitle {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
}

.sectionSubtitle {
  margin: 4px 0 0;
  font-size: 0.82rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.tableWrap {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
  overflow: auto;
}

.table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
}

.table th,
.table td {
  text-align: left;
  padding: 10px 12px;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.09);
  vertical-align: middle;
  font-size: 0.9rem;
}

.table th {
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-surface-rgb) / 0.05);
}

.alignRight {
  text-align: right;
}

.emptyCell {
  text-align: center;
  padding: 20px 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.skeletonRow {
  height: 14px;
  border-radius: 999px;
  background: linear-gradient(
    90deg,
    rgb(var(--color-surface-rgb) / 0.1) 25%,
    rgb(var(--color-surface-rgb) / 0.22) 50%,
    rgb(var(--color-surface-rgb) / 0.1) 75%
  );
  background-size: 220% 100%;
  animation: pulse 1.3s linear infinite;
}

.runBtn,
.ghostBtn,
.dangerBtn {
  border-radius: 9px;
  padding: 7px 11px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
}

.runBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.52);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: rgb(var(--color-surface-rgb) / 1);
}

.ghostBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.dangerBtn {
  border: 1px solid rgb(248 113 113 / 0.58);
  background: rgb(248 113 113 / 0.16);
  color: rgb(254 202 202);
}

.runBtn:disabled,
.ghostBtn:disabled,
.dangerBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.quickRunGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.quickRunBtn {
  display: grid;
  justify-items: start;
  gap: 4px;
  text-align: left;
  min-height: 62px;
}

.quickRunHint {
  font-size: 0.72rem;
  font-weight: 600;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.inlineActions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.inlineActions--end {
  justify-content: flex-end;
}

.inlineField {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.inlineField input {
  width: 68px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.55);
  color: rgb(var(--color-surface-rgb) / 0.96);
  padding: 6px 8px;
}

.runModeCell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.modeBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  padding: 2px 7px;
  font-size: 0.68rem;
  letter-spacing: 0.04em;
  font-weight: 700;
}

.modeBadge--dry {
  border-color: rgb(245 158 11 / 0.56);
  color: rgb(254 243 199);
}

.modeBadge--auto {
  border-color: rgb(34 197 94 / 0.5);
  color: rgb(187 247 208);
}

.modeHint {
  font-size: 0.72rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.statusBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 0.74rem;
  text-transform: uppercase;
  font-weight: 700;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.statusBadge--success {
  border-color: rgb(34 197 94 / 0.5);
  background: rgb(34 197 94 / 0.2);
  color: rgb(187 247 208);
}

.statusBadge--partial {
  border-color: rgb(245 158 11 / 0.58);
  background: rgb(245 158 11 / 0.2);
  color: rgb(254 243 199);
}

.statusBadge--failed {
  border-color: rgb(244 63 94 / 0.56);
  background: rgb(244 63 94 / 0.2);
  color: rgb(254 205 211);
}

.statusBadge--muted {
  opacity: 0.72;
}

.itemStatusCell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.sourceTypeCell {
  display: grid;
  gap: 4px;
}

.sourceHint {
  font-size: 0.7rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.manualBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1px solid rgb(148 163 184 / 0.55);
  padding: 2px 7px;
  font-size: 0.68rem;
  letter-spacing: 0.04em;
  font-weight: 700;
  color: rgb(226 232 240);
}

.filters {
  display: grid;
  grid-template-columns: repeat(6, minmax(140px, 1fr)) auto;
  gap: 10px;
  margin-bottom: 12px;
}

.filterField {
  display: grid;
  gap: 6px;
  font-size: 0.76rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.filterField span {
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.filterField input,
.filterField select {
  border-radius: 9px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: rgb(var(--color-surface-rgb) / 0.96);
  padding: 8px 10px;
  min-height: 36px;
}

.filterField textarea {
  border-radius: 9px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: rgb(var(--color-surface-rgb) / 0.96);
  padding: 8px 10px;
  resize: vertical;
}

.translationTools {
  display: grid;
  gap: 10px;
}

.translationResult p {
  margin: 8px 0 0;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.17);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.65);
  padding: 10px;
  font-size: 0.82rem;
  white-space: pre-wrap;
}

.translationResult .translationSummary {
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.providerBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  padding: 2px 7px;
  font-size: 0.7rem;
  font-weight: 700;
}

.providerBadge--lt {
  border-color: rgb(16 185 129 / 0.58);
  background: rgb(16 185 129 / 0.2);
  color: rgb(167 243 208);
}

.providerBadge--ollama {
  border-color: rgb(14 165 233 / 0.58);
  background: rgb(14 165 233 / 0.2);
  color: rgb(186 230 253);
}

.providerBadge--mixed {
  border-color: rgb(245 158 11 / 0.58);
  background: rgb(245 158 11 / 0.2);
  color: rgb(254 243 199);
}

.providerBadge--muted {
  opacity: 0.7;
}

.filterActions {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.pagination {
  margin-top: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.paginationInfo {
  font-size: 0.86rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.paginationActions {
  display: flex;
  gap: 8px;
}

.modalBackdrop {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: grid;
  place-items: center;
  background: rgb(6 11 20 / 0.7);
  padding: 16px;
}

.modalBackdrop--inner {
  z-index: 1400;
}

.modalCard {
  width: min(760px, 100%);
  max-height: 88vh;
  overflow: auto;
  border-radius: 14px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 14px;
  box-shadow: 0 24px 48px rgb(0 0 0 / 0.42);
}

.modalCard--preview {
  width: min(680px, 100%);
}

.modalHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.modalHeader h3 {
  margin: 0;
  font-size: 1.02rem;
}

.detailGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.detailGrid--single {
  grid-template-columns: 1fr;
}

.detailGrid dt {
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.detailGrid dd {
  margin: 5px 0 0;
  font-size: 0.9rem;
}

.detailBlock {
  margin-top: 12px;
}

.detailBlockHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.detailBlock h4 {
  margin: 0 0 6px;
  font-size: 0.86rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detailBlock pre,
.detailBlock p {
  margin: 0;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.17);
  background: rgb(var(--color-bg-rgb) / 0.65);
  border-radius: 8px;
  padding: 10px;
  font-size: 0.82rem;
  white-space: pre-wrap;
  word-break: break-word;
}

.table--compact {
  min-width: 680px;
}

.itemLink {
  color: rgb(var(--color-primary-rgb) / 0.95);
  text-decoration: none;
}

.itemLink:hover {
  text-decoration: underline;
}

.pagination--inner {
  margin-top: 10px;
}

@keyframes pulse {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}

@media (max-width: 980px) {
  .filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filterActions {
    grid-column: span 2;
  }
}

@media (max-width: 680px) {
  .quickRunGrid {
    grid-template-columns: 1fr;
  }

  .detailGrid {
    grid-template-columns: 1fr;
  }

  .sectionHeader {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
