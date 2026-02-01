<template>
  <div class="todayTab">
    <div class="tabActions">
      <button class="actionbtn" @click="fetchNow" :disabled="fetching">
        {{ fetching ? 'Fetching…' : 'Fetch now' }}
      </button>
      <button class="actionbtn" @click="publishScheduledNow" :disabled="publishingScheduled">
        {{ publishingScheduled ? 'Publishing…' : 'Publish scheduled' }}
      </button>
      <select v-model="statusFilter" class="filterSelect">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="scheduled">Scheduled</option>
        <option value="published">Published</option>
        <option value="discarded">Discarded</option>
        <option value="error">Error</option>
      </select>
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search items..."
        class="searchInput"
      />
      <select v-model="sourceFilter" class="filterSelect">
        <option value="">All sources</option>
        <option value="nasa_news">NASA News</option>
      </select>
    </div>

    <div v-if="fetchResult" class="fetchResult">
      <div class="resultItem">
        <span class="resultLabel">Created:</span>
        <span class="resultValue">{{ fetchResult.created }}</span>
      </div>
      <div class="resultItem">
        <span class="resultLabel">Skipped:</span>
        <span class="resultValue">{{ fetchResult.skipped }}</span>
      </div>
      <div class="resultItem">
        <span class="resultLabel">Errors:</span>
        <span class="resultValue">{{ fetchResult.errors }}</span>
      </div>
    </div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-3/4"></div>
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-4/5"></div>
    </div>

    <div v-if="successMessage" class="state stateSuccess">
      <div class="stateTitle">Success</div>
      <div class="stateText">{{ successMessage }}</div>
      <button class="ghostbtn" @click="successMessage = null">Dismiss</button>
    </div>

    <div v-if="error" class="state stateError">
      <div class="stateTitle">Error</div>
      <div class="stateText">{{ error }}</div>
      <button class="ghostbtn" @click="error = null">Dismiss</button>
    </div>

    <div v-else-if="items.length === 0" class="state">
      <div class="stateTitle">Žiadne položky na dnes</div>
    </div>

    <!-- Bulk Actions -->
    <div v-if="selectedItems.length > 0" class="bulkActions">
      <span class="bulkInfo">{{ selectedItems.length }} items selected</span>
      <button class="actionbtn" @click="bulkApprove" :disabled="bulkLoading">
        {{ bulkLoading ? 'Processing…' : 'Approve' }}
      </button>
      <button class="actionbtn" @click="bulkPublish" :disabled="bulkLoading">
        {{ bulkLoading ? 'Processing…' : 'Publish' }}
      </button>
      <button class="ghostbtn" @click="bulkDiscard" :disabled="bulkLoading">
        {{ bulkLoading ? 'Processing…' : 'Discard' }}
      </button>
      <button class="ghostbtn" @click="clearSelection">Clear</button>
    </div>

    <ul v-else class="itemsList">
      <li v-for="item in items" :key="item.id" class="itemCard">
        <div class="itemHeader">
          <input
            type="checkbox"
            :checked="selectedItems.includes(item.id)"
            @change="toggleSelection(item.id)"
            class="itemCheckbox"
          />
          <span class="itemBadge" :class="`badge-${item.status}`">{{ item.status }}</span>
          <span v-if="item.source" class="sourceBadge">{{ item.source }}</span>
        </div>

        <div class="itemTitle">{{ item.title }}</div>

        <div v-if="item.summary" class="itemSummary">{{ truncate(item.summary, 240) }}</div>

        <div class="itemMeta">
          <span v-if="item.published_at" class="metaItem">
            Published: {{ formatDateTime(item.published_at) }}
          </span>
          <span v-if="item.fetched_at" class="metaItem">
            Fetched: {{ formatDateTime(item.fetched_at) }}
          </span>
          <span v-if="item.scheduled_for" class="metaItem">
            Scheduled: {{ formatDateTime(item.scheduled_for) }}
          </span>
        </div>

        <div class="itemActions">
          <button class="ghostbtn" @click="preview(item)">Preview</button>
          <button
            v-if="item.status === 'pending' || item.status === 'approved'"
            class="actionbtn"
            @click="publishNow(item)"
            :disabled="actionLoading[item.id] === 'publish'"
          >
            {{ actionLoading[item.id] === 'publish' ? 'Publishing…' : 'Publish now' }}
          </button>
          <button
            v-if="item.status === 'pending' || item.status === 'approved'"
            class="ghostbtn"
            @click="openSchedule(item)"
          >
            Schedule
          </button>
          <button
            v-if="item.status !== 'published' && item.status !== 'discarded'"
            class="ghostbtn"
            @click="discard(item)"
            :disabled="actionLoading[item.id] === 'discard'"
          >
            {{ actionLoading[item.id] === 'discard' ? 'Discarding…' : 'Discard' }}
          </button>
          <a
            v-if="item.url"
            :href="item.url"
            target="_blank"
            rel="noopener noreferrer"
            class="ghostbtn"
          >
            Open
          </a>
        </div>
      </li>
    </ul>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="pagination">
      <button
        class="paginationBtn"
        :disabled="pagination.current_page === 1"
        @click="goToPage(pagination.current_page - 1)"
      >
        Previous
      </button>
      <span class="paginationInfo">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
        ({{ pagination.total }} total)
      </span>
      <button
        class="paginationBtn"
        :disabled="pagination.current_page === pagination.last_page"
        @click="goToPage(pagination.current_page + 1)"
      >
        Next
      </button>
    </div>

    <!-- Preview Modal -->
    <div v-if="previewItem" class="modalOverlay" @click="closePreview">
      <div class="modalCard" @click.stop>
        <div class="modalHeader">
          <h2>Preview</h2>
          <button class="ghostbtn" @click="closePreview">&times;</button>
        </div>
        <div class="modalBody">
          <div class="formGroup">
            <label for="editTitle">Title:</label>
            <input
              id="editTitle"
              v-model="editItem.title"
              type="text"
              class="formInput"
            />
          </div>
          <div class="formGroup">
            <label for="editSummary">Summary:</label>
            <textarea
              id="editSummary"
              v-model="editItem.summary"
              class="formTextarea"
              rows="4"
            ></textarea>
          </div>
          <div class="formGroup">
            <label>URL:</label>
            <a v-if="editItem.url" :href="editItem.url" target="_blank" rel="noopener noreferrer">
              {{ editItem.url }}
            </a>
          </div>
          <div class="modalActions">
            <button class="actionbtn" @click="saveEdit">Save</button>
            <button class="ghostbtn" @click="closePreview">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Schedule Modal -->
    <div v-if="scheduleItem" class="modalOverlay" @click="closeSchedule">
      <div class="modalCard" @click.stop>
        <div class="modalHeader">
          <h2>Schedule</h2>
          <button class="ghostbtn" @click="closeSchedule">&times;</button>
        </div>
        <div class="modalBody">
          <label for="scheduleFor">Schedule for:</label>
          <input
            id="scheduleFor"
            v-model="scheduleFor"
            type="datetime-local"
            class="scheduleInput"
          />
          <div class="modalActions">
            <button class="actionbtn" @click="confirmSchedule">Schedule</button>
            <button class="ghostbtn" @click="closeSchedule">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api'

export default {
  name: 'TodayTab',
  data() {
    return {
      loading: false,
      error: null,
      successMessage: null,
      actionLoading: {},
      items: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 0,
      },
      searchQuery: '',
      sourceFilter: '',
      searchDebounce: null,
      selectedItems: [],
      bulkLoading: false,
      editItem: null,
      statusFilter: '',
      fetching: false,
      fetchResult: null,
      previewItem: null,
      scheduleItem: null,
      scheduleFor: '',
      publishingScheduled: false,
    }
  },
  watch: {
    statusFilter() {
      this.pagination.current_page = 1
      this.loadItems()
    },
    sourceFilter() {
      this.pagination.current_page = 1
      this.loadItems()
    },
    searchQuery() {
      clearTimeout(this.searchDebounce)
      this.searchDebounce = setTimeout(() => {
        this.pagination.current_page = 1
        this.loadItems()
      }, 500)
    },
  },
  created() {
    this.loadItems()
  },
  methods: {
    async loadItems() {
      this.loading = true
      this.error = null
      try {
        const params = {
          scope: 'today',
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
        }
        if (this.statusFilter) params.status = this.statusFilter
        if (this.sourceFilter) params.source = this.sourceFilter
        if (this.searchQuery) params.search = this.searchQuery
        
        const res = await api.get('/admin/astrobot/items', { params })
        this.items = res.data.data || []
        this.pagination = {
          current_page: res.data.current_page || 1,
          last_page: res.data.last_page || 1,
          per_page: res.data.per_page || 50,
          total: res.data.total || 0,
        }
      } catch (err) {
        this.error = err?.response?.data?.message || err?.message || 'Failed to load items.'
      } finally {
        this.loading = false
      }
    },

    async fetchNow() {
      this.fetching = true
      this.fetchResult = null
      try {
        const res = await api.post('/admin/astrobot/fetch')
        this.fetchResult = res.data
        // Refresh list
        await this.loadItems()
      } catch (err) {
        this.error = 'Fetch failed: ' + (err?.response?.data?.message || err?.message)
      } finally {
        this.fetching = false
      }
    },

    preview(item) {
      this.previewItem = item
      this.editItem = { ...item }
    },

    closePreview() {
      this.previewItem = null
      this.editItem = null
    },

    async saveEdit() {
      if (!this.editItem) return
      try {
        await api.put(`/admin/astrobot/items/${this.editItem.id}`, {
          title: this.editItem.title,
          summary: this.editItem.summary,
        })
        this.successMessage = 'Item updated successfully'
        this.closePreview()
        await this.loadItems()
      } catch (err) {
        this.error = 'Update failed: ' + (err?.response?.data?.message || err?.message)
      }
    },

    toggleSelection(itemId) {
      const index = this.selectedItems.indexOf(itemId)
      if (index > -1) {
        this.selectedItems.splice(index, 1)
      } else {
        this.selectedItems.push(itemId)
      }
    },

    clearSelection() {
      this.selectedItems = []
    },

    async bulkApprove() {
      await this.bulkAction('approve')
    },

    async bulkPublish() {
      if (!confirm('Publish selected items now?')) return
      await this.bulkAction('publish')
    },

    async bulkDiscard() {
      const reason = prompt('Reason for discard (optional):')
      await this.bulkAction('discard', { reason })
    },

    async bulkAction(action, data = {}) {
      this.bulkLoading = true
      try {
        await api.post('/admin/astrobot/bulk', {
          action,
          item_ids: this.selectedItems,
          ...data
        })
        this.successMessage = `Bulk ${action} completed successfully`
        this.clearSelection()
        await this.loadItems()
      } catch (err) {
        this.error = `Bulk ${action} failed: ` + (err?.response?.data?.message || err?.message)
      } finally {
        this.bulkLoading = false
      }
    },

    openSchedule(item) {
      this.scheduleItem = item
      // Default to tomorrow same time
      const tomorrow = new Date()
      tomorrow.setDate(tomorrow.getDate() + 1)
      this.scheduleFor = tomorrow.toISOString().slice(0, 16)
    },

    closeSchedule() {
      this.scheduleItem = null
      this.scheduleFor = ''
    },

    async confirmSchedule() {
      if (!this.scheduleItem || !this.scheduleFor) return
      try {
        await api.post(`/admin/astrobot/items/${this.scheduleItem.id}/schedule`, {
          scheduled_for: new Date(this.scheduleFor).toISOString(),
        })
        this.closeSchedule()
        await this.loadItems()
      } catch (err) {
        this.error = 'Schedule failed: ' + (err?.response?.data?.message || err?.message)
      }
    },

    async publishNow(item) {
      if (!confirm('Publish this item now?')) return
      this.actionLoading[item.id] = 'publish'
      try {
        await api.post(`/admin/astrobot/items/${item.id}/publish`)
        this.successMessage = 'Item published successfully'
        await this.loadItems()
      } catch (err) {
        this.error = 'Publish failed: ' + (err?.response?.data?.message || err?.message)
      } finally {
        delete this.actionLoading[item.id]
      }
    },

    async discard(item) {
      const reason = prompt('Reason for discard (optional):')
      this.actionLoading[item.id] = 'discard'
      try {
        await api.post(`/admin/astrobot/items/${item.id}/discard`, { reason })
        this.successMessage = 'Item discarded successfully'
        await this.loadItems()
      } catch (err) {
        this.error = 'Discard failed: ' + (err?.response?.data?.message || err?.message)
      } finally {
        delete this.actionLoading[item.id]
      }
    },

    async publishScheduledNow() {
      this.publishingScheduled = true
      try {
        const res = await api.post('/admin/astrobot/publish-scheduled')
        const message = res.data?.message || 'Scheduled items published.'
        this.successMessage = message
        // Refresh list
        await this.loadItems()
      } catch (err) {
        this.error = 'Publish scheduled failed: ' + (err?.response?.data?.message || err?.message)
      } finally {
        this.publishingScheduled = false
      }
    },

    truncate(text, max) {
      if (!text) return ''
      if (text.length <= max) return text
      return text.slice(0, max) + '…'
    },

    formatDateTime(value) {
      if (!value) return ''
      const d = new Date(value)
      return d.toLocaleString()
    },

    goToPage(page) {
      this.pagination.current_page = page
      this.loadItems()
    },
  },
}
</script>

<style scoped>
.todayTab {
  display: grid;
  gap: 1.5rem;
}

.tabActions {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.filterSelect {
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-text-secondary);
  background: var(--color-bg);
  color: var(--color-surface);
}

.searchInput {
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-text-secondary);
  background: var(--color-bg);
  color: var(--color-surface);
  min-width: 200px;
}

.bulkActions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  padding: 1rem;
  background: rgb(var(--color-primary-rgb) / 0.1);
  border: 1px solid rgb(var(--color-primary-rgb) / 0.3);
  border-radius: 1rem;
  margin-bottom: 1rem;
}

.bulkInfo {
  font-weight: 600;
  color: var(--color-primary);
  margin-right: auto;
}

.itemCheckbox {
  width: 1.25rem;
  height: 1.25rem;
  margin-right: 0.75rem;
  cursor: pointer;
}

.formGroup {
  display: grid;
  gap: 0.5rem;
}

.formGroup label {
  font-weight: 600;
  color: var(--color-surface);
}

.formInput,
.formTextarea {
  padding: 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-text-secondary);
  background: var(--color-bg);
  color: var(--color-surface);
  font-family: inherit;
}

.formTextarea {
  resize: vertical;
  min-height: 100px;
}

.fetchResult {
  display: flex;
  gap: 2rem;
  padding: 1rem;
  background: rgb(var(--color-bg-rgb) / 0.6);
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.resultItem {
  display: flex;
  flex-direction: column;
}

.resultLabel {
  font-size: 0.85rem;
  color: var(--color-text-secondary);
}

.resultValue {
  font-weight: 700;
  color: var(--color-surface);
}

.itemsList {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 1rem;
}

.itemCard {
  padding: 1.25rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.4);
  border-radius: 1rem;
  display: grid;
  gap: 0.75rem;
}

.itemHeader {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.itemBadge {
  padding: 0.25rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}

.badge-pending { background: #f59e0b; color: #fff; }
.badge-approved { background: #10b981; color: #fff; }
.badge-scheduled { background: #3b82f6; color: #fff; }
.badge-published { background: #6b7280; color: #fff; }
.badge-discarded { background: #ef4444; color: #fff; }
.badge-error { background: #dc2626; color: #fff; }

.stateSuccess {
  padding: 2rem;
  text-align: center;
  background: rgb(34 197 94 / 0.1);
  border: 1px solid rgb(34 197 94 / 0.3);
  border-radius: 1rem;
  color: var(--color-surface);
}

.stateError {
  padding: 2rem;
  text-align: center;
  background: rgb(239 68 68 / 0.1);
  border: 1px solid rgb(239 68 68 / 0.3);
  border-radius: 1rem;
  color: var(--color-surface);
}

.stateTitle {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.stateText {
  margin-bottom: 1rem;
  color: var(--color-text-secondary);
}

.sourceBadge {
  padding: 0.25rem 0.75rem;
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-primary);
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.itemTitle {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--color-surface);
}

.itemSummary {
  color: var(--color-text-secondary);
  line-height: 1.5;
}

.itemMeta {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  font-size: 0.85rem;
  color: var(--color-text-secondary);
}

.itemActions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.modalOverlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modalCard {
  background: var(--color-bg);
  border: 1px solid var(--color-text-secondary);
  border-radius: 1rem;
  padding: 1.5rem;
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.modalHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.modalBody {
  display: grid;
  gap: 1rem;
}

.modalActions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  margin-top: 1rem;
}

.scheduleInput {
  padding: 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-text-secondary);
  background: var(--color-bg);
  color: var(--color-surface);
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 2rem;
  padding: 1rem;
  background: rgb(var(--color-bg-rgb) / 0.6);
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.paginationBtn {
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-text-secondary);
  background: var(--color-bg);
  color: var(--color-surface);
  cursor: pointer;
  transition: all 0.2s ease-out;
}

.paginationBtn:hover:not(:disabled) {
  background: rgb(var(--color-bg-rgb) / 0.8);
}

.paginationBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.paginationInfo {
  font-size: 0.9rem;
  color: var(--color-text-secondary);
}
</style>
