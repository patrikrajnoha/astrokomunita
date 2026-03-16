<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminAiActionPanel from '@/components/admin/shared/AdminAiActionPanel.vue'
import { useBotEngineStore } from '@/stores/botEngine'
import { deleteAllBotPosts } from '@/services/api/admin/bots'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import BotRunDetailModal from './botEngine/BotRunDetailModal.vue'
import {
  DEFAULT_PUBLISH_ALL_LIMIT,
  VALID_BOT_IDENTITIES,
  normalizeBotIdentity,
  toErrorMessage,
  formatDateTime,
  translationProviderLabel,
  translationProviderClass,
  translationModeLabel,
  runModeLabel,
  runModeClass,
  runPublishLimit,
  resolvePublishAllLimitDefault,
  requiresPublishConfirm,
  botIdentityLabel,
  sourceTypeLabel,
  sourceStateLabel,
  sourceCountLabel,
  quickRunResultChips,
  runStatCount,
  normalizeOutageProvider,
  statusClass,
  runStatusLabel,
  runStatusHint,
  canPublishItem,
  canDeleteItemPost,
} from './botEngineView.utils'
import { useBotEngineRuns } from './botEngine/useBotEngineRuns'
import { createQuickRunHandlers } from './botEngine/quickRunHandlers'
import { createRunItemHandlers } from './botEngine/runItemHandlers'
import { useBotEngineTranslationTools } from './botEngine/useBotEngineTranslationTools'

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

const {
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
  applyRunsFilters,
} = useBotEngineRuns({
  props,
  store,
  sources,
  runsPage,
  runItemsPage,
  filters,
  toast,
  normalizeBotIdentity,
  toErrorMessage,
})

const pageTitle = computed(() => 'Modul')
const pageSubtitle = computed(() => 'Run control, preklady a publikovanie.')

const quickRunBusyIdentity = ref('')
const {
  aiPanelError,
  aiPanelLastRun,
  aiPanelNotice,
  aiPanelRunHint,
  aiPanelStatus,
  isTranslationQueueActive,
  loadTranslationHealth,
  retryTranslationLimit,
  saveTranslationOutageSimulation,
  startTranslationHealthPolling,
  stopTranslationHealthPolling,
  testTranslation,
  translationHealthState,
  translationOutageProvider,
  translationQueue,
  translationTestModel,
  translationTestProvider,
  translationTestResult,
  translationTestTemperature,
  translationTestText,
} = useBotEngineTranslationTools({
  store,
  translationHealth,
  toast,
  toErrorMessage,
  normalizeOutageProvider,
  translationProviderLabel,
})

async function confirmPublishToAstroFeed() {
  return Boolean(
    await confirm({
      title: 'Publikovať do AstroFeedu',
      message: 'Naozaj publikovať túto položku do AstroFeedu?',
      confirmText: 'Publikovať',
      cancelText: 'Zrušiť',
    }),
  )
}

async function initialize() {
  await Promise.all([loadSources(), loadRuns(), loadTranslationHealth()])
}

const { quickRunAll, quickRunIdentity } = createQuickRunHandlers({
  normalizeBotIdentity,
  botIdentityLabel,
  sourceCountLabel,
  quickRunResultChips,
  runStatusHint,
  runStatusLabel,
  toErrorMessage,
  validBotIdentities: VALID_BOT_IDENTITIES,
  enabledSourcesByIdentity,
  hasEnabledSources,
  quickRunBusyIdentity,
  runSource: store.runSource.bind(store),
  reloadData: async () => {
    await Promise.all([loadSources(), loadRuns()])
  },
  toast,
})

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

async function confirmDeletePublishedPost() {
  return Boolean(
    await confirm({
      title: 'Vymazať publikovaný príspevok',
      message: 'Naozaj vymazať publikovaný bot príspevok z feedu?',
      confirmText: 'Vymazať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    }),
  )
}

async function confirmBackfillTranslation() {
  return Boolean(
    await confirm({
      title: 'Doplniť preklad',
      message: 'Doplniť preklad aj do už publikovaných príspevkov?',
      confirmText: 'Doplniť',
      cancelText: 'Zrušiť',
    }),
  )
}

async function confirmDeleteAllBotPosts() {
  return Boolean(
    await confirm({
      title: 'Hromadné mazanie',
      message: 'Naozaj vymazať všetky publikované bot príspevky podľa aktuálneho filtra?',
      confirmText: 'Vymazať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    }),
  )
}

const {
  goToItemsPage,
  publishItem,
  deleteItemPost,
  deleteAllBotPostsForFilter,
  publishAllForRun,
  retryTranslateForRun,
  backfillTranslateForRun,
} = createRunItemHandlers({
  store,
  toast,
  toErrorMessage,
  selectedRun,
  runItemsMeta,
  publishAllLimit,
  retryTranslationLimit,
  defaultPublishAllLimit: DEFAULT_PUBLISH_ALL_LIMIT,
  canPublishItem,
  canDeleteItemPost,
  requiresPublishConfirm,
  confirmPublishToAstroFeed,
  confirmDeletePublishedPost,
  confirmBackfillTranslation,
  confirmDeleteAllBotPosts,
  loadRuns,
  loadTranslationHealth,
  effectiveBotIdentity,
  filterForm,
  deleteAllBotPostsApi: deleteAllBotPosts,
})

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

<template src="./botEngine/BotEngineView.template.html"></template>

<style scoped src="./botEngine/BotEngineView.css"></style>



