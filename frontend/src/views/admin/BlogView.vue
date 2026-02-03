<template>
  <div class="page">
    <header class="pageHeader">
      <h1>Blog Admin</h1>
      <button class="btn btn-primary" @click="showBlogForm = true">
        + Nový článok
      </button>
    </header>

    <!-- Filters -->
    <BlogFilters
      v-model:status="filters.status"
      v-model:search="filters.search"
      @filter="blogTable.refresh"
      @clear="clearFilters"
    />

    <!-- Blog posts table -->
    <BlogPostsTable
      :data="blogTable.data"
      :loading="blogTable.loading"
      :error="blogTable.error"
      :pagination="blogTable.pagination"
      @row-click="editPost"
      @edit="editPost"
      @delete="deletePost"
      @publish="publishPost"
      @unpublish="unpublishPost"
    />

    <!-- Pagination -->
    <PaginationBar
      v-if="blogTable.pagination"
      :pagination="blogTable.pagination"
      :has-prev-page="blogTable.hasPrevPage"
      :has-next-page="blogTable.hasNextPage"
      @prev-page="blogTable.prevPage"
      @next-page="blogTable.nextPage"
      @per-page-change="blogTable.setPerPage"
    />

    <!-- Blog form modal -->
    <BlogPostForm
      v-if="showBlogForm"
      :post="editingPost"
      @close="closeBlogForm"
      @save="saveBlogPost"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useAdminTable } from '@/composables/useAdminTable.js';
import { useNotifications } from '@/composables/useNotifications.js';
import { getBlogPosts, deleteBlogPost as deleteBlogPostApi, publishBlogPost, unpublishBlogPost } from '@/services/api/admin/blog.js';

// Components
import BlogFilters from '@/components/admin/filters/BlogFilters.vue';
import BlogPostsTable from '@/components/admin/tables/BlogPostsTable.vue';
import PaginationBar from '@/components/admin/shared/PaginationBar.vue';
import BlogPostForm from '@/components/admin/forms/BlogPostForm.vue';

const { success, handleApiError } = useNotifications();

// State
const showBlogForm = ref(false);
const editingPost = ref(null);

// Table instance
const blogTable = useAdminTable(getBlogPosts, {
  defaultFilters: { status: '' }
});

// Filters
const filters = ref({
  status: '',
  search: ''
});

// Methods
function editPost(post) {
  editingPost.value = post;
  showBlogForm.value = true;
}

async function deletePost(post) {
  if (!confirm(`Naozaj chcete vymazať článok "${post.title}"?`)) {
    return;
  }

  try {
    await deleteBlogPostApi(post.id);
    success('Článok vymazaný');
    blogTable.refresh();
  } catch (err) {
    handleApiError(err);
  }
}

async function publishPost(post) {
  try {
    await publishBlogPost(post.id);
    success('Článok publikovaný');
    blogTable.refresh();
  } catch (err) {
    handleApiError(err);
  }
}

async function unpublishPost(post) {
  try {
    await unpublishBlogPost(post.id);
    success('Článok skrytý');
    blogTable.refresh();
  } catch (err) {
    handleApiError(err);
  }
}

async function saveBlogPost() {
  try {
    // Tu by bola logika pre vytvorenie/aktualizáciu článku
    success('Článok uložený');
    closeBlogForm();
    blogTable.refresh();
  } catch (err) {
    handleApiError(err);
  }
}

function closeBlogForm() {
  showBlogForm.value = false;
  editingPost.value = null;
}

function clearFilters() {
  filters.value = {
    status: '',
    search: ''
  };
  blogTable.setFilters(filters.value);
}

// Watch filters
watch(filters, (newFilters) => {
  blogTable.setFilters(newFilters);
}, { deep: true });
</script>

<style scoped>
.pageHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.pageHeader h1 {
  margin: 0;
  font-size: 1.875rem;
  font-weight: 700;
  color: var(--color-text);
}

@media (max-width: 768px) {
  .pageHeader {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
}
</style>
