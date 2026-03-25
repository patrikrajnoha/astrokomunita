<script setup>
import { nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { formatDateTime, scrollElementIntoView } from './utils'

const props = defineProps({
  selectedQueueId: {
    type: [String, Number],
    default: null,
  },
  initialStatus: {
    type: String,
    default: 'pending',
  },
})

const emit = defineEmits(['changed'])

const route = useRoute()
const router = useRouter()

const tabs = [
  { id: 'pending', label: 'Čakajúce' },
  { id: 'flagged', label: 'Označené' },
  { id: 'blocked', label: 'Blokované' },
  { id: 'reviewed', label: 'Skontrolované' },
]

const queueRef = ref(null)
const activeTab = ref(resolveTab(route.query.queueStatus, props.initialStatus))
const loading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const items = ref([])
const selectedId = ref(null)
const detail = ref(null)
const note = ref('')

function resolveTab(...values) {
  for (const value of values) {
    const normalized = String(value || '').toLowerCase()
    if (tabs.some((tab) => tab.id === normalized)) {
      return normalized
    }
    if (normalized === 'ok') {
      return 'reviewed'
    }
  }
  return 'pending'
}

function isReviewedDetail(payload) {
  return Boolean(payload?.logs?.some((log) => log?.reviewed_by_admin_id))
}

function syncQueryWithState() {
  const nextQuery = {
    ...route.query,
    queueStatus: activeTab.value,
  }

  if ((route.query.queueStatus || '') === activeTab.value) {
    return
  }

  router.replace({ query: nextQuery })
}

async function loadQueue() {
  loading.value = true
  error.value = ''

  try {
    const res = await api.get('/admin/moderation', {
      params: {
        status: activeTab.value,
        per_page: 30,
      },
    })

    items.value = res?.data?.data || []

    if (!items.value.length) {
      selectedId.value = null
      detail.value = null
      return
    }

    const preferredId = props.selectedQueueId || selectedId.value
    const hasPreferred = items.value.some((item) => String(item.id) === String(preferredId))
    selectedId.value = hasPreferred ? preferredId : items.value[0].id

    await loadDetail(selectedId.value)
    await focusSelectedQueue()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať frontu.'
  } finally {
    loading.value = false
  }
}

async function loadDetail(id) {
  if (!id) return

  try {
    const res = await api.get(`/admin/moderation/${id}`)
    detail.value = res.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať detail.'
  }
}

async function resolveSelectedQueue(id) {
  if (!id) return

  try {
    const res = await api.get(`/admin/moderation/${id}`)
    detail.value = res.data
    const resolvedTab = isReviewedDetail(res.data)
      ? 'reviewed'
      : resolveTab(res.data?.post?.moderation_status, activeTab.value)

    if (resolvedTab !== activeTab.value) {
      activeTab.value = resolvedTab
      return
    }

    selectedId.value = id
    await focusSelectedQueue()
  } catch {
    // Ignore deep-link failures and keep current queue state.
  }
}

async function act(action) {
  if (!selectedId.value || actionLoading.value) return

  actionLoading.value = true
  error.value = ''

  try {
    await api.post(`/admin/moderation/${selectedId.value}/action`, {
      action,
      note: note.value || null,
    })

    note.value = ''
    emit('changed')
    await loadQueue()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa uložiť zmenu.'
  } finally {
    actionLoading.value = false
  }
}

function scoreSummary(item) {
  const text = item?.moderation_summary?.text || {}
  const media = item?.moderation_summary?.attachment || {}
  const toxicity = Number(text?.toxicity_score || 0).toFixed(2)
  const hate = Number(text?.hate_score || 0).toFixed(2)
  const nsfw = Number(media?.nsfw_score || 0).toFixed(2)
  return `tox:${toxicity} hate:${hate} nsfw:${nsfw}`
}

async function focusSelectedQueue() {
  if (!selectedId.value) return
  await nextTick()
  const item = queueRef.value?.querySelector?.(`[data-queue-id="${selectedId.value}"]`)
  scrollElementIntoView(item)
}

watch(
  () => route.query.queueStatus,
  (value) => {
    const nextTab = resolveTab(value, props.initialStatus)
    if (nextTab !== activeTab.value) {
      activeTab.value = nextTab
    }
  },
)

watch(activeTab, () => {
  syncQueryWithState()
  loadQueue()
})

watch(selectedId, (id) => {
  loadDetail(id)
  focusSelectedQueue()
})

watch(
  () => props.selectedQueueId,
  (id) => {
    resolveSelectedQueue(id)
  },
  { immediate: true },
)

syncQueryWithState()
loadQueue()
</script>

<template>
  <section class="grid gap-3">
    <div v-if="error" class="rounded-xl bg-danger/10 text-danger px-3 py-2 text-xs">{{ error }}</div>

    <!-- Sub-tabs -->
    <div class="flex gap-1 flex-wrap p-0.5 bg-hover rounded-xl w-fit">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="px-3 py-1.5 rounded-[10px] text-[12px] font-semibold border-0 cursor-pointer transition-colors duration-150 disabled:opacity-50"
        :class="activeTab === tab.id ? 'bg-secondary-btn text-white' : 'bg-transparent text-muted'"
        type="button"
        :disabled="loading"
        @click="activeTab = tab.id"
      >{{ tab.label }}</button>
    </div>

    <!-- Master-detail layout -->
    <div class="queueLayout">

      <!-- List -->
      <section ref="queueRef" class="rounded-xl border border-white/[0.08] overflow-auto max-h-[75vh]">
        <div v-if="loading" class="p-3 text-muted/60 text-xs">Načítavam frontu…</div>
        <div v-else-if="!items.length" class="p-3 text-muted/60 text-xs">Žiadne položky.</div>
        <button
          v-for="item in items"
          :key="item.id"
          class="queueItem w-full text-left px-3 py-2.5 border-b border-white/[0.06] last:border-b-0 cursor-pointer transition-colors"
          :class="String(selectedId) === String(item.id)
            ? 'bg-vivid/10 shadow-[inset_3px_0_0_#0F73FF]'
            : 'hover:bg-hover'"
          :data-queue-id="item.id"
          type="button"
          @click="selectedId = item.id"
        >
          <div class="flex justify-between items-center gap-2 mb-1">
            <strong class="text-[12.5px] tabular-nums">#{{ item.id }}</strong>
            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-secondary-btn text-muted">{{ item.moderation_status }}</span>
          </div>
          <div class="text-[12.5px] text-muted/90 overflow-hidden text-ellipsis whitespace-nowrap">{{ item.snippet || '—' }}</div>
          <div class="mt-1 text-[11px] text-muted/60 font-mono">{{ scoreSummary(item) }}</div>
          <div class="text-[11px] text-muted/50">{{ formatDateTime(item.created_at) }}</div>
        </button>
      </section>

      <!-- Detail -->
      <section class="rounded-xl border border-white/[0.08] p-3 grid gap-3 content-start overflow-auto max-h-[75vh]">
        <div v-if="!detail" class="text-muted/60 text-xs py-2">Vyber položku na kontrolu.</div>
        <template v-else>

          <div class="flex justify-between items-center gap-2">
            <h3 class="m-0 text-[13.5px] font-bold">Príspevok #{{ detail.post.id }}</h3>
            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-secondary-btn text-muted shrink-0">{{ detail.post.moderation_status }}</span>
          </div>

          <p class="m-0 text-[13px] leading-relaxed whitespace-pre-wrap text-muted/90">{{ detail.post.content }}</p>

          <img
            v-if="detail.post.attachment_url"
            :src="detail.post.attachment_url"
            alt="príloha"
            class="w-full max-h-64 object-contain rounded-xl"
          />

          <textarea
            v-model="note"
            class="w-full box-border rounded-xl bg-hover border-0 text-[13px] px-2.5 py-2 resize-y focus:outline-none placeholder:text-muted/40"
            rows="2"
            placeholder="Admin poznámka…"
          />

          <div class="flex gap-2">
            <button
              class="px-4 py-1.5 rounded-xl bg-vivid text-white text-[13px] font-bold border-0 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              type="button"
              :disabled="actionLoading"
              @click="act('approve')"
            >Schváliť</button>
            <button
              class="px-4 py-1.5 rounded-xl bg-danger text-white text-[13px] font-bold border-0 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              type="button"
              :disabled="actionLoading"
              @click="act('reject')"
            >Zamietnuť</button>
          </div>

          <!-- Logs -->
          <div class="pt-2 border-t border-white/[0.07]">
            <p class="m-0 mb-2 text-[10.5px] font-bold uppercase tracking-[0.07em] text-muted/60">Logy</p>
            <div v-if="!detail.logs?.length" class="text-muted/60 text-xs">Zatiaľ bez logov.</div>
            <div
              v-for="log in detail.logs"
              :key="log.id"
              class="rounded-xl bg-hover px-2.5 py-2 mb-1.5 grid gap-1"
            >
              <div class="flex justify-between items-center gap-2">
                <span class="text-xs text-muted/80">{{ log.entity_type }}</span>
                <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-secondary-btn text-muted">{{ log.decision }}</span>
              </div>
              <div class="text-[11px] text-muted/60">{{ formatDateTime(log.created_at) }} · {{ log.latency_ms }}ms</div>
              <div class="text-[11px] text-muted/60">Chyba: {{ log.error_code || '—' }}</div>
              <pre class="m-0 mt-1 text-[10.5px] rounded-lg bg-app px-2 py-1.5 overflow-auto max-h-40 font-mono leading-relaxed">{{ JSON.stringify(log.model_versions || {}, null, 2) }}</pre>
            </div>
          </div>

        </template>
      </section>

    </div>
  </section>
</template>

<style scoped>
.queueLayout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 10px;
  align-items: start;
}

.queueItem {
  background: transparent;
  color: inherit;
}

@media (max-width: 760px) {
  .queueLayout {
    grid-template-columns: 1fr;
  }
}
</style>
