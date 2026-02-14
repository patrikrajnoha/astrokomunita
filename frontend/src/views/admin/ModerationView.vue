<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'

const tabs = [
  { id: 'pending', label: 'Pending' },
  { id: 'flagged', label: 'Flagged' },
  { id: 'blocked', label: 'Blocked' },
  { id: 'reviewed', label: 'Reviewed' },
]

const activeTab = ref('pending')
const loading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const items = ref([])
const selectedId = ref(null)
const detail = ref(null)
const note = ref('')

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

    if (!selectedId.value || !items.value.some((item) => item.id === selectedId.value)) {
      selectedId.value = items.value[0].id
    }

    await loadDetail(selectedId.value)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load moderation queue.'
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
    error.value = e?.response?.data?.message || 'Failed to load moderation detail.'
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
    await loadQueue()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update moderation state.'
  } finally {
    actionLoading.value = false
  }
}

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString()
}

function scoreSummary(item) {
  const text = item?.moderation_summary?.text || {}
  const media = item?.moderation_summary?.attachment || {}
  const toxicity = Number(text?.toxicity_score || 0).toFixed(2)
  const hate = Number(text?.hate_score || 0).toFixed(2)
  const nsfw = Number(media?.nsfw_score || 0).toFixed(2)
  return `tox:${toxicity} hate:${hate} nsfw:${nsfw}`
}

watch(activeTab, () => {
  loadQueue()
})

watch(selectedId, (id) => {
  loadDetail(id)
})

onMounted(() => {
  loadQueue()
})
</script>

<template>
  <AdminPageShell title="Moderation" subtitle="Automated queue for posts and attachments.">
    <div v-if="error" class="alert">{{ error }}</div>

    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="tab"
        :class="{ active: activeTab === tab.id }"
        type="button"
        :disabled="loading"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="layout">
      <section class="queue">
        <div v-if="loading" class="hint">Loading moderation queue...</div>
        <div v-else-if="!items.length" class="hint">No items.</div>
        <button
          v-for="item in items"
          :key="item.id"
          class="queueItem"
          :class="{ selected: selectedId === item.id }"
          type="button"
          @click="selectedId = item.id"
        >
          <div class="row">
            <strong>#{{ item.id }}</strong>
            <span class="badge">{{ item.moderation_status }}</span>
          </div>
          <div class="snippet">{{ item.snippet || '-' }}</div>
          <div class="meta">{{ scoreSummary(item) }}</div>
          <div class="meta">{{ formatDate(item.created_at) }}</div>
        </button>
      </section>

      <section class="detail">
        <div v-if="!detail" class="hint">Select an item to inspect.</div>
        <template v-else>
          <div class="detailHeader">
            <h3>Post #{{ detail.post.id }}</h3>
            <span class="badge">{{ detail.post.moderation_status }}</span>
          </div>

          <p class="text">{{ detail.post.content }}</p>

          <img
            v-if="detail.post.attachment_url"
            :src="detail.post.attachment_url"
            alt="attachment"
            class="preview"
          />

          <textarea
            v-model="note"
            class="note"
            rows="3"
            placeholder="Admin note"
          />

          <div class="actions">
            <button class="btn" type="button" :disabled="actionLoading" @click="act('approve')">Approve</button>
            <button class="btn danger" type="button" :disabled="actionLoading" @click="act('reject')">Reject</button>
          </div>

          <h4>Logs</h4>
          <div v-if="!detail.logs?.length" class="hint">No logs yet.</div>
          <div v-for="log in detail.logs" :key="log.id" class="logItem">
            <div class="row">
              <span>{{ log.entity_type }}</span>
              <span class="badge">{{ log.decision }}</span>
            </div>
            <div class="meta">{{ formatDate(log.created_at) }} | {{ log.latency_ms }}ms</div>
            <div class="meta">error: {{ log.error_code || '-' }}</div>
            <pre class="json">{{ JSON.stringify(log.model_versions || {}, null, 2) }}</pre>
          </div>
        </template>
      </section>
    </div>
  </AdminPageShell>
</template>

<style scoped>
.alert {
  border: 1px solid rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  border-radius: 10px;
  padding: 10px;
  margin-bottom: 10px;
  color: var(--color-danger);
}

.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.tab {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  padding: 6px 12px;
  background: transparent;
  color: inherit;
}

.tab.active {
  background: rgb(var(--color-primary-rgb) / 0.2);
  border-color: rgb(var(--color-primary-rgb) / 0.5);
}

.layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 12px;
}

.queue,
.detail {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 10px;
}

.queue {
  max-height: 75vh;
  overflow: auto;
}

.queueItem {
  width: 100%;
  text-align: left;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  padding: 8px;
  margin-bottom: 8px;
  background: transparent;
  color: inherit;
}

.queueItem.selected {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.1);
}

.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.badge {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  font-size: 11px;
  padding: 2px 8px;
  text-transform: uppercase;
}

.snippet {
  margin-top: 6px;
  font-size: 13px;
}

.meta {
  margin-top: 4px;
  font-size: 12px;
  opacity: 0.8;
}

.detailHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.text {
  white-space: pre-wrap;
}

.preview {
  width: 100%;
  max-height: 280px;
  object-fit: contain;
  border-radius: 10px;
  margin-bottom: 10px;
}

.note {
  width: 100%;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  background: transparent;
  color: inherit;
  padding: 8px;
}

.actions {
  margin: 10px 0;
  display: flex;
  gap: 8px;
}

.btn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  background: transparent;
  color: inherit;
  padding: 6px 12px;
}

.btn.danger {
  border-color: rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  color: var(--color-danger);
}

.logItem {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  padding: 8px;
  margin-top: 8px;
}

.json {
  margin: 6px 0 0;
  max-height: 180px;
  overflow: auto;
  font-size: 11px;
  border-radius: 8px;
  padding: 6px;
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.hint {
  opacity: 0.8;
  font-size: 13px;
}

@media (max-width: 960px) {
  .layout {
    grid-template-columns: 1fr;
  }
}
</style>
