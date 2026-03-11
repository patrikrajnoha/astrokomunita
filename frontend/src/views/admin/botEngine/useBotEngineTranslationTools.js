import { computed, ref } from 'vue'

export function useBotEngineTranslationTools({
  store,
  translationHealth,
  toast,
  toErrorMessage,
  normalizeOutageProvider,
  translationProviderLabel,
}) {
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
  const translationHealthPollTimer = ref(null)

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

  async function loadTranslationHealth() {
    try {
      const health = await store.fetchTranslationHealth()
      translationOutageProvider.value = normalizeOutageProvider(health?.simulate_outage_provider)
    } catch (error) {
      toast.error(toErrorMessage(error, 'Nepodarilo sa načítať stav prekladov.'))
    }
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

  return {
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
  }
}
