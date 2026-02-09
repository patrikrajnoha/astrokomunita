<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import LoadingIndicator from '@/components/shared/LoadingIndicator.vue'

const loading = ref(false)
const error = ref('')
const status = ref('open')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const res = await api.get('/admin/reports', {
      params: { status: status.value, page: page.value, per_page: perPage.value },
    })
    data.value = res.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load reports.'
  } finally {
    loading.value = false
  }
}

function updateRow(updated) {
  if (!data.value || !updated) return
  const rows = data.value.data || []
  const idx = rows.findIndex((r) => r.id === updated.id)
  if (idx >= 0) {
    rows[idx] = { ...rows[idx], ...updated }
  }
}

async function act(report, action) {
  if (!report?.id) return
  const ok = window.confirm(`Confirm ${action}?`)
  if (!ok) return

  loading.value = true
  error.value = ''

  try {
    const res = await api.post(`/admin/reports/${report.id}/${action}`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Action failed.'
  } finally {
    loading.value = false
  }
}

function prevPage() {
  if (!data.value || page.value <= 1) return
  page.value -= 1
  load()
}

function nextPage() {
  if (!data.value || page.value >= data.value.last_page) return
  page.value += 1
  load()
}

watch([status, perPage], () => {
  page.value = 1
  load()
})

onMounted(load)
</script>

<template>
  <div style="max-width: 1100px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
      <div>
        <h1 style="margin:0 0 6px;">Reports</h1>
        <div style="opacity:.8; font-size: 14px;">
          Moderation queue (MVP).
        </div>
      </div>
    </div>

    <div
      style="
        margin-top: 16px;
        padding: 12px;
        border: 1px solid rgb(var(--color-surface-rgb) / .12);
        border-radius: 12px;
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 12px;
      "
    >
      <div style="grid-column: span 3;">
        <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Status</label>
        <select
          v-model="status"
          :disabled="loading"
          style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
        >
          <option value="open">open</option>
          <option value="reviewed">reviewed</option>
          <option value="dismissed">dismissed</option>
          <option value="action_taken">action_taken</option>
        </select>
      </div>

      <div style="grid-column: span 3;">
        <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Per page</label>
        <select
          v-model.number="perPage"
          :disabled="loading"
          style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
        >
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
      </div>
    </div>

    <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
      {{ error }}
    </div>

    <LoadingIndicator :loading="loading" text="Loading..." />

    <div
      v-if="data && !loading"
      style="
        margin-top: 16px;
        border: 1px solid rgb(var(--color-surface-rgb) / .12);
        border-radius: 12px;
        overflow: hidden;
      "
    >
      <table style="width:100%; border-collapse:collapse;">
        <thead style="background: rgb(var(--color-surface-rgb) / .05);">
          <tr>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Date</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Reason</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Reporter</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Author</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Snippet</th>
            <th style="text-align:right; padding:12px; font-size:12px; opacity:.85;">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="r in data.data"
            :key="r.id"
            style="border-top: 1px solid rgb(var(--color-surface-rgb) / .08);"
          >
            <td style="padding:12px; white-space:nowrap;">{{ r.created_at }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ r.reason }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ r.reporter?.name || '-' }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ r.target?.user?.name || '-' }}</td>
            <td style="padding:12px;">
              {{ r.target?.content?.slice(0, 80) || '-' }}
            </td>
            <td style="padding:12px; text-align:right; white-space:nowrap;">
              <button
                @click="act(r, 'hide')"
                :disabled="loading"
                style="padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
              >
                Hide
              </button>
              <button
                @click="act(r, 'delete')"
                :disabled="loading"
                style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
              >
                Delete
              </button>
              <button
                @click="act(r, 'warn')"
                :disabled="loading"
                style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
              >
                Warn
              </button>
              <button
                @click="act(r, 'ban')"
                :disabled="loading"
                style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
              >
                Ban
              </button>
              <button
                @click="act(r, 'dismiss')"
                :disabled="loading"
                style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
              >
                Dismiss
              </button>
            </td>
          </tr>

          <tr v-if="data.data.length === 0">
            <td colspan="6" style="padding:16px; opacity:.8;">
              No reports.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="data"
      style="
        margin-top: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
      "
    >
      <div style="opacity:.85; font-size: 14px;">
        Page {{ data.current_page }} / {{ data.last_page }} (total {{ data.total }})
      </div>

      <div style="display:flex; gap:10px;">
        <button
          @click="prevPage"
          :disabled="loading || page <= 1"
          style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
        >
          Prev
        </button>
        <button
          @click="nextPage"
          :disabled="loading || (data && page >= data.last_page)"
          style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>
