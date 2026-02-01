<template>
  <div class="homeLayout">
    <section class="centerCol">
      <PostComposer
        v-if="auth?.isAuthed"
        @created="onPostCreated"
      />

      <FeedList ref="feed" />
    </section>

    <aside class="rightCol">
      <section class="card panel">
        <div class="panelTitle">Najbližšia udalosť</div>

        <div v-if="loading" class="panelLoading">
          <div class="skeleton h-4 w-2/3"></div>
          <div class="skeleton h-4 w-1/2"></div>
          <div class="skeleton h-8 w-full"></div>
        </div>

        <div v-else-if="error" class="state stateError">
          <div class="stateTitle">Nepodarilo sa načítať</div>
          <div class="stateText">{{ error }}</div>
          <button class="ghostbtn" @click="fetchNextEvent">Skúsiť znova</button>
        </div>

        <div v-else-if="!nextEvent" class="state">
          <div class="stateTitle">Zatiaľ žiadna udalosť</div>
          <div class="stateText">Pozri kalendár alebo udalosti.</div>
          <div class="panelActions">
            <router-link class="ghostbtn" to="/events">Všetky udalosti</router-link>
          </div>
        </div>

        <div v-else class="eventCard">
          <div class="eventTitle">{{ nextEvent.title }}</div>
          <div class="eventMeta">{{ formatDateTime(nextEvent.max_at) }}</div>
          <router-link class="actionbtn" :to="`/events/${nextEvent.id}`">
            Detail
          </router-link>
        </div>
      </section>

      <section class="card panel">
        <div class="panelTitle">Najnovšie články</div>

        <div v-if="articlesLoading" class="panelLoading">
          <div class="skeleton h-4 w-4/5"></div>
          <div class="skeleton h-4 w-2/3"></div>
          <div class="skeleton h-4 w-3/4"></div>
        </div>

        <div v-else-if="articlesError" class="state stateError">
          <div class="stateTitle">Nepodarilo sa načítať</div>
          <div class="stateText">{{ articlesError }}</div>
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

      <section v-if="nasaEnabled" class="card panel">
        <div class="panelTitle">NASA – Obrázok dňa</div>

        <div v-if="nasaLoading" class="panelLoading">
          <div class="skeleton nasaThumb"></div>
          <div class="skeleton h-4 w-4/5"></div>
          <div class="skeleton h-4 w-2/3"></div>
        </div>

        <div v-else-if="!nasaItem || !nasaItem.available" class="state">
          <div class="stateText">Obrázok dňa je momentálne nedostupný</div>
        </div>

        <div v-else class="nasaCard">
          <a
            class="nasaImageLink"
            :href="nasaItem.link"
            target="_blank"
            rel="noopener noreferrer"
          >
            <div class="nasaImageWrap">
              <img
                :src="nasaItem.image_url"
                :alt="nasaItem.title"
                loading="lazy"
              />
            </div>
          </a>

          <div class="nasaTitle">{{ nasaItem.title }}</div>
          <div v-if="nasaItem.excerpt" class="nasaExcerpt">{{ nasaItem.excerpt }}</div>

          <div class="panelActions">
            <a
              class="ghostbtn"
              :href="nasaItem.link"
              target="_blank"
              rel="noopener noreferrer"
            >
              Zobraziť na NASA.gov
            </a>
          </div>
        </div>
      </section>
    </aside>
  </div>
</template>

<script>
import api from '../services/api'
import { useAuthStore } from '@/stores/auth'
import PostComposer from '@/components/PostComposer.vue'
import FeedList from '@/components/FeedList.vue'
import { blogPosts } from '@/services/blogPosts'

export default {
  name: 'HomeView',
  components: { PostComposer, FeedList },
  data() {
    return {
      auth: useAuthStore(),
      nextEvent: null,
      loading: true,
      error: null,
      latestArticles: [],
      articlesLoading: true,
      articlesError: null,

      nasaEnabled:
        import.meta.env.VITE_FEATURE_NASA_IOTD !== 'false' &&
        import.meta.env.VITE_FEATURE_NASA_IOTD !== '0',
      nasaItem: null,
      nasaLoading: false,
      nasaError: null,
    }
  },
  methods: {
    // 🔧 FIX: robustné parsovanie response, plus detekcia prázdneho objektu
    async fetchNextEvent() {
      this.loading = true
      this.error = null
      this.nextEvent = null

      try {
        const res = await api.get('/events/next')
        const payload = res?.data

        // podporíme viac tvarov odpovede:
        // 1) event priamo: { id, title, ... }
        // 2) wrapper: { data: { ... } }
        // 3) wrapper: { event: { ... } }
        const ev = payload?.data ?? payload?.event ?? payload

        // ak je ev null/undefined alebo prázdny objekt -> berieme ako "žiadna udalosť"
        const isEmptyObject =
          ev && typeof ev === 'object' && !Array.isArray(ev) && Object.keys(ev).length === 0

        // ak ti backend náhodou vracia [] alebo {data: []}, tiež berieme ako empty
        const isEmptyArray = Array.isArray(ev) && ev.length === 0

        if (!ev || isEmptyObject || isEmptyArray) {
          this.nextEvent = null
        } else if (Array.isArray(ev)) {
          // keby náhodou prišiel array, zoberieme prvý
          this.nextEvent = ev[0] ?? null
        } else {
          // základná kontrola: keď nemá title/id, nechceme renderovať "— — —"
          if (!ev.title || !ev.id) {
            this.nextEvent = null
          } else {
            this.nextEvent = ev
          }
        }
      } catch (err) {
        this.error =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať najbližšiu udalosť.'
      } finally {
        this.loading = false
      }
    },

    typeLabel(type) {
      const map = {
        meteors: 'Meteory',
        eclipse: 'Zatmenie',
        conjunction: 'Konjunkcia',
        comet: 'Kométa',
      }
      return map[type] || type
    },

    iconForType(type) {
      const map = {
        meteors: '✨',
        eclipse: '🌑',
        conjunction: '🪐',
        comet: '☄️',
      }
      return map[type] || '🔭'
    },

    formatDateTime(value) {
      if (!value) return '—'
      return value.replace('T', ' ').slice(0, 16)
    },

    onPostCreated(createdPost) {
      this.$refs.feed?.prepend?.(createdPost)
    },

    async fetchLatestArticles() {
      this.articlesLoading = true
      this.articlesError = null
      this.latestArticles = []

      try {
        const data = await blogPosts.listPublic({ page: 1 })
        const rows = Array.isArray(data?.data) ? data.data : []
        this.latestArticles = rows.slice(0, 3)
      } catch (err) {
        this.articlesError =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať články.'
      } finally {
        this.articlesLoading = false
      }
    },

    async fetchNasaIotd() {
      this.nasaLoading = true
      this.nasaError = null
      this.nasaItem = null

      try {
        const res = await api.get('/nasa/iotd')
        const payload = res?.data

        if (payload && payload.available) {
          this.nasaItem = payload
        } else {
          this.nasaItem = null
        }
      } catch (err) {
        this.nasaError =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať NASA Image of the Day.'
        this.nasaItem = null
      } finally {
        this.nasaLoading = false
      }
    },
  },
  created() {
    this.fetchNextEvent()
    this.fetchLatestArticles()

    if (this.nasaEnabled) {
      this.fetchNasaIotd()
    }
  },
}
</script>

<style scoped>
.homeLayout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 1.5rem;
  align-items: start;
}

.centerCol {
  display: grid;
  gap: 1.25rem;
  max-width: 680px;
  width: 100%;
  margin: 0 auto;
}

.rightCol {
  position: sticky;
  top: 1.25rem;
  align-self: start;
  display: grid;
  gap: 1rem;
}

@media (max-width: 1100px) {
  .homeLayout {
    grid-template-columns: minmax(0, 1fr);
  }

  .rightCol {
    display: none;
  }
}

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

.eventCard {
  display: grid;
  gap: 0.6rem;
}

.eventTitle {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--color-surface);
}

.eventMeta {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.panelActions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
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

.actionbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
}
.actionbtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.28);
}

.ghostbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
}
.ghostbtn:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
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

.nasaCard {
  display: grid;
  gap: 0.6rem;
}

.nasaThumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: 1rem;
}

.nasaImageWrap {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: 1rem;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.nasaImageWrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.nasaTitle {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.25;
}

.nasaExcerpt {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  display: -webkit-box;
  line-clamp: 3;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
