<template>
  <div class="blogPostsTable">
    <!-- Loading state -->
    <LoadingSpinner v-if="loading" text="Načítavam články..." />
    
    <!-- Error state -->
    <div v-else-if="error" class="errorState">
      <div class="errorIcon">⚠️</div>
      <div class="errorText">{{ error }}</div>
      <button class="btn btn-outline" @click="$emit('refresh')">Skúsiť znova</button>
    </div>
    
    <!-- Empty state -->
    <div v-else-if="!data?.data?.length" class="emptyState">
      <div class="emptyIcon">📝</div>
      <div class="emptyText">Žiadne články nenájdené</div>
    </div>
    
    <!-- Table -->
    <div v-else class="tableContainer">
      <table class="table">
        <thead>
          <tr>
            <th class="tableHeader">Názov</th>
            <th class="tableHeader">Status</th>
            <th class="tableHeader">Autor</th>
            <th class="tableHeader">Vytvorené</th>
            <th class="tableHeader">Publikované</th>
            <th class="tableHeader">Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="post in data.data"
            :key="post.id"
            class="tableRow"
            @click="$emit('row-click', post)"
          >
            <td class="tableCell">
              <div class="postTitle">
                {{ post.title }}
                <span v-if="post.excerpt" class="postExcerpt">
                  {{ truncate(post.excerpt, 100) }}
                </span>
              </div>
            </td>
            <td class="tableCell">
              <StatusBadge :status="post.published_at ? 'published' : 'draft'" />
            </td>
            <td class="tableCell">
              <div class="authorInfo">
                <div>{{ post.author?.name || 'Neznámy' }}</div>
                <div v-if="post.author?.email" class="authorEmail">
                  {{ post.author.email }}
                </div>
              </div>
            </td>
            <td class="tableCell">
              <div class="dateInfo">{{ formatDate(post.created_at) }}</div>
            </td>
            <td class="tableCell">
              <div class="dateInfo">
                <div v-if="post.published_at">
                  {{ formatDate(post.published_at) }}
                </div>
                <div v-else class="unpublished">Nepublikované</div>
              </div>
            </td>
            <td class="tableCell tableActions">
              <div class="actionButtons" @click.stop>
                <button
                  class="actionBtn actionBtn--primary"
                  @click="$emit('edit', post)"
                >
                  Upraviť
                </button>
                <button
                  v-if="!post.published_at"
                  class="actionBtn actionBtn--success"
                  @click="$emit('publish', post)"
                >
                  Publikovať
                </button>
                <button
                  v-if="post.published_at"
                  class="actionBtn actionBtn--secondary"
                  @click="$emit('unpublish', post)"
                >
                  Skryť
                </button>
                <button
                  class="actionBtn actionBtn--danger"
                  @click="$emit('delete', post)"
                >
                  Vymazať
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { formatDate } from '@/utils/dateUtils.js';
import { truncate } from '@/utils/textUtils.js';
import StatusBadge from '@/components/admin/shared/StatusBadge.vue';
import LoadingSpinner from '@/components/admin/shared/LoadingSpinner.vue';

defineProps({
  data: {
    type: Object,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: null
  }
});

defineEmits([
  'row-click',
  'edit',
  'delete',
  'publish',
  'unpublish',
  'refresh'
]);
</script>

<style scoped>
.blogPostsTable {
  background: var(--color-background);
  border-radius: 0.5rem;
  overflow: hidden;
}

.errorState, .emptyState {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.errorIcon, .emptyIcon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.errorText, .emptyText {
  color: var(--color-text-secondary);
  margin-bottom: 1.5rem;
  font-size: 1.125rem;
}

.tableContainer {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: 0.5rem;
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.tableHeader {
  background: var(--color-background-secondary);
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.tableRow {
  border-bottom: 1px solid var(--color-border);
  cursor: pointer;
  transition: background-color 0.2s;
}

.tableRow:hover {
  background: var(--color-background-hover);
}

.tableRow:last-child {
  border-bottom: none;
}

.tableCell {
  padding: 1rem;
  vertical-align: top;
}

.postTitle {
  font-weight: 500;
  color: var(--color-text);
  margin-bottom: 0.25rem;
}

.postExcerpt {
  display: block;
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin-top: 0.25rem;
}

.authorInfo {
  font-size: 0.875rem;
}

.authorEmail {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin-top: 0.125rem;
}

.dateInfo {
  font-size: 0.875rem;
  color: var(--color-text);
}

.unpublished {
  color: var(--color-text-secondary);
  font-style: italic;
}

.tableActions {
  width: 1px; /* Minimize width */
  white-space: nowrap;
}

.actionButtons {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.actionBtn {
  padding: 0.25rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.actionBtn--primary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.actionBtn--primary:hover {
  background: var(--color-primary-hover);
}

.actionBtn--secondary {
  background: var(--color-background);
  color: var(--color-text);
  border-color: var(--color-border);
}

.actionBtn--secondary:hover {
  background: var(--color-background-hover);
}

.actionBtn--success {
  background: var(--color-success);
  color: white;
  border-color: var(--color-success);
}

.actionBtn--success:hover {
  background: var(--color-success-hover);
}

.actionBtn--danger {
  background: var(--color-danger);
  color: white;
  border-color: var(--color-danger);
}

.actionBtn--danger:hover {
  background: var(--color-danger-hover);
}

@media (max-width: 768px) {
  .tableContainer {
    border-radius: 0;
  }
  
  .tableHeader, .tableCell {
    padding: 0.5rem;
  }
  
  .actionButtons {
    flex-direction: column;
    gap: 0.125rem;
  }
  
  .actionBtn {
    font-size: 0.625rem;
    padding: 0.1875rem 0.375rem;
  }
}
</style>
