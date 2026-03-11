import { ref } from 'vue'
import { eventCandidates } from '@/services/eventCandidates'
import { candidateDisplayTitle } from '@/utils/translatedFields'

function createBatchPayloadFromParams(params) {
  return {
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
}

export function useCandidatesCrawledBatchActions({
  buildParams,
  confirm,
  error,
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
      await eventCandidates.approve(candidate.id)
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
      toast.success('Retranslate spusteny.')
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Retranslate zlyhal'
      toast.error(error.value)
    } finally {
      loading.value = false
    }
  }

  async function publishAllVisiblePending() {
    const ids = visiblePendingCandidateIds.value
    if (ids.length === 0) {
      toast.warn('Na tejto stranke nie su ziadni pending kandidati.')
      return
    }

    const ok = await confirm({
      title: 'Publikovat vsetko',
      message: `Naozaj publikovat ${ids.length} pending kandidatov na aktualnej stranke?`,
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
          await eventCandidates.approve(candidateId)
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
      message: 'Naozaj publikovat vsetky pending udalosti podla aktualneho filtra? (max 1000)',
      confirmText: 'Publikovat',
      cancelText: 'Zrusit',
      variant: 'danger',
    })
    if (!ok) return

    loading.value = true
    error.value = null
    startPublishProgress('Publikujem podla filtra...', 1)

    try {
      const result = await eventCandidates.approveBatch(createBatchPayloadFromParams(buildParams()))
      advancePublishProgress(1, 1)
      if (result.failed > 0) {
        toast.warn(`Publikovane: ${result.published}, zlyhalo: ${result.failed}.`)
      } else {
        toast.success(`Publikovanych ${result.published} kandidatov.`)
      }
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadne publikovanie podla filtra zlyhalo'
      toast.error(error.value)
    } finally {
      finishPublishProgress()
      loading.value = false
    }
  }

  async function retranslateVisiblePending() {
    const ids = visiblePendingCandidateIds.value
    if (ids.length === 0) {
      toast.warn('Na tejto stranke nie su ziadni pending kandidati.')
      return
    }

    const ok = await confirm({
      title: 'Prelozit znova viditelnych',
      message: `Spustit novy preklad pre ${ids.length} pending kandidatov na aktualnej stranke?`,
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
        toast.success(`Preklad bol spusteny pre ${successCount} kandidatov.`)
      } else {
        toast.warn(`Preklad spusteny: ${successCount}, zlyhalo: ${failCount}.`)
      }

      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadny retranslate zlyhal'
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
        toast.warn(`Retranslate podla filtra: queued ${result.queued}, failed ${result.failed}.`)
      } else {
        toast.success(`Retranslate podla filtra: queued ${result.queued}.`)
      }
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadny retranslate podla filtra zlyhal'
      toast.error(error.value)
    } finally {
      loading.value = false
    }
  }

  return {
    advancePublishProgress,
    finishPublishProgress,
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
