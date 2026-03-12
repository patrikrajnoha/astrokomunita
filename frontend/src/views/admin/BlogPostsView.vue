<script setup>
import { ref, watch } from "vue";
import { useAdminBlogPostsEditor } from "./blog/useAdminBlogPostsEditor";

const {
  activeTab,
  aiTagFallbackUsed,
  aiTagSuggestions,
  aiTagSuggestionsError,
  aiTagSuggestionsLoading,
  applySelectedAiTags,
  canPublishNow,
  clearQuery,
  computeStatus,
  contentTabIssues,
  contentWordCount,
  coverPreview,
  data,
  deleting,
  error,
  form,
  formError,
  formatDate,
  hasQuery,
  hasSelectedAiTagSuggestions,
  isDirty,
  isEditing,
  isFocusActive,
  listDensity,
  loading,
  load,
  mediaTabIssues,
  metaTabIssues,
  nextPage,
  onCoverChange,
  onPerPageChange,
  page,
  pageRangeLabel,
  per_page,
  posts,
  previewBlocks,
  previewToc,
  prevPage,
  publishChecklist,
  publishDisabledReason,
  publishNow,
  query,
  readTimeFor,
  remove,
  save,
  saveCoverOnly,
  saveStateLabel,
  saving,
  selectedId,
  selectedPost,
  selectedStatus,
  selectPost,
  setListDensity,
  setPublishNow,
  setStatusFilter,
  showPreview,
  startNewPost,
  status,
  statusLabel,
  suggestAiTags,
  tagCount,
  tagsInput,
  titleLength,
  titleSlugPreview,
  toggleFocusMode,
} = useAdminBlogPostsEditor();

const isCreateModalOpen = ref(false);

async function openCreateModal() {
  const started = await startNewPost(false, false);
  if (!started) return;
  isCreateModalOpen.value = true;
}

async function closeCreateModal() {
  const closed = await startNewPost(false, false);
  if (!closed) return;
  isCreateModalOpen.value = false;
}

watch(isEditing, (value) => {
  if (value) {
    isCreateModalOpen.value = false;
  }
});
</script>
<template src="./blog/BlogPostsView.template.html"></template>

<style scoped src="./blog/BlogPostsView.css"></style>





