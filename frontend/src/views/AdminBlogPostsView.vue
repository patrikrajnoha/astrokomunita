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

const isEditing = computed(() => !!selectedId.value);

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
  if (!post.published_at) return "draft";
  const d = new Date(post.published_at);
  if (isNaN(d.getTime())) return "draft";
  return d.getTime() <= Date.now() ? "published" : "scheduled";
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
}

function setPublishNow() {
  form.value.published_at = toDateTimeLocal(new Date().toISOString());
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

  const flushParagraph = () => {
    const t = buffer.join(" ").trim();
    if (t) blocks.push({ type: "p", html: inlineMarkdown(t) });
    buffer = [];
  };

  const flushList = () => {
    if (listBuffer.length) {
      blocks.push({
        type: "ul",
        items: listBuffer.map((item) => inlineMarkdown(item)),
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
  return `${minutes} min čítania`;
}

const previewBlocks = computed(() => parseContentBlocks(form.value.content));
const previewToc = computed(() =>
  previewBlocks.value.filter((b) => b.type === "h2" || b.type === "h3")
);

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
    error.value =
      e?.response?.data?.message || "Chyba pri načítaní článkov.";
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
    const msg =
      e?.response?.data?.message || "Chyba pri ukladaní článku.";
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
    formError.value = "Najprv vyber cover obrázok.";
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
      e?.response?.data?.message || "Chyba pri ukladaní cover obrázka.";
  } finally {
    saving.value = false;
  }
}
async function remove() {
  if (!selectedId.value) return;
  if (!confirm("Naozaj chceš zmazať tento článok?")) return;

  deleting.value = true;
  formError.value = null;

  try {
    await blogPosts.adminDelete(selectedId.value);
    resetForm();
    await load();
  } catch (e) {
    formError.value =
      e?.response?.data?.message || "Chyba pri mazaní článku.";
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
    <div class="header">
      <div>
        <h1>Správa článkov</h1>
        <p>Admin panel pre tvorbu a publikovanie obsahu.</p>
      </div>
      <div class="header-actions">
        <button class="ghost" @click="resetForm" :disabled="saving || deleting">
          Nový článok
        </button>
      </div>
    </div>

    <div class="filters">
      <div class="field">
        <label>Status</label>
        <select v-model="status" @change="page = 1; load()" :disabled="loading">
          <option value="">všetko</option>
          <option value="published">published</option>
          <option value="scheduled">scheduled</option>
          <option value="draft">draft</option>
        </select>
      </div>

      <div class="field">
        <label>Per page</label>
        <select
          v-model.number="per_page"
          @change="page = 1; load()"
          :disabled="loading"
        >
          <option :value="5">5</option>
          <option :value="10">10</option>
          <option :value="20">20</option>
        </select>
      </div>

      <div class="field grow">
        <div class="stat">
          <span>Page</span>
          <strong>{{ data?.current_page || 1 }}</strong>
          <span>/ {{ data?.last_page || 1 }}</span>
        </div>
      </div>

      <div class="field buttons">
        <button class="ghost" @click="load" :disabled="loading">Refresh</button>
      </div>
    </div>

    <div v-if="error" class="error">
      {{ error }}
    </div>

    <div class="grid">
      <section class="panel list">
        <div class="panel-head">
          <h2>Články</h2>
          <div class="muted">
            {{ data?.total || 0 }} celkom
          </div>
        </div>

        <div v-if="loading" class="muted pad">Loading...</div>

        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Titulok</th>
                <th>Status</th>
                <th>Publikované</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="post in data?.data || []"
                :key="post.id"
                :class="{ active: post.id === selectedId }"
                @click="selectPost(post)"
              >
                <td>#{{ post.id }}</td>
            <td class="title-cell">
              <div class="title">{{ post.title }}</div>
              <div class="meta">Autor: {{ post.user?.name || "-" }}</div>
              <div v-if="post.tags?.length" class="tagline">
                Tagy: {{ post.tags.map((t) => t.name).join(", ") }}
              </div>
            </td>
                <td>
                  <span :class="['pill', computeStatus(post)]">
                    {{ computeStatus(post) }}
                  </span>
                </td>
                <td>{{ formatDate(post.published_at) }}</td>
              </tr>
              <tr v-if="(data?.data || []).length === 0">
                <td colspan="4" class="empty">Žiadne články.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="pager">
          <button class="ghost" @click="prevPage" :disabled="loading || page <= 1">
            Prev
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
        <div class="panel-head">
          <h2>{{ isEditing ? "Editácia" : "Nový článok" }}</h2>
          <div class="muted">
            {{ isEditing ? `ID #${selectedId}` : "Draft" }}
          </div>
        </div>

        <div v-if="formError" class="error">
          {{ formError }}
        </div>

        <div class="form">
          <label>
            <span>Titulok</span>
            <input
              v-model="form.title"
              type="text"
              placeholder="Napíš jasný, krátky nadpis"
            />
          </label>

          <label>
            <span>Obsah</span>
            <textarea
              v-model="form.content"
              rows="10"
              placeholder="Plný obsah článku..."
            />
          </label>

          <label>
            <span>Tagy (oddelené čiarkou)</span>
            <input
              v-model="tagsInput"
              type="text"
              placeholder="napr. planéty, kométy, pozorovanie"
            />
          </label>

          <label>
            <span>Publikovať od</span>
            <input v-model="form.published_at" type="datetime-local" />
          </label>

          <label>
            <span>Cover image</span>
            <input type="file" accept="image/*" @change="onCoverChange" />
          </label>

          <div v-if="coverPreview" class="cover-preview">
            <img :src="coverPreview" alt="Cover preview" />
          </div>

          <div class="actions">
            <button class="accent" @click="save" :disabled="saving || deleting">
              {{ saving ? "Ukladám..." : "Uložiť" }}
            </button>
            <button
              v-if="isEditing"
              class="ghost"
              @click="saveCoverOnly"
              :disabled="saving || deleting"
            >
              Uložiť len cover
            </button>
            <button
              class="ghost"
              @click="showPreview = !showPreview"
              :disabled="saving || deleting"
            >
              {{ showPreview ? "Skryť preview" : "Preview" }}
            </button>
            <button class="ghost" @click="setPublishNow" :disabled="saving || deleting">
              Publikovať teraz
            </button>
            <button
              v-if="isEditing"
              class="danger"
              @click="remove"
              :disabled="saving || deleting"
            >
              {{ deleting ? "Mažem..." : "Zmazať" }}
            </button>
          </div>
        </div>

        <div v-if="showPreview" class="preview">
          <div class="preview-header">
            <div class="preview-kicker">Preview</div>
            <div class="preview-meta">
              {{ readTimeFor(form.content) }}
            </div>
          </div>
          <h2 class="preview-title">{{ form.title || "Bez názvu" }}</h2>
          <div v-if="tagsInput" class="taglist">
            <span
              v-for="tag in tagsInput.split(',').map(t => t.trim()).filter(Boolean)"
              :key="tag"
              class="tag-pill"
            >
              {{ tag }}
            </span>
          </div>

          <div v-if="coverPreview" class="preview-cover">
            <img :src="coverPreview" alt="Preview cover" />
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
              <template v-for="(block, i) in previewBlocks" :key="i">
                <h3 v-if="block.type === 'h2'">{{ block.text }}</h3>
                <h4 v-else-if="block.type === 'h3'">{{ block.text }}</h4>
                <ul v-else-if="block.type === 'ul'">
                  <li v-for="(item, idx) in block.items" :key="idx" v-html="item"></li>
                </ul>
                <p v-else v-html="block.html"></p>
              </template>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.admin-blog {
  --panel: rgba(15, 23, 42, 0.85);
  --panel-border: rgba(148, 163, 184, 0.25);
  --muted: rgba(226, 232, 240, 0.7);
  --accent: #22c55e;
  --danger: #ef4444;
  --ring: rgba(56, 189, 248, 0.4);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
  border-radius: 16px;
  background: linear-gradient(120deg, rgba(15, 23, 42, 0.9), rgba(2, 132, 199, 0.2));
  border: 1px solid var(--panel-border);
}

.header h1 {
  margin: 0 0 6px;
  font-size: 24px;
}

.header p {
  margin: 0;
  color: var(--muted);
  font-size: 14px;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px;
  border-radius: 14px;
  background: var(--panel);
  border: 1px solid var(--panel-border);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 140px;
}

.field label {
  font-size: 12px;
  color: var(--muted);
}

.field select,
.field input {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--panel-border);
  background: rgba(15, 23, 42, 0.6);
  color: inherit;
}

.field.grow {
  flex: 1;
  justify-content: flex-end;
}

.stat {
  display: flex;
  align-items: baseline;
  gap: 6px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(2, 132, 199, 0.12);
  border: 1px solid rgba(2, 132, 199, 0.25);
}

.buttons {
  justify-content: flex-end;
}

.error {
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.4);
  color: #fecaca;
}

.grid {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
  gap: 16px;
}

.panel {
  border-radius: 16px;
  background: var(--panel);
  border: 1px solid var(--panel-border);
  overflow: hidden;
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
}

.panel-head h2 {
  margin: 0;
  font-size: 16px;
}

.muted {
  color: var(--muted);
  font-size: 12px;
}

.pad {
  padding: 16px;
}

.table-wrap {
  overflow: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: rgba(148, 163, 184, 0.08);
}

th,
td {
  padding: 12px;
  text-align: left;
  font-size: 13px;
}

tbody tr {
  border-top: 1px solid rgba(148, 163, 184, 0.08);
  cursor: pointer;
}

tbody tr.active {
  background: rgba(2, 132, 199, 0.15);
}

.title-cell .title {
  font-weight: 600;
}

.title-cell .meta {
  font-size: 12px;
  color: var(--muted);
  margin-top: 4px;
}

.title-cell .tagline {
  font-size: 11px;
  color: rgba(226, 232, 240, 0.6);
  margin-top: 4px;
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
  background: rgba(34, 197, 94, 0.2);
  color: #86efac;
  border: 1px solid rgba(34, 197, 94, 0.4);
}

.pill.draft {
  background: rgba(248, 113, 113, 0.2);
  color: #fecaca;
  border: 1px solid rgba(248, 113, 113, 0.3);
}

.pill.scheduled {
  background: rgba(56, 189, 248, 0.2);
  color: #bae6fd;
  border: 1px solid rgba(56, 189, 248, 0.4);
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
  border-top: 1px solid rgba(148, 163, 184, 0.12);
}

.form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
}

.form label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.form input,
.form textarea {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--panel-border);
  background: rgba(15, 23, 42, 0.6);
  color: inherit;
  font-size: 14px;
}

.cover-preview {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, 0.25);
  background: rgba(15, 23, 42, 0.5);
}

.cover-preview img {
  width: 100%;
  display: block;
  max-height: 220px;
  object-fit: cover;
}

.form input:focus,
.form textarea:focus,
.field select:focus {
  outline: none;
  box-shadow: 0 0 0 3px var(--ring);
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.preview {
  margin-top: 16px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.25);
  background: rgba(15, 23, 42, 0.7);
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
  color: rgba(226, 232, 240, 0.6);
}

.preview-meta {
  font-size: 12px;
  color: rgba(226, 232, 240, 0.7);
}

.preview-title {
  margin: 0 0 10px;
  font-size: 22px;
}

.preview-cover {
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 12px;
  border: 1px solid rgba(148, 163, 184, 0.25);
}

.preview-cover img {
  display: block;
  width: 100%;
  max-height: 200px;
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
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: rgba(15, 23, 42, 0.6);
  font-size: 12px;
}

.preview-toc-title {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 10px;
  color: rgba(226, 232, 240, 0.6);
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
  color: rgba(226, 232, 240, 0.85);
}

.preview-content p {
  margin: 0 0 10px;
  color: rgba(226, 232, 240, 0.85);
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
  color: #bae6fd;
  text-decoration: underline;
}

.preview-content code {
  font-family: "Courier New", monospace;
  font-size: 0.9em;
  background: rgba(15, 23, 42, 0.6);
  padding: 2px 5px;
  border-radius: 6px;
  border: 1px solid rgba(148, 163, 184, 0.3);
}

button {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--panel-border);
  background: rgba(148, 163, 184, 0.08);
  color: inherit;
  cursor: pointer;
}

button.accent {
  background: rgba(34, 197, 94, 0.18);
  border-color: rgba(34, 197, 94, 0.45);
  color: #bbf7d0;
}

button.danger {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.5);
  color: #fecaca;
}

button.ghost {
  background: transparent;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 980px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .header {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 900px) {
  .preview-layout {
    grid-template-columns: 1fr;
  }
}
</style>
