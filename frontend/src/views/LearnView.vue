<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { blogPosts } from "@/services/blogPosts";

const loading = ref(false);
const error = ref(null);
const data = ref(null);
const page = ref(1);
const tags = ref([]);
const selectedTag = ref("");
const search = ref("");
const searchInput = ref("");

function setMeta({ title, description }) {
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
}

const featuredPost = computed(() => {
  if (!data.value || page.value !== 1) return null;
  return data.value.data?.[0] || null;
});

const listPosts = computed(() => {
  if (!data.value) return [];
  if (page.value === 1) {
    return data.value.data?.slice(1) || [];
  }
  return data.value.data || [];
});

function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString("sk-SK", { dateStyle: "long" });
}

function excerpt(text, limit = 200) {
  if (!text) return "";
  const cleaned = String(text).replace(/\s+/g, " ").trim();
  if (cleaned.length <= limit) return cleaned;
  return `${cleaned.slice(0, limit).trim()}…`;
}

function readTime(text) {
  if (!text) return "1 min čítania";
  const words = text.trim().split(/\s+/).filter(Boolean).length;
  const minutes = Math.max(1, Math.round(words / 220));
  return `${minutes} min čítania`;
}

async function load() {
  loading.value = true;
  error.value = null;
  try {
    data.value = await blogPosts.listPublic({
      page: page.value,
      tag: selectedTag.value || undefined,
      q: search.value || undefined,
    });
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Chyba pri načítaní článkov.";
  } finally {
    loading.value = false;
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

async function loadTags() {
  try {
    tags.value = await blogPosts.listTagsPublic();
  } catch {
    tags.value = [];
  }
}

onMounted(loadTags);

function selectTag(slug) {
  selectedTag.value = slug;
  page.value = 1;
  load();
}

function applySearch() {
  search.value = searchInput.value.trim();
  page.value = 1;
  load();
}

function clearSearch() {
  search.value = "";
  searchInput.value = "";
  page.value = 1;
  load();
}

function highlight(text) {
  if (!text) return "";
  if (!search.value) return text;
  const safe = search.value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const re = new RegExp(`(${safe})`, "gi");
  return text.replace(re, "<mark>$1</mark>");
}

watch(
  () => [selectedTag.value, search.value],
  () => {
    const tagLabel = selectedTag.value
      ? ` • ${tags.value.find((t) => t.slug === selectedTag.value)?.name || "Tag"}`
      : "";
    const searchLabel = search.value ? ` • Hľadanie: ${search.value}` : "";
    setMeta({
      title: `Vzdelávanie${tagLabel}${searchLabel} | Nebeský sprievodca`,
      description:
        "Kvalitné články o astronómii, pozorovaniach a dianí na nočnej oblohe.",
    });
  },
  { immediate: true }
);
</script>

<template>
  <section class="learn">
    <header class="hero">
      <div>
        <p class="kicker">Nebeský sprievodca</p>
        <h1>Vzdelávanie a články</h1>
        <p class="lead">
          Dlhšie, kvalitné texty o astronómii, pozorovaniach a dianí na nočnej
          oblohe.
        </p>
      </div>
      <div class="hero-card">
        <div class="hero-label">Nový obsah každý týždeň</div>
        <div class="hero-meta">Kurátorované redakciou</div>
      </div>
    </header>

    <div v-if="error" class="error">{{ error }}</div>

    <div class="filters">
      <div v-if="tags.length" class="tag-filter">
        <button
          class="tag"
          :class="{ active: !selectedTag }"
          @click="selectTag('')"
        >
          Všetko
        </button>
        <button
          v-for="tag in tags"
          :key="tag.id"
          class="tag"
          :class="{ active: selectedTag === tag.slug }"
          @click="selectTag(tag.slug)"
        >
          {{ tag.name }}
          <span class="count">{{ tag.published_posts_count }}</span>
        </button>
      </div>

      <div class="search">
        <input
          v-model="searchInput"
          type="text"
          placeholder="Hľadaj v článkoch..."
          @keyup.enter="applySearch"
        />
        <button class="ghost" @click="applySearch">Hľadať</button>
        <button class="ghost" @click="clearSearch" :disabled="!search">
          Reset
        </button>
      </div>
    </div>

    <div v-if="loading" class="muted">Načítavam články…</div>

    <div v-if="data && !loading" class="grid">
      <article v-if="featuredPost" class="featured-hero">
        <div
          v-if="featuredPost.cover_image_url"
          class="featured-media"
          :style="{ backgroundImage: `url(${featuredPost.cover_image_url})` }"
        ></div>
        <div class="featured-content">
          <p class="card-kicker">Hlavný článok</p>
          <h2 class="featured-title">
            <router-link :to="`/learn/${featuredPost.slug || featuredPost.id}`">
              {{ featuredPost.title }}
            </router-link>
          </h2>
          <p
            class="card-excerpt"
            v-html="highlight(excerpt(featuredPost.content, 260))"
          ></p>
          <div v-if="featuredPost.tags?.length" class="taglist">
            <span v-for="tag in featuredPost.tags" :key="tag.id" class="tag-pill">
              {{ tag.name }}
            </span>
          </div>
          <div class="card-meta">
            <span>{{ formatDate(featuredPost.published_at) }}</span>
            <span>•</span>
            <span>{{ featuredPost.user?.name || "Redakcia" }}</span>
            <span>•</span>
            <span>{{ readTime(featuredPost.content) }}</span>
          </div>
          <router-link class="cta" :to="`/learn/${featuredPost.slug || featuredPost.id}`">
            Čítať článok →
          </router-link>
        </div>
      </article>

      <article
        v-for="post in listPosts"
        :key="post.id"
        class="card"
      >
        <div
          class="card-media"
          v-if="post.cover_image_url"
          :style="{ backgroundImage: `url(${post.cover_image_url})` }"
        ></div>
        <div class="card-body">
          <p class="card-kicker">Vzdelávanie</p>
          <h2 class="card-title">
            <router-link :to="`/learn/${post.slug || post.id}`">{{ post.title }}</router-link>
          </h2>
          <p class="card-excerpt" v-html="highlight(excerpt(post.content))"></p>
          <div v-if="post.tags?.length" class="taglist">
            <span v-for="tag in post.tags" :key="tag.id" class="tag-pill">
              {{ tag.name }}
            </span>
          </div>
          <div class="card-meta">
            <span>{{ formatDate(post.published_at) }}</span>
            <span>•</span>
            <span>{{ post.user?.name || "Redakcia" }}</span>
            <span>•</span>
            <span>{{ readTime(post.content) }}</span>
          </div>
        </div>
        <div class="card-accent"></div>
      </article>
    </div>

    <div v-if="data" class="pager">
      <button class="ghost" @click="prevPage" :disabled="loading || page <= 1">
        Predošlá
      </button>
      <div class="muted">Strana {{ data.current_page }} z {{ data.last_page }}</div>
      <button
        class="ghost"
        @click="nextPage"
        :disabled="loading || page >= data.last_page"
      >
        Ďalšia
      </button>
    </div>
  </section>
</template>

<style scoped>
.learn {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: "Georgia", "Times New Roman", serif;
}

.hero {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, 0.7fr);
  gap: 20px;
  padding: 24px;
  border-radius: 20px;
  background: radial-gradient(
      circle at 20% 10%,
      rgba(56, 189, 248, 0.18),
      transparent 55%
    ),
    linear-gradient(120deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.7));
  border: 1px solid rgba(148, 163, 184, 0.25);
}

.kicker {
  text-transform: uppercase;
  letter-spacing: 0.25em;
  font-size: 12px;
  color: rgba(226, 232, 240, 0.7);
  margin: 0 0 10px;
}

.hero h1 {
  font-size: 34px;
  margin: 0 0 10px;
}

.lead {
  margin: 0;
  color: rgba(226, 232, 240, 0.85);
  font-size: 16px;
  line-height: 1.7;
}

.hero-card {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 18px;
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.75);
  border: 1px solid rgba(148, 163, 184, 0.2);
  gap: 6px;
  text-align: right;
}

.hero-label {
  font-size: 14px;
  color: #bae6fd;
}

.hero-meta {
  font-size: 12px;
  color: rgba(226, 232, 240, 0.7);
}

.grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.tag-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filters {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.search {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.search input {
  min-width: 240px;
  flex: 1;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.5);
  color: inherit;
}

.search .ghost {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.tag {
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.5);
  color: inherit;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 12px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.tag.active {
  border-color: rgba(56, 189, 248, 0.5);
  color: #bae6fd;
  background: rgba(56, 189, 248, 0.12);
}

.tag .count {
  font-size: 11px;
  color: rgba(226, 232, 240, 0.6);
}

.card {
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  background: rgba(15, 23, 42, 0.85);
  border: 1px solid rgba(148, 163, 184, 0.18);
  display: flex;
  flex-direction: column;
  min-height: 260px;
}

.card-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.card-media {
  width: 100%;
  height: 140px;
  background-size: cover;
  background-position: center;
  filter: saturate(0.9);
}

.featured-hero {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
  gap: 20px;
  border-radius: 20px;
  background: rgba(15, 23, 42, 0.9);
  border: 1px solid rgba(148, 163, 184, 0.2);
  overflow: hidden;
}

.featured-media {
  min-height: 260px;
  background-size: cover;
  background-position: center;
}

.featured-content {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.featured-title {
  margin: 0;
  font-size: 26px;
  line-height: 1.35;
}

.featured-title a {
  color: inherit;
  text-decoration: none;
}

.featured-title a:hover {
  color: #bae6fd;
}

.cta {
  margin-top: auto;
  text-decoration: none;
  color: #bae6fd;
  font-size: 14px;
}

.card-kicker {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: rgba(226, 232, 240, 0.6);
  margin: 0;
}

.card-title {
  font-size: 20px;
  margin: 0;
  line-height: 1.4;
}

.card-title a {
  color: inherit;
  text-decoration: none;
}

.card-title a:hover {
  color: #bae6fd;
}

.card-excerpt {
  margin: 0;
  color: rgba(226, 232, 240, 0.78);
  line-height: 1.6;
  font-size: 14px;
}

.taglist {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.tag-pill {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  color: rgba(226, 232, 240, 0.75);
}

.card-meta {
  margin-top: auto;
  font-size: 12px;
  color: rgba(226, 232, 240, 0.6);
  display: flex;
  gap: 8px;
  align-items: center;
}

.card-accent {
  position: absolute;
  inset: auto 0 0 0;
  height: 4px;
  background: linear-gradient(90deg, #38bdf8, transparent);
  opacity: 0.7;
}

.error {
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid rgba(248, 113, 113, 0.5);
  background: rgba(248, 113, 113, 0.15);
  color: #fecaca;
}

.muted {
  color: rgba(226, 232, 240, 0.7);
  font-size: 14px;
}

.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.ghost {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.ghost:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

mark {
  background: rgba(56, 189, 248, 0.3);
  color: inherit;
  padding: 0 2px;
  border-radius: 3px;
}

@media (max-width: 900px) {
  .hero {
    grid-template-columns: 1fr;
  }

  .featured-hero {
    grid-template-columns: 1fr;
  }
}
</style>
