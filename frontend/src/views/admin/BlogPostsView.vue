<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useAdminBlogPostsEditor } from "./blog/useAdminBlogPostsEditor";
import ArticleRichEditor from "@/components/admin/forms/ArticleRichEditor.vue";

const {
  aiTagFallbackUsed,
  aiLoadingPercent,
  aiSuggestActionLabel,
  aiTagSuggestionMode,
  aiTagSuggestions,
  aiTagSuggestionsError,
  aiTagSuggestionsLoading,
  applySelectedAiTags,
  canPublishNow,
  closeEditor,
  clearQuery,
  computeStatus,
  contentWordCount,
  coverFile,
  coverInputEl,
  coverInputLabel,
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
  hidePublished,
  isEditing,
  loading,
  load,
  nextPage,
  onCoverChange,
  page,
  pageRangeLabel,
  posts,
  openCoverPicker,
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
  saveStateClass,
  saveStateLabel,
  saving,
  selectedId,
  selectedPost,
  selectedStatus,
  selectPost,
  setAiTagSuggestionMode,
  setPublishNow,
  setStatusFilter,
  showPreview,
  startNewPost,
  status,
  statusLabel,
  suggestAiTags,
  tagsInput,
  titleLength,
  titleSlugPreview,
  uploadInlineImage,
  unhidePublished,
  unpublish,
} = useAdminBlogPostsEditor();

const isSettingsOpen = ref(false);
const isListCollapsed = ref(false);

watch(isEditing, (v) => { if (v) isListCollapsed.value = true; });

async function handleNewPost() {
  await startNewPost(false);
}

async function handleBack() {
  await closeEditor(false);
}

function toggleSettings() {
  isSettingsOpen.value = !isSettingsOpen.value;
}

function handleKeydown(e) {
  if (e.key === "Escape" && isSettingsOpen.value) {
    isSettingsOpen.value = false;
  }
}

onMounted(() => window.addEventListener("keydown", handleKeydown));
onBeforeUnmount(() => window.removeEventListener("keydown", handleKeydown));

watch(showPreview, (v) => { if (v) isSettingsOpen.value = false; });
watch(isEditing, (v) => { if (!v) isSettingsOpen.value = false; });
</script>

<template src="./blog/BlogPostsView.template.html"></template>
<style scoped src="./blog/BlogPostsView.css"></style>
