import { ref } from 'vue'
import { eventCandidates } from '@/services/eventCandidates'
import { candidateDisplayTitle } from '@/utils/translatedFields'

function createBatchPayloadFromParams(params) {
  return {
    status: params.status,
    type: params.type,
    description_mode: params.description_mode,
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
}

const TERMINAL_PUBLISH_RUN_STATUSES = new Set(['completed', 'completed_with_failures', 'failed'])
const PUBLISH_RUN_POLL_INTERVAL_MS = 1200
const PUBLISH_RUN_POLL_TIMEOUT_MS = 10 * 60 * 1000
const PUBLISH_RUN_STALL_POLL_THRESHOLD = 8

export function useCandidatesCrawledBatchActions({
  buildParams,
  confirm,
  error,
  getPublishDescriptionMode,
  load,
  loading,
  toast,
  visiblePendingCandidateIds,
}) {
  const publishProgressActive = ref(false)
  const publishProgressLabel = ref('')
  const publishProgressPercent = ref(0)

  function startPublishProgress(label, totalSteps = 1) {
    publishProgressActive.value = true
    publishProgressLabel.value = label
    publishProgressPercent.value = totalSteps > 0 ? 1 : 0
  }

  function advancePublishProgress(doneSteps, totalSteps) {
    if (!publishProgressActive.value || totalSteps <= 0) return
    const safeDone = Math.max(0, Math.min(totalSteps, Number(doneSteps) || 0))
    publishProgressPercent.value = Math.max(1, Math.round((safeDone / totalSteps) * 100))
  }

  function finishPublishProgress() {
    if (!publishProgressActive.value) return
    publishProgressPercent.value = 100
    window.setTimeout(() => {
      publishProgressActive.value = false
      publishProgressLabel.value = ''
      publishProgressPercent.value = 0
    }, 500)
  }

  function resolvePublishDescriptionMode() {
    const mode = String(typeof getPublishDescriptionMode === 'function' ? getPublishDescriptionMode() : '').trim().toLowerCase()
    return ['template', 'ai', 'mix'].includes(mode) ? mode : 'template'
  }

  function normalizePublishRun(run) {
    const status = String(run?.status || '').trim().toLowerCase()
    const totalSelectedRaw = Number(run?.total_selected ?? 0)
    const totalSelected = Number.isFinite(totalSelectedRaw) ? Math.max(0, Math.round(totalSelectedRaw)) : 0

    const processedRaw = Number(run?.processed ?? 0)
    const processed = Number.isFinite(processedRaw)
      ? Math.max(0, Math.min(totalSelected > 0 ? totalSelected : processedRaw, processedRaw))
      : 0

    return {
      id: Number(run?.id || 0),
      status,
      isTerminal: Boolean(run?.is_terminal) || TERMINAL_PUBLISH_RUN_STATUSES.has(status),
      totalSelected,
      processed,
      published: Math.max(0, Number(run?.published || 0)),
      failed: Math.max(0, Number(run?.failed || 0)),
      errorMessage: String(run?.error_message || '').trim(),
    }
  }

  async function waitForPublishRunCompletion(runId, payload, initialRun = null) {
    let latest = normalizePublishRun(initialRun)
    let queuedWithoutProgressPolls = latest.status === 'queued' && latest.processed <= 0 ? 1 : 0

    if (latest.totalSelected > 0) {
      advancePublishProgress(latest.processed, latest.totalSelected)
    }

    if (latest.isTerminal) {
      return { ...latest, timedOut: false, usedSyncFallback: false }
    }

    const startedAt = Date.now()
    let lastPollError = null

    while (Date.now() - startedAt < PUBLISH_RUN_POLL_TIMEOUT_MS) {
      await new Promise((resolve) => window.setTimeout(resolve, PUBLISH_RUN_POLL_INTERVAL_MS))

      try {
        const response = await eventCandidates.approveBatchRunStatus(runId)
        latest = normalizePublishRun(response?.run)
        lastPollError = null
      } catch (pollError) {
        lastPollError = pollError
      }

      if (latest.totalSelected > 0) {
        advancePublishProgress(latest.processed, latest.totalSelected)
      }

      if (latest.isTerminal) {
        return { ...latest, timedOut: false, usedSyncFallback: false }
      }

      if (latest.status === 'queued' && latest.processed <= 0) {
        queuedWithoutProgressPolls += 1
      } else {
        queuedWithoutProgressPolls = 0
      }

      if (queuedWithoutProgressPolls >= PUBLISH_RUN_STALL_POLL_THRESHOLD) {
        publishProgressLabel.value = 'Queue worker nereaguje, dokoncujem publikovanie priamo...'
        const fallbackResult = await eventCandidates.approveBatch(payload)
        const fallbackTotal = Math.max(
          0,
          Number(
            fallbackResult?.total_selected
            ?? latest.totalSelected
            ?? 0
          )
        )

        if (fallbackTotal > 0) {
          advancePublishProgress(fallbackTotal, fallbackTotal)
        }

        return {
          ...latest,
          status: Number(fallbackResult?.failed || 0) > 0 ? 'completed_with_failures' : 'completed',
          isTerminal: true,
          totalSelected: fallbackTotal,
          processed: fallbackTotal,
          published: Math.max(0, Number(fallbackResult?.published || 0)),
          failed: Math.max(0, Number(fallbackResult?.failed || 0)),
          errorMessage: '',
          timedOut: false,
          usedSyncFallback: true,
        }
      }
    }

    if (latest.id > 0) {
      return { ...latest, timedOut: true, usedSyncFallback: false }
    }

    if (lastPollError) {
      throw lastPollError
    }

    return { ...latest, timedOut: true, usedSyncFallback: false }
  }

  async function runApproveBatchWithPolling(payload) {
    const started = await eventCandidates.approveBatchStart(payload)
    const run = normalizePublishRun(started?.run)

    if (!run.id) {
      throw new Error('Server nevratil ID publish runu.')
    }

    if (run.totalSelected <= 0) {
      advancePublishProgress(1, 1)
      return { ...run, timedOut: false, usedSyncFallback: false }
    }

    startPublishProgress(publishProgressLabel.value, run.totalSelected)
    advancePublishProgress(run.processed, run.totalSelected)

    const finished = await waitForPublishRunCompletion(run.id, payload, started?.run)
    if (finished.status === 'failed') {
      throw new Error(finished.errorMessage || 'Hromadne publikovanie zlyhalo na serveri.')
    }

    return finished
  }

  async function publishCandidateQuick(candidate) {
    if (!candidate?.id || String(candidate?.status || '') !== 'pending') return

    const ok = await confirm({
      title: 'Publikovat kandidata',
      message: `Publikovat "${candidateDisplayTitle(candidate)}" do udalosti?`,
      confirmText: 'Publikovat',
      cancelText: 'Zrusit',
    })
    if (!ok) return

    loading.value = true
    error.value = null
    startPublishProgress('Publikovanie kandidata...', 1)
    try {
      await eventCandidates.approve(candidate.id, { mode: resolvePublishDescriptionMode() })
      advancePublishProgress(1, 1)
      toast.success('Kandidat bol publikovany.')
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Publikovanie zlyhalo'
      toast.error(error.value)
    } finally {
      finishPublishProgress()
      loading.value = false
    }
  }

  async function retranslateCandidateQuick(candidate) {
    if (!candidate?.id || loading.value) return

    loading.value = true
    error.value = null
    try {
      await eventCandidates.retranslate(candidate.id)
      toast.success('Generovanie popisu bolo spustene.')
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Spustenie generovania popisu zlyhalo'
      toast.error(error.value)
    } finally {
      loading.value = false
    }
  }

  async function publishAllPending() {
    const ok = await confirm({
      title: 'Publikovat vsetko',
      message: 'Naozaj publikovat vsetky cakajuce udalosti bez filtra? (max 1000)',
      confirmText: 'Publikovat',
      cancelText: 'Zrusit',
      variant: 'danger',
    })
    if (!ok) return

    loading.value = true
    error.value = null
    startPublishProgress('Publikujem vsetky cakajuce udalosti...', 1)

    try {
      const result = await runApproveBatchWithPolling({
        status: 'pending',
        limit: 1000,
        mode: resolvePublishDescriptionMode(),
      })

      if (result.timedOut) {
        toast.warn(`Publikovanie bezi na pozadi (run #${result.id}). Obnov zoznam o chvilu.`)
      } else if (result.usedSyncFallback) {
        if (result.failed > 0) {
          toast.warn(`Queue worker nereagoval, publikovane priamo: ${result.published}, zlyhalo: ${result.failed}.`)
        } else {
          toast.success(`Queue worker nereagoval, publikovanych ${result.published} kandidatov priamo.`)
        }
      } else if (result.failed > 0) {
        toast.warn(`Publikovane: ${result.published}, zlyhalo: ${result.failed}.`)
      } else {
        toast.success(`Publikovanych ${result.published} kandidatov.`)
      }
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || 'Hromadne publikovanie vsetkeho zlyhalo'
      toast.error(error.value)
    } finally {
      finishPublishProgress()
      loading.value = false
    }
  }

  async function publishAllVisiblePending() {
    const ids = visiblePendingCandidateIds.value
    if (ids.length === 0) {
      toast.warn('Na tejto stranke nie su ziadni cakajuci kandidati.')
      return
    }

    const ok = await confirm({
      title: 'Publikovat vsetko',
      message: `Naozaj publikovat ${ids.length} cakajucich kandidatov na aktualnej stranke?`,
      confirmText: 'Publikovat',
      cancelText: 'Zrusit',
      variant: 'danger',
    })
    if (!ok) return

    loading.value = true
    error.value = null

    let successCount = 0
    let failCount = 0
    const total = ids.length

    try {
      startPublishProgress('Publikujem viditelnych kandidatov...', total)
      let doneCount = 0
      for (const candidateId of ids) {
        try {
          await eventCandidates.approve(candidateId, { mode: resolvePublishDescriptionMode() })
          successCount += 1
        } catch {
          failCount += 1
        }
        doneCount += 1
        advancePublishProgress(doneCount, total)
      }

      if (failCount === 0) {
        toast.success(`Publikovanych ${successCount} kandidatov.`)
      } else {
        toast.warn(`Publikovane: ${successCount}, zlyhalo: ${failCount}.`)
      }

      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadne publikovanie zlyhalo'
      toast.error(error.value)
    } finally {
      finishPublishProgress()
      loading.value = false
    }
  }

  async function publishAllByFilter() {
    const ok = await confirm({
      title: 'Publikovat vsetko podla filtra',
      message: 'Naozaj publikovat vsetky cakajuce udalosti podla aktualneho filtra? (max 1000)',
      confirmText: 'Publikovat',
      cancelText: 'Zrusit',
      variant: 'danger',
    })
    if (!ok) return

    loading.value = true
    error.value = null
    startPublishProgress('Publikujem podla filtra...', 1)

    try {
      const result = await runApproveBatchWithPolling({
        ...createBatchPayloadFromParams(buildParams()),
        mode: resolvePublishDescriptionMode(),
      })

      if (result.timedOut) {
        toast.warn(`Publikovanie bezi na pozadi (run #${result.id}). Obnov zoznam o chvilu.`)
      } else if (result.usedSyncFallback) {
        if (result.failed > 0) {
          toast.warn(`Queue worker nereagoval, publikovane priamo: ${result.published}, zlyhalo: ${result.failed}.`)
        } else {
          toast.success(`Queue worker nereagoval, publikovanych ${result.published} kandidatov priamo.`)
        }
      } else if (result.failed > 0) {
        toast.warn(`Publikovane: ${result.published}, zlyhalo: ${result.failed}.`)
      } else {
        toast.success(`Publikovanych ${result.published} kandidatov.`)
      }
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || 'Hromadne publikovanie podla filtra zlyhalo'
      toast.error(error.value)
    } finally {
      finishPublishProgress()
      loading.value = false
    }
  }

  async function retranslateVisiblePending() {
    const ids = visiblePendingCandidateIds.value
    if (ids.length === 0) {
      toast.warn('Na tejto stranke nie su ziadni cakajuci kandidati.')
      return
    }

    const ok = await confirm({
      title: 'Prelozit znova viditelne',
      message: `Spustit novy preklad pre ${ids.length} cakajucich kandidatov na aktualnej stranke?`,
      confirmText: 'Spustit',
      cancelText: 'Zrusit',
    })
    if (!ok) return

    loading.value = true
    error.value = null

    let successCount = 0
    let failCount = 0

    try {
      for (const candidateId of ids) {
        try {
          await eventCandidates.retranslate(candidateId)
          successCount += 1
        } catch {
          failCount += 1
        }
      }

      if (failCount === 0) {
        toast.success(`Generovanie popisu bolo spustene pre ${successCount} kandidatov.`)
      } else {
        toast.warn(`Generovanie popisu spustene: ${successCount}, zlyhalo: ${failCount}.`)
      }

      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadne spustenie generovania popisov zlyhalo'
      toast.error(error.value)
    } finally {
      loading.value = false
    }
  }

  async function retranslateByFilter() {
    const ok = await confirm({
      title: 'Prelozit znova podla filtra',
      message: 'Spustit novy preklad pre kandidatov podla aktualneho filtra? (max 1000)',
      confirmText: 'Spustit',
      cancelText: 'Zrusit',
    })
    if (!ok) return

    loading.value = true
    error.value = null

    try {
      const result = await eventCandidates.retranslateBatch(createBatchPayloadFromParams(buildParams()))
      if (result.failed > 0) {
        toast.warn(`Generovanie popisov podla filtra: spustene ${result.queued}, zlyhalo ${result.failed}.`)
      } else {
        toast.success(`Generovanie popisov podla filtra: spustene ${result.queued}.`)
      }
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadne generovanie popisov podla filtra zlyhalo'
      toast.error(error.value)
    } finally {
      loading.value = false
    }
  }

  return {
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
  }
}

