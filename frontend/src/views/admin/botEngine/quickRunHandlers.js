export function createQuickRunHandlers({
  normalizeBotIdentity,
  botIdentityLabel,
  sourceCountLabel,
  quickRunResultChips,
  runStatusHint,
  toErrorMessage,
  validBotIdentities,
  enabledSourcesByIdentity,
  hasEnabledSources,
  quickRunBusyIdentity,
  runSource,
  reloadData,
  toast,
  onProgressStart,
  onProgressUpdate,
  onProgressDone,
}) {
  async function executeQuickRun(identity, hooks = {}) {
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
      if (typeof hooks.onSourceStart === 'function') {
        hooks.onSourceStart({
          identity: normalizedIdentity,
          sourceKey: source.key,
        })
      }

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
        lastErrorMessage = toErrorMessage(error, `Nepodarilo sa spustiť zdroj "${source.key}".`)
      } finally {
        if (typeof hooks.onSourceDone === 'function') {
          hooks.onSourceDone({
            identity: normalizedIdentity,
            sourceKey: source.key,
          })
        }
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

    const enabledSources = Array.isArray(enabledSourcesByIdentity.value[normalizedIdentity])
      ? enabledSourcesByIdentity.value[normalizedIdentity]
      : []
    const total = enabledSources.length
    let completed = 0

    if (typeof onProgressStart === 'function') {
      onProgressStart({
        scope: 'identity',
        identity: normalizedIdentity,
        total,
      })
    }

    quickRunBusyIdentity.value = normalizedIdentity
    let result = null
    try {
      result = await executeQuickRun(normalizedIdentity, {
        onSourceStart: ({ sourceKey }) => {
          if (typeof onProgressUpdate === 'function') {
            onProgressUpdate({
              scope: 'identity',
              identity: normalizedIdentity,
              total,
              completed,
              sourceKey,
              phase: 'running',
            })
          }
        },
        onSourceDone: ({ sourceKey }) => {
          completed += 1
          if (typeof onProgressUpdate === 'function') {
            onProgressUpdate({
              scope: 'identity',
              identity: normalizedIdentity,
              total,
              completed,
              sourceKey,
              phase: 'completed',
            })
          }
        },
      })
    } catch (error) {
      toast.error(toErrorMessage(error, 'Spustenie bota zlyhalo.'))
      return
    } finally {
      quickRunBusyIdentity.value = ''
      if (typeof onProgressDone === 'function') {
        onProgressDone({
          scope: 'identity',
          identity: normalizedIdentity,
        })
      }
    }

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

    const total = validBotIdentities.reduce((sum, identity) => {
      const normalizedIdentity = normalizeBotIdentity(identity)
      const enabledSources = Array.isArray(enabledSourcesByIdentity.value[normalizedIdentity])
        ? enabledSourcesByIdentity.value[normalizedIdentity]
        : []

      return sum + enabledSources.length
    }, 0)

    let completed = 0
    if (typeof onProgressStart === 'function') {
      onProgressStart({
        scope: 'all',
        identity: 'all',
        total,
      })
    }

    quickRunBusyIdentity.value = 'all'

    const results = []
    try {
      for (const identity of validBotIdentities) {
        const normalizedIdentity = normalizeBotIdentity(identity)
        const result = await executeQuickRun(identity, {
          onSourceStart: ({ sourceKey }) => {
            if (typeof onProgressUpdate === 'function') {
              onProgressUpdate({
                scope: 'all',
                identity: normalizedIdentity,
                total,
                completed,
                sourceKey,
                phase: 'running',
              })
            }
          },
          onSourceDone: ({ sourceKey }) => {
            completed += 1
            if (typeof onProgressUpdate === 'function') {
              onProgressUpdate({
                scope: 'all',
                identity: normalizedIdentity,
                total,
                completed,
                sourceKey,
                phase: 'completed',
              })
            }
          },
        })
        if (result && result.processedCount > 0) {
          results.push(result)
        }
      }
    } catch (error) {
      toast.error(toErrorMessage(error, 'Spustenie všetkých botov zlyhalo.'))
      return
    } finally {
      quickRunBusyIdentity.value = ''
      if (typeof onProgressDone === 'function') {
        onProgressDone({
          scope: 'all',
          identity: 'all',
        })
      }
    }
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
