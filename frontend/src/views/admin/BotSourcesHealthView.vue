<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { useToast } from '@/composables/useToast'
import {
  clearBotSourceCooldown,
  getBotSources,
  resetBotSourceHealth,
  reviveBotSource,
  runBotSource,
  updateBotSource,
} from '@/services/api/admin/bots'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
  refreshToken: {
    type: Number,
    default: 0,
  },
})

const toast = useToast()

const loading = ref(false)
const savingId = ref(null)
const runningSourceKey = ref('')
const error = ref('')
const rows = ref([])

const filters = reactive({
  q: '',
  status: 'all',
})

const editor = reactive({
  id: null,
  name: '',
  url: '',
})

const hasActiveFilters = computed(() => {
  return String(filters.q || '').trim() !== '' || filters.status !== 'all'
})

const filteredRows = computed(() => {
  const search = String(filters.q || '').trim().toLowerCase()

  return rows.value.filter((row) => {
    if (filters.status === 'failing' && !isFailingStatus(row)) {
      return false
    }

    if (filters.status === 'inactive' && row?.is_enabled !== false) {
      return false
    }

    if (!search) {
      return true
    }

    const haystack = [
      row?.key,
      row?.name,
      row?.url,
      row?.source_type,
      row?.status,
    ]
      .map((value) => String(value || '').toLowerCase())
      .join(' ')

    return haystack.includes(search)
  })
})

const summaryLine = computed(() => {
  if (loading.value) return 'Načítavam zdroje…'

  if (hasActiveFilters.value) {
    return `${filteredRows.value.length} / ${rows.value.length} zdrojov`
  }

  return `${rows.value.length} zdrojov`
})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatLatency(value) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric <= 0) return '-'
  if (numeric < 1000) {
    return `${Math.round(numeric)} ms`
  }

  return `${Math.round(numeric / 1000)} s`
}

function isFailingStatus(row) {
  const status = String(row?.status || '').trim().toLowerCase()
  return status === 'fail' || status === 'warn' || Boolean(row?.is_dead)
}

function sourceStatusLabel(row) {
  if (row?.is_enabled === false) return 'Neaktívny'
  if (row?.is_dead) return 'Mŕtvy'

  const status = String(row?.status || '').trim().toLowerCase()
  if (status === 'fail') return 'Chyba'
  if (status === 'warn') return 'Upozornenie'
  if (status === 'ok') return 'OK'

  return 'Neznáme'
}

function sourceStatusClass(row) {
  if (row?.is_enabled === false) return 'status status--inactive'
  if (row?.is_dead) return 'status status--dead'

  const status = String(row?.status || '').trim().toLowerCase()
  if (status === 'fail') return 'status status--fail'
  if (status === 'warn') return 'status status--warn'
  if (status === 'ok') return 'status status--ok'

  return 'status'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await getBotSources()
    rows.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Načítanie zdrojov zlyhalo.'
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.q = ''
  filters.status = 'all'
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
    error.value = e?.response?.data?.message || 'Uloženie source konfigurácie zlyhalo.'
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
    error.value = e?.response?.data?.message || 'Aktualizácia source statusu zlyhala.'
  } finally {
    savingId.value = null
  }
}

async function runSourceNow(row) {
  const sourceKey = String(row?.key || '').trim()
  if (!sourceKey) return

  runningSourceKey.value = sourceKey
  try {
    await runBotSource(sourceKey, {
      mode: 'auto',
      force_manual_override: true,
    })
    toast.success(`Zdroj ${sourceKey} bol spustený.`)
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || `Spustenie zdroja ${sourceKey} zlyhalo.`)
  } finally {
    runningSourceKey.value = ''
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
    error.value = e?.response?.data?.message || 'Vyčistenie cooldownu zlyhalo.'
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
    error.value = e?.response?.data?.message || 'Obnovenie zdroja zlyhalo.'
  } finally {
    savingId.value = null
  }
}

watch(
  () => props.refreshToken,
  () => {
    void load()
  },
)

onMounted(() => {
  void load()
})
</script>

<template src="./botSourcesHealth/BotSourcesHealthView.template.html"></template>

<style scoped src="./botSourcesHealth/BotSourcesHealthView.css"></style>
