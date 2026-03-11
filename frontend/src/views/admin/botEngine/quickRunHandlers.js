export function createQuickRunHandlers({
  normalizeBotIdentity,
  botIdentityLabel,
  sourceCountLabel,
  quickRunResultChips,
  runStatusHint,
  runStatusLabel,
  toErrorMessage,
  validBotIdentities,
  enabledSourcesByIdentity,
  hasEnabledSources,
  quickRunBusyIdentity,
  runSource,
  reloadData,
  toast,
}) {
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
        skippedCount: 0,
        failedCount: 0,
        lastErrorMessage: '',
        lastHintMessage: '',
        summary: `${botIdentityLabel(normalizedIdentity)}: 0 zdrojov.`,
      }
    }

    let successCount = 0
    let partialCount = 0
    let skippedCount = 0
    let failedCount = 0
    let lastErrorMessage = ''
    let lastHintMessage = ''

    for (const source of enabledSources) {
      try {
        const result = await runSource(source.key, {
          mode: 'auto',
          force_manual_override: true,
        })
        const status = String(result?.status || '').toLowerCase()
        const statusHint = runStatusHint(result)

        if (status === 'success') {
          successCount++
        } else if (status === 'partial') {
          partialCount++
          if (lastHintMessage === '' && statusHint !== '') {
            lastHintMessage = statusHint
          }
        } else if (status === 'skipped') {
          skippedCount++
          if (lastHintMessage === '' && statusHint !== '') {
            lastHintMessage = statusHint
          }
        } else {
          failedCount++
          lastErrorMessage =
            statusHint || `Zdroj "${source.key}" skoncil so stavom "${status || 'unknown'}".`
        }
      } catch (error) {
        failedCount++
        lastErrorMessage = toErrorMessage(error, `Nepodarilo sa spustit zdroj "${source.key}".`)
      }
    }

    const processedCount = successCount + partialCount + skippedCount + failedCount
    const summary = `${botIdentityLabel(normalizedIdentity)}: ${sourceCountLabel(processedCount)} (${quickRunResultChips({
      successCount,
      partialCount,
      skippedCount,
      failedCount,
    })}).`

    return {
      identity: normalizedIdentity,
      processedCount,
      successCount,
      partialCount,
      skippedCount,
      failedCount,
      lastErrorMessage,
      lastHintMessage,
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

    await reloadData()

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

    if (result.partialCount > 0 || result.skippedCount > 0) {
      const hint = result.lastHintMessage ? ` ${result.lastHintMessage}` : ''
      toast.success(`${result.summary}${hint}`)
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
    for (const identity of validBotIdentities) {
      const result = await executeQuickRun(identity)
      if (result && result.processedCount > 0) {
        results.push(result)
      }
    }

    quickRunBusyIdentity.value = ''
    await reloadData()

    if (results.length === 0) {
      return
    }

    let lastErrorMessage = ''
    let lastHintMessage = ''
    let hasFailure = false
    let hasPartial = false
    let hasSkipped = false

    for (const result of results) {
      if (result.failedCount > 0) {
        hasFailure = true
        if (result.lastErrorMessage !== '') {
          lastErrorMessage = result.lastErrorMessage
        }
      }

      if (result.partialCount > 0) {
        hasPartial = true
      }

      if (result.skippedCount > 0) {
        hasSkipped = true
      }

      if (result.lastHintMessage !== '') {
        lastHintMessage = result.lastHintMessage
      }
    }

    const summary = results.map((result) => result.summary).join(' | ')
    const completionLabel = hasFailure
      ? 'Spustenie dokoncene s chybami.'
      : hasPartial
        ? 'Spustenie dokoncene ciastocne.'
        : hasSkipped
          ? 'Spustenie dokoncene s preskocenymi zdrojmi.'
          : 'Spustenie dokoncene.'

    if (hasFailure && lastErrorMessage !== '') {
      toast.error(`${completionLabel} ${summary} ${lastErrorMessage}`)
      return
    }

    if (hasFailure) {
      toast.error(`${completionLabel} ${summary}`)
      return
    }

    if ((hasPartial || hasSkipped) && lastHintMessage !== '') {
      toast.success(`${completionLabel} ${summary} ${lastHintMessage}`)
      return
    }

    toast.success(`${completionLabel} ${summary}`)
  }

  return {
    quickRunAll,
    quickRunIdentity,
  }
}
