<template>
  <section class="mx-auto max-w-7xl space-y-6">
    <header class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.5)] p-5 sm:p-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
          <p class="text-xs uppercase tracking-[0.18em] text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Admin Center</p>
          <h1 class="text-2xl font-bold text-[var(--color-surface)] sm:text-3xl">AstroBot</h1>
          <p class="max-w-2xl text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)]">
            RSS inbox, review a publikovanie na jednom mieste. Filtrovanie, schvalovanie a manualna synchronizacia.
          </p>
          <p v-if="lastSyncedAt" class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Posledna synchronizacia: {{ lastSyncedAt }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.45)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.7)]"
            @click="showSettings = !showSettings"
          >
            Nastavenia
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-[color:rgb(var(--color-primary-rgb)/0.5)] bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:bg-[color:rgb(var(--color-primary-rgb)/0.32)] disabled:cursor-not-allowed disabled:opacity-70"
            :disabled="refreshing"
            @click="refreshRss"
          >
            <LoadingIndicator v-if="refreshing" size="sm" :text="''" :full-width="false" />
            <span>{{ refreshing ? 'Synchronizujem...' : 'Synchronizovat RSS' }}</span>
          </button>
        </div>
      </div>
    </header>

    <section v-if="showSettings" class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.42)] p-4 sm:p-5">
      <SettingsTab />
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
      <article v-for="card in statCards" :key="card.key" class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.16)] bg-[color:rgb(var(--color-bg-rgb)/0.46)] p-4">
        <p class="text-xs uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">{{ card.label }}</p>
        <p class="mt-2 text-2xl font-bold text-[var(--color-surface)]">{{ statsLoading ? '...' : card.count }}</p>
      </article>
    </section>

    <section class="space-y-4 rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.42)] p-4 sm:p-5">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <label class="flex-1">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">Search</span>
          <input
            v-model="searchInput"
            type="text"
            placeholder="Hladat podla nazvu alebo summary"
            class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] px-3 py-2 text-sm text-[var(--color-surface)] outline-none ring-0 placeholder:text-[color:rgb(var(--color-text-secondary-rgb)/0.8)] focus:border-[color:rgb(var(--color-primary-rgb)/0.5)]"
          />
        </label>

        <label class="lg:w-56">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">Status</span>
          <select
            v-model="statusFilter"
            class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] px-3 py-2 text-sm text-[var(--color-surface)] outline-none focus:border-[color:rgb(var(--color-primary-rgb)/0.5)]"
          >
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </label>

        <label class="lg:w-48">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">Sort</span>
          <select
            v-model="sortOrder"
            class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] px-3 py-2 text-sm text-[var(--color-surface)] outline-none focus:border-[color:rgb(var(--color-primary-rgb)/0.5)]"
          >
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
          </select>
        </label>
      </div>

      <div v-if="error" class="flex flex-col gap-2 rounded-xl border border-[color:rgb(var(--color-danger-rgb)/0.45)] bg-[color:rgb(var(--color-danger-rgb)/0.12)] px-4 py-3 text-sm text-[var(--color-surface)] sm:flex-row sm:items-center sm:justify-between">
        <span>{{ error }}</span>
        <button type="button" class="rounded-lg border border-[color:rgb(var(--color-danger-rgb)/0.55)] px-3 py-1.5 text-xs font-semibold" @click="loadItems">Retry</button>
      </div>

      <div v-if="loading" class="space-y-2">
        <div v-for="row in 6" :key="`skeleton-${row}`" class="h-12 animate-pulse rounded-lg bg-[color:rgb(var(--color-text-secondary-rgb)/0.15)]"></div>
      </div>

      <template v-else>
        <div v-if="displayedItems.length === 0" class="rounded-2xl border border-dashed border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.35)] p-8 text-center">
          <p class="text-base font-semibold text-[var(--color-surface)]">Zatial tu nic nie je</p>
          <p class="mt-1 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Skus synchronizovat RSS alebo zmen status filter.</p>
          <button
            type="button"
            class="mt-4 rounded-xl border border-[color:rgb(var(--color-primary-rgb)/0.5)] bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)]"
            @click="refreshRss"
            :disabled="refreshing"
          >
            Synchronizovat RSS
          </button>
        </div>

        <div v-else class="space-y-3">
          <div class="hidden overflow-hidden rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] lg:block">
            <table class="min-w-full border-collapse text-sm">
              <thead class="bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-left text-xs uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">
                <tr>
                  <th class="px-4 py-3">Title</th>
                  <th class="px-4 py-3">Source</th>
                  <th class="px-4 py-3">Published</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in displayedItems" :key="item.id" class="border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.14)]">
                  <td class="px-4 py-3 align-top">
                    <p class="font-semibold text-[var(--color-surface)]">{{ item.title || 'Bez nazvu' }}</p>
                    <p v-if="item.summary" class="mt-1 line-clamp-2 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">{{ item.summary }}</p>
                  </td>
                  <td class="px-4 py-3 align-top text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">{{ item.domain || item.source || 'unknown' }}</td>
                  <td class="px-4 py-3 align-top text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">{{ formatDateTime(itemDate(item)) }}</td>
                  <td class="px-4 py-3 align-top">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="flex justify-end gap-1">
                      <a
                        v-if="item.url"
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-2 py-1 text-xs font-semibold text-[var(--color-surface)]"
                        title="Preview source"
                      >
                        Preview
                      </a>
                      <button
                        type="button"
                        class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-2 py-1 text-xs font-semibold text-[var(--color-surface)]"
                        title="Edit"
                        @click="openEdit(item)"
                      >
                        Edit
                      </button>
                      <button
                        v-if="canPublish(item)"
                        type="button"
                        class="rounded-lg border border-[color:rgb(var(--color-primary-rgb)/0.45)] bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-2 py-1 text-xs font-semibold text-[var(--color-surface)] disabled:opacity-60"
                        title="Publish"
                        :disabled="actionLoading[item.id] === 'publish'"
                        @click="publishItem(item)"
                      >
                        {{ actionLoading[item.id] === 'publish' ? '...' : 'Publish' }}
                      </button>
                      <button
                        v-if="canReject(item)"
                        type="button"
                        class="rounded-lg border border-[color:rgb(var(--color-danger-rgb)/0.45)] px-2 py-1 text-xs font-semibold text-[var(--color-danger)] disabled:opacity-60"
                        title="Reject"
                        :disabled="actionLoading[item.id] === 'reject'"
                        @click="rejectItem(item)"
                      >
                        {{ actionLoading[item.id] === 'reject' ? '...' : 'Reject' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="grid gap-3 lg:hidden">
            <article v-for="item in displayedItems" :key="`mobile-${item.id}`" class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.5)] p-4">
              <div class="flex items-start justify-between gap-2">
                <h3 class="text-sm font-semibold text-[var(--color-surface)]">{{ item.title || 'Bez nazvu' }}</h3>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
              </div>
              <p v-if="item.summary" class="mt-2 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">{{ item.summary }}</p>
              <div class="mt-2 space-y-1 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
                <p>Source: {{ item.domain || item.source || 'unknown' }}</p>
                <p>Date: {{ formatDateTime(itemDate(item)) }}</p>
              </div>
              <details class="mt-3 rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] p-2">
                <summary class="cursor-pointer text-xs font-semibold text-[var(--color-surface)]">Actions</summary>
                <div class="mt-2 flex flex-wrap gap-2">
                  <a
                    v-if="item.url"
                    :href="item.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-2 py-1 text-xs font-semibold text-[var(--color-surface)]"
                  >
                    Preview
                  </a>
                  <button type="button" class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-2 py-1 text-xs font-semibold text-[var(--color-surface)]" @click="openEdit(item)">Edit</button>
                  <button
                    v-if="canPublish(item)"
                    type="button"
                    class="rounded-lg border border-[color:rgb(var(--color-primary-rgb)/0.45)] bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-2 py-1 text-xs font-semibold text-[var(--color-surface)]"
                    :disabled="actionLoading[item.id] === 'publish'"
                    @click="publishItem(item)"
                  >
                    Publish
                  </button>
                  <button
                    v-if="canReject(item)"
                    type="button"
                    class="rounded-lg border border-[color:rgb(var(--color-danger-rgb)/0.45)] px-2 py-1 text-xs font-semibold text-[var(--color-danger)]"
                    :disabled="actionLoading[item.id] === 'reject'"
                    @click="rejectItem(item)"
                  >
                    Reject
                  </button>
                </div>
              </details>
            </article>
          </div>

          <div class="flex flex-col gap-2 rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.45)] p-3 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Strana {{ pagination.current_page }} / {{ pagination.last_page }} ({{ pagination.total }} items)</p>
            <div class="flex gap-2">
              <button
                type="button"
                class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-3 py-1.5 text-xs font-semibold text-[var(--color-surface)] disabled:opacity-50"
                :disabled="pagination.current_page <= 1 || loading"
                @click="goToPage(pagination.current_page - 1)"
              >
                Predchadzajuca
              </button>
              <button
                type="button"
                class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-3 py-1.5 text-xs font-semibold text-[var(--color-surface)] disabled:opacity-50"
                :disabled="pagination.current_page >= pagination.last_page || loading"
                @click="goToPage(pagination.current_page + 1)"
              >
                Dalsia
              </button>
            </div>
          </div>
        </div>
      </template>
    </section>

    <div v-if="editItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click="closeEdit">
      <div class="w-full max-w-2xl rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.25)] bg-[color:rgb(var(--color-bg-rgb)/0.96)] p-5" @click.stop>
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-[var(--color-surface)]">Upravit polozku</h2>
          <button type="button" class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-2 py-1 text-sm" @click="closeEdit">X</button>
        </div>
        <div class="mt-4 space-y-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">Title</span>
            <input v-model="editItem.title" type="text" class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.62)] px-3 py-2 text-sm" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">Summary</span>
            <textarea v-model="editItem.summary" rows="6" class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.62)] px-3 py-2 text-sm"></textarea>
          </label>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-3 py-1.5 text-sm" @click="closeEdit">Zrusit</button>
          <button type="button" class="rounded-lg border border-[color:rgb(var(--color-primary-rgb)/0.45)] bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-3 py-1.5 text-sm font-semibold" @click="saveEdit">Ulozit</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import LoadingIndicator from '@/components/shared/LoadingIndicator.vue'
import SettingsTab from '@/components/admin/astrobot/SettingsTab.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const { confirm, prompt } = useConfirm()
const toast = useToast()

const loading = ref(false)
const statsLoading = ref(false)
const refreshing = ref(false)
const error = ref('')
const showSettings = ref(false)
const lastSyncedAt = ref('')
const items = ref([])
const editItem = ref(null)
const searchInput = ref('')
const search = ref('')
const sortOrder = ref('newest')
const statusFilter = ref('all')
const actionLoading = ref({})
const searchDebounce = ref(null)
const stats = ref({
  needs_review: 0,
  draft: 0,
  published: 0,
  rejected: 0,
  failed: 0,
})

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
})

const statusOptions = [
  { value: 'all', label: 'All' },
  { value: 'needs_review', label: 'Pending' },
  { value: 'draft', label: 'Reviewed' },
  { value: 'published', label: 'Published' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'failed', label: 'Failed' },
]

const statCards = computed(() => [
  { key: 'needs_review', label: 'Pending', count: stats.value.needs_review || 0 },
  { key: 'draft', label: 'Ready', count: stats.value.draft || 0 },
  { key: 'published', label: 'Published', count: stats.value.published || 0 },
  { key: 'failed', label: 'Failed', count: stats.value.failed || 0 },
  { key: 'rejected', label: 'Rejected', count: stats.value.rejected || 0 },
])

const displayedItems = computed(() => {
  if (sortOrder.value === 'newest') return items.value

  return [...items.value].sort((a, b) => {
    const aDate = new Date(itemDate(a) || 0).getTime()
    const bDate = new Date(itemDate(b) || 0).getTime()
    return aDate - bDate
  })
})

const itemDate = (item) => item?.published_at || item?.fetched_at || item?.created_at || null

const formatDateTime = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleString('sk-SK')
}

const statusLabel = (status) => {
  if (!status) return 'Unknown'

  const map = {
    needs_review: 'Pending',
    draft: 'Reviewed',
    published: 'Published',
    rejected: 'Rejected',
    failed: 'Failed',
  }

  return map[status] || status
}

const statusClass = (status) => {
  const map = {
    needs_review: 'bg-amber-500/20 text-amber-200',
    draft: 'bg-sky-500/20 text-sky-200',
    published: 'bg-emerald-500/20 text-emerald-200',
    rejected: 'bg-rose-500/20 text-rose-200',
    failed: 'bg-red-500/20 text-red-200',
  }

  return map[status] || 'bg-[color:rgb(var(--color-text-secondary-rgb)/0.22)] text-[var(--color-surface)]'
}

const canPublish = (item) => item?.status !== 'published'
const canReject = (item) => item?.status !== 'rejected'

const mapItem = (item) => {
  let domain = ''
  try {
    domain = item?.url ? new URL(item.url).hostname : ''
  } catch {
    domain = ''
  }

  return {
    ...item,
    domain,
  }
}

const loadItems = async () => {
  loading.value = true
  error.value = ''

  try {
    const res = await api.get('/admin/astrobot/items', {
      params: {
        scope: 'all',
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
        search: search.value || undefined,
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
      },
    })

    items.value = (res?.data?.data || []).map(mapItem)
    pagination.value = {
      current_page: res?.data?.current_page || 1,
      last_page: res?.data?.last_page || 1,
      per_page: res?.data?.per_page || pagination.value.per_page,
      total: res?.data?.total || 0,
    }
  } catch (err) {
    error.value = err?.response?.data?.message || 'Nepodarilo sa nacitat AstroBot inbox.'
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  statsLoading.value = true

  try {
    const statuses = ['needs_review', 'draft', 'published', 'rejected', 'failed']
    const responses = await Promise.all(
      statuses.map((status) =>
        api.get('/admin/astrobot/items', {
          params: {
            scope: 'all',
            status,
            page: 1,
            per_page: 1,
          },
        }),
      ),
    )

    const nextStats = {}
    statuses.forEach((status, index) => {
      nextStats[status] = Number(responses[index]?.data?.total || 0)
    })

    stats.value = {
      ...stats.value,
      ...nextStats,
    }
  } catch {
    // keep previous values on non-critical stats failure
  } finally {
    statsLoading.value = false
  }
}

const refreshRss = async () => {
  if (refreshing.value) return

  const ok = await confirm({
    title: 'Synchronizovat RSS',
    message: 'Naozaj synchronizovat RSS teraz?',
    confirmText: 'Synchronizovat',
    cancelText: 'Zrusit',
  })

  if (!ok) return

  refreshing.value = true

  try {
    const res = await api.post('/admin/astrobot/sync')
    const result = res?.data?.result || {}
    const serverTime = res?.data?.last_synced_at || res?.data?.server_time || result.synced_at

    if (serverTime) {
      lastSyncedAt.value = formatDateTime(serverTime)
    }

    toast.success(
      `RSS synchronizovane. Added: ${result.added ?? 0}, updated: ${result.updated ?? 0}, published: ${result.published ?? 0}.`,
    )

    pagination.value.current_page = 1
    await Promise.all([loadItems(), loadStats()])
  } catch (err) {
    toast.error(
      err?.response?.status === 429
        ? 'Synchronizacia je limitovana. Skus znova o chvilu.'
        : (err?.response?.data?.message || 'Synchronizacia zlyhala.'),
    )
  } finally {
    refreshing.value = false
  }
}

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  loadItems()
}

const openEdit = (item) => {
  editItem.value = {
    id: item.id,
    title: item.title || '',
    summary: item.summary || '',
  }
}

const closeEdit = () => {
  editItem.value = null
}

const saveEdit = async () => {
  if (!editItem.value?.id) return

  try {
    await api.put(`/admin/astrobot/items/${editItem.value.id}`, {
      title: editItem.value.title,
      summary: editItem.value.summary,
    })

    toast.success('Polozka bola upravena.')
    closeEdit()
    await Promise.all([loadItems(), loadStats()])
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Uprava zlyhala.')
  }
}

const publishItem = async (item) => {
  const ok = await confirm({
    title: 'Publish item',
    message: 'Publikovat tuto polozku?',
    confirmText: 'Publish',
    cancelText: 'Cancel',
  })

  if (!ok) return

  actionLoading.value = {
    ...actionLoading.value,
    [item.id]: 'publish',
  }

  try {
    await api.post(`/admin/astrobot/items/${item.id}/publish`)
    toast.success('Polozka bola publikovana.')
    await Promise.all([loadItems(), loadStats()])
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Publikovanie zlyhalo.')
  } finally {
    const next = { ...actionLoading.value }
    delete next[item.id]
    actionLoading.value = next
  }
}

const rejectItem = async (item) => {
  const note = await prompt({
    title: 'Reject item',
    message: 'Dovod zamietnutia (volitelne):',
    placeholder: 'Napis poznamku',
    confirmText: 'Reject',
    cancelText: 'Cancel',
  })

  if (note === null) return

  actionLoading.value = {
    ...actionLoading.value,
    [item.id]: 'reject',
  }

  try {
    await api.post(`/admin/astrobot/items/${item.id}/reject`, { note })
    toast.success('Polozka bola zamietnuta.')
    await Promise.all([loadItems(), loadStats()])
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Reject zlyhal.')
  } finally {
    const next = { ...actionLoading.value }
    delete next[item.id]
    actionLoading.value = next
  }
}

watch([statusFilter], () => {
  pagination.value.current_page = 1
  loadItems()
})

watch(searchInput, (value) => {
  if (searchDebounce.value) {
    clearTimeout(searchDebounce.value)
  }

  searchDebounce.value = setTimeout(() => {
    search.value = value.trim()
    pagination.value.current_page = 1
    loadItems()
  }, 350)
})

onBeforeUnmount(() => {
  if (searchDebounce.value) {
    clearTimeout(searchDebounce.value)
  }
})

onMounted(async () => {
  await Promise.all([loadItems(), loadStats()])
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>

