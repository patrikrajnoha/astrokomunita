<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import api from '@/services/api'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'

const tabs = [
  { id: 'pending', label: 'Čaká' },
  { id: 'flagged', label: 'Nahlásené' },
  { id: 'blocked', label: 'Blokované' },
  { id: 'reviewed', label: 'Skontrolované' },
]

const activeTab = ref('pending')
const loading = ref(false)
const actionLoading = ref(false)
const healthLoading = ref(false)
const error = ref('')
const items = ref([])
const selectedId = ref(null)
const detail = ref(null)
const note = ref('')
const moderationHealth = ref({
  status: 'checking',
  checkedAt: null,
  device: null,
  error: '',
})

let healthIntervalId = null

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
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať moderačnú frontu.'
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
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať detail moderácie.'
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
    error.value = e?.response?.data?.message || 'Nepodarilo sa aktualizovať stav moderácie.'
  } finally {
    actionLoading.value = false
  }
}

async function loadModerationHealth() {
  healthLoading.value = true

  try {
    const res = await api.get('/admin/moderation/health')
    moderationHealth.value = {
      status: res?.data?.status || 'running',
      checkedAt: res?.data?.checked_at || null,
      device: res?.data?.service?.device || null,
      error: '',
    }
  } catch (e) {
    moderationHealth.value = {
      status: 'down',
      checkedAt: e?.response?.data?.checked_at || null,
      device: null,
      error: e?.response?.data?.error?.message || 'Moderačná služba je nedostupná.',
    }
  } finally {
    healthLoading.value = false
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
  loadModerationHealth()
  loadQueue()
  healthIntervalId = setInterval(() => {
    loadModerationHealth()
  }, 15000)
})

onUnmounted(() => {
  if (healthIntervalId) {
    clearInterval(healthIntervalId)
    healthIntervalId = null
  }
})
</script>

<template>
  <AdminPageShell title="Moderacia" subtitle="Automatická fronta pre príspevky a prílohy.">
    <div class="healthBar">
      <div class="healthState">
        <span class="dot" :class="`is-${moderationHealth.status}`" />
        <strong>
          Moderačná služba:
          {{ moderationHealth.status === 'running' ? 'beží' : moderationHealth.status === 'checking' ? 'kontrolujem...' : 'mimo prevádzky' }}
        </strong>
        <span v-if="moderationHealth.device" class="meta">({{ moderationHealth.device }})</span>
      </div>
      <div class="healthMeta">
        <span class="meta" v-if="moderationHealth.checkedAt">Posledná kontrola: {{ formatDate(moderationHealth.checkedAt) }}</span>
        <button class="tab" type="button" :disabled="healthLoading" @click="loadModerationHealth">
          {{ healthLoading ? 'Kontrolujem...' : 'Obnoviť stav' }}
        </button>
      </div>
    </div>

    <div v-if="moderationHealth.error" class="warn">{{ moderationHealth.error }}</div>
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
        <div v-if="loading" class="hint">Načítavam moderačnú frontu...</div>
        <div v-else-if="!items.length" class="hint">Žiadne položky.</div>
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
        <div v-if="!detail" class="hint">Vyberte polozku na kontrolu.</div>
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
            placeholder="Poznámka admina"
          />

          <div class="actions">
            <button class="btn" type="button" :disabled="actionLoading" @click="act('approve')">Schváliť</button>
            <button class="btn danger" type="button" :disabled="actionLoading" @click="act('reject')">Zamietnuť</button>
          </div>

          <h4>Logy</h4>
          <div v-if="!detail.logs?.length" class="hint">Zatiaľ bez logov.</div>
          <div v-for="log in detail.logs" :key="log.id" class="logItem">
            <div class="row">
              <span>{{ log.entity_type }}</span>
              <span class="badge">{{ log.decision }}</span>
            </div>
            <div class="meta">{{ formatDate(log.created_at) }} | {{ log.latency_ms }}ms</div>
            <div class="meta">chyba: {{ log.error_code || '-' }}</div>
            <pre class="json">{{ JSON.stringify(log.model_versions || {}, null, 2) }}</pre>
          </div>
        </template>
      </section>
    </div>
  </AdminPageShell>
</template>

<style scoped>
.healthBar {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 10px;
  margin-bottom: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.healthState,
.healthMeta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  display: inline-block;
  background: rgb(var(--color-surface-rgb) / 0.5);
}

.dot.is-running {
  background: rgb(34 197 94);
}

.dot.is-checking {
  background: rgb(234 179 8);
}

.dot.is-down {
  background: rgb(var(--color-danger-rgb, 239 68 68));
}

.warn {
  border: 1px solid rgb(234 179 8 / 0.45);
  border-radius: 10px;
  padding: 10px;
  margin-bottom: 10px;
}

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
