import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import {
  addHoursToLocalInput,
  nowLocalInput,
  toLocalInput,
} from '../candidatesListView.utils'

function createEmptyManualForm() {
  return {
    title: '',
    description: '',
    event_type: 'meteor_shower',
    starts_at: '',
    ends_at: '',
  }
}

export function useCandidatesManualEvents({
  activeTab,
  confirm,
  toast,
  resolveTimeFilterParams,
}) {
  const manualLoading = ref(false)
  const manualError = ref(null)
  const manualStatus = ref('draft')
  const manualType = ref('')
  const manualQ = ref('')
  const manualPage = ref(1)
  const manualPerPage = ref(20)
  const manualData = ref(null)
  const showManualForm = ref(false)
  const manualEditingId = ref(null)
  const manualForm = ref(createEmptyManualForm())

  const manualTypeOptions = [
    { value: 'meteor_shower', label: 'Meteoritický roj' },
    { value: 'eclipse_lunar', label: 'Zatmenie Mesiaca' },
    { value: 'eclipse_solar', label: 'Zatmenie Slnka' },
    { value: 'planetary_event', label: 'Planetárny úkaz' },
    { value: 'aurora', label: 'Polárna žiara' },
    { value: 'other', label: 'Iná udalosť' },
  ]

  const manualFormErrors = computed(() => {
    const errors = []

    if (!String(manualForm.value.title || '').trim()) {
      errors.push('Názov je povinný.')
    }
    if (!manualForm.value.starts_at) {
      errors.push('Čas začiatku je povinný.')
    }
    if (manualForm.value.starts_at && manualForm.value.ends_at) {
      const start = new Date(manualForm.value.starts_at)
      const end = new Date(manualForm.value.ends_at)
      if (!Number.isNaN(start.getTime()) && !Number.isNaN(end.getTime()) && end < start) {
        errors.push('Koniec nemôže byť skôr ako začiatok.')
      }
    }

    return errors
  })

  const manualCanSave = computed(() => manualFormErrors.value.length === 0)
  const manualStats = computed(() => {
    const rows = Array.isArray(manualData.value?.data) ? manualData.value.data : []
    return {
      total: rows.length,
      draft: rows.filter((row) => String(row?.status || '').toLowerCase() === 'draft').length,
      published: rows.filter((row) => String(row?.status || '').toLowerCase() === 'published').length,
    }
  })

  function resetManualToFirstPage() {
    manualPage.value = 1
  }

  function buildManualParams() {
    return {
      status: manualStatus.value || undefined,
      type: manualType.value || undefined,
      q: manualQ.value?.trim() ? manualQ.value.trim() : undefined,
      page: manualPage.value,
      per_page: manualPerPage.value,
    }
  }

  async function loadManual() {
    manualLoading.value = true
    manualError.value = null

    try {
      const res = await api.get('/admin/manual-events', { params: buildManualParams() })
      manualData.value = res.data
    } catch (error) {
      manualError.value = error?.response?.data?.message || 'Chyba pri načítaní návrhov'
    } finally {
      manualLoading.value = false
    }
  }

  function clearManualFilters() {
    manualStatus.value = 'draft'
    manualType.value = ''
    manualQ.value = ''
    manualPage.value = 1
    manualPerPage.value = 20
    loadManual()
  }

  function prevManualPage() {
    if (!manualData.value || manualPage.value <= 1) return
    manualPage.value -= 1
    loadManual()
  }

  function nextManualPage() {
    if (!manualData.value || manualPage.value >= manualData.value.last_page) return
    manualPage.value += 1
    loadManual()
  }

  function openManualFormCreate() {
    manualEditingId.value = null
    manualForm.value = createEmptyManualForm()
    showManualForm.value = true
  }

  function openManualFormEdit(row) {
    manualEditingId.value = row.id
    manualForm.value = {
      title: row.title || '',
      description: row.description || '',
      event_type: row.event_type || 'meteor_shower',
      starts_at: toLocalInput(row.starts_at),
      ends_at: toLocalInput(row.ends_at),
    }
    showManualForm.value = true
  }

  function closeManualForm() {
    showManualForm.value = false
  }

  function setManualStartNow() {
    manualForm.value.starts_at = nowLocalInput()
  }

  function setManualEndByHours(hours) {
    manualForm.value.ends_at = addHoursToLocalInput(
      manualForm.value.starts_at || nowLocalInput(),
      hours,
    )
  }

  function updateManualRow(updated) {
    if (!manualData.value || !updated) return
    const rows = manualData.value.data || []
    const idx = rows.findIndex((row) => row.id === updated.id)
    if (idx >= 0) {
      rows[idx] = { ...rows[idx], ...updated }
    }
  }

  async function saveManual() {
    if (!manualCanSave.value) {
      manualError.value = manualFormErrors.value[0] || 'Skontroluj formulár.'
      return
    }

    manualLoading.value = true
    manualError.value = null

    const payload = {
      title: manualForm.value.title,
      description: manualForm.value.description || null,
      event_type: manualForm.value.event_type,
      starts_at: manualForm.value.starts_at,
      ends_at: manualForm.value.ends_at || null,
    }

    try {
      if (manualEditingId.value) {
        const res = await api.put(`/admin/manual-events/${manualEditingId.value}`, payload)
        updateManualRow(res.data)
      } else {
        const res = await api.post('/admin/manual-events', payload)
        manualData.value = manualData.value || { data: [], current_page: 1, last_page: 1, total: 0 }
        manualData.value.data = [res.data, ...(manualData.value.data || [])]
        manualData.value.total = (manualData.value.total || 0) + 1
      }
      showManualForm.value = false
    } catch (error) {
      manualError.value = error?.response?.data?.message || 'Uloženie zlyhalo'
    } finally {
      manualLoading.value = false
    }
  }

  async function deleteManual(row) {
    if (!row?.id) return

    const ok = await confirm({
      title: 'Zmazať návrh',
      message: `Zmazať návrh "${row.title}"?`,
      confirmText: 'Zmazať',
      cancelText: 'Zrušiť',
      variant: 'danger',
    })
    if (!ok) return

    manualLoading.value = true
    manualError.value = null

    try {
      await api.delete(`/admin/manual-events/${row.id}`)
      if (manualData.value?.data) {
        manualData.value.data = manualData.value.data.filter((entry) => entry.id !== row.id)
      }
      toast.success('Návrh bol zmazaný.')
    } catch (error) {
      manualError.value = error?.response?.data?.message || 'Mazanie zlyhalo'
      toast.error(manualError.value)
    } finally {
      manualLoading.value = false
    }
  }

  async function publishManual(row) {
    if (!row?.id) return

    const ok = await confirm({
      title: 'Publikovať návrh',
      message: `Publikovať "${row.title}" do udalostí?`,
      confirmText: 'Publikovať',
      cancelText: 'Zrušiť',
    })
    if (!ok) return

    manualLoading.value = true
    manualError.value = null

    try {
      const res = await api.post(`/admin/manual-events/${row.id}/publish`)
      updateManualRow({
        id: row.id,
        status: 'published',
        published_event_id: res.data?.data?.id ?? res.data?.id ?? null,
      })
      toast.success('Návrh bol publikovaný.')
    } catch (error) {
      manualError.value = error?.response?.data?.message || 'Publikovanie zlyhalo'
      toast.error(manualError.value)
    } finally {
      manualLoading.value = false
    }
  }

  function buildManualBatchPayload() {
    const timeFilters = resolveTimeFilterParams()
    return {
      status: manualStatus.value || 'draft',
      type: manualType.value || undefined,
      q: manualQ.value?.trim() ? manualQ.value.trim() : undefined,
      year: timeFilters.year,
      month: timeFilters.month,
      limit: 1000,
    }
  }

  watch([manualStatus, manualType, manualPerPage], () => {
    resetManualToFirstPage()
    if (activeTab.value === 'manual') loadManual()
  })

  return {
    buildManualBatchPayload,
    clearManualFilters,
    closeManualForm,
    deleteManual,
    loadManual,
    manualCanSave,
    manualData,
    manualEditingId,
    manualError,
    manualForm,
    manualFormErrors,
    manualLoading,
    manualPage,
    manualPerPage,
    manualQ,
    manualStats,
    manualStatus,
    manualType,
    manualTypeOptions,
    nextManualPage,
    openManualFormCreate,
    openManualFormEdit,
    prevManualPage,
    publishManual,
    resetManualToFirstPage,
    saveManual,
    setManualEndByHours,
    setManualStartNow,
    showManualForm,
  }
}
