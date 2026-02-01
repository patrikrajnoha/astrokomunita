<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const loading = ref(false)
const error = ref('')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)

function statusLabel(user) {
  if (!user?.is_active) return 'inactive'
  if (user?.is_banned) return 'banned'
  return 'active'
}

function isSelf(user) {
  return auth.user && user && Number(auth.user.id) === Number(user.id)
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const res = await api.get('/admin/users', {
      params: { page: page.value, per_page: perPage.value },
    })
    data.value = res.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load users.'
  } finally {
    loading.value = false
  }
}

function updateRow(updated) {
  if (!data.value || !updated) return
  const rows = data.value.data || []
  const idx = rows.findIndex((u) => u.id === updated.id)
  if (idx >= 0) {
    rows[idx] = { ...rows[idx], ...updated }
  }
}

async function banUser(user) {
  if (!user || isSelf(user)) return
  const ok = window.confirm(`Ban user ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/ban`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Ban failed.'
  }
}

async function unbanUser(user) {
  if (!user || isSelf(user)) return
  const ok = window.confirm(`Unban user ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/unban`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Unban failed.'
  }
}

async function deactivateUser(user) {
  if (!user || isSelf(user) || !user.is_active) return
  const ok = window.confirm(`Deactivate user ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/deactivate`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Deactivate failed.'
  }
}

async function resetProfile(user) {
  if (!user) return
  const ok = window.confirm(`Reset profile for ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reset-profile`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reset profile failed.'
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

onMounted(load)
</script>

<template>
  <div style="max-width: 1100px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
      <div>
        <h1 style="margin:0 0 6px;">Users</h1>
        <div style="opacity:.8; font-size: 14px;">
          Admin user management (MVP).
        </div>
      </div>
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
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Name</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Email</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Role</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Status</th>
            <th style="text-align:right; padding:12px; font-size:12px; opacity:.85;">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="u in data.data"
            :key="u.id"
            style="border-top: 1px solid rgb(var(--color-surface-rgb) / .08);"
          >
            <td style="padding:12px; white-space:nowrap;">{{ u.name }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ u.email }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ u.role }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ statusLabel(u) }}</td>
            <td style="padding:12px; text-align:right; white-space:nowrap;">
              <button
                v-if="!u.is_banned"
                @click="banUser(u)"
                :disabled="loading || isSelf(u)"
                style="padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
              >
                Ban
              </button>
              <button
                v-else
                @click="unbanUser(u)"
                :disabled="loading || isSelf(u)"
                style="padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
              >
                Unban
              </button>
              <button
                @click="deactivateUser(u)"
                :disabled="loading || isSelf(u) || !u.is_active"
                style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
              >
                Deactivate
              </button>
              <button
                @click="resetProfile(u)"
                :disabled="loading"
                style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
              >
                Reset profile
              </button>
            </td>
          </tr>

          <tr v-if="data.data.length === 0">
            <td colspan="5" style="padding:16px; opacity:.8;">
              No users found.
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
