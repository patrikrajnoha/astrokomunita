<script setup>
import { computed } from 'vue'

const props = defineProps({
  manualStats: {
    type: Object,
    default: () => ({
      total: 0,
      draft: 0,
      published: 0,
    }),
  },
  manualLoading: {
    type: Boolean,
    default: false,
  },
  manualStatus: {
    type: String,
    default: 'draft',
  },
  manualType: {
    type: String,
    default: '',
  },
  manualPerPage: {
    type: Number,
    default: 20,
  },
  manualQ: {
    type: String,
    default: '',
  },
  showManualForm: {
    type: Boolean,
    default: false,
  },
  manualEditingId: {
    type: [Number, String],
    default: null,
  },
  manualTypeOptions: {
    type: Array,
    default: () => [],
  },
  manualForm: {
    type: Object,
    default: () => ({
      title: '',
      description: '',
      event_type: 'meteor_shower',
      starts_at: '',
      ends_at: '',
    }),
  },
  manualFormErrors: {
    type: Array,
    default: () => [],
  },
  manualCanSave: {
    type: Boolean,
    default: false,
  },
  manualError: {
    type: String,
    default: '',
  },
  manualData: {
    type: Object,
    default: null,
  },
  manualPage: {
    type: Number,
    default: 1,
  },
  formatDate: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits([
  'update:manualStatus',
  'update:manualType',
  'update:manualPerPage',
  'update:manualQ',
  'update:manualForm',
  'open-manual-form-create',
  'clear-manual-filters',
  'search-manual',
  'set-manual-start-now',
  'set-manual-end-by-hours',
  'close-manual-form',
  'save-manual',
  'open-manual-form-edit',
  'delete-manual',
  'publish-manual',
  'prev-manual-page',
  'next-manual-page',
])

const manualRows = computed(() =>
  Array.isArray(props.manualData?.data) ? props.manualData.data : [],
)

function isPublished(row) {
  return String(row?.status || '').toLowerCase() === 'published'
}

function updateManualStatus(value) {
  emit('update:manualStatus', value)
}

function updateManualType(value) {
  emit('update:manualType', value)
}

function updateManualPerPage(value) {
  emit('update:manualPerPage', Number(value))
}

function updateManualQ(value) {
  emit('update:manualQ', value)
}

function updateManualFormField(field, value) {
  emit('update:manualForm', {
    ...(props.manualForm || {}),
    [field]: value,
  })
}

function openManualFormCreate() {
  emit('open-manual-form-create')
}

function clearManualFilters() {
  emit('clear-manual-filters')
}

function searchManual() {
  emit('search-manual')
}

function setManualStartNow() {
  emit('set-manual-start-now')
}

function setManualEndByHours(hours) {
  emit('set-manual-end-by-hours', hours)
}

function closeManualForm() {
  emit('close-manual-form')
}

function saveManual() {
  emit('save-manual')
}

function openManualFormEdit(row) {
  emit('open-manual-form-edit', row)
}

function deleteManual(row) {
  emit('delete-manual', row)
}

function publishManual(row) {
  emit('publish-manual', row)
}

function prevManualPage() {
  emit('prev-manual-page')
}

function nextManualPage() {
  emit('next-manual-page')
}
</script>

<template src="./candidateManual/CandidateManualTab.template.html"></template>

<style scoped src="./candidateManual/CandidateManualTab.css"></style>
