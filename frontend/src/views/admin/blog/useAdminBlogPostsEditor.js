import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { blogPosts } from "@/services/blogPosts";
import { useConfirm } from "@/composables/useConfirm";
import { useToast } from "@/composables/useToast";
import {
  computeStatus,
  formatDate,
  formatMetricCount,
  fromDateTimeLocal,
  getInitialListDensity,
  persistListDensity,
  postClickCount,
  postReadCount,
  readTimeFor,
  slugifyHeading,
  statusLabel,
  toDateTimeLocal,
} from "../blogPostsView.utils";
import { useAdminBlogPostsAiTags } from "./useAdminBlogPostsAiTags";
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
  const creatingNew = ref(false);
  const selectedPostRecord = ref(null);
  const form = ref({
    title: "",
    content: "",
    published_at: "",
  });
  const coverFile = ref(null);
  const coverPreview = ref("");
  const coverInputEl = ref(null);
  const tagsInput = ref("");
  const showPreview = ref(false);
  const listDensity = ref(getInitialListDensity(LIST_DENSITY_STORAGE_KEY));
  const query = ref("");
  const activeTab = ref("content");
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
  const isEditing = computed(() => creatingNew.value || !!selectedId.value);
  const selectedPost = computed(() => selectedPostRecord.value);
  const selectedStatus = computed(() => {
    if (!isEditing.value) return "draft";
    if (selectedPost.value) return computeStatus(selectedPost.value);
    const fallback = fromDateTimeLocal(form.value.published_at);
    if (!fallback) return "draft";
    return computeStatus({ published_at: fallback });
  });
  const isDirty = computed(() => makeFormSnapshot() !== formSnapshot.value);
  const titleLength = computed(() => String(form.value.title || "").trim().length);
  const renderedContent = computed(() => renderArticleContent(form.value.content));
  const contentPlainText = computed(() => renderedContent.value.plainText);
  const contentWordCount = computed(
    () => String(contentPlainText.value || "").trim().split(/\s+/).filter(Boolean).length
  );
  const hasCover = computed(() => Boolean(coverPreview.value));
  const coverInputLabel = computed(() => {
    if (coverFile.value?.name) {
      return coverFile.value.name;
    }
    if (coverPreview.value) {
      return "Používa sa aktuálny uložený obrázok";
    }
    return "Nie je vybratý žiadny súbor";
  });
  const tagCount = computed(() => parseTagsInput().length);
  const titleSlugPreview = computed(() =>
    slugifyHeading(form.value.title || "nový-článok")
  );
  const contentTabIssues = computed(
    () => Number(titleLength.value === 0) + Number(contentWordCount.value < 80)
  );
  const metaTabIssues = computed(() => Number(tagCount.value === 0));
  const mediaTabIssues = computed(() => Number(!hasCover.value));
  const publishChecklist = computed(() => [
    {
      key: "title",
      label: "Nadpis je vyplnený",
      done: titleLength.value >= 8,
      required: true,
    },
    {
      key: "content",
      label: "Obsah má aspoň 80 slov",
      done: contentWordCount.value >= 80,
      required: true,
    },
    {
      key: "tags",
      label: "Článok má aspoň 1 tag",
      done: tagCount.value > 0,
      required: false,
    },
    {
      key: "cover",
      label: "Titulný obrázok je vybraný",
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
    return `Pred publikovaním doplň: ${labels.join(", ")}.`;
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
    if (saving.value) return "Ukladám...";
    if (isDirty.value) return "Neuložené zmeny";
    if (lastSavedAt.value instanceof Date && !Number.isNaN(lastSavedAt.value.getTime())) {
      const at = lastSavedAt.value.toLocaleTimeString("sk-SK", {
        hour: "2-digit",
        minute: "2-digit",
      });
      return `Uložené ${at}`;
    }
    return "Bez zmien";
  });

  const saveStateClass = computed(() => {
    if (saving.value) return "is-saving";
    if (isDirty.value) return "is-dirty";
    if (lastSavedAt.value instanceof Date && !Number.isNaN(lastSavedAt.value.getTime())) return "is-saved";
    return "";
  });

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

  const aiTags = useAdminBlogPostsAiTags({
    selectedId,
    isEditing,
    titleLength,
    contentPlainText,
    saving,
    formError,
    selectedPostRecord,
    tagsInput,
    selectedPost,
    lastSavedAt,
    posts,
    save,
    load,
    selectPost,
    syncFormSnapshot,
    parseTagsInput,
  });

  function clearLocalCoverPreview() {
    if (localCoverObjectUrl) {
      URL.revokeObjectURL(localCoverObjectUrl);
      localCoverObjectUrl = "";
    }
  }

  function applyEmptyForm({ openEditor = false } = {}) {
    clearLocalCoverPreview();
    selectedId.value = null;
    creatingNew.value = Boolean(openEditor);
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
    aiTags.resetAiTagSuggestions();
    syncFormSnapshot();
  }

  function applyPostToForm(post) {
    clearLocalCoverPreview();
    selectedId.value = post.id;
    creatingNew.value = false;
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
    aiTags.resetAiTagSuggestions();
    syncFormSnapshot();
  }

  async function confirmDiscardChanges() {
    if (!isDirty.value) return true;
    return confirm({
      title: "Zahodiť zmeny?",
      message: "Máš neuložené zmeny. Chceš pokračovať bez uloženia?",
      confirmText: "Zahodiť",
      cancelText: "Ponechať",
      variant: "danger",
    });
  }

  async function startNewPost(force = false) {
    if (!force) {
      const ok = await confirmDiscardChanges();
      if (!ok) return false;
    }
    applyEmptyForm({ openEditor: true });
    return true;
  }

  async function closeEditor(force = false) {
    if (!force) {
      const ok = await confirmDiscardChanges();
      if (!ok) return false;
    }
    applyEmptyForm();
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
        publishDisabledReason.value || "Pred publikovaním treba doplniť povinné polia."
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
      title: "Publikovať článok",
      message: missingRecommended.length
        ? `Pred publikovaním odporúčame doplniť:\n${missingRecommendedCopy}\n\nChceš pokračovať a publikovať teraz?`
        : "Článok bude publikovaný okamžite. Chceš pokračovať?",
      confirmText: "Publikovať",
      cancelText: "Späť",
    });
    if (!ok) return;
    setPublishNow();
    await save();
  }

  async function setHiddenState(nextHidden, copy = {}) {
    if (!selectedId.value) return;

    const ok = await confirm({
      title: copy.title || (nextHidden ? "Skryť článok" : "Zobraziť článok"),
      message:
        copy.message ||
        (nextHidden
          ? "Článok zostane publikovaný, ale nebude viditeľný pre čitateľov. Chceš pokračovať?"
          : "Článok bude znovu viditeľný pre čitateľov. Chceš pokračovať?"),
      confirmText: copy.confirmText || (nextHidden ? "Skryť" : "Zobraziť"),
      cancelText: "Späť",
    });
    if (!ok) return;

    saving.value = true;
    formError.value = null;

    try {
      const saved = await blogPosts.adminUpdate(selectedId.value, {
        is_hidden: Boolean(nextHidden),
      });

      if (saved?.id) {
        selectedPostRecord.value = saved;
      }

      await load();
      if (saved?.id) {
        const found = posts.value.find((post) => post.id === saved.id);
        if (found) {
          await selectPost(found, true);
        } else {
          syncFormSnapshot();
        }
      } else {
        syncFormSnapshot();
      }

      lastSavedAt.value = new Date();
      toast.success(nextHidden ? "Článok bol skrytý." : "Článok je znovu viditeľný.");
    } catch (e) {
      formError.value =
        e?.response?.data?.message ||
        (nextHidden
          ? "Nepodarilo sa skryť článok."
          : "Nepodarilo sa zobraziť článok.");
      toast.error(formError.value);
    } finally {
      saving.value = false;
    }
  }

  async function hidePublished() {
    return setHiddenState(true, {
      title: "Skryť publikovaný článok",
      message:
        "Článok zostane publikovaný, ale nebude viditeľný pre čitateľov. Chceš pokračovať?",
      confirmText: "Skryť",
    });
  }

  async function unhidePublished() {
    return setHiddenState(false, {
      title: "Zobraziť článok",
      message: "Článok bude znovu viditeľný pre čitateľov. Chceš pokračovať?",
      confirmText: "Zobraziť",
    });
  }

  async function unpublish() {
    if (!selectedId.value) return;

    const ok = await confirm({
      title: "Nepublikovať článok",
      message:
        "Článok sa vráti medzi koncepty a nebude viditeľný ako publikovaný. Chceš pokračovať?",
      confirmText: "Nepublikovať",
      cancelText: "Späť",
    });
    if (!ok) return;

    saving.value = true;
    formError.value = null;

    try {
      const saved = await blogPosts.adminUpdate(selectedId.value, {
        published_at: null,
      });

      form.value.published_at = "";

      if (saved?.id) {
        selectedPostRecord.value = saved;
      }

      await load();
      if (saved?.id) {
        const found = posts.value.find((post) => post.id === saved.id);
        if (found) {
          await selectPost(found, true);
        } else {
          syncFormSnapshot();
        }
      } else {
        syncFormSnapshot();
      }

      lastSavedAt.value = new Date();
      toast.success("Článok bol stiahnutý z publikácie.");
    } catch (e) {
      formError.value =
        e?.response?.data?.message || "Nepodarilo sa zrušiť publikovanie článku.";
      toast.error(formError.value);
    } finally {
      saving.value = false;
    }
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

  function openCoverPicker() {
    const input = coverInputEl.value;
    if (!input) return;
    input.value = "";
    input.click();
  }

  async function uploadInlineImage(file) {
    if (!file || typeof file !== "object") {
      throw new Error("Neplatný súbor.");
    }

    const mime = String(file.type || "").toLowerCase();
    if (!mime.startsWith("image/")) {
      throw new Error("Podporované sú iba obrázky.");
    }

    try {
      const payload = await blogPosts.adminUploadInlineImage(file);
      const url = String(payload?.url || "").trim();
      if (!url) {
        throw new Error("Server nevrátil URL obrázka.");
      }
      return { url };
    } catch (e) {
      const msg =
        e?.response?.data?.errors?.image?.[0] ||
        e?.response?.data?.message ||
        e?.message ||
        "Nepodarilo sa nahrať obrázok.";
      throw new Error(msg);
    }
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
      error.value = e?.response?.data?.message || "Nepodarilo sa načítať články.";
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

    const wasEditing = !!selectedId.value;

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
        creatingNew.value = false;
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
        toast.success(wasEditing ? "Článok bol uložený." : "Článok bol vytvorený.");
      }
    } catch (e) {
      const msg = e?.response?.data?.message || "Nepodarilo sa uložiť článok.";
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

  async function saveCoverOnly() {
    if (!selectedId.value) return;
    if (!coverFile.value) {
      formError.value = "Najprv vyber titulný obrázok.";
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
      toast.success("Titulný obrázok bol uložený.");
    } catch (e) {
      formError.value =
        e?.response?.data?.message || "Nepodarilo sa uložiť titulný obrázok.";
      toast.error(formError.value);
    } finally {
      saving.value = false;
    }
  }

  async function remove() {
    if (!selectedId.value) return;
    const ok = await confirm({
      title: "Vymazať článok",
      message: "Naozaj chceš vymazať tento článok?",
      confirmText: "Vymazať",
      cancelText: "Zrušiť",
      variant: "danger",
    });
    if (!ok) return;

    deleting.value = true;
    formError.value = null;

    try {
      await blogPosts.adminDelete(selectedId.value);
      await closeEditor(true);
      await load();
      toast.success("Článok bol vymazaný.");
    } catch (e) {
      formError.value = e?.response?.data?.message || "Nepodarilo sa vymazať článok.";
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
    ...aiTags,
    activeTab,
    closeEditor,
    clearQuery,
    computeStatus,
    contentTabIssues,
    contentWordCount,
    coverFile,
    coverInputEl,
    coverInputLabel,
    openCoverPicker,
    coverPreview,
    data,
    deleting,
    error,
    form,
    formatMetricCount,
    formError,
    formatDate,
    hasCover,
    hasQuery,
    isDirty,
    isEditing,
    listDensity,
    load,
    loading,
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
    saveStateClass,
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
    hidePublished,
    tagCount,
    tagsInput,
    titleLength,
    titleSlugPreview,
    unhidePublished,
    uploadInlineImage,
    unpublish,
  };
}
