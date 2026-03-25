<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { useBotEngineStore } from '@/stores/botEngine'
import { useToast } from '@/composables/useToast'
import {
  VALID_BOT_IDENTITIES,
  normalizeBotIdentity,
  toErrorMessage,
  translationProviderLabel,
  translationModeLabel,
  sourceCountLabel,
  quickRunResultChips,
  runStatusHint,
  botIdentityLabel,
} from './botEngineView.utils'
import { useBotEngineRuns } from './botEngine/useBotEngineRuns'
import { createQuickRunHandlers } from './botEngine/quickRunHandlers'
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
})

const store = useBotEngineStore()
const toast = useToast()

const {
  sources,
  runsPage,
  runItemsPage,
  filters,
  translationHealth,
} = storeToRefs(store)

const {
  enabledSourcesByIdentity,
  hasEnabledSources,
  loadSources,
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

const pageTitle = computed(() => 'Legacy nástroje')
const pageSubtitle = computed(() => 'Sekundárny servisný panel pre zriedkavé manuálne akcie.')

const quickRunBusyIdentity = ref('')
const quickRunProgress = ref({
  active: false,
  scope: 'all',
  identity: '',
  total: 0,
  completed: 0,
  sourceKey: '',
  phase: 'idle',
})

const quickRunProgressPercent = computed(() => {
  const total = Number(quickRunProgress.value.total || 0)
  if (total <= 0) {
    return quickRunProgress.value.active ? 100 : 0
  }

  const completed = Number(quickRunProgress.value.completed || 0)
  const ratio = Math.min(1, Math.max(0, completed / total))
  return Math.round(ratio * 100)
})

const quickRunProgressLabel = computed(() => {
  if (quickRunProgress.value.scope === 'identity') {
    const identityLabel = botIdentityLabel(quickRunProgress.value.identity)
    return identityLabel ? `Run control: ${identityLabel}` : 'Run control'
  }

  return 'Run control: všetky zdroje'
})

const quickRunProgressHint = computed(() => {
  if (!quickRunProgress.value.active) {
    return ''
  }

  const total = Number(quickRunProgress.value.total || 0)
  const completed = Number(quickRunProgress.value.completed || 0)
  const sourceKey = String(quickRunProgress.value.sourceKey || '').trim()
  const runningText = sourceKey !== '' ? `Spracúva sa zdroj ${sourceKey}.` : 'Spracúvajú sa zdroje.'

  if (quickRunProgress.value.phase === 'running') {
    return `${runningText} Hotové: ${completed}/${total}.`
  }

  return `Hotové: ${completed}/${total}.`
})

function resetQuickRunProgress() {
  quickRunProgress.value = {
    active: false,
    scope: 'all',
    identity: '',
    total: 0,
    completed: 0,
    sourceKey: '',
    phase: 'idle',
  }
}

function handleQuickRunProgressStart(payload = {}) {
  quickRunProgress.value = {
    active: true,
    scope: String(payload.scope || 'all'),
    identity: String(payload.identity || ''),
    total: Math.max(0, Number(payload.total || 0)),
    completed: 0,
    sourceKey: '',
    phase: 'running',
  }
}

function handleQuickRunProgressUpdate(payload = {}) {
  if (!quickRunProgress.value.active) {
    return
  }

  const total = Math.max(0, Number(payload.total ?? quickRunProgress.value.total))
  const completed = Math.min(total, Math.max(0, Number(payload.completed ?? quickRunProgress.value.completed)))

  quickRunProgress.value = {
    ...quickRunProgress.value,
    scope: String(payload.scope || quickRunProgress.value.scope || 'all'),
    identity: String(payload.identity || quickRunProgress.value.identity || ''),
    total,
    completed,
    sourceKey: String(payload.sourceKey || ''),
    phase: String(payload.phase || quickRunProgress.value.phase || 'running'),
  }
}

function handleQuickRunProgressDone() {
  resetQuickRunProgress()
}

const {
  aiPanelError,
  aiPanelLastRun,
  aiPanelNotice,
  aiPanelRunHint,
  aiPanelStatus,
  loadTranslationHealth,
  testTranslation,
  translationTestModel,
  translationTestProvider,
  translationTestProviderOptions,
  translationTestResult,
  translationTestTemperature,
  translationTestText,
} = useBotEngineTranslationTools({
  store,
  translationHealth,
  toast,
  toErrorMessage,
  normalizeOutageProvider: (value) => value,
  translationProviderLabel,
})

const aiStatus = computed(() => {
  const status = String(aiPanelStatus.value || 'idle').trim().toLowerCase()

  if (status === 'success') {
    return {
      label: 'V poriadku',
      className: 'statusBadge statusBadge--success',
    }
  }

  if (status === 'fallback') {
    return {
      label: 'Degradovaný',
      className: 'statusBadge statusBadge--partial',
    }
  }

  if (status === 'error') {
    return {
      label: 'Chyba',
      className: 'statusBadge statusBadge--failed',
    }
  }

  return {
    label: 'Pripravený',
    className: 'statusBadge statusBadge--muted',
  }
})

const { quickRunAll } = createQuickRunHandlers({
  normalizeBotIdentity,
  botIdentityLabel,
  sourceCountLabel,
  quickRunResultChips,
  runStatusHint,
  toErrorMessage,
  validBotIdentities: VALID_BOT_IDENTITIES,
  enabledSourcesByIdentity,
  hasEnabledSources,
  quickRunBusyIdentity,
  runSource: store.runSource.bind(store),
  reloadData: async () => {
    await loadSources()
  },
  toast,
  onProgressStart: handleQuickRunProgressStart,
  onProgressUpdate: handleQuickRunProgressUpdate,
  onProgressDone: handleQuickRunProgressDone,
})

onMounted(async () => {
  await Promise.all([loadSources(), loadTranslationHealth()])
})
</script>

<template src="./botEngine/BotEngineView.template.html"></template>

<style scoped src="./botEngine/BotEngineView.css"></style>
