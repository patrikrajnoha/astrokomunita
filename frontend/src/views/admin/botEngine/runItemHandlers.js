export function createRunItemHandlers({
  store,
  toast,
  toErrorMessage,
  selectedRun,
  runItemsMeta,
  publishAllLimit,
  retryTranslationLimit,
  defaultPublishAllLimit,
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
  deleteAllBotPostsApi,
}) {
  async function refreshCurrentRunItems() {
    if (!selectedRun.value?.id) {
      return
    }

    await store.fetchItemsForRun(selectedRun.value.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
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
    if (
      requiresPublishConfirm(item, selectedRun.value?.source_key) &&
      !(await confirmPublishToAstroFeed())
    ) {
      return
    }

    try {
      const response = await store.publishItem(item.id, { force: false })
      if (response?.already_published) {
        toast.info('Položka už je publikovaná.')
      } else {
        toast.success('Položka bola publikovaná.')
      }

      await refreshCurrentRunItems()
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
      await refreshCurrentRunItems()
    } catch (error) {
      toast.error(toErrorMessage(error, 'Nepodarilo sa vymazať publikovaný príspevok.'))
    }
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
              const response = await deleteAllBotPostsApi(params)
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
      await refreshCurrentRunItems()
    } catch (error) {
      toast.error(toErrorMessage(error, 'Hromadné mazanie zlyhalo.'))
    }
  }

  async function publishAllForRun() {
    const runId = Number(selectedRun.value?.id || 0)
    if (!Number.isInteger(runId) || runId <= 0) return

    const limit = Number(publishAllLimit.value)
    const normalizedLimit =
      Number.isInteger(limit) && limit > 0 ? limit : defaultPublishAllLimit

    try {
      const response = await store.publishRun(runId, { publish_limit: normalizedLimit })
      const publishedCount = Number(response?.published_count || 0)
      const skippedCount = Number(response?.skipped_count || 0)
      const failedCount = Number(response?.failed_count || 0)

      toast.success(
        `Publikované: ${publishedCount}. Preskočené: ${skippedCount}. Chyby: ${failedCount}.`,
      )

      await refreshCurrentRunItems()
    } catch (error) {
      toast.error(toErrorMessage(error, 'Nepodarilo sa publikovať položky z behu.'))
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
        `Opakovanie hotové: ${Number(result.done_count || 0)} OK, ${Number(result.skipped_count || 0)} preskočené, ${Number(result.failed_count || 0)} chyby.`,
      )

      await refreshCurrentRunItems()
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

      await refreshCurrentRunItems()
    } catch (error) {
      toast.error(toErrorMessage(error, 'Doplnenie prekladu zlyhalo.'))
    }
  }

  return {
    goToItemsPage,
    publishItem,
    deleteItemPost,
    deleteAllBotPostsForFilter,
    publishAllForRun,
    retryTranslateForRun,
    backfillTranslateForRun,
  }
}
