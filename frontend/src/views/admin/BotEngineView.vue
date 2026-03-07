<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import { useBotEngineStore } from '@/stores/botEngine'
import { deleteAllBotPosts } from '@/services/api/admin/bots'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { BOT_FAILURE_REASONS, BOT_FAILURE_REASON_MESSAGES } from '@/constants/botFailureReasons'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
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
const { confirm } = useConfirm()
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
  savingTranslationOutage,
} = storeToRefs(store)
const selectedRun = ref(null)
const selectedPreviewItem = ref(null)
const publishAllLimit = ref(DEFAULT_PUBLISH_ALL_LIMIT)
const retryTranslationLimit = ref(10)
const translationTestText = ref('NASA studies the Sun and planets in our Solar System.')
const translationTestProvider = ref('auto')
const translationTestModel = ref('')
const translationTestTemperature = ref('')
const translationTestResult = ref(null)
const translationOutageProvider = ref('none')
const aiPanelError = ref('')
const aiPanelLastRun = ref(null)
const aiPanelNotice = ref('')

const filterForm = ref({
  sourceKey: '',
  bot_identity: '',
  status: '',
  date_from: '',
  date_to: '',
  per_page: 20,
})

function normalizeBotIdentity(value) {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
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

const pageTitle = computed(() => 'Boty')
const pageSubtitle = computed(() => '')

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

const translationHealthState = computed(() => {
  if (!translationHealth.value) {
    return {
      label: 'Neznámy',
      className: 'statusBadge statusBadge--muted',
    }
  }

  if (translationHealth.value.degraded) {
    return {
      label: 'Obmedzený',
      className: 'statusBadge statusBadge--partial',
    }
  }

  if (translationHealth.value.result?.ok) {
    return {
      label: 'Aktívny',
      className: 'statusBadge statusBadge--success',
    }
  }

  return {
    label: 'Nedostupný',
    className: 'statusBadge statusBadge--failed',
  }
})

const quickRunBusyIdentity = ref('')
const translationHealthPollTimer = ref(null)

const translationQueue = computed(() => {
  const queue = translationHealth.value?.translation_queue
  return {
    done: Number(queue?.done || 0),
    skipped: Number(queue?.skipped || 0),
    failed: Number(queue?.failed || 0),
    pending: Number(queue?.pending || 0),
    processed: Number(queue?.processed || 0),
    total: Number(queue?.total || 0),
    progressPercent: Math.max(0, Math.min(100, Number(queue?.progress_percent || 0))),
  }
})

const isTranslationQueueActive = computed(() => translationQueue.value.pending > 0)
const aiPanelStatus = computed(() => {
  if (aiPanelError.value) return 'error'
  if (aiPanelLastRun.value?.status) return String(aiPanelLastRun.value.status)
  if (translationHealth.value?.degraded) return 'fallback'
  if (translationHealth.value?.result?.ok) return 'success'
  if (translationHealth.value?.result?.ok === false) return 'error'
  return 'idle'
})
const aiPanelRunHint = computed(() => (aiPanelLastRun.value?.updated_at ? 'Naposledy: tento beh' : 'Naposledy: -'))

function toErrorMessage(error, fallbackMessage) {
  const status = Number(error?.response?.status || 0)
  const code = String(error?.code || '')
    .trim()
    .toUpperCase()
  const messageText = String(error?.message || '')
    .trim()
    .toLowerCase()
  const retryAfter = Number(error?.response?.data?.retry_after || 0)
  const failureReason = String(
    error?.response?.data?.failure_reason || error?.response?.data?.meta?.failure_reason || '',
  )
    .trim()
    .toLowerCase()
  const baseMessage =
    error?.response?.data?.message || error?.userMessage || error?.message || fallbackMessage
  const isTimeoutOrNetwork =
    code === 'ECONNABORTED' || code === 'ERR_NETWORK' || messageText.includes('timeout')

  if (isTimeoutOrNetwork) {
    return 'Run trva dlhsie. Skus to o chvilu, alebo otvor detail runu.'
  }

  if (
    [
      BOT_FAILURE_REASONS.RATE_LIMITED,
      BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED,
      BOT_FAILURE_REASONS.NEEDS_API_KEY,
    ].includes(failureReason)
  ) {
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
    return `${baseMessage} Skús znova o ${retryAfter} s.`
  }

  return baseMessage
}

function toStatNumber(value) {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

function statsSummary(stats) {
  const source = stats && typeof stats === 'object' ? stats : {}

  return [
    `načítané ${toStatNumber(source.fetched_count)}`,
    `nové ${toStatNumber(source.new_count)}`,
    `duplikáty ${toStatNumber(source.dupes_count)}`,
    `publikované ${toStatNumber(source.published_count)}`,
    `preskočené ${toStatNumber(source.skipped_count)}`,
    `chyby ${toStatNumber(source.failed_count)}`,
  ].join(' | ')
}

function formatDateTime(value) {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleString('sk-SK', {
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
  if (value === true) return 'áno'
  if (value === false) return 'nie'
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
  const normalized = String(provider || '')
    .trim()
    .toLowerCase()
  if (!normalized) return '-'
  if (normalized === 'libretranslate') return 'LibreTranslate'
  if (normalized === 'ollama') return 'Ollama'
  if (normalized === 'ollama_postedit') return 'Ollama post-edit'
  if (normalized === 'mixed') return 'Mix'
  return normalized
}

function translationProviderClass(provider) {
  const normalized = String(provider || '')
    .trim()
    .toLowerCase()
  if (normalized === 'libretranslate') return 'providerBadge providerBadge--lt'
  if (normalized === 'ollama') return 'providerBadge providerBadge--ollama'
  if (normalized === 'ollama_postedit') return 'providerBadge providerBadge--ollama'
  if (normalized === 'mixed') return 'providerBadge providerBadge--mixed'
  return 'providerBadge providerBadge--muted'
}

function translationModeLabel(mode) {
  const normalized = String(mode || '')
    .trim()
    .toLowerCase()
  if (normalized === 'lt_ollama_postedit') return 'LT + Ollama úprava'
  if (normalized === 'ollama_direct') return 'Ollama priamo'
  if (normalized === 'lt_only') return 'Len LT'
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
  return String(value || '')
    .trim()
    .toLowerCase() === 'dry'
    ? 'dry'
    : 'auto'
}

function runModeLabel(run) {
  return normalizeRunMode(run?.meta?.mode) === 'dry' ? 'TEST' : 'AUTO'
}

function runModeClass(run) {
  return runModeLabel(run) === 'TEST' ? 'modeBadge modeBadge--dry' : 'modeBadge modeBadge--auto'
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
    const normalized = String(candidate || '')
      .trim()
      .toLowerCase()
    if (normalized !== '') {
      return normalized
    }
  }

  return ''
}

function requiresPublishConfirm(item) {
  return resolveItemSourceKey(item) === 'wiki_onthisday_astronomy'
}

async function confirmPublishToAstroFeed() {
  return Boolean(
    await confirm({
      title: 'Publikovat do AstroFeedu',
      message: 'Naozaj publikovat tuto polozku do AstroFeedu?',
      confirmText: 'Publikovat',
      cancelText: 'Zrusit',
    }),
  )
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

function sourceTypeLabel(sourceType) {
  const normalized = String(sourceType || '')
    .trim()
    .toLowerCase()
  if (!normalized) {
    return '-'
  }

  if (normalized === 'rss') {
    return 'RSS'
  }

  if (normalized === 'api') {
    return 'API'
  }

  return normalized.toUpperCase()
}

function sourceStateLabel(isEnabled) {
  return isEnabled ? 'Aktívny' : 'Vypnutý'
}

function sourceCountLabel(count) {
  const normalized = Number(count) || 0

  if (normalized === 1) {
    return '1 zdroj'
  }

  if (normalized >= 2 && normalized <= 4) {
    return `${normalized} zdroje`
  }

  return `${normalized} zdrojov`
}

function itemStatusLabel(status) {
  const normalized = String(status || '')
    .trim()
    .toLowerCase()

  if (normalized === 'published') return 'Publikované'
  if (normalized === 'done' || normalized === 'success') return 'Hotovo'
  if (normalized === 'pending') return 'Čaká'
  if (normalized === 'skipped') return 'Preskočené'
  if (normalized === 'failed') return 'Chyba'

  return normalized || 'Neznáme'
}

function runStatCount(run, key) {
  return toStatNumber(run?.stats?.[key])
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

function normalizeOutageProvider(value) {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  return ['none', 'ollama', 'libretranslate'].includes(normalized) ? normalized : 'none'
}

async function loadTranslationHealth() {
  try {
    const health = await store.fetchTranslationHealth()
    translationOutageProvider.value = normalizeOutageProvider(health?.simulate_outage_provider)
  } catch (error) {
    toast.error(toErrorMessage(error, 'Nepodarilo sa načítať stav prekladov.'))
  }
}

async function initialize() {
  await Promise.all([loadSources(), loadRuns(), loadTranslationHealth()])
}

function startTranslationHealthPolling() {
  stopTranslationHealthPolling()
  translationHealthPollTimer.value = setInterval(() => {
    loadTranslationHealth()
  }, 5000)
}

function stopTranslationHealthPolling() {
  if (translationHealthPollTimer.value) {
    clearInterval(translationHealthPollTimer.value)
    translationHealthPollTimer.value = null
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

async function executeQuickRun(identity) {
  const normalizedIdentity = normalizeBotIdentity(identity)
  if (normalizedIdentity === '') {
    return null
  }

  const enabledSources = Array.isArray(enabledSourcesByIdentity.value[normalizedIdentity])
    ? enabledSourcesByIdentity.value[normalizedIdentity]
    : []

  if (enabledSources.length === 0) {
    return {
      identity: normalizedIdentity,
      processedCount: 0,
      successCount: 0,
      partialCount: 0,
      failedCount: 0,
      lastErrorMessage: '',
      summary: `${botIdentityLabel(normalizedIdentity)}: 0 zdrojov.`,
    }
  }

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

  const processedCount = successCount + partialCount + failedCount
  const summary = `${botIdentityLabel(normalizedIdentity)}: ${processedCount} ${processedCount === 1 ? 'zdroj' : 'zdrojov'}, OK ${successCount}, čiastočne ${partialCount}, chyby ${failedCount}.`

  return {
    identity: normalizedIdentity,
    processedCount,
    successCount,
    partialCount,
    failedCount,
    lastErrorMessage,
    summary,
  }
}

async function quickRunIdentity(identity) {
  const normalizedIdentity = normalizeBotIdentity(identity)
  if (normalizedIdentity === '') {
    return
  }

  quickRunBusyIdentity.value = normalizedIdentity
  const result = await executeQuickRun(normalizedIdentity)
  quickRunBusyIdentity.value = ''

  await Promise.all([loadSources(), loadRuns()])

  if (!result) {
    return
  }

  if (result.failedCount > 0 && result.lastErrorMessage !== '') {
    toast.error(`${result.summary} ${result.lastErrorMessage}`)
    return
  }

  if (result.failedCount > 0) {
    toast.error(result.summary)
    return
  }

  toast.success(result.summary)
}

async function quickRunAll() {
  if (!hasEnabledSources.value || quickRunBusyIdentity.value !== '') {
    return
  }

  quickRunBusyIdentity.value = 'all'

  const results = []
  for (const identity of VALID_BOT_IDENTITIES) {
    const result = await executeQuickRun(identity)
    if (result && result.processedCount > 0) {
      results.push(result)
    }
  }

  quickRunBusyIdentity.value = ''
  await Promise.all([loadSources(), loadRuns()])

  if (results.length === 0) {
    return
  }

  let lastErrorMessage = ''
  let hasFailure = false

  for (const result of results) {
    if (result.failedCount > 0) {
      hasFailure = true
      if (result.lastErrorMessage !== '') {
        lastErrorMessage = result.lastErrorMessage
      }
    }
  }

  const summary = results.map((result) => result.summary).join(' | ')
  if (hasFailure && lastErrorMessage !== '') {
    toast.error(`Spustenie dokončené. ${summary} ${lastErrorMessage}`)
    return
  }

  if (hasFailure) {
    toast.error(`Spustenie dokončené. ${summary}`)
    return
  }

  toast.success(`Spustenie dokončené. ${summary}`)
}

async function openRunDetail(run) {
  selectedRun.value = run
  publishAllLimit.value = resolvePublishAllLimitDefault(run)

  try {
    await store.fetchItemsForRun(run?.id, { page: 1, per_page: 20 })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Nepodarilo sa načítať položky behu.'))
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

async function confirmDeletePublishedPost() {
  return Boolean(
    await confirm({
      title: 'Vymazat publikovany prispevok',
      message: 'Naozaj vymazat publikovany bot prispevok z feedu?',
      confirmText: 'Vymazat',
      cancelText: 'Zrusit',
      variant: 'danger',
    }),
  )
}

async function confirmBackfillTranslation() {
  return Boolean(
    await confirm({
      title: 'Doplnit preklad',
      message: 'Doplnit preklad aj do uz publikovanych prispevkov?',
      confirmText: 'Doplnit',
      cancelText: 'Zrusit',
    }),
  )
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
    toast.error(toErrorMessage(error, 'Nepodarilo sa načítať položky behu.'))
  }
}

async function publishItem(item) {
  if (!item?.id || !canPublishItem(item)) return
  if (requiresPublishConfirm(item) && !(await confirmPublishToAstroFeed())) return

  try {
    const response = await store.publishItem(item.id, { force: false })
    if (response?.already_published) {
      toast.info('Položka už je publikovaná.')
    } else {
      toast.success('Položka bola publikovaná.')
    }

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Nepodarilo sa publikovať položku.'))
  }
}

async function deleteItemPost(item) {
  if (!item?.id || !canDeleteItemPost(item)) return
  if (!(await confirmDeletePublishedPost())) return

  try {
    await store.deleteItemPost(item.id)
    toast.success('Publikovaný príspevok bol vymazaný.')

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Nepodarilo sa vymazať publikovaný príspevok.'))
  }
}

async function confirmDeleteAllBotPosts() {
  return Boolean(
    await confirm({
      title: 'Hromadne mazanie',
      message: 'Naozaj vymazat vsetky publikovane bot prispevky podla aktualneho filtra?',
      confirmText: 'Vymazat',
      cancelText: 'Zrusit',
      variant: 'danger',
    }),
  )
}

async function deleteAllBotPostsForFilter() {
  if (!(await confirmDeleteAllBotPosts())) {
    return
  }

  try {
    const deleteAllPostsAction =
      typeof store.deleteAllPosts === 'function'
        ? store.deleteAllPosts.bind(store)
        : async (params) => {
            const response = await deleteAllBotPosts(params)
            return response?.data || null
          }

    const result = await deleteAllPostsAction({
      source_key: filterForm.value.sourceKey || '',
      bot_identity: effectiveBotIdentity.value || '',
    })
    if (!result) {
      return
    }

    toast.success(
      `Vymazane posty: ${Number(result.deleted_posts || 0)} | bez postu: ${Number(result.missing_posts || 0)} | chyby: ${Number(result.failed_items || 0)}.`,
    )

    await Promise.all([loadRuns(), loadTranslationHealth()])

    if (selectedRun.value?.id) {
      await store.fetchItemsForRun(selectedRun.value.id, {
        page: runItemsMeta.value?.current_page || 1,
        per_page: runItemsMeta.value?.per_page || 20,
      })
    }
  } catch (error) {
    toast.error(toErrorMessage(error, 'Hromadné mazanie zlyhalo.'))
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
      `Publikované: ${publishedCount}. Preskočené: ${skippedCount}. Chyby: ${failedCount}.`,
    )

    await store.fetchItemsForRun(runId, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Nepodarilo sa publikovať položky z behu.'))
  }
}

async function testTranslation() {
  aiPanelError.value = ''
  aiPanelNotice.value = ''

  try {
    const payload = {
      text: String(translationTestText.value || '').trim(),
    }
    const provider = String(translationTestProvider.value || '')
      .trim()
      .toLowerCase()
    if (provider !== '' && provider !== 'auto') {
      payload.provider = provider
    }

    const model = String(translationTestModel.value || '').trim()
    if (model !== '') {
      payload.model = model
    }

    const temperature = Number(translationTestTemperature.value)
    if (Number.isFinite(temperature) && temperature >= 0) {
      payload.temperature = temperature
    }

    const result = await store.testTranslation(payload)
    if (!result) {
      return
    }

    translationTestResult.value = result
    aiPanelLastRun.value = {
      status: result?.meta?.fallback_used ? 'fallback' : 'success',
      latency_ms: Number(result?.latency_ms || 0),
      status_code: Number(result?.status_code || 0) || null,
      updated_at: new Date().toISOString(),
    }
    aiPanelNotice.value = 'Test dokončený.'
    const providerLabel = translationProviderLabel(result.provider)
    toast.success(
      `Test prekladu je v poriadku (${providerLabel}, ${Number(result.latency_ms || 0)} ms).`,
    )
  } catch (error) {
    const safeError = toErrorMessage(error, 'Test prekladu zlyhal.')
    translationTestResult.value = null
    aiPanelNotice.value = ''
    aiPanelError.value = safeError
    aiPanelLastRun.value = {
      status: 'error',
      latency_ms: null,
      status_code: Number(error?.response?.status || 0) || null,
      updated_at: new Date().toISOString(),
    }
    toast.error(safeError)
  }
}

async function saveTranslationOutageSimulation() {
  const provider = normalizeOutageProvider(translationOutageProvider.value)

  try {
    const response = await store.setTranslationOutageProvider(provider)
    translationOutageProvider.value = normalizeOutageProvider(response?.new_value || provider)
    await loadTranslationHealth()
    toast.success(`Simulácia výpadku uložená (${translationOutageProvider.value}).`)
  } catch (error) {
    toast.error(toErrorMessage(error, 'Nepodarilo sa uložiť simuláciu výpadku.'))
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
      `Retry hotové: ${Number(result.done_count || 0)} OK, ${Number(result.skipped_count || 0)} preskočené, ${Number(result.failed_count || 0)} chyby.`,
    )

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Opakovanie prekladu zlyhalo.'))
  }
}

async function backfillTranslateForRun() {
  const sourceKey = String(selectedRun.value?.source_key || '').trim()
  if (!sourceKey) {
    return
  }

  if (!(await confirmBackfillTranslation())) {
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
      `Doplnené: ${Number(result.updated_posts || 0)}. Preskočené: ${Number(result.skipped || 0)}. Chyby: ${Number(result.failed || 0)}.`,
    )

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Doplnenie prekladu zlyhalo.'))
  }
}

function statusClass(status) {
  const normalizedReason = String(
    status?.failure_reason || status?.meta?.failure_reason || '',
  ).toLowerCase()
  if (
    [
      BOT_FAILURE_REASONS.RATE_LIMITED,
      BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED,
      BOT_FAILURE_REASONS.NEEDS_API_KEY,
    ].includes(normalizedReason)
  ) {
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
  if (
    reason === BOT_FAILURE_REASONS.RATE_LIMITED ||
    reason === BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED
  ) {
    return 'Limit'
  }
  if (reason === BOT_FAILURE_REASONS.NEEDS_API_KEY) {
    return 'Chýba API kľúč'
  }

  const normalized = String(run?.status || '')
    .trim()
    .toLowerCase()
  if (normalized === 'success') return 'Hotovo'
  if (normalized === 'partial') return 'Čiastočne'
  if (normalized === 'failed') return 'Chyba'
  if (normalized === 'skipped') return 'Preskočené'
  return 'Neznáme'
}

function runStatusHint(run) {
  return run?.ui_message || run?.meta?.ui_message || run?.error_text || ''
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
  startTranslationHealthPolling()
})

onBeforeUnmount(() => {
  stopTranslationHealthPolling()
})
</script>

<template>
  <component
    :is="props.embedded ? 'section' : AdminPageShell"
    v-bind="props.embedded ? {} : { title: pageTitle, subtitle: pageSubtitle }"
    class="botEngineShell"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">{{ pageTitle }}</h2>
        <p v-if="pageSubtitle" class="embeddedSubtitle">{{ pageSubtitle }}</p>
      </div>
      <div class="embeddedHeaderActions">
        <RouterLink class="runBtn headerRunBtn headerRunBtn--ghost" :to="{ name: 'admin.bots.activity' }">
          Activity log
        </RouterLink>
        <button
          type="button"
          class="runBtn headerRunBtn"
          data-testid="quick-run-all"
          :disabled="quickRunBusyIdentity !== '' || !hasEnabledSources"
          @click="quickRunAll"
        >
          {{ quickRunBusyIdentity === 'all' ? 'Spúšťam všetko...' : 'Spustiť všetko' }}
        </button>
      </div>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <RouterLink class="runBtn headerRunBtn headerRunBtn--ghost" :to="{ name: 'admin.bots.activity' }">
        Activity log
      </RouterLink>
      <button
        type="button"
        class="runBtn headerRunBtn"
        data-testid="quick-run-all"
        :disabled="quickRunBusyIdentity !== '' || !hasEnabledSources"
        @click="quickRunAll"
      >
        {{ quickRunBusyIdentity === 'all' ? 'Spúšťam všetko...' : 'Spustiť všetko' }}
      </button>
    </template>

    <section class="quickStrip" aria-label="Rýchle spustenie">
      <button
        type="button"
        class="quickPill"
        :class="{ 'quickPill--active': effectiveBotIdentity === 'kozmo' }"
        data-testid="quick-run-kozmo"
        :title="sourceCountLabel(enabledSourcesByIdentity.kozmo.length)"
        :disabled="quickRunBusyIdentity !== '' || enabledSourcesByIdentity.kozmo.length === 0"
        @click="quickRunIdentity('kozmo')"
      >
        <span class="quickPill__label">{{
          quickRunBusyIdentity === 'kozmo' ? 'Spúšťam KozmoBot' : 'KozmoBot'
        }}</span>
        <span class="countBadge">{{ enabledSourcesByIdentity.kozmo.length }}</span>
      </button>

      <button
        type="button"
        class="quickPill"
        :class="{ 'quickPill--active': effectiveBotIdentity === 'stela' }"
        data-testid="quick-run-stela"
        :title="sourceCountLabel(enabledSourcesByIdentity.stela.length)"
        :disabled="quickRunBusyIdentity !== '' || enabledSourcesByIdentity.stela.length === 0"
        @click="quickRunIdentity('stela')"
      >
        <span class="quickPill__label">{{
          quickRunBusyIdentity === 'stela' ? 'Spúšťam StellarBot' : 'StellarBot'
        }}</span>
        <span class="countBadge">{{ enabledSourcesByIdentity.stela.length }}</span>
      </button>
    </section>

    <AdminAiActionPanel
      title="AI pomocník"
      description="Rýchly test prekladu bez interných detailov."
      action-label="Otestovať preklad"
      :enabled="true"
      :status="aiPanelStatus"
      :latency-ms="aiPanelLastRun?.latency_ms ?? null"
      :last-run-at="aiPanelLastRun?.updated_at ?? null"
      :raw-status-code="aiPanelLastRun?.status_code ?? null"
      :is-loading="store.testingTranslation"
      :error-message="aiPanelError"
      @run="testTranslation"
    >
      <p class="translationMetaText">{{ aiPanelRunHint }}</p>
      <p v-if="aiPanelNotice" class="translationMetaText">{{ aiPanelNotice }}</p>
      <span v-if="aiPanelStatus === 'fallback'" class="statusBadge statusBadge--partial">Použitý fallback</span>
      <p v-if="translationTestResult" class="translationMetaText">
        Posledný výstup: {{ translationTestResult.translated_text || '-' }}
      </p>
      <template #advanced>
        <div class="advancedActions">
          <label class="filterField filterField--compact">
            <span>Provider</span>
            <select v-model="translationTestProvider">
              <option value="auto">auto</option>
              <option value="libretranslate">libretranslate</option>
              <option value="ollama">ollama</option>
            </select>
          </label>
          <label class="filterField filterField--compact">
            <span>Model</span>
            <input v-model.trim="translationTestModel" type="text" placeholder="predvoleny" />
          </label>
          <label class="filterField filterField--compact">
            <span>Teplota</span>
            <input v-model.number="translationTestTemperature" type="number" step="0.1" min="0" max="2" />
          </label>
        </div>
        <label class="filterField">
          <span>Testovací text</span>
          <textarea
            v-model="translationTestText"
            rows="3"
            maxlength="5000"
            placeholder="Krátky anglický text na test prekladu."
          />
        </label>
        <div v-if="translationTestResult" class="translationResult">
          <div class="inlineActions">
            <span :class="translationProviderClass(translationTestResult.provider)">
              {{ translationProviderLabel(translationTestResult.provider) }}
            </span>
            <span class="modeHint">{{ Number(translationTestResult.latency_ms || 0) }} ms</span>
            <span class="modeHint">{{ translationModeLabel(translationTestResult.mode) }}</span>
          </div>
          <p class="translationMetaText">
            Reťazec:
            {{
              Array.isArray(translationTestResult.provider_chain) &&
              translationTestResult.provider_chain.length > 0
                ? translationTestResult.provider_chain.join(' -> ')
                : '-'
            }}
            · Kvalita:
            {{
              Array.isArray(translationTestResult.quality_flags) &&
              translationTestResult.quality_flags.length > 0
                ? translationTestResult.quality_flags.join(', ')
                : 'OK'
            }}
          </p>
          <p>{{ translationTestResult.translated_text || '-' }}</p>
        </div>
      </template>
    </AdminAiActionPanel>

    <section class="panel">
      <header class="panelHeader">
        <p class="sectionLabel">Zdroje</p>
        <button
          type="button"
          class="ghostBtn ghostBtn--compact"
          :disabled="loadingSources"
          @click="loadSources"
        >
          Obnoviť
        </button>
      </header>

      <div class="tableWrap">
        <table class="table table--sources">
          <thead>
            <tr>
              <th>Zdroj</th>
              <th>Bot</th>
              <th>Typ</th>
              <th>Stav</th>
              <th>Posledný beh</th>
              <th class="alignRight">Akcia</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loadingSources">
              <tr v-for="index in 5" :key="`sources-skeleton-${index}`">
                <td colspan="6">
                  <div class="skeletonRow"></div>
                </td>
              </tr>
            </template>
            <tr v-else-if="filteredSources.length === 0">
              <td colspan="6" class="emptyCell">Žiadne zdroje.</td>
            </tr>
            <tr v-else v-for="source in filteredSources" :key="source.id || source.key">
              <td>
                <code>{{ source.key }}</code>
              </td>
              <td>{{ botIdentityLabel(normalizeBotIdentity(source.bot_identity)) || '-' }}</td>
              <td>{{ sourceTypeLabel(source.source_type) }}</td>
              <td>
                <span class="stateBadge">
                  <span
                    :class="
                      source.is_enabled
                        ? 'statusDot statusDot--success'
                        : 'statusDot statusDot--muted'
                    "
                  ></span>
                  {{ sourceStateLabel(source.is_enabled) }}
                </span>
              </td>
              <td>{{ formatDateTime(source.last_run_at) }}</td>
              <td class="alignRight">
                <button
                  type="button"
                  class="iconBtn"
                  :data-testid="`source-run-${source.key}`"
                  :disabled="!source.is_enabled || store.isSourceRunning(source.key)"
                  :aria-label="
                    store.isSourceRunning(source.key)
                      ? `Spúšťa sa ${source.key}`
                      : `Spustiť ${source.key}`
                  "
                  :title="store.isSourceRunning(source.key) ? 'Spúšťam' : 'Spustiť'"
                  @click="runNow(source.key, 'auto')"
                >
                  <span v-if="store.isSourceRunning(source.key)" class="iconBtn__busy">...</span>
                  <svg v-else viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M5 3.5v9l7-4.5-7-4.5Z" fill="currentColor" />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <details class="panel panel--collapsible panel--translations" open>
      <summary class="collapsibleSummary">
        <p class="sectionLabel">Preklady</p>
        <div class="collapsibleMeta">
          <span class="summaryMetaValue">{{
            translationProviderLabel(translationHealth?.provider)
          }}</span>
          <span :class="translationHealthState.className" data-testid="translation-health-state">
            {{ translationHealthState.label }}
          </span>
        </div>
      </summary>

      <div class="translationTools">
        <div class="translationOverview">
          <div class="miniMetric">
            <span class="miniLabel">Poskytovateľ</span>
            <strong>{{ translationProviderLabel(translationHealth?.provider) }}</strong>
          </div>
          <div class="miniMetric">
            <span class="miniLabel">Stav</span>
            <span :class="translationHealthState.className">
              {{ translationHealthState.label }}
            </span>
          </div>
        </div>

        <div class="progressWrap progressWrap--compact">
          <div class="progressHeader">
            <span>Fronta</span>
            <strong
              >{{ translationQueue.done + translationQueue.skipped }}/{{
                translationQueue.total || 0
              }}</strong
            >
          </div>
          <div
            class="progressTrack"
            role="progressbar"
            :aria-valuenow="translationQueue.progressPercent"
            aria-valuemin="0"
            aria-valuemax="100"
          >
            <div
              class="progressFill"
              :class="{ 'progressFill--active': isTranslationQueueActive }"
              :style="{ width: `${translationQueue.progressPercent}%` }"
            ></div>
          </div>
        </div>

        <details class="advancedTools">
          <summary>Rozšírené</summary>

          <div class="advancedTools__body">
            <div class="advancedActions">
              <label class="filterField filterField--compact">
                <span>Výpadok</span>
                <select
                  v-model="translationOutageProvider"
                  data-testid="translation-outage-provider"
                >
                  <option value="none">žiadny</option>
                  <option value="ollama">ollama</option>
                  <option value="libretranslate">libretranslate</option>
                </select>
              </label>

              <button
                type="button"
                class="ghostBtn"
                data-testid="translation-outage-save"
                :disabled="savingTranslationOutage"
                @click="saveTranslationOutageSimulation"
              >
                {{ savingTranslationOutage ? 'Ukladám...' : 'Uložiť' }}
              </button>

              <button
                type="button"
                class="dangerBtn"
                :disabled="store.deletingAllPosts"
                @click="deleteAllBotPostsForFilter"
              >
                {{ store.deletingAllPosts ? 'Mažem príspevky...' : 'Vymazať bot príspevky' }}
              </button>
            </div>
          </div>
        </details>
      </div>
    </details>

    <section class="panel panel--runs">
      <header class="panelHeader">
        <p class="sectionLabel">Behy</p>
        <button
          type="button"
          class="ghostBtn ghostBtn--compact"
          :disabled="loadingRuns"
          @click="loadRuns()"
        >
          Obnoviť
        </button>
      </header>

      <form class="filters" @submit.prevent="applyRunsFilters">
        <label v-if="!hasPresetBotIdentity" class="filterField filterField--compact">
          <span>Bot</span>
          <select v-model="filterForm.bot_identity">
            <option value="">Všetky</option>
            <option value="kozmo">KozmoBot</option>
            <option value="stela">StellarBot</option>
          </select>
        </label>
        <label v-else class="filterField filterField--compact">
          <span>Bot</span>
          <input :value="botIdentityLabel(filterForm.bot_identity) || '-'" type="text" readonly />
        </label>

        <label class="filterField filterField--compact">
          <span>Zdroj</span>
          <select v-model="filterForm.sourceKey">
            <option value="">Všetky</option>
            <option v-for="sourceKey in sourceOptions" :key="sourceKey" :value="sourceKey">
              {{ sourceKey }}
            </option>
          </select>
        </label>

        <label class="filterField filterField--compact">
          <span>Stav</span>
          <select v-model="filterForm.status">
            <option value="">Všetky</option>
            <option value="success">Hotovo</option>
            <option value="partial">Čiastočne</option>
            <option value="failed">Chyba</option>
          </select>
        </label>

        <label class="filterField filterField--compact">
          <span>Od</span>
          <input v-model="filterForm.date_from" type="date" />
        </label>

        <label class="filterField filterField--compact">
          <span>Do</span>
          <input v-model="filterForm.date_to" type="date" />
        </label>

        <label class="filterField filterField--compact">
          <span>Na stranu</span>
          <select v-model.number="filterForm.per_page">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
        </label>

        <div class="filterActions">
          <button type="submit" class="runBtn" :disabled="loadingRuns">Filtrovať</button>
          <button type="button" class="ghostBtn" :disabled="loadingRuns" @click="resetRunsFilters">
            Vyčistiť
          </button>
        </div>
      </form>

      <div class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>Čas</th>
              <th>Zdroj</th>
              <th>Stav</th>
              <th>Nové</th>
              <th>Publikované</th>
              <th>Chyby</th>
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
              <td colspan="6" class="emptyCell">Žiadne behy pre zvolený filter.</td>
            </tr>
            <tr v-else v-for="run in runs" :key="run.id">
              <td>
                <div class="timeCell">
                  <span>{{ formatDateTime(run.started_at) }}</span>
                  <button type="button" class="textBtn" @click="openRunDetail(run)">Detail</button>
                </div>
              </td>
              <td>
                <code>{{ run.source_key || '-' }}</code>
              </td>
              <td>
                <div class="runStatusCell">
                  <span
                    :class="statusClass(run)"
                    :title="runStatusHint(run)"
                    data-testid="run-status-badge"
                  >
                    {{ runStatusLabel(run) }}
                  </span>
                  <span :class="runModeClass(run)" data-testid="run-mode-badge">{{
                    runModeLabel(run)
                  }}</span>
                  <span
                    v-if="runPublishLimit(run) !== null"
                    class="modeHint"
                    data-testid="run-mode-limit"
                  >
                    limit {{ runPublishLimit(run) }}
                  </span>
                </div>
              </td>
              <td>{{ runStatCount(run, 'new_count') }}</td>
              <td>{{ runStatCount(run, 'published_count') }}</td>
              <td>{{ runStatCount(run, 'failed_count') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="runsMeta" class="pagination">
        <div class="paginationInfo">
          Strana {{ runsMeta.current_page }} z {{ runsMeta.last_page }} · {{ runsMeta.total }} spolu
        </div>
        <div class="paginationActions">
          <button
            type="button"
            class="ghostBtn ghostBtn--compact"
            :disabled="!canPrevPage || loadingRuns"
            @click="goToPage((runsMeta.current_page || 1) - 1)"
          >
            Späť
          </button>
          <button
            type="button"
            class="ghostBtn ghostBtn--compact"
            :disabled="!canNextPage || loadingRuns"
            @click="goToPage((runsMeta.current_page || 1) + 1)"
          >
            Ďalej
          </button>
        </div>
      </footer>
    </section>

    <teleport to="body">
      <div v-if="selectedRun" class="modalBackdrop" @click.self="closeRunDetail">
        <article class="modalCard" role="dialog" aria-modal="true" aria-label="Detail behu bota">
          <header class="modalHeader">
            <h3>Detail behu</h3>
            <button type="button" class="ghostBtn ghostBtn--compact" @click="closeRunDetail">
              Zavrieť
            </button>
          </header>

          <dl class="detailGrid">
            <div>
              <dt>Zdroj</dt>
              <dd>
                <code>{{ selectedRun.source_key || '-' }}</code>
              </dd>
            </div>
            <div>
              <dt>Spustené</dt>
              <dd>{{ formatDateTime(selectedRun.started_at) }}</dd>
            </div>
            <div>
              <dt>Dokončené</dt>
              <dd>{{ formatDateTime(selectedRun.finished_at) }}</dd>
            </div>
            <div>
              <dt>Stav</dt>
              <dd>
                <span :class="statusClass(selectedRun)" :title="runStatusHint(selectedRun)">
                  {{ runStatusLabel(selectedRun) }}
                </span>
              </dd>
            </div>
            <div>
              <dt>Cooldown</dt>
              <dd>
                {{ formatDateTime(selectedRun.cooldown_until || selectedRun.meta?.cooldown_until) }}
              </dd>
            </div>
          </dl>

          <div class="detailBlock">
            <h4>Štatistiky</h4>
            <pre>{{ formatStatsJson(selectedRun.stats) }}</pre>
          </div>

          <div class="detailBlock">
            <h4>Chyba</h4>
            <p>{{ selectedRun.error_text || '-' }}</p>
          </div>

          <div class="detailBlock">
            <div class="detailBlockHeader">
              <h4>Položky</h4>
              <div class="inlineActions">
                <label class="inlineField">
                  <span>Limit</span>
                  <input
                    v-model.number="publishAllLimit"
                    data-testid="publish-all-limit"
                    type="number"
                    min="1"
                    max="100"
                  />
                </label>
                <button
                  type="button"
                  class="runBtn"
                  data-testid="publish-all-btn"
                  :disabled="store.isRunPublishing(selectedRun?.id)"
                  @click="publishAllForRun"
                >
                  {{
                    store.isRunPublishing(selectedRun?.id) ? 'Publikujem...' : 'Publikovať všetko'
                  }}
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="loadingRunItems"
                  @click="goToItemsPage(1)"
                >
                  Obnoviť položky
                </button>
                <details class="advancedTools advancedTools--inline">
                  <summary>Rozšírené</summary>
                  <div class="advancedTools__body">
                    <div class="advancedActions">
                      <label class="inlineField">
                        <span>Retry</span>
                        <input
                          v-model.number="retryTranslationLimit"
                          type="number"
                          min="1"
                          max="100"
                        />
                      </label>
                      <button
                        type="button"
                        class="ghostBtn"
                        data-testid="retry-translation-btn"
                        :disabled="store.isTranslationRetrying(selectedRun?.source_key)"
                        @click="retryTranslateForRun"
                      >
                        {{
                          store.isTranslationRetrying(selectedRun?.source_key)
                            ? 'Skúšam...'
                            : 'Skúsiť preklad'
                        }}
                      </button>
                      <button
                        type="button"
                        class="ghostBtn"
                        data-testid="backfill-translation-btn"
                        :disabled="store.isTranslationBackfilling(selectedRun?.source_key)"
                        @click="backfillTranslateForRun"
                      >
                        {{
                          store.isTranslationBackfilling(selectedRun?.source_key)
                            ? 'Dopĺňam...'
                            : 'Doplniť preklad'
                        }}
                      </button>
                    </div>
                  </div>
                </details>
              </div>
            </div>

            <div class="tableWrap">
              <table class="table table--compact">
                <thead>
                  <tr>
                    <th>Kľúč</th>
                    <th>Publikovanie</th>
                    <th>Preklad</th>
                    <th>Poskytovateľ</th>
                    <th>Post</th>
                    <th>Použitý</th>
                    <th>Dôvod</th>
                    <th>Čas</th>
                    <th class="alignRight">Akcie</th>
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
                    <td colspan="9" class="emptyCell">Žiadne položky v tomto behu.</td>
                  </tr>
                  <tr v-else v-for="item in runItems" :key="item.id || item.stable_key">
                    <td>
                      <code :title="item.stable_key">{{ formatStableKey(item.stable_key) }}</code>
                    </td>
                    <td>
                      <div class="itemStatusCell">
                        <span :class="itemStatusClass(item.publish_status)">
                          {{ itemStatusLabel(item.publish_status) }}
                        </span>
                        <span v-if="isManualPublishedItem(item)" class="manualBadge">MANUÁL</span>
                      </div>
                    </td>
                    <td>
                      <span
                        :class="itemStatusClass(item.translation_status)"
                        :title="item.translation_error || ''"
                      >
                        {{ itemStatusLabel(item.translation_status) }}
                      </span>
                    </td>
                    <td>
                      <span
                        :class="translationProviderClass(item.translation_provider)"
                        :title="item.translation_error || ''"
                      >
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
                        <button type="button" class="ghostBtn" @click="openItemPreview(item)">
                          Náhľad
                        </button>
                        <button
                          type="button"
                          class="runBtn"
                          data-testid="item-publish-btn"
                          :disabled="
                            !canPublishItem(item) ||
                            store.isItemPublishing(item.id) ||
                            store.isItemDeleting(item.id)
                          "
                          @click="publishItem(item)"
                        >
                          {{ store.isItemPublishing(item.id) ? 'Publikujem...' : 'Publikovať' }}
                        </button>
                        <button
                          type="button"
                          class="dangerBtn"
                          data-testid="item-delete-btn"
                          :disabled="
                            !canDeleteItemPost(item) ||
                            store.isItemDeleting(item.id) ||
                            store.isItemPublishing(item.id)
                          "
                          @click="deleteItemPost(item)"
                        >
                          {{ store.isItemDeleting(item.id) ? 'Mažem...' : 'Vymazať príspevok' }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <footer v-if="runItemsMeta" class="pagination pagination--inner">
              <div class="paginationInfo">
                Strana {{ runItemsMeta.current_page }} z {{ runItemsMeta.last_page }} ·
                {{ runItemsMeta.total }} spolu
              </div>
              <div class="paginationActions">
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="!canPrevItemsPage || loadingRunItems"
                  @click="goToItemsPage((runItemsMeta.current_page || 1) - 1)"
                >
                  Späť
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="!canNextItemsPage || loadingRunItems"
                  @click="goToItemsPage((runItemsMeta.current_page || 1) + 1)"
                >
                  Ďalej
                </button>
              </div>
            </footer>
          </div>
        </article>
      </div>

      <div
        v-if="selectedPreviewItem"
        class="modalBackdrop modalBackdrop--inner"
        @click.self="closeItemPreview"
      >
        <article
          class="modalCard modalCard--preview"
          role="dialog"
          aria-modal="true"
          aria-label="Náhľad položky bota"
        >
          <header class="modalHeader">
            <h3>Náhľad položky</h3>
            <button type="button" class="ghostBtn ghostBtn--compact" @click="closeItemPreview">
              Zavrieť
            </button>
          </header>

          <dl class="detailGrid detailGrid--single">
            <div>
              <dt>Zdrojový odkaz</dt>
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
            <h4>Stav</h4>
            <div class="inlineActions">
              <span :class="itemStatusClass(selectedPreviewItem.publish_status)">
                publikovanie: {{ itemStatusLabel(selectedPreviewItem.publish_status) }}
              </span>
              <span :class="itemStatusClass(selectedPreviewItem.translation_status)">
                preklad: {{ itemStatusLabel(selectedPreviewItem.translation_status) }}
              </span>
            </div>
          </div>

          <div class="detailBlock">
            <h4>Náhľad</h4>
            <p>{{ selectedPreviewItem.title || '-' }}</p>
            <p>{{ selectedPreviewItem.content || '-' }}</p>
          </div>

          <div class="detailBlock">
            <h4>Originál</h4>
            <p>{{ selectedPreviewItem.title_original || '-' }}</p>
            <p>{{ selectedPreviewItem.content_original || '-' }}</p>
          </div>

          <div class="detailBlock">
            <h4>Preklad</h4>
            <p>{{ selectedPreviewItem.title_translated || '-' }}</p>
            <p>{{ selectedPreviewItem.content_translated || '-' }}</p>
          </div>
        </article>
      </div>
    </teleport>
  </component>
</template>

<style scoped>
.botEngineShell {
  display: grid;
  gap: 14px;
}

.embeddedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.embeddedTitle {
  margin: 0 0 6px;
  font-size: 1.06rem;
  font-weight: 800;
}

.embeddedSubtitle {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.85rem;
}

.embeddedHeaderActions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

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
  flex-wrap: wrap;
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  padding: 7px 11px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  line-height: 1.2;
  white-space: nowrap;
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
  max-width: 100%;
}

.inlineActions--end {
  justify-content: flex-end;
  width: 100%;
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
  grid-template-columns: repeat(6, minmax(120px, 1fr)) auto;
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

.progressWrap {
  display: grid;
  gap: 6px;
}

.progressHeader {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.progressTrack {
  height: 10px;
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.12);
}

.progressFill {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, rgb(34 197 94 / 0.75), rgb(16 185 129 / 0.95));
  transition: width 0.3s ease;
}

.progressFill--active {
  box-shadow: 0 0 0 1px rgb(16 185 129 / 0.35) inset;
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
  flex-wrap: wrap;
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
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 6px;
  flex-wrap: wrap;
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
  min-width: 760px;
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
    justify-content: flex-start;
  }
}

@media (max-width: 680px) {
  .card {
    padding: 12px;
  }

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

  .sectionHeader > .ghostBtn,
  .sectionHeader > .dangerBtn,
  .sectionHeader > .runBtn {
    width: 100%;
  }

  .filters {
    grid-template-columns: 1fr;
  }

  .filterActions {
    grid-column: auto;
  }

  .inlineActions {
    display: grid;
    grid-template-columns: 1fr;
    align-items: stretch;
  }

  .inlineActions .runBtn,
  .inlineActions .ghostBtn,
  .inlineActions .dangerBtn {
    width: 100%;
  }

  .progressHeader {
    flex-direction: column;
    align-items: flex-start;
  }

  .modalCard {
    width: 100%;
    max-height: 92vh;
    padding: 12px;
  }
}

@media (max-width: 1200px) {
  .filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .filterActions {
    grid-column: span 3;
  }
}

:deep(.adminPageShell.botEngineShell) {
  padding: 18px 16px;
  background: var(--color-bg-main);
}

:deep(.adminPageShell.botEngineShell .adminPageShell__header) {
  align-items: center;
  margin-bottom: 10px;
}

:deep(.adminPageShell.botEngineShell .adminPageShell__title) {
  margin: 0;
  font-size: 1.9rem;
  letter-spacing: -0.03em;
}

:deep(.adminPageShell.botEngineShell .adminPageShell__content) {
  gap: 10px;
}

.quickStrip,
.panel {
  display: grid;
  gap: 10px;
}

.quickStrip {
  grid-template-columns: repeat(2, minmax(0, max-content));
  gap: 8px;
  padding-bottom: 6px;
}

.panel {
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  padding-top: 10px;
}

.panel--runs {
  order: 2;
}

.panel--collapsible {
  padding-top: 0;
}

.panel--translations {
  order: 3;
}

.panelHeader,
.collapsibleSummary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.collapsibleSummary {
  list-style: none;
  cursor: pointer;
  padding-top: 10px;
}

.collapsibleSummary::-webkit-details-marker {
  display: none;
}

.collapsibleMeta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.summaryMetaValue {
  font-size: 0.82rem;
  color: rgb(var(--color-surface-rgb) / 0.82);
}

.sectionLabel {
  margin: 0;
  font-size: 0.72rem;
  line-height: 1;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.8);
}

.tableWrap {
  border-color: rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.18);
}

.table {
  min-width: 680px;
}

.table--sources {
  min-width: 620px;
}

.table th,
.table td {
  padding: 9px 10px;
  border-bottom-color: rgb(var(--color-surface-rgb) / 0.08);
  font-size: 0.86rem;
}

.table th {
  font-size: 0.7rem;
  letter-spacing: 0.12em;
  background: rgb(var(--color-surface-rgb) / 0.03);
}

.table tbody tr:last-child td {
  border-bottom: none;
}

.emptyCell {
  padding: 16px 10px;
}

.runBtn,
.ghostBtn,
.dangerBtn,
.quickPill,
.iconBtn {
  min-height: 34px;
  border-radius: 999px;
  padding: 0 12px;
  line-height: 1;
}

.runBtn {
  border-color: rgb(var(--color-primary-rgb) / 0.4);
  background: rgb(var(--color-primary-rgb) / 0.18);
}

.ghostBtn {
  border-color: rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-surface-rgb) / 0.03);
}

.dangerBtn {
  border-color: rgb(248 113 113 / 0.28);
  background: rgb(248 113 113 / 0.12);
}

.ghostBtn--compact {
  min-height: 30px;
  padding: 0 10px;
}

.headerRunBtn {
  min-height: 36px;
  padding-inline: 14px;
}

.headerRunBtn--ghost {
  border-color: rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-surface-rgb) / 0.03);
}

.quickPill {
  justify-content: space-between;
  min-width: 176px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-surface-rgb) / 0.04);
  color: rgb(var(--color-surface-rgb) / 0.94);
}

.quickPill--active {
  border-color: rgb(var(--color-primary-rgb) / 0.32);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.quickPill__label {
  text-align: left;
}

.countBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 24px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.08);
  color: rgb(var(--color-surface-rgb) / 0.9);
  font-size: 0.72rem;
  font-weight: 700;
}

.iconBtn {
  width: 30px;
  min-width: 30px;
  height: 30px;
  min-height: 30px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-surface-rgb) / 0.03);
  color: rgb(var(--color-surface-rgb) / 0.92);
  padding: 0;
}

.iconBtn svg {
  width: 12px;
  height: 12px;
}

.iconBtn__busy {
  font-size: 0.8rem;
  letter-spacing: 0.08em;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
  align-items: flex-end;
}

.filterField {
  gap: 5px;
  min-width: 140px;
  font-size: 0.74rem;
}

.filterField--compact {
  min-width: 124px;
}

.filterField span {
  letter-spacing: 0.12em;
}

.filterField input,
.filterField select,
.filterField textarea {
  border-radius: 10px;
  border-color: rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-bg-rgb) / 0.28);
}

.filterField input,
.filterField select {
  min-height: 34px;
}

.filterField textarea {
  min-height: 86px;
}

.filterActions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.stateBadge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: rgb(var(--color-surface-rgb) / 0.92);
}

.statusDot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.28);
}

.statusDot--success {
  background: rgb(74 222 128);
}

.statusDot--muted {
  background: rgb(148 163 184 / 0.8);
}

.statusBadge,
.modeBadge,
.providerBadge,
.manualBadge {
  border-color: rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-surface-rgb) / 0.04);
  padding: 3px 8px;
  font-size: 0.68rem;
  letter-spacing: 0.06em;
}

.statusBadge {
  min-width: 64px;
}

.statusBadge--success {
  border-color: rgb(34 197 94 / 0.28);
}

.statusBadge--partial {
  border-color: rgb(245 158 11 / 0.3);
}

.statusBadge--failed {
  border-color: rgb(244 63 94 / 0.3);
}

.statusBadge--muted,
.providerBadge--muted {
  opacity: 1;
  color: rgb(var(--color-text-secondary-rgb) / 0.78);
}

.modeBadge--dry {
  border-color: rgb(245 158 11 / 0.28);
}

.modeBadge--auto {
  border-color: rgb(34 197 94 / 0.28);
}

.providerBadge--lt {
  border-color: rgb(16 185 129 / 0.3);
}

.providerBadge--ollama {
  border-color: rgb(14 165 233 / 0.3);
}

.providerBadge--mixed {
  border-color: rgb(245 158 11 / 0.3);
}

.timeCell,
.runStatusCell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.timeCell {
  display: grid;
  justify-items: start;
  gap: 4px;
}

.modeHint,
.translationMetaText,
.paginationInfo {
  font-size: 0.76rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
}

.textBtn {
  padding: 0;
  border: none;
  background: transparent;
  color: rgb(var(--color-primary-rgb) / 0.9);
  font-size: 0.76rem;
  font-weight: 600;
  cursor: pointer;
}

.textBtn:hover {
  text-decoration: underline;
}

.translationTools {
  gap: 10px;
  padding-top: 8px;
}

.translationOverview {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.miniMetric {
  display: grid;
  gap: 4px;
  min-width: 140px;
}

.miniLabel {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: rgb(var(--color-text-secondary-rgb) / 0.8);
}

.progressWrap--compact {
  max-width: 360px;
}

.progressTrack {
  height: 8px;
  border-color: rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(var(--color-surface-rgb) / 0.06);
}

.progressFill {
  background: rgb(34 197 94 / 0.78);
}

.progressFill--active {
  background: rgb(16 185 129 / 0.88);
  box-shadow: none;
}

.advancedTools {
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.08);
  padding-top: 8px;
}

.advancedTools > summary {
  cursor: pointer;
  font-size: 0.8rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.advancedTools--inline {
  border-top: none;
  padding-top: 0;
}

.advancedTools--inline > summary {
  min-height: 34px;
  display: inline-flex;
  align-items: center;
}

.advancedTools__body {
  display: grid;
  gap: 10px;
  margin-top: 10px;
}

.advancedActions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
}

.translationResult {
  display: grid;
  gap: 8px;
}

.translationResult p {
  margin: 0;
  border-color: rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.24);
}

.pagination {
  margin-top: 10px;
  gap: 10px;
}

.modalBackdrop {
  background: rgb(6 11 20 / 0.72);
}

.modalCard {
  border-radius: 16px;
  border-color: rgb(var(--color-surface-rgb) / 0.14);
  background: rgb(21 29 40 / 0.98);
  box-shadow: none;
}

.detailGrid dt,
.detailBlock h4 {
  letter-spacing: 0.12em;
}

.detailBlock pre,
.detailBlock p {
  border-color: rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.24);
}

@media (max-width: 900px) {
  .quickStrip {
    grid-template-columns: 1fr;
  }

  .filterField,
  .filterField--compact {
    min-width: min(100%, 160px);
  }

  .advancedActions,
  .translationOverview {
    align-items: stretch;
  }
}

@media (max-width: 720px) {
  :deep(.adminPageShell.botEngineShell .adminPageShell__header) {
    align-items: stretch;
    flex-direction: column;
  }

  .headerRunBtn {
    width: 100%;
  }

  .panelHeader,
  .collapsibleSummary {
    flex-direction: column;
    align-items: stretch;
  }

  .table {
    min-width: 620px;
  }

  .table--sources {
    min-width: 560px;
  }

  .inlineActions,
  .detailBlockHeader .inlineActions {
    display: grid;
    width: 100%;
  }
}
</style>

