<template>
  <div class="adminLayout">
    <div class="pageHeader">
      <h1 class="pageTitle">Feed sidebar configuration</h1>
      <p class="pageDescription">
        Nastav poradie a viditelnost sekcii pre konkretnu stranku.
      </p>
    </div>

    <div class="card">
      <div class="tabs" role="tablist" aria-label="Sidebar scopes">
        <button
          v-for="tab in scopeTabs"
          :key="tab.value"
          type="button"
          class="tabBtn"
          :class="{ active: activeScope === tab.value }"
          :disabled="loading"
          @click="onScopeClick(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="cardHeader">
        <h2>{{ activeTabLabel }} sidebar sections</h2>
        <button class="btn btnPrimary" :disabled="loading || !hasChanges" @click="saveChanges">
          <span v-if="loading" class="spinner"></span>
          {{ loading ? 'Saving...' : 'Save changes' }}
        </button>
      </div>

      <div v-if="error" class="alert alertError">{{ error }}</div>

      <div v-if="loading" class="skeletonList" aria-live="polite">
        <div v-for="n in 5" :key="n" class="skeletonRow"></div>
      </div>

      <div v-else-if="sections.length === 0" class="emptyState">
        <div class="emptyTitle">No sections</div>
        <div class="emptyText">No sidebar sections were found for this scope.</div>
      </div>

      <draggable
        v-else
        v-model="sections"
        tag="div"
        :component-data="{ class: 'sectionsList' }"
        handle=".dragHandle"
        item-key="section_key"
        @start="dragStart"
        @end="dragEnd"
      >
        <template #item="{ element: section, index }">
          <div
            class="sectionItem"
            :class="{
              isDragging: isDragging && draggedIndex === index,
              isHidden: !section.is_enabled,
            }"
          >
            <div class="sectionContent">
              <button type="button" class="dragHandle" aria-label="Drag section">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                  <path
                    d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-12a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"
                  />
                </svg>
              </button>

              <div class="sectionInfo">
                <div class="sectionTitle">{{ section.title }}</div>
                <div class="sectionKey">{{ section.section_key }}</div>
              </div>

              <label class="toggle">
                <input v-model="section.is_enabled" type="checkbox" />
                <span class="toggleSlider"></span>
                <span class="toggleLabel">{{ section.is_enabled ? 'Visible' : 'Hidden' }}</span>
              </label>
            </div>
          </div>
        </template>
      </draggable>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import draggable from 'vuedraggable'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { sidebarConfigAdminApi } from '@/services/api/admin/sidebarConfig'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'

const scopeTabs = [
  { value: 'home', label: 'Home' },
  { value: 'events', label: 'Events' },
  { value: 'calendar', label: 'Calendar' },
  { value: 'learning', label: 'Learning' },
  { value: 'notifications', label: 'Notifications' },
]

const activeScope = ref('home')
const sections = ref([])
const originalSections = ref([])
const loading = ref(false)
const error = ref('')
const isDragging = ref(false)
const draggedIndex = ref(null)
const { showToast } = useToast()
const { confirm } = useConfirm()
const sidebarConfigStore = useSidebarConfigStore()

const activeTabLabel = computed(() => scopeTabs.find((tab) => tab.value === activeScope.value)?.label || 'Home')

const normalize = (items) => {
  return [...(Array.isArray(items) ? items : [])]
    .map((item) => ({
      section_key: String(item.section_key || ''),
      title: String(item.title || ''),
      order: Number.isFinite(item.order) ? Number(item.order) : 0,
      is_enabled: Boolean(item.is_enabled),
    }))
    .sort((a, b) => a.order - b.order)
}

const hasChanges = computed(() => {
  const current = JSON.stringify(
    normalize(sections.value).map((item) => ({
      section_key: item.section_key,
      order: item.order,
      is_enabled: item.is_enabled,
    })),
  )

  const initial = JSON.stringify(
    normalize(originalSections.value).map((item) => ({
      section_key: item.section_key,
      order: item.order,
      is_enabled: item.is_enabled,
    })),
  )

  return current !== initial
})

const applyOrderFromPosition = () => {
  sections.value.forEach((item, index) => {
    item.order = index
  })
}

const setScopeData = (items) => {
  sections.value = normalize(items)
  applyOrderFromPosition()
  originalSections.value = normalize(sections.value)
}

const loadScope = async (scope) => {
  loading.value = true
  error.value = ''

  try {
    const payload = await sidebarConfigAdminApi.get(scope)
    setScopeData(payload?.data || [])
  } catch (err) {
    const message = err?.response?.data?.message || 'Failed to load sidebar configuration.'
    error.value = message
    showToast(message, 'error')
    setScopeData(sidebarConfigStore.getDefaultForScope())
  } finally {
    loading.value = false
  }
}

const onScopeClick = async (nextScope) => {
  if (nextScope === activeScope.value) return

  if (hasChanges.value) {
    const confirmed = await confirm({
      title: 'Unsaved changes',
      message: 'You have unsaved changes. Continue and discard them?',
      confirmText: 'Discard changes',
      cancelText: 'Stay here',
      variant: 'danger',
    })
    if (!confirmed) return
  }

  activeScope.value = nextScope
  await loadScope(nextScope)
}

const dragStart = (event) => {
  isDragging.value = true
  draggedIndex.value = event.oldIndex
}

const dragEnd = () => {
  isDragging.value = false
  draggedIndex.value = null
  applyOrderFromPosition()
}

const saveChanges = async () => {
  if (!hasChanges.value) return

  loading.value = true
  error.value = ''

  try {
    const payloadItems = normalize(sections.value).map((item, index) => ({
      section_key: item.section_key,
      order: index,
      is_enabled: Boolean(item.is_enabled),
    }))

    const response = await sidebarConfigAdminApi.update(activeScope.value, payloadItems)
    const savedItems = normalize(response?.data || payloadItems)

    setScopeData(savedItems)
    sidebarConfigStore.byScope[activeScope.value] = savedItems

    showToast('Sidebar configuration saved.', 'success')
  } catch (err) {
    const message = err?.response?.data?.message || 'Failed to save sidebar configuration.'
    error.value = message
    showToast(message, 'error')
  } finally {
    loading.value = false
  }
}

const beforeUnloadListener = (event) => {
  if (!hasChanges.value) return

  event.preventDefault()
  event.returnValue = ''
}

onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnloadListener)
  await loadScope(activeScope.value)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnloadListener)
})

onBeforeRouteLeave(async () => {
  if (!hasChanges.value) return true
  return confirm({
    title: 'Unsaved changes',
    message: 'You have unsaved changes. Leave this page?',
    confirmText: 'Leave page',
    cancelText: 'Stay here',
    variant: 'danger',
  })
})
</script>

<style scoped>
.adminLayout {
  max-width: 880px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.pageHeader {
  margin-bottom: 1.5rem;
}

.pageTitle {
  font-size: 2rem;
  font-weight: 800;
  color: var(--color-surface);
  margin-bottom: 0.4rem;
}

.pageDescription {
  color: var(--color-text-secondary);
  font-size: 1rem;
}

.card {
  background: rgb(var(--color-bg-rgb) / 0.55);
  border: 1px solid var(--color-text-secondary);
  border-radius: 1.5rem;
  padding: 1.5rem;
}

.tabs {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.tabBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  border-radius: 0.65rem;
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-text-secondary);
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.56rem 0.65rem;
}

.tabBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.tabBtn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.cardHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.cardHeader h2 {
  font-size: 1.06rem;
  font-weight: 700;
  color: var(--color-surface);
}

.btn {
  padding: 0.72rem 1.2rem;
  border-radius: 0.75rem;
  border: 1px solid transparent;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.btnPrimary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgb(255 255 255 / 0.35);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.alert {
  padding: 0.75rem;
  border-radius: 0.72rem;
  margin-bottom: 1rem;
  font-weight: 500;
}

.alertError {
  background: rgb(var(--color-danger-rgb) / 0.1);
  border: 1px solid rgb(var(--color-danger-rgb) / 0.3);
  color: var(--color-danger);
}

.skeletonList {
  display: grid;
  gap: 0.72rem;
}

.skeletonRow {
  height: 62px;
  border-radius: 0.9rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 220% 100%;
  animation: shimmer 1.1s linear infinite;
}

@keyframes shimmer {
  from {
    background-position: 220% 0;
  }

  to {
    background-position: -220% 0;
  }
}

.emptyState {
  text-align: center;
  padding: 2.4rem 1rem;
}

.emptyTitle {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-surface);
}

.emptyText {
  color: var(--color-text-secondary);
  margin-top: 0.25rem;
}

.sectionsList {
  display: grid;
  gap: 0.68rem;
}

.sectionItem {
  background: rgb(var(--color-bg-rgb) / 0.3);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 0.95rem;
  transition: all 0.2s ease;
}

.sectionItem.isHidden {
  opacity: 0.62;
}

.sectionItem.isDragging {
  opacity: 0.82;
  transform: rotate(1deg);
}

.sectionContent {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  padding: 0.9rem;
}

.dragHandle {
  color: var(--color-text-secondary);
  border: 0;
  background: transparent;
  cursor: grab;
  padding: 0.25rem;
}

.dragHandle:active {
  cursor: grabbing;
}

.sectionInfo {
  flex: 1;
}

.sectionTitle {
  font-weight: 600;
  color: var(--color-surface);
  margin-bottom: 0.2rem;
}

.sectionKey {
  font-size: 0.82rem;
  color: var(--color-text-secondary);
  font-family: monospace;
}

.toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  cursor: pointer;
}

.toggle input[type='checkbox'] {
  display: none;
}

.toggleSlider {
  width: 46px;
  height: 22px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.35);
  position: relative;
}

.toggleSlider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  top: 2px;
  left: 2px;
  background: white;
  transition: transform 0.2s ease;
}

.toggle input[type='checkbox']:checked + .toggleSlider {
  background: var(--color-primary);
}

.toggle input[type='checkbox']:checked + .toggleSlider::before {
  transform: translateX(24px);
}

.toggleLabel {
  font-size: 0.84rem;
  color: var(--color-text-secondary);
}

@media (max-width: 840px) {
  .tabs {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .cardHeader {
    flex-direction: column;
    align-items: stretch;
    gap: 0.8rem;
  }

  .sectionContent {
    flex-wrap: wrap;
  }
}
</style>
