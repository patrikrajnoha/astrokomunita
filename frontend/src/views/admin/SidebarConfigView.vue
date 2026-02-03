<template>
  <div class="adminLayout">
    <div class="pageHeader">
      <h1 class="pageTitle">Feed sidebar configuration</h1>
      <p class="pageDescription">Určuje poradie a viditeľnosť sekcií v pravom stĺpci feedu.</p>
    </div>

    <div class="card">
      <div class="cardHeader">
        <h2>Sekcie sidebaru</h2>
        <button 
          class="btn btnPrimary"
          :disabled="loading || !hasChanges"
          @click="saveChanges"
        >
          <span v-if="loading" class="spinner"></span>
          {{ loading ? 'Saving...' : 'Save changes' }}
        </button>
      </div>

      <div v-if="error" class="alert alertError">
        {{ error }}
      </div>

      <div v-if="success" class="alert alertSuccess">
        {{ success }}
      </div>

      <div v-if="sections.length === 0 && !loading" class="emptyState">
        <div class="emptyTitle">Žiadne sekcie</div>
        <div class="emptyText">Nenašli sa žiadne sekcie sidebaru.</div>
      </div>

      <draggable
        v-else-if="sections.length > 0"
        v-model="sections"
        tag="div"
        :component-data="{
          class: 'sectionsList'
        }"
        handle=".dragHandle"
        item-key="id"
        @start="dragStart"
        @end="dragEnd"
      >
        <template #item="{ element: section, index }">
          <div 
            class="sectionItem"
            :class="{ 
              'isDragging': isDragging && draggedIndex === index,
              'isHidden': !section.is_visible 
            }"
          >
            <div class="sectionContent">
              <div class="dragHandle">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-12a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                </svg>
              </div>

              <div class="sectionInfo">
                <div class="sectionTitle">{{ section.title }}</div>
                <div class="sectionKey">{{ section.key }}</div>
              </div>

              <div class="sectionActions">
                <label class="toggle">
                  <input
                    type="checkbox"
                    v-model="section.is_visible"
                    @change="markAsChanged"
                  />
                  <span class="toggleSlider"></span>
                  <span class="toggleLabel">{{ section.is_visible ? 'Viditeľné' : 'Skryté' }}</span>
                </label>
              </div>
            </div>
          </div>
        </template>
      </draggable>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import draggable from 'vuedraggable'
import api from '@/services/api'

export default {
  name: 'SidebarConfigView',
  components: {
    draggable
  },
  setup() {
    const sections = ref([])
    const originalSections = ref([])
    const loading = ref(false)
    const error = ref('')
    const success = ref('')
    const isDragging = ref(false)
    const draggedIndex = ref(null)

    const hasChanges = computed(() => {
      if (sections.value.length !== originalSections.value.length) {
        return true
      }

      return sections.value.some((section, index) => {
        const original = originalSections.value[index]
        return (
          section.id !== original.id ||
          section.sort_order !== original.sort_order ||
          section.is_visible !== original.is_visible
        )
      })
    })

    const fetchSections = async () => {
      loading.value = true
      error.value = ''
      success.value = ''

      try {
        const response = await api.get('/admin/sidebar-sections')
        sections.value = response.data?.data || []
        originalSections.value = JSON.parse(JSON.stringify(sections.value))
      } catch (err) {
        error.value = err?.response?.data?.message || 'Nepodarilo sa načítať sekcie'
        console.error('Failed to fetch sidebar sections:', err)
      } finally {
        loading.value = false
      }
    }

    const markAsChanged = () => {
      // Changes are tracked automatically via hasChanges computed
    }

    const dragStart = (evt) => {
      isDragging.value = true
      draggedIndex.value = evt.oldIndex
    }

    const dragEnd = () => {
      isDragging.value = false
      draggedIndex.value = null
      
      // Update sort_order based on new positions
      sections.value.forEach((section, index) => {
        section.sort_order = index + 1
      })
    }

    const saveChanges = async () => {
      if (!hasChanges.value) return

      loading.value = true
      error.value = ''
      success.value = ''

      try {
        const payload = {
          sections: sections.value.map(section => ({
            id: section.id,
            sort_order: section.sort_order,
            is_visible: section.is_visible
          }))
        }

        const response = await api.put('/admin/sidebar-sections', payload)
        
        // Update original sections with the saved data
        originalSections.value = JSON.parse(JSON.stringify(sections.value))
        
        success.value = response.data?.message || 'Zmeny boli úspešne uložené'
        
        // Clear success message after 3 seconds
        setTimeout(() => {
          success.value = ''
        }, 3000)
      } catch (err) {
        error.value = err?.response?.data?.message || 'Nepodarilo sa uložiť zmeny'
        console.error('Failed to save sidebar sections:', err)
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      fetchSections()
    })

    return {
      sections,
      loading,
      error,
      success,
      isDragging,
      draggedIndex,
      hasChanges,
      markAsChanged,
      dragStart,
      dragEnd,
      saveChanges
    }
  }
}
</script>

<style scoped>
.adminLayout {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.pageHeader {
  margin-bottom: 2rem;
}

.pageTitle {
  font-size: 2rem;
  font-weight: 800;
  color: var(--color-surface);
  margin-bottom: 0.5rem;
}

.pageDescription {
  color: var(--color-text-secondary);
  font-size: 1.1rem;
}

.card {
  background: rgb(var(--color-bg-rgb) / 0.55);
  border: 1px solid var(--color-text-secondary);
  border-radius: 1.5rem;
  padding: 1.5rem;
  overflow: hidden;
}

.cardHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.cardHeader h2 {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-surface);
  margin: 0;
}

.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 0.75rem;
  border: 1px solid transparent;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btnPrimary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.btnPrimary:hover:not(:disabled) {
  background: rgb(var(--color-primary-rgb) / 0.9);
  transform: translateY(-1px);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.alert {
  padding: 1rem;
  border-radius: 0.75rem;
  margin-bottom: 1.5rem;
  font-weight: 500;
}

.alertError {
  background: rgb(var(--color-danger-rgb) / 0.1);
  border: 1px solid rgb(var(--color-danger-rgb) / 0.3);
  color: var(--color-danger);
}

.alertSuccess {
  background: rgb(var(--color-success-rgb) / 0.1);
  border: 1px solid rgb(var(--color-success-rgb) / 0.3);
  color: var(--color-success);
}

.emptyState {
  text-align: center;
  padding: 3rem 1rem;
}

.emptyTitle {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-surface);
  margin-bottom: 0.5rem;
}

.emptyText {
  color: var(--color-text-secondary);
}

.sectionsList {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.sectionItem {
  background: rgb(var(--color-bg-rgb) / 0.3);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 1rem;
  transition: all 0.2s ease;
}

.sectionItem:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.05);
}

.sectionItem.isHidden {
  opacity: 0.6;
}

.sectionItem.isDragging {
  opacity: 0.8;
  transform: rotate(2deg);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.sectionContent {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
}

.dragHandle {
  cursor: grab;
  color: var(--color-text-secondary);
  padding: 0.5rem;
  border-radius: 0.5rem;
  transition: all 0.2s ease;
}

.dragHandle:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  color: var(--color-surface);
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
  font-size: 1rem;
  margin-bottom: 0.25rem;
}

.sectionKey {
  font-size: 0.85rem;
  color: var(--color-text-secondary);
  font-family: monospace;
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  display: inline-block;
}

.sectionActions {
  display: flex;
  align-items: center;
}

.toggle {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
}

.toggle input[type="checkbox"] {
  display: none;
}

.toggleSlider {
  width: 48px;
  height: 24px;
  background: rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 24px;
  position: relative;
  transition: background 0.3s ease;
}

.toggleSlider::before {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  transition: transform 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle input[type="checkbox"]:checked + .toggleSlider {
  background: var(--color-primary);
}

.toggle input[type="checkbox"]:checked + .toggleSlider::before {
  transform: translateX(24px);
}

.toggleLabel {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--color-text-secondary);
  user-select: none;
}

@media (max-width: 640px) {
  .adminLayout {
    padding: 1rem 0.5rem;
  }
  
  .cardHeader {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .sectionContent {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  
  .dragHandle {
    align-self: flex-start;
  }
}
</style>
