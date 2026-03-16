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
      title: 'Publikovať kandidáta',
      message: `Publikovať "${candidateDisplayTitle(candidate)}" do udalosti?`,
      confirmText: 'Publikovať',
      cancelText: 'Zrušiť',
    })
    if (!ok) return

    loading.value = true
    error.value = null
    startPublishProgress('Publikovanie kandidáta...', 1)
    try {
      await eventCandidates.approve(candidate.id)
      advancePublishProgress(1, 1)
      toast.success('Kandidát bol publikovaný.')
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
      toast.success('Retranslate spustený.')
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
      toast.warn('Na tejto stránke nie sú žiadni pending kandidáti.')
      return
    }

    const ok = await confirm({
      title: 'Publikovať všetko',
      message: `Naozaj publikovať ${ids.length} pending kandidátov na aktuálnej stránke?`,
      confirmText: 'Publikovať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    })
    if (!ok) return

    loading.value = true
    error.value = null

    let successCount = 0
    let failCount = 0
    const total = ids.length

    try {
      startPublishProgress('Publikujem viditeľných kandidátov...', total)
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
        toast.success(`Publikovaných ${successCount} kandidátov.`)
      } else {
        toast.warn(`Publikované: ${successCount}, zlyhalo: ${failCount}.`)
      }

      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadné publikovanie zlyhalo'
      toast.error(error.value)
    } finally {
      finishPublishProgress()
      loading.value = false
    }
  }

  async function publishAllByFilter() {
    const ok = await confirm({
      title: 'Publikovať všetko podľa filtra',
      message: 'Naozaj publikovať všetky pending udalosti podľa aktuálneho filtra? (max 1000)',
      confirmText: 'Publikovať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    })
    if (!ok) return

    loading.value = true
    error.value = null
    startPublishProgress('Publikujem podľa filtra...', 1)

    try {
      const result = await eventCandidates.approveBatch(createBatchPayloadFromParams(buildParams()))
      advancePublishProgress(1, 1)
      if (result.failed > 0) {
        toast.warn(`Publikované: ${result.published}, zlyhalo: ${result.failed}.`)
      } else {
        toast.success(`Publikovaných ${result.published} kandidátov.`)
      }
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadné publikovanie podľa filtra zlyhalo'
      toast.error(error.value)
    } finally {
      finishPublishProgress()
      loading.value = false
    }
  }

  async function retranslateVisiblePending() {
    const ids = visiblePendingCandidateIds.value
    if (ids.length === 0) {
      toast.warn('Na tejto stránke nie sú žiadni pending kandidáti.')
      return
    }

    const ok = await confirm({
      title: 'Preložiť znova viditeľných',
      message: `Spustiť nový preklad pre ${ids.length} pending kandidátov na aktuálnej stránke?`,
      confirmText: 'Spustiť',
      cancelText: 'Zrušiť',
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
        toast.success(`Preklad bol spustený pre ${successCount} kandidátov.`)
      } else {
        toast.warn(`Preklad spustený: ${successCount}, zlyhalo: ${failCount}.`)
      }

      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadný retranslate zlyhal'
      toast.error(error.value)
    } finally {
      loading.value = false
    }
  }

  async function retranslateByFilter() {
    const ok = await confirm({
      title: 'Preložiť znova podľa filtra',
      message: 'Spustiť nový preklad pre kandidátov podľa aktuálneho filtra? (max 1000)',
      confirmText: 'Spustiť',
      cancelText: 'Zrušiť',
    })
    if (!ok) return

    loading.value = true
    error.value = null

    try {
      const result = await eventCandidates.retranslateBatch(createBatchPayloadFromParams(buildParams()))
      if (result.failed > 0) {
        toast.warn(`Retranslate podľa filtra: queued ${result.queued}, failed ${result.failed}.`)
      } else {
        toast.success(`Retranslate podľa filtra: queued ${result.queued}.`)
      }
      await load()
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hromadný retranslate podľa filtra zlyhal'
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
