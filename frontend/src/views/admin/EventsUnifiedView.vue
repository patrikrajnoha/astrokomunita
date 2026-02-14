<template>
  <div class="adminEvents">
    <!-- Header with view toggle -->
    <header class="adminHeader">
      <div class="headerContent">
        <div>
          <h1 class="adminTitle">Events</h1>
          <p class="adminSubtitle">Admin events management</p>
        </div>
        <div class="viewToggle">
          <button
            class="toggleBtn"
            :class="{ active: currentView === 'list' }"
            @click="currentView = 'list'"
          >
            📋 List
          </button>
          <button
            class="toggleBtn"
            :class="{ active: currentView === 'create' }"
            @click="currentView = 'create'"
          >
            ➕ Create
          </button>
          <button
            v-if="currentView === 'edit'"
            class="toggleBtn"
            :class="{ active: currentView === 'edit' }"
            @click="currentView = 'list'"
          >
            ← Back
          </button>
        </div>
      </div>
    </header>

    <!-- List View -->
    <div v-if="currentView === 'list'" class="listView">
      <!-- Error State -->
      <div v-if="error" class="errorState">
        <div class="errorTitle">Failed to load events</div>
        <div class="errorText">{{ error }}</div>
        <button class="retryBtn" @click="refresh">Skúsiť znova</button>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="loadingState">
        <div class="skeleton h-8 w-32 mb-4"></div>
        <div class="skeleton h-64 w-full"></div>
      </div>

      <!-- Events Table -->
      <div v-else-if="data" class="tableContainer">
        <div class="tableHeader">
          <h2>Events List</h2>
          <div class="tableInfo">
            Page {{ pagination?.currentPage }} / {{ pagination?.lastPage }} (total {{ pagination?.total }})
          </div>
        </div>

        <div class="tableWrapper">
          <table class="eventsTable">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Start</th>
                <th>Visibility</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="event in data.data" :key="event.id">
                <td class="titleCell">{{ event.title }}</td>
                <td class="typeCell">{{ event.type }}</td>
                <td class="dateCell">{{ formatDate(event.start_at || event.starts_at || event.max_at) }}</td>
                <td class="visibilityCell">
                  <span class="badge" :class="event.visibility === 1 ? 'public' : 'hidden'">
                    {{ event.visibility === 1 ? 'public' : 'hidden' }}
                  </span>
                </td>
                <td class="actionsCell">
                  <button
                    class="actionBtn editBtn"
                    @click="editEvent(event)"
                  >
                    ✏️ Edit
                  </button>
                </td>
              </tr>
              <tr v-if="data.data.length === 0">
                <td colspan="5" class="emptyState">No events found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination" class="pagination">
          <button
            class="paginationBtn"
            :disabled="!hasPrevPage"
            @click="prevPage"
          >
            ← Previous
          </button>
          <span class="paginationInfo">
            Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
          </span>
          <button
            class="paginationBtn"
            :disabled="!hasNextPage"
            @click="nextPage"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Form View -->
    <div v-if="currentView === 'create' || currentView === 'edit'" class="formView">
      <div class="formContainer">
        <h2>{{ isEdit ? 'Edit Event' : 'Create Event' }}</h2>
        <p class="formSubtitle">
          {{ isEdit ? 'Update existing event data.' : 'Add a new manual event.' }}
        </p>

        <!-- Error/Success Messages -->
        <div v-if="formError" class="formError">
          {{ formError }}
        </div>
        <div v-if="formSuccess" class="formSuccess">
          {{ formSuccess }}
        </div>

        <form @submit.prevent="submitForm" class="eventForm">
          <div class="formGrid">
            <!-- Title -->
            <div class="formField">
              <label for="title">Title</label>
              <input
                id="title"
                v-model="form.title"
                type="text"
                required
                :disabled="formLoading"
                class="formInput"
              />
            </div>

            <!-- Description -->
            <div class="formField full">
              <label for="description">Description</label>
              <textarea
                id="description"
                v-model="form.description"
                rows="4"
                :disabled="formLoading"
                class="formTextarea"
              ></textarea>
            </div>

            <!-- Type and Visibility -->
            <div class="formField">
              <label for="type">Event Type</label>
              <select
                id="type"
                v-model="form.type"
                :disabled="formLoading"
                class="formSelect"
              >
                <option v-for="type in eventTypes" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </select>
            </div>

            <div class="formField">
              <label for="visibility">Visibility</label>
              <select
                id="visibility"
                v-model.number="form.visibility"
                :disabled="formLoading"
                class="formSelect"
              >
                <option :value="1">Public</option>
                <option :value="0">Hidden</option>
              </select>
            </div>

            <!-- Start and End Dates -->
            <div class="formField">
              <label for="start_at">Starts At</label>
              <input
                id="start_at"
                v-model="form.start_at"
                type="datetime-local"
                required
                :disabled="formLoading"
                class="formInput"
              />
            </div>

            <div class="formField">
              <label for="end_at">Ends At (optional)</label>
              <input
                id="end_at"
                v-model="form.end_at"
                type="datetime-local"
                :disabled="formLoading"
                class="formInput"
              />
            </div>
          </div>

          <!-- Form Actions -->
          <div class="formActions">
            <button
              type="button"
              @click="currentView = 'list'"
              :disabled="formLoading"
              class="cancelBtn"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="formLoading"
              class="submitBtn"
            >
              {{ formLoading ? 'Saving...' : (isEdit ? 'Update Event' : 'Create Event') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue'
import { useAdminTable } from '@/composables/useAdminTable'
import http from '@/services/api'

export default {
  name: 'AdminEventsUnifiedView',
  setup() {
    const currentView = ref('list')
    const editingEvent = ref(null)

    // Form state
    const formLoading = ref(false)
    const formError = ref('')
    const formSuccess = ref('')

    const eventTypes = [
      { value: 'meteor_shower', label: 'Meteory' },
      { value: 'eclipse_lunar', label: 'Zatmenie (L)' },
      { value: 'eclipse_solar', label: 'Zatmenie (S)' },
      { value: 'planetary_event', label: 'Konjunkcia' },
      { value: 'other', label: 'Iné' },
    ]

    const form = ref({
      title: '',
      description: '',
      type: 'meteor_shower',
      start_at: '',
      end_at: '',
      visibility: 1,
    })

    // Use useAdminTable for events list
    const {
      loading,
      error,
      data,
      pagination,
      hasNextPage,
      hasPrevPage,
      nextPage,
      prevPage,
      refresh
    } = useAdminTable(
      async (params) => {
        const response = await http.get('/admin/events', { params })
        return response
      }
    )

    const isEdit = computed(() => currentView.value === 'edit' && editingEvent.value)

    // Methods
    const editEvent = (event) => {
      editingEvent.value = event
      form.value = {
        title: event.title || '',
        description: event.description || '',
        type: event.type || 'meteor_shower',
        start_at: toLocalInput(event.start_at || event.starts_at || event.max_at),
        end_at: toLocalInput(event.end_at || event.ends_at),
        visibility: typeof event.visibility === 'number' ? event.visibility : 1,
      }
      currentView.value = 'edit'
      formError.value = ''
      formSuccess.value = ''
    }

    const toLocalInput = (value) => {
      if (!value) return ''
      const d = new Date(value)
      if (isNaN(d.getTime())) return ''
      const pad = (n) => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
    }

    const submitForm = async () => {
      formLoading.value = true
      formError.value = ''
      formSuccess.value = ''

      const payload = {
        title: form.value.title,
        description: form.value.description || null,
        type: form.value.type,
        start_at: form.value.start_at,
        end_at: form.value.end_at || null,
        visibility: form.value.visibility,
      }

      try {
        if (isEdit.value) {
          await http.put(`/admin/events/${editingEvent.value.id}`, payload)
          formSuccess.value = 'Event updated successfully.'
        } else {
          await http.post('/admin/events', payload)
          formSuccess.value = 'Event created successfully.'
        }

        // Reset form and go back to list after a short delay
        setTimeout(() => {
          currentView.value = 'list'
          resetForm()
          refresh()
        }, 1000)
      } catch (err) {
        formError.value = err.response?.data?.message || 'Save failed.'
      } finally {
        formLoading.value = false
      }
    }

    const resetForm = () => {
      form.value = {
        title: '',
        description: '',
        type: 'meteor_shower',
        start_at: '',
        end_at: '',
        visibility: 1,
      }
      editingEvent.value = null
      formError.value = ''
      formSuccess.value = ''
    }

    const formatDate = (value) => {
      if (!value) return '-'
      const d = new Date(value)
      if (isNaN(d.getTime())) return String(value)
      return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
    }

    // Reset form when switching to create view
    watch(currentView, (newView) => {
      if (newView === 'create') {
        resetForm()
      }
    })

    return {
      currentView,
      loading,
      error,
      data,
      pagination,
      hasNextPage,
      hasPrevPage,
      nextPage,
      prevPage,
      refresh,
      formLoading,
      formError,
      formSuccess,
      form,
      eventTypes,
      isEdit,
      editEvent,
      submitForm,
      formatDate,
    }
  }
}
</script>

<style scoped>
.adminEvents {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.adminHeader {
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding-bottom: 1rem;
}

.headerContent {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 1rem;
}

.adminTitle {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
}

.adminSubtitle {
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.25rem;
}

.viewToggle {
  display: flex;
  gap: 0.5rem;
}

.toggleBtn {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.375rem;
  transition: all 0.2s;
  color: rgba(255, 255, 255, 0.8);
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.2);
  cursor: pointer;
}

.toggleBtn:hover {
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.1);
}

.toggleBtn.active {
  background-color: #3b82f6;
  color: #ffffff;
  border-color: #3b82f6;
}

/* List View Styles */
.listView {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.errorState {
  text-align: center;
  padding: 3rem 0;
}

.errorTitle {
  font-size: 1.125rem;
  font-weight: 600;
  color: #ef4444;
}

.errorText {
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.5rem;
}

.retryBtn {
  margin-top: 1rem;
  padding: 0.5rem 1rem;
  background-color: #3b82f6;
  color: white;
  border-radius: 0.375rem;
  border: none;
  cursor: pointer;
}

.retryBtn:hover {
  background-color: #2563eb;
}

.loadingState {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.tableContainer {
  background-color: rgba(255, 255, 255, 0.05);
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.1);
  overflow: hidden;
}

.tableHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.tableHeader h2 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #ffffff;
  margin: 0;
}

.tableInfo {
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.7);
}

.tableWrapper {
  overflow-x: auto;
}

.eventsTable {
  width: 100%;
  border-collapse: collapse;
}

.eventsTable th {
  text-align: left;
  padding: 1rem 1.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.8);
  background-color: rgba(255, 255, 255, 0.05);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.eventsTable td {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.titleCell {
  font-weight: 500;
  color: #ffffff;
}

.typeCell {
  color: rgba(255, 255, 255, 0.8);
}

.dateCell {
  color: rgba(255, 255, 255, 0.7);
  white-space: nowrap;
}

.visibilityCell .badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.visibilityCell .badge.public {
  background-color: rgba(34, 197, 94, 0.2);
  color: #22c55e;
}

.visibilityCell .badge.hidden {
  background-color: rgba(239, 68, 68, 0.2);
  color: #ef4444;
}

.actionsCell {
  text-align: right;
}

.actionBtn {
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.875rem;
}

.actionBtn:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.editBtn:hover {
  border-color: #3b82f6;
  background-color: rgba(59, 130, 246, 0.1);
}

.emptyState {
  text-align: center;
  padding: 2rem;
  color: rgba(255, 255, 255, 0.5);
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.paginationBtn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  cursor: pointer;
  transition: all 0.2s;
}

.paginationBtn:hover:not(:disabled) {
  background-color: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.paginationBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.paginationInfo {
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Form View Styles */
.formView {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.formContainer {
  background-color: rgba(255, 255, 255, 0.05);
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 1.5rem;
}

.formContainer h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #ffffff;
  margin: 0 0 0.5rem 0;
}

.formSubtitle {
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 1.5rem;
}

.formError {
  padding: 0.75rem;
  background-color: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 0.375rem;
  color: #ef4444;
  margin-bottom: 1rem;
}

.formSuccess {
  padding: 0.75rem;
  background-color: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  border-radius: 0.375rem;
  color: #22c55e;
  margin-bottom: 1rem;
}

.eventForm {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.formGrid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.formField.full {
  grid-column: 1 / -1;
}

.formField label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 0.5rem;
}

.formInput,
.formTextarea,
.formSelect {
  width: 100%;
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background-color: rgba(255, 255, 255, 0.05);
  color: #ffffff;
  transition: all 0.2s;
}

.formInput:focus,
.formTextarea:focus,
.formSelect:focus {
  outline: none;
  border-color: #3b82f6;
  background-color: rgba(255, 255, 255, 0.08);
}

.formTextarea {
  resize: vertical;
  min-height: 100px;
}

.formActions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1rem;
}

.cancelBtn,
.submitBtn {
  padding: 0.75rem 1.5rem;
  border-radius: 0.375rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  cursor: pointer;
  transition: all 0.2s;
  font-weight: 500;
}

.cancelBtn {
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
}

.cancelBtn:hover:not(:disabled) {
  background-color: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.submitBtn {
  background-color: #3b82f6;
  color: #ffffff;
  border-color: #3b82f6;
}

.submitBtn:hover:not(:disabled) {
  background-color: #2563eb;
}

.cancelBtn:disabled,
.submitBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Skeleton styles */
.skeleton {
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 0.375rem;
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Responsive */
@media (max-width: 768px) {
  .headerContent {
    flex-direction: column;
    align-items: flex-start;
  }

  .formGrid {
    grid-template-columns: 1fr;
  }

  .tableWrapper {
    overflow-x: scroll;
  }

  .eventsTable {
    min-width: 600px;
  }
}
</style>
