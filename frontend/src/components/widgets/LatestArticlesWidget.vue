<template>
  <section class="card panel">
    <div class="panelTitle">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-4/5"></div>
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-3/4"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa načítať</div>
      <div class="stateText">{{ error }}</div>
    </div>

    <div v-else-if="latestArticles.length === 0" class="state">
      <div class="stateTitle">Zatiaľ žiadne články</div>
    </div>

    <ul v-else class="articleList">
      <li v-for="post in latestArticles" :key="post.id">
        <router-link class="articleLink" :to="`/learn/${post.slug}`">
          {{ post.title }}
        </router-link>
      </li>
    </ul>
  </section>
</template>

<script>
import { ref, onMounted } from 'vue'
import { blogPosts } from '@/services/blogPosts'

export default {
  name: 'LatestArticlesWidget',
  props: {
    title: {
      type: String,
      default: 'Najnovšie články'
    }
  },
  setup() {
    const latestArticles = ref([])
    const loading = ref(true)
    const error = ref(null)

    const fetchLatestArticles = async () => {
      loading.value = true
      error.value = null
      latestArticles.value = []

      try {
        const data = await blogPosts.listPublic({ page: 1 })
        const rows = Array.isArray(data?.data) ? data.data : []
        latestArticles.value = rows.slice(0, 3)
      } catch (err) {
        error.value =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať články.'
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      fetchLatestArticles()
    })

    return {
      latestArticles,
      loading,
      error
    }
  }
}
</script>

<style scoped>
.card {
  position: relative;
  border: 1px solid var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.55);
  border-radius: 1.5rem;
  padding: 1.25rem;
  overflow: hidden;
}

.panel {
  display: grid;
  gap: 0.75rem;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.95rem;
}

.panelLoading {
  display: grid;
  gap: 0.5rem;
}

.articleList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.65rem;
}

.articleLink {
  color: var(--color-surface);
  text-decoration: none;
  font-weight: 600;
  line-height: 1.4;
}

.articleLink:hover {
  color: var(--color-primary);
}

.stateTitle {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--color-surface);
}

.stateText {
  margin-top: 0.35rem;
  color: var(--color-text-secondary);
}

.stateError .stateTitle,
.stateError .stateText {
  color: var(--color-danger);
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0.75rem;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-4 { height: 1rem; }
.w-4\/5 { width: 80%; }
.w-2\/3 { width: 66.666667%; }
.w-3\/4 { width: 75%; }
</style>
