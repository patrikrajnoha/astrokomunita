<template>
  <section class="mx-auto max-w-5xl space-y-6">
    <header class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.45)] p-6">
      <p class="text-xs uppercase tracking-[0.18em] text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Admin Center</p>
      <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold text-[var(--color-surface)]">AstroBot NASA RSS</h1>
        <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-200">Automatický režim</span>
      </div>
      <p class="mt-3 max-w-3xl text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)]">
        Sync beží automaticky každú hodinu cez queue worker. Tu vieš skontrolovať stav a núdzovo spustiť preklad.
      </p>
    </header>

    <section class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.45)] p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-[var(--color-surface)]">Posledný beh synchronizácie</h2>
          <p class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
            Retencia: max {{ status.keep_max_items ?? 30 }} položiek, max {{ status.keep_max_days ?? 14 }} dní
          </p>
        </div>
        <button
          type="button"
          class="rounded-xl border border-[color:rgb(var(--color-primary-rgb)/0.55)] bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)] disabled:cursor-not-allowed disabled:opacity-70"
          :disabled="loadingSync || loadingStatus"
          @click="syncNow"
        >
          {{ loadingSync ? 'Spúšťam...' : 'Núdzovo spustiť NASA RSS sync' }}
        </button>
      </div>

      <div v-if="loadingStatus" class="mt-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Načítavam stav...</div>

      <div v-else-if="!status.last_run" class="mt-4 rounded-xl border border-dashed border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] p-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)]">
        Zatiaľ nebol zaznamenaný žiadny beh.
      </div>

      <dl v-else class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.55)] p-3">
          <dt class="text-xs uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">Čas</dt>
          <dd class="mt-1 text-sm font-semibold text-[var(--color-surface)]">{{ formatDateTime(status.last_run.finished_at) }}</dd>
        </div>
        <div class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.55)] p-3">
          <dt class="text-xs uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">Nové položky</dt>
          <dd class="mt-1 text-xl font-bold text-[var(--color-surface)]">{{ status.last_run.new_items ?? 0 }}</dd>
        </div>
        <div class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.55)] p-3">
          <dt class="text-xs uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">Publikované</dt>
          <dd class="mt-1 text-xl font-bold text-[var(--color-surface)]">{{ status.last_run.published_items ?? 0 }}</dd>
        </div>
        <div class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.55)] p-3">
          <dt class="text-xs uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">Zmazané</dt>
          <dd class="mt-1 text-xl font-bold text-[var(--color-surface)]">{{ status.last_run.deleted_items ?? 0 }}</dd>
        </div>
        <div class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.55)] p-3">
          <dt class="text-xs uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">Posledná chyba</dt>
          <dd class="mt-1 text-sm font-semibold text-[var(--color-surface)]">{{ status.last_run.error_message || '-' }}</dd>
        </div>
      </dl>
    </section>

    <section class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.45)] p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-[var(--color-surface)]">RSS položky a preklad</h2>
        <div class="flex flex-wrap gap-2">
          <button class="ghostbtn" :disabled="loadingItems || loadingRetranslatePending" @click="loadItems">
            {{ loadingItems ? 'Načítavam...' : 'Obnoviť zoznam' }}
          </button>
          <button class="actionbtn" :disabled="loadingRetranslatePending" @click="retranslatePending">
            {{ loadingRetranslatePending ? 'Queueujem...' : 'Preložiť pending' }}
          </button>
        </div>
      </div>

      <p class="mt-2 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
        Badge zobrazuje `translation_status`; pri `failed` je detail v tooltipe.
      </p>

      <div v-if="loadingItems" class="mt-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Načítavam RSS položky...</div>
      <div v-else-if="items.length === 0" class="mt-4 rounded-xl border border-dashed border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] p-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)]">
        Zatiaľ nie sú dostupné RSS položky.
      </div>

      <ul v-else class="mt-4 space-y-3">
        <li
          v-for="item in items"
          :key="item.id"
          class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.55)] p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
              <span class="rounded-full bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-[var(--color-surface)]">
                {{ item.source || 'rss' }}
              </span>
              <span
                class="rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-wide"
                :class="translationStatusClass(item.translation_status)"
                :title="item.translation_status === 'failed' ? (item.translation_error || 'Preklad zlyhal') : ''"
              >
                {{ item.translation_status || 'pending' }}
              </span>
            </div>
            <button
              class="ghostbtn"
              :disabled="retranslateBusyIds[item.id]"
              @click="retranslateItem(item)"
            >
              {{ retranslateBusyIds[item.id] ? 'Queueujem...' : 'Preložiť znovu' }}
            </button>
          </div>

          <h3 class="mt-2 text-sm font-semibold text-[var(--color-surface)]">{{ item.title }}</h3>
          <p v-if="item.summary" class="mt-1 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)]">{{ truncate(item.summary, 220) }}</p>
          <p v-if="item.translated_summary" class="mt-2 text-sm text-emerald-200/90">{{ truncate(item.translated_summary, 220) }}</p>

          <div class="mt-2 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">
            RSS: {{ formatDateTime(item.published_at) }} · Sync: {{ formatDateTime(item.fetched_at) }}
          </div>
        </li>
      </ul>
    </section>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const loadingStatus = ref(false)
const loadingSync = ref(false)
const loadingItems = ref(false)
const loadingRetranslatePending = ref(false)
const status = ref({
  mode: 'automatic',
  keep_max_items: 30,
  keep_max_days: 14,
  last_run: null,
})
const items = ref([])
const retranslateBusyIds = ref({})

const formatDateTime = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleString('sk-SK')
}

const truncate = (value, max) => {
  if (!value) return ''
  return value.length > max ? `${value.slice(0, max)}...` : value
}

const translationStatusClass = (statusValue) => {
  if (statusValue === 'done') return 'bg-emerald-500/20 text-emerald-200'
  if (statusValue === 'failed') return 'bg-rose-500/20 text-rose-200'
  return 'bg-amber-500/20 text-amber-100'
}

const loadStatus = async () => {
  loadingStatus.value = true
  try {
    const res = await api.get('/admin/astrobot/nasa/status')
    status.value = {
      ...status.value,
      ...(res?.data || {}),
    }
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Nepodarilo sa načítať NASA AstroBot stav.')
  } finally {
    loadingStatus.value = false
  }
}

const loadItems = async () => {
  loadingItems.value = true
  try {
    const res = await api.get('/admin/astrobot/items', {
      params: {
        scope: 'all',
        per_page: 30,
      },
    })
    items.value = res?.data?.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Nepodarilo sa načítať RSS položky.')
  } finally {
    loadingItems.value = false
  }
}

const syncNow = async () => {
  if (loadingSync.value) return
  loadingSync.value = true

  try {
    const res = await api.post('/admin/astrobot/nasa/sync-now')
    const result = res?.data?.result || {}
    toast.success(`Sync hotový: nové ${result.new ?? 0}, publikované ${result.published ?? 0}, zmazané ${result.deleted ?? 0}.`)
    await Promise.all([loadStatus(), loadItems()])
  } catch (err) {
    if (err?.response?.status === 409) {
      toast.warn('Sync už aktuálne beží.')
    } else {
      toast.error(err?.response?.data?.message || 'Núdzový sync zlyhal.')
    }
  } finally {
    loadingSync.value = false
  }
}

const retranslateItem = async (item) => {
  retranslateBusyIds.value = { ...retranslateBusyIds.value, [item.id]: true }
  try {
    await api.post(`/admin/astrobot/rss-items/${item.id}/retranslate`, { force: true })
    toast.success('Preklad bol zaradený do queue.')
    await loadItems()
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Nepodarilo sa zaradiť preklad do queue.')
  } finally {
    const next = { ...retranslateBusyIds.value }
    delete next[item.id]
    retranslateBusyIds.value = next
  }
}

const retranslatePending = async () => {
  loadingRetranslatePending.value = true
  try {
    const res = await api.post('/admin/astrobot/rss-items/retranslate-pending', {
      limit: 100,
      force: false,
    })
    toast.success(`Do queue bolo pridaných ${res?.data?.queued ?? 0} položiek.`)
    await loadItems()
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Nepodarilo sa zaradiť pending preklady do queue.')
  } finally {
    loadingRetranslatePending.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadStatus(), loadItems()])
})
</script>
