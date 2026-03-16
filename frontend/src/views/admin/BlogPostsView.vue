<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useAdminBlogPostsEditor } from "./blog/useAdminBlogPostsEditor";
import ArticleRichEditor from "@/components/admin/forms/ArticleRichEditor.vue";

const {
  aiTagFallbackUsed,
  aiSuggestActionLabel,
  aiTagSuggestionMode,
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
  formatMetricCount,
  formError,
  formatDate,
  hasQuery,
  hasSelectedAiTagSuggestions,
  isDirty,
  isEditing,
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
  previewHtml,
  previewToc,
  prevPage,
  publishChecklist,
  publishDisabledReason,
  publishNow,
  query,
  postClickCount,
  postReadCount,
  readTimeFor,
  remove,
  save,
  saveCoverOnly,
  saveStateClass,
  saveStateLabel,
  saving,
  selectedId,
  selectedPost,
  selectedStatus,
  selectPost,
  setAiTagSuggestionMode,
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
  unpublish,
} = useAdminBlogPostsEditor();

const isCreateModalOpen = ref(false);
const isPostModalOpen = ref(false);
const isSettingsModalOpen = ref(false);
const isEditorModalOpen = computed(
  () => isCreateModalOpen.value || isPostModalOpen.value
);

async function openCreateModal() {
  const started = await startNewPost(false);
  if (!started) return;
  isPostModalOpen.value = false;
  isCreateModalOpen.value = true;
}

async function closeCreateModal() {
  const closed = await startNewPost(false);
  if (!closed) return;
  isCreateModalOpen.value = false;
  isPostModalOpen.value = false;
  isSettingsModalOpen.value = false;
}

async function openPostModal(post) {
  if (!post) return;
  if (selectedId.value === post.id) {
    isCreateModalOpen.value = false;
    isPostModalOpen.value = true;
    return;
  }
  await selectPost(post);
  if (selectedId.value === post.id) {
    isCreateModalOpen.value = false;
    isPostModalOpen.value = true;
  }
}

async function closePostModal() {
  const closed = await startNewPost(false);
  if (!closed) return;
  isPostModalOpen.value = false;
  isSettingsModalOpen.value = false;
}

async function closeEditorModal() {
  if (isCreateModalOpen.value) {
    await closeCreateModal();
    return;
  }
  if (isPostModalOpen.value) {
    await closePostModal();
  }
}

function closeSettingsModal() {
  isSettingsModalOpen.value = false;
}

function toggleSettingsModal() {
  if (!isEditorModalOpen.value) return;
  isSettingsModalOpen.value = !isSettingsModalOpen.value;
}

function handleWindowKeydown(event) {
  if (event.key === "Escape" && isSettingsModalOpen.value) {
    isSettingsModalOpen.value = false;
    return;
  }
  if (event.key === "Escape" && isEditorModalOpen.value) {
    closeEditorModal();
  }
}

onMounted(() => {
  window.addEventListener("keydown", handleWindowKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", handleWindowKeydown);
});

watch(isEditing, (value) => {
  if (value) {
    isCreateModalOpen.value = false;
  } else {
    isPostModalOpen.value = false;
    isSettingsModalOpen.value = false;
  }
});

watch(showPreview, (value) => {
  if (value) {
    isSettingsModalOpen.value = false;
  }
});

watch(isCreateModalOpen, (value) => {
  if (value) {
    isPostModalOpen.value = false;
  }
});

watch(isPostModalOpen, (value) => {
  if (value) {
    isCreateModalOpen.value = false;
  }
});
</script>
<template src="./blog/BlogPostsView.template.html"></template>

<style scoped src="./blog/BlogPostsView.css"></style>





