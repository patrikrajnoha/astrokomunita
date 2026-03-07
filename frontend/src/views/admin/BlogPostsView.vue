<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { blogPosts } from "@/services/blogPosts";
import { useConfirm } from "@/composables/useConfirm";
import { useToast } from "@/composables/useToast";

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
const LIST_DENSITY_STORAGE_KEY = "admin.blog.listDensity";
const listDensity = ref(getInitialListDensity());
const query = ref("");
const activeTab = ref("content");
const aiTagSuggestionsLoading = ref(false);
const aiTagSuggestionsError = ref("");
const aiTagSuggestions = ref([]);
const aiTagFallbackUsed = ref(false);
const formSnapshot = ref("");
const lastSavedAt = ref(null);
const loadToken = ref(0);
const { confirm } = useConfirm();
const toast = useToast();

let queryDebounceTimer = null;
let localCoverObjectUrl = "";

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
const isDirty = computed(() => makeFormSnapshot() !== formSnapshot.value);
const titleLength = computed(() => String(form.value.title || "").trim().length);
const contentWordCount = computed(
  () => String(form.value.content || "").trim().split(/\s+/).filter(Boolean).length
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
const pageRangeLabel = computed(() => {
  const from = data.value?.from || 0;
  const to = data.value?.to || 0;
  const total = data.value?.total || 0;
  return `${from}-${to} / ${total}`;
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

function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleString("sk-SK", { dateStyle: "medium", timeStyle: "short" });
}

function toDateTimeLocal(value) {
  if (!value) return "";
  const d = new Date(value);
  if (isNaN(d.getTime())) return "";
  const pad = (n) => String(n).padStart(2, "0");
  const yyyy = d.getFullYear();
  const mm = pad(d.getMonth() + 1);
  const dd = pad(d.getDate());
  const hh = pad(d.getHours());
  const min = pad(d.getMinutes());
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
}

function fromDateTimeLocal(value) {
  if (!value) return null;
  const d = new Date(value);
  if (isNaN(d.getTime())) return null;
  return d.toISOString();
}

function computeStatus(post) {
  if (!post?.published_at) return "draft";
  const d = new Date(post.published_at);
  if (isNaN(d.getTime())) return "draft";
  return d.getTime() <= Date.now() ? "published" : "scheduled";
}

function statusLabel(value) {
  switch (value) {
    case "published":
      return "Publikovany";
    case "scheduled":
      return "Naplanovany";
    case "draft":
    default:
      return "Koncept";
  }
}

function resetAiTagSuggestions() {
  aiTagSuggestionsLoading.value = false;
  aiTagSuggestionsError.value = "";
  aiTagSuggestions.value = [];
  aiTagFallbackUsed.value = false;
}

function parseTagsInput() {
  return tagsInput.value
    .split(",")
    .map((t) => t.trim())
    .filter(Boolean);
}

function makeFormSnapshot() {
  const normalizedTags = parseTagsInput()
    .map((tag) => tag.toLowerCase())
    .sort();

  return JSON.stringify({
    selected_id: selectedId.value || 0,
    title: String(form.value.title || "").trim(),
    content: String(form.value.content || "").trim(),
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
    if (!ok) return;
  }
  applyEmptyForm();
  if (shouldEnableFocus) {
    focusMode.value = true;
  }
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
  const missing = publishMissing.value;
  const missingCopy = missing.map((item) => `- ${item.label}`).join("\n");
  const ok = await confirm({
    title: "Publikovat clanok",
    message: missing.length
      ? `Pred publikovanim odporucame doplnit:\n${missingCopy}\n\nChces pokracovat a publikovat teraz?`
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
  persistListDensity(value);
}

function getInitialListDensity() {
  if (typeof window === "undefined") return "comfortable";
  try {
    const stored = window.localStorage.getItem(LIST_DENSITY_STORAGE_KEY);
    if (stored === "comfortable" || stored === "dense") return stored;
  } catch {
    // Ignore unavailable localStorage and fallback to default.
  }
  return "comfortable";
}

function persistListDensity(value) {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(LIST_DENSITY_STORAGE_KEY, value);
  } catch {
    // Ignore unavailable localStorage.
  }
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

function slugifyHeading(text) {
  return text
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-")
    .slice(0, 80);
}

function escapeHtml(text) {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function inlineMarkdown(text) {
  const safe = escapeHtml(text);
  let html = safe;
  html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
  html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  html = html.replace(/\*([^*]+)\*/g, "<em>$1</em>");
  html = html.replace(
    /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
  );
  return html;
}

function parseContentBlocks(text) {
  const raw = text || "";
  if (!raw.trim()) return [];

  const lines = raw.split(/\r?\n/);
  const blocks = [];
  let buffer = [];
  let listBuffer = [];
  let keyIndex = 0;

  const nextKey = () => `b-${keyIndex++}`;

  const flushParagraph = () => {
    const t = buffer.join(" ").trim();
    if (t) blocks.push({ type: "p", html: inlineMarkdown(t), key: nextKey() });
    buffer = [];
  };

  const flushList = () => {
    if (listBuffer.length) {
      blocks.push({
        type: "ul",
        items: listBuffer.map((item) => inlineMarkdown(item)),
        key: nextKey(),
      });
      listBuffer = [];
    }
  };

  lines.forEach((line) => {
    const trimmed = line.trim();
    const h2 = trimmed.startsWith("## ");
    const h3 = trimmed.startsWith("### ");
    const isList = trimmed.startsWith("- ") || trimmed.startsWith("* ");

    if (h2 || h3) {
      flushList();
      flushParagraph();
      const title = trimmed.replace(/^###?\s+/, "");
      blocks.push({
        type: h3 ? "h3" : "h2",
        text: title,
        id: slugifyHeading(title),
        key: nextKey(),
      });
      return;
    }

    if (trimmed === "") {
      flushList();
      flushParagraph();
      return;
    }

    if (isList) {
      flushParagraph();
      listBuffer.push(trimmed.replace(/^[-*]\s+/, ""));
      return;
    }

    buffer.push(trimmed);
  });

  flushList();
  flushParagraph();
  return blocks;
}

function readTimeFor(text) {
  const words = String(text || "").trim().split(/\s+/).filter(Boolean).length;
  const minutes = Math.max(1, Math.round(words / 220));
  return `${minutes} min citania`;
}

const previewBlocks = computed(() => parseContentBlocks(form.value.content));
const previewToc = computed(() =>
  previewBlocks.value.filter((b) => b.type === "h2" || b.type === "h3")
);

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

async function save() {
  formError.value = null;
  saving.value = true;

  const wasEditing = isEditing.value;

  try {
    const tags = parseTagsInput();

    const payload = {
      title: form.value.title?.trim(),
      content: form.value.content?.trim(),
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
    toast.success(wasEditing ? "Clanok bol ulozeny." : "Clanok bol vytvoreny.");
  } catch (e) {
    const msg = e?.response?.data?.message || "Nepodarilo sa ulozit clanok.";
    const fieldErrors = e?.response?.data?.errors;
    if (fieldErrors) {
      const firstField = Object.keys(fieldErrors)[0];
      const firstMessage = fieldErrors[firstField]?.[0];
      formError.value = firstMessage || msg;
    } else {
      formError.value = msg;
    }
  } finally {
    saving.value = false;
  }
}

async function suggestAiTags() {
  if (!isEditing.value || !selectedId.value || aiTagSuggestionsLoading.value) return;

  aiTagSuggestionsLoading.value = true;
  aiTagSuggestionsError.value = "";
  aiTagSuggestions.value = [];
  aiTagFallbackUsed.value = false;

  try {
    const response = await blogPosts.adminSuggestTags(selectedId.value);
    const items = Array.isArray(response?.tags) ? response.tags : [];

    aiTagSuggestions.value = items
      .slice(0, 5)
      .map((item) => ({
        id: Number(item?.id || 0),
        name: String(item?.name || "").trim(),
        reason: String(item?.reason || "").trim(),
        checked: true,
      }))
      .filter((item) => item.id > 0 && item.name && item.reason);
    aiTagFallbackUsed.value = Boolean(response?.fallback_used);
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
    .filter((item) => item.id > 0 && item.name);

  if (selected.length === 0) return;

  const existingTagIds = Array.isArray(selectedPost.value?.tags)
    ? selectedPost.value.tags
      .map((tag) => Number(tag?.id || 0))
      .filter((id) => id > 0)
    : [];
  const mergedTagIds = Array.from(
    new Set([...existingTagIds, ...selected.map((item) => item.id)]),
  );

  const existingTags = parseTagsInput();
  const normalizedExistingNames = new Set(
    existingTags.map((tag) => String(tag || "").trim().toLowerCase()).filter(Boolean),
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
    const saved = await blogPosts.adminUpdate(selectedId.value, {
      tag_ids: mergedTagIds,
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
    toast.success("Tagy boli pridane.");
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
  clearLocalCoverPreview();
});
</script>

<template>
  <div class="admin-blog">
    <div class="command-bar">
      <div class="command-left">
        <div class="search-field">
          <input
            v-model="query"
            type="search"
            placeholder="Hladat podla nazvu, tagov, autora"
          />
          <button v-if="hasQuery" type="button" class="clear-search" @click="clearQuery">
            Vycistit
          </button>
        </div>
        <div class="segmented" role="group" aria-label="Stav clanku">
          <button
            type="button"
            :class="{ active: status === '' }"
            @click="setStatusFilter('')"
            :disabled="loading"
          >
            Vsetky
          </button>
          <button
            type="button"
            :class="{ active: status === 'draft' }"
            @click="setStatusFilter('draft')"
            :disabled="loading"
          >
            Koncepty
          </button>
          <button
            type="button"
            :class="{ active: status === 'scheduled' }"
            @click="setStatusFilter('scheduled')"
            :disabled="loading"
          >
            Naplanovane
          </button>
          <button
            type="button"
            :class="{ active: status === 'published' }"
            @click="setStatusFilter('published')"
            :disabled="loading"
          >
            Publikovane
          </button>
        </div>
      </div>

      <div class="command-right">
        <div class="density-switch" role="group" aria-label="Hustota zoznamu">
          <button
            type="button"
            :class="{ active: listDensity === 'comfortable' }"
            @click="setListDensity('comfortable')"
            :disabled="loading"
          >
            Komfort
          </button>
          <button
            type="button"
            :class="{ active: listDensity === 'dense' }"
            @click="setListDensity('dense')"
            :disabled="loading"
          >
            Huste
          </button>
        </div>

        <label class="select-field">
          <span>Na stranu</span>
          <select
            v-model.number="per_page"
            @change="onPerPageChange"
            :disabled="loading"
          >
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
          </select>
        </label>
        <button class="ghost" @click="load" :disabled="loading">
          Obnovit
        </button>
        <button class="primary" @click="startNewPost" :disabled="saving || deleting">
          Novy clanok
        </button>
      </div>
    </div>

    <div class="command-meta">
      <span class="meta-text">{{ pageRangeLabel }}</span>
      <span class="meta-sep">•</span>
      <span class="meta-text">{{ status ? statusLabel(status) : "Vsetky stavy" }}</span>
      <template v-if="hasQuery">
        <span class="meta-sep">•</span>
        <span class="meta-text">Filter: "{{ query }}"</span>
      </template>
    </div>

    <div v-if="error" class="error">
      {{ error }}
    </div>

    <div class="grid" :class="{ 'focus-grid': isFocusActive }">
      <section class="panel list">
        <div class="panel-head">
          <div>
            <h2>Clanky</h2>
            <div class="muted">
              {{ data?.total || 0 }} spolu
            </div>
          </div>
          <div class="page-meta">
            <span>Strana</span>
            <strong>{{ data?.current_page || 1 }}</strong>
            <span>/ {{ data?.last_page || 1 }}</span>
          </div>
        </div>

        <div class="list-body">
          <div v-if="loading" class="muted pad">Nacitavam...</div>

          <div v-else :class="['card-list', `density-${listDensity}`]">
            <button
              v-for="post in posts"
              :key="post.id"
              type="button"
              class="post-card"
              :class="{ active: post.id === selectedId }"
              @click="selectPost(post)"
            >
              <div class="card-main compact">
                <div class="card-topline">
                  <div class="card-title">{{ post.title || "(Bez nazvu)" }}</div>
                  <span :class="['pill', computeStatus(post)]">
                    {{ statusLabel(computeStatus(post)) }}
                  </span>
                </div>
                <div class="card-meta compact">
                  <span>{{ formatDate(post.published_at || post.created_at) }}</span>
                  <span v-if="post.user?.name">{{ post.user.name }}</span>
                  <span>{{ post.tags?.length || 0 }} tagov</span>
                </div>
              </div>
            </button>

            <div v-if="posts.length === 0" class="empty">
              Nenasli sa ziadne clanky.
            </div>
          </div>
        </div>

        <div class="pager">
          <button class="ghost" @click="prevPage" :disabled="loading || page <= 1">
            Predchadzajuca
          </button>
          <button
            class="ghost"
            @click="nextPage"
            :disabled="loading || (data && page >= data.last_page)"
          >
            Dalsia
          </button>
        </div>
      </section>

      <section class="panel editor" :class="{ 'focus-mode': isFocusActive }">
        <div class="editor-topbar">
          <div class="editor-title">
            <div>
              <h2>{{ isEditing ? "Uprava clanku" : "Novy clanok" }}</h2>
              <div class="muted">
                {{ isEditing ? `ID #${selectedId}` : "Koncept" }}
              </div>
            </div>
            <div class="editor-badges">
              <span :class="['pill', selectedStatus]">
                {{ statusLabel(selectedStatus) }}
              </span>
              <span v-if="isFocusActive" class="focus-badge">Focus rezim</span>
              <span v-if="isDirty" class="dirty-badge">Neulozene zmeny</span>
              <span class="save-state">
                {{ saveStateLabel }}
              </span>
              <span class="muted">
                {{ formatDate(selectedPost?.updated_at || selectedPost?.published_at) }}
              </span>
            </div>
          </div>

          <div class="editor-actions">
            <button
              v-if="selectedStatus === 'draft'"
              class="ghost"
              @click="save"
              :disabled="saving || deleting"
            >
              {{ saving ? "Ukladam..." : "Ulozit koncept" }}
            </button>
            <button
              class="primary"
              @click="selectedStatus === 'draft' ? publishNow : save"
              :disabled="saving || deleting"
            >
              {{
                saving
                  ? "Ukladam..."
                  : selectedStatus === "draft"
                    ? "Publikovat teraz"
                    : "Ulozit zmeny"
              }}
            </button>
            <button
              class="ghost"
              @click="showPreview = !showPreview"
              :disabled="saving || deleting"
            >
              {{ showPreview ? "Upravit" : "Nahlad" }}
            </button>
            <button
              type="button"
              class="ghost focus-toggle"
              :class="{ active: isFocusActive }"
              @click="toggleFocusMode"
            >
              {{ isFocusActive ? "Ukoncit focus" : "Focus pisanie" }}
            </button>
            <details v-if="isEditing" class="more-menu">
              <summary>Viac</summary>
              <div class="menu">
                <button
                  class="danger"
                  type="button"
                  @click="remove"
                  :disabled="saving || deleting"
                >
                  {{ deleting ? "Mazem..." : "Vymazat" }}
                </button>
              </div>
            </details>
          </div>
        </div>

        <div v-if="formError" class="error">
          {{ formError }}
        </div>

        <div class="editor-body">
          <div v-if="showPreview" class="preview">
            <div class="preview-header">
              <div class="preview-kicker">Nahlad</div>
              <div class="preview-meta">
                {{ readTimeFor(form.content) }}
              </div>
            </div>
            <h2 class="preview-title">{{ form.title || "Bez nazvu" }}</h2>
            <div v-if="tagsInput" class="taglist">
              <span
                v-for="tag in tagsInput.split(',').map((t) => t.trim()).filter(Boolean)"
                :key="tag"
                class="tag-chip"
              >
                {{ tag }}
              </span>
            </div>

            <div v-if="coverPreview" class="preview-cover">
              <img :src="coverPreview" alt="Cover preview" />
            </div>

            <div class="preview-layout">
              <aside v-if="previewToc.length" class="preview-toc">
                <div class="preview-toc-title">Obsah</div>
                <ul>
                  <li v-for="item in previewToc" :key="item.id" :class="item.type">
                    <span>{{ item.text }}</span>
                  </li>
                </ul>
              </aside>

              <div class="preview-content">
                <template v-for="block in previewBlocks">
                  <h3 v-if="block.type === 'h2'" :key="`${block.key}-h2`">{{ block.text }}</h3>
                  <h4 v-else-if="block.type === 'h3'" :key="`${block.key}-h3`">{{ block.text }}</h4>
                  <ul v-else-if="block.type === 'ul'" :key="`${block.key}-ul`">
                    <li v-for="(item, idx) in block.items" :key="idx" v-html="item"></li>
                  </ul>
                  <p v-else :key="`${block.key}-p`" v-html="block.html"></p>
                </template>
              </div>
            </div>
          </div>

          <div v-else class="editor-form">
            <div class="title-row">
              <input
                v-model="form.title"
                type="text"
                class="title-input"
                placeholder="Napis jasny a kratky nadpis"
              />
              <div class="title-metrics">
                <span :class="['title-counter', { warn: titleLength > 70 }]">
                  {{ titleLength }}/70
                </span>
                <span class="muted">{{ contentWordCount }} slov</span>
              </div>
            </div>

            <div class="editor-layout" :class="{ 'is-focus': isFocusActive }">
              <div class="editor-main">
                <div class="tabs">
                  <button
                    type="button"
                    :class="{ active: activeTab === 'content' }"
                    @click="activeTab = 'content'"
                  >
                    Obsah
                    <span v-if="contentTabIssues" class="tab-count">{{ contentTabIssues }}</span>
                  </button>
                  <button
                    type="button"
                    :class="{ active: activeTab === 'meta' }"
                    @click="activeTab = 'meta'"
                  >
                    Meta
                    <span v-if="metaTabIssues" class="tab-count">{{ metaTabIssues }}</span>
                  </button>
                  <button
                    type="button"
                    :class="{ active: activeTab === 'media' }"
                    @click="activeTab = 'media'"
                  >
                    Media
                    <span v-if="mediaTabIssues" class="tab-count">{{ mediaTabIssues }}</span>
                  </button>
                </div>

                <div class="tab-body">
                  <div v-show="activeTab === 'content'" class="tab-panel">
                    <label class="field-block">
                      <span>Obsah</span>
                      <textarea
                        v-model="form.content"
                        rows="16"
                        placeholder="Cely obsah clanku..."
                      />
                    </label>
                    <div class="hint-row">
                      <span>{{ readTimeFor(form.content) }}</span>
                      <span>{{ contentWordCount }} slov</span>
                    </div>
                  </div>

                  <div v-show="activeTab === 'meta'" class="tab-panel">
                    <label class="field-block">
                      <span>Tagy (oddelene ciarkou)</span>
                      <input
                        v-model="tagsInput"
                        type="text"
                        placeholder="napr. planety, komety, pozorovanie"
                      />
                    </label>

                    <section class="ai-tags-panel">
                      <div class="ai-tags-head">
                        <h4>AI: Navrhnut tagy</h4>
                        <span v-if="aiTagFallbackUsed" class="ai-tags-fallback">Fallback</span>
                      </div>
                      <p class="muted">Vyberieme max 5 existujucich tagov pre tento clanok.</p>

                      <div class="ai-tags-actions">
                        <button
                          type="button"
                          class="ghost"
                          :disabled="!isEditing || aiTagSuggestionsLoading || saving || deleting"
                          @click="suggestAiTags"
                        >
                          {{ aiTagSuggestionsLoading ? "Navrhujem..." : "Navrhnut tagy" }}
                        </button>
                        <button
                          type="button"
                          class="primary"
                          :disabled="!isEditing || !hasSelectedAiTagSuggestions || saving || deleting"
                          @click="applySelectedAiTags"
                        >
                          Pridat vybrane
                        </button>
                      </div>

                      <p v-if="!isEditing" class="muted">Najprv uloz clanok, potom navrhni tagy.</p>
                      <p v-if="aiTagSuggestionsError" class="ai-tags-error">{{ aiTagSuggestionsError }}</p>

                      <div v-if="aiTagSuggestions.length" class="ai-tags-list">
                        <label
                          v-for="item in aiTagSuggestions"
                          :key="`ai-tag-${item.id}`"
                          class="ai-tag-option"
                        >
                          <input v-model="item.checked" type="checkbox" />
                          <div class="ai-tag-copy">
                            <strong>{{ item.name }}</strong>
                            <p>{{ item.reason }}</p>
                          </div>
                        </label>
                      </div>
                    </section>

                    <label class="field-block">
                      <span>Publikovat od</span>
                      <input v-model="form.published_at" type="datetime-local" />
                    </label>

                    <div class="meta-actions">
                      <button
                        class="ghost"
                        type="button"
                        @click="setPublishNow"
                        :disabled="saving || deleting"
                      >
                        Nastavit na teraz
                      </button>
                      <button
                        class="ghost"
                        type="button"
                        @click="form.published_at = ''"
                        :disabled="saving || deleting || !form.published_at"
                      >
                        Ponechat ako koncept
                      </button>
                    </div>
                  </div>

                  <div v-show="activeTab === 'media'" class="tab-panel">
                    <label class="field-block">
                      <span>Titulny obrazok</span>
                      <input type="file" accept="image/*" @change="onCoverChange" />
                    </label>

                    <div v-if="coverPreview" class="cover-preview">
                      <img :src="coverPreview" alt="Cover preview" />
                    </div>

                    <button
                      v-if="isEditing"
                      class="ghost"
                      type="button"
                      @click="saveCoverOnly"
                      :disabled="saving || deleting"
                    >
                      Ulozit iba titulny obrazok
                    </button>
                  </div>
                </div>
              </div>

              <aside v-if="!isFocusActive" class="editor-sidebar">
                <section class="sidebar-card">
                  <h3>Stav clanku</h3>
                  <div class="status-stack">
                    <div class="status-row">
                      <span>Slug</span>
                      <code>/clanky/{{ titleSlugPreview }}</code>
                    </div>
                    <div class="status-row">
                      <span>Citanie</span>
                      <strong>{{ readTimeFor(form.content) }}</strong>
                    </div>
                    <div class="status-row">
                      <span>Tagy</span>
                      <strong>{{ tagCount }}</strong>
                    </div>
                    <div class="status-row">
                      <span>Ulozenie</span>
                      <strong>{{ saveStateLabel }}</strong>
                    </div>
                  </div>
                </section>

                <section class="sidebar-card">
                  <h3>Checklist publikovania</h3>
                  <ul class="checklist">
                    <li
                      v-for="item in publishChecklist"
                      :key="item.key"
                      :class="{ done: item.done }"
                    >
                      <span class="check-dot">{{ item.done ? "OK" : "!" }}</span>
                      <span>
                        {{ item.label }}
                        <small v-if="!item.required">(odporucane)</small>
                      </span>
                    </li>
                  </ul>
                </section>

                <section class="sidebar-card">
                  <h3>Skratky</h3>
                  <div class="shortcut-list">
                    <div><kbd>Ctrl</kbd> + <kbd>S</kbd> ulozit</div>
                    <div><kbd>Ctrl</kbd> + <kbd>Enter</kbd> publikovat</div>
                  </div>
                </section>
              </aside>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.admin-blog {
  --bg: var(--color-bg);
  --panel: rgb(var(--color-bg-rgb) / 0.9);
  --panel-soft: rgb(var(--color-bg-rgb) / 0.6);
  --panel-border: rgb(var(--color-text-secondary-rgb) / 0.2);
  --muted: rgb(var(--color-surface-rgb) / 0.65);
  --text: var(--color-surface);
  --primary: var(--color-primary);
  --primary-strong: rgb(var(--color-primary-rgb) / 0.85);
  --danger: var(--color-danger);
  --ring: rgb(var(--color-primary-rgb) / 0.35);
  color: var(--text);
  position: relative;
  isolation: isolate;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.admin-blog::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  background:
    radial-gradient(circle at 8% 0%, rgb(var(--color-primary-rgb) / 0.12), transparent 42%),
    radial-gradient(circle at 100% 15%, rgb(var(--color-success-rgb) / 0.1), transparent 36%);
}

.command-bar {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: space-between;
  padding: 12px;
  border-radius: 16px;
  background: linear-gradient(120deg, rgb(var(--color-bg-rgb) / 0.95), rgb(var(--color-primary-rgb) / 0.18));
  border: 1px solid var(--panel-border);
  box-shadow: 0 10px 30px rgb(var(--color-bg-rgb) / 0.28);
  backdrop-filter: blur(10px);
}

.command-left,
.command-right {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.search-field {
  display: flex;
  align-items: center;
  gap: 8px;
}

.search-field input {
  min-width: 220px;
  padding: 10px 14px;
  border-radius: 999px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: inherit;
  font-size: 14px;
}

.clear-search {
  white-space: nowrap;
  padding-inline: 12px;
}

.command-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  font-size: 12px;
  color: var(--muted);
  padding: 0 4px;
}

.meta-text {
  white-space: nowrap;
}

.meta-sep {
  color: rgb(var(--color-text-secondary-rgb) / 0.6);
}

.segmented {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 4px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  border: 1px solid var(--panel-border);
}

.segmented button {
  padding: 8px 14px;
  border-radius: 999px;
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
}

.segmented button.active {
  background: rgb(var(--color-primary-rgb) / 0.25);
  color: var(--color-primary);
}

.density-switch {
  display: inline-flex;
  align-items: center;
  border-radius: 10px;
  padding: 2px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.6);
}

.density-switch button {
  padding: 7px 10px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
}

.density-switch button.active {
  background: rgb(var(--color-primary-rgb) / 0.22);
  color: var(--color-primary);
}

.select-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
}

.select-field select {
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: inherit;
}

.error {
  padding: 10px 12px;
  border-radius: 12px;
  background: rgb(var(--color-danger-rgb) / 0.15);
  border: 1px solid rgb(var(--color-danger-rgb) / 0.4);
  color: var(--color-danger);
}

.grid {
  display: grid;
  grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
  gap: 16px;
  min-height: 70vh;
}

.grid.focus-grid {
  grid-template-columns: 1fr;
}

.grid.focus-grid .list {
  display: none;
}

.panel {
  border-radius: 18px;
  background: var(--panel);
  border: 1px solid var(--panel-border);
  box-shadow: 0 12px 36px rgb(var(--color-bg-rgb) / 0.26);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
}

.panel-head h2 {
  margin: 0 0 4px;
  font-size: 17px;
  letter-spacing: 0.01em;
}

.page-meta {
  display: flex;
  align-items: baseline;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.muted {
  color: var(--muted);
  font-size: 12px;
}

.list-body {
  flex: 1;
  overflow: auto;
}

.card-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px;
}

.card-list.density-dense {
  gap: 6px;
  padding: 10px 12px;
}

.post-card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 16px;
  background: rgb(var(--color-bg-rgb) / 0.35);
  padding: 14px 16px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 12px;
  text-align: left;
  cursor: pointer;
  color: inherit;
  transition: background 150ms ease, border-color 150ms ease, transform 150ms ease, box-shadow 150ms ease;
}

.card-list.density-dense .post-card {
  padding: 10px 12px;
  border-radius: 12px;
}

.post-card:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.08);
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgb(var(--color-bg-rgb) / 0.25);
}

.post-card.active {
  border-color: rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.15);
  box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 6px;
}

.card-meta {
  display: flex;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.card-main.compact {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.card-list.density-dense .card-main.compact {
  gap: 4px;
}

.card-topline {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.card-meta.compact {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 10px;
}

.card-list.density-dense .card-meta.compact {
  font-size: 11px;
  gap: 6px 8px;
}

.card-meta.compact span + span::before {
  content: "•";
  margin-right: 10px;
  color: rgb(var(--color-text-secondary-rgb) / 0.65);
}

.tag-chip {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.15);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.card-list.density-dense .pill {
  padding: 3px 7px;
  font-size: 10px;
}

.pill.published {
  background: rgb(var(--color-success-rgb) / 0.2);
  color: var(--color-success);
  border: 1px solid rgb(var(--color-success-rgb) / 0.4);
}

.pill.draft {
  background: rgb(var(--color-danger-rgb) / 0.2);
  color: var(--color-danger);
  border: 1px solid rgb(var(--color-danger-rgb) / 0.3);
}

.pill.scheduled {
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-primary);
  border: 1px solid rgb(var(--color-primary-rgb) / 0.4);
}

.empty {
  padding: 16px;
  text-align: center;
  color: var(--muted);
}

.pager {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
}

.editor {
  background: linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.92), rgb(var(--color-bg-rgb) / 0.7));
}

.editor-topbar {
  position: sticky;
  top: 0;
  z-index: 3;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: rgb(var(--color-bg-rgb) / 0.95);
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
  box-shadow: inset 0 -1px 0 rgb(var(--color-text-secondary-rgb) / 0.08);
  backdrop-filter: blur(12px);
}

.editor-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.editor-title h2 {
  margin: 0 0 6px;
  font-size: 18px;
}

.editor-badges {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.dirty-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid rgb(245 158 11 / 0.5);
  background: rgb(245 158 11 / 0.14);
  color: rgb(245 158 11);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.focus-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.46);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-primary);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.save-state {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  color: rgb(var(--color-surface-rgb) / 0.82);
  font-size: 11px;
}

.editor-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.editor-body {
  flex: 1;
  padding: 22px;
  overflow: auto;
}

.editor-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.title-row {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.title-metrics {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.title-counter {
  font-size: 12px;
  color: var(--muted);
}

.title-counter.warn {
  color: rgb(245 158 11);
}

.title-input {
  font-size: 28px;
  font-weight: 700;
  line-height: 1.25;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.6);
  color: inherit;
}

.tabs {
  display: inline-flex;
  gap: 6px;
  padding: 4px;
  border-radius: 999px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.6);
}

.tabs button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
}

.tabs button.active {
  background: rgb(var(--color-primary-rgb) / 0.25);
  color: var(--color-primary);
}

.tab-count {
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  line-height: 1;
  color: var(--color-danger);
  background: rgb(var(--color-danger-rgb) / 0.16);
  border: 1px solid rgb(var(--color-danger-rgb) / 0.32);
}

.editor-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

.editor-layout.is-focus {
  grid-template-columns: 1fr;
}

.editor-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.editor-sidebar {
  position: static;
  align-self: start;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.editor-sidebar .sidebar-card:last-child {
  grid-column: span 2;
}

.sidebar-card {
  border-radius: 14px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  background: linear-gradient(
    170deg,
    rgb(var(--color-bg-rgb) / 0.68),
    rgb(var(--color-primary-rgb) / 0.08)
  );
  padding: 14px 15px;
  display: grid;
  gap: 12px;
}

.sidebar-card h3 {
  margin: 0;
  font-size: 13px;
  text-transform: uppercase;
  color: rgb(var(--color-surface-rgb) / 0.85);
  letter-spacing: 0.02em;
}

.status-stack {
  display: grid;
  gap: 8px;
}

.status-row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 12px;
  color: var(--muted);
  padding-bottom: 8px;
  border-bottom: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.22);
}

.status-row:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

.status-row strong {
  color: rgb(var(--color-surface-rgb) / 0.9);
  font-weight: 600;
}

.status-row code {
  max-width: 180px;
  text-overflow: ellipsis;
  white-space: nowrap;
  overflow: hidden;
  border-radius: 8px;
  padding: 2px 6px;
  font-size: 11px;
  background: rgb(var(--color-bg-rgb) / 0.55);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.tab-body {
  background: rgb(var(--color-bg-rgb) / 0.55);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 16px;
  padding: 18px;
}

.tab-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.hint-row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 12px;
  color: var(--muted);
}

.field-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 12px;
  color: var(--muted);
}

.field-block input,
.field-block textarea {
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.6);
  color: inherit;
  font-size: 14px;
}

.field-block textarea {
  line-height: 1.6;
}

.ai-tags-panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 14px;
  background: rgb(var(--color-bg-rgb) / 0.5);
  padding: 12px;
  display: grid;
  gap: 10px;
}

.ai-tags-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.ai-tags-head h4 {
  margin: 0;
  font-size: 14px;
}

.ai-tags-fallback {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid rgb(245 158 11 / 0.42);
  background: rgb(245 158 11 / 0.12);
  font-size: 11px;
}

.ai-tags-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.meta-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.ai-tags-list {
  display: grid;
  gap: 8px;
}

.ai-tag-option {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 10px;
  align-items: flex-start;
  padding: 8px 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.5);
}

.ai-tag-copy strong {
  display: block;
  margin-bottom: 4px;
  font-size: 13px;
}

.ai-tag-copy p {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.ai-tags-error {
  margin: 0;
  font-size: 13px;
  color: var(--color-danger);
}

.cover-preview {
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background: rgb(var(--color-bg-rgb) / 0.5);
}

.cover-preview img {
  width: 100%;
  display: block;
  max-height: 240px;
  object-fit: cover;
}

.checklist {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}

.checklist li {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 8px;
  font-size: 12px;
  color: var(--muted);
  align-items: center;
}

.checklist li.done {
  color: rgb(var(--color-success-rgb) / 0.95);
}

.checklist li small {
  color: rgb(var(--color-surface-rgb) / 0.56);
  margin-left: 4px;
}

.check-dot {
  width: 18px;
  height: 18px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.36);
  color: var(--color-danger);
  background: rgb(var(--color-danger-rgb) / 0.14);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
}

.checklist li.done .check-dot {
  border-color: rgb(var(--color-success-rgb) / 0.44);
  color: var(--color-success);
  background: rgb(var(--color-success-rgb) / 0.14);
}

.shortcut-list {
  display: grid;
  gap: 10px;
  font-size: 12px;
  color: var(--muted);
}

kbd {
  font-family: "Courier New", monospace;
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 6px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.58);
  color: rgb(var(--color-surface-rgb) / 0.88);
}

.preview {
  padding: 16px;
  border-radius: 16px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background: rgb(var(--color-bg-rgb) / 0.7);
}

.preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.preview-kicker {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 11px;
  color: rgb(var(--color-surface-rgb) / 0.6);
}

.preview-meta {
  font-size: 12px;
  color: rgb(var(--color-surface-rgb) / 0.7);
}

.preview-title {
  margin: 0 0 10px;
  font-size: 24px;
}

.preview-cover {
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.preview-cover img {
  display: block;
  width: 100%;
  max-height: 220px;
  object-fit: cover;
}

.preview-layout {
  display: grid;
  grid-template-columns: minmax(0, 0.8fr) minmax(0, 2fr);
  gap: 12px;
}

.preview-toc {
  padding: 10px;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.6);
  font-size: 12px;
}

.preview-toc-title {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 10px;
  color: rgb(var(--color-surface-rgb) / 0.6);
  margin-bottom: 8px;
}

.preview-toc ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 6px;
}

.preview-toc li.h3 {
  margin-left: 8px;
}

.preview-content h3 {
  margin: 16px 0 6px;
  font-size: 16px;
}

.preview-content h4 {
  margin: 12px 0 6px;
  font-size: 14px;
  color: rgb(var(--color-surface-rgb) / 0.85);
}

.preview-content p {
  margin: 0 0 10px;
  color: rgb(var(--color-surface-rgb) / 0.85);
  line-height: 1.6;
}

.preview-content ul {
  margin: 0 0 10px;
  padding-left: 18px;
}

.preview-content li {
  margin: 6px 0;
}

.preview-content a {
  color: var(--color-primary);
  text-decoration: underline;
}

.preview-content code {
  font-family: "Courier New", monospace;
  font-size: 0.9em;
  background: rgb(var(--color-bg-rgb) / 0.6);
  padding: 2px 5px;
  border-radius: 6px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
}

button {
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
  color: inherit;
  cursor: pointer;
}

button.primary {
  background: rgb(var(--color-primary-rgb) / 0.22);
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  color: var(--color-primary);
}

button:hover:not(:disabled) {
  border-color: rgb(var(--color-primary-rgb) / 0.36);
}

button.ghost {
  background: transparent;
}

button.danger {
  background: rgb(var(--color-danger-rgb) / 0.2);
  border-color: rgb(var(--color-danger-rgb) / 0.5);
  color: var(--color-danger);
}

.focus-toggle.active {
  background: rgb(var(--color-primary-rgb) / 0.22);
  border-color: rgb(var(--color-primary-rgb) / 0.48);
  color: var(--color-primary);
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.more-menu {
  position: relative;
}

.more-menu summary {
  list-style: none;
  cursor: pointer;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
}

.more-menu[open] summary {
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.more-menu summary::-webkit-details-marker {
  display: none;
}

.more-menu .menu {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  background: rgb(var(--color-bg-rgb) / 0.98);
  border: 1px solid var(--panel-border);
  border-radius: 12px;
  padding: 8px;
  box-shadow: 0 18px 40px rgb(var(--color-bg-rgb) / 0.35);
  min-width: 160px;
  z-index: 10;
}

.more-menu .menu button {
  width: 100%;
}

input:focus-visible,
textarea:focus-visible,
select:focus-visible,
button:focus-visible,
summary:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--ring);
}

@media (min-width: 1700px) {
  .editor:not(.focus-mode) .editor-layout {
    grid-template-columns: minmax(0, 1fr) minmax(290px, 330px);
  }

  .editor:not(.focus-mode) .editor-sidebar {
    position: sticky;
    top: 84px;
    grid-template-columns: 1fr;
  }

  .editor:not(.focus-mode) .editor-sidebar .sidebar-card:last-child {
    grid-column: span 1;
  }
}

@media (max-width: 980px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .command-bar {
    position: static;
  }

  .editor-sidebar {
    grid-template-columns: 1fr;
  }

  .editor-sidebar .sidebar-card:last-child {
    grid-column: span 1;
  }
}

@media (max-width: 900px) {
  .admin-blog {
    gap: 12px;
  }

  .command-bar {
    padding: 10px;
    border-radius: 12px;
    gap: 10px;
  }

  .command-left,
  .command-right {
    width: 100%;
    align-items: stretch;
  }

  .search-field {
    width: 100%;
  }

  .search-field {
    flex-wrap: wrap;
    justify-content: stretch;
  }

  .search-field input {
    width: 100%;
    min-width: 0;
  }

  .clear-search {
    width: 100%;
  }

  .segmented {
    width: 100%;
    overflow-x: auto;
    justify-content: flex-start;
  }

  .segmented button {
    white-space: nowrap;
  }

  .select-field {
    flex: 1 1 100%;
  }

  .density-switch {
    width: 100%;
  }

  .density-switch button {
    flex: 1;
  }

  .editor-topbar {
    position: static;
    padding: 12px;
  }

  .editor-title {
    flex-direction: column;
    align-items: flex-start;
  }

  .editor-actions {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .editor-actions > * {
    width: 100%;
  }

  .more-menu {
    grid-column: span 1;
  }

  .title-input {
    font-size: 1.15rem;
  }

  .tabs {
    width: 100%;
    overflow-x: auto;
    justify-content: flex-start;
  }

  .tabs button {
    white-space: nowrap;
  }

  .editor-body,
  .tab-body {
    padding: 14px;
  }

  .title-metrics {
    flex-direction: column;
    align-items: flex-start;
  }

  .status-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .status-row code {
    max-width: 100%;
  }

  .meta-actions {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr;
  }

  .preview-layout {
    grid-template-columns: 1fr;
  }

  button,
  .more-menu summary,
  .field-block input,
  .field-block textarea {
    min-height: 42px;
  }

  .field-block textarea {
    min-height: 180px;
  }

  .preview-layout {
    grid-template-columns: 1fr;
  }
}
</style>


