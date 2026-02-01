<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()

const loading = ref(false)
const error = ref('')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const res = await api.get('/admin/events', {
      params: { page: page.value, per_page: perPage.value },
    })
    data.value = res.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load events.'
  } finally {
    loading.value = false
  }
}

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (isNaN(d.getTime())) return String(value)
  return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function openCreate() {
  router.push('/admin/events/create')
}

function openEdit(id) {
  router.push(`/admin/events/${id}/edit`)
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

onMounted(load)
</script>

<template>
  <div style="max-width: 1100px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
      <div>
        <h1 style="margin:0 0 6px;">Events</h1>
        <div style="opacity:.8; font-size: 14px;">
          Admin events list (MVP).
        </div>
      </div>

      <button
        @click="openCreate"
        :disabled="loading"
        style="padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
      >
        Create event
      </button>
    </div>

    <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
      {{ error }}
    </div>

    <div v-if="loading" style="margin-top: 12px; opacity: .85;">
      Loading...
    </div>

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
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Title</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Type</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Start</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Visibility</th>
            <th style="text-align:right; padding:12px; font-size:12px; opacity:.85;">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="ev in data.data"
            :key="ev.id"
            style="border-top: 1px solid rgb(var(--color-surface-rgb) / .08);"
          >
            <td style="padding:12px;">{{ ev.title }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ ev.type }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ formatDate(ev.start_at || ev.starts_at || ev.max_at) }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ ev.visibility === 1 ? 'public' : 'hidden' }}</td>
            <td style="padding:12px; text-align:right;">
              <button
                @click="openEdit(ev.id)"
                style="padding:8px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
              >
                Edit
              </button>
            </td>
          </tr>

          <tr v-if="data.data.length === 0">
            <td colspan="5" style="padding:16px; opacity:.8;">
              No events found.
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
