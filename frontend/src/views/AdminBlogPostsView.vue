<script setup>
import { computed, onMounted, ref } from "vue";
import { blogPosts } from "@/services/blogPosts";

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
const form = ref({
  title: "",
  content: "",
  published_at: "",
});
const coverFile = ref(null);
const coverPreview = ref("");
const tagsInput = ref("");
const showPreview = ref(false);
const query = ref("");
const activeTab = ref("content");

const isEditing = computed(() => !!selectedId.value);
const selectedPost = computed(
  () => data.value?.data?.find((p) => p.id === selectedId.value) || null
);
const selectedStatus = computed(() => {
  if (!isEditing.value) return "draft";
  return selectedPost.value ? computeStatus(selectedPost.value) : "draft";
});

function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleString("en-US", { dateStyle: "medium", timeStyle: "short" });
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
  if (!post.published_at) return "draft";
  const d = new Date(post.published_at);
  if (isNaN(d.getTime())) return "draft";
  return d.getTime() <= Date.now() ? "published" : "scheduled";
}

function statusLabel(value) {
  switch (value) {
    case "published":
      return "published";
    case "scheduled":
      return "scheduled";
    case "draft":
    default:
      return "draft";
  }
}

function resetForm() {
  selectedId.value = null;
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
}

function selectPost(post) {
  selectedId.value = post.id;
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
}

function setPublishNow() {
  form.value.published_at = toDateTimeLocal(new Date().toISOString());
}

function setStatusFilter(value) {
  status.value = value;
  page.value = 1;
  load();
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
  return `${minutes} min read`;
}

const previewBlocks = computed(() => parseContentBlocks(form.value.content));
const previewToc = computed(() =>
  previewBlocks.value.filter((b) => b.type === "h2" || b.type === "h3")
);

const filteredPosts = computed(() => {
  const list = data.value?.data || [];
  const q = query.value.trim().toLowerCase();
  if (!q) return list;
  return list.filter((post) => {
    const tagText = (post.tags || []).map((t) => t.name).join(" ");
    const author = post.user?.name || "";
    const base = [post.title, post.content, tagText, author, String(post.id)]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
    return base.includes(q);
  });
});

function onCoverChange(event) {
  const file = event.target.files?.[0] || null;
  coverFile.value = file;
  if (file) {
    coverPreview.value = URL.createObjectURL(file);
  } else {
    coverPreview.value = isEditing.value
      ? data.value?.data?.find((p) => p.id === selectedId.value)
          ?.cover_image_url || ""
      : "";
  }
}

async function load() {
  loading.value = true;
  error.value = null;

  try {
    data.value = await blogPosts.adminList({
      status: status.value || undefined,
      page: page.value,
      per_page: per_page.value,
    });
  } catch (e) {
    error.value = e?.response?.data?.message || "Failed to load posts.";
  } finally {
    loading.value = false;
  }
}

async function save() {
  formError.value = null;
  saving.value = true;

  try {
    const tags = tagsInput.value
      .split(",")
      .map((t) => t.trim())
      .filter(Boolean);

    const payload = {
      title: form.value.title?.trim(),
      content: form.value.content?.trim(),
      published_at: fromDateTimeLocal(form.value.published_at),
      cover_image: coverFile.value || undefined,
      tags,
    };

    let saved;
    if (isEditing.value) {
      saved = await blogPosts.adminUpdate(selectedId.value, payload);
    } else {
      saved = await blogPosts.adminCreate(payload);
    }

    await load();
    if (saved?.id) {
      const found = data.value?.data?.find((p) => p.id === saved.id);
      if (found) {
        selectPost(found);
      } else {
        selectedId.value = saved.id;
      }
    }
  } catch (e) {
    const msg = e?.response?.data?.message || "Failed to save post.";
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

async function saveCoverOnly() {
  if (!selectedId.value) return;
  if (!coverFile.value) {
    formError.value = "Pick a cover image first.";
    return;
  }

  saving.value = true;
  formError.value = null;

  try {
    const saved = await blogPosts.adminUpdate(selectedId.value, {
      cover_image: coverFile.value,
    });
    await load();
    coverFile.value = null;
    if (saved?.id) {
      const found = data.value?.data?.find((p) => p.id === saved.id);
      if (found) {
        selectPost(found);
      }
    }
  } catch (e) {
    formError.value =
      e?.response?.data?.message || "Failed to save cover image.";
  } finally {
    saving.value = false;
  }
}

async function remove() {
  if (!selectedId.value) return;
  if (!confirm("Are you sure you want to delete this post?")) return;

  deleting.value = true;
  formError.value = null;

  try {
    await blogPosts.adminDelete(selectedId.value);
    resetForm();
    await load();
  } catch (e) {
    formError.value = e?.response?.data?.message || "Failed to delete post.";
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

onMounted(load);
</script>

<template>
  <div class="admin-blog">
    <div class="command-bar">
      <div class="command-left">
        <div class="search-field">
          <input
            v-model="query"
            type="search"
            placeholder="Search by title, tags, author"
          />
        </div>
        <div class="segmented" role="group" aria-label="Status">
          <button
            type="button"
            :class="{ active: status === '' }"
            @click="setStatusFilter('')"
            :disabled="loading"
          >
            All
          </button>
          <button
            type="button"
            :class="{ active: status === 'draft' }"
            @click="setStatusFilter('draft')"
            :disabled="loading"
          >
            Drafts
          </button>
          <button
            type="button"
            :class="{ active: status === 'scheduled' }"
            @click="setStatusFilter('scheduled')"
            :disabled="loading"
          >
            Scheduled
          </button>
          <button
            type="button"
            :class="{ active: status === 'published' }"
            @click="setStatusFilter('published')"
            :disabled="loading"
          >
            Published
          </button>
        </div>
      </div>

      <div class="command-right">
        <label class="select-field">
          <span>Per page</span>
          <select
            v-model.number="per_page"
            @change="page = 1; load()"
            :disabled="loading"
          >
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
          </select>
        </label>
        <button class="ghost" @click="load" :disabled="loading">
          Refresh
        </button>
        <button class="primary" @click="resetForm" :disabled="saving || deleting">
          New post
        </button>
      </div>
    </div>

    <div v-if="error" class="error">
      {{ error }}
    </div>

    <div class="grid">
      <section class="panel list">
        <div class="panel-head">
          <div>
            <h2>Posts</h2>
            <div class="muted">
              {{ data?.total || 0 }} total
            </div>
          </div>
          <div class="page-meta">
            <span>Page</span>
            <strong>{{ data?.current_page || 1 }}</strong>
            <span>/ {{ data?.last_page || 1 }}</span>
          </div>
        </div>

        <div class="list-body">
          <div v-if="loading" class="muted pad">Loading...</div>

          <div v-else class="card-list">
            <button
              v-for="post in filteredPosts"
              :key="post.id"
              type="button"
              class="post-card"
              :class="{ active: post.id === selectedId }"
              @click="selectPost(post)"
            >
              <div class="card-main">
                <div class="card-title">{{ post.title || "(Untitled)" }}</div>
                <div class="card-meta">
                  <span>{{ post.user?.name || "-" }}</span>
                  <span>•</span>
                  <span>{{ formatDate(post.published_at) }}</span>
                </div>
                <div v-if="post.tags?.length" class="tag-row">
                  <span
                    v-for="tag in post.tags"
                    :key="tag.id || tag.name"
                    class="tag-chip"
                  >
                    {{ tag.name }}
                  </span>
                </div>
              </div>
              <div class="card-side">
                <span :class="['pill', computeStatus(post)]">
                  {{ statusLabel(computeStatus(post)) }}
                </span>
              </div>
            </button>

            <div v-if="filteredPosts.length === 0" class="empty">
              No posts found.
            </div>
          </div>
        </div>

        <div class="pager">
          <button class="ghost" @click="prevPage" :disabled="loading || page <= 1">
            Previous
          </button>
          <button
            class="ghost"
            @click="nextPage"
            :disabled="loading || (data && page >= data.last_page)"
          >
            Next
          </button>
        </div>
      </section>

      <section class="panel editor">
        <div class="editor-topbar">
          <div class="editor-title">
            <div>
              <h2>{{ isEditing ? "Editing" : "New post" }}</h2>
              <div class="muted">
                {{ isEditing ? `ID #${selectedId}` : "Draft" }}
              </div>
            </div>
            <div class="editor-badges">
              <span :class="['pill', selectedStatus]">
                {{ statusLabel(selectedStatus) }}
              </span>
              <span class="muted">
                {{ formatDate(selectedPost?.published_at) }}
              </span>
            </div>
          </div>

          <div class="editor-actions">
            <button class="primary" @click="save" :disabled="saving || deleting">
              {{ saving ? "Saving..." : "Save" }}
            </button>
            <button
              class="ghost"
              @click="showPreview = !showPreview"
              :disabled="saving || deleting"
            >
              {{ showPreview ? "Edit" : "Preview" }}
            </button>
            <button
              v-if="selectedStatus === 'draft'"
              class="ghost"
              @click="setPublishNow"
              :disabled="saving || deleting"
            >
              Publish now
            </button>
            <details v-if="isEditing" class="more-menu">
              <summary>More</summary>
              <div class="menu">
                <button
                  class="danger"
                  type="button"
                  @click="remove"
                  :disabled="saving || deleting"
                >
                  {{ deleting ? "Deleting..." : "Delete" }}
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
              <div class="preview-kicker">Preview</div>
              <div class="preview-meta">
                {{ readTimeFor(form.content) }}
              </div>
            </div>
            <h2 class="preview-title">{{ form.title || "Untitled" }}</h2>
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
                <div class="preview-toc-title">Contents</div>
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
            <input
              v-model="form.title"
              type="text"
              class="title-input"
              placeholder="Write a clear, short headline"
            />

            <div class="tabs">
              <button
                type="button"
                :class="{ active: activeTab === 'content' }"
                @click="activeTab = 'content'"
              >
                Content
              </button>
              <button
                type="button"
                :class="{ active: activeTab === 'meta' }"
                @click="activeTab = 'meta'"
              >
                Meta
              </button>
              <button
                type="button"
                :class="{ active: activeTab === 'media' }"
                @click="activeTab = 'media'"
              >
                Media
              </button>
            </div>

            <div class="tab-body">
              <div v-show="activeTab === 'content'" class="tab-panel">
                <label class="field-block">
                  <span>Content</span>
                  <textarea
                    v-model="form.content"
                    rows="14"
                    placeholder="Full article content..."
                  />
                </label>
              </div>

              <div v-show="activeTab === 'meta'" class="tab-panel">
                <label class="field-block">
                  <span>Tags (comma separated)</span>
                  <input
                    v-model="tagsInput"
                    type="text"
                    placeholder="e.g. planets, comets, observation"
                  />
                </label>

                <label class="field-block">
                  <span>Publish from</span>
                  <input v-model="form.published_at" type="datetime-local" />
                </label>

                <button
                  class="ghost"
                  @click="setPublishNow"
                  :disabled="saving || deleting"
                >
                  Publish now
                </button>
              </div>

              <div v-show="activeTab === 'media'" class="tab-panel">
                <label class="field-block">
                  <span>Cover image</span>
                  <input type="file" accept="image/*" @change="onCoverChange" />
                </label>

                <div v-if="coverPreview" class="cover-preview">
                  <img :src="coverPreview" alt="Cover preview" />
                </div>

                <button
                  v-if="isEditing"
                  class="ghost"
                  @click="saveCoverOnly"
                  :disabled="saving || deleting"
                >
                  Save cover only
                </button>
              </div>
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
  display: flex;
  flex-direction: column;
  gap: 16px;
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
  backdrop-filter: blur(10px);
}

.command-left,
.command-right {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.search-field input {
  min-width: 220px;
  padding: 10px 14px;
  border-radius: 999px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: inherit;
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
  grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
  gap: 16px;
  min-height: 70vh;
}

.panel {
  border-radius: 18px;
  background: var(--panel);
  border: 1px solid var(--panel-border);
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
  font-size: 16px;
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
}

.post-card:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.08);
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

.tag-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.tag-chip {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.15);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.card-side {
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
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

.editor-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.editor-body {
  flex: 1;
  padding: 16px;
  overflow: auto;
}

.editor-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.title-input {
  font-size: 22px;
  font-weight: 600;
  padding: 12px 14px;
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

.tab-body {
  background: rgb(var(--color-bg-rgb) / 0.55);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 16px;
  padding: 16px;
}

.tab-panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
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
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid var(--panel-border);
  background: rgb(var(--color-bg-rgb) / 0.6);
  color: inherit;
  font-size: 14px;
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

button.ghost {
  background: transparent;
}

button.danger {
  background: rgb(var(--color-danger-rgb) / 0.2);
  border-color: rgb(var(--color-danger-rgb) / 0.5);
  color: var(--color-danger);
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

input:focus,
textarea:focus,
select:focus,
button:focus,
summary:focus {
  outline: none;
  box-shadow: 0 0 0 3px var(--ring);
}

@media (max-width: 980px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .command-bar {
    position: static;
  }
}

@media (max-width: 900px) {
  .preview-layout {
    grid-template-columns: 1fr;
  }
}
</style>


