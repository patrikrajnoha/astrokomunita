<script setup>
import { computed } from 'vue'
import {
  DEFAULT_PUBLISH_ALL_LIMIT,
  canDeleteItemPost,
  canPublishItem,
  formatBool,
  formatDateTime,
  formatStableKey,
  formatStatsJson,
  isManualPublishedItem,
  itemStatusClass,
  itemStatusLabel,
  runStatusHint,
  runStatusLabel,
  statusClass,
  translationProviderClass,
  translationProviderLabel,
} from '../botEngineView.utils'

const props = defineProps({
  selectedRun: {
    type: Object,
    default: null,
  },
  selectedPreviewItem: {
    type: Object,
    default: null,
  },
  runItems: {
    type: Array,
    default: () => [],
  },
  runItemsMeta: {
    type: Object,
    default: null,
  },
  loadingRunItems: {
    type: Boolean,
    default: false,
  },
  canPrevItemsPage: {
    type: Boolean,
    default: false,
  },
  canNextItemsPage: {
    type: Boolean,
    default: false,
  },
  publishAllLimit: {
    type: Number,
    default: DEFAULT_PUBLISH_ALL_LIMIT,
  },
  retryTranslationLimit: {
    type: Number,
    default: 10,
  },
  isRunPublishing: {
    type: Function,
    default: () => false,
  },
  isTranslationRetrying: {
    type: Function,
    default: () => false,
  },
  isTranslationBackfilling: {
    type: Function,
    default: () => false,
  },
  isItemPublishing: {
    type: Function,
    default: () => false,
  },
  isItemDeleting: {
    type: Function,
    default: () => false,
  },
})

const emit = defineEmits([
  'close-run-detail',
  'update:publishAllLimit',
  'publish-all',
  'refresh-items',
  'update:retryTranslationLimit',
  'retry-translation',
  'backfill-translation',
  'go-to-items-page',
  'open-item-preview',
  'publish-item',
  'delete-item-post',
  'close-item-preview',
])

const publishAllLimitModel = computed({
  get: () => props.publishAllLimit,
  set: (value) => emit('update:publishAllLimit', value),
})

const retryTranslationLimitModel = computed({
  get: () => props.retryTranslationLimit,
  set: (value) => emit('update:retryTranslationLimit', value),
})

function closeRunDetail() {
  emit('close-run-detail')
}

function publishAll() {
  emit('publish-all')
}

function refreshItems() {
  emit('refresh-items')
}

function retryTranslation() {
  emit('retry-translation')
}

function backfillTranslation() {
  emit('backfill-translation')
}

function goToItemsPage(page) {
  emit('go-to-items-page', page)
}

function openItemPreview(item) {
  emit('open-item-preview', item)
}

function publishItem(item) {
  emit('publish-item', item)
}

function deleteItemPost(item) {
  emit('delete-item-post', item)
}

function closeItemPreview() {
  emit('close-item-preview')
}
</script>

<template src="./botRunDetail/BotRunDetailModal.template.html"></template>

<style scoped src="./botRunDetail/BotRunDetailModal.css"></style>
