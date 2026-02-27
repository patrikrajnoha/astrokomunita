<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  getCrawlRuns,
  getEventTranslationHealth,
  getEventSources,
  purgeEventSources,
  runEventSourceCrawl,
  updateEventSource,
} from '@/services/api/admin/eventSources'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const toast = useToast()

const loading = ref(false)
const error = ref('')
const runningSelected = ref(false)
const purging = ref(false)
const runningByKey = ref({})
const purgeDryRun = ref(true)
const purgeModalOpen = ref(false)
const purgeConfirmInput = ref('')

const sources = ref([])
const selectedKeys = ref([])
const recentRuns = ref([])
const latestRunBySourceKey = ref({})

const yearTouched = ref(false)
const year = ref(new Date().getFullYear())
const activeOps = ref(0)
const progressValue = ref(0)
let progressIntervalId = null
const translationHealth = ref(null)
const translationHealthLoading = ref(false)
let translationPollId = null

const supportedSelectedKeys = computed(() => {
  const selectedSet = new Set(selectedKeys.value.map((key) => normalizeSourceKey(key)))

  return sources.value
    .filter((source) => selectedSet.has(normalizeSourceKey(source.key)))
    .filter((source) => Boolean(source?.manual_run_supported) && Boolean(source?.is_enabled))
    .map((source) => normalizeSourceKey(source.key))
})

const canRunSelected = computed(() => {
  return !runningSelected.value && supportedSelectedKeys.value.length > 0
})

const isBusy = computed(() => activeOps.value > 0)

const progressLabel = computed(() => {
  if (runningSelected.value) return 'Prebieha crawling vybranych zdrojov...'
  if (purging.value) return 'Prebieha mazanie crawlnutych dat...'
  if (isBusy.value) return 'Prebieha nacitavanie...'
  return ''
})

const translationPendingCount = computed(() => Number(translationHealth.value?.pending_candidates_total || 0))

const translationQueuedJobs = computed(() => Number(translationHealth.value?.queue?.queued_event_translation_jobs || 0))

const translationCounts = computed(() => translationHealth.value?.counts_24h || {})

const translationProgressPercent = computed(() => {
  const done = Number(translationCounts.value?.done || 0)
  const failed = Number(translationCounts.value?.failed || 0)
  const pending = Number(translationCounts.value?.pending || 0)
  const total = done + failed + pending
  if (total <= 0) return 0
  return Math.max(0, Math.min(100, Math.round(((done + failed) / total) * 100)))
})

const translationIsActive = computed(() => {
  return translationPendingCount.value > 0 || translationQueuedJobs.value > 0
})

const translationProgressLabel = computed(() => {
  if (!translationIsActive.value) return 'Preklad udalosti momentalne nebezi.'
  return `Prekladaju sa udalosti... pending ${translationPendingCount.value}, vo fronte ${translationQueuedJobs.value}.`
})

const purgeTargetKeys = computed(() => {
  const selectedSet = new Set(selectedKeys.value.map((key) => normalizeSourceKey(key)))

  return sources.value
    .filter((source) => selectedSet.size === 0 || selectedSet.has(normalizeSourceKey(source.key)))
    .filter((source) => Boolean(source?.manual_run_supported))
    .map((source) => normalizeSourceKey(source.key))
})

function normalizeSourceKey(value) {
  return String(value || '').trim().toLowerCase()
}

function sourceLabel(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'AstroPixels'
  if (key === 'imo') return 'IMO'
  if (key === 'nasa_watch_the_skies') return 'NASA WTS'
  if (key === 'nasa') return 'NASA'
  return key || '-'
}

function sourceToneClass(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'sourceBadge--astropixels'
  if (key === 'imo') return 'sourceBadge--imo'
  if (key === 'nasa' || key === 'nasa_watch_the_skies') return 'sourceBadge--nasa'
  return 'sourceBadge--generic'
}

function isSourceSupported(source) {
  return Boolean(source?.manual_run_supported)
}

function sourceStatusLabel(source) {
  if (!isSourceSupported(source)) return 'Nepodporovane'
  return source?.is_enabled ? 'Zapnute' : 'Vypnute'
}

function sourceStatusTone(source) {
  if (!isSourceSupported(source)) return 'muted'
  return source?.is_enabled ? 'success' : 'muted'
}

function runStatusTone(status) {
  const value = String(status || '').toLowerCase()
  if (value === 'success') return 'success'
  if (value === 'running' || value === 'processing') return 'warning'
  if (value === 'failed' || value === 'error') return 'danger'
  if (value === 'never') return 'muted'
  return 'muted'
}

function formatDate(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function toCount(value) {
  const n = Number(value)
  return Number.isFinite(n) && n >= 0 ? n : 0
}

function runCounters(run) {
  if (!run) {
    return {
      fetched: 0,
      created: 0,
      updated: 0,
      skipped: 0,
    }
  }

  return {
    fetched: toCount(run.fetched_count),
    created: toCount(run.created_candidates_count),
    updated: toCount(run.updated_candidates_count),
    skipped: toCount(run.skipped_duplicates_count),
  }
}

function runTranslation(run) {
  const summary = run?.translation || {}
  const breakdown = summary?.done_breakdown || {}

  return {
    total: toCount(summary.total),
    done: toCount(summary.done),
    failed: toCount(summary.failed),
    pending: toCount(summary.pending),
    both: toCount(breakdown.both),
    titleOnly: toCount(breakdown.title_only),
    descriptionOnly: toCount(breakdown.description_only),
    withoutText: toCount(breakdown.without_text),
  }
}

function runTranslationModeLabel(run) {
  const t = runTranslation(run)

  if (t.done <= 0) {
    return t.pending > 0 ? 'cakajuce' : 'zatial nic'
  }

  if (t.both > 0 && t.titleOnly === 0 && t.descriptionOnly === 0 && t.withoutText === 0) {
    return 'title+popis'
  }

  if (t.titleOnly > 0 && t.both === 0 && t.descriptionOnly === 0) {
    return 'iba title'
  }

  if (t.descriptionOnly > 0 && t.both === 0 && t.titleOnly === 0) {
    return 'iba popis'
  }

  return 'mix'
}

function isRunTranslationFullyCorrect(run) {
  const t = runTranslation(run)
  if (t.total <= 0) return false
  if (t.failed > 0 || t.pending > 0) return false
  if (t.done !== t.total) return false
  if (t.withoutText > 0) return false
  if (t.titleOnly > 0 || t.descriptionOnly > 0) return false
  return true
}

function runTranslationQualityLabel(run) {
  const t = runTranslation(run)
  if (t.total <= 0) return 'Nehodnotene'
  return isRunTranslationFullyCorrect(run) ? 'Preklad OK' : 'Problem'
}

function runTranslationQualityTone(run) {
  const t = runTranslation(run)
  if (t.total <= 0) return 'muted'
  return isRunTranslationFullyCorrect(run) ? 'success' : 'danger'
}

function findLatestRunForSource(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  return latestRunBySourceKey.value[key] || null
}

function runStatusLabel(run) {
  if (!run) return 'Nikdy'
  const status = String(run.status || '').trim()
  return status !== '' ? status : 'Nezname'
}

function isSourceCheckboxDisabled(source) {
  return runningSelected.value || !source?.is_enabled || !isSourceSupported(source)
}

function isRowRunDisabled(source) {
  const key = normalizeSourceKey(source?.key)
  return runningSelected.value || Boolean(runningByKey.value[key]) || !source?.is_enabled || !isSourceSupported(source)
}

function rowRunDisabledReason(source) {
  if (!isSourceSupported(source)) {
    return 'Nepodporovane v MVP'
  }

  if (!source?.is_enabled) {
    return 'Najprv zapni zdroj'
  }

  return ''
}

function beginOperation() {
  activeOps.value += 1
  if (progressIntervalId !== null) return
  progressValue.value = 8
  progressIntervalId = window.setInterval(() => {
    if (progressValue.value < 92) {
      progressValue.value += Math.max(1, Math.floor((100 - progressValue.value) / 14))
    }
  }, 220)
}

function endOperation() {
  activeOps.value = Math.max(0, activeOps.value - 1)
  if (activeOps.value > 0) return
  if (progressIntervalId !== null) {
    window.clearInterval(progressIntervalId)
    progressIntervalId = null
  }
  progressValue.value = 100
  window.setTimeout(() => {
    if (activeOps.value === 0) {
      progressValue.value = 0
    }
  }, 200)
}

async function load() {
  beginOperation()
  loading.value = true
  error.value = ''

  try {
    const [sourcesRes, runsRes] = await Promise.all([
      getEventSources(),
      getCrawlRuns({ per_page: 10 }),
    ])

    const sourceList = Array.isArray(sourcesRes?.data?.data) ? sourcesRes.data.data : []
    sources.value = sourceList

    const runList = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    recentRuns.value = runList

    const latestByKey = {}
    for (const run of runList) {
      const key = normalizeSourceKey(run?.source_name)
      if (key === '' || latestByKey[key]) {
        continue
      }
      latestByKey[key] = run
    }
    latestRunBySourceKey.value = latestByKey

    if (!yearTouched.value) {
      const latestYear = Number(runList[0]?.year)
      year.value = Number.isFinite(latestYear) && latestYear >= 2000 ? latestYear : new Date().getFullYear()
    }
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa nacitat crawling data.'
  } finally {
    loading.value = false
    endOperation()
  }
}

async function loadTranslationHealth() {
  translationHealthLoading.value = true
  try {
    const response = await getEventTranslationHealth()
    translationHealth.value = response?.data || null
  } catch {
    // Neriesime toast, je to iba doplnkova diagnostika.
  } finally {
    translationHealthLoading.value = false
  }
}

function startTranslationPoll() {
  if (translationPollId !== null) return
  translationPollId = window.setInterval(() => {
    loadTranslationHealth()
  }, 3500)
}

function stopTranslationPoll() {
  if (translationPollId === null) return
  window.clearInterval(translationPollId)
  translationPollId = null
}

async function toggleSource(source, checked) {
  try {
    await updateEventSource(source.id, { is_enabled: checked })
    source.is_enabled = checked

    const key = normalizeSourceKey(source.key)
    if (!checked) {
      selectedKeys.value = selectedKeys.value.filter((item) => normalizeSourceKey(item) !== key)
    }
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa aktualizovat zdroj.'
  }
}

async function runSelected() {
  if (!canRunSelected.value) {
    return
  }

  runningSelected.value = true
  beginOperation()
  error.value = ''

  try {
    await runEventSourceCrawl({
      source_keys: supportedSelectedKeys.value,
      year: Number(year.value),
    })
    toast.success('Crawl run bol vytvoreny pre vybrane zdroje.')
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Rucne spustenie zlyhalo.'
  } finally {
    runningSelected.value = false
    endOperation()
  }
}

async function purgeCrawledData() {
  if (purging.value) {
    return
  }

  purging.value = true
  beginOperation()
  error.value = ''

  try {
    const response = await purgeEventSources({
      source_keys: purgeTargetKeys.value,
      dry_run: Boolean(purgeDryRun.value),
      confirm: 'delete_crawled_events',
    })

    const deleted = response?.data?.deleted || {}
    const events = Number(deleted.events || 0)
    const candidates = Number(deleted.event_candidates || 0)
    const runs = Number(deleted.crawl_runs || 0)
    const mode = purgeDryRun.value ? 'Dry run:' : 'Vymazane:'
    toast.success(`${mode} udalosti ${events}, kandidati ${candidates}, runy ${runs}.`)

    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Mazanie zlyhalo.'
  } finally {
    purging.value = false
    purgeModalOpen.value = false
    purgeConfirmInput.value = ''
    endOperation()
  }
}

function openPurgeModal() {
  purgeConfirmInput.value = ''
  purgeModalOpen.value = true
}

function closePurgeModal() {
  if (purging.value) return
  purgeModalOpen.value = false
  purgeConfirmInput.value = ''
}

async function runSingleSource(source) {
  const key = normalizeSourceKey(source?.key)
  if (!key || isRowRunDisabled(source)) {
    return
  }

  runningByKey.value = {
    ...runningByKey.value,
    [key]: true,
  }

  beginOperation()
  error.value = ''

  try {
    await runEventSourceCrawl({
      source_keys: [key],
      year: Number(year.value),
    })
    toast.success(`Crawl run bol vytvoreny pre ${sourceLabel(key)}.`)
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Rucne spustenie zlyhalo.'
  } finally {
    runningByKey.value = {
      ...runningByKey.value,
      [key]: false,
    }
    endOperation()
  }
}

function viewRunCandidates(run) {
  const sourceKey = normalizeSourceKey(run?.source_name)

  router.push({
    name: 'admin.event-candidates',
    query: {
      run_id: run?.id != null ? String(run.id) : undefined,
      source_key: sourceKey || undefined,
      source: sourceKey || undefined,
      year: run?.year != null ? String(run.year) : undefined,
    },
  })
}

function openRunDetails(run) {
  if (!run?.id) return

  router.push({
    name: 'admin.crawl-run.detail',
    params: { id: String(run.id) },
  })
}

onMounted(async () => {
  await Promise.all([load(), loadTranslationHealth()])
  startTranslationPoll()
})

onUnmounted(() => {
  stopTranslationPoll()
})
</script>

<template>
  <AdminPageShell title="Crawling" subtitle="Zapni zdroj -> spusti crawling -> skontroluj kandidatov z runu.">
    <div v-if="error" class="alert">{{ error }}</div>
    <section v-if="isBusy || progressValue > 0" class="progressPanel" data-testid="crawl-progress-panel">
      <div class="progressPanel__label">{{ progressLabel }}</div>
      <div class="progressBar">
        <div class="progressBar__fill" :style="{ width: `${progressValue}%` }"></div>
      </div>
    </section>

    <section
      v-if="translationHealthLoading || translationIsActive"
      class="progressPanel"
      data-testid="translation-progress-panel"
    >
      <div class="progressPanel__label">{{ translationProgressLabel }}</div>
      <div class="progressBar progressBar--translation">
        <div class="progressBar__fill progressBar__fill--translation" :style="{ width: `${translationProgressPercent}%` }"></div>
      </div>
      <div class="progressPanel__meta">
        <span>Done: {{ Number(translationCounts.done || 0) }}</span>
        <span>Failed: {{ Number(translationCounts.failed || 0) }}</span>
        <span>Pending: {{ Number(translationCounts.pending || 0) }}</span>
      </div>
    </section>

    <section class="card runPanel">
      <div class="runPanel__head">
        <h2>Panel spustenia</h2>
        <div class="runPanel__meta">Vybranych podporovanych zdrojov: {{ supportedSelectedKeys.length }}</div>
      </div>

      <div class="runPanel__actions">
        <label class="runPanel__field" for="run-year">
          <span>Rok</span>
          <input
            id="run-year"
            v-model.number="year"
            type="number"
            min="2000"
            max="2100"
            :disabled="runningSelected"
            @input="yearTouched = true"
          />
        </label>

        <button
          type="button"
          class="primaryBtn"
          data-testid="run-selected-btn"
          :disabled="!canRunSelected"
          @click="runSelected"
        >
          {{ runningSelected ? 'Spusta sa...' : 'Spustit vybrane' }}
        </button>

        <label class="runPanel__switch" for="purge-dry-run">
          <input id="purge-dry-run" v-model="purgeDryRun" type="checkbox" :disabled="purging" />
          <span>Dry run mazania</span>
        </label>

        <button
          type="button"
          class="dangerBtn"
          data-testid="purge-crawled-btn"
          :disabled="purging"
          @click="openPurgeModal"
        >
          {{ purging ? 'Maze sa...' : 'Vymazat crawlnute udalosti' }}
        </button>
      </div>

      <p class="runPanel__hint">Vytvori crawl run a naimportuje kandidatov. Ciel mazania = vybrane podporovane zdroje (alebo vsetky, ak nic nie je vybrane).</p>
    </section>

    <div v-if="purgeModalOpen" class="modalBackdrop" @click.self="closePurgeModal">
      <div class="modalCard" role="dialog" aria-modal="true" aria-labelledby="purge-modal-title">
        <h3 id="purge-modal-title">Potvrdit mazanie</h3>
        <p class="modalText">
          Toto vymaze crawlnute udalosti, kandidatov a crawl runy pre vybrane podporovane zdroje.
          Pre pokracovanie napis <code>delete_crawled_events</code>.
        </p>
        <input
          v-model="purgeConfirmInput"
          data-testid="purge-confirm-input"
          class="modalInput"
          type="text"
          autocomplete="off"
          :disabled="purging"
        />
        <div class="modalActions">
          <button type="button" class="ghostBtn" data-testid="purge-cancel-btn" :disabled="purging" @click="closePurgeModal">
            Zrusit
          </button>
          <button
            type="button"
            class="dangerBtn"
            data-testid="purge-confirm-btn"
            :disabled="purging || purgeConfirmInput !== 'delete_crawled_events'"
            @click="purgeCrawledData"
          >
            {{ purging ? 'Maze sa...' : (purgeDryRun ? 'Spustit dry run' : 'Vymazat teraz') }}
          </button>
        </div>
      </div>
    </div>

    <section class="card">
      <div class="cardHead">
        <h2>Zdroje</h2>
      </div>

      <div v-if="loading" class="muted">Nacitavam zdroje...</div>
      <div v-else class="tableWrap">
        <table class="table compact">
          <thead>
            <tr>
              <th aria-label="Vyber zdroja">[ ]</th>
              <th>Zdroj</th>
              <th>Stav</th>
              <th>Posledny run</th>
              <th>Pocitadla</th>
              <th>Akcie</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="source in sources" :key="source.id" :data-testid="`source-row-${normalizeSourceKey(source.key)}`">
              <td class="tight">
                <input
                  :id="`source-select-${source.id}`"
                  v-model="selectedKeys"
                  :value="source.key"
                  type="checkbox"
                  :data-testid="`source-select-${normalizeSourceKey(source.key)}`"
                  :disabled="isSourceCheckboxDisabled(source)"
                />
              </td>

              <td>
                <span class="sourceBadge" :class="sourceToneClass(source.key)">{{ sourceLabel(source.key) }}</span>
              </td>

              <td>
                <span class="pill" :class="`pill--${sourceStatusTone(source)}`">{{ sourceStatusLabel(source) }}</span>
              </td>

              <td>
                <div class="stackTiny">
                  <span>{{ formatDate(findLatestRunForSource(source.key)?.started_at) }}</span>
                  <span class="pill" :class="`pill--${runStatusTone(runStatusLabel(findLatestRunForSource(source.key)))}`">
                    {{ runStatusLabel(findLatestRunForSource(source.key)) }}
                  </span>
                </div>
              </td>

              <td>
                <div class="counterRow">
                  <span>F {{ runCounters(findLatestRunForSource(source.key)).fetched }}</span>
                  <span>C {{ runCounters(findLatestRunForSource(source.key)).created }}</span>
                  <span>U {{ runCounters(findLatestRunForSource(source.key)).updated }}</span>
                  <span>S {{ runCounters(findLatestRunForSource(source.key)).skipped }}</span>
                </div>
              </td>

              <td>
                <div class="actionRow">
                  <label :for="`source-enabled-${source.id}`" class="switchLabel">
                    <input
                      :id="`source-enabled-${source.id}`"
                      :checked="source.is_enabled"
                      type="checkbox"
                      :disabled="runningSelected"
                      @change="toggleSource(source, $event.target.checked)"
                    />
                    <span>{{ source.is_enabled ? 'Zap' : 'Vyp' }}</span>
                  </label>

                  <button
                    type="button"
                    class="ghostBtn"
                    :data-testid="`run-source-${normalizeSourceKey(source.key)}`"
                    :disabled="isRowRunDisabled(source)"
                    :title="rowRunDisabledReason(source)"
                    @click="runSingleSource(source)"
                  >
                    {{ runningByKey[normalizeSourceKey(source.key)] ? 'Spusta sa...' : 'Spustit' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <div class="cardHead">
        <h2>Posledne runy</h2>
        <span class="muted">Poslednych 10</span>
      </div>

      <div v-if="recentRuns.length === 0" class="muted">Zatial ziadne runy.</div>
      <div v-else class="tableWrap">
        <table class="table compact">
          <thead>
            <tr>
              <th>Cas</th>
              <th>Zdroj</th>
              <th>Rok</th>
              <th>Stav</th>
              <th>Pocitadla</th>
              <th>Preklad</th>
              <th>Akcia</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="run in recentRuns" :key="run.id">
              <td>{{ formatDate(run.started_at) }}</td>
              <td>
                <span class="sourceBadge" :class="sourceToneClass(run.source_name)">{{ sourceLabel(run.source_name) }}</span>
              </td>
              <td>{{ run.year || '-' }}</td>
              <td>
                <span class="pill" :class="`pill--${runStatusTone(run.status)}`">{{ run.status || 'nezname' }}</span>
              </td>
              <td>
                <div class="counterRow">
                  <span>F {{ runCounters(run).fetched }}</span>
                  <span>C {{ runCounters(run).created }}</span>
                  <span>U {{ runCounters(run).updated }}</span>
                  <span>S {{ runCounters(run).skipped }}</span>
                </div>
              </td>
              <td>
                <div v-if="runTranslation(run).total > 0" class="stackTiny">
                  <span class="pill" :class="`pill--${runTranslationQualityTone(run)}`">
                    {{ runTranslationQualityLabel(run) }}
                  </span>
                  <div class="counterRow">
                    <span>D {{ runTranslation(run).done }}</span>
                    <span>F {{ runTranslation(run).failed }}</span>
                    <span>P {{ runTranslation(run).pending }}</span>
                  </div>
                  <span class="muted">Forma: {{ runTranslationModeLabel(run) }}</span>
                </div>
                <span v-else class="muted">-</span>
              </td>
              <td>
                <div class="actionRow">
                  <button type="button" class="ghostBtn" @click="viewRunCandidates(run)">Kandidati</button>
                  <button type="button" class="ghostBtn" @click="openRunDetails(run)">Detail</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 12px;
  background: rgb(var(--color-bg-rgb) / 0.82);
}

.cardHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.cardHead h2,
.runPanel__head h2 {
  margin: 0;
  font-size: 16px;
}

.runPanel {
  display: grid;
  gap: 8px;
}

.runPanel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.runPanel__meta,
.runPanel__hint,
.muted {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.runPanel__actions {
  display: flex;
  align-items: end;
  gap: 10px;
  flex-wrap: wrap;
}

.runPanel__field {
  display: grid;
  gap: 4px;
  font-size: 12px;
}

.runPanel__field input {
  width: 120px;
}

.runPanel__field input,
.ghostBtn,
.primaryBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 10px;
  padding: 7px 10px;
  background: transparent;
  color: inherit;
}

.primaryBtn {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.dangerBtn {
  border: 1px solid rgb(220 38 38 / 0.35);
  border-radius: 10px;
  padding: 7px 10px;
  background: rgb(220 38 38 / 0.12);
  color: inherit;
}

.ghostBtn:disabled,
.primaryBtn:disabled,
.dangerBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.runPanel__switch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.progressPanel {
  margin-bottom: 10px;
  display: grid;
  gap: 6px;
}

.progressPanel__label {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.progressPanel__meta {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.progressBar {
  width: 100%;
  height: 8px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.18);
  overflow: hidden;
}

.progressBar__fill {
  height: 100%;
  background: linear-gradient(90deg, rgb(14 116 144 / 0.9), rgb(59 130 246 / 0.9));
  transition: width 180ms linear;
}

.progressBar--translation {
  background: rgb(16 185 129 / 0.18);
}

.progressBar__fill--translation {
  background: linear-gradient(90deg, rgb(5 150 105 / 0.9), rgb(22 163 74 / 0.9));
}

.modalBackdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  place-items: center;
  background: rgb(0 0 0 / 0.45);
  padding: 16px;
}

.modalCard {
  width: min(520px, 100%);
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb));
  padding: 14px;
  display: grid;
  gap: 10px;
}

.modalCard h3 {
  margin: 0;
  font-size: 16px;
}

.modalText {
  margin: 0;
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.modalInput {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 10px;
  padding: 8px 10px;
  background: transparent;
  color: inherit;
}

.modalActions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  text-align: left;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 7px 8px;
  vertical-align: middle;
}

.table th {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.table .tight {
  width: 1%;
  white-space: nowrap;
}

.sourceBadge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  background: rgb(var(--color-surface-rgb) / 0.1);
  padding: 2px 8px;
  font-size: 12px;
}

.sourceBadge--astropixels {
  border-color: rgb(30 64 175 / 0.35);
  background: rgb(30 64 175 / 0.12);
}

.sourceBadge--imo {
  border-color: rgb(6 95 70 / 0.35);
  background: rgb(6 95 70 / 0.12);
}

.sourceBadge--nasa {
  border-color: rgb(107 33 168 / 0.35);
  background: rgb(107 33 168 / 0.12);
}

.pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  padding: 2px 8px;
  font-size: 12px;
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.pill--success {
  border-color: rgb(22 163 74 / 0.35);
  background: rgb(22 163 74 / 0.12);
}

.pill--warning {
  border-color: rgb(202 138 4 / 0.35);
  background: rgb(202 138 4 / 0.12);
}

.pill--danger {
  border-color: rgb(220 38 38 / 0.35);
  background: rgb(220 38 38 / 0.12);
}

.stackTiny {
  display: grid;
  gap: 4px;
}

.counterRow {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 12px;
}

.actionRow {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.switchLabel {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.alert {
  margin-bottom: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
  color: rgb(185 28 28);
}

@media (max-width: 900px) {
  .card {
    padding: 10px;
  }

  .runPanel__actions {
    align-items: stretch;
  }
}
</style>
