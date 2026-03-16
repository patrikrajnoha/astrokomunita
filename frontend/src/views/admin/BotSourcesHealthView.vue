<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  clearBotSourceCooldown,
  getBotSources,
  resetBotSourceHealth,
  reviveBotSource,
  updateBotSource,
} from '@/services/api/admin/bots'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const loading = ref(false)
const savingId = ref(null)
const error = ref('')
const rows = ref([])

const filters = reactive({
  q: '',
  enabled: '',
  failing_only: false,
})

const editor = reactive({
  id: null,
  name: '',
  url: '',
})

const hasActiveFilters = computed(() => {
  return (
    String(filters.q || '').trim() !== '' ||
    filters.enabled === '1' ||
    filters.enabled === '0' ||
    filters.failing_only
  )
})

const summaryLine = computed(() => {
  const count = rows.value.length
  if (loading.value) return 'Načítavam zdroje...'
  if (hasActiveFilters.value) return `${count} zdrojov pre aktivne filtre`
  return `${count} zdrojov`
})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatRate(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric < 0) return '-'
  return `${(numeric * 100).toFixed(1)}%`
}

function requestParams() {
  const params = {}
  if (String(filters.q || '').trim() !== '') params.q = String(filters.q).trim()
  if (filters.enabled === '1' || filters.enabled === '0') params.enabled = Number(filters.enabled)
  if (filters.failing_only) params.failing_only = 1
  return params
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await getBotSources(requestParams())
    rows.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nacitanie zdrojov zlyhalo.'
  } finally {
    loading.value = false
  }
}

async function clearFilters() {
  filters.q = ''
  filters.enabled = ''
  filters.failing_only = false
  await load()
}

function startEdit(row) {
  editor.id = row.id
  editor.name = String(row.name || '')
  editor.url = String(row.url || '')
}

function cancelEdit() {
  editor.id = null
  editor.name = ''
  editor.url = ''
}

async function saveEdit() {
  if (!editor.id) return
  savingId.value = editor.id
  try {
    await updateBotSource(editor.id, {
      name: String(editor.name || '').trim() || null,
      url: String(editor.url || '').trim(),
    })
    cancelEdit()
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Ulozenie source konfiguracie zlyhalo.'
  } finally {
    savingId.value = null
  }
}

async function toggleEnabled(row) {
  savingId.value = row.id
  try {
    await updateBotSource(row.id, { is_enabled: !row.is_enabled })
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Aktualizacia source statusu zlyhala.'
  } finally {
    savingId.value = null
  }
}

async function resetHealth(row) {
  savingId.value = row.id
  try {
    await resetBotSourceHealth(row.id)
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reset health zlyhal.'
  } finally {
    savingId.value = null
  }
}

async function clearCooldown(row) {
  savingId.value = row.id
  try {
    await clearBotSourceCooldown(row.id)
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Clear cooldown zlyhal.'
  } finally {
    savingId.value = null
  }
}

async function reviveSource(row) {
  savingId.value = row.id
  try {
    await reviveBotSource(row.id)
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Revive source zlyhal.'
  } finally {
    savingId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template src="./botSourcesHealth/BotSourcesHealthView.template.html"></template>

<style scoped src="./botSourcesHealth/BotSourcesHealthView.css"></style>
