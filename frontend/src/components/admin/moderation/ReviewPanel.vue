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
const items = ref([])

const emptyState = computed(() => {
  if (props.mode === 'reviewed') {
    return {
      title: 'Zatiaľ nič',
      description: 'Skontrolované položky sa zobrazia tu.',
    }
  }

  return {
    title: 'Zatiaľ nič',
    description: 'Položky na kontrolu sa zobrazia tu.',
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
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať prehľad.'
  } finally {
    loading.value = false
  }
}

function sourceLabel(item) {
  return item.kind === 'report' ? 'Nahlásenie' : 'Fronta'
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
  <section class="grid gap-3">

    <div v-if="error" class="rounded-xl bg-danger/10 text-danger px-3 py-2 text-xs">{{ error }}</div>

    <!-- Loading skeletons -->
    <div v-if="loading" class="grid gap-2">
      <div
        v-for="index in 4"
        :key="index"
        class="rounded-xl bg-hover/60 min-h-[108px] animate-pulse"
      />
    </div>

    <!-- Empty state -->
    <div v-else-if="!items.length" class="rounded-xl bg-hover px-4 py-8 text-center">
      <p class="m-0 font-bold text-sm">{{ emptyState.title }}</p>
      <p class="m-0 mt-1.5 text-xs text-muted/70">{{ emptyState.description }}</p>
    </div>

    <!-- Items -->
    <div v-else class="grid gap-2">
      <article
        v-for="item in items"
        :key="`${item.kind}-${item.id}`"
        class="rounded-xl bg-hover p-3 grid gap-2"
      >
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <span class="text-[10.5px] font-bold uppercase px-2 py-0.5 rounded-full bg-secondary-btn text-muted">{{ sourceLabel(item) }}</span>
          <span class="text-[11px] text-muted/60">{{ formatRelativeTime(item.created_at) }}</span>
        </div>

        <p class="m-0 text-[13.5px] font-bold leading-snug">{{ item.label }}</p>
        <p class="m-0 text-[12.5px] text-muted/85">{{ targetSummary(item) }}</p>
        <p class="m-0 text-[12.5px] text-muted/70">{{ item.reason || '—' }}</p>

        <div class="flex items-center justify-between gap-2 flex-wrap pt-1 border-t border-white/[0.07]">
          <span class="text-[10.5px] font-bold uppercase px-2 py-0.5 rounded-full bg-secondary-btn text-muted">{{ item.status }}</span>
          <button
            type="button"
            class="px-3 py-1.5 rounded-xl bg-secondary-btn text-muted text-xs font-bold border-0 cursor-pointer hover:text-white transition-colors"
            @click="emit('inspect', item)"
          >Skontrolovať</button>
        </div>
      </article>
    </div>

  </section>
</template>
