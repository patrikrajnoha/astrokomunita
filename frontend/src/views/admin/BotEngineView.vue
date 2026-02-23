<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { useBotEngineStore } from '@/stores/botEngine'
import { useToast } from '@/composables/useToast'

const store = useBotEngineStore()
const toast = useToast()

const { sources, runsPage, runItemsPage, filters, loadingSources, loadingRuns, loadingRunItems } = storeToRefs(store)
const selectedRun = ref(null)
const selectedPreviewItem = ref(null)
const publishAllLimit = ref(10)

const filterForm = ref({
  sourceKey: '',
  status: '',
  date_from: '',
  date_to: '',
  per_page: 20,
})

const runs = computed(() => (Array.isArray(runsPage.value?.data) ? runsPage.value.data : []))
const runsMeta = computed(() => runsPage.value?.meta || null)
const runItems = computed(() => (Array.isArray(runItemsPage.value?.data) ? runItemsPage.value.data : []))
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

const sourceOptions = computed(() => {
  return Array.isArray(sources.value)
    ? sources.value.map((source) => String(source?.key || '')).filter((key) => key !== '')
    : []
})

function toErrorMessage(error, fallbackMessage) {
  const status = Number(error?.response?.status || 0)
  const retryAfter = Number(error?.response?.data?.retry_after || 0)
  const baseMessage = error?.response?.data?.message || error?.userMessage || error?.message || fallbackMessage

  if (status === 429 && retryAfter > 0) {
    return `${baseMessage} Retry in ${retryAfter}s.`
  }

  return (
    baseMessage
  )
}

function toStatNumber(value) {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

function statsSummary(stats) {
  const source = stats && typeof stats === 'object' ? stats : {}

  return [
    `fetched ${toStatNumber(source.fetched_count)}`,
    `new ${toStatNumber(source.new_count)}`,
    `dupes ${toStatNumber(source.dupes_count)}`,
    `published ${toStatNumber(source.published_count)}`,
    `skipped ${toStatNumber(source.skipped_count)}`,
    `failed ${toStatNumber(source.failed_count)}`,
  ].join(' | ')
}

function formatDateTime(value) {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleString(undefined, {
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
  if (value === true) return 'yes'
  if (value === false) return 'no'
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

function syncFilterFormFromStore() {
  filterForm.value = {
    sourceKey: String(filters.value?.sourceKey || ''),
    status: String(filters.value?.status || ''),
    date_from: String(filters.value?.date_from || ''),
    date_to: String(filters.value?.date_to || ''),
    per_page: Number(filters.value?.per_page) || 20,
  }
}

async function loadSources() {
  try {
    await store.fetchSources()
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to load bot sources.'))
  }
}

async function loadRuns(params = {}) {
  try {
    await store.fetchRuns(params)
    syncFilterFormFromStore()
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to load bot runs.'))
  }
}

async function initialize() {
  await Promise.all([loadSources(), loadRuns()])
}

async function applyRunsFilters() {
  await loadRuns({
    ...filterForm.value,
    page: 1,
  })
}

async function resetRunsFilters() {
  const defaults = store.resetFilters()
  filterForm.value = {
    sourceKey: defaults.sourceKey,
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

    toast.success(`${String(result.status || 'unknown').toUpperCase()} | ${statsSummary(result.stats)}`)

    await Promise.all([loadSources(), loadRuns()])
  } catch (error) {
    toast.error(toErrorMessage(error, 'Bot run failed.'))
  }
}

async function dryRun(sourceKey) {
  await runNow(sourceKey, 'dry')
}

async function openRunDetail(run) {
  selectedRun.value = run

  try {
    await store.fetchItemsForRun(run?.id, { page: 1, per_page: 20 })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to load run items.'))
  }
}

function closeRunDetail() {
  selectedRun.value = null
  selectedPreviewItem.value = null
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
    toast.error(toErrorMessage(error, 'Failed to load run items.'))
  }
}

async function publishItem(item) {
  if (!item?.id || !canPublishItem(item)) return

  try {
    const response = await store.publishItem(item.id, { force: false })
    if (response?.already_published) {
      toast.info('Item is already published.')
    } else {
      toast.success('Item published.')
    }

    await store.fetchItemsForRun(selectedRun.value?.id, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to publish item.'))
  }
}

async function publishAllForRun() {
  const runId = Number(selectedRun.value?.id || 0)
  if (!Number.isInteger(runId) || runId <= 0) return

  const limit = Number(publishAllLimit.value)
  const normalizedLimit = Number.isInteger(limit) && limit > 0 ? limit : 10

  try {
    const response = await store.publishRun(runId, { publish_limit: normalizedLimit })
    const publishedCount = Number(response?.published_count || 0)
    const skippedCount = Number(response?.skipped_count || 0)
    const failedCount = Number(response?.failed_count || 0)

    toast.success(
      `Published ${publishedCount} item(s). Skipped ${skippedCount}, failed ${failedCount}.`,
    )

    await store.fetchItemsForRun(runId, {
      page: runItemsMeta.value?.current_page || 1,
      per_page: runItemsMeta.value?.per_page || 20,
    })
  } catch (error) {
    toast.error(toErrorMessage(error, 'Failed to publish run items.'))
  }
}

function statusClass(status) {
  const normalized = String(status || '').toLowerCase()
  if (normalized === 'success') return 'statusBadge statusBadge--success'
  if (normalized === 'partial') return 'statusBadge statusBadge--partial'
  if (normalized === 'failed') return 'statusBadge statusBadge--failed'
  return 'statusBadge'
}

onMounted(async () => {
  syncFilterFormFromStore()
  await initialize()
})
</script>

<template>
  <AdminPageShell title="Bot Engine" subtitle="Run bot sources manually and inspect run history.">
    <section class="card">
      <header class="sectionHeader">
        <div>
          <h2 class="sectionTitle">Sources</h2>
          <p class="sectionSubtitle">Registered bot sources with manual run action.</p>
        </div>
        <button type="button" class="ghostBtn" :disabled="loadingSources" @click="loadSources">
          Refresh
        </button>
      </header>

      <div class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>key</th>
              <th>bot_identity</th>
              <th>source_type</th>
              <th>is_enabled</th>
              <th>last_run_at</th>
              <th class="alignRight">actions</th>
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
            <tr v-else-if="sources.length === 0">
              <td colspan="6" class="emptyCell">No sources available.</td>
            </tr>
            <tr v-else v-for="source in sources" :key="source.id || source.key">
              <td><code>{{ source.key }}</code></td>
              <td>{{ source.bot_identity || '-' }}</td>
              <td>{{ source.source_type || '-' }}</td>
              <td>
                <span class="statusBadge" :class="source.is_enabled ? 'statusBadge--success' : 'statusBadge--muted'">
                  {{ source.is_enabled ? 'enabled' : 'disabled' }}
                </span>
              </td>
              <td>{{ formatDateTime(source.last_run_at) }}</td>
              <td class="alignRight">
                <div class="inlineActions inlineActions--end">
                  <button
                    type="button"
                    class="runBtn"
                    :disabled="!source.is_enabled || store.isSourceRunning(source.key)"
                    @click="runNow(source.key, 'auto')"
                  >
                    {{ store.isSourceRunning(source.key) ? 'Running...' : 'Run now' }}
                  </button>
                  <button
                    type="button"
                    class="ghostBtn"
                    :disabled="!source.is_enabled || store.isSourceRunning(source.key)"
                    @click="dryRun(source.key)"
                  >
                    Dry run
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <header class="sectionHeader">
        <div>
          <h2 class="sectionTitle">Runs</h2>
          <p class="sectionSubtitle">Filter and inspect historical bot runs.</p>
        </div>
        <button type="button" class="ghostBtn" :disabled="loadingRuns" @click="loadRuns()">
          Refresh
        </button>
      </header>

      <form class="filters" @submit.prevent="applyRunsFilters">
        <label class="filterField">
          <span>sourceKey</span>
          <select v-model="filterForm.sourceKey">
            <option value="">All</option>
            <option v-for="sourceKey in sourceOptions" :key="sourceKey" :value="sourceKey">
              {{ sourceKey }}
            </option>
          </select>
        </label>

        <label class="filterField">
          <span>status</span>
          <select v-model="filterForm.status">
            <option value="">All</option>
            <option value="success">success</option>
            <option value="partial">partial</option>
            <option value="failed">failed</option>
          </select>
        </label>

        <label class="filterField">
          <span>date_from</span>
          <input v-model="filterForm.date_from" type="date" />
        </label>

        <label class="filterField">
          <span>date_to</span>
          <input v-model="filterForm.date_to" type="date" />
        </label>

        <label class="filterField">
          <span>per_page</span>
          <select v-model.number="filterForm.per_page">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
        </label>

        <div class="filterActions">
          <button type="submit" class="runBtn" :disabled="loadingRuns">Apply</button>
          <button type="button" class="ghostBtn" :disabled="loadingRuns" @click="resetRunsFilters">Reset</button>
        </div>
      </form>

      <div class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>started_at</th>
              <th>source_key</th>
              <th>status</th>
              <th>stats summary</th>
              <th class="alignRight">actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loadingRuns">
              <tr v-for="index in 6" :key="`runs-skeleton-${index}`">
                <td colspan="5">
                  <div class="skeletonRow"></div>
                </td>
              </tr>
            </template>
            <tr v-else-if="runs.length === 0">
              <td colspan="5" class="emptyCell">No runs found for selected filters.</td>
            </tr>
            <tr v-else v-for="run in runs" :key="run.id">
              <td>{{ formatDateTime(run.started_at) }}</td>
              <td><code>{{ run.source_key || '-' }}</code></td>
              <td>
                <span :class="statusClass(run.status)">
                  {{ run.status || 'unknown' }}
                </span>
              </td>
              <td>{{ statsSummary(run.stats) }}</td>
              <td class="alignRight">
                <button type="button" class="ghostBtn" @click="openRunDetail(run)">Detail</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="runsMeta" class="pagination">
        <div class="paginationInfo">
          Page {{ runsMeta.current_page }} / {{ runsMeta.last_page }} (total {{ runsMeta.total }})
        </div>
        <div class="paginationActions">
          <button type="button" class="ghostBtn" :disabled="!canPrevPage || loadingRuns" @click="goToPage((runsMeta.current_page || 1) - 1)">
            Prev
          </button>
          <button type="button" class="ghostBtn" :disabled="!canNextPage || loadingRuns" @click="goToPage((runsMeta.current_page || 1) + 1)">
            Next
          </button>
        </div>
      </footer>
    </section>

    <teleport to="body">
      <div v-if="selectedRun" class="modalBackdrop" @click.self="closeRunDetail">
        <article class="modalCard" role="dialog" aria-modal="true" aria-label="Bot run detail">
          <header class="modalHeader">
            <h3>Run detail</h3>
            <button type="button" class="ghostBtn" @click="closeRunDetail">Close</button>
          </header>

          <dl class="detailGrid">
            <div>
              <dt>source_key</dt>
              <dd><code>{{ selectedRun.source_key || '-' }}</code></dd>
            </div>
            <div>
              <dt>started_at</dt>
              <dd>{{ formatDateTime(selectedRun.started_at) }}</dd>
            </div>
            <div>
              <dt>finished_at</dt>
              <dd>{{ formatDateTime(selectedRun.finished_at) }}</dd>
            </div>
            <div>
              <dt>status</dt>
              <dd>
                <span :class="statusClass(selectedRun.status)">
                  {{ selectedRun.status || 'unknown' }}
                </span>
              </dd>
            </div>
          </dl>

          <div class="detailBlock">
            <h4>stats</h4>
            <pre>{{ formatStatsJson(selectedRun.stats) }}</pre>
          </div>

          <div class="detailBlock">
            <h4>error_text</h4>
            <p>{{ selectedRun.error_text || '-' }}</p>
          </div>

          <div class="detailBlock">
            <div class="detailBlockHeader">
              <h4>items</h4>
              <div class="inlineActions">
                <label class="inlineField">
                  <span>Limit</span>
                  <input v-model.number="publishAllLimit" type="number" min="1" max="100" />
                </label>
                <button
                  type="button"
                  class="runBtn"
                  :disabled="store.isRunPublishing(selectedRun?.id)"
                  @click="publishAllForRun"
                >
                  {{ store.isRunPublishing(selectedRun?.id) ? 'Publishing...' : 'Publish all' }}
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="loadingRunItems"
                  @click="goToItemsPage(1)"
                >
                  Refresh items
                </button>
              </div>
            </div>

            <div class="tableWrap">
              <table class="table table--compact">
                <thead>
                  <tr>
                    <th>stable_key</th>
                    <th>publish_status</th>
                    <th>translation_status</th>
                    <th>post_id</th>
                    <th>used_translation</th>
                    <th>skip_reason</th>
                    <th>fetched_at</th>
                    <th class="alignRight">actions</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-if="loadingRunItems">
                    <tr v-for="index in 4" :key="`items-skeleton-${index}`">
                      <td colspan="8">
                        <div class="skeletonRow"></div>
                      </td>
                    </tr>
                  </template>
                  <tr v-else-if="runItems.length === 0">
                    <td colspan="8" class="emptyCell">No items found for this run.</td>
                  </tr>
                  <tr v-else v-for="item in runItems" :key="item.id || item.stable_key">
                    <td>
                      <code :title="item.stable_key">{{ formatStableKey(item.stable_key) }}</code>
                    </td>
                    <td>
                      <span :class="itemStatusClass(item.publish_status)">
                        {{ item.publish_status || 'unknown' }}
                      </span>
                    </td>
                    <td>
                      <span :class="itemStatusClass(item.translation_status)">
                        {{ item.translation_status || 'unknown' }}
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
                        <button type="button" class="ghostBtn" @click="openItemPreview(item)">Preview</button>
                        <button
                          type="button"
                          class="runBtn"
                          :disabled="!canPublishItem(item) || store.isItemPublishing(item.id)"
                          @click="publishItem(item)"
                        >
                          {{ store.isItemPublishing(item.id) ? 'Publishing...' : 'Publish' }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <footer v-if="runItemsMeta" class="pagination pagination--inner">
              <div class="paginationInfo">
                Page {{ runItemsMeta.current_page }} / {{ runItemsMeta.last_page }} (total {{ runItemsMeta.total }})
              </div>
              <div class="paginationActions">
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="!canPrevItemsPage || loadingRunItems"
                  @click="goToItemsPage((runItemsMeta.current_page || 1) - 1)"
                >
                  Prev
                </button>
                <button
                  type="button"
                  class="ghostBtn"
                  :disabled="!canNextItemsPage || loadingRunItems"
                  @click="goToItemsPage((runItemsMeta.current_page || 1) + 1)"
                >
                  Next
                </button>
              </div>
            </footer>
          </div>
        </article>
      </div>

      <div v-if="selectedPreviewItem" class="modalBackdrop modalBackdrop--inner" @click.self="closeItemPreview">
        <article class="modalCard modalCard--preview" role="dialog" aria-modal="true" aria-label="Bot item preview">
          <header class="modalHeader">
            <h3>Item preview</h3>
            <button type="button" class="ghostBtn" @click="closeItemPreview">Close</button>
          </header>

          <dl class="detailGrid detailGrid--single">
            <div>
              <dt>source link</dt>
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
            <h4>status</h4>
            <div class="inlineActions">
              <span :class="itemStatusClass(selectedPreviewItem.publish_status)">
                publish: {{ selectedPreviewItem.publish_status || 'unknown' }}
              </span>
              <span :class="itemStatusClass(selectedPreviewItem.translation_status)">
                translation: {{ selectedPreviewItem.translation_status || 'unknown' }}
              </span>
            </div>
          </div>

          <div class="detailBlock">
            <h4>translation preview</h4>
            <p>{{ selectedPreviewItem.title || '-' }}</p>
            <p>{{ selectedPreviewItem.content || '-' }}</p>
          </div>

          <div class="detailBlock">
            <h4>originál</h4>
            <p>{{ selectedPreviewItem.title_original || '-' }}</p>
            <p>{{ selectedPreviewItem.content_original || '-' }}</p>
          </div>

          <div class="detailBlock">
            <h4>preklad</h4>
            <p>{{ selectedPreviewItem.title_translated || '-' }}</p>
            <p>{{ selectedPreviewItem.content_translated || '-' }}</p>
          </div>
        </article>
      </div>
    </teleport>
  </AdminPageShell>
</template>

<style scoped>
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
.ghostBtn {
  border-radius: 9px;
  padding: 7px 11px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
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

.runBtn:disabled,
.ghostBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.inlineActions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.inlineActions--end {
  justify-content: flex-end;
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

.filters {
  display: grid;
  grid-template-columns: repeat(5, minmax(140px, 1fr)) auto;
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

.filterActions {
  display: flex;
  align-items: flex-end;
  gap: 8px;
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
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
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
  min-width: 680px;
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
  }
}

@media (max-width: 680px) {
  .detailGrid {
    grid-template-columns: 1fr;
  }

  .sectionHeader {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
