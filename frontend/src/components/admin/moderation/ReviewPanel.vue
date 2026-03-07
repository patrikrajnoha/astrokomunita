<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { formatRelativeTime } from './utils'

const props = defineProps({
  mode: {
    type: String,
    default: 'actionable',
  },
})

const emit = defineEmits(['inspect'])

const loading = ref(false)
const error = ref('')
const sourceFilter = ref('all')
const items = ref([])

const sourceOptions = [
  { id: 'all', label: 'Vsetko' },
  { id: 'report', label: 'Reporty' },
  { id: 'queue', label: 'Fronta' },
]

const filteredItems = computed(() => {
  if (sourceFilter.value === 'all') return items.value
  return items.value.filter((item) => item.kind === sourceFilter.value)
})

const emptyState = computed(() => {
  if (props.mode === 'reviewed') {
    return {
      title: 'Zatial nic',
      description: 'Skontrolovane polozky sa zobrazia tu.',
    }
  }

  return {
    title: 'Zatial nic',
    description: 'Polozky na kontrolu sa zobrazia tu.',
  }
})

async function load() {
  loading.value = true
  error.value = ''

  try {
    const params = { limit: 50 }
    if (props.mode === 'reviewed') {
      params.mode = 'reviewed'
    }

    const res = await api.get('/admin/moderation/review-feed', { params })
    items.value = Array.isArray(res?.data) ? res.data : []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa nacitat prehlad.'
  } finally {
    loading.value = false
  }
}

function sourceLabel(item) {
  return item.kind === 'report' ? 'Nahlasenie' : 'Fronta'
}

function targetSummary(item) {
  const author = item?.target?.author || '-'
  const summary = item?.target?.summary || ''
  return summary ? `${author}: ${summary}` : author
}

watch(
  () => props.mode,
  () => {
    load()
  },
  { immediate: true },
)
</script>

<template>
  <section class="reviewPanel">
    <div class="reviewToolbar">
      <div class="filterPills" role="tablist" aria-label="Filtre zdroja">
        <button
          v-for="option in sourceOptions"
          :key="option.id"
          class="pill"
          :class="{ active: sourceFilter === option.id }"
          type="button"
          @click="sourceFilter = option.id"
        >
          {{ option.label }}
        </button>
      </div>

      <button type="button" class="pill" :disabled="loading" @click="load">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </div>

    <div v-if="error" class="alert">{{ error }}</div>

    <div v-if="loading" class="list">
      <div v-for="index in 4" :key="index" class="skeletonRow" />
    </div>

    <div v-else-if="!filteredItems.length" class="emptyState">
      <div class="emptyTitle">{{ emptyState.title }}</div>
      <div class="emptyDescription">{{ emptyState.description }}</div>
    </div>

    <div v-else class="list">
      <article v-for="item in filteredItems" :key="`${item.kind}-${item.id}`" class="reviewItem">
        <div class="metaRow">
          <span class="sourceBadge">{{ sourceLabel(item) }}</span>
          <span class="timeLabel">{{ formatRelativeTime(item.created_at) }}</span>
        </div>

        <div class="itemLabel">{{ item.label }}</div>
        <div class="itemTarget">{{ targetSummary(item) }}</div>
        <div class="itemReason">{{ item.reason || '-' }}</div>

        <div class="actions">
          <span class="statusBadge">{{ item.status }}</span>
          <button type="button" class="actionBtn" @click="emit('inspect', item)">Skontrolovat</button>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.reviewPanel {
  display: grid;
  gap: 14px;
}

.reviewToolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.filterPills {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.pill,
.actionBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 999px;
  padding: 7px 12px;
  background: transparent;
  color: inherit;
}

.pill.active {
  background: rgb(var(--color-primary-rgb) / 0.16);
  border-color: rgb(var(--color-primary-rgb) / 0.45);
}

.alert {
  border: 1px solid rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  border-radius: 10px;
  padding: 10px;
  color: var(--color-danger);
}

.list {
  display: grid;
  gap: 10px;
}

.reviewItem,
.skeletonRow,
.emptyState {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 14px;
  padding: 12px;
  background: rgb(var(--color-bg-rgb) / 0.35);
}

.skeletonRow {
  min-height: 112px;
  background:
    linear-gradient(90deg, rgb(var(--color-surface-rgb) / 0.05), rgb(var(--color-surface-rgb) / 0.1), rgb(var(--color-surface-rgb) / 0.05));
}

.metaRow,
.actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.sourceBadge,
.statusBadge,
.timeLabel {
  font-size: 12px;
  opacity: 0.82;
}

.sourceBadge,
.statusBadge {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 999px;
  padding: 2px 8px;
  text-transform: uppercase;
}

.itemLabel {
  margin-top: 8px;
  font-weight: 700;
}

.itemTarget,
.itemReason {
  margin-top: 4px;
  font-size: 13px;
  opacity: 0.85;
}

.emptyTitle {
  font-weight: 700;
}

.emptyDescription {
  margin-top: 6px;
  opacity: 0.75;
}
</style>
