<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { blogPosts } from "@/services/blogPosts";
import { blogComments } from "@/services/blogComments";
import { useAuthStore } from "@/stores/auth";

const route = useRoute();
const auth = useAuthStore();
const loading = ref(false);
const error = ref(null);
const post = ref(null);
const related = ref([]);
const copied = ref(false);
const commentsData = ref(null);
const commentsLoading = ref(false);
const commentsError = ref(null);
const commentInput = ref("");
const commentSubmitting = ref(false);
const commentPage = ref(1);

function setMeta({ title, description, image }) {
  if (typeof document === "undefined") return;
  document.title = title;

  const ensure = (name, property) => {
    let tag = document.querySelector(`meta[${property ? "property" : "name"}='${name}']`);
    if (!tag) {
      tag = document.createElement("meta");
      tag.setAttribute(property ? "property" : "name", name);
      document.head.appendChild(tag);
    }
    return tag;
  };

  ensure("description", false).setAttribute("content", description);
  ensure("og:title", true).setAttribute("content", title);
  ensure("og:description", true).setAttribute("content", description);
  if (image) {
    ensure("og:image", true).setAttribute("content", image);
  }
}

function shareUrl() {
  if (typeof window === "undefined") return "";
  return window.location.href;
}

async function copyLink() {
  const url = shareUrl();
  try {
    await navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  } catch {
    copied.value = false;
  }
}

function shareTo(platform) {
  const url = encodeURIComponent(shareUrl());
  const text = encodeURIComponent(post.value?.title || "");
  let share;
  if (platform === "x") {
    share = `https://x.com/intent/tweet?url=${url}&text=${text}`;
  } else if (platform === "facebook") {
    share = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
  } else if (platform === "linkedin") {
    share = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
  }
  if (share) {
    window.open(share, "_blank", "noopener,noreferrer");
  }
}

function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString("sk-SK", { dateStyle: "long" });
}

const readTime = computed(() => {
  const text = post.value?.content || "";
  const words = text.trim().split(/\s+/).filter(Boolean).length;
  const minutes = Math.max(1, Math.round(words / 220));
  return `${minutes} min čítania`;
});

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

const contentBlocks = computed(() => {
  const raw = post.value?.content || "";
  if (!raw.trim()) return [];

  const lines = raw.split(/\r?\n/);
  const blocks = [];
  let buffer = [];
  let listBuffer = [];

  const flushParagraph = () => {
    const text = buffer.join(" ").trim();
    if (text) {
      blocks.push({ type: "p", html: inlineMarkdown(text) });
    }
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
});

const tocItems = computed(() =>
  contentBlocks.value.filter((b) => b.type === "h2" || b.type === "h3")
);

async function load() {
  const slug = String(route.params.slug || "");
  if (!slug) {
    error.value = "Neplatný článok.";
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    post.value = await blogPosts.getPublic(slug);
    await loadComments();
    try {
      related.value = await blogPosts.getRelated(slug);
    } catch {
      related.value = [];
    }

    const desc = post.value?.content
      ? String(post.value.content).replace(/\s+/g, " ").trim().slice(0, 160)
      : "Článok z oblasti astronómie a pozorovania oblohy.";
    setMeta({
      title: `${post.value?.title || "Článok"} | Nebeský sprievodca`,
      description: desc,
      image: post.value?.cover_image_url || null,
    });
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Článok sa nepodarilo načítať.";
  } finally {
    loading.value = false;
  }
}

async function loadComments() {
  const slug = String(route.params.slug || "");
  if (!slug) return;
  commentsLoading.value = true;
  commentsError.value = null;
  try {
    commentsData.value = await blogComments.list(slug, {
      page: commentPage.value,
    });
  } catch (e) {
    commentsError.value =
      e?.response?.data?.message || "Chyba pri načítaní komentárov.";
  } finally {
    commentsLoading.value = false;
  }
}

async function submitComment() {
  if (!auth.isAuthed) {
    commentsError.value = "Pre pridanie komentára sa prihlás.";
    return;
  }
  const slug = String(route.params.slug || "");
  const content = commentInput.value.trim();
  if (!content) return;

  commentSubmitting.value = true;
  commentsError.value = null;
  try {
    await blogComments.create(slug, { content });
    commentInput.value = "";
    commentPage.value = 1;
    await loadComments();
  } catch (e) {
    commentsError.value =
      e?.response?.data?.message || "Komentár sa nepodarilo uložiť.";
  } finally {
    commentSubmitting.value = false;
  }
}

async function removeComment(id) {
  const slug = String(route.params.slug || "");
  try {
    await blogComments.remove(slug, id);
    await loadComments();
  } catch (e) {
    commentsError.value =
      e?.response?.data?.message || "Komentár sa nepodarilo zmazať.";
  }
}

function prevComments() {
  if (!commentsData.value || commentPage.value <= 1) return;
  commentPage.value -= 1;
  loadComments();
}

function nextComments() {
  if (!commentsData.value || commentPage.value >= commentsData.value.last_page) return;
  commentPage.value += 1;
  loadComments();
}
onMounted(load);
watch(
  () => route.params.slug,
  () => load()
);
</script>

<template>
  <article class="detail">
    <router-link class="back" to="/learn">← Späť na články</router-link>

    <div v-if="error" class="error">{{ error }}</div>
    <div v-else-if="loading" class="muted">Načítavam článok…</div>

    <template v-else-if="post">
      <header class="hero">
        <div class="hero-left">
          <p class="kicker">Vzdelávanie</p>
          <h1>{{ post.title }}</h1>
          <div v-if="post.tags?.length" class="taglist">
            <span v-for="tag in post.tags" :key="tag.id" class="tag-pill">
              {{ tag.name }}
            </span>
          </div>
          <div class="meta">
            <span>{{ formatDate(post.published_at) }}</span>
            <span>•</span>
            <span>{{ post.user?.name || "Redakcia" }}</span>
            <span>•</span>
            <span>{{ readTime }}</span>
            <span v-if="typeof post.views === 'number'">•</span>
            <span v-if="typeof post.views === 'number'">
              {{ post.views }} zobrazení
            </span>
          </div>
          <div class="share">
            <button class="share-btn" @click="copyLink">
              {{ copied ? "Skopírované" : "Kopírovať link" }}
            </button>
            <button class="share-btn" @click="shareTo('x')">X</button>
            <button class="share-btn" @click="shareTo('facebook')">Facebook</button>
            <button class="share-btn" @click="shareTo('linkedin')">LinkedIn</button>
          </div>
        </div>

        <div v-if="post.cover_image_url" class="hero-media">
          <img :src="post.cover_image_url" :alt="post.title" />
        </div>
      </header>

      <section class="layout">
        <aside class="rail">
          <div class="rail-card">
            <div class="rail-label">Rýchly prehľad</div>
            <div class="rail-meta">
              <div class="rail-item">
                <span>Publikované</span>
                <strong>{{ formatDate(post.published_at) }}</strong>
              </div>
              <div class="rail-item">
                <span>Autor</span>
                <strong>{{ post.user?.name || "Redakcia" }}</strong>
              </div>
              <div class="rail-item">
                <span>Čas čítania</span>
                <strong>{{ readTime }}</strong>
              </div>
            </div>
          </div>

          <div v-if="tocItems.length" class="toc">
            <div class="toc-title">Obsah</div>
            <ul>
              <li v-for="item in tocItems" :key="item.id" :class="item.type">
                <a :href="`#${item.id}`">{{ item.text }}</a>
              </li>
            </ul>
          </div>
        </aside>

        <div class="content">
          <template v-for="(block, i) in contentBlocks" :key="i">
            <h2 v-if="block.type === 'h2'" :id="block.id">{{ block.text }}</h2>
            <h3 v-else-if="block.type === 'h3'" :id="block.id">
              {{ block.text }}
            </h3>
            <ul v-else-if="block.type === 'ul'">
              <li v-for="(item, idx) in block.items" :key="idx" v-html="item"></li>
            </ul>
            <p v-else v-html="block.html"></p>
          </template>
        </div>
      </section>

      <section class="comments">
        <div class="comments-head">
          <h2>Komentáre</h2>
          <span v-if="post?.comments_count" class="muted">
            {{ post.comments_count }} celkom
          </span>
        </div>

        <div v-if="commentsError" class="error">{{ commentsError }}</div>

        <div v-if="auth.isAuthed" class="comment-form">
          <textarea
            v-model="commentInput"
            rows="3"
            placeholder="Napíš svoj komentár..."
          ></textarea>
          <button
            class="ghost"
            @click="submitComment"
            :disabled="commentSubmitting || !commentInput.trim()"
          >
            {{ commentSubmitting ? "Odosielam..." : "Pridať komentár" }}
          </button>
        </div>
        <div v-else class="comment-locked">
          Pre pridanie komentára sa prihlás.
        </div>

        <div v-if="commentsLoading" class="muted">Načítavam komentáre…</div>

        <div v-else class="comment-list">
          <div v-if="(commentsData?.data || []).length === 0" class="muted">
            Zatiaľ žiadne komentáre.
          </div>
          <article v-for="c in commentsData?.data || []" :key="c.id" class="comment">
            <div class="comment-meta">
              <strong>{{ c.user?.name || "Používateľ" }}</strong>
              <span>•</span>
              <span>{{ formatDate(c.created_at) }}</span>
            </div>
            <p>{{ c.content }}</p>
            <button
              v-if="auth.user && (auth.user.id === c.user_id || auth.user.is_admin)"
              class="ghost danger"
              @click="removeComment(c.id)"
            >
              Zmazať
            </button>
          </article>
        </div>

        <div v-if="commentsData" class="pager comments-pager">
          <button class="ghost" @click="prevComments" :disabled="commentsLoading || commentPage <= 1">
            Predošlé
          </button>
          <div class="muted">
            Strana {{ commentsData.current_page }} z {{ commentsData.last_page }}
          </div>
          <button
            class="ghost"
            @click="nextComments"
            :disabled="commentsLoading || commentPage >= commentsData.last_page"
          >
            Ďalšie
          </button>
        </div>
      </section>

      <section v-if="related.length" class="related">
        <div class="related-head">
          <h2>Podobné články</h2>
          <p>Výber na základe spoločných tém.</p>
        </div>
        <div class="related-grid">
          <article v-for="item in related" :key="item.id" class="related-card">
            <div
              v-if="item.cover_image_url"
              class="related-media"
              :style="{ backgroundImage: `url(${item.cover_image_url})` }"
            ></div>
            <div class="related-body">
              <div class="related-tags" v-if="item.tags?.length">
                <span v-for="tag in item.tags" :key="tag.id" class="tag-pill">
                  {{ tag.name }}
                </span>
              </div>
              <h3>
                <router-link :to="`/learn/${item.slug || item.id}`">
                  {{ item.title }}
                </router-link>
              </h3>
              <div class="related-meta">
                <span>{{ formatDate(item.published_at) }}</span>
                <span>•</span>
                <span>{{ item.user?.name || "Redakcia" }}</span>
              </div>
            </div>
          </article>
        </div>
      </section>
    </template>
  </article>
</template>

<style scoped>
.detail {
  display: flex;
  flex-direction: column;
  gap: 18px;
  font-family: "Georgia", "Times New Roman", serif;
}

.back {
  text-decoration: none;
  color: rgba(186, 230, 253, 0.9);
  font-size: 13px;
}

.hero {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.9fr);
  gap: 18px;
  padding: 24px;
  border-radius: 22px;
  background: linear-gradient(
      140deg,
      rgb(var(--color-bg-rgb) / 0.95),
      rgba(2, 132, 199, 0.15)
    ),
    radial-gradient(circle at 80% 10%, rgba(94, 234, 212, 0.2), transparent 40%);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.hero-left {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.kicker {
  text-transform: uppercase;
  letter-spacing: 0.25em;
  font-size: 12px;
  color: rgb(var(--color-surface-rgb) / 0.7);
  margin: 0 0 10px;
}

.hero h1 {
  margin: 0;
  font-size: 34px;
  line-height: 1.2;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  color: rgb(var(--color-surface-rgb) / 0.75);
  font-size: 13px;
}

.share {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.share-btn {
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: inherit;
  font-size: 12px;
  cursor: pointer;
}

.share-btn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.4);
  color: var(--color-primary);
}

.taglist {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-pill {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  color: rgb(var(--color-surface-rgb) / 0.8);
}

.hero-media {
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background: rgb(var(--color-bg-rgb) / 0.6);
  min-height: 220px;
}

.hero-media img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.layout {
  display: grid;
  grid-template-columns: minmax(0, 0.65fr) minmax(0, 2fr);
  gap: 16px;
}

.rail {
  display: grid;
  gap: 12px;
  align-self: start;
  position: sticky;
  top: 90px;
}

.rail-card {
  padding: 14px;
  border-radius: 14px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.rail-label {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 11px;
  color: rgb(var(--color-surface-rgb) / 0.6);
  margin-bottom: 10px;
}

.rail-meta {
  display: grid;
  gap: 8px;
  font-size: 13px;
}

.rail-item {
  display: grid;
  gap: 4px;
}

.rail-item span {
  color: rgb(var(--color-surface-rgb) / 0.6);
  font-size: 12px;
}

.rail-item strong {
  font-weight: 600;
}

.toc {
  padding: 14px;
  border-radius: 14px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  font-size: 13px;
}

.toc-title {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 11px;
  color: rgb(var(--color-surface-rgb) / 0.6);
  margin-bottom: 10px;
}

.toc ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 6px;
}

.toc li a {
  color: rgba(186, 230, 253, 0.9);
  text-decoration: none;
}

.toc li.h3 {
  margin-left: 10px;
  font-size: 12px;
}

.content {
  padding: 16px 22px 26px;
  border-radius: 18px;
  background: rgb(var(--color-bg-rgb) / 0.8);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  line-height: 1.8;
  font-size: 16px;
  color: rgb(var(--color-surface-rgb) / 0.9);
}

.content p {
  margin: 0 0 16px;
}

.content p:last-child {
  margin-bottom: 0;
}

.content ul {
  margin: 0 0 16px;
  padding-left: 18px;
}

.content li {
  margin: 6px 0;
}

.content a {
  color: var(--color-primary);
  text-decoration: underline;
}

.content code {
  font-family: "Courier New", monospace;
  font-size: 0.9em;
  background: rgb(var(--color-bg-rgb) / 0.6);
  padding: 2px 5px;
  border-radius: 6px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
}

.content h2,
.content h3 {
  margin: 22px 0 10px;
  line-height: 1.4;
}

.content h2 {
  font-size: 22px;
}

.content h3 {
  font-size: 18px;
  color: rgb(var(--color-surface-rgb) / 0.85);
}

.error {
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.5);
  background: rgb(var(--color-danger-rgb) / 0.15);
  color: var(--color-danger);
}

.muted {
  color: rgb(var(--color-surface-rgb) / 0.7);
  font-size: 14px;
}

.related {
  margin-top: 6px;
  padding: 18px;
  border-radius: 18px;
  background: rgb(var(--color-bg-rgb) / 0.75);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.comments {
  margin-top: 10px;
  padding: 18px;
  border-radius: 18px;
  background: rgb(var(--color-bg-rgb) / 0.75);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.comments-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 12px;
}

.comment-form {
  display: grid;
  gap: 10px;
  margin-bottom: 12px;
}

.comment-form textarea {
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.6);
  color: inherit;
}

.comment-locked {
  padding: 12px;
  border-radius: 12px;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.35);
  color: rgb(var(--color-surface-rgb) / 0.7);
  font-size: 13px;
}

.comment-list {
  display: grid;
  gap: 10px;
}

.comment {
  padding: 12px;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.6);
}

.comment-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: rgb(var(--color-surface-rgb) / 0.65);
  margin-bottom: 6px;
}

.comment p {
  margin: 0 0 8px;
  line-height: 1.6;
}

.comments-pager {
  margin-top: 10px;
}

.ghost.danger {
  border-color: rgb(var(--color-danger-rgb) / 0.45);
  color: var(--color-danger);
}

.related-head h2 {
  margin: 0 0 6px;
  font-size: 20px;
}

.related-head p {
  margin: 0 0 16px;
  color: rgb(var(--color-surface-rgb) / 0.7);
  font-size: 13px;
}

.related-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.related-card {
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.8);
  display: flex;
  flex-direction: column;
}

.related-media {
  height: 120px;
  background-size: cover;
  background-position: center;
}

.related-body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.related-body h3 {
  margin: 0;
  font-size: 16px;
  line-height: 1.4;
}

.related-body h3 a {
  color: inherit;
  text-decoration: none;
}

.related-body h3 a:hover {
  color: var(--color-primary);
}

.related-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 12px;
  color: rgb(var(--color-surface-rgb) / 0.6);
}

.related-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

@media (max-width: 900px) {
  .hero {
    grid-template-columns: 1fr;
  }

  .layout {
    grid-template-columns: 1fr;
  }

  .rail {
    position: static;
  }
}
</style>
