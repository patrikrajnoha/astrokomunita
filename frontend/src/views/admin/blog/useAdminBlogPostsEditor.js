import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { blogPosts } from "@/services/blogPosts";
import { useConfirm } from "@/composables/useConfirm";
import { useToast } from "@/composables/useToast";
import {
  computeStatus,
  formatDate,
  fromDateTimeLocal,
  getInitialListDensity,
  persistListDensity,
  readTimeFor,
  slugifyHeading,
  statusLabel,
  toDateTimeLocal,
} from "../blogPostsView.utils";
import {
  hasHtmlMarkup,
  renderArticleContent,
  sanitizeArticleHtml,
} from "@/utils/articleContent";

const LIST_DENSITY_STORAGE_KEY = "admin.blog.listDensity";
const AUTO_SAVE_INTERVAL_MS = 60_000;

export function useAdminBlogPostsEditor() {
  const loading = ref(false);
  const saving = ref(false);
  const deleting = ref(false);
  const error = ref(null);
  const formError = ref(null);

  const status = ref("");
  const page = ref(1);
  const per_page = ref(10);
  const data = ref(null);

  const selectedId = ref(null);
  const selectedPostRecord = ref(null);
  const form = ref({
    title: "",
    content: "",
    published_at: "",
  });
  const coverFile = ref(null);
  const coverPreview = ref("");
  const tagsInput = ref("");
  const showPreview = ref(false);
  const focusMode = ref(false);
  const listDensity = ref(getInitialListDensity(LIST_DENSITY_STORAGE_KEY));
  const query = ref("");
  const activeTab = ref("content");
  const aiTagSuggestionsLoading = ref(false);
  const aiTagSuggestionsError = ref("");
  const aiTagSuggestions = ref([]);
  const aiTagFallbackUsed = ref(false);
  const aiTagSuggestionMode = ref("existing_only");
  const formSnapshot = ref("");
  const lastSavedAt = ref(null);
  const loadToken = ref(0);
  const { confirm } = useConfirm();
  const toast = useToast();

  let queryDebounceTimer = null;
  let localCoverObjectUrl = "";
  let autoSaveTimer = null;

  const posts = computed(() => data.value?.data || []);
  const hasQuery = computed(() => query.value.trim() !== "");
  const isFocusActive = computed(() => focusMode.value);
  const isEditing = computed(() => !!selectedId.value);
  const selectedPost = computed(() => selectedPostRecord.value);
  const selectedStatus = computed(() => {
    if (!isEditing.value) return "draft";
    if (selectedPost.value) return computeStatus(selectedPost.value);
    const fallback = fromDateTimeLocal(form.value.published_at);
    if (!fallback) return "draft";
    return computeStatus({ published_at: fallback });
  });
  const hasSelectedAiTagSuggestions = computed(() =>
    aiTagSuggestions.value.some((item) => item.checked)
  );
  const hasAiSuggestionResult = computed(
    () => aiTagSuggestions.value.length > 0 || aiTagSuggestionsError.value !== ""
  );
  const aiSuggestActionLabel = computed(() =>
    aiTagSuggestionsLoading.value
      ? "Navrhujem..."
      : hasAiSuggestionResult.value
      ? "Navrhnut znova"
      : "Navrhnut tagy"
  );
  const isDirty = computed(() => makeFormSnapshot() !== formSnapshot.value);
  const titleLength = computed(() => String(form.value.title || "").trim().length);
  const renderedContent = computed(() => renderArticleContent(form.value.content));
  const contentPlainText = computed(() => renderedContent.value.plainText);
  const contentWordCount = computed(
    () => String(contentPlainText.value || "").trim().split(/\s+/).filter(Boolean).length
  );
  const hasCover = computed(() => Boolean(coverPreview.value));
  const tagCount = computed(() => parseTagsInput().length);
  const titleSlugPreview = computed(() =>
    slugifyHeading(form.value.title || "novy-clanok")
  );
  const contentTabIssues = computed(
    () => Number(titleLength.value === 0) + Number(contentWordCount.value < 80)
  );
  const metaTabIssues = computed(() => Number(tagCount.value === 0));
  const mediaTabIssues = computed(() => Number(!hasCover.value));
  const publishChecklist = computed(() => [
    {
      key: "title",
      label: "Nadpis je vyplneny",
      done: titleLength.value >= 8,
      required: true,
    },
    {
      key: "content",
      label: "Obsah ma aspon 80 slov",
      done: contentWordCount.value >= 80,
      required: true,
    },
    {
      key: "tags",
      label: "Clanok ma aspon 1 tag",
      done: tagCount.value > 0,
      required: false,
    },
    {
      key: "cover",
      label: "Titulny obrazok je vybrany",
      done: hasCover.value,
      required: false,
    },
  ]);
  const publishMissing = computed(() =>
    publishChecklist.value.filter((item) => !item.done)
  );
  const publishMissingRequired = computed(() =>
    publishChecklist.value.filter((item) => item.required && !item.done)
  );
  const canPublishNow = computed(() => publishMissingRequired.value.length === 0);
  const publishDisabledReason = computed(() => {
    if (canPublishNow.value) return "";
    const labels = publishMissingRequired.value.map((item) => item.label.toLowerCase());
    return `Pred publikovanim dopln: ${labels.join(", ")}.`;
  });
  const pageRangeLabel = computed(() => {
    const from = data.value?.from || 0;
    const to = data.value?.to || 0;
    const total = data.value?.total || 0;
    return `${from}-${to} / ${total}`;
  });
  const canAutoSave = computed(() => {
    if (loading.value || saving.value || deleting.value) return false;
    if (!isDirty.value) return false;
    if (titleLength.value < 3) return false;
    if (String(contentPlainText.value || "").trim().length < 10) return false;
    return true;
  });
  const saveStateLabel = computed(() => {
    if (saving.value) return "Ukladam...";
    if (isDirty.value) return "Neulozene zmeny";
    if (lastSavedAt.value instanceof Date && !Number.isNaN(lastSavedAt.value.getTime())) {
      const at = lastSavedAt.value.toLocaleTimeString("sk-SK", {
        hour: "2-digit",
        minute: "2-digit",
      });
      return `Ulozene ${at}`;
    }
    return "Bez zmien";
  });

  function toMetricCount(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed < 0) return null;
    return Math.floor(parsed);
  }

  function postReadCount(post) {
    const readMetric =
      toMetricCount(post?.read_count) ??
      toMetricCount(post?.reads_count) ??
      toMetricCount(post?.reads);
    if (readMetric !== null) return readMetric;
    return toMetricCount(post?.views_count) ?? toMetricCount(post?.views) ?? 0;
  }

  function postClickCount(post) {
    const clickMetric =
      toMetricCount(post?.click_count) ??
      toMetricCount(post?.clicks_count) ??
      toMetricCount(post?.clicks);
    if (clickMetric !== null) return clickMetric;
    return postReadCount(post);
  }

  function formatMetricCount(value) {
    return Number(value || 0).toLocaleString("sk-SK");
  }

  function resetAiTagSuggestions() {
    aiTagSuggestionsLoading.value = false;
    aiTagSuggestionsError.value = "";
    aiTagSuggestions.value = [];
    aiTagFallbackUsed.value = false;
  }

  function setAiTagSuggestionMode(mode) {
    if (mode !== "existing_only" && mode !== "allow_new") return;
    if (aiTagSuggestionMode.value === mode) return;
    aiTagSuggestionMode.value = mode;
    resetAiTagSuggestions();
  }

  function parseTagsInput() {
    return tagsInput.value
      .split(",")
      .map((t) => t.trim())
      .filter(Boolean);
  }

  function normalizeContentForStorage(value) {
    const raw = String(value || "").trim();
    if (!raw) return "";
    if (!hasHtmlMarkup(raw)) return raw;
    return sanitizeArticleHtml(raw);
  }

  function makeFormSnapshot() {
    const normalizedTags = parseTagsInput()
      .map((tag) => tag.toLowerCase())
      .sort();

    return JSON.stringify({
      selected_id: selectedId.value || 0,
      title: String(form.value.title || "").trim(),
      content: normalizeContentForStorage(form.value.content),
      published_at: form.value.published_at || "",
      tags: normalizedTags,
      cover_file: coverFile.value ? `${coverFile.value.name}:${coverFile.value.size}` : "",
      cover_preview: coverPreview.value || "",
    });
  }

  function syncFormSnapshot() {
    formSnapshot.value = makeFormSnapshot();
  }

  function clearLocalCoverPreview() {
    if (localCoverObjectUrl) {
      URL.revokeObjectURL(localCoverObjectUrl);
      localCoverObjectUrl = "";
    }
  }

  function applyEmptyForm() {
    clearLocalCoverPreview();
    selectedId.value = null;
    selectedPostRecord.value = null;
    form.value = {
      title: "",
      content: "",
      published_at: "",
    };
    coverFile.value = null;
    coverPreview.value = "";
    tagsInput.value = "";
    formError.value = null;
    showPreview.value = false;
    activeTab.value = "content";
    lastSavedAt.value = null;
    resetAiTagSuggestions();
    syncFormSnapshot();
  }

  function applyPostToForm(post) {
    clearLocalCoverPreview();
    selectedId.value = post.id;
    selectedPostRecord.value = post;
    form.value = {
      title: post.title || "",
      content: post.content || "",
      published_at: toDateTimeLocal(post.published_at),
    };
    coverFile.value = null;
    coverPreview.value = post.cover_image_url || "";
    tagsInput.value = (post.tags || []).map((t) => t.name).join(", ");
    formError.value = null;
    showPreview.value = false;
    activeTab.value = "content";
    const updated = post?.updated_at ? new Date(post.updated_at) : null;
    lastSavedAt.value =
      updated instanceof Date && !Number.isNaN(updated.getTime()) ? updated : null;
    resetAiTagSuggestions();
    syncFormSnapshot();
  }

  async function confirmDiscardChanges() {
    if (!isDirty.value) return true;
    return confirm({
      title: "Zahodit zmeny?",
      message: "Mas neulozene zmeny. Chces pokracovat bez ulozenia?",
      confirmText: "Zahodit",
      cancelText: "Ponechat",
      variant: "danger",
    });
  }

  async function startNewPost(force = false, shouldEnableFocus = true) {
    if (!force) {
      const ok = await confirmDiscardChanges();
      if (!ok) return false;
    }
    applyEmptyForm();
    focusMode.value = Boolean(shouldEnableFocus);
    return true;
  }

  async function selectPost(post, force = false) {
    if (!post) return;
    if (!force && selectedId.value === post.id) return;

    if (!force) {
      const ok = await confirmDiscardChanges();
      if (!ok) return;
    }

    applyPostToForm(post);
  }

  function setPublishNow() {
    form.value.published_at = toDateTimeLocal(new Date().toISOString());
  }

  async function publishNow() {
    if (!canPublishNow.value) {
      toast.error(
        publishDisabledReason.value || "Pred publikovanim treba doplnit povinne polia."
      );
      return;
    }

    const missingRecommended = publishChecklist.value.filter(
      (item) => !item.required && !item.done
    );
    const missingRecommendedCopy = missingRecommended
      .map((item) => `- ${item.label}`)
      .join("\n");
    const ok = await confirm({
      title: "Publikovat clanok",
      message: missingRecommended.length
        ? `Pred publikovanim odporucame doplnit:\n${missingRecommendedCopy}\n\nChces pokracovat a publikovat teraz?`
        : "Clanok bude publikovany okamzite. Chces pokracovat?",
      confirmText: "Publikovat",
      cancelText: "Spat",
    });
    if (!ok) return;
    setPublishNow();
    await save();
  }

  function setStatusFilter(value) {
    if (status.value === value) return;
    status.value = value;
    page.value = 1;
    load();
  }

  function onPerPageChange() {
    page.value = 1;
    load();
  }

  function clearQuery() {
    query.value = "";
  }

  function setListDensity(value) {
    if (value !== "comfortable" && value !== "dense") return;
    listDensity.value = value;
    persistListDensity(LIST_DENSITY_STORAGE_KEY, value);
  }

  function queueSearchLoad() {
    if (queryDebounceTimer) {
      clearTimeout(queryDebounceTimer);
    }
    queryDebounceTimer = setTimeout(() => {
      queryDebounceTimer = null;
      load();
    }, 260);
  }

  const previewHtml = computed(() => renderedContent.value.html);
  const previewToc = computed(() => renderedContent.value.toc);

  function onCoverChange(event) {
    const file = event.target.files?.[0] || null;
    coverFile.value = file;

    clearLocalCoverPreview();

    if (file) {
      localCoverObjectUrl = URL.createObjectURL(file);
      coverPreview.value = localCoverObjectUrl;
      return;
    }

    coverPreview.value = selectedPost.value?.cover_image_url || "";
  }

  async function load() {
    const token = ++loadToken.value;
    loading.value = true;
    error.value = null;

    try {
      const payload = await blogPosts.adminList({
        status: status.value || undefined,
        q: query.value.trim() || undefined,
        page: page.value,
        per_page: per_page.value,
      });

      if (token !== loadToken.value) return;

      data.value = payload;
      if (selectedId.value) {
        const refreshed = (payload?.data || []).find((p) => p.id === selectedId.value);
        if (refreshed) {
          selectedPostRecord.value = refreshed;
        }
      }
    } catch (e) {
      if (token !== loadToken.value) return;
      error.value = e?.response?.data?.message || "Nepodarilo sa nacitat clanky.";
    } finally {
      if (token === loadToken.value) {
        loading.value = false;
      }
    }
  }

  async function save(options = {}) {
    const silent = Boolean(options?.silent);
    if (!silent) {
      formError.value = null;
    }
    saving.value = true;

    const wasEditing = isEditing.value;

    try {
      const tags = parseTagsInput();

      const payload = {
        title: form.value.title?.trim(),
        content: normalizeContentForStorage(form.value.content),
        published_at: fromDateTimeLocal(form.value.published_at),
        cover_image: coverFile.value || undefined,
        tags,
      };

      const saved = wasEditing
        ? await blogPosts.adminUpdate(selectedId.value, payload)
        : await blogPosts.adminCreate(payload);

      if (saved?.id) {
        selectedId.value = saved.id;
        selectedPostRecord.value = saved;
      }

      await load();
      if (saved?.id) {
        const found = posts.value.find((p) => p.id === saved.id);
        if (found) {
          await selectPost(found, true);
        } else {
          syncFormSnapshot();
        }
      } else {
        syncFormSnapshot();
      }

      lastSavedAt.value = new Date();
      if (!silent) {
        toast.success(wasEditing ? "Clanok bol ulozeny." : "Clanok bol vytvoreny.");
      }
    } catch (e) {
      const msg = e?.response?.data?.message || "Nepodarilo sa ulozit clanok.";
      const fieldErrors = e?.response?.data?.errors;
      if (!silent) {
        if (fieldErrors) {
          const firstField = Object.keys(fieldErrors)[0];
          const firstMessage = fieldErrors[firstField]?.[0];
          formError.value = firstMessage || msg;
        } else {
          formError.value = msg;
        }
      }
    } finally {
      saving.value = false;
    }
  }

  function triggerAutoSave() {
    if (!canAutoSave.value) return;
    save({ silent: true });
  }

  async function ensureDraftForAiSuggestions() {
    if (isEditing.value && selectedId.value) return true;

    const hasMinimumContent =
      titleLength.value >= 3 && String(contentPlainText.value || "").trim().length >= 10;

    if (!hasMinimumContent) {
      aiTagSuggestionsError.value =
        "Pre AI tagy dopln aspon kratky nadpis a obsah (min. 10 znakov).";
      return false;
    }

    await save({ silent: true });

    if (!isEditing.value || !selectedId.value) {
      aiTagSuggestionsError.value =
        "Nepodarilo sa vytvorit koncept clanku pre AI navrh tagov.";
      return false;
    }

    return true;
  }

  async function suggestAiTags() {
    if (aiTagSuggestionsLoading.value) return;

    if (!isEditing.value || !selectedId.value) {
      const ready = await ensureDraftForAiSuggestions();
      if (!ready) return;
    }

    aiTagSuggestionsLoading.value = true;
    aiTagSuggestionsError.value = "";
    aiTagSuggestions.value = [];
    aiTagFallbackUsed.value = false;

    try {
      const response = await blogPosts.adminSuggestTags(selectedId.value, {
        mode: aiTagSuggestionMode.value,
      });
      const items = Array.isArray(response?.tags) ? response.tags : [];

      aiTagSuggestions.value = items
        .slice(0, 5)
        .map((item) => ({
          id: Number(item?.id || 0),
          name: String(item?.name || "").trim(),
          reason: String(item?.reason || "").trim(),
          checked: true,
        }))
        .filter((item) => item.id >= 0 && item.name && item.reason);
      aiTagFallbackUsed.value = Boolean(response?.fallback_used);

      if (aiTagSuggestions.value.length === 0) {
        if (response?.reason === "provider_error") {
          aiTagSuggestionsError.value = "AI je docasne nedostupne.";
        } else if (response?.reason === "no_existing_tags") {
          aiTagSuggestionsError.value =
            "Zatial nemas ziadne existujuce tagy. Prepni na 'Aj nove'.";
        } else {
          aiTagSuggestionsError.value = response?.fallback_used
            ? "Nenasli sa vhodne fallback tagy."
            : "Nenasli sa vhodne AI tagy.";
        }
      }
    } catch (e) {
      aiTagSuggestionsError.value =
        e?.response?.data?.message || "Nepodarilo sa navrhnut tagy.";
    } finally {
      aiTagSuggestionsLoading.value = false;
    }
  }

  async function applySelectedAiTags() {
    const selected = aiTagSuggestions.value
      .filter((item) => item.checked)
      .map((item) => ({
        id: Number(item?.id || 0),
        name: String(item?.name || "").trim(),
      }))
      .filter((item) => item.id >= 0 && item.name);

    if (selected.length === 0) return;
    const selectedExisting = selected.filter((item) => item.id > 0);
    const hasNewTagNames = selected.some((item) => item.id === 0);

    const existingTagIds = Array.isArray(selectedPost.value?.tags)
      ? selectedPost.value.tags
          .map((tag) => Number(tag?.id || 0))
          .filter((id) => id > 0)
      : [];
    const mergedTagIds = Array.from(
      new Set([...existingTagIds, ...selectedExisting.map((item) => item.id)])
    );

    const existingTags = parseTagsInput();
    const normalizedExistingNames = new Set(
      existingTags.map((tag) => String(tag || "").trim().toLowerCase()).filter(Boolean)
    );
    const mergedNames = [...existingTags];

    selected.forEach((item) => {
      const key = item.name.toLowerCase();
      if (!key || normalizedExistingNames.has(key)) return;
      normalizedExistingNames.add(key);
      mergedNames.push(item.name);
    });

    tagsInput.value = mergedNames.join(", ");

    if (!isEditing.value || !selectedId.value) {
      return;
    }

    saving.value = true;
    formError.value = null;

    try {
      const attachedBeforeIds = Array.isArray(selectedPost.value?.tags)
        ? selectedPost.value.tags
            .map((tag) => Number(tag?.id || 0))
            .filter((id) => id > 0)
        : [];

      const payload = hasNewTagNames
        ? { tags: mergedNames }
        : { tag_ids: mergedTagIds };
      const saved = await blogPosts.adminUpdate(selectedId.value, payload);
      if (saved?.id) {
        selectedPostRecord.value = saved;
      }

      const tagSync = saved?.tag_sync || null;
      await load();
      if (saved?.id) {
        const found = posts.value.find((p) => p.id === saved.id);
        if (found) {
          await selectPost(found, true);
        } else {
          syncFormSnapshot();
        }
      } else {
        syncFormSnapshot();
      }
      lastSavedAt.value = new Date();
      if (tagSync && typeof tagSync === "object") {
        const createdNew = Number(tagSync.created_new || 0);
        const attachedExisting = Number(tagSync.attached_existing || 0);
        const addedTotal = Number(tagSync.added_total || 0);
        if (addedTotal <= 0) {
          toast.success("Tagy uz boli priradene.");
        } else {
          const parts = [];
          if (attachedExisting > 0) parts.push(`existujuce: ${attachedExisting}`);
          if (createdNew > 0) parts.push(`nove: ${createdNew}`);
          const suffix = parts.length ? ` (${parts.join(", ")})` : "";
          toast.success(`Tagy boli pridane${suffix}.`);
        }
      } else {
        const attachedAfterIds = Array.isArray(saved?.tags)
          ? saved.tags
              .map((tag) => Number(tag?.id || 0))
              .filter((id) => id > 0)
          : attachedBeforeIds;
        const addedCount = Math.max(
          0,
          attachedAfterIds.filter((id) => !attachedBeforeIds.includes(id)).length
        );
        toast.success(addedCount > 0 ? `Tagy boli pridane (${addedCount}).` : "Tagy uz boli priradene.");
      }
    } catch (e) {
      formError.value = e?.response?.data?.message || "Nepodarilo sa pridat tagy.";
      toast.error(formError.value);
    } finally {
      saving.value = false;
    }
  }

  async function saveCoverOnly() {
    if (!selectedId.value) return;
    if (!coverFile.value) {
      formError.value = "Najprv vyber titulny obrazok.";
      return;
    }

    saving.value = true;
    formError.value = null;

    try {
      const saved = await blogPosts.adminUpdate(selectedId.value, {
        cover_image: coverFile.value,
      });
      if (saved?.id) {
        selectedPostRecord.value = saved;
      }
      await load();
      if (saved?.id) {
        const found = posts.value.find((p) => p.id === saved.id);
        if (found) {
          await selectPost(found, true);
        } else {
          syncFormSnapshot();
        }
      } else {
        syncFormSnapshot();
      }
      lastSavedAt.value = new Date();
      toast.success("Titulny obrazok bol ulozeny.");
    } catch (e) {
      formError.value =
        e?.response?.data?.message || "Nepodarilo sa ulozit titulny obrazok.";
      toast.error(formError.value);
    } finally {
      saving.value = false;
    }
  }

  async function remove() {
    if (!selectedId.value) return;
    const ok = await confirm({
      title: "Vymazat clanok",
      message: "Naozaj chces vymazat tento clanok?",
      confirmText: "Vymazat",
      cancelText: "Zrusit",
      variant: "danger",
    });
    if (!ok) return;

    deleting.value = true;
    formError.value = null;

    try {
      await blogPosts.adminDelete(selectedId.value);
      await startNewPost(true, false);
      await load();
      toast.success("Clanok bol vymazany.");
    } catch (e) {
      formError.value = e?.response?.data?.message || "Nepodarilo sa vymazat clanok.";
      toast.error(formError.value);
    } finally {
      deleting.value = false;
    }
  }

  function prevPage() {
    if (!data.value || page.value <= 1) return;
    page.value -= 1;
    load();
  }

  function nextPage() {
    if (!data.value || page.value >= data.value.last_page) return;
    page.value += 1;
    load();
  }

  function handleBeforeUnload(event) {
    if (!isDirty.value) return;
    event.preventDefault();
    event.returnValue = "";
  }

  function handleEditorShortcuts(event) {
    if (event.defaultPrevented) return;
    const key = String(event.key || "").toLowerCase();
    const combo = event.ctrlKey || event.metaKey;

    if (key === "escape" && focusMode.value) {
      focusMode.value = false;
      return;
    }

    if (!combo) return;

    if (key === "s") {
      event.preventDefault();
      if (!saving.value && !deleting.value) {
        save();
      }
      return;
    }

    if (key === "enter") {
      event.preventDefault();
      if (!saving.value && !deleting.value) {
        publishNow();
      }
    }
  }

  function toggleFocusMode() {
    focusMode.value = !focusMode.value;
  }

  watch(query, () => {
    page.value = 1;
    queueSearchLoad();
  });

  onMounted(() => {
    window.addEventListener("beforeunload", handleBeforeUnload);
    window.addEventListener("keydown", handleEditorShortcuts);
    autoSaveTimer = window.setInterval(triggerAutoSave, AUTO_SAVE_INTERVAL_MS);
    applyEmptyForm();
    load();
  });

  onBeforeUnmount(() => {
    window.removeEventListener("beforeunload", handleBeforeUnload);
    window.removeEventListener("keydown", handleEditorShortcuts);
    if (queryDebounceTimer) {
      clearTimeout(queryDebounceTimer);
      queryDebounceTimer = null;
    }
    if (autoSaveTimer) {
      clearInterval(autoSaveTimer);
      autoSaveTimer = null;
    }
    clearLocalCoverPreview();
  });

  return {
    activeTab,
    aiTagFallbackUsed,
    aiSuggestActionLabel,
    aiTagSuggestionMode,
    aiTagSuggestions,
    aiTagSuggestionsError,
    aiTagSuggestionsLoading,
    applySelectedAiTags,
    clearQuery,
    computeStatus,
    contentTabIssues,
    contentWordCount,
    coverFile,
    coverPreview,
    data,
    deleting,
    error,
    focusMode,
    form,
    formatMetricCount,
    formError,
    formatDate,
    hasCover,
    hasQuery,
    hasSelectedAiTagSuggestions,
    isDirty,
    isEditing,
    isFocusActive,
    listDensity,
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
    canPublishNow,
    publishNow,
    publishMissing,
    postClickCount,
    postReadCount,
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
    toggleFocusMode,
  };
}
