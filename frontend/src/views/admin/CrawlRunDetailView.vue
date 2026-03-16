<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminSectionHeader from '@/components/admin/AdminSectionHeader.vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getCrawlRun } from '@/services/api/admin/eventSources'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const error = ref('')
const run = ref(null)

const runId = computed(() => Number(route.params.id || 0))
const crawlSourcesRoute = computed(() => ({
  name: 'admin.event-sources',
  query: { ...route.query },
}))

function normalizeSourceKey(value) {
  return String(value || '').trim().toLowerCase()
}

function sourceLabel(value) {
  const key = normalizeSourceKey(value)
  if (key === 'astropixels') return 'AstroPixels'
  if (key === 'imo') return 'IMO'
  if (key === 'nasa_watch_the_skies' || key === 'nasa_wts') return 'NASA WTS'
  if (key === 'nasa') return 'NASA'
  return key || '-'
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

function openRunCandidates() {
  if (!run.value) return

  const sourceKey = normalizeSourceKey(run.value.source_name)

  router.push({
    name: 'admin.event-candidates',
    query: {
      run_id: String(run.value.id),
      source_key: sourceKey || undefined,
      source: sourceKey || undefined,
      year: run.value.year != null ? String(run.value.year) : undefined,
    },
  })
}

async function loadRun() {
  if (!Number.isFinite(runId.value) || runId.value <= 0) {
    error.value = 'Neplatné ID crawl runu.'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response = await getCrawlRun(runId.value)
    run.value = response?.data || null
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Nepodarilo sa načítať crawl run.'
  } finally {
    loading.value = false
  }
}

onMounted(loadRun)
</script>

<template>
  <AdminPageShell title="Detail crawl runu" subtitle="Metadata behu a priamy odkaz na kontrolu kandidatov.">
    <AdminSectionHeader
      section="events"
      title="Detail crawl runu"
      back-label="Spat na crawling"
      :back-to="crawlSourcesRoute"
    />

    <div v-if="error" class="alert">{{ error }}</div>
    <div v-if="loading" class="muted">Načítavam run...</div>

    <template v-else-if="run">
      <section class="card">
        <div class="head">
          <div>
            <h2>#{{ run.id }} - {{ sourceLabel(run.source_name) }}</h2>
            <p class="muted">Rok {{ run.year || '-' }} | Stav {{ run.status || '-' }}</p>
          </div>
        </div>

        <dl class="metaGrid">
          <dt>Spustene</dt>
          <dd>{{ formatDate(run.started_at) }}</dd>

          <dt>Dokoncene</dt>
          <dd>{{ formatDate(run.finished_at) }}</dd>

          <dt>Nacitane</dt>
          <dd>{{ toCount(run.fetched_count) }}</dd>

          <dt>Vytvorene</dt>
          <dd>{{ toCount(run.created_candidates_count) }}</dd>

          <dt>Aktualizovane</dt>
          <dd>{{ toCount(run.updated_candidates_count) }}</dd>

          <dt>Preskocene</dt>
          <dd>{{ toCount(run.skipped_duplicates_count) }}</dd>
        </dl>
      </section>

      <details v-if="run.status === 'failed' && (run.error_summary || run.error_log)" class="card">
        <summary>Chyba</summary>
        <pre class="errorBlock">{{ run.error_summary || run.error_log }}</pre>
      </details>

      <section class="card actionCard">
        <button type="button" class="primaryBtn" data-testid="view-candidates-btn" @click="openRunCandidates">
          Zobrazit kandidatov z tohto behu
        </button>
      </section>
    </template>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 12px;
  background: rgb(var(--color-bg-rgb) / 0.82);
}

.head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.head h2 {
  margin: 0 0 4px;
  font-size: 18px;
}

.metaGrid {
  margin: 12px 0 0;
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 8px 12px;
}

.metaGrid dt {
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.metaGrid dd {
  margin: 0;
}

.primaryBtn,
.ghostBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 10px;
  padding: 9px 12px;
  background: transparent;
  color: inherit;
}

.primaryBtn {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.actionCard {
  display: flex;
  justify-content: flex-start;
}

.errorBlock {
  margin: 10px 0 0;
  white-space: pre-wrap;
  max-height: 280px;
  overflow: auto;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 10px;
  font-size: 12px;
}

.muted {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.alert {
  margin-bottom: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
  color: rgb(185 28 28);
}

@media (max-width: 800px) {
  .head {
    flex-direction: column;
  }

  .metaGrid {
    grid-template-columns: 1fr;
    gap: 4px;
  }
}
</style>
